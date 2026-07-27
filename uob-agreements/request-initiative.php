<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_email'])) {
  header('Location: login.php?to=request-initiative-compatible.php');
  exit;
}

$pageTitle = 'طلب مبادرة قبل التنفيذ';
$hidePageHeader = true;
$mainContainer = false;
require_once __DIR__ . '/header.php';

$isArabic = ($_SESSION['lang'] ?? 'ar') === 'ar';
$pageTitle = $isArabic ? 'طلب مبادرة قبل التنفيذ' : 'Pre-Execution Initiative Request';
$errors = [];
$success = false;
$requestId = '';
$requestsFile = __DIR__ . '/data/initiative_requests.csv';
$agreements = function_exists('readAgreements') ? readAgreements() : [];
$structureFile = __DIR__ . '/data/UOB_Colleges_Departments.csv';
$uobStructure = [];

if (file_exists($structureFile) && ($structureHandle = fopen($structureFile, 'r')) !== false) {
  $structureHeader = null;

  while (($structureRow = fgetcsv($structureHandle)) !== false) {
    if (isset($structureRow[0]) && trim((string)$structureRow[0]) === 'اسم الكلية بالعربي') {
      $structureHeader = $structureRow;
      break;
    }
  }

  if ($structureHeader) {
    while (($structureRow = fgetcsv($structureHandle)) !== false) {
      $structureRow = array_pad($structureRow, count($structureHeader), '');
      $structureData = array_combine($structureHeader, $structureRow);

      $collegeName = trim((string)($isArabic
        ? ($structureData['اسم الكلية بالعربي'] ?? '')
        : ($structureData['College Name in English'] ?? '')));
      $departmentName = trim((string)($isArabic
        ? ($structureData['اسم القسم بالعربي'] ?? '')
        : ($structureData['Department Name in English'] ?? '')));

      if ($collegeName !== '' && $departmentName !== '') {
        $uobStructure[$collegeName] ??= [];
        $uobStructure[$collegeName][] = $departmentName;
      }
    }
  }

  fclose($structureHandle);
}

foreach ($uobStructure as &$departments) {
  $departments = array_values(array_unique($departments));
  sort($departments);
}
unset($departments);
ksort($uobStructure);

if (empty($_SESSION['initiative_request_csrf'])) {
  $_SESSION['initiative_request_csrf'] = bin2hex(random_bytes(24));
}

function reqv(string $key, string $default = ''): string {
  return trim((string)($_POST[$key] ?? $default));
}

function reqarr(string $key): array {
  $value = $_POST[$key] ?? [];
  if (!is_array($value)) return [];
  return array_values(array_unique(array_filter(array_map(
    static fn($item) => trim((string)$item),
    $value
  ))));
}

function reqh($value): string {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function appendRequestCsv(string $file, array $fields, array $data): bool {
  $directory = dirname($file);
  if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) return false;

  $fp = fopen($file, 'c+');
  if (!$fp) return false;

  if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    return false;
  }

  rewind($fp);
  $existingHeader = fgetcsv($fp);
  $existingRows = [];
  if (is_array($existingHeader) && $existingHeader) {
    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($existingHeader), '');
      $existingRows[] = array_combine($existingHeader, array_slice($row, 0, count($existingHeader)));
    }
  } else {
    $existingHeader = [];
  }

  $finalHeader = array_values(array_unique(array_merge($existingHeader, $fields)));
  ftruncate($fp, 0);
  rewind($fp);
  fputcsv($fp, $finalHeader);

  foreach ($existingRows as $existingRow) {
    $line = [];
    foreach ($finalHeader as $field) $line[] = $existingRow[$field] ?? '';
    fputcsv($fp, $line);
  }

  $row = [];
  foreach ($finalHeader as $field) $row[] = $data[$field] ?? '';
  $written = fputcsv($fp, $row) !== false;

  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $written;
}

$requesterTypes = [
  'faculty' => ['ar' => 'عضو هيئة تدريس', 'en' => 'Faculty Member'],
  'staff' => ['ar' => 'موظف', 'en' => 'Staff Member'],
  'student' => ['ar' => 'طالب', 'en' => 'Student'],
  'student_group' => ['ar' => 'مجموعة طلابية', 'en' => 'Student Group'],
  'other' => ['ar' => 'أخرى', 'en' => 'Other'],
];

$entityOptions = [];
foreach (array_keys($uobStructure) as $entityName) {
  $entityOptions[$entityName] = ['ar' => $entityName, 'en' => $entityName];
}
$entityOptions['other'] = ['ar' => 'أخرى', 'en' => 'Other'];

$initiativeTypes = [
  'workshop_training' => ['ar' => 'ورشة عمل / تدريب', 'en' => 'Workshop / Training'],
  'lecture_seminar' => ['ar' => 'محاضرة / ندوة', 'en' => 'Lecture / Seminar'],
  'student_initiative' => ['ar' => 'مبادرة طلابية', 'en' => 'Student Initiative'],
  'community_engagement' => ['ar' => 'مشاركة مجتمعية', 'en' => 'Community Engagement'],
  'volunteering' => ['ar' => 'برنامج تطوعي', 'en' => 'Volunteering Program'],
  'awareness_campaign' => ['ar' => 'حملة توعوية', 'en' => 'Awareness Campaign'],
  'research' => ['ar' => 'بحث أو تعاون علمي', 'en' => 'Research or Academic Collaboration'],
  'consultation' => ['ar' => 'استشارة / دور استشاري', 'en' => 'Consultation / Advisory Role'],
  'partnership' => ['ar' => 'شراكة مجتمعية', 'en' => 'Community Partnership'],
  'sustainability' => ['ar' => 'نشاط استدامة', 'en' => 'Sustainability Activity'],
  'other' => ['ar' => 'أخرى', 'en' => 'Other'],
];

$targetGroups = [
  'university_students' => ['ar' => 'طلبة الجامعة', 'en' => 'University Students'],
  'school_students' => ['ar' => 'طلبة المدارس', 'en' => 'School Students'],
  'faculty_staff' => ['ar' => 'أعضاء هيئة التدريس والموظفون', 'en' => 'Faculty and Staff'],
  'professionals' => ['ar' => 'المهنيون', 'en' => 'Professionals'],
  'alumni' => ['ar' => 'الخريجون', 'en' => 'Alumni'],
  'local_community' => ['ar' => 'المجتمع المحلي', 'en' => 'Local Community'],
  'vulnerable_groups' => ['ar' => 'الفئات المحتاجة', 'en' => 'Vulnerable Groups'],
  'open_public' => ['ar' => 'مفتوح للجمهور', 'en' => 'Open to the Public'],
  'other' => ['ar' => 'أخرى', 'en' => 'Other'],
];

$resourceOptions = [
  'venue' => ['ar' => 'قاعة أو موقع', 'en' => 'Venue'],
  'budget' => ['ar' => 'ميزانية أو تمويل', 'en' => 'Budget or Funding'],
  'media' => ['ar' => 'دعم إعلامي', 'en' => 'Media Support'],
  'transport' => ['ar' => 'مواصلات', 'en' => 'Transportation'],
  'equipment' => ['ar' => 'تجهيزات أو دعم تقني', 'en' => 'Equipment or Technical Support'],
  'none' => ['ar' => 'لا توجد متطلبات إضافية', 'en' => 'No Additional Requirements'],
  'other' => ['ar' => 'أخرى', 'en' => 'Other'],
];

