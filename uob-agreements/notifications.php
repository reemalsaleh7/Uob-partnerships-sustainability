<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
  header("Location: login.php?to=notifications.php");
  exit;
}

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$pageTitle = $isArabic ? "منصة الطلبات والتحديثات" : "Requests & Updates Platform";
$hidePageHeader = true;
$mainContainer = false;
markAllNotificationsAsRead();
require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');
$userEmail = strtolower(trim($_SESSION['user_email'] ?? ''));

$notificationsFile = __DIR__ . '/data/notifications.csv';
$requestsFile = __DIR__ . '/data/initiative_requests.csv';
$aiCacheFile = __DIR__ . '/data/ai_suggestions_cache.json';
$hiddenSuggestionsFile = __DIR__ . '/data/hidden_ai_suggestions.json';
$userPrefsFile = __DIR__ . '/data/user_preferences.json';
$savedSuggestionsFile = __DIR__ . '/data/saved_suggestions.json';

$openaiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

function staffReadCsv($file) {
  if (!file_exists($file)) return [];
  $rows = [];
  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);
    if (!$header) {
      fclose($fp);
      return [];
    }
    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($header), '');
      $rows[] = array_combine($header, $row);
    }
    fclose($fp);
  }
  return $rows;
}

function staffWriteJson($file, $data) {
  $dir = dirname($file);
  if (!is_dir($dir)) mkdir($dir, 0777, true);
  file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function staffReadJson($file) {
  if (!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

function loadUserPrefs($file) {
  if (!file_exists($file)) return [];
  $data = json_decode(file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

function saveUserPrefs($file, $data) {
  staffWriteJson($file, $data);
}

function updateUserPreference($file, $email, $type, $action) {
  $email = strtolower(trim($email));
  $type = trim($type);

  if ($email === '' || $type === '') return;

  $prefs = loadUserPrefs($file);

  if (!isset($prefs[$email])) {
    $prefs[$email] = [
      'liked_types' => [],
      'rejected_types' => []
    ];
  }

  if ($action === 'like') {
    $prefs[$email]['liked_types'][] = $type;
    $prefs[$email]['rejected_types'] = array_values(array_diff($prefs[$email]['rejected_types'], [$type]));
  }

  if ($action === 'reject') {
    $prefs[$email]['rejected_types'][] = $type;
    $prefs[$email]['liked_types'] = array_values(array_diff($prefs[$email]['liked_types'], [$type]));
  }

  $prefs[$email]['liked_types'] = array_values(array_unique($prefs[$email]['liked_types']));
  $prefs[$email]['rejected_types'] = array_values(array_unique($prefs[$email]['rejected_types']));

  saveUserPrefs($file, $prefs);
}

function suggestionKey($email, $title) {
  return md5(strtolower(trim($email)) . '|' . trim($title));
}

function staffStatusLabel($status, $isArabic) {
  $status = trim($status);
  if ($status === 'approved' || $status === 'معتمد') return $isArabic ? 'موافق عليه' : 'Approved';
  if ($status === 'rejected' || $status === 'مرفوض') return $isArabic ? 'مرفوض' : 'Rejected';
  return $isArabic ? 'قيد المراجعة' : 'Pending';
}

function staffStatusClass($status) {
  $status = trim($status);
  if ($status === 'approved' || $status === 'معتمد') return 'good';
  if ($status === 'rejected' || $status === 'مرفوض') return 'bad';
  return 'wait';
}

function staffTypeInfo($type) {
  $type = trim($type ?: 'info');
  if ($type === 'success') return ['✅', 'good'];
  if ($type === 'danger') return ['❌', 'bad'];
  if ($type === 'suggestion') return ['💡', 'idea'];
  return ['🔔', 'info'];
}

function recommendationLevel($type) {
  $type = strtolower(trim($type));

  if (str_contains($type, 'innovation') || str_contains($type, 'partnership')) {
    return ['🔥 Highly Recommended', 'high'];
  }

  if (str_contains($type, 'sustainability') || str_contains($type, 'community')) {
    return ['⭐ Good Fit', 'medium'];
  }

  return ['⚠ Needs Review', 'low'];
}

function appendSuggestionRequest($file, $data) {
  $headers = [
    'request_id',
    'email',
    'submitted_by',
    'college',
    'department',
    'initiative_title',
    'initiative_type',
    'description',
    'target_group',
    'expected_outputs',
    'sdgs',
    'status',
    'admin_notes',
    'created_at'
  ];

  $exists = file_exists($file);
  $fp = fopen($file, $exists ? 'a' : 'w');

  if (!$exists) fputcsv($fp, $headers);

  $row = [];
  foreach ($headers as $h) {
    $row[] = $data[$h] ?? '';
  }

  fputcsv($fp, $row);
  fclose($fp);
}

function getExistingInitiativesSummary() {
  $items = [];
  $sdgCounts = [];
  $typeCounts = [];

  if (function_exists('loadAllInitiatives')) {
    $all = loadAllInitiatives(false);

    foreach ($all as $it) {
      $sdgText = trim(($it['sdgs'] ?? '') . ' ' . ($it['sdg_primary'] ?? '') . ' ' . ($it['sdg_secondary'] ?? ''));
      preg_match_all('/SDG\s*\d+/i', $sdgText, $matches);

      foreach ($matches[0] ?? [] as $sdg) {
        $key = strtoupper(str_replace(' ', '', $sdg));
        $sdgCounts[$key] = ($sdgCounts[$key] ?? 0) + 1;
      }

      $type = trim($it['type'] ?? '');
      if ($type !== '') {
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
      }
    }

    foreach (array_slice($all, 0, 80) as $it) {
      $items[] = [
        'title' => $it['title'] ?? '',
        'description' => $it['description'] ?? '',
        'target_group' => $it['target_group'] ?? '',
        'type' => $it['type'] ?? '',
        'sdg' => $it['sdgs'] ?? ($it['sdg_primary'] ?? ''),
        'outputs' => $it['outputs'] ?? ''
      ];
    }
  }

  return [
    'recent_initiatives' => $items,
    'sdg_coverage_counts' => $sdgCounts,
    'type_coverage_counts' => $typeCounts
  ];
}

function fallbackSuggestions($college, $department) {
  $target = trim($department ?: $college ?: 'القسم');

  return [
    [
      'title_ar' => 'مختبر ابتكار تطبيقي للقسم',
      'title_en' => 'Department Applied Innovation Lab',
      'summary_ar' => "إنشاء مساحة تطبيقية قصيرة المدى داخل $target لتطوير حلول بحثية أو عملية تخدم الجامعة والمجتمع.",
      'summary_en' => "Create a short applied innovation lab for $target to develop practical or research-based solutions.",
      'target_group_ar' => 'أعضاء هيئة التدريس، الموظفون، والطلبة المشاركون',
      'target_group_en' => 'Faculty, staff, and selected students',
      'implementation_ar' => 'تحديد تحدي واقعي، تشكيل فرق، تنفيذ نموذج أولي، ثم عرض النتائج.',
      'implementation_en' => 'Define a real challenge, form teams, build prototypes, then present outcomes.',
      'expected_outputs_ar' => 'نماذج أولية، تقرير أثر، عرض نهائي، وتوصيات للتطبيق.',
      'expected_outputs_en' => 'Prototypes, impact report, final presentation, and recommendations.',
      'sdgs' => 'SDG 4, SDG 9, SDG 17',
      'why_suitable_ar' => "يناسب $target لأنه يحول المعرفة الأكاديمية إلى حلول قابلة للقياس.",
      'why_suitable_en' => "Suitable because it turns academic knowledge into measurable solutions.",
      'initiative_type' => 'Innovation / Partnership',
      'duration' => '1 week'
    ],
    [
      'title_ar' => 'عيادة استشارية جامعية مصغرة',
      'title_en' => 'Mini University Consultancy Clinic',
      'summary_ar' => "مبادرة يقدم فيها $target جلسات استشارية قصيرة داخل الجامعة أو للمجتمع.",
      'summary_en' => "$target provides short consultancy sessions for university units or community partners.",
      'target_group_ar' => 'وحدات الجامعة، الجهات المجتمعية، والموظفون',
      'target_group_en' => 'University units, community entities, and staff',
      'implementation_ar' => 'فتح نموذج طلب استشارة، جدولة جلسات شهرية، توثيق الحالات، وقياس الرضا.',
      'implementation_en' => 'Open a request form, schedule monthly sessions, document cases, and measure satisfaction.',
      'expected_outputs_ar' => 'عدد الاستشارات، تقارير مختصرة، توصيات، ومؤشر رضا.',
      'expected_outputs_en' => 'Consultations, brief reports, recommendations, and satisfaction indicator.',
      'sdgs' => 'SDG 4, SDG 11, SDG 17',
      'why_suitable_ar' => 'تعزز دور القسم في خدمة المجتمع وتوثق أثره.',
      'why_suitable_en' => 'Strengthens the department’s community role and documents impact.',
      'initiative_type' => 'Community / Consultancy',
      'duration' => '3 days'
    ],
    [
      'title_ar' => 'تحدي التحول الأخضر داخل المكتب',
      'title_en' => 'Green Office Transformation Challenge',
      'summary_ar' => "تحدي داخلي يقوده $target لتحسين ممارسات الاستدامة في بيئة العمل.",
      'summary_en' => "$target leads an internal challenge to improve workplace sustainability practices.",
      'target_group_ar' => 'الموظفون وأعضاء هيئة التدريس داخل الكلية أو القسم',
      'target_group_en' => 'Staff and faculty within the college or department',
      'implementation_ar' => 'قياس الوضع الحالي، تقليل الورق والطاقة، نشر توعية، ثم قياس التحسن.',
      'implementation_en' => 'Assess current practices, reduce paper and energy use, run awareness actions, then measure improvement.',
      'expected_outputs_ar' => 'نسبة تقليل الورق، مبادرات توعية، تقرير استدامة مصغر، وخطة تحسين.',
      'expected_outputs_en' => 'Paper reduction percentage, awareness actions, mini sustainability report, and improvement plan.',
      'sdgs' => 'SDG 12, SDG 13',
      'why_suitable_ar' => 'فكرة سهلة التنفيذ وتظهر أثر القسم في الاستدامة بشكل مباشر.',
      'why_suitable_en' => 'Easy to implement and directly shows sustainability impact.',
      'initiative_type' => 'Sustainability',
      'duration' => '1 month'
    ]
  ];
}

function generateAiSuggestions($apiKey, $college, $department, $existing, $userEmail) {
  $target = trim($department ?: $college);

  if ($target === '') return [];

  if (!$apiKey) {
    return fallbackSuggestions($college, $department);
  }

  $prefs = loadUserPrefs(__DIR__ . '/data/user_preferences.json');

  $liked = implode(', ', $prefs[$userEmail]['liked_types'] ?? []);
  $rejected = implode(', ', $prefs[$userEmail]['rejected_types'] ?? []);
  $existingText = json_encode($existing, JSON_UNESCAPED_UNICODE);

  $prompt = "
You are a senior university innovation and strategy consultant working for the University of Bahrain.

Your task is to generate HIGH-QUALITY, REALISTIC, NON-GENERIC initiatives that can be implemented within a university environment and contribute to institutional impact, sustainability rankings, partnerships, and community engagement.

Context:
University: University of Bahrain
College: $college
Department: $department

User Preferences:
Preferred Types: $liked
Avoided Types: $rejected

Existing Initiatives (do NOT repeat or imitate):
$existingText

CRITICAL REQUIREMENTS:

You must generate initiatives that are:
- Strategic (not random activities)
- Innovative (not typical university events)
- Realistic and implementable
- Aligned with university goals, rankings (THE, QS, GreenMetric), and SDGs
- Suitable for multiple stakeholders (students, faculty, staff, community, industry)
- Capable of producing measurable impact

STRICTLY FORBIDDEN IDEAS:
- Open days
- General workshops
- Awareness campaigns
- Basic competitions
- Career talks
- Generic training sessions
- Common student activities done in any university
- Digital apps/platform ideas

These will be considered LOW QUALITY and must NOT be generated.

FOCUS ON:
- Applied initiatives with real execution (pilot programs, labs, field work, partnerships)
- Initiatives that connect the university with:
  • Industry
  • Government
  • Community
  • International partners
- Initiatives that solve REAL problems or gaps
- Initiatives that utilize university strengths (labs, expertise, research)
- Initiatives that can be documented in sustainability or ranking reports
- Initiatives that produce tangible outputs (not just attendance)

ALSO:
- Do NOT focus on only one group (like freshmen or graduates)
- Each initiative must benefit broader or multiple groups
- Avoid repetitive formats across ideas

QUALITY EXPECTATION:
Each idea should feel like:
\"This is something a Vice President would approve and fund\"

OUTPUT REQUIREMENTS:

Return ONLY JSON (no explanation, no markdown)

Each initiative must include:

- title_ar (professional, specific title)
- title_en
- summary_ar (clear, impactful description — NOT generic)
- summary_en
- target_group_ar (multiple relevant groups)
- target_group_en
- implementation_ar (practical, realistic execution steps)
- implementation_en
- expected_outputs_ar (MEASURABLE outputs: numbers, reports, prototypes, partnerships, etc.)
- expected_outputs_en
- sdgs (e.g., SDG 4, SDG 8, SDG 9)
- why_suitable_ar (WHY this fills a gap and suits the department/university)
- why_suitable_en
- initiative_type (choose meaningful types like: Applied Impact / Industry Collaboration / Research Translation / Sustainability / Community Impact)
- duration (realistic timeframe)

IMPORTANT:
Avoid vague language like:
\"improve skills\" or \"enhance awareness\"

Instead use:
- measurable outcomes
- real actions
- clear value

Return 4 initiatives only.
";

  $ch = curl_init();

  curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_TIMEOUT => 35,
    CURLOPT_HTTPHEADER => [
      "Authorization: Bearer " . $apiKey,
      "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS => json_encode([
      "model" => "gpt-4o-mini",
      "messages" => [
        ["role" => "user", "content" => $prompt]
      ],
      "temperature" => 0.9
    ], JSON_UNESCAPED_UNICODE)
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $data = json_decode($response, true);
  $text = trim($data['choices'][0]['message']['content'] ?? '');

  $text = preg_replace('/^```json\s*/', '', $text);
  $text = preg_replace('/^```\s*/', '', $text);
  $text = preg_replace('/\s*```$/', '', $text);

  $decoded = json_decode(trim($text), true);

  return is_array($decoded) && !empty($decoded)
    ? $decoded
    : fallbackSuggestions($college, $department);
}

$allNotifications = staffReadCsv($notificationsFile);
$allRequests = staffReadCsv($requestsFile);

$myRequests = array_values(array_filter($allRequests, function($r) use ($userEmail) {
  return strtolower(trim($r['email'] ?? '')) === $userEmail
      || strtolower(trim($r['submitted_by'] ?? '')) === $userEmail;
}));

usort($myRequests, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));

$userCollege = '';
$userDepartment = '';

if (!empty($myRequests)) {
  $userCollege = trim($myRequests[0]['college'] ?? '');
  $userDepartment = trim($myRequests[0]['department'] ?? '');
}

$notifications = array_values(array_filter($allNotifications, function($n) use ($userEmail, $userCollege, $userDepartment) {
  $targetType = trim($n['target_type'] ?? '');
  $targetEmail = strtolower(trim($n['target_email'] ?? ''));
  $targetCollege = trim($n['target_college'] ?? '');
  $targetDepartment = trim($n['target_department'] ?? '');

  return $targetType === 'all'
    || ($targetType === 'email' && $targetEmail === $userEmail)
    || ($targetType === 'college' && $userCollege !== '' && $targetCollege === $userCollege)
    || ($targetType === 'department' && $userDepartment !== '' && $targetDepartment === $userDepartment);
}));

usort($notifications, fn($a, $b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));

$totalNotifications = count($notifications);

$pendingCount = 0;
$approvedCount = 0;
$requestsRejectedCount = 0;

foreach ($myRequests as $r) {
  $st = trim($r['status'] ?? 'pending');

  if ($st === 'pending' || $st === 'قيد المراجعة' || $st === '') {
    $pendingCount++;
  } elseif ($st === 'approved' || $st === 'معتمد') {
    $approvedCount++;
  } elseif ($st === 'rejected' || $st === 'مرفوض') {
    $requestsRejectedCount++;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $type = $_POST['initiative_type'] ?? '';
  $title = $_POST['initiative_title'] ?? '';

  $hidden = staffReadJson($hiddenSuggestionsFile);

  if ($action === 'reject_suggestion') {
    updateUserPreference($userPrefsFile, $userEmail, $type, 'reject');

    $hidden[] = suggestionKey($userEmail, $title);
    staffWriteJson($hiddenSuggestionsFile, array_values(array_unique($hidden)));

    header("Location: notifications.php?tab=suggestions&lang=" . urlencode($lang));
    exit;
  }

 if ($action === 'like_suggestion') {
  updateUserPreference($userPrefsFile, $userEmail, $type, 'like');

  $saved = staffReadJson($savedSuggestionsFile);

  $alreadySaved = false;
  foreach ($saved as $item) {
    if (
      strtolower(trim($item['email'] ?? '')) === $userEmail &&
      trim($item['title'] ?? '') === trim($title)
    ) {
      $alreadySaved = true;
      break;
    }
  }

  if (!$alreadySaved) {
    $saved[] = [
      'email' => $userEmail,
      'title' => $title,
      'type' => $type,
      'description' => $_POST['description'] ?? '',
      'target_group' => $_POST['target_group'] ?? '',
      'expected_outputs' => $_POST['expected_outputs'] ?? '',
      'sdgs' => $_POST['sdgs'] ?? '',
      'created_at' => date('Y-m-d H:i:s')
    ];

    staffWriteJson($savedSuggestionsFile, $saved);
  }

  $hidden[] = suggestionKey($userEmail, $title);
  staffWriteJson($hiddenSuggestionsFile, array_values(array_unique($hidden)));

  header("Location: notifications.php?tab=suggestions&lang=" . urlencode($lang));
  exit;
}

if ($action === 'delete_saved_suggestion') {
  $saved = staffReadJson($savedSuggestionsFile);

  $saved = array_values(array_filter($saved, function($item) use ($userEmail, $title) {
    return !(
      strtolower(trim($item['email'] ?? '')) === $userEmail &&
      trim($item['title'] ?? '') === trim($title)
    );
  }));

  staffWriteJson($savedSuggestionsFile, $saved);

  header("Location: notifications.php?tab=suggestions&lang=" . urlencode($lang));
  exit;
}



  if ($action === 'request_ai_suggestion') {
    updateUserPreference($userPrefsFile, $userEmail, $type, 'like');

    $hidden[] = suggestionKey($userEmail, $title);
    staffWriteJson($hiddenSuggestionsFile, array_values(array_unique($hidden)));

    appendSuggestionRequest($requestsFile, [
      'request_id' => 'REQ-' . date('YmdHis'),
      'email' => $userEmail,
      'submitted_by' => $userEmail,
      'college' => $_POST['college'] ?? '',
      'department' => $_POST['department'] ?? '',
      'initiative_title' => $_POST['initiative_title'] ?? '',
      'initiative_type' => $_POST['initiative_type'] ?? '',
      'description' => $_POST['description'] ?? '',
      'target_group' => $_POST['target_group'] ?? '',
      'expected_outputs' => $_POST['expected_outputs'] ?? '',
      'sdgs' => $_POST['sdgs'] ?? '',
      'status' => 'قيد المراجعة',
      'admin_notes' => '',
      'created_at' => date('Y-m-d H:i:s')
    ]);

    header("Location: notifications.php?tab=requests&lang=" . urlencode($lang));
    exit;
  }
}

$suggestions = [];

if ($userCollege !== '' || $userDepartment !== '') {
  $cache = staffReadJson($aiCacheFile);
  $cacheVersion = 'uob-strategic-v1';
  $cacheKey = md5($cacheVersion . '|' . $userEmail . '|' . $userCollege . '|' . $userDepartment);
  $now = time();

  if (isset($cache[$cacheKey]['created_at']) && ($now - (int)$cache[$cacheKey]['created_at']) < 86400) {
    $suggestions = $cache[$cacheKey]['suggestions'] ?? [];
  } else {
    $existing = getExistingInitiativesSummary();
    $suggestions = generateAiSuggestions($openaiKey, $userCollege, $userDepartment, $existing, $userEmail);
    $cache[$cacheKey] = [
      'created_at' => $now,
      'suggestions' => $suggestions
    ];
    staffWriteJson($aiCacheFile, $cache);
  }
}

$prefs = loadUserPrefs($userPrefsFile);
$hidden = staffReadJson($hiddenSuggestionsFile);

$suggestions = array_values(array_filter($suggestions, function($s) use ($hidden, $userEmail, $prefs) {
  $title = $s['title_ar'] ?? ($s['title_en'] ?? '');
  $type = $s['initiative_type'] ?? '';

  if (in_array(suggestionKey($userEmail, $title), $hidden, true)) return false;

  if (in_array($type, $prefs[$userEmail]['rejected_types'] ?? [])) return false;

  return true;
}));

if (count($suggestions) < 2 && ($userCollege !== '' || $userDepartment !== '')) {
  $existing = getExistingInitiativesSummary();
  $newSuggestions = generateAiSuggestions($openaiKey, $userCollege, $userDepartment, $existing, $userEmail);
  $suggestions = array_merge($suggestions, $newSuggestions);
}

$activeTab = $_GET['tab'] ?? 'suggestions';

if (!in_array($activeTab, ['notifications', 'requests', 'suggestions'], true)) {
  $activeTab = 'suggestions';
}

$prefs = loadUserPrefs($userPrefsFile);

$savedSuggestions = staffReadJson($savedSuggestionsFile);

$mySavedSuggestions = array_values(array_filter($savedSuggestions, function($s) use ($userEmail) {
  return strtolower(trim($s['email'] ?? '')) === $userEmail;
}));

$likedCount = count($prefs[$userEmail]['liked_types'] ?? []);
$rejectedCount = count($prefs[$userEmail]['rejected_types'] ?? []);
$savedCount = count($mySavedSuggestions);
$approvedRequestsCount = $approvedCount ?? 0;

$mostPreferredType = $isArabic ? 'غير محدد' : 'Not specified';

if (!empty($prefs[$userEmail]['liked_types'])) {
  $typeCounts = array_count_values($prefs[$userEmail]['liked_types']);
  arsort($typeCounts);
  $mostPreferredType = array_key_first($typeCounts);
}
?>

<style>
.staff-hero {
  background: radial-gradient(850px 280px at 12% 5%, rgba(201,162,39,.28), transparent 60%),
              linear-gradient(135deg, #0b1f3a 0%, #102a4c 55%, #113c63 100%);
  color: #fff;
  padding: 56px 20px 92px;
}

.staff-hero-inner {
  max-width: 1180px;
  margin: auto;
  display: grid;
  grid-template-columns: 1.1fr .9fr;
  gap: 24px;
  align-items: center;
}

.staff-hero h1 {
  font-size: clamp(34px, 4vw, 54px);
  font-weight: 950;
  margin: 0;
}

.staff-hero p {
  margin-top: 14px;
  color: rgba(255,255,255,.86);
  font-weight: 800;
  line-height: 1.9;
}

.staff-stats {
  display: grid;
  grid-template-columns: repeat(2, minmax(0,1fr));
  gap: 12px;
}

.staff-stat {
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.16);
  border-radius: 22px;
  padding: 18px;
  backdrop-filter: blur(10px);
}

.staff-stat span {
  display: block;
  color: rgba(255,255,255,.72);
  font-size: 13px;
  font-weight: 850;
}

.staff-stat strong {
  display: block;
  font-size: 30px;
  font-weight: 950;
  margin-top: 4px;
}

.staff-shell {
  max-width: 1180px;
  margin: -50px auto 50px;
  padding: 0 20px;
  position: relative;
  z-index: 5;
}

.staff-panel {
  background: #fff;
  border: 1px solid rgba(230,235,242,.96);
  border-radius: 28px;
  box-shadow: 0 24px 60px rgba(2,8,23,.12);
  overflow: hidden;
}

.staff-tabs {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding: 18px;
  background: linear-gradient(180deg, #fff, #f8fbff);
  border-bottom: 1px solid #e8eef6;
}

.staff-tab {
  border: 1px solid #dbe6f3;
  background: #fff;
  color: #0b1f3a;
  border-radius: 16px;
  min-height: 46px;
  padding: 10px 16px;
  font-weight: 950;
  cursor: pointer;
}

.staff-tab.active {
  background: #0b1f3a;
  color: #fff;
  border-color: #0b1f3a;
}

.staff-content {
  padding: 20px;
}

.staff-pane {
  display: none;
}

.staff-pane.active {
  display: block;
}

.staff-section-title {
  color: #0b1f3a;
  font-weight: 950;
  font-size: 24px;
  margin: 0 0 16px;
}

.staff-card {
  border: 1px solid #e8eef6;
  background: #fbfdff;
  border-radius: 20px;
  padding: 18px;
  margin-bottom: 14px;
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 14px;
  align-items: start;
}

.staff-icon,
.staff-suggestion-icon {
  width: 48px;
  height: 48px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #eef4ff;
  font-size: 22px;
}

.staff-title {
  color: #0b1f3a;
  font-size: 18px;
  font-weight: 950;
  margin-bottom: 6px;
}

.staff-msg {
  color: #334155;
  font-weight: 800;
  line-height: 1.8;
}

.staff-date {
  color: #64748b;
  font-size: 12px;
  font-weight: 850;
  white-space: nowrap;
}

.staff-pill {
  display: inline-flex;
  margin-top: 8px;
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 950;
}

.good {
  background: #dcfce7;
  color: #166534;
}

.bad {
  background: #fee2e2;
  color: #991b1b;
}

.wait {
  background: #fef9c3;
  color: #92400e;
}

.idea {
  background: #fff7ed;
  color: #9a3412;
}

.info {
  background: #eef4ff;
  color: #0b1f3a;
}

.score-high {
  background: #dcfce7;
  color: #166534;
}

.score-medium {
  background: #eef4ff;
  color: #0b1f3a;
}

.score-low {
  background: #fef9c3;
  color: #92400e;
}

.staff-empty {
  padding: 34px;
  text-align: center;
  color: #64748b;
  font-weight: 950;
  background: #fbfdff;
  border: 1px dashed #dbe6f3;
  border-radius: 22px;
}

.staff-table-wrap {
  overflow: auto;
  border: 1px solid #e8eef6;
  border-radius: 20px;
}

.staff-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  min-width: 850px;
}

.staff-table th {
  background: #f2f5fb;
  color: #0b1f3a;
  font-weight: 950;
  padding: 14px;
  border-bottom: 1px solid #e8eef6;
  white-space: nowrap;
}

.staff-table td {
  padding: 14px;
  border-bottom: 1px solid #eef2f7;
  font-weight: 800;
  color: #334155;
  vertical-align: top;
}

.staff-table tr:hover {
  background: #fbfdff;
}

.staff-mini {
  color: #64748b;
  font-size: 12px;
  font-weight: 850;
  margin-top: 4px;
}

.staff-profile-box {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 18px;
}

.staff-profile-chip {
  background: #eef4ff;
  color: #0b1f3a;
  border: 1px solid #dbe6f3;
  border-radius: 999px;
  padding: 8px 14px;
  font-weight: 950;
}

.staff-suggestions {
  display: grid;
  grid-template-columns: repeat(3, minmax(0,1fr));
  gap: 16px;
}

.staff-suggestion {
  border: 1px solid #e8eef6;
  border-radius: 22px;
  background: #fff;
  padding: 20px;
  box-shadow: 0 12px 28px rgba(2,8,23,.06);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.staff-suggestion h3 {
  color: #0b1f3a;
  font-weight: 950;
  font-size: 19px;
  margin: 0;
}

.staff-suggestion p {
  color: #334155;
  font-weight: 800;
  line-height: 1.8;
  margin: 0;
}

.staff-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: auto;
}

.staff-outline-btn {
  border: 1px solid #dbe6f3;
  background: #fff;
  color: #0b1f3a;
  border-radius: 14px;
  padding: 9px 14px;
  font-weight: 950;
  cursor: pointer;
}

.staff-submit-btn {
  border: 0;
  background: #0b1f3a;
  color: #fff;
  border-radius: 14px;
  padding: 9px 14px;
  font-weight: 950;
  cursor: pointer;
}

.suggestion-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(2,8,23,.58);
  z-index: 9999;
  padding: 28px;
  overflow: auto;
}

.suggestion-modal.show {
  display: block;
}

.suggestion-modal-box {
  max-width: 780px;
  margin: 60px auto;
  background: #fff;
  border-radius: 26px;
  padding: 28px;
  box-shadow: 0 30px 90px rgba(2,8,23,.35);
  position: relative;
}

.suggestion-close {
  position: absolute;
  top: 16px;
  left: 16px;
  border: 0;
  background: #fee2e2;
  color: #991b1b;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  font-size: 24px;
  font-weight: 950;
  cursor: pointer;
}

.suggestion-modal-box h2 {
  color: #0b1f3a;
  font-weight: 950;
  margin: 0 0 12px;
}

.suggestion-modal-box p,
.suggestion-modal-box li {
  color: #334155;
  font-weight: 800;
  line-height: 1.9;
}

.suggestion-detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-top: 14px;
}

.suggestion-detail-box {
  background: #f8fbff;
  border: 1px solid #e8eef6;
  border-radius: 18px;
  padding: 14px;
}

.suggestion-detail-box b {
  color: #0b1f3a;
}

.modal-footer-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 18px;
}

@media(max-width: 992px) {
  .staff-hero-inner {
    grid-template-columns: 1fr;
  }

  .staff-suggestions {
    grid-template-columns: 1fr;
  }

  .suggestion-detail-grid {
    grid-template-columns: 1fr;
  }
}

@media(max-width: 768px) {
  .staff-stats {
    grid-template-columns: 1fr;
  }

  .staff-card {
    grid-template-columns: 1fr;
  }
}

.staff-dashboard {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}

.dash-card {
  background: linear-gradient(180deg, #ffffff, #f8fbff);
  border: 1px solid #e4eaf3;
  border-radius: 20px;
  padding: 16px;
  box-shadow: 0 10px 24px rgba(2,8,23,.06);
}

.dash-card span {
  display: block;
  color: #64748b;
  font-size: 12px;
  font-weight: 900;
  margin-bottom: 6px;
}

.dash-card strong {
  display: block;
  color: #0b1f3a;
  font-size: 28px;
  font-weight: 950;
}

.staff-actions form {
  display: inline-flex;
}

.staff-submit-btn,
.staff-outline-btn {
  min-height: 42px;
  border-radius: 999px;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 950;
  border: 1px solid transparent;
  cursor: pointer;
  transition: .2s ease;
}

.staff-submit-btn {
  background: linear-gradient(135deg, #0b1f3a, #123d68);
  color: #fff;
  box-shadow: 0 8px 18px rgba(11,31,58,.18);
}

.staff-submit-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 12px 24px rgba(11,31,58,.24);
}

.staff-outline-btn {
  background: #fff;
  color: #0b1f3a;
  border-color: #d8e2ef;
}

.staff-outline-btn:hover {
  background: #f8fbff;
  border-color: #c9a227;
  color: #0b1f3a;
}

.staff-save-btn {
  background: #fff8e1;
  color: #7a5a00;
  border-color: #ead38a;
}

.staff-save-btn:hover {
  background: #f7e8a6;
  border-color: #c9a227;
}

.staff-reject-btn {
  background: #fff;
  color: #991b1b;
  border-color: #f3c7c7;
}

.staff-reject-btn:hover {
  background: #fee2e2;
  color: #991b1b;
}

@media(max-width: 992px) {
  .staff-dashboard {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media(max-width: 600px) {
  .staff-dashboard {
    grid-template-columns: 1fr;
  }
}
</style>

<section class="staff-hero">
  <div class="staff-hero-inner">
    <div>
      <h1><?= $isArabic ? 'منصة الطلبات والتحديثات' : 'Requests & Updates Platform' ?></h1>
      <p>
        <?= $isArabic
          ? 'تابع إشعاراتك، حالة طلباتك، والاقتراحات الذكية المناسبة لكليتك وقسمك في مكان واحد.'
          : 'Track your notifications, request status, and smart suggestions for your college and department in one place.' ?>
      </p>
    </div>

    <div class="staff-stats">
      <div class="staff-stat">
        <span><?= $isArabic ? 'الإشعارات' : 'Notifications' ?></span>
        <strong><?= (int)$totalNotifications ?></strong>
      </div>

      <div class="staff-stat">
        <span><?= $isArabic ? 'طلباتي' : 'My Requests' ?></span>
        <strong><?= count($myRequests) ?></strong>
      </div>

      <div class="staff-stat">
        <span><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?></span>
        <strong><?= (int)$pendingCount ?></strong>
      </div>

      <div class="staff-stat">
        <span><?= $isArabic ? 'اقتراحات ذكية' : 'Smart Suggestions' ?></span>
        <strong><?= count($suggestions) ?></strong>
      </div>
    </div>
  </div>
</section>

<div class="staff-shell">
  <div class="staff-panel">
    <div class="staff-tabs">
      <button type="button" class="staff-tab <?= $activeTab === 'notifications' ? 'active' : '' ?>" data-tab="notifications">
        🔔 <?= $isArabic ? 'الإشعارات' : 'Notifications' ?>
      </button>

      <button type="button" class="staff-tab <?= $activeTab === 'requests' ? 'active' : '' ?>" data-tab="requests">
        📋 <?= $isArabic ? 'طلباتي' : 'My Requests' ?>
      </button>

      <button type="button" class="staff-tab <?= $activeTab === 'suggestions' ? 'active' : '' ?>" data-tab="suggestions">
        💡 <?= $isArabic ? 'اقتراحات ذكية' : 'Smart Suggestions' ?>
      </button>
    </div>

    <div class="staff-content">

      <div class="staff-pane <?= $activeTab === 'notifications' ? 'active' : '' ?>" id="notifications">
        <h2 class="staff-section-title"><?= $isArabic ? 'الإشعارات الخاصة بك' : 'Your Notifications' ?></h2>

        <?php if (!$notifications): ?>
          <div class="staff-empty">
            <?= $isArabic ? 'لا توجد إشعارات حالياً.' : 'No notifications yet.' ?>
          </div>
        <?php endif; ?>

        <?php foreach ($notifications as $n): ?>
          <?php [$icon, $cls] = staffTypeInfo($n['type'] ?? 'info'); ?>

          <div class="staff-card">
            <div class="staff-icon"><?= $icon ?></div>

            <div>
              <div class="staff-title"><?= h($n['title'] ?? '—') ?></div>
              <div class="staff-msg"><?= nl2br(h($n['message'] ?? '—')) ?></div>
              <span class="staff-pill <?= h($cls) ?>"><?= h($n['type'] ?? 'info') ?></span>
            </div>

            <div class="staff-date"><?= h($n['created_at'] ?? '') ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="staff-pane <?= $activeTab === 'requests' ? 'active' : '' ?>" id="requests">
        <h2 class="staff-section-title"><?= $isArabic ? 'طلباتي وحالة المراجعة' : 'My Requests & Review Status' ?></h2>

        <?php if (!$myRequests): ?>
          <div class="staff-empty">
            <?= $isArabic ? 'لم تقم بتقديم أي طلب حتى الآن.' : 'You have not submitted any requests yet.' ?>
          </div>
        <?php else: ?>
          <div class="staff-table-wrap">
            <table class="staff-table">
              <thead>
                <tr>
                  <th><?= $isArabic ? 'رقم الطلب' : 'Request ID' ?></th>
                  <th><?= $isArabic ? 'عنوان المبادرة' : 'Initiative Title' ?></th>
                  <th><?= $isArabic ? 'الكلية / القسم' : 'College / Department' ?></th>
                  <th><?= $isArabic ? 'الحالة' : 'Status' ?></th>
                  <th><?= $isArabic ? 'ملاحظات الأدمن' : 'Admin Notes' ?></th>
                  <th><?= $isArabic ? 'تاريخ التقديم' : 'Submitted At' ?></th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($myRequests as $r): ?>
                  <?php
                    $st = trim($r['status'] ?? 'pending');
                    $stCls = staffStatusClass($st);
                  ?>

                  <tr>
                    <td><?= h($r['request_id'] ?? '—') ?></td>

                    <td>
                      <?= h($r['initiative_title'] ?? '—') ?>
                      <div class="staff-mini"><?= h($r['initiative_type'] ?? '') ?></div>
                    </td>

                    <td>
                      <?= h($r['college'] ?? '—') ?>
                      <div class="staff-mini"><?= h($r['department'] ?? '') ?></div>
                    </td>

                    <td>
                      <span class="staff-pill <?= h($stCls) ?>">
                        <?= h(staffStatusLabel($st, $isArabic)) ?>
                      </span>
                    </td>

                    <td><?= nl2br(h($r['admin_notes'] ?? '—')) ?></td>
                    <td><?= h($r['created_at'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="staff-pane <?= $activeTab === 'suggestions' ? 'active' : '' ?>" id="suggestions">
        <h2 class="staff-section-title"><?= $isArabic ? 'اقتراحات ذكية للمبادرات' : 'Smart Initiative Suggestions' ?></h2>
            
<div class="staff-dashboard">
  <div class="dash-card">
    <span>⭐ <?= $isArabic ? 'المحفوظات' : 'Saved' ?></span>
    <strong><?= (int)$savedCount ?></strong>
  </div>

  <div class="dash-card">
    <span>❌ <?= $isArabic ? 'المرفوضة' : 'Rejected' ?></span>
    <strong><?= (int)$rejectedCount ?></strong>
  </div>

  <div class="dash-card">
    <span>✅ <?= $isArabic ? 'الطلبات المقبولة' : 'Approved Requests' ?></span>
    <strong><?= (int)$approvedRequestsCount ?></strong>
  </div>

  <div class="dash-card">
    <span>📊 <?= $isArabic ? 'أكثر نوع مفضل' : 'Top Type' ?></span>
    <strong style="font-size:15px;line-height:1.4;"><?= h($mostPreferredType) ?></strong>
  </div>
</div>



        <div class="staff-profile-box">
          <span class="staff-profile-chip">
            <?= $isArabic ? 'الكلية:' : 'College:' ?>
            <?= h($userCollege ?: ($isArabic ? 'غير محددة' : 'Not specified')) ?>
          </span>

          <span class="staff-profile-chip">
            <?= $isArabic ? 'القسم:' : 'Department:' ?>
            <?= h($userDepartment ?: ($isArabic ? 'غير محدد' : 'Not specified')) ?>
          </span>


          

          
        </div>
          
           <div style="margin-bottom:18px;">
             <button type="button" class="staff-submit-btn" onclick="openSavedModal()">
               <?= $isArabic ? '⭐ عرض المحفوظات' : '⭐ View Saved' ?>
             </button>
           </div>
         
         
          
        <?php if (!$suggestions): ?>
          <div class="staff-empty">
            <?= $isArabic
              ? 'لا توجد اقتراحات حالياً، أو تم إخفاء جميع الاقتراحات المتاحة.'
              : 'No suggestions currently, or all available suggestions have been hidden.' ?>
          </div>
        <?php else: ?>
          <div class="staff-suggestions">
            <?php foreach ($suggestions as $i => $s): ?>
              <?php
                $title = $isArabic ? ($s['title_ar'] ?? '') : ($s['title_en'] ?? '');
                $summary = $isArabic ? ($s['summary_ar'] ?? '') : ($s['summary_en'] ?? '');
                $target = $isArabic ? ($s['target_group_ar'] ?? '') : ($s['target_group_en'] ?? '');
                $implementation = $isArabic ? ($s['implementation_ar'] ?? '') : ($s['implementation_en'] ?? '');
                $outputs = $isArabic ? ($s['expected_outputs_ar'] ?? '') : ($s['expected_outputs_en'] ?? '');
                $why = $isArabic ? ($s['why_suitable_ar'] ?? '') : ($s['why_suitable_en'] ?? '');
                $type = $s['initiative_type'] ?? '';
                $duration = $s['duration'] ?? ($isArabic ? 'غير محددة' : 'Not specified');
                $sdgs = $s['sdgs'] ?? '';
                $modalId = 'sugModal' . $i;
                [$scoreText, $scoreClass] = recommendationLevel($type);
              ?>

              <div class="staff-suggestion">
                

                <h3><?= h($title) ?></h3>
                <p><?= h($summary) ?></p>

                <div>
                  <span class="staff-pill idea">🎯 <?= h($type) ?></span>
                  <span class="staff-pill info">⏱ <?= h($duration) ?></span>
                  <span class="staff-pill <?= h('score-' . $scoreClass) ?>"><?= h($scoreText) ?></span>
                  <span class="staff-pill idea"><?= h($sdgs) ?></span>
                </div>

                <div class="staff-actions">
                  <button type="button" class="staff-outline-btn" onclick="openSuggestionModal('<?= h($modalId) ?>')">
                    <?= $isArabic ? 'عرض التفاصيل' : 'View Details' ?>
                  </button>

                  <form method="post">
                    <input type="hidden" name="action" value="request_ai_suggestion">
                    <input type="hidden" name="college" value="<?= h($userCollege) ?>">
                    <input type="hidden" name="department" value="<?= h($userDepartment) ?>">
                    <input type="hidden" name="initiative_title" value="<?= h($title) ?>">
                    <input type="hidden" name="initiative_type" value="<?= h($type) ?>">
                    <input type="hidden" name="description" value="<?= h($summary . "\n\n" . $implementation . "\n\n" . $why) ?>">
                    <input type="hidden" name="target_group" value="<?= h($target) ?>">
                    <input type="hidden" name="expected_outputs" value="<?= h($outputs) ?>">
                    <input type="hidden" name="sdgs" value="<?= h($sdgs) ?>">

                    <button class="staff-submit-btn" type="submit">
                      <?= $isArabic ? '✔ طلب موافقة' : '✔ Request Approval' ?>
                    </button>
                  </form>

                  <form method="post">
                    <input type="hidden" name="action" value="like_suggestion">
                    <input type="hidden" name="initiative_title" value="<?= h($title) ?>">
                    <input type="hidden" name="initiative_type" value="<?= h($type) ?>">
                    <input type="hidden" name="description" value="<?= h($summary) ?>">
                    <input type="hidden" name="target_group" value="<?= h($target) ?>">
                    <input type="hidden" name="expected_outputs" value="<?= h($outputs) ?>">
                    <input type="hidden" name="sdgs" value="<?= h($sdgs) ?>">

                    <button class="staff-outline-btn staff-save-btn" type="submit">
                      <?= $isArabic ? '⭐ حفظ لاحقاً' : '⭐ Save Later' ?>
                    </button>
                  </form>

                  <form method="post">
                    <input type="hidden" name="action" value="reject_suggestion">
                    <input type="hidden" name="initiative_title" value="<?= h($title) ?>">
                    <input type="hidden" name="initiative_type" value="<?= h($type) ?>">

                    <button class="staff-outline-btn staff-reject-btn" type="submit">
                      <?= $isArabic ? '❌ مو مناسب' : '❌ Not Suitable' ?>
                    </button>
                  </form>
                </div>
              </div>

              <div id="<?= h($modalId) ?>" class="suggestion-modal">
                <div class="suggestion-modal-box">
                  <button type="button" class="suggestion-close" onclick="closeSuggestionModal('<?= h($modalId) ?>')">×</button>

                  <h2><?= h($title) ?></h2>
                  <p><?= h($summary) ?></p>

                  <span class="staff-pill idea">🎯 <?= h($type) ?></span>
                  <span class="staff-pill info">⏱ <?= h($duration) ?></span>
                  <span class="staff-pill <?= h('score-' . $scoreClass) ?>"><?= h($scoreText) ?></span>
                  <span class="staff-pill idea"><?= h($sdgs) ?></span>

                  <div class="suggestion-detail-grid">
                    <div class="suggestion-detail-box">
                      <b><?= $isArabic ? 'الفئة المستهدفة' : 'Target Group' ?></b>
                      <p><?= h($target) ?></p>
                    </div>

                    <div class="suggestion-detail-box">
                      <b><?= $isArabic ? 'نوع المبادرة' : 'Initiative Type' ?></b>
                      <p><?= h($type) ?></p>
                    </div>

                    <div class="suggestion-detail-box">
                      <b><?= $isArabic ? 'خطوات التنفيذ' : 'Implementation Steps' ?></b>
                      <p><?= h($implementation) ?></p>
                    </div>

                    <div class="suggestion-detail-box">
                      <b><?= $isArabic ? 'المخرجات المتوقعة' : 'Expected Outputs' ?></b>
                      <p><?= h($outputs) ?></p>
                    </div>
                  </div>

                  <div class="suggestion-detail-box" style="margin-top:12px;">
                    <b><?= $isArabic ? 'لماذا يناسب القسم؟' : 'Why Suitable?' ?></b>
                    <p><?= h($why) ?></p>
                  </div>

                  <div class="modal-footer-actions">
                    <form method="post">
                      <input type="hidden" name="action" value="request_ai_suggestion">
                      <input type="hidden" name="college" value="<?= h($userCollege) ?>">
                      <input type="hidden" name="department" value="<?= h($userDepartment) ?>">
                      <input type="hidden" name="initiative_title" value="<?= h($title) ?>">
                      <input type="hidden" name="initiative_type" value="<?= h($type) ?>">
                      <input type="hidden" name="description" value="<?= h($summary . "\n\n" . $implementation . "\n\n" . $why) ?>">
                      <input type="hidden" name="target_group" value="<?= h($target) ?>">
                      <input type="hidden" name="expected_outputs" value="<?= h($outputs) ?>">
                      <input type="hidden" name="sdgs" value="<?= h($sdgs) ?>">

                      <button class="staff-submit-btn" type="submit">
                        <?= $isArabic ? 'طلب موافقة على الاقتراح' : 'Request Approval' ?>
                      </button>
                    </form>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
   

<div id="savedModal" class="suggestion-modal">
  <div class="suggestion-modal-box">
    <button type="button" class="suggestion-close" onclick="closeSavedModal()">×</button>

    <h2><?= $isArabic ? 'الاقتراحات المحفوظة' : 'Saved Suggestions' ?></h2>

    <?php if (empty($mySavedSuggestions)): ?>
      <div class="staff-empty">
        <?= $isArabic ? 'لا توجد اقتراحات محفوظة حالياً.' : 'No saved suggestions yet.' ?>
      </div>
    <?php else: ?>
      <?php foreach ($mySavedSuggestions as $saved): ?>
        <div class="suggestion-detail-box" style="margin-bottom:12px;">
          <h3 style="margin:0;color:#0b1f3a;font-weight:950;">
            <?= h($saved['title'] ?? '—') ?>
          </h3>

          <span class="staff-pill idea">
            🎯 <?= h($saved['type'] ?? '') ?>
          </span>

          <span class="staff-pill info">
            <?= h($saved['sdgs'] ?? '') ?>
          </span>

          <p><?= nl2br(h($saved['description'] ?? '')) ?></p>

          <div class="staff-mini">
            <?= $isArabic ? 'تاريخ الحفظ:' : 'Saved At:' ?>
            <?= h($saved['created_at'] ?? '') ?>
          </div>

          <div style="margin-top:12px;">
            <form method="post">
              <input type="hidden" name="action" value="request_ai_suggestion">
              <input type="hidden" name="college" value="<?= h($userCollege) ?>">
              <input type="hidden" name="department" value="<?= h($userDepartment) ?>">
              <input type="hidden" name="initiative_title" value="<?= h($saved['title'] ?? '') ?>">
              <input type="hidden" name="initiative_type" value="<?= h($saved['type'] ?? '') ?>">
              <input type="hidden" name="description" value="<?= h($saved['description'] ?? '') ?>">
              <input type="hidden" name="target_group" value="<?= h($saved['target_group'] ?? '') ?>">
              <input type="hidden" name="expected_outputs" value="<?= h($saved['expected_outputs'] ?? '') ?>">
              <input type="hidden" name="sdgs" value="<?= h($saved['sdgs'] ?? '') ?>">

              <button class="staff-submit-btn" type="submit">
                <?= $isArabic ? '✔ طلب موافقة' : '✔ Request Approval' ?>
              </button>
            </form>
            <form method="post" style="margin-top:8px;">
  <input type="hidden" name="action" value="delete_saved_suggestion">
  <input type="hidden" name="initiative_title" value="<?= h($saved['title'] ?? '') ?>">

  <button class="staff-outline-btn staff-reject-btn" type="submit">
    <?= $isArabic ? '🗑 حذف من المحفوظات' : '🗑 Delete from Saved' ?>
  </button>
</form>




          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>




<script>
document.querySelectorAll('.staff-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    const tab = btn.dataset.tab;

    document.querySelectorAll('.staff-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.staff-pane').forEach(p => p.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(tab).classList.add('active');

    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
  });
});

function openSuggestionModal(id) {
  document.getElementById(id)?.classList.add('show');
}

function closeSuggestionModal(id) {
  document.getElementById(id)?.classList.remove('show');
}

document.querySelectorAll('.suggestion-modal').forEach(m => {
  m.addEventListener('click', e => {
    if (e.target === m) m.classList.remove('show');
  });
});

function openSavedModal() {
  document.getElementById('savedModal')?.classList.add('show');
}

function closeSavedModal() {
  document.getElementById('savedModal')?.classList.remove('show');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>