$sdgGoals = [
  'SDG 1' => ['ar' => 'القضاء على الفقر', 'en' => 'No Poverty'],
  'SDG 2' => ['ar' => 'القضاء على الجوع', 'en' => 'Zero Hunger'],
  'SDG 3' => ['ar' => 'الصحة الجيدة والرفاه', 'en' => 'Good Health and Well-being'],
  'SDG 4' => ['ar' => 'التعليم الجيد', 'en' => 'Quality Education'],
  'SDG 5' => ['ar' => 'المساواة بين الجنسين', 'en' => 'Gender Equality'],
  'SDG 6' => ['ar' => 'المياه النظيفة والنظافة الصحية', 'en' => 'Clean Water and Sanitation'],
  'SDG 7' => ['ar' => 'طاقة نظيفة وبأسعار معقولة', 'en' => 'Affordable and Clean Energy'],
  'SDG 8' => ['ar' => 'العمل اللائق والنمو الاقتصادي', 'en' => 'Decent Work and Economic Growth'],
  'SDG 9' => ['ar' => 'الصناعة والابتكار والبنية التحتية', 'en' => 'Industry, Innovation and Infrastructure'],
  'SDG 10' => ['ar' => 'الحد من أوجه عدم المساواة', 'en' => 'Reduced Inequalities'],
  'SDG 11' => ['ar' => 'مدن ومجتمعات مستدامة', 'en' => 'Sustainable Cities and Communities'],
  'SDG 12' => ['ar' => 'الاستهلاك والإنتاج المسؤولان', 'en' => 'Responsible Consumption and Production'],
  'SDG 13' => ['ar' => 'العمل المناخي', 'en' => 'Climate Action'],
  'SDG 14' => ['ar' => 'الحياة تحت الماء', 'en' => 'Life Below Water'],
  'SDG 15' => ['ar' => 'الحياة في البر', 'en' => 'Life on Land'],
  'SDG 16' => ['ar' => 'السلام والعدل والمؤسسات القوية', 'en' => 'Peace, Justice and Strong Institutions'],
  'SDG 17' => ['ar' => 'عقد الشراكات لتحقيق الأهداف', 'en' => 'Partnerships for the Goals'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = reqv('_csrf');
  if (!hash_equals($_SESSION['initiative_request_csrf'] ?? '', $csrf)) {
    $errors[] = $isArabic ? 'انتهت صلاحية الجلسة. أعد تحميل الصفحة وحاول مرة أخرى.' : 'The session expired. Reload the page and try again.';
  }

  $requesterName = reqv('requester_name');
  $requesterType = reqv('requester_type');
  $requesterTypeOther = reqv('requester_type_other');
  $requesterEmail = reqv('requester_email');
  $requesterMobile = reqv('requester_mobile');
  $entity = reqv('entity');
  $entityOther = reqv('entity_other');
  $departmentChoice = reqv('department');
  $departmentOther = reqv('department_other');
  $department = $departmentChoice === 'other' ? $departmentOther : $departmentChoice;
  $title = reqv('title');
  $primaryType = reqv('primary_type');
  $primaryTypeOther = reqv('primary_type_other');
  $secondaryTypes = reqarr('secondary_types');
  $description = reqv('description');
  $objective = reqv('objective');
  $targets = reqarr('target_groups');
  $targetOther = reqv('target_group_other');
  $expectedParticipants = reqv('expected_participants');
  $startDate = reqv('start_date');
  $endDate = reqv('end_date');
  $scope = reqv('scope');
  $scopeOther = reqv('scope_other');
  $venue = reqv('venue');
  $relatedAgreement = reqv('related_agreement');
  $agreementCode = $relatedAgreement === 'yes' ? reqv('agreement_code') : '';
  $hasExternalPartner = reqv('has_external_partner');
  $partnerName = $hasExternalPartner === 'yes' ? reqv('partner_name') : '';
  $partnerRole = $hasExternalPartner === 'yes' ? reqv('partner_role') : '';
  $resources = reqarr('resources');
  $resourceOther = reqv('resource_other');
  $estimatedBudget = reqv('estimated_budget');
  $needsMedia = reqv('needs_media');
  $supportsSdg = reqv('supports_sdg');
  $selectedSdgs = $supportsSdg === 'yes' ? reqarr('sdg_goals') : [];
  $declaration = reqv('declaration');

  if ($requesterName === '') $errors[] = $isArabic ? 'اسم مقدم الطلب مطلوب.' : 'Requester name is required.';
  if ($requesterType === '') $errors[] = $isArabic ? 'صفة مقدم الطلب مطلوبة.' : 'Requester type is required.';
  if ($requesterType === 'other' && $requesterTypeOther === '') $errors[] = $isArabic ? 'يرجى تحديد صفة مقدم الطلب الأخرى.' : 'Specify the other requester type.';
  if (!filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) $errors[] = $isArabic ? 'أدخل بريدًا إلكترونيًا صحيحًا.' : 'Enter a valid email address.';
  if ($entity === '') $errors[] = $isArabic ? 'الكلية أو الجهة مطلوبة.' : 'College or entity is required.';
  if ($entity === 'other' && $entityOther === '') $errors[] = $isArabic ? 'يرجى تحديد الكلية أو الجهة الأخرى.' : 'Specify the other college or entity.';
  if ($department === '') $errors[] = $isArabic ? 'القسم مطلوب.' : 'Department is required.';
  if ($title === '') $errors[] = $isArabic ? 'عنوان المبادرة مطلوب.' : 'Initiative title is required.';
  if ($primaryType === '') $errors[] = $isArabic ? 'نوع المبادرة الرئيسي مطلوب.' : 'Primary initiative type is required.';
  if ($primaryType === 'other' && $primaryTypeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نوع المبادرة الآخر.' : 'Specify the other initiative type.';
  if ($description === '') $errors[] = $isArabic ? 'وصف المبادرة مطلوب.' : 'Initiative description is required.';
  if ($objective === '') $errors[] = $isArabic ? 'هدف المبادرة مطلوب.' : 'Initiative objective is required.';
  if (!$targets) $errors[] = $isArabic ? 'اختر جمهورًا مستهدفًا واحدًا على الأقل.' : 'Select at least one target audience.';
  if (in_array('other', $targets, true) && $targetOther === '') $errors[] = $isArabic ? 'يرجى تحديد الجمهور المستهدف الآخر.' : 'Specify the other target audience.';
  if ($expectedParticipants === '' || !ctype_digit($expectedParticipants)) $errors[] = $isArabic ? 'أدخل العدد المتوقع للمشاركين.' : 'Enter the expected number of participants.';
  if ($startDate === '' || $endDate === '') $errors[] = $isArabic ? 'تاريخا البداية والنهاية مطلوبان.' : 'Start and end dates are required.';
  if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) $errors[] = $isArabic ? 'تاريخ النهاية يجب ألا يسبق تاريخ البداية.' : 'End date cannot be before start date.';
  if ($scope === '') $errors[] = $isArabic ? 'نطاق التنفيذ مطلوب.' : 'Implementation scope is required.';
  if ($scope === 'other' && $scopeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نطاق التنفيذ الآخر.' : 'Specify the other implementation scope.';
  if ($venue === '') $errors[] = $isArabic ? 'مكان التنفيذ المقترح مطلوب.' : 'Proposed venue is required.';
  if ($relatedAgreement === '') $errors[] = $isArabic ? 'حدد ما إذا كانت المبادرة مرتبطة باتفاقية.' : 'Specify whether the initiative is linked to an agreement.';
  if ($relatedAgreement === 'yes' && $agreementCode === '') $errors[] = $isArabic ? 'اختر الاتفاقية المرتبطة.' : 'Select the related agreement.';
  if ($hasExternalPartner === '') $errors[] = $isArabic ? 'حدد ما إذا كانت هناك جهة خارجية مشاركة.' : 'Specify whether an external partner is involved.';
  if ($hasExternalPartner === 'yes' && $partnerName === '') $errors[] = $isArabic ? 'اسم الجهة الخارجية مطلوب.' : 'External partner name is required.';
  if (!$resources) $errors[] = $isArabic ? 'حدد متطلبات المبادرة.' : 'Select the initiative requirements.';
  if (in_array('other', $resources, true) && $resourceOther === '') $errors[] = $isArabic ? 'يرجى تحديد المتطلب الآخر.' : 'Specify the other requirement.';
  if ($supportsSdg === '') $errors[] = $isArabic ? 'حدد ما إذا كانت المبادرة تدعم أهداف التنمية المستدامة.' : 'Specify whether the initiative supports the SDGs.';
  if ($supportsSdg === 'yes' && !$selectedSdgs) $errors[] = $isArabic ? 'اختر هدف تنمية مستدامة واحدًا على الأقل.' : 'Select at least one SDG.';
  if ($declaration !== 'yes') $errors[] = $isArabic ? 'يجب الموافقة على الإقرار.' : 'You must accept the declaration.';

  $uploadedFiles = [];
  if (!$errors && !empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
    $uploadDir = __DIR__ . '/uploads/initiative-requests/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png'];
    $count = min(count($_FILES['attachments']['name']), 5);

    for ($i = 0; $i < $count; $i++) {
      if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $original = basename((string)$_FILES['attachments']['name'][$i]);
      $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) {
        $errors[] = ($isArabic ? 'نوع ملف غير مسموح: ' : 'Unsupported file type: ') . $original;
        continue;
      }
      if (($_FILES['attachments']['size'][$i] ?? 0) > 10 * 1024 * 1024) {
        $errors[] = ($isArabic ? 'حجم الملف يتجاوز 10 ميغابايت: ' : 'File exceeds 10 MB: ') . $original;
        continue;
      }
      $safeName = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
      $destination = $uploadDir . $safeName;
      if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $destination)) {
        $uploadedFiles[] = 'uploads/initiative-requests/' . $safeName;
      }
    }
  }

  if (!$errors) {
    $requestId = 'REQ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $fields = [
      'request_id', 'submitted_by', 'full_name', 'email', 'college', 'department',
      'initiative_title', 'initiative_type', 'description', 'expected_date',
      'status', 'admin_notes', 'approved_by', 'approved_at', 'used', 'created_at',
      'requester_name', 'requester_type', 'requester_type_other',
      'requester_email', 'requester_mobile', 'entity', 'entity_other',
      'title', 'primary_type', 'primary_type_other', 'secondary_types',
      'objective', 'target_groups', 'target_group_other', 'expected_participants',
      'start_date', 'end_date', 'scope', 'scope_other', 'venue', 'related_agreement',
      'agreement_code', 'has_external_partner', 'partner_name', 'partner_role',
      'resources', 'resource_other', 'estimated_budget', 'needs_media', 'supports_sdg',
      'sdg_goals', 'attachments'
    ];
    $data = [
      'request_id' => $requestId,
      'submitted_by' => $_SESSION['user_email'] ?? $requesterEmail,
      'full_name' => $requesterName,
      'email' => $requesterEmail,
      'college' => $entity === 'other'
        ? $entityOther
        : ($entityOptions[$entity][$isArabic ? 'ar' : 'en'] ?? $entity),
      'department' => $department,
      'initiative_title' => $title,
      'initiative_type' => $primaryType === 'other'
        ? $primaryTypeOther
        : ($initiativeTypes[$primaryType][$isArabic ? 'ar' : 'en'] ?? $primaryType),
      'description' => $description,
      'expected_date' => $startDate,
      'status' => 'pending',
      'admin_notes' => '',
      'approved_by' => '',
      'approved_at' => '',
      'used' => '0',
      'created_at' => date('Y-m-d H:i:s'),
      'requester_name' => $requesterName,
      'requester_type' => $requesterType,
      'requester_type_other' => $requesterTypeOther,
      'requester_email' => $requesterEmail,
      'requester_mobile' => $requesterMobile,
      'entity' => $entity,
      'entity_other' => $entityOther,
      'title' => $title,
      'primary_type' => $primaryType,
      'primary_type_other' => $primaryTypeOther,
      'secondary_types' => implode(' | ', $secondaryTypes),
      'objective' => $objective,
      'target_groups' => implode(' | ', $targets),
      'target_group_other' => $targetOther,
      'expected_participants' => $expectedParticipants,
      'start_date' => $startDate,
      'end_date' => $endDate,
      'scope' => $scope,
      'scope_other' => $scopeOther,
      'venue' => $venue,
      'related_agreement' => $relatedAgreement,
      'agreement_code' => $agreementCode,
      'has_external_partner' => $hasExternalPartner,
      'partner_name' => $partnerName,
      'partner_role' => $partnerRole,
      'resources' => implode(' | ', $resources),
      'resource_other' => $resourceOther,
      'estimated_budget' => $estimatedBudget,
      'needs_media' => $needsMedia,
      'supports_sdg' => $supportsSdg,
      'sdg_goals' => implode(' | ', $selectedSdgs),
      'attachments' => implode(' | ', $uploadedFiles),
    ];

    if (appendRequestCsv($requestsFile, $fields, $data)) {
      $success = true;
      $_POST = [];
      $_SESSION['initiative_request_csrf'] = bin2hex(random_bytes(24));
    } else {
      $errors[] = $isArabic ? 'تعذر حفظ الطلب. تحقق من صلاحيات مجلد البيانات.' : 'The request could not be saved. Check data directory permissions.';
    }
  }
}
?>

<style>
.request-hero{position:relative;overflow:hidden;padding:58px 20px 72px;background:linear-gradient(135deg,#8f6f3f,#b89a68 55%,#87693e);color:#fff}
.request-hero-inner,.request-shell{max-width:1180px;margin:auto}
.request-hero h1{margin:0;font-size:clamp(30px,4vw,50px);font-weight:950}
.request-hero p{margin:12px 0 0;max-width:780px;line-height:1.9;font-weight:700;color:rgba(255,255,255,.9)}
.request-shell{position:relative;margin-top:-38px;margin-bottom:42px;padding:28px;background:#fff;border:1px solid #e6ebf2;border-radius:28px;box-shadow:0 24px 60px rgba(2,8,23,.12)}
.request-progress{display:flex;align-items:center;gap:14px;margin:0 0 24px;padding:14px 18px;border:1px solid rgba(184,154,104,.28);border-radius:17px;background:rgba(184,154,104,.07)}
.request-progress-percent{flex:0 0 auto;min-width:48px;color:#8f6f3f;font-size:18px;font-weight:950;text-align:center}
.request-progress-track{flex:1;height:13px;overflow:hidden;border-radius:999px;background:#e8edf3;box-shadow:inset 0 1px 3px rgba(15,23,42,.1)}
.request-progress-fill{width:0;height:100%;border-radius:inherit;background:linear-gradient(90deg,#8f6f3f,#b89a68,#d7bd82);transition:width .28s ease}
.request-tabs{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));align-items:end;gap:8px;margin:0 0 12px;overflow:visible;padding:18px 10px 0;border-bottom:5px solid #8f6f3f;border-radius:18px 18px 8px 8px;background:linear-gradient(180deg,#fff,rgba(184,154,104,.06))}
.request-tab-btn{position:relative;display:flex;align-items:center;justify-content:center;gap:7px;width:100%;min-width:0;min-height:66px;padding:12px 9px 16px;border:1px solid rgba(184,154,104,.38);border-bottom:0;border-radius:18px 18px 5px 5px;background:linear-gradient(180deg,#fff,#f5f7fa);color:#0b1f3a;font-size:clamp(11px,1.05vw,14px);font-weight:900;line-height:1.35;white-space:normal;filter:drop-shadow(0 5px 7px rgba(15,23,42,.09));transition:transform .2s ease,filter .2s ease,border-color .2s ease,background .2s ease;z-index:1}
.request-tab-btn::after{content:"";position:absolute;right:18%;bottom:6px;left:18%;height:4px;border-radius:999px;background:rgba(184,154,104,.28)}
.request-tab-number{display:inline-flex;align-items:center;justify-content:center;flex:0 0 28px;width:28px;height:28px;border-radius:10px;background:rgba(184,154,104,.16);color:#8f6f3f;font-size:14px;font-weight:950}
.request-tab-btn:hover{border-color:#b89a68;background:linear-gradient(180deg,#fff,#f7f0e4);transform:translateY(-3px);filter:drop-shadow(0 8px 9px rgba(15,23,42,.12))}
.request-tab-btn.active{min-height:74px;border-color:#8f6f3f;background:linear-gradient(180deg,#b89a68,#8f6f3f);color:#fff;filter:drop-shadow(0 9px 11px rgba(143,111,63,.3));transform:translateY(0);z-index:2}
.request-tab-btn.active::after{background:rgba(255,255,255,.42)}
.request-tab-btn.active .request-tab-number{background:rgba(255,255,255,.2);color:#fff}
.request-section{display:none;padding:8px 0}
.request-section.active{display:block}
.request-section-title{margin:0 0 18px;padding:12px 15px;border-radius:15px;background:rgba(184,154,104,.13);color:#0b1f3a;font-size:18px;font-weight:950}
.request-subsection-title{display:flex;align-items:center;gap:10px;margin-top:2px;color:#8f6f3f;font-size:15px;font-weight:950}
.request-subsection-title::before{content:"";width:7px;height:28px;border-radius:999px;background:linear-gradient(180deg,#b89a68,#8f6f3f)}
.request-order-1{order:1}.request-order-2{order:2}.request-order-3{order:3}.request-order-4{order:4}.request-order-5{order:5}.request-order-6{order:6}.request-order-7{order:7}.request-order-8{order:8}.request-order-9{order:9}
.request-label{display:block;margin-bottom:8px;color:#0b1f3a;font-weight:900}
.request-help{margin-top:7px;color:#64748b;font-size:13px;font-weight:700;line-height:1.6}
.request-input{min-height:54px;border:1px solid #d9e3ef!important;border-radius:15px!important;background:#fbfdff!important;font-weight:700}
.request-input:focus{border-color:#b89a68!important;box-shadow:0 0 0 .2rem rgba(184,154,104,.18)!important}
textarea.request-input{min-height:120px;padding-top:13px}
.request-choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.request-choice{display:flex;align-items:center;gap:10px;min-height:58px;padding:13px 15px;border:1px solid #d9e3ef;border-radius:16px;background:#fbfdff;font-weight:800;cursor:pointer}
.request-choice input{width:18px;height:18px;accent-color:#8f6f3f}
.request-other{flex:1;min-width:150px}
.request-other .request-input{min-height:42px}
.request-hidden{display:none!important}
.request-alert{max-width:1180px;margin:18px auto;border-radius:16px;font-weight:800}
.request-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:24px;border-top:1px solid #e6ebf2;margin-top:24px}
.request-nav-group{display:flex;gap:10px}
.request-nav-btn{min-height:50px;padding:0 22px;border:1px solid #b89a68;border-radius:15px;background:#fff;color:#8f6f3f;font-weight:950}
.request-nav-btn:disabled{opacity:.45;cursor:not-allowed}
.request-submit{min-height:54px;padding:0 28px;border:0;border-radius:16px;background:linear-gradient(180deg,#b89a68,#8f6f3f);color:#fff;font-weight:950}
.request-submit.request-hidden{display:none!important}
.request-declaration{padding:16px;border:1px solid rgba(184,154,104,.35);border-radius:16px;background:rgba(184,154,104,.08)}
@media(max-width:1050px){.request-tabs{grid-template-columns:repeat(3,minmax(0,1fr));padding-top:12px}.request-tab-btn{font-size:13px}}
@media(max-width:767px){.request-shell{margin:0;border-radius:0;padding:18px}.request-tabs{grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;padding:10px 7px 0}.request-tab-btn{min-height:62px;font-size:12px}.request-choice-grid{grid-template-columns:1fr}.request-hero{padding:40px 18px}.request-actions{align-items:stretch;flex-direction:column}.request-nav-group{display:grid;grid-template-columns:1fr 1fr}.request-submit,.request-nav-btn{width:100%}}
</style>

<section class="request-hero">
  <div class="request-hero-inner">
    <h1><?= $isArabic ? 'طلب مبادرة قبل التنفيذ' : 'Pre-Execution Initiative Request' ?></h1>
    <p><?= $isArabic ? 'استخدم هذا النموذج لطلب الموافقة على المبادرة قبل تنفيذها. بعد الموافقة والتنفيذ، يُستكمل تقرير الإنجاز في نموذج منفصل.' : 'Use this form to request approval before implementation. After approval and delivery, complete the separate initiative report.' ?></p>
  </div>
</section>

<?php if ($errors): ?>
  <div class="alert alert-danger request-alert">
    <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= reqh($error) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success request-alert">
    <?= $isArabic ? 'تم إرسال الطلب بنجاح. رقم الطلب:' : 'Request submitted successfully. Request ID:' ?>
    <strong><?= reqh($requestId) ?></strong>
  </div>
<?php endif; ?>

<main class="request-shell">
  <form method="post" enctype="multipart/form-data" id="initiativeRequestForm">
    <input type="hidden" name="_csrf" value="<?= reqh($_SESSION['initiative_request_csrf']) ?>">

    <nav class="request-tabs" aria-label="<?= $isArabic ? 'أقسام نموذج طلب المبادرة' : 'Initiative request sections' ?>">
      <button type="button" class="request-tab-btn active" data-request-tab="requester-section"><span class="request-tab-number">1</span><span><?= $isArabic ? 'بيانات مقدم الطلب' : 'Requester Details' ?></span></button>
      <button type="button" class="request-tab-btn" data-request-tab="initiative-section"><span class="request-tab-number">2</span><span><?= $isArabic ? 'معلومات المبادرة' : 'Initiative Information' ?></span></button>
      <button type="button" class="request-tab-btn" data-request-tab="execution-section"><span class="request-tab-number">3</span><span><?= $isArabic ? 'التنفيذ' : 'Implementation' ?></span></button>
      <button type="button" class="request-tab-btn" data-request-tab="agreements-section"><span class="request-tab-number">4</span><span><?= $isArabic ? 'الاتفاقيات والشركاء' : 'Agreements and Partners' ?></span></button>
      <button type="button" class="request-tab-btn" data-request-tab="requirements-section"><span class="request-tab-number">5</span><span><?= $isArabic ? 'المتطلبات' : 'Requirements' ?></span></button>
      <button type="button" class="request-tab-btn" data-request-tab="approval-section"><span class="request-tab-number">6</span><span><?= $isArabic ? 'الاستدامة والموافقة' : 'Sustainability and Approval' ?></span></button>
    </nav>

    <div class="request-progress">
      <div class="request-progress-track" id="requestProgressTrack" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="request-progress-fill" id="requestProgressFill"></div>
      </div>
      <span class="request-progress-percent" id="requestProgressPercent">0%</span>
    </div>

    <section class="request-section active" id="requester-section">
      <h2 class="request-section-title"><?= $isArabic ? '1. بيانات مقدم الطلب' : '1. Requester Details' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title request-order-1"><?= $isArabic ? 'المعلومات الشخصية والتواصل' : 'Personal and Contact Information' ?></div>
        <div class="col-md-4 request-order-2"><label class="request-label"><?= $isArabic ? 'اسم مقدم الطلب' : 'Requester Name' ?></label><input class="form-control request-input" name="requester_name" value="<?= reqh($_POST['requester_name'] ?? '') ?>" required></div>
        <div class="col-12 request-order-5 request-subsection-title"><?= $isArabic ? 'الصفة والجهة التابعة' : 'Role and Affiliated Entity' ?></div>
        <div class="col-12 request-order-6">
          <label class="request-label"><?= $isArabic ? 'صفة مقدم الطلب' : 'Requester Type' ?></label>
          <?php $selectedRequesterType = $_POST['requester_type'] ?? ''; ?>
          <div class="request-choice-grid">
            <?php foreach ($requesterTypes as $value => $label): ?>
              <label class="request-choice">
                <input type="radio" name="requester_type" value="<?= reqh($value) ?>" <?= $selectedRequesterType === $value ? 'checked' : '' ?>>
                <span><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></span>
                <?php if ($value === 'other'): ?><span class="request-other <?= $selectedRequesterType === 'other' ? '' : 'request-hidden' ?>" data-other-for="requester_type"><input class="form-control request-input" name="requester_type_other" value="<?= reqh($_POST['requester_type_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'حدد الصفة' : 'Specify the type' ?>"></span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-4 request-order-3"><label class="request-label"><?= $isArabic ? 'البريد الإلكتروني' : 'Email Address' ?></label><input type="email" class="form-control request-input" name="requester_email" value="<?= reqh($_POST['requester_email'] ?? ($_SESSION['user_email'] ?? '')) ?>" required></div>
        <div class="col-md-4 request-order-4"><label class="request-label"><?= $isArabic ? 'رقم الهاتف' : 'Mobile Number' ?></label><input class="form-control request-input" name="requester_mobile" value="<?= reqh($_POST['requester_mobile'] ?? '') ?>"></div>
        <div class="col-md-6 request-order-7">
          <label class="request-label"><?= $isArabic ? 'الكلية أو الجهة' : 'College or Entity' ?></label>
          <?php $selectedEntity = $_POST['entity'] ?? ''; ?>
          <select class="form-select request-input" name="entity" id="entitySelect" required>
            <option value=""><?= $isArabic ? 'اختر الكلية أو الجهة' : 'Select college or entity' ?></option>
            <?php foreach ($entityOptions as $value => $label): ?><option value="<?= reqh($value) ?>" <?= $selectedEntity === $value ? 'selected' : '' ?>><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></option><?php endforeach; ?>
          </select>
          <div class="<?= $selectedEntity === 'other' ? '' : 'request-hidden' ?>" data-select-other="entity"><input class="form-control request-input mt-2" name="entity_other" value="<?= reqh($_POST['entity_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'اكتب اسم الجهة' : 'Enter entity name' ?>"></div>
        </div>
        <div class="col-md-6 request-order-8">
          <label class="request-label"><?= $isArabic ? 'القسم التابع للكلية أو الجهة' : 'Department within the College or Entity' ?></label>
          <select class="form-select request-input" name="department" id="departmentSelect" required>
            <option value=""><?= $isArabic ? 'اختر القسم' : 'Select department' ?></option>
          </select>
          <div class="<?= ($_POST['department'] ?? '') === 'other' ? '' : 'request-hidden' ?>" id="departmentOtherWrap">
            <input class="form-control request-input mt-2" name="department_other" value="<?= reqh($_POST['department_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'اكتب اسم القسم' : 'Enter department name' ?>">
          </div>
        </div>
      </div>
    </section>

    <section class="request-section" id="initiative-section">
      <h2 class="request-section-title"><?= $isArabic ? '2. معلومات المبادرة' : '2. Initiative Information' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title request-order-1"><?= $isArabic ? 'فكرة المبادرة وهدفها' : 'Initiative Idea and Objective' ?></div>
        <div class="col-12 request-order-2"><label class="request-label"><?= $isArabic ? 'عنوان المبادرة' : 'Initiative Title' ?></label><input class="form-control request-input" name="title" value="<?= reqh($_POST['title'] ?? '') ?>" required><div class="request-help"><?= $isArabic ? 'اكتب عنوانًا واضحًا ومختصرًا.' : 'Enter a clear, concise title.' ?></div></div>
        <div class="col-12 request-subsection-title request-order-5"><?= $isArabic ? 'التصنيف والجمهور المستهدف' : 'Classification and Target Audience' ?></div>
        <div class="col-md-6 request-order-6">
          <label class="request-label"><?= $isArabic ? 'نوع المبادرة الرئيسي' : 'Primary Initiative Type' ?></label>
          <?php $selectedPrimaryType = $_POST['primary_type'] ?? ''; ?>
          <select class="form-select request-input" name="primary_type" id="primaryTypeSelect" required>
            <option value=""><?= $isArabic ? 'اختر النوع الرئيسي' : 'Select primary type' ?></option>
            <?php foreach ($initiativeTypes as $value => $label): ?><option value="<?= reqh($value) ?>" <?= $selectedPrimaryType === $value ? 'selected' : '' ?>><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></option><?php endforeach; ?>
          </select>
          <div class="<?= $selectedPrimaryType === 'other' ? '' : 'request-hidden' ?>" data-select-other="primary_type"><input class="form-control request-input mt-2" name="primary_type_other" value="<?= reqh($_POST['primary_type_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'حدد النوع الآخر' : 'Specify the other type' ?>"></div>
        </div>
        <div class="col-md-6 request-order-7">
          <label class="request-label"><?= $isArabic ? 'أنواع ثانوية (اختياري)' : 'Secondary Types (Optional)' ?></label>
          <?php $selectedSecondary = reqarr('secondary_types'); ?>
          <select class="form-select request-input" name="secondary_types[]" multiple size="5"><?php foreach ($initiativeTypes as $value => $label): if ($value === 'other') continue; ?><option value="<?= reqh($value) ?>" <?= in_array($value, $selectedSecondary, true) ? 'selected' : '' ?>><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></option><?php endforeach; ?></select>
          <div class="request-help"><?= $isArabic ? 'يمكن اختيار أكثر من نوع.' : 'You may select more than one type.' ?></div>
        </div>
        <div class="col-12 request-order-3"><label class="request-label"><?= $isArabic ? 'وصف مختصر للمبادرة' : 'Brief Initiative Description' ?></label><textarea class="form-control request-input" name="description" maxlength="1600" required><?= reqh($_POST['description'] ?? '') ?></textarea><div class="request-help"><?= $isArabic ? 'اشرح فكرة المبادرة وآلية تنفيذها بإيجاز.' : 'Briefly explain the idea and how it will be implemented.' ?></div></div>
        <div class="col-12 request-order-4"><label class="request-label"><?= $isArabic ? 'الهدف الرئيسي للمبادرة' : 'Main Initiative Objective' ?></label><textarea class="form-control request-input" name="objective" maxlength="1200" required><?= reqh($_POST['objective'] ?? '') ?></textarea></div>
        <div class="col-12 request-order-8">
          <label class="request-label"><?= $isArabic ? 'الجمهور المستهدف (اختر كل ما ينطبق)' : 'Target Audience (Select all that apply)' ?></label>
          <?php $selectedTargets = reqarr('target_groups'); ?>
          <div class="request-choice-grid">
            <?php foreach ($targetGroups as $value => $label): ?>
              <label class="request-choice">
                <input type="checkbox" name="target_groups[]" value="<?= reqh($value) ?>" <?= in_array($value, $selectedTargets, true) ? 'checked' : '' ?>>
                <span><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></span>
                <?php if ($value === 'other'): ?><span class="request-other <?= in_array('other', $selectedTargets, true) ? '' : 'request-hidden' ?>" data-other-for="target_groups"><input class="form-control request-input" name="target_group_other" value="<?= reqh($_POST['target_group_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'حدد الجمهور الآخر' : 'Specify other audience' ?>"></span><?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-6 request-order-9"><label class="request-label"><?= $isArabic ? 'العدد المتوقع للمشاركين أو المستفيدين' : 'Expected Number of Participants or Beneficiaries' ?></label><input type="number" min="0" class="form-control request-input" name="expected_participants" value="<?= reqh($_POST['expected_participants'] ?? '') ?>" required></div>
      </div>
    </section>

    <section class="request-section" id="execution-section">
      <h2 class="request-section-title"><?= $isArabic ? '3. التنفيذ' : '3. Implementation' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'التوقيت والمكان' : 'Timing and Location' ?></div>
        <div class="col-md-6"><label class="request-label"><?= $isArabic ? 'تاريخ البداية المتوقع' : 'Expected Start Date' ?></label><input type="date" class="form-control request-input" name="start_date" value="<?= reqh($_POST['start_date'] ?? '') ?>" required></div>
        <div class="col-md-6"><label class="request-label"><?= $isArabic ? 'تاريخ النهاية المتوقع' : 'Expected End Date' ?></label><input type="date" class="form-control request-input" name="end_date" value="<?= reqh($_POST['end_date'] ?? '') ?>" required></div>
        <div class="col-12">
          <label class="request-label"><?= $isArabic ? 'نطاق التنفيذ' : 'Implementation Scope' ?></label>
          <?php $selectedScope = $_POST['scope'] ?? ''; $scopes = ['within_uob' => ['داخل جامعة البحرين', 'Within UOB'], 'outside_uob' => ['خارج جامعة البحرين', 'Outside UOB'], 'virtual' => ['عن بُعد', 'Virtual'], 'hybrid' => ['مختلط', 'Hybrid'], 'other' => ['أخرى', 'Other']]; ?>
          <div class="request-choice-grid"><?php foreach ($scopes as $value => $label): ?><label class="request-choice"><input type="radio" name="scope" value="<?= reqh($value) ?>" <?= $selectedScope === $value ? 'checked' : '' ?>><span><?= reqh($isArabic ? $label[0] : $label[1]) ?></span><?php if ($value === 'other'): ?><span class="request-other <?= $selectedScope === 'other' ? '' : 'request-hidden' ?>" data-other-for="scope"><input class="form-control request-input" name="scope_other" value="<?= reqh($_POST['scope_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'حدد النطاق' : 'Specify scope' ?>"></span><?php endif; ?></label><?php endforeach; ?></div>
        </div>
        <div class="col-12"><label class="request-label"><?= $isArabic ? 'مكان التنفيذ المقترح' : 'Proposed Venue or Location' ?></label><input class="form-control request-input" name="venue" value="<?= reqh($_POST['venue'] ?? '') ?>" placeholder="<?= $isArabic ? 'مثال: جامعة البحرين، مدرسة، مؤسسة، منصة افتراضية' : 'Example: UOB, school, organization, or virtual platform' ?>" required></div>
      </div>
    </section>

    <section class="request-section" id="agreements-section">
      <h2 class="request-section-title"><?= $isArabic ? '4. الاتفاقيات والشركاء' : '4. Agreements and Partners' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'الارتباط باتفاقية قائمة' : 'Existing Agreement Link' ?></div>
        <div class="col-12">
          <label class="request-label"><?= $isArabic ? 'هل المبادرة مرتبطة باتفاقية أو مذكرة تفاهم قائمة؟' : 'Is the initiative linked to an existing agreement or memorandum of understanding?' ?></label>
          <?php $related = $_POST['related_agreement'] ?? ''; ?>
          <div class="request-choice-grid"><label class="request-choice"><input type="radio" name="related_agreement" value="yes" <?= $related === 'yes' ? 'checked' : '' ?>><?= $isArabic ? 'نعم' : 'Yes' ?></label><label class="request-choice"><input type="radio" name="related_agreement" value="no" <?= $related === 'no' ? 'checked' : '' ?>><?= $isArabic ? 'لا' : 'No' ?></label></div>
        </div>
        <div class="col-12 <?= $related === 'yes' ? '' : 'request-hidden' ?>" id="agreementWrap">
          <label class="request-label"><?= $isArabic ? 'اسم الاتفاقية أو الجهة الشريكة' : 'Agreement Name or Partner Entity' ?></label>
          <select class="form-select request-input" name="agreement_code"><option value=""><?= $isArabic ? 'اختر الاتفاقية' : 'Select agreement' ?></option><?php foreach ($agreements as $code => $agreement): ?><option value="<?= reqh($code) ?>" <?= ($_POST['agreement_code'] ?? '') === (string)$code ? 'selected' : '' ?>><?= reqh($code . ' — ' . ($agreement['اسم الاتفاقية'] ?? '')) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'الشريك الخارجي' : 'External Partner' ?></div>
        <div class="col-12">
          <label class="request-label"><?= $isArabic ? 'هل توجد جهة خارجية مشاركة؟' : 'Is an External Partner Involved?' ?></label>
          <?php $hasPartner = $_POST['has_external_partner'] ?? ''; ?>
          <div class="request-choice-grid"><label class="request-choice"><input type="radio" name="has_external_partner" value="yes" <?= $hasPartner === 'yes' ? 'checked' : '' ?>><?= $isArabic ? 'نعم' : 'Yes' ?></label><label class="request-choice"><input type="radio" name="has_external_partner" value="no" <?= $hasPartner === 'no' ? 'checked' : '' ?>><?= $isArabic ? 'لا' : 'No' ?></label></div>
        </div>
        <div class="col-12 <?= $hasPartner === 'yes' ? '' : 'request-hidden' ?>" id="partnerWrap">
          <div class="row g-3"><div class="col-md-6"><label class="request-label"><?= $isArabic ? 'اسم الجهة الخارجية' : 'External Partner Name' ?></label><input class="form-control request-input" name="partner_name" value="<?= reqh($_POST['partner_name'] ?? '') ?>"></div><div class="col-md-6"><label class="request-label"><?= $isArabic ? 'دورها في المبادرة' : 'Role in the Initiative' ?></label><input class="form-control request-input" name="partner_role" value="<?= reqh($_POST['partner_role'] ?? '') ?>"></div></div>
        </div>
      </div>
    </section>

    <section class="request-section" id="requirements-section">
      <h2 class="request-section-title"><?= $isArabic ? '5. المتطلبات' : '5. Requirements' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'الدعم والموارد المطلوبة' : 'Required Support and Resources' ?></div>
        <div class="col-12">
          <label class="request-label"><?= $isArabic ? 'ما المتطلبات اللازمة لتنفيذ المبادرة؟' : 'What Is Required to Implement the Initiative?' ?></label>
          <?php $selectedResources = reqarr('resources'); ?>
          <div class="request-choice-grid"><?php foreach ($resourceOptions as $value => $label): ?><label class="request-choice"><input type="checkbox" name="resources[]" value="<?= reqh($value) ?>" <?= in_array($value, $selectedResources, true) ? 'checked' : '' ?>><span><?= reqh($isArabic ? $label['ar'] : $label['en']) ?></span><?php if ($value === 'other'): ?><span class="request-other <?= in_array('other', $selectedResources, true) ? '' : 'request-hidden' ?>" data-other-for="resources"><input class="form-control request-input" name="resource_other" value="<?= reqh($_POST['resource_other'] ?? '') ?>" placeholder="<?= $isArabic ? 'حدد المتطلب' : 'Specify requirement' ?>"></span><?php endif; ?></label><?php endforeach; ?></div>
        </div>
        <div class="col-md-6"><label class="request-label"><?= $isArabic ? 'الميزانية التقديرية (إن وجدت)' : 'Estimated Budget (if any)' ?></label><input type="number" min="0" step="0.001" class="form-control request-input" name="estimated_budget" value="<?= reqh($_POST['estimated_budget'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="request-label"><?= $isArabic ? 'هل تحتاج المبادرة إلى دعم إعلامي؟' : 'Does the Initiative Need Media Support?' ?></label><select class="form-select request-input" name="needs_media"><option value=""><?= $isArabic ? 'اختر' : 'Select' ?></option><option value="yes" <?= ($_POST['needs_media'] ?? '') === 'yes' ? 'selected' : '' ?>><?= $isArabic ? 'نعم' : 'Yes' ?></option><option value="no" <?= ($_POST['needs_media'] ?? '') === 'no' ? 'selected' : '' ?>><?= $isArabic ? 'لا' : 'No' ?></option></select></div>
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'المرفقات الداعمة' : 'Supporting Attachments' ?></div>
        <div class="col-12"><label class="request-label"><?= $isArabic ? 'ملفات داعمة للطلب (اختياري، بحد أقصى 5 ملفات)' : 'Supporting Files (Optional, Maximum 5 Files)' ?></label><input type="file" class="form-control request-input" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"><div class="request-help"><?= $isArabic ? 'الحد الأقصى لحجم كل ملف 10 ميغابايت.' : 'Maximum size per file is 10 MB.' ?></div></div>
      </div>
    </section>

    <section class="request-section" id="approval-section">
      <h2 class="request-section-title"><?= $isArabic ? '6. الاستدامة والموافقة' : '6. Sustainability and Approval' ?></h2>
      <div class="row g-4">
        <div class="col-12 request-subsection-title"><?= $isArabic ? 'الاستدامة' : 'Sustainability' ?></div>
        <div class="col-12">
          <label class="request-label"><?= $isArabic ? 'هل تدعم المبادرة أهداف التنمية المستدامة؟' : 'Does the Initiative Support the Sustainable Development Goals?' ?></label>
          <?php $supports = $_POST['supports_sdg'] ?? ''; ?>
          <div class="request-choice-grid"><label class="request-choice"><input type="radio" name="supports_sdg" value="yes" <?= $supports === 'yes' ? 'checked' : '' ?>><?= $isArabic ? 'نعم' : 'Yes' ?></label><label class="request-choice"><input type="radio" name="supports_sdg" value="no" <?= $supports === 'no' ? 'checked' : '' ?>><?= $isArabic ? 'لا' : 'No' ?></label></div>
        </div>
        <div class="col-12 <?= $supports === 'yes' ? '' : 'request-hidden' ?>" id="sdgWrap">
          <label class="request-label"><?= $isArabic ? 'اختر أهداف التنمية المستدامة المرتبطة' : 'Select the Relevant SDGs' ?></label>
          <?php $selectedSdgs = reqarr('sdg_goals'); ?>
          <div class="request-choice-grid"><?php foreach ($sdgGoals as $value => $label): ?><label class="request-choice"><input type="checkbox" name="sdg_goals[]" value="<?= reqh($value) ?>" <?= in_array($value, $selectedSdgs, true) ? 'checked' : '' ?>><span><?= reqh($value . ' — ' . ($isArabic ? $label['ar'] : $label['en'])) ?></span></label><?php endforeach; ?></div>
        </div>
      </div>
      <div class="request-subsection-title mt-4 mb-3"><?= $isArabic ? 'الإقرار النهائي' : 'Final Declaration' ?></div>
      <label class="request-declaration"><input type="checkbox" name="declaration" value="yes" <?= ($_POST['declaration'] ?? '') === 'yes' ? 'checked' : '' ?>> <span><?= $isArabic ? 'أقر بأن المعلومات المقدمة صحيحة، وأن تنفيذ المبادرة لن يبدأ قبل الحصول على الموافقة الرسمية.' : 'I confirm that the information is accurate and that implementation will not begin before formal approval is granted.' ?></span></label>
    </section>

    <div class="request-actions">
      <div class="request-nav-group">
        <button type="button" class="request-nav-btn" id="previousSectionBtn"><?= $isArabic ? 'السابق' : 'Previous' ?></button>
        <button type="button" class="request-nav-btn" id="nextSectionBtn"><?= $isArabic ? 'التالي' : 'Next' ?></button>
      </div>
      <button type="submit" class="request-submit request-hidden" id="submitRequestBtn"><?= $isArabic ? 'إرسال طلب المبادرة' : 'Submit Initiative Request' ?></button>
    </div>
  </form>
</main>

<script>
const UOB_STRUCTURE = <?= json_encode($uobStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const SELECTED_DEPARTMENT = <?= json_encode($_POST['department'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const DEPARTMENT_PLACEHOLDER = <?= json_encode($isArabic ? 'اختر القسم' : 'Select department', JSON_UNESCAPED_UNICODE) ?>;
const OTHER_LABEL = <?= json_encode($isArabic ? 'أخرى' : 'Other', JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function () {
  const tabButtons = Array.from(document.querySelectorAll('.request-tab-btn'));
  const tabSections = Array.from(document.querySelectorAll('.request-section'));
  const previousSectionBtn = document.getElementById('previousSectionBtn');
  const nextSectionBtn = document.getElementById('nextSectionBtn');
  const submitRequestBtn = document.getElementById('submitRequestBtn');
  let currentSection = 0;

  function showSection(index) {
    currentSection = Math.max(0, Math.min(index, tabSections.length - 1));
    tabButtons.forEach((button, buttonIndex) => button.classList.toggle('active', buttonIndex === currentSection));
    tabSections.forEach((section, sectionIndex) => section.classList.toggle('active', sectionIndex === currentSection));
    if (previousSectionBtn) previousSectionBtn.disabled = currentSection === 0;
    if (nextSectionBtn) nextSectionBtn.classList.toggle('request-hidden', currentSection === tabSections.length - 1);
    submitRequestBtn?.classList.toggle('request-hidden', currentSection !== tabSections.length - 1);
    document.querySelector('.request-shell')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  tabButtons.forEach((button, index) => button.addEventListener('click', () => showSection(index)));
  previousSectionBtn?.addEventListener('click', () => showSection(currentSection - 1));
  nextSectionBtn?.addEventListener('click', () => showSection(currentSection + 1));

  const requestForm = document.getElementById('initiativeRequestForm');
  const progressFill = document.getElementById('requestProgressFill');
  const progressPercent = document.getElementById('requestProgressPercent');
  const progressTrack = document.getElementById('requestProgressTrack');

  function isConditionallyAvailable(field) {
    return !field.disabled && !field.closest('.request-hidden');
  }

  function updateProgress() {
    if (!requestForm) return;

    const fields = Array.from(requestForm.querySelectorAll('input[name], select[name], textarea[name]'))
      .filter(field => field.type !== 'hidden' && field.type !== 'submit' && field.type !== 'button' && isConditionallyAvailable(field));

    const units = new Map();

    fields.forEach(field => {
      const isChoice = field.type === 'radio' || field.type === 'checkbox';
      const key = isChoice ? `group:${field.name}` : `field:${field.name}`;

      if (!units.has(key)) units.set(key, []);
      units.get(key).push(field);
    });

    let completed = 0;
    units.forEach(group => {
      const firstField = group[0];
      let isComplete = false;

      if (firstField.type === 'radio' || firstField.type === 'checkbox') {
        isComplete = group.some(field => field.checked);
      } else if (firstField.type === 'file') {
        isComplete = firstField.files && firstField.files.length > 0;
      } else if (firstField.tagName === 'SELECT' && firstField.multiple) {
        isComplete = firstField.selectedOptions.length > 0;
      } else {
        isComplete = String(firstField.value || '').trim() !== '';
      }

      if (isComplete) completed++;
    });

    const total = units.size;
    const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
    if (progressFill) progressFill.style.width = `${percentage}%`;
    if (progressPercent) progressPercent.textContent = `${percentage}%`;
    progressTrack?.setAttribute('aria-valuenow', String(percentage));
  }

  requestForm?.addEventListener('input', updateProgress);
  requestForm?.addEventListener('change', () => window.setTimeout(updateProgress, 0));

  requestForm?.addEventListener('submit', function (event) {
    const invalidField = requestForm.querySelector(':invalid');
    if (!invalidField) return;

    const invalidSection = invalidField.closest('.request-section');
    const invalidSectionIndex = tabSections.indexOf(invalidSection);
    if (invalidSectionIndex >= 0) {
      event.preventDefault();
      showSection(invalidSectionIndex);
      window.setTimeout(() => invalidField.reportValidity(), 50);
    }
  });

  showSection(0);

  function radioValue(name) {
    return document.querySelector(`input[name="${name}"]:checked`)?.value || '';
  }

  function toggleRadioOther(name) {
    const value = radioValue(name);
    document.querySelectorAll(`[data-other-for="${name}"]`).forEach(el => el.classList.toggle('request-hidden', value !== 'other'));
  }

  function toggleCheckboxOther(name) {
    const checked = !!document.querySelector(`input[name="${name}[]"][value="other"]:checked`);
    document.querySelectorAll(`[data-other-for="${name}"]`).forEach(el => el.classList.toggle('request-hidden', !checked));
  }

  ['requester_type', 'scope'].forEach(name => {
    document.querySelectorAll(`input[name="${name}"]`).forEach(input => input.addEventListener('change', () => toggleRadioOther(name)));
    toggleRadioOther(name);
  });

  ['target_groups', 'resources'].forEach(name => {
    document.querySelectorAll(`input[name="${name}[]"]`).forEach(input => input.addEventListener('change', () => toggleCheckboxOther(name)));
    toggleCheckboxOther(name);
  });

  const entitySelect = document.getElementById('entitySelect');
  const entityOther = document.querySelector('[data-select-other="entity"]');
  const departmentSelect = document.getElementById('departmentSelect');
  const departmentOtherWrap = document.getElementById('departmentOtherWrap');

  function populateDepartments(preserveSelection = false) {
    if (!departmentSelect) return;

    const currentSelection = preserveSelection ? SELECTED_DEPARTMENT : '';
    const departments = UOB_STRUCTURE[entitySelect?.value] || [];
    departmentSelect.innerHTML = '';
    departmentSelect.add(new Option(DEPARTMENT_PLACEHOLDER, ''));

    departments.forEach(department => {
      departmentSelect.add(new Option(department, department));
    });

    departmentSelect.add(new Option(OTHER_LABEL, 'other'));

    if (currentSelection && [...departmentSelect.options].some(option => option.value === currentSelection)) {
      departmentSelect.value = currentSelection;
    } else if (entitySelect?.value === 'other') {
      departmentSelect.value = 'other';
    }

    departmentOtherWrap?.classList.toggle('request-hidden', departmentSelect.value !== 'other');
  }

  const toggleEntity = (preserveSelection = false) => {
    entityOther?.classList.toggle('request-hidden', entitySelect?.value !== 'other');
    populateDepartments(preserveSelection);
  };

  entitySelect?.addEventListener('change', () => toggleEntity(false));
  departmentSelect?.addEventListener('change', () => {
    departmentOtherWrap?.classList.toggle('request-hidden', departmentSelect.value !== 'other');
  });
  toggleEntity(true);

  const primaryTypeSelect = document.getElementById('primaryTypeSelect');
  const primaryTypeOther = document.querySelector('[data-select-other="primary_type"]');
  const togglePrimaryType = () => primaryTypeOther?.classList.toggle('request-hidden', primaryTypeSelect?.value !== 'other');
  primaryTypeSelect?.addEventListener('change', togglePrimaryType);
  togglePrimaryType();

  const agreementWrap = document.getElementById('agreementWrap');
  const toggleAgreement = () => agreementWrap?.classList.toggle('request-hidden', radioValue('related_agreement') !== 'yes');
  document.querySelectorAll('input[name="related_agreement"]').forEach(input => input.addEventListener('change', toggleAgreement));
  toggleAgreement();

  const partnerWrap = document.getElementById('partnerWrap');
  const togglePartner = () => partnerWrap?.classList.toggle('request-hidden', radioValue('has_external_partner') !== 'yes');
  document.querySelectorAll('input[name="has_external_partner"]').forEach(input => input.addEventListener('change', togglePartner));
  togglePartner();

  const sdgWrap = document.getElementById('sdgWrap');
  const toggleSdg = () => sdgWrap?.classList.toggle('request-hidden', radioValue('supports_sdg') !== 'yes');
  document.querySelectorAll('input[name="supports_sdg"]').forEach(input => input.addEventListener('change', toggleSdg));
  toggleSdg();
  updateProgress();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>