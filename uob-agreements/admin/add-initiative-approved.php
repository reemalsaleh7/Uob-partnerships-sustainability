<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_email'])) {
  header("Location: ../login.php?to=admin/add-initiative-approved.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_evidence_file_action'])) {
  header('Content-Type: application/json; charset=utf-8');
  $action = (string)$_POST['_evidence_file_action'];
  $uploadDir = __DIR__ . '/../uploads/initiative-evidence/';
  $ownerPrefix = substr(hash('sha256', (string)$_SESSION['user_email']), 0, 12) . '-';

  if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

  if ($action === 'upload') {
    if (empty($_FILES['evidence_file']) || ($_FILES['evidence_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      echo json_encode(['ok'=>false,'message'=>'Upload failed.']);
      exit;
    }

    $originalName = basename((string)$_FILES['evidence_file']['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','mp4','mov'];
    if (!in_array($extension, $allowedExtensions, true)) {
      echo json_encode(['ok'=>false,'message'=>'Unsupported file type.']);
      exit;
    }

    $storedName = $ownerPrefix . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . $storedName;
    if (!move_uploaded_file($_FILES['evidence_file']['tmp_name'], $destination)) {
      echo json_encode(['ok'=>false,'message'=>'Could not save the file.']);
      exit;
    }

    echo json_encode([
      'ok'=>true,
      'file'=>[
        'name'=>$originalName,
        'path'=>'uploads/initiative-evidence/' . $storedName,
        'url'=>'../uploads/initiative-evidence/' . $storedName,
      ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'delete') {
    $path = trim((string)($_POST['path'] ?? ''));
    $basename = basename($path);
    $allowedPath = $path === 'uploads/initiative-evidence/' . $basename && str_starts_with($basename, $ownerPrefix);
    if ($allowedPath && is_file($uploadDir . $basename)) @unlink($uploadDir . $basename);
    echo json_encode(['ok'=>true]);
    exit;
  }

  echo json_encode(['ok'=>false,'message'=>'Invalid action.']);
  exit;
}

$requestIdFromUrl = $_GET['request_id'] ?? '';
// =========================
// MULTI-LANGUAGE SETUP - يجب أن يكون بعد include header.php
// =========================
// لا نستخدم t() هنا لأن header.php لم يتم تضمينه بعد

$agreements = readAgreements();
$agreementPrefill = trim($_GET['agreement'] ?? '');

// =========================
// تعيين المتغيرات قبل تضمين header.php
// =========================
$pageTitle = "إضافة مبادرة";  // قيمة مؤقتة، سيتم ترجمتها بعد header.php

$breadcrumb = [
  ['label' => 'المبادرات', 'href' => '../initiatives.php', 'active' => false],
  ['label' => 'إضافة مبادرة', 'href' => '#', 'active' => true],
];
// Landing layout: hide title band + use full width
$hidePageHeader = true;
$mainContainer = false;

// =========================
// تضمين header.php (هنا يتم تعريف دالة t())
// =========================
require_once __DIR__ . '/../header.php';

// ✅ تحديد اللغة الحالية
$isArabic = ($_SESSION['lang'] ?? 'ar') === 'ar';

// =========================
// الآن يمكن استخدام دالة t() بعد تضمين header.php
// =========================
$pageTitle = t('add_initiative_page_title');
$pageSubtitle = t('fill_required_fields');
$breadcrumb = [
  ['label' => t('initiatives'), 'href' => '../initiatives.php', 'active' => false],
  ['label' => t('add_initiative_page_title'), 'href' => '#', 'active' => true],
];

$errors = [];
$success = false;

// ... باقي الكود كما هو ...

/* =========================
   Options from your sample sheets
   ========================= */
$initiativeTypes = [
  ['value'=>'workshop_training','ar'=>'ورشة عمل / تدريب','en'=>'Workshop / Training'],
  ['value'=>'lecture_seminar','ar'=>'محاضرة / ندوة','en'=>'Lecture / Seminar'],
  ['value'=>'conference_forum','ar'=>'مؤتمر / ملتقى / منتدى','en'=>'Conference / Forum'],
  ['value'=>'exhibition_fair','ar'=>'معرض / فعالية تعريفية','en'=>'Exhibition / Fair'],
  ['value'=>'competition_hackathon','ar'=>'مسابقة / هاكاثون','en'=>'Competition / Hackathon'],
  ['value'=>'cultural_sports_event','ar'=>'فعالية ثقافية / فنية / رياضية','en'=>'Cultural / Arts / Sports Event'],
  ['value'=>'field_visit','ar'=>'زيارة ميدانية','en'=>'Field Visit'],
  ['value'=>'exchange_program','ar'=>'برنامج تبادل','en'=>'Exchange Program'],
  ['value'=>'joint_research','ar'=>'بحث مشترك','en'=>'Joint Research'],
  ['value'=>'coordination_professional_meeting','ar'=>'اجتماع تنسيقي / مهني','en'=>'Coordination / Professional Meeting'],
  ['value'=>'student_initiative','ar'=>'مبادرة طلابية','en'=>'Student Initiative'],
  ['value'=>'community_engagement','ar'=>'مشاركة مجتمعية','en'=>'Community Engagement'],
  ['value'=>'volunteering_program','ar'=>'برنامج تطوعي','en'=>'Volunteering Program'],
  ['value'=>'consultation_advisory','ar'=>'استشارة / دور استشاري','en'=>'Consultation / Advisory Role'],
  ['value'=>'awareness_campaign','ar'=>'حملة توعوية / مشاركة إعلامية','en'=>'Awareness Campaign / Media Engagement'],
  ['value'=>'capacity_building_training','ar'=>'بناء القدرات وتدريب المجتمع','en'=>'Capacity Building & Community Training'],
  ['value'=>'community_partnerships','ar'=>'شراكة مجتمعية / مشروع مشترك','en'=>'Community Partnership / Joint Project'],
  ['value'=>'knowledge_transfer','ar'=>'نقل المعرفة','en'=>'Knowledge Transfer'],
  ['value'=>'tutoring_coaching_mentorship','ar'=>'إرشاد / تدريب / توجيه','en'=>'Tutoring / Coaching / Mentorship'],
  ['value'=>'professional_membership','ar'=>'عضوية / لجنة / تحكيم','en'=>'Professional Membership / Committee / Jury'],
  ['value'=>'media_article','ar'=>'مقال / نشر إعلامي','en'=>'Media / Newspaper Article'],
  ['value'=>'school_outreach','ar'=>'أنشطة تعليمية للمدارس','en'=>'School Outreach Activities'],
  ['value'=>'vulnerable_groups','ar'=>'دعم الفئات المحتاجة','en'=>'Support for Vulnerable Groups'],
  ['value'=>'sustainability_activities','ar'=>'أنشطة الاستدامة','en'=>'Sustainability Activities'],
  ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
  ['value'=>'volunteer_teaching_training','ar'=>'تعليم أو تدريب تطوعي للفئات المحتاجة','en'=>'Volunteer Teaching or Training for Vulnerable Groups'],
];

$targetGroups = [
  ['value'=>'university_students','ar'=>'طلبة الجامعة','en'=>'University students'],
  ['value'=>'professionals','ar'=>'المهنيون','en'=>'Professionals'],
  ['value'=>'open_public','ar'=>'مفتوح للجمهور','en'=>'Open to public'],
  ['value'=>'faculty_staff','ar'=>'أعضاء هيئة التدريس والموظفون','en'=>'Faculty and staff members'],
  ['value'=>'school_students','ar'=>'طلبة المدارس','en'=>'School students'],
  ['value'=>'alumni','ar'=>'الخريجون','en'=>'Alumni'],
  ['value'=>'local_community','ar'=>'المجتمع المحلي','en'=>'Local community'],
  ['value'=>'disadvantaged_groups','ar'=>'الفئات المحتاجة','en'=>'Disadvantaged groups'],
  ['value'=>'ngos','ar'=>'المنظمات غير الربحية / المجتمع المدني','en'=>'NGOs / Civil Society'],
  ['value'=>'government_entities','ar'=>'الجهات الحكومية','en'=>'Government Entities'],
  ['value'=>'private_sector','ar'=>'القطاع الخاص','en'=>'Private Sector'],
  ['value'=>'persons_with_disabilities','ar'=>'الأشخاص ذوو الإعاقة','en'=>'Persons with Disabilities'],
  ['value'=>'children_elderly','ar'=>'الأطفال / كبار السن','en'=>'Children / Older People'],
  ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
];

$initiativeDescriptors = [
  ['value'=>'research','ar'=>'البحث (المنشورات، التعاون)','en'=>'Research (publications, collaborations)'],
  ['value'=>'student_support','ar'=>'دعم الطلبة (المنح، التدريب العملي، إتاحة الفرص)','en'=>'Student support (scholarships, placements, access)'],
  ['value'=>'staff_practices','ar'=>'ممارسات الموظفين (العقود، الإنصاف، السياسات)','en'=>'Staff practices (contracts, equity, policies)'],
  ['value'=>'community_outreach','ar'=>'التواصل المجتمعي (التدريب، الشراكات، التطوع)','en'=>'Community outreach (training, partnerships, volunteering)'],
  ['value'=>'campus_operations','ar'=>'عمليات الحرم الجامعي (الطاقة، المياه، السكن، النفايات، الغذاء، النقل)','en'=>'Campus operations (energy, water, housing, waste, food, transport)'],
  ['value'=>'governance','ar'=>'الحوكمة أو السياسات أو التقارير','en'=>'Governance, policies, or reports'],
  ['value'=>'other','ar'=>'أخرى','en'=>'Others'],
];

$evidenceTypes = [
 'upload' => ['ar'=>'تحميل ملف (سياسة، تقرير، مجموعة بيانات، صورة)','en'=>'Upload a file (policy, report, dataset, photo)'],
 'url' => ['ar'=>'توفير رابط عام (مفضل)','en'=>'Provide a public URL link (preferred)'],
 'explanation' => ['ar'=>'شرح كتابي مختصر (فقط عند عدم توفر ملف أو رابط)','en'=>'Short written explanation (only if no file/link available)']
];
$publicSharingOptions = [
 ['value'=>'yes','ar'=>'نعم','en'=>'Yes'],
 ['value'=>'no','ar'=>'لا','en'=>'No'],
];
$departmentOptions = [
 ['value'=>'college_deanship_vp','ar'=>'كلية / عمادة / مكتب نائب الرئيس','en'=>'College / Deanship / Vice President Office'],
 ['value'=>'applied_studies','ar'=>'كلية الدراسات التطبيقية','en'=>'College of Applied Studies'],
 ['value'=>'arts','ar'=>'كلية الآداب','en'=>'College of Arts'],
 ['value'=>'law','ar'=>'كلية الحقوق','en'=>'College of Law'],
 ['value'=>'science','ar'=>'كلية العلوم','en'=>'College of Science'],
 ['value'=>'engineering','ar'=>'كلية الهندسة','en'=>'College of Engineering'],
 ['value'=>'it','ar'=>'كلية تقنية المعلومات','en'=>'College of Information Technology'],
 ['value'=>'business','ar'=>'كلية إدارة الأعمال','en'=>'College of Business Administration'],
 ['value'=>'btc','ar'=>'كلية محمد جابر الأنصاري لإعداد المعلمين','en'=>'Mohammed Jaber Al Ansari College for Teachers (BTC)'],
 ['value'=>'health_sport','ar'=>'كلية العلوم الصحية والرياضية','en'=>'College of Health and Sport Sciences'],
 ['value'=>'admission','ar'=>'عمادة القبول والتسجيل','en'=>'Deanship of Admission and Registration'],
 ['value'=>'student_affairs','ar'=>'عمادة شؤون الطلبة','en'=>'Deanship of Student Affairs'],
 ['value'=>'graduate_research','ar'=>'عمادة الدراسات العليا والبحث العلمي','en'=>'Deanship of Graduate Studies and Scientific Research'],
 ['value'=>'vppd','ar'=>'مكتب نائب الرئيس للشراكات والتطوير','en'=>'Vice President Office for Partnerships and Development'],
 ['value'=>'vp_academic','ar'=>'مكتب نائب الرئيس للشؤون الأكاديمية','en'=>'Vice President Office for Academic Affairs'],
 ['value'=>'elc','ar'=>'مركز اللغة الإنجليزية','en'=>'English Language Center'],
  ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
];

$academicDepartmentOptions = [
  ['value'=>'cas_administrative_programs','ar'=>'كلية التعليم التطبيقي — قسم البرامج الإدارية','en'=>'College of Applied Studies — Administrative Programs Department'],
  ['value'=>'cas_engineering_programs','ar'=>'كلية التعليم التطبيقي — قسم البرامج الهندسية','en'=>'College of Applied Studies — Engineering Programs Department'],

  ['value'=>'arts_arabic_islamic','ar'=>'كلية الآداب — قسم اللغة العربية والدراسات الإسلامية','en'=>'College of Arts — Arabic Language and Islamic Studies'],
  ['value'=>'arts_english','ar'=>'كلية الآداب — قسم اللغة الإنجليزية وآدابها','en'=>'College of Arts — English Language and Literature'],
  ['value'=>'arts_social_sciences','ar'=>'كلية الآداب — قسم العلوم الاجتماعية','en'=>'College of Arts — Social Sciences'],
  ['value'=>'arts_psychology','ar'=>'كلية الآداب — قسم علم النفس','en'=>'College of Arts — Psychology'],
  ['value'=>'arts_media_tourism_fine_arts','ar'=>'كلية الآداب — قسم الإعلام والسياحة والفنون','en'=>'College of Arts — Mass Communication, Tourism and Fine Arts'],

  ['value'=>'btc_initial_teacher_education','ar'=>'كلية محمد جابر الأنصاري للمعلمين — قسم إعداد المعلمين','en'=>'Mohammed Jaber Al Ansari College for Teachers — Initial Teacher Education'],
  ['value'=>'btc_arabic_islamic','ar'=>'كلية محمد جابر الأنصاري للمعلمين — قسم اللغة العربية والدراسات الإسلامية','en'=>'Mohammed Jaber Al Ansari College for Teachers — Arabic and Islamic Studies'],
  ['value'=>'btc_english','ar'=>'كلية محمد جابر الأنصاري للمعلمين — قسم تعليم اللغة الإنجليزية','en'=>'Mohammed Jaber Al Ansari College for Teachers — English Language Education'],
  ['value'=>'btc_math_science','ar'=>'كلية محمد جابر الأنصاري للمعلمين — قسم الرياضيات والعلوم','en'=>'Mohammed Jaber Al Ansari College for Teachers — Mathematics and Science Education'],
  ['value'=>'btc_education_studies','ar'=>'كلية محمد جابر الأنصاري للمعلمين — قسم الدراسات التربوية','en'=>'Mohammed Jaber Al Ansari College for Teachers — Education Studies'],

  ['value'=>'business_accounting','ar'=>'كلية إدارة الأعمال — قسم المحاسبة','en'=>'College of Business Administration — Accounting'],
  ['value'=>'business_economics_finance','ar'=>'كلية إدارة الأعمال — قسم الاقتصاد والتمويل','en'=>'College of Business Administration — Economics and Finance'],
  ['value'=>'business_management_marketing','ar'=>'كلية إدارة الأعمال — قسم الإدارة والتسويق','en'=>'College of Business Administration — Management and Marketing'],
  ['value'=>'business_islamic_banking','ar'=>'كلية إدارة الأعمال — قسم الصيرفة الإسلامية','en'=>'College of Business Administration — Islamic Banking'],

  ['value'=>'engineering_civil','ar'=>'كلية الهندسة — قسم الهندسة المدنية','en'=>'College of Engineering — Civil Engineering'],
  ['value'=>'engineering_architecture_interior','ar'=>'كلية الهندسة — قسم العمارة والتصميم الداخلي','en'=>'College of Engineering — Architecture and Interior Design'],
  ['value'=>'engineering_chemical','ar'=>'كلية الهندسة — قسم الهندسة الكيميائية','en'=>'College of Engineering — Chemical Engineering'],
  ['value'=>'engineering_electrical_electronics','ar'=>'كلية الهندسة — قسم الهندسة الكهربائية والإلكترونية','en'=>'College of Engineering — Electrical and Electronics Engineering'],
  ['value'=>'engineering_mechanical','ar'=>'كلية الهندسة — قسم الهندسة الميكانيكية','en'=>'College of Engineering — Mechanical Engineering'],

  ['value'=>'health_nursing','ar'=>'كلية العلوم الصحية والرياضية — قسم التمريض','en'=>'College of Health and Sport Sciences — Nursing'],
  ['value'=>'health_allied','ar'=>'كلية العلوم الصحية والرياضية — قسم العلوم الصحية المساندة','en'=>'College of Health and Sport Sciences — Allied Health'],
  ['value'=>'health_physical_education','ar'=>'كلية العلوم الصحية والرياضية — قسم التربية الرياضية','en'=>'College of Health and Sport Sciences — Physical Education'],

  ['value'=>'it_computer_engineering','ar'=>'كلية تقنية المعلومات — قسم هندسة الحاسوب','en'=>'College of Information Technology — Computer Engineering'],
  ['value'=>'it_computer_science','ar'=>'كلية تقنية المعلومات — قسم علم الحاسوب','en'=>'College of Information Technology — Computer Science'],
  ['value'=>'it_information_systems','ar'=>'كلية تقنية المعلومات — قسم نظم المعلومات','en'=>'College of Information Technology — Information Systems'],

  ['value'=>'law_public','ar'=>'كلية الحقوق — قسم القانون العام','en'=>'College of Law — Public Law'],
  ['value'=>'law_private','ar'=>'كلية الحقوق — قسم القانون الخاص','en'=>'College of Law — Private Law'],

  ['value'=>'science_biology','ar'=>'كلية العلوم — قسم الأحياء','en'=>'College of Science — Biology'],
  ['value'=>'science_chemistry','ar'=>'كلية العلوم — قسم الكيمياء','en'=>'College of Science — Chemistry'],
  ['value'=>'science_mathematics','ar'=>'كلية العلوم — قسم الرياضيات','en'=>'College of Science — Mathematics'],
  ['value'=>'science_physics','ar'=>'كلية العلوم — قسم الفيزياء','en'=>'College of Science — Physics'],

  ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
];

$sdgGoals = [
  ['value'=>'SDG 1',  'ar'=>'SDG 1 - القضاء على الفقر',                    'en'=>'SDG 1 No Poverty'],
  ['value'=>'SDG 2',  'ar'=>'SDG 2 - القضاء على الجوع',                    'en'=>'SDG 2 Zero Hunger'],
  ['value'=>'SDG 3',  'ar'=>'SDG 3 - الصحة الجيدة والرفاه',                'en'=>'SDG 3 Good Health and Well-being'],
  ['value'=>'SDG 4',  'ar'=>'SDG 4 - التعليم الجيد',                       'en'=>'SDG 4 Quality Education'],
  ['value'=>'SDG 5',  'ar'=>'SDG 5 - المساواة بين الجنسين',                'en'=>'SDG 5 Gender Equality'],
  ['value'=>'SDG 6',  'ar'=>'SDG 6 - المياه النظيفة والنظافة الصحية',      'en'=>'SDG 6 Clean Water and Sanitation'],
  ['value'=>'SDG 7',  'ar'=>'SDG 7 - طاقة نظيفة وبأسعار معقولة',           'en'=>'SDG 7 Affordable and Clean Energy'],
  ['value'=>'SDG 8',  'ar'=>'SDG 8 - العمل اللائق والنمو الاقتصادي',       'en'=>'SDG 8 Decent Work and Economic Growth'],
  ['value'=>'SDG 9',  'ar'=>'SDG 9 - الصناعة والابتكار والبنية التحتية',    'en'=>'SDG 9 Industry, Innovation and Infrastructure'],
  ['value'=>'SDG 10', 'ar'=>'SDG 10 - الحد من أوجه عدم المساواة',           'en'=>'SDG 10 Reduced Inequalities'],
  ['value'=>'SDG 11', 'ar'=>'SDG 11 - مدن ومجتمعات مستدامة',                'en'=>'SDG 11 Sustainable Cities and Communities'],
  ['value'=>'SDG 12', 'ar'=>'SDG 12 - الاستهلاك والإنتاج المسؤولان',         'en'=>'SDG 12 Responsible Consumption and Production'],
  ['value'=>'SDG 13', 'ar'=>'SDG 13 - العمل المناخي',                       'en'=>'SDG 13 Climate Action'],
  ['value'=>'SDG 14', 'ar'=>'SDG 14 - الحياة تحت الماء',                    'en'=>'SDG 14 Life Below Water'],
  ['value'=>'SDG 15', 'ar'=>'SDG 15 - الحياة في البر',                      'en'=>'SDG 15 Life on Land'],
  ['value'=>'SDG 16', 'ar'=>'SDG 16 - السلام والعدل والمؤسسات القوية',       'en'=>'SDG 16 Peace, Justice and Strong Institutions'],
  ['value'=>'SDG 17', 'ar'=>'SDG 17 - عقد الشراكات لتحقيق الأهداف',          'en'=>'SDG 17 Partnerships for the Goals'],
];

$outputSuggestions = [];
$allOutputs = [];

$files = [
  INITIATIVES_MASTER,
  __DIR__ . '/../data/initiatives.csv',
  __DIR__ . '/../data/initiatives2.csv'
];

foreach ($files as $file) {
  if (!file_exists($file)) continue;

  if (($handle = fopen($file, 'r')) !== false) {
    $header = fgetcsv($handle);
    if (!$header) {
      fclose($handle);
      continue;
    }

    $outputIndex = false;

    foreach ($header as $i => $col) {
      $col = trim((string)$col);
      if ($col === 'outputs' || str_contains($col, 'المخرجات')) {
        $outputIndex = $i;
        break;
      }
    }

    if ($outputIndex === false) {
      fclose($handle);
      continue;
    }

    while (($row = fgetcsv($handle)) !== false) {
      $text = trim((string)($row[$outputIndex] ?? ''));
      if ($text === '') continue;

      $parts = preg_split('/،|,|\||\-|\n/u', $text);

      foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && mb_strlen($p) >= 3) {
          $allOutputs[] = $p;
        }
      }
    }

    fclose($handle);
  }
}

if ($allOutputs) {
  $counts = array_count_values($allOutputs);
  arsort($counts);

  foreach (array_slice(array_keys($counts), 0, 12) as $out) {
    $outputSuggestions[] = $out;
  }
}

/* =========================
   Legacy CSV fields
   ========================= */
$fields = [
  'id',
  'agreement_code',
  'initiative_number',
  'entity',
  'coordinator',
  'title',
  'type',
  'start_date',
  'end_date',
  'location',
  'description',
  'target_group',
  'male_beneficiaries',
  'female_beneficiaries',
  'youth_18_35',
  'sdgs',
  'published',
  'news_link',
  'images_link',
  'outputs',
  'notes',
  'responsible_name','responsible_email','responsible_mobile','department_unit','department_unit_other','department_within_college','requester_department','requester_department_other','coordinator_type','coordinator_type_other',
  'implementation_scope','implementation_scope_other','initiative_descriptors','initiative_descriptor_other','initiative_objective','secondary_initiative_types','secondary_initiative_type_other','initiative_type_other','published_other','target_group_other','total_attendees','societal_impact',
  'resources_mobilized','environmental_impact','evidence_type','evidence_value',
  'press_release','public_sharing','faculty_staff_group',
  'external_entities','supporting_files','initiative_contributors','initiative_participants',
  'status',
  'activity_status','activity_recurrence','academic_year','duration_hours','provider_categories',
  'unspecified_beneficiaries','beneficiary_count_basis',
  'ranking_framework','the_areas','qs_categories',
  'primary_sdg','secondary_sdgs',
  'evidence_document_type','evidence_date','evidence_owner','evidence_public_access',
  'environmental_impact_types','environmental_before_value','environmental_after_value',
  'environmental_improvement_value','environmental_unit','environmental_measurement_basis','environmental_data_source',
  'international_participation','international_countries','international_country_count','international_participants',
  'international_partner','international_partner_type','international_collaboration_nature',
  'internal_funding_bhd','external_funding_amount','external_funding_currency','in_kind_support_bhd','funding_entity',
  'training_hours','trainees_count','volunteers_count','volunteer_hours_per_person','total_volunteer_hours',
  'media_coverage_type','media_outlet_name','media_headline','media_publication_date',
  'tv_channel','tv_program','tv_interview_topic','tv_interviewer','tv_uob_representatives',
  'tv_interview_date','tv_broadcast_status','tv_broadcast_scope','tv_interview_language',
  'tv_duration_minutes','tv_interview_link','tv_interview_highlights',
  'notes_vppd','submitted_by','submitted_at'
];

function postv(string $key, string $default = ''): string {
  return trim($_POST[$key] ?? $default);
}

function ensureInitiativesCsvSchema(string $file, array $fields): bool {
  if (!file_exists($file)) return true;
  $fp = fopen($file, 'r');
  if (!$fp) return false;
  $oldHeader = fgetcsv($fp) ?: [];
  if ($oldHeader === $fields) {
    fclose($fp);
    return true;
  }
  $rows = [];
  while (($row = fgetcsv($fp)) !== false) {
    $row = array_pad($row, count($oldHeader), '');
    $rows[] = array_combine($oldHeader, array_slice($row, 0, count($oldHeader))) ?: [];
  }
  fclose($fp);
  $temporaryFile = $file . '.schema-' . bin2hex(random_bytes(4)) . '.tmp';
  $out = fopen($temporaryFile, 'w');
  if (!$out) return false;
  fputcsv($out, $fields);
  foreach ($rows as $existingRow) {
    fputcsv($out, array_map(fn($field) => $existingRow[$field] ?? '', $fields));
  }
  fclose($out);
  if (!rename($temporaryFile, $file)) {
    @unlink($temporaryFile);
    return false;
  }
  return true;
}

$approvalRequestsFile = __DIR__ . '/../data/initiative_requests.csv';
$approvedRequest = null;

function findApprovedRequest($file, $requestId) {
  if (!file_exists($file) || trim($requestId) === '') return null;

  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);

    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($header), '');
      $r = array_combine($header, $row);

      if (($r['request_id'] ?? '') === $requestId) {
        fclose($fp);

        if (($r['status'] ?? '') === 'approved' && ($r['used'] ?? '0') === '0') {
          return $r;
        }

        return null;
      }
    }

    fclose($fp);
  }

  return null;
}

function markApprovalRequestUsed($file, $requestId) {
  if (!file_exists($file)) return;

  $rows = [];
  $header = [];

  if (($fp = fopen($file, 'r')) !== false) {
    $header = fgetcsv($fp);

    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($header), '');
      $r = array_combine($header, $row);

      if (($r['request_id'] ?? '') === $requestId) {
        $r['used'] = '1';
      }

      $rows[] = $r;
    }

    fclose($fp);
  }

  $fp = fopen($file, 'w');
  fputcsv($fp, $header);

  foreach ($rows as $r) {
    $line = [];
    foreach ($header as $h) {
      $line[] = $r[$h] ?? '';
    }
    fputcsv($fp, $line);
  }

  fclose($fp);
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $approvalRequestId = postv('approval_request_id');
  $approvedRequest = findApprovedRequest($approvalRequestsFile, $approvalRequestId);

  if ($approvalRequestId === '') {
    $errors[] = $isArabic ? 'رقم طلب الموافقة مطلوب.' : 'Approval request ID is required.';
  } elseif (!$approvedRequest) {
    $errors[] = $isArabic ? 'رقم الموافقة غير صحيح، أو غير موافق عليه، أو تم استخدامه مسبقاً.' : 'Approval request ID is invalid, not approved, or already used.';
  }
  $isRelated = postv('related_agreement');
  $agreementCode = ($isRelated === 'نعم') ? postv('_agreement_code') : '';
  $agreementName = $agreements[$agreementCode]['اسم الاتفاقية'] ?? '';

  $title = postv('عنوان المبادرة');
  $initiativeNumber = postv('رقم المبادرة');
  $entity = postv('الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)');
  $coordinator = '';
  $providerCategories = $_POST['provider_categories'] ?? [];
  if (!is_array($providerCategories)) $providerCategories = [];
  $providerCategories = array_values(array_intersect(['academic','administrative','students','student_group','joint','external_partner'], array_unique(array_map('trim', $providerCategories))));
  $initiativeType = postv('نوع المبادرة');

  $startDate = postv('تاريخ تنفيذ المبادرة');
  $endDate = postv('تاريخ انتهاء المبادرة');
  $activityStatus = postv('activity_status');
  $activityRecurrence = postv('activity_recurrence');
  $academicYear = postv('academic_year');
  $durationHours = postv('duration_hours');

  $locationMode = postv('location_mode');
  $outsideLocation = postv('outside_location');
  if ($locationMode !== 'outside_uob') {
    $outsideLocation = '';
  }
  $locationValue = $locationMode;
  if ($outsideLocation !== '') {
    $locationValue .= ' - ' . $outsideLocation;
  }

  $description = postv('نبذة عن المبادرة وأهدافها');
  $selectedTargets = $_POST['target_groups'] ?? [];
  if (!is_array($selectedTargets)) $selectedTargets = [];
  $selectedTargets = array_values(array_unique(array_filter(array_map('trim', $selectedTargets), fn($value) => $value !== '' && $value !== 'other')));
  $targetText = implode('، ', $selectedTargets);

  $male = (int)($_POST['male_count'] ?? 0);
  $female = (int)($_POST['female_count'] ?? 0);
  $unspecifiedBeneficiaries = (int)($_POST['unspecified_count'] ?? 0);
  $totalBeneficiaries = (string)($male + $female + $unspecifiedBeneficiaries);
  $beneficiaryCountBasis = postv('beneficiary_count_basis');
  $youthFlag = postv('youth_18_35');

  $supportsSdg = postv('supports_sdg');
  $sdgMain = postv('primary_sdg');
  $secondarySdgs = $_POST['secondary_sdgs'] ?? [];
  if (!is_array($secondarySdgs)) $secondarySdgs = [];
  $secondarySdgs = array_values(array_unique(array_filter(array_map('trim', $secondarySdgs), fn($value) => $value !== '' && $value !== $sdgMain)));
  if ($supportsSdg !== 'نعم') {
    $sdgMain = '';
    $secondarySdgs = [];
  }
  $selectedSdgs = $supportsSdg === 'نعم' ? array_values(array_filter(array_merge([$sdgMain], $secondarySdgs))) : [];
  $sdgSecondary = implode(' | ', $secondarySdgs);
  $rankingFramework = postv('ranking_framework');
  $theAreas = $_POST['the_areas'] ?? [];
  if (!is_array($theAreas)) $theAreas = [];
  $theAreas = array_values(array_intersect(['teaching','research','outreach','stewardship'], array_unique(array_map('trim', $theAreas))));
  $qsCategories = $_POST['qs_categories'] ?? [];
  if (!is_array($qsCategories)) $qsCategories = [];
  $qsCategories = array_values(array_intersect(['environmental','social','governance'], array_unique(array_map('trim', $qsCategories))));
  if (!in_array($rankingFramework, ['the','both'], true)) $theAreas = [];
  if (!in_array($rankingFramework, ['qs','both'], true)) $qsCategories = [];

  $published = postv('هل نُشرت على موقع الجامعة؟');
  $publishedYesValues = ['uob','partner'];
  $mediaCoverageActiveValues = ['uob','partner','in_progress'];
  $mediaCoverageType = in_array($published, $mediaCoverageActiveValues, true) ? postv('media_coverage_type') : '';
  $mediaOutletName = $mediaCoverageType === 'news' ? postv('media_outlet_name') : '';
  $mediaHeadline = $mediaCoverageType === 'news' ? postv('media_headline') : '';
  $mediaPublicationDate = $mediaCoverageType === 'news' ? postv('media_publication_date') : '';
  $newsLink = $mediaCoverageType === 'news' && in_array($published, $publishedYesValues, true)
    ? postv('رابط خبر المبادرة')
    : '';
  $tvChannel = $mediaCoverageType === 'tv_interview' ? postv('tv_channel') : '';
  $tvProgram = $mediaCoverageType === 'tv_interview' ? postv('tv_program') : '';
  $tvInterviewTopic = $mediaCoverageType === 'tv_interview' ? postv('tv_interview_topic') : '';
  $tvInterviewer = $mediaCoverageType === 'tv_interview' ? postv('tv_interviewer') : '';
  $tvUobRepresentatives = $mediaCoverageType === 'tv_interview' ? postv('tv_uob_representatives') : '';
  $tvInterviewDate = $mediaCoverageType === 'tv_interview' ? postv('tv_interview_date') : '';
  $tvBroadcastStatus = $mediaCoverageType === 'tv_interview' ? postv('tv_broadcast_status') : '';
  $tvBroadcastScope = $mediaCoverageType === 'tv_interview' ? postv('tv_broadcast_scope') : '';
  $tvInterviewLanguage = $mediaCoverageType === 'tv_interview' ? postv('tv_interview_language') : '';
  $tvDurationMinutes = $mediaCoverageType === 'tv_interview' ? postv('tv_duration_minutes') : '';
  $tvInterviewLink = $mediaCoverageType === 'tv_interview' && in_array($published, $publishedYesValues, true)
    ? postv('tv_interview_link')
    : '';
  $tvInterviewHighlights = $mediaCoverageType === 'tv_interview' ? postv('tv_interview_highlights') : '';
  $evidenceUrls = $_POST['evidence_url'] ?? [];
  if (!is_array($evidenceUrls)) $evidenceUrls = [$evidenceUrls];
  $evidenceUrls = array_values(array_slice(array_unique(array_filter(array_map(
    static fn($url) => trim((string)$url),
    $evidenceUrls
  ), static fn($url) => $url !== '')), 0, 10));
  $imagesLink = implode(' | ', $evidenceUrls);
  $outputs = postv('المخرجات التي تم تحقيقها ');
  $notesEntity = postv('m_notes_entity');
  $notesVppd = postv('m_notes_vppd');
  $responsibleName = postv('responsible_name');
  $responsibleEmail = postv('responsible_email');
  $responsibleMobile = postv('responsible_mobile');
  $departmentUnit = postv('department_unit');
  $departmentUnitOther = postv('department_unit_other');
  $departmentWithinCollege = postv('department_within_college');
  $requesterDepartment = '';
  $requesterDepartmentOther = '';
  $implementationScope = $locationMode;
  $implementationScopeOther = '';
  $initiativeDescriptorsSelected = $_POST['initiative_descriptors'] ?? [];
  if (!is_array($initiativeDescriptorsSelected)) $initiativeDescriptorsSelected = [];
  $initiativeDescriptorsSelected = array_values(array_unique(array_filter(array_map('trim', $initiativeDescriptorsSelected), fn($value) => $value !== '' && $value !== 'other')));
  $initiativeDescriptorOther = '';
  // Keep the legacy objective column synchronized with the combined field.
  $initiativeObjective = $description;
  $initiativeTypeOther = postv('initiative_type_other');
  $secondaryInitiativeTypes = $_POST['secondary_initiative_types'] ?? [];
  if (!is_array($secondaryInitiativeTypes)) $secondaryInitiativeTypes = [];
  $secondaryInitiativeTypes = array_values(array_unique(array_filter(array_map('trim', $secondaryInitiativeTypes), fn($value) => $value !== '' && $value !== 'other')));
  $secondaryInitiativeTypeOther = '';
  $publishedOther = '';
  $targetGroupOther = '';
  $totalAttendees = $totalBeneficiaries;
  $societalImpact = postv('societal_impact');
  $resourcesMobilizedOptions = $_POST['resources_mobilized_options'] ?? [];
  if (!is_array($resourcesMobilizedOptions)) $resourcesMobilizedOptions = [];
  $resourcesMobilizedOptions = array_values(array_intersect(['budget','external_funding','volunteers','staff_hours','facilities','equipment','partnerships'], array_unique(array_map('trim', $resourcesMobilizedOptions))));
  $resourcesMobilizedOther = postv('resources_mobilized_other');
  $resourcesMobilized = implode(' | ', array_filter(array_merge($resourcesMobilizedOptions, [$resourcesMobilizedOther])));
  $environmentalImpactTypes = $_POST['environmental_impact_types'] ?? [];
  if (!is_array($environmentalImpactTypes)) $environmentalImpactTypes = [];
  $environmentalImpactTypes = array_values(array_intersect(['energy','water','waste','emissions','trees','transport','biodiversity','procurement'], array_unique(array_map('trim', $environmentalImpactTypes))));
  $environmentalBeforeValue = postv('environmental_before_value');
  $environmentalAfterValue = postv('environmental_after_value');
  $environmentalImprovementValue = postv('environmental_improvement_value');
  $environmentalUnit = postv('environmental_unit');
  $environmentalMeasurementBasis = postv('environmental_measurement_basis');
  $environmentalDataSource = postv('environmental_data_source');
  $environmentalImpact = postv('environmental_impact');
  $environmentalModuleRequired = in_array('environmental', $qsCategories, true)
    || in_array('campus_operations', $initiativeDescriptorsSelected, true)
    || $initiativeType === 'sustainability_activities'
    || in_array('sustainability_activities', $secondaryInitiativeTypes, true);
  if (!$environmentalModuleRequired) {
    $environmentalImpactTypes = [];
    $environmentalBeforeValue = '';
    $environmentalAfterValue = '';
    $environmentalImprovementValue = '';
    $environmentalUnit = '';
    $environmentalMeasurementBasis = '';
    $environmentalDataSource = '';
    $environmentalImpact = '';
  }

  $internationalParticipation = postv('international_participation');
  $internationalCountries = $_POST['international_countries'] ?? [];
  if (!is_array($internationalCountries)) $internationalCountries = [];
  $internationalCountries = array_values(array_unique(array_filter(array_map('trim', $internationalCountries))));
  $internationalCountryCount = count($internationalCountries);
  $internationalParticipants = max(0, (int)($_POST['international_participants'] ?? 0));
  $internationalPartner = postv('international_partner');
  $internationalPartnerType = postv('international_partner_type');
  $internationalCollaborationNature = $_POST['international_collaboration_nature'] ?? [];
  if (!is_array($internationalCollaborationNature)) $internationalCollaborationNature = [];
  $internationalCollaborationNature = array_values(array_intersect(['research','teaching','training','exchange','funding','joint_organization','knowledge_transfer'], array_unique(array_map('trim', $internationalCollaborationNature))));
  if ($internationalParticipation !== 'yes') {
    $internationalCountries = [];
    $internationalCountryCount = 0;
    $internationalParticipants = 0;
    $internationalPartner = '';
    $internationalPartnerType = '';
    $internationalCollaborationNature = [];
  }

  $internalFundingBhd = postv('internal_funding_bhd');
  $externalFundingAmount = postv('external_funding_amount');
  $externalFundingCurrency = postv('external_funding_currency');
  $inKindSupportBhd = postv('in_kind_support_bhd');
  $fundingEntity = postv('funding_entity');
  if (!in_array('budget', $resourcesMobilizedOptions, true)) $internalFundingBhd = '';
  if (!in_array('external_funding', $resourcesMobilizedOptions, true)) {
    $externalFundingAmount = '';
    $externalFundingCurrency = '';
    $fundingEntity = '';
  }

  $trainingHours = postv('training_hours');
  $traineesCount = max(0, (int)($_POST['trainees_count'] ?? 0));
  $volunteersCount = max(0, (int)($_POST['volunteers_count'] ?? 0));
  $volunteerHoursPerPerson = postv('volunteer_hours_per_person');
  $totalVolunteerHours = $volunteersCount * max(0, (float)$volunteerHoursPerPerson);
  $trainingTypeValues = ['workshop_training','capacity_building_training','tutoring_coaching_mentorship','volunteer_teaching_training'];
  $volunteerTypeValues = ['volunteering_program','volunteer_teaching_training'];
  $trainingModuleRequired = in_array($initiativeType, $trainingTypeValues, true) || (bool)array_intersect($secondaryInitiativeTypes, $trainingTypeValues);
  $volunteerModuleRequired = in_array($initiativeType, $volunteerTypeValues, true) || (bool)array_intersect($secondaryInitiativeTypes, $volunteerTypeValues) || in_array('volunteers', $resourcesMobilizedOptions, true);
  if (!$trainingModuleRequired) {
    $trainingHours = '';
    $traineesCount = 0;
  }
  if (!$volunteerModuleRequired) {
    $volunteersCount = 0;
    $volunteerHoursPerPerson = '';
    $totalVolunteerHours = 0;
  }
  $evidenceTypesSelected = $_POST['evidence_type'] ?? [];
  if (!is_array($evidenceTypesSelected)) $evidenceTypesSelected = [];
  $evidenceTypesSelected = array_values(array_intersect(['upload','url','explanation'], array_unique(array_map('trim', $evidenceTypesSelected))));
  $evidenceType = implode(' | ', $evidenceTypesSelected);
  if (!in_array('url', $evidenceTypesSelected, true)) $imagesLink = '';
  $evidenceExplanation = postv('evidence_explanation');
  $evidenceDocumentType = postv('evidence_document_type');
  $evidenceDate = postv('evidence_date');
  $evidenceOwner = postv('evidence_owner');
  $evidencePublicAccess = postv('evidence_public_access');
  $evidenceValue = '';
  $publicSharingSelected = postv('public_sharing');
  if (!$evidenceTypesSelected) {
    $evidenceDocumentType = '';
    $evidenceDate = '';
    $evidenceOwner = '';
    $evidencePublicAccess = '';
    $publicSharingSelected = '';
  }
  $externalEntities = postv('external_entities');

  // Dynamic initiative contributors / participants
  $contributorNames = $_POST['contributor_name'] ?? [];
  $contributorTypes = $_POST['contributor_type'] ?? [];
  $contributorEmails = $_POST['contributor_email'] ?? [];
  $contributorMobiles = $_POST['contributor_mobile'] ?? [];
  $contributorDepartments = $_POST['contributor_department'] ?? [];
  $contributorSubdepartments = $_POST['contributor_subdepartment'] ?? [];
  $contributorRoles = $_POST['contributor_role'] ?? [];
  $contributorTypeOthers = $_POST['contributor_type_other'] ?? [];
  $contributorDepartmentOthers = $_POST['contributor_department_other'] ?? [];
  $contributorRoleOthers = $_POST['contributor_role_other'] ?? [];
  $primaryResponsibleIndex = isset($_POST['primary_responsible']) ? (int)$_POST['primary_responsible'] : 0;
  $initiativeCoordinatorIndex = isset($_POST['initiative_coordinator']) ? (int)$_POST['initiative_coordinator'] : -1;
  foreach (['contributorNames','contributorTypes','contributorEmails','contributorMobiles','contributorDepartments','contributorSubdepartments','contributorRoles','contributorTypeOthers','contributorDepartmentOthers','contributorRoleOthers'] as $varName) {
    if (!is_array($$varName)) $$varName = [];
  }
  $initiativeContributors = [];
  $contributorsCount = max(count($contributorNames), count($contributorTypes), count($contributorEmails), count($contributorMobiles), count($contributorDepartments), count($contributorSubdepartments), count($contributorRoles));
  for ($i = 0; $i < $contributorsCount; $i++) {
    $contributor = [
      'name' => trim((string)($contributorNames[$i] ?? '')),
      'type' => trim((string)($contributorTypes[$i] ?? '')),
      'email' => trim((string)($contributorEmails[$i] ?? '')),
      'mobile' => trim((string)($contributorMobiles[$i] ?? '')),
      'department' => trim((string)($contributorDepartments[$i] ?? '')),
      'subdepartment' => trim((string)($contributorSubdepartments[$i] ?? '')),
      'role' => trim((string)($contributorRoles[$i] ?? '')),
      'type_other' => trim((string)($contributorTypeOthers[$i] ?? '')),
      'department_other' => trim((string)($contributorDepartmentOthers[$i] ?? '')),
      'role_other' => trim((string)($contributorRoleOthers[$i] ?? '')),
      'is_primary' => $i === $primaryResponsibleIndex,
      'is_coordinator' => $i === $initiativeCoordinatorIndex,
    ];
    if ($contributor['name'] === '' && $contributor['email'] === '' && $contributor['mobile'] === '' && $contributor['role'] === '' && $contributor['role_other'] === '') continue;
    $initiativeContributors[] = $contributor;
  }
  if ($initiativeContributors && !array_filter($initiativeContributors, fn($person) => !empty($person['is_primary']))) {
    $initiativeContributors[0]['is_primary'] = true;
  }
  $primaryResponsible = $initiativeContributors
    ? (current(array_filter($initiativeContributors, fn($person) => !empty($person['is_primary']))) ?: $initiativeContributors[0])
    : null;
  $responsibleName = $primaryResponsible['name'] ?? '';
  $responsibleEmail = $primaryResponsible['email'] ?? '';
  $responsibleMobile = $primaryResponsible['mobile'] ?? '';
  $coordinatorPerson = $initiativeContributors
    ? (current(array_filter($initiativeContributors, fn($person) => !empty($person['is_coordinator']))) ?: null)
    : null;
  $coordinator = $coordinatorPerson['name'] ?? '';

  // Additional people who participated in implementation (separate from responsible people).
  $participantNames = $_POST['participant_name'] ?? [];
  $participantTypes = $_POST['participant_type'] ?? [];
  $participantEmails = $_POST['participant_email'] ?? [];
  $participantMobiles = $_POST['participant_mobile'] ?? [];
  $participantDepartments = $_POST['participant_department'] ?? [];
  $participantSubdepartments = $_POST['participant_subdepartment'] ?? [];
  $participantRoles = $_POST['participant_role'] ?? [];
  $participantTypeOthers = $_POST['participant_type_other'] ?? [];
  $participantDepartmentOthers = $_POST['participant_department_other'] ?? [];
  $participantRoleOthers = $_POST['participant_role_other'] ?? [];
  foreach (['participantNames','participantTypes','participantEmails','participantMobiles','participantDepartments','participantSubdepartments','participantRoles','participantTypeOthers','participantDepartmentOthers','participantRoleOthers'] as $varName) {
    if (!is_array($$varName)) $$varName = [];
  }
  $initiativeParticipants = [];
  $participantsCount = max(count($participantNames), count($participantTypes), count($participantEmails), count($participantMobiles), count($participantDepartments), count($participantSubdepartments), count($participantRoles));
  for ($i = 0; $i < $participantsCount; $i++) {
    $participant = [
      'name' => trim((string)($participantNames[$i] ?? '')),
      'type' => trim((string)($participantTypes[$i] ?? '')),
      'email' => trim((string)($participantEmails[$i] ?? '')),
      'mobile' => trim((string)($participantMobiles[$i] ?? '')),
      'department' => trim((string)($participantDepartments[$i] ?? '')),
      'subdepartment' => trim((string)($participantSubdepartments[$i] ?? '')),
      'role' => trim((string)($participantRoles[$i] ?? '')),
      'type_other' => trim((string)($participantTypeOthers[$i] ?? '')),
      'department_other' => trim((string)($participantDepartmentOthers[$i] ?? '')),
      'role_other' => trim((string)($participantRoleOthers[$i] ?? '')),
    ];
    if (implode('', $participant) === '') continue;
    $initiativeParticipants[] = $participant;
  }

  $uploadedEvidencePaths = [];
  $existingEvidenceFiles = json_decode((string)($_POST['evidence_files_json'] ?? '[]'), true);
  if (!is_array($existingEvidenceFiles)) $existingEvidenceFiles = [];
  $ownerPrefix = substr(hash('sha256', (string)$_SESSION['user_email']), 0, 12) . '-';
  foreach ($existingEvidenceFiles as $existingEvidenceFile) {
    $path = trim((string)($existingEvidenceFile['path'] ?? ''));
    $basename = basename($path);
    if (
      $path === 'uploads/initiative-evidence/' . $basename
      && str_starts_with($basename, $ownerPrefix)
      && is_file(__DIR__ . '/../uploads/initiative-evidence/' . $basename)
    ) {
      $uploadedEvidencePaths[] = $path;
    }
  }
  $uploadedEvidencePaths = array_values(array_unique(array_slice($uploadedEvidencePaths, 0, 10)));
  if (in_array('upload', $evidenceTypesSelected, true) && !empty($_FILES['supporting_files']['name']) && is_array($_FILES['supporting_files']['name'])) {
    $uploadDir = __DIR__ . '/../uploads/initiative-evidence/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    $allowedExtensions = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','mp4','mov'];
    $fileCount = min(count($_FILES['supporting_files']['name']), max(0, 10 - count($uploadedEvidencePaths)));
    for ($i=0; $i<$fileCount; $i++) {
      if (($_FILES['supporting_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $originalName = basename((string)$_FILES['supporting_files']['name'][$i]);
      $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if (!in_array($extension, $allowedExtensions, true)) { $errors[] = 'نوع الملف غير مسموح: '.$originalName; continue; }
      $storedName = date('YmdHis').'-'.$i.'-'.preg_replace('/[^A-Za-z0-9_-]+/','-',pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;
      if (move_uploaded_file($_FILES['supporting_files']['tmp_name'][$i], $uploadDir.$storedName)) $uploadedEvidencePaths[]='uploads/initiative-evidence/'.$storedName;
    }
  }
  $evidenceValueParts = [];
  if (in_array('upload', $evidenceTypesSelected, true) && $uploadedEvidencePaths) {
    $evidenceValueParts[] = implode(' | ', $uploadedEvidencePaths);
  }
  if (in_array('url', $evidenceTypesSelected, true) && $evidenceUrls) {
    $evidenceValueParts[] = implode(' | ', $evidenceUrls);
  }
  if (in_array('explanation', $evidenceTypesSelected, true) && $evidenceExplanation !== '') {
    $evidenceValueParts[] = $evidenceExplanation;
  }
  $evidenceValue = implode(' | ', $evidenceValueParts);


  // Validation errors with translation
  if ($isRelated === '') $errors[] = t('required_field');
  if ($isRelated === 'نعم' && $agreementCode === '') $errors[] = t('agreement_required');
  if ($title === '') $errors[] = t('title_required');
  if ($initiativeType === '') $errors[] = t('type_required');
  if ($entity === '') $errors[] = t('entity_required');
  if ($startDate === '') $errors[] = t('date_required');
  if ($endDate !== '' && $startDate !== '' && $endDate < $startDate) $errors[] = $isArabic ? 'تاريخ الانتهاء لا يمكن أن يسبق تاريخ البداية.' : 'The end date cannot be earlier than the start date.';
  if ($activityStatus === '') $errors[] = $isArabic ? 'حالة النشاط مطلوبة.' : 'Activity status is required.';
  if ($academicYear === '') $errors[] = $isArabic ? 'السنة الأكاديمية أو فترة التقرير مطلوبة.' : 'Academic year or reporting period is required.';
  if ($locationMode === '') $errors[] = t('location_required');
  if ($locationMode === 'outside_uob' && $outsideLocation === '') $errors[] = $isArabic ? 'مكان التنفيذ أو اسم موقع المبادرة مطلوب.' : 'Venue / Location Name is required.';
  if ($supportsSdg === 'نعم' && count($selectedSdgs) === 0) $errors[] = t('sdg_required');
  if (in_array($published, $mediaCoverageActiveValues, true) && !in_array($mediaCoverageType, ['news','tv_interview'], true)) {
    $errors[] = $isArabic ? 'يرجى تحديد نوع التغطية الإعلامية.' : 'Select the media coverage type.';
  }
  if ($mediaCoverageType === 'news') {
    if ($mediaOutletName === '') $errors[] = $isArabic ? 'اسم المنصة أو الجهة الناشرة مطلوب.' : 'The publishing platform or outlet name is required.';
    if ($mediaHeadline === '') $errors[] = $isArabic ? 'عنوان الخبر مطلوب.' : 'The news headline is required.';
    if ($mediaPublicationDate === '' && in_array($published, $publishedYesValues, true)) {
      $errors[] = $isArabic ? 'تاريخ نشر الخبر مطلوب.' : 'The news publication date is required.';
    }
    if (in_array($published, $publishedYesValues, true) && $newsLink === '') $errors[] = t('news_link_required');
  }
  if ($mediaCoverageType === 'tv_interview') {
    if ($tvChannel === '') $errors[] = $isArabic ? 'اسم القناة التلفزيونية مطلوب.' : 'The TV channel name is required.';
    if ($tvProgram === '') $errors[] = $isArabic ? 'اسم البرنامج التلفزيوني مطلوب.' : 'The TV program name is required.';
    if ($tvInterviewTopic === '') $errors[] = $isArabic ? 'عنوان أو موضوع المقابلة مطلوب.' : 'The interview title or topic is required.';
    if ($tvUobRepresentatives === '') $errors[] = $isArabic ? 'أسماء ممثلي جامعة البحرين مطلوبة.' : 'University of Bahrain representative names are required.';
    if ($tvInterviewDate === '') $errors[] = $isArabic ? 'تاريخ المقابلة مطلوب.' : 'The interview date is required.';
    if ($tvBroadcastStatus === '') $errors[] = $isArabic ? 'حالة بث المقابلة مطلوبة.' : 'The interview broadcast status is required.';
    if ($tvBroadcastScope === '') $errors[] = $isArabic ? 'نطاق بث المقابلة مطلوب.' : 'The interview broadcast scope is required.';
    if ($tvInterviewLanguage === '') $errors[] = $isArabic ? 'لغة المقابلة مطلوبة.' : 'The interview language is required.';
    if ($tvDurationMinutes !== '' && (!is_numeric($tvDurationMinutes) || (float)$tvDurationMinutes <= 0)) {
      $errors[] = $isArabic ? 'مدة المقابلة يجب أن تكون رقمًا أكبر من صفر.' : 'Interview duration must be a number greater than zero.';
    }
    if (in_array($tvBroadcastStatus, ['aired','live'], true) && in_array($published, $publishedYesValues, true) && $tvInterviewLink === '') {
      $errors[] = $isArabic ? 'رابط مشاهدة المقابلة مطلوب بعد بثها.' : 'A viewing link is required after the interview has aired.';
    }
  }
  if ($coordinator === '') $errors[] = $isArabic ? 'يرجى تحديد منسق المبادرة من قائمة الأشخاص المسؤولين.' : 'Select the initiative coordinator from the responsible people.';
  if (!$providerCategories) $errors[] = $isArabic ? 'يرجى تحديد الفئة أو الفئات المقدمة للنشاط.' : 'Select the category or categories delivering the activity.';
  if ($responsibleName === '') $errors[] = $isArabic ? 'اسم الشخص المسؤول مطلوب.' : 'Responsible person name is required.';
  if ($responsibleEmail === '' || !filter_var($responsibleEmail, FILTER_VALIDATE_EMAIL)) $errors[] = $isArabic ? 'البريد الإلكتروني للشخص المسؤول غير صحيح.' : 'A valid responsible person email is required.';
  foreach ($initiativeContributors as $index => $person) {
    if ($person['name'] === '') $errors[] = ($isArabic ? 'اسم الشخص المسؤول رقم ' : 'Responsible person name is required for person ') . ($index + 1) . '.';
    if ($person['email'] === '' || !filter_var($person['email'], FILTER_VALIDATE_EMAIL)) $errors[] = ($isArabic ? 'البريد الإلكتروني غير صحيح للشخص المسؤول رقم ' : 'A valid email is required for responsible person ') . ($index + 1) . '.';
  }
  foreach ($initiativeParticipants as $index => $participant) {
    $number = $index + 1;
    if ($participant['name'] === '') $errors[] = $isArabic ? "اسم المشارك رقم {$number} مطلوب." : "Participant {$number} name is required.";
    if ($participant['type'] === '') $errors[] = $isArabic ? "نوع المشارك رقم {$number} مطلوب." : "Participant {$number} type is required.";
    if ($participant['role'] === '') $errors[] = $isArabic ? "دور المشارك رقم {$number} مطلوب." : "Participant {$number} role is required.";
    if ($participant['email'] !== '' && !filter_var($participant['email'], FILTER_VALIDATE_EMAIL)) $errors[] = $isArabic ? "البريد الإلكتروني للمشارك رقم {$number} غير صحيح." : "Participant {$number} email is invalid.";
    if ($participant['type'] === 'other' && $participant['type_other'] === '') $errors[] = $isArabic ? "يرجى تحديد نوع المشارك الآخر رقم {$number}." : "Specify the other type for participant {$number}.";
    if ($participant['department'] === 'other' && $participant['department_other'] === '') $errors[] = $isArabic ? "يرجى تحديد جهة المشارك الأخرى رقم {$number}." : "Specify the other entity for participant {$number}.";
    if ($participant['role'] === 'other' && $participant['role_other'] === '') $errors[] = $isArabic ? "يرجى تحديد دور المشارك الآخر رقم {$number}." : "Specify the other role for participant {$number}.";
  }
  if ($departmentUnit === '') $errors[] = $isArabic ? 'القسم / المركز / الوحدة مطلوب.' : 'Department / Center / Unit is required.';
  if ($departmentUnit === 'other' && $departmentUnitOther === '') $errors[] = $isArabic ? 'يرجى تحديد القسم / المركز / الوحدة الأخرى.' : 'Please specify the other Department / Center / Unit.';
  if ($initiativeType === 'other' && $initiativeTypeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نوع المبادرة الآخر.' : 'Please specify the other initiative type.';
  if (in_array($initiativeType, $secondaryInitiativeTypes, true)) $errors[] = $isArabic ? 'لا يمكن اختيار النوع الرئيسي نفسه كنوع ثانوي.' : 'The primary initiative type cannot also be selected as a secondary type.';
  if (!$initiativeDescriptorsSelected) $errors[] = $isArabic ? 'يرجى اختيار مجال مساهمة واحد على الأقل.' : 'Select at least one contribution area.';
  if ($description === '') $errors[] = $isArabic ? 'وصف المبادرة وأهدافها مطلوب.' : 'The initiative description and objectives are required.';
  if ($activityStatus === 'completed' && $outputs === '') $errors[] = $isArabic ? 'المخرجات المباشرة مطلوبة للنشاط المكتمل.' : 'Direct outputs are required for a completed activity.';
  if ($activityStatus === 'completed' && $societalImpact === '') $errors[] = $isArabic ? 'يرجى توضيح أثر النشاط المكتمل أو ذكر أن الأثر لم يُقَس بعد.' : 'Describe the impact of the completed activity or state that it has not yet been measured.';
  if ($activityStatus === 'completed' && !$evidenceTypesSelected) $errors[] = $isArabic ? 'يرجى إرفاق دليل أو رابط أو شرح للنشاط المكتمل.' : 'Provide evidence, a URL, or an explanation for a completed activity.';
  if ($totalBeneficiaries !== '0' && $beneficiaryCountBasis === '') $errors[] = $isArabic ? 'يرجى تحديد ما إذا كان عدد المستفيدين فعليًا أو تقديريًا.' : 'Specify whether the beneficiary count is actual or estimated.';
  if ($rankingFramework === '') $errors[] = $isArabic ? 'يرجى تحديد ارتباط النشاط بالتصنيفات.' : 'Specify the activity’s ranking relevance.';
  if (in_array($rankingFramework, ['the','both'], true) && !$theAreas) $errors[] = $isArabic ? 'اختر مجال THE واحدًا على الأقل.' : 'Select at least one THE area.';
  if (in_array($rankingFramework, ['qs','both'], true) && !$qsCategories) $errors[] = $isArabic ? 'اختر فئة QS واحدة على الأقل.' : 'Select at least one QS category.';
  if ($environmentalModuleRequired && !$environmentalImpactTypes) $errors[] = $isArabic ? 'اختر نوع أثر بيئي واحدًا على الأقل.' : 'Select at least one environmental impact type.';
  if ($environmentalModuleRequired && $environmentalImpact === '') $errors[] = $isArabic ? 'يرجى شرح الأثر البيئي المحقق أو المتوقع.' : 'Describe the achieved or expected environmental impact.';
  if ($environmentalModuleRequired && $environmentalMeasurementBasis === '') $errors[] = $isArabic ? 'حدد ما إذا كانت بيانات الأثر البيئي فعلية أو تقديرية.' : 'Specify whether the environmental impact data is actual or estimated.';
  if ($environmentalModuleRequired && ($environmentalBeforeValue !== '' || $environmentalAfterValue !== '' || $environmentalImprovementValue !== '') && $environmentalUnit === '') $errors[] = $isArabic ? 'وحدة القياس مطلوبة عند إدخال قيم بيئية.' : 'A unit is required when environmental values are entered.';
  if ($environmentalModuleRequired && $environmentalMeasurementBasis === 'actual' && $environmentalDataSource === '') $errors[] = $isArabic ? 'مصدر البيانات مطلوب عند اختيار قياس فعلي.' : 'A data source is required for actual environmental measurements.';
  if ($internationalParticipation === '') $errors[] = $isArabic ? 'يرجى تحديد ما إذا كانت هناك مشاركة دولية.' : 'Specify whether there is international participation.';
  if ($internationalParticipation === 'yes' && !$internationalCountries) $errors[] = $isArabic ? 'أضف دولة مشاركة واحدة على الأقل.' : 'Add at least one participating country.';
  if ($internationalParticipation === 'yes' && !$internationalCollaborationNature) $errors[] = $isArabic ? 'اختر طبيعة التعاون الدولي.' : 'Select the nature of international collaboration.';
  if (in_array('budget', $resourcesMobilizedOptions, true) && ($internalFundingBhd === '' || !is_numeric($internalFundingBhd) || (float)$internalFundingBhd < 0)) $errors[] = $isArabic ? 'أدخل قيمة صحيحة للميزانية الداخلية بالدينار البحريني.' : 'Enter a valid internal budget amount in BHD.';
  if (in_array('external_funding', $resourcesMobilizedOptions, true) && ($externalFundingAmount === '' || !is_numeric($externalFundingAmount) || (float)$externalFundingAmount < 0)) $errors[] = $isArabic ? 'أدخل قيمة صحيحة للتمويل الخارجي.' : 'Enter a valid external funding amount.';
  if (in_array('external_funding', $resourcesMobilizedOptions, true) && $externalFundingCurrency === '') $errors[] = $isArabic ? 'حدد عملة التمويل الخارجي.' : 'Select the external funding currency.';
  if (in_array('external_funding', $resourcesMobilizedOptions, true) && $fundingEntity === '') $errors[] = $isArabic ? 'اكتب اسم الجهة الممولة.' : 'Enter the funding entity.';
  if ($trainingModuleRequired && $activityStatus === 'completed' && ($trainingHours === '' || !is_numeric($trainingHours))) $errors[] = $isArabic ? 'أدخل عدد ساعات التدريب للنشاط المكتمل.' : 'Enter the training hours for the completed activity.';
  if ($trainingModuleRequired && $activityStatus === 'completed' && $traineesCount < 1) $errors[] = $isArabic ? 'أدخل عدد المتدربين للنشاط المكتمل.' : 'Enter the number of trainees for the completed activity.';
  if ($volunteerModuleRequired && $activityStatus === 'completed' && $volunteersCount < 1) $errors[] = $isArabic ? 'أدخل عدد المتطوعين للنشاط المكتمل.' : 'Enter the number of volunteers for the completed activity.';
  if ($volunteerModuleRequired && $activityStatus === 'completed' && ($volunteerHoursPerPerson === '' || !is_numeric($volunteerHoursPerPerson))) $errors[] = $isArabic ? 'أدخل متوسط ساعات التطوع للفرد.' : 'Enter the average volunteer hours per person.';
  if (in_array('explanation', $evidenceTypesSelected, true) && (in_array('upload', $evidenceTypesSelected, true) || in_array('url', $evidenceTypesSelected, true))) {
    $errors[] = $isArabic ? 'الشرح الكتابي بديل عند عدم توفر ملف أو رابط، ولا يمكن اختياره معهما.' : 'The written explanation is an alternative only when no file or URL is available, so it cannot be selected with them.';
  }
  if (in_array('url', $evidenceTypesSelected, true)) {
    if (!$evidenceUrls) $errors[] = $isArabic ? 'يرجى إضافة رابط دليل واحد على الأقل.' : 'Please add at least one evidence URL.';
    foreach ($evidenceUrls as $evidenceUrl) {
      if (!filter_var($evidenceUrl, FILTER_VALIDATE_URL)) {
        $errors[] = ($isArabic ? 'رابط الدليل غير صحيح: ' : 'Invalid evidence URL: ') . $evidenceUrl;
      }
    }
  }
  if (in_array('explanation', $evidenceTypesSelected, true) && $evidenceExplanation === '') $errors[] = $isArabic ? 'يرجى كتابة شرح مختصر للدليل.' : 'Please provide a short evidence explanation.';
  if (in_array('upload', $evidenceTypesSelected, true) && !$uploadedEvidencePaths) $errors[] = $isArabic ? 'يرجى تحميل ملف دليل واحد على الأقل.' : 'Please upload at least one evidence file.';
  if ($evidenceTypesSelected && $evidenceDocumentType === '') $errors[] = $isArabic ? 'يرجى تحديد نوع الدليل.' : 'Select the evidence type.';
  if ($evidenceTypesSelected && $evidenceDate === '') $errors[] = $isArabic ? 'يرجى تحديد تاريخ الدليل.' : 'Enter the evidence date.';
  if ($evidenceTypesSelected && $evidenceOwner === '') $errors[] = $isArabic ? 'يرجى تحديد الجهة المالكة للدليل.' : 'Enter the evidence owner.';
  if ($evidenceTypesSelected && $evidencePublicAccess === '') $errors[] = $isArabic ? 'يرجى تحديد ما إذا كان الدليل متاحًا للعامة.' : 'Specify whether the evidence is publicly accessible.';
  if ($evidenceTypesSelected && $publicSharingSelected === '') $errors[] = $isArabic ? 'يرجى تحديد صلاحية جامعة البحرين في استخدام الدليل ونشره.' : 'Specify whether the University of Bahrain may use and publish the evidence.';
  foreach ($initiativeContributors as $index => $contributor) {
    $number = $index + 1;
    if ($contributor['role'] === '') $errors[] = $isArabic ? "دور الشخص المسؤول رقم {$number} مطلوب." : "Responsible person {$number} role is required.";
    if ($contributor['role'] === 'other' && $contributor['role_other'] === '') $errors[] = $isArabic ? "يرجى تحديد الدور الآخر للشخص المسؤول رقم {$number}." : "Specify the other role for responsible person {$number}.";
  }
  if ($initiativeNumber === '') {
    $initiativeNumber = (string)date('YmdHis');
  }

  if (!$errors) {
    $data = [];
    foreach ($fields as $f) $data[$f] = '';

    $data['id'] = 'INIT-' . time();
$data['agreement_code'] = $agreementCode;
$data['initiative_number'] = $initiativeNumber;
$data['entity'] = $entity;
$data['coordinator'] = $coordinator;
$data['coordinator_type']='';
$data['coordinator_type_other']='';
$data['title'] = $title;
$data['type'] = $initiativeType;
$data['start_date'] = $startDate;
$data['end_date'] = $endDate;
$data['location'] = $locationValue;
$data['description'] = $description;
$data['target_group'] = $targetText;
$data['male_beneficiaries'] = $male;
$data['female_beneficiaries'] = $female;
$data['youth_18_35'] = $youthFlag;
$data['sdgs'] = implode(' | ', $selectedSdgs);
$data['published'] = $published;
$data['news_link'] = $newsLink;
$data['images_link'] = $imagesLink;
$data['outputs'] = $outputs;
$data['notes'] = ''; // 🔥 الأدمن فقط
$data['responsible_name']=$responsibleName;
$data['responsible_email']=$responsibleEmail;
$data['responsible_mobile']=$responsibleMobile;
$data['department_unit']=$departmentUnit;
$data['department_unit_other']=$departmentUnitOther;
$data['department_within_college']=$departmentWithinCollege;
$data['requester_department']=$requesterDepartment;
$data['requester_department_other']=$requesterDepartmentOther;
$data['implementation_scope']=$implementationScope;
$data['implementation_scope_other']=$implementationScopeOther;
$data['initiative_descriptors']=implode(' | ',$initiativeDescriptorsSelected);
$data['initiative_descriptor_other']=$initiativeDescriptorOther;
$data['initiative_objective']=$initiativeObjective;
$data['secondary_initiative_types']=implode(' | ',$secondaryInitiativeTypes);
$data['secondary_initiative_type_other']=$secondaryInitiativeTypeOther;
$data['initiative_type_other']=$initiativeTypeOther;
$data['published_other']=$publishedOther;
$data['target_group_other']=$targetGroupOther;
$data['total_attendees']=$totalAttendees;
$data['societal_impact']=$societalImpact;
$data['resources_mobilized']=$resourcesMobilized;
$data['environmental_impact']=$environmentalImpact;
$data['evidence_type']=$evidenceType;
$data['evidence_value']=$evidenceValue;
$data['press_release']='';
$data['public_sharing']=$publicSharingSelected;
$data['faculty_staff_group']='';
$data['external_entities']=$externalEntities;
$data['supporting_files']=implode(' | ',$uploadedEvidencePaths);
$data['initiative_contributors']=json_encode($initiativeContributors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$data['initiative_participants']=json_encode($initiativeParticipants, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$data['activity_status']=$activityStatus;
$data['activity_recurrence']=$activityRecurrence;
$data['academic_year']=$academicYear;
$data['duration_hours']=$durationHours;
$data['provider_categories']=implode(' | ',$providerCategories);
$data['unspecified_beneficiaries']=$unspecifiedBeneficiaries;
$data['beneficiary_count_basis']=$beneficiaryCountBasis;
$data['ranking_framework']=$rankingFramework;
$data['the_areas']=implode(' | ',$theAreas);
$data['qs_categories']=implode(' | ',$qsCategories);
$data['primary_sdg']=$sdgMain;
$data['secondary_sdgs']=$sdgSecondary;
$data['evidence_document_type']=$evidenceDocumentType;
$data['evidence_date']=$evidenceDate;
$data['evidence_owner']=$evidenceOwner;
$data['evidence_public_access']=$evidencePublicAccess;
$data['environmental_impact_types']=implode(' | ',$environmentalImpactTypes);
$data['environmental_before_value']=$environmentalBeforeValue;
$data['environmental_after_value']=$environmentalAfterValue;
$data['environmental_improvement_value']=$environmentalImprovementValue;
$data['environmental_unit']=$environmentalUnit;
$data['environmental_measurement_basis']=$environmentalMeasurementBasis;
$data['environmental_data_source']=$environmentalDataSource;
$data['international_participation']=$internationalParticipation;
$data['international_countries']=implode(' | ',$internationalCountries);
$data['international_country_count']=$internationalCountryCount;
$data['international_participants']=$internationalParticipants;
$data['international_partner']=$internationalPartner;
$data['international_partner_type']=$internationalPartnerType;
$data['international_collaboration_nature']=implode(' | ',$internationalCollaborationNature);
$data['internal_funding_bhd']=$internalFundingBhd;
$data['external_funding_amount']=$externalFundingAmount;
$data['external_funding_currency']=$externalFundingCurrency;
$data['in_kind_support_bhd']=$inKindSupportBhd;
$data['funding_entity']=$fundingEntity;
$data['training_hours']=$trainingHours;
$data['trainees_count']=$traineesCount;
$data['volunteers_count']=$volunteersCount;
$data['volunteer_hours_per_person']=$volunteerHoursPerPerson;
$data['total_volunteer_hours']=$totalVolunteerHours;
$data['media_coverage_type']=$mediaCoverageType;
$data['media_outlet_name']=$mediaOutletName;
$data['media_headline']=$mediaHeadline;
$data['media_publication_date']=$mediaPublicationDate;
$data['tv_channel']=$tvChannel;
$data['tv_program']=$tvProgram;
$data['tv_interview_topic']=$tvInterviewTopic;
$data['tv_interviewer']=$tvInterviewer;
$data['tv_uob_representatives']=$tvUobRepresentatives;
$data['tv_interview_date']=$tvInterviewDate;
$data['tv_broadcast_status']=$tvBroadcastStatus;
$data['tv_broadcast_scope']=$tvBroadcastScope;
$data['tv_interview_language']=$tvInterviewLanguage;
$data['tv_duration_minutes']=$tvDurationMinutes;
$data['tv_interview_link']=$tvInterviewLink;
$data['tv_interview_highlights']=$tvInterviewHighlights;

$data['status'] = 'قيد المراجعة';
$data['notes_vppd'] = '';
$data['submitted_by'] = $_SESSION['user_email'] ?? '';
$data['submitted_at'] = date('Y-m-d H:i:s');
 

    if (!file_exists(INITIATIVES_MASTER)) {
      $fp = fopen(INITIATIVES_MASTER, 'w');
      fputcsv($fp, $fields);
      fclose($fp);
    } elseif (!ensureInitiativesCsvSchema(INITIATIVES_MASTER, $fields)) {
      $errors[] = $isArabic ? 'تعذر تحديث بنية ملف بيانات المبادرات.' : 'The initiatives data file schema could not be updated.';
    }

    if (!$errors) {
      $fp = fopen(INITIATIVES_MASTER, 'a');
      $row = [];
      foreach ($fields as $f) $row[] = $data[$f];
      fputcsv($fp, $row);
      fclose($fp);
    }

    if (!$errors) markApprovalRequestUsed($approvalRequestsFile, $approvalRequestId);

    if (!$errors) {
      $success = true;
      $_POST = [];
    }
  }
}

$selectedAgreementCode = $_POST['_agreement_code'] ?? $agreementPrefill;
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<style>
/* =========================
   Premium Add Initiative Page
   ========================= */
.init-admin-hero{
  position:relative;
  overflow:hidden;
  min-height:290px;
  background:
    radial-gradient(800px 260px at 12% 10%, rgba(255,255,255,.18), transparent 60%),
    radial-gradient(900px 320px at 88% 10%, rgba(255,255,255,.10), transparent 45%),
    linear-gradient(135deg, #b89a68 0%, #a98755 55%, #87693e 100%);
  border-bottom:1px solid rgba(255,255,255,.10);
}
.init-admin-hero::before{
  content:"";
  position:absolute;
  width:320px; height:320px;
  border-radius:50%;
  top:-80px; left:-60px;
  background: radial-gradient(circle, rgba(201,162,39,.22), transparent 68%);
  animation:initFloatOne 8s ease-in-out infinite;
}
.init-admin-hero::after{
  content:"";
  position:absolute;
  width:420px; height:420px;
  border-radius:50%;
  bottom:-180px; right:-100px;
  background: radial-gradient(circle, rgba(255,255,255,.18), transparent 68%);
  animation:initFloatTwo 10s ease-in-out infinite;
}
@keyframes initFloatOne{
  0%,100%{ transform:translateY(0) translateX(0); }
  50%{ transform:translateY(18px) translateX(10px); }
}
@keyframes initFloatTwo{
  0%,100%{ transform:translateY(0) translateX(0); }
  50%{ transform:translateY(-18px) translateX(-12px); }
}
.init-admin-hero-inner{
  position:relative;
  z-index:2;
  max-width:1180px;
  margin-inline:auto;
  padding:48px 20px 54px;
  display:grid;
  grid-template-columns: 1.1fr .9fr;
  gap:24px;
  align-items:end;
}
.init-admin-copy h1{
  margin:14px 0 0;
  color:#fff;
  font-size:clamp(30px, 4vw, 52px);
  font-weight:950;
  line-height:1.12;
}
.init-admin-copy p{
  margin:14px 0 0;
  color:rgba(255,255,255,.88);
  font-weight:800;
  line-height:1.95;
  max-width:68ch;
}
.init-admin-stats{
  display:grid;
  grid-template-columns:repeat(2, minmax(0,1fr));
  gap:12px;
}
.init-stat{
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.14);
  border-radius:22px;
  padding:16px 18px;
  backdrop-filter:blur(10px);
  box-shadow:0 16px 34px rgba(2,8,23,.14);
}
.init-stat span{
  display:block;
  color:rgba(255,255,255,.74);
  font-size:12px;
  font-weight:800;
}
.init-stat strong{
  display:block;
  color:#fff;
  font-size:20px;
  font-weight:950;
  margin-top:4px;
}
.init-form-shell{
  max-width:1180px;
  margin:-28px auto 36px;
  position:relative;
  z-index:5;
  background:rgba(255,255,255,.97);
  border:1px solid rgba(230,235,242,.96);
  border-radius:28px;
  box-shadow:0 24px 60px rgba(2,8,23,.12);
  padding:28px;
  overflow:hidden;
}
.init-form-shell::before{
  content:"";
  position:absolute;
  inset:auto auto -90px -90px;
  width:240px; height:240px;
  border-radius:50%;
  background:radial-gradient(circle, rgba(201,162,39,.12), transparent 70%);
  pointer-events:none;
}
.init-form-top{
  position:relative;
  z-index:2;
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  padding-bottom:18px;
  margin-bottom:20px;
  border-bottom:1px solid rgba(230,235,242,.95);
}
.init-form-heading{
  margin:0;
  color:var(--uob-navy);
  font-size:clamp(28px, 3vw, 40px);
  font-weight:950;
}
.init-form-sub{
  margin:8px 0 0;
  color:var(--muted);
  font-weight:800;
}
.init-icon-box{
  width:82px; height:82px;
  border-radius:24px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:32px;
  background:
    radial-gradient(circle at 30% 20%, rgba(201,162,39,.22), transparent 55%),
    linear-gradient(180deg, #fff, #f7f9fd);
  border:1px solid rgba(230,235,242,.95);
  box-shadow:0 12px 28px rgba(2,8,23,.08);
  animation:initFloatThree 3.8s ease-in-out infinite;
}
@keyframes initFloatThree{
  0%,100%{ transform:translateY(0); }
  50%{ transform:translateY(-6px); }
}

.init-tabs{
  display:grid;
  grid-template-columns:repeat(5,minmax(0,1fr));
  align-items:end;
  gap:8px;
  margin:0 0 12px;
  overflow:visible;
  padding:18px 10px 0;
  border-bottom:5px solid #8f6f3f;
  border-radius:18px 18px 8px 8px;
  background:linear-gradient(180deg,#fff,rgba(184,154,104,.06));
}
.init-tab-btn{
  position:relative;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:7px;
  width:100%;
  min-width:0;
  min-height:66px;
  padding:12px 9px 16px;
  border:1px solid rgba(184,154,104,.38);
  border-bottom:0;
  border-radius:18px 18px 5px 5px;
  background:linear-gradient(180deg,#fff,#f5f7fa);
  color:#0b1f3a;
  font-size:clamp(11px,1.05vw,14px);
  font-weight:900;
  line-height:1.35;
  white-space:normal;
  filter:drop-shadow(0 5px 7px rgba(15,23,42,.09));
  transition:transform .2s ease,filter .2s ease,border-color .2s ease,background .2s ease;
  z-index:1;
}
.init-tab-btn::after{
  content:"";
  position:absolute;
  right:18%;
  bottom:6px;
  left:18%;
  height:4px;
  border-radius:999px;
  background:rgba(184,154,104,.28);
}
.init-tab-number{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  flex:0 0 28px;
  width:28px;
  height:28px;
  border-radius:10px;
  background:rgba(184,154,104,.16);
  color:#8f6f3f;
  font-size:14px;
  font-weight:950;
}
.init-tab-btn:hover{
  border-color:#b89a68;
  background:linear-gradient(180deg,#fff,#f7f0e4);
  transform:translateY(-3px);
  filter:drop-shadow(0 8px 9px rgba(15,23,42,.12));
}
.init-tab-btn.active{
  min-height:74px;
  color:#ffffff !important;
  border-color:#8f6f3f !important;
  background:linear-gradient(180deg, #b89a68, #8f6f3f) !important;
  filter:drop-shadow(0 9px 11px rgba(143,111,63,.3));
  transform:translateY(0);
  z-index:2;
}
.init-tab-btn.active::after{background:rgba(255,255,255,.42)}
.init-tab-btn.active .init-tab-number{background:rgba(255,255,255,.2);color:#fff}
.init-progress{
  display:flex;
  align-items:center;
  gap:14px;
  margin:0 0 24px;
  padding:14px 18px;
  border:1px solid rgba(184,154,104,.28);
  border-radius:17px;
  background:rgba(184,154,104,.07);
}
.init-progress-percent{
  flex:0 0 auto;
  min-width:48px;
  color:#8f6f3f;
  font-size:18px;
  font-weight:950;
  text-align:center;
}
.init-progress-track{
  flex:1;
  height:13px;
  overflow:hidden;
  border-radius:999px;
  background:#e8edf3;
  box-shadow:inset 0 1px 3px rgba(15,23,42,.1);
}
.init-progress-fill{
  width:0;
  height:100%;
  border-radius:inherit;
  background:linear-gradient(90deg,#8f6f3f,#b89a68,#d7bd82);
  transition:width .28s ease;
}

.init-tab-pane{
  display:none;
  animation:initFade .28s ease;
}
.init-tab-pane.active{ display:block; }
@keyframes initFade{
  from{ opacity:0; transform:translateY(8px); }
  to{ opacity:1; transform:translateY(0); }
}

.init-section-title{
  margin:0 0 14px 0;
  padding:12px 14px;
  border-radius:16px;
  border:1px solid rgba(11,31,58,.08);
  background:linear-gradient(180deg, rgba(11,31,58,.05), rgba(11,31,58,.025));
  color:var(--uob-navy);
  font-size:15px;
  font-weight:950;
}

.init-label{
  display:block;
  color:var(--uob-navy);
  font-size:14px;
  font-weight:900;
  margin-bottom:8px;
}
.init-input{
  min-height:56px;
  border-radius:16px !important;
  border:1px solid #d9e3ef !important;
  background:#fbfdff !important;
  color:#0f172a !important;
  font-size:15px;
  font-weight:800;
  padding-inline:16px;
  transition:all .18s ease;
  box-shadow:none !important;
}
.init-input::placeholder{
  color:#94a3b8;
  font-weight:700;
}
html[dir="rtl"] .init-evidence-url:placeholder-shown,
body.rtl .init-evidence-url:placeholder-shown{
  direction:rtl;
  text-align:right;
}
html[dir="rtl"] .init-evidence-url:not(:placeholder-shown),
body.rtl .init-evidence-url:not(:placeholder-shown){
  direction:ltr;
  text-align:left;
}
.init-evidence-links-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px}
.init-evidence-links-actions{display:flex;align-items:center;gap:8px}
.init-evidence-count{display:inline-flex;align-items:center;min-height:36px;padding:5px 10px;border-radius:10px;background:rgba(184,154,104,.1);color:#8f6f3f;font-size:13px;font-weight:950;white-space:nowrap}
.init-evidence-link-add,.init-evidence-links-clear{min-height:36px;padding:5px 11px;border-radius:10px;font-size:13px;font-weight:900;cursor:pointer;transition:.18s ease}
.init-evidence-link-add{border:1px solid #b89a68;background:#b89a68;color:#fff}
.init-evidence-links-clear{border:1px solid #fecdd3;background:#fff1f2;color:#be123c}
.init-evidence-link-add:hover{background:#8f6f3f}
.init-evidence-links-clear:hover{background:#ffe4e6}
.init-evidence-links-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.init-evidence-link-row{display:flex;align-items:center;gap:7px;min-width:0}
.init-evidence-link-row .init-input{flex:1;min-width:0;min-height:46px;font-size:13px}
.init-evidence-link-remove{display:inline-flex;align-items:center;justify-content:center;flex:0 0 38px;width:38px;height:38px;border:1px solid #fecdd3;border-radius:11px;background:#fff1f2;color:#be123c;font-size:19px;font-weight:900;cursor:pointer}
.init-evidence-link-remove:hover{background:#ffe4e6}
.init-evidence-file-picker{min-height:48px!important;padding:5px 8px!important}
.init-evidence-files-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:8px;margin-top:9px}
.init-evidence-file-row{display:flex;align-items:center;gap:7px;min-width:0;padding:7px 8px;border:1px solid #d9e3ef;border-radius:12px;background:#fff}
.init-evidence-file-name{flex:1;min-width:0;color:#0b1f3a;font-size:13px;font-weight:850;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.init-evidence-file-download{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:5px 9px;border:1px solid rgba(184,154,104,.4);border-radius:9px;background:rgba(184,154,104,.1);color:#8f6f3f;font-size:12px;font-weight:900;text-decoration:none}
.init-evidence-file-download:hover{background:rgba(184,154,104,.18);color:#8f6f3f}
.init-evidence-upload-status{margin-top:6px;color:#64748b;font-size:12px;font-weight:800}
.init-doc-evidence-block{order:1}
.init-doc-media-block{order:2}
.init-doc-notes-block{order:3}
@media(max-width:767px){.init-evidence-links-list{grid-template-columns:1fr}.init-evidence-files-list{grid-template-columns:1fr}}
@media(max-width:576px){.init-evidence-links-head{align-items:stretch;flex-direction:column}.init-evidence-links-actions{display:grid;grid-template-columns:1fr 1fr}.init-evidence-link-add,.init-evidence-links-clear{width:100%}}
.init-input:hover{
  background:#fff !important;
  border-color:rgba(201,162,39,.45) !important;
}
.init-input:focus{
  background:#fff !important;
  border-color:rgba(201,162,39,.75) !important;
  box-shadow:0 0 0 .22rem rgba(201,162,39,.16) !important;
  transform:translateY(-1px);
}
textarea.init-input{
  min-height:130px;
  padding-top:14px;
  resize:vertical;
}
.form-select.init-input{
  padding-inline-end:42px;
}

.init-form-shell .row.g-4{
  --bs-gutter-x:1.35rem;
  --bs-gutter-y:1.2rem;
}

.init-choice-grid{
  display:grid;
  grid-template-columns:repeat(2, minmax(0,1fr));
  gap:12px;
}
.init-radio-card{
  position:relative;
  display:flex;
  align-items:center;
  gap:10px;
  min-height:60px;
  padding:14px 16px;
  border-radius:18px;
  border:1px solid rgba(219,228,239,.95);
  background:#fbfdff;
  font-weight:900;
  color:var(--text);
  cursor:pointer;
  transition:.18s ease;
}
.init-radio-card:hover{
  background:#fff;
  border-color:rgba(201,162,39,.45);
  transform:translateY(-2px);
}
.init-radio-card-other{
  flex-wrap:nowrap;
}
.init-scope-other-inline{
  flex:1 1 auto;
  min-width:140px;
  margin-inline-start:8px;
}
.init-scope-other-input{
  width:100%;
  min-height:44px;
  border-radius:12px !important;
  background:#fff !important;
}
.init-inline-other-wrap{
  flex:1 1 220px;
  min-width:150px;
}
.init-inline-other-input{
  width:100%;
  min-height:40px;
  border-radius:12px !important;
  background:#fff !important;
}
.init-native-inline-shell{
  display:flex;
  align-items:stretch;
  gap:10px;
}
.init-native-inline-shell > .form-select{
  flex:1 1 45%;
}
.init-native-inline-shell > .init-inline-other-wrap:not(.init-hidden){
  display:flex;
  align-items:center;
}
.ts-control > .init-inline-other-wrap:not(.init-hidden){
  display:flex;
  align-items:center;
  margin:0;
}
.init-ts-add-many{
  width:42px;
  height:42px;
  flex:0 0 42px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:0;
  border-radius:50%;
  background:#b89a68;
  color:#fff;
  font-size:26px;
  font-weight:900;
  line-height:1;
  cursor:pointer;
  transition:.18s ease;
}
.init-ts-add-many:hover{
  background:#8f6f3f;
  transform:translateY(-1px);
}
.init-radio-card > input[type="radio"]{
  width:18px;
  height:18px;
  accent-color:var(--uob-navy);
  flex:0 0 auto;
}
.init-check-grid{
  display:grid;
  grid-template-columns:repeat(2, minmax(0,1fr));
  gap:12px;
}
.init-check-card{
  display:flex;
  align-items:flex-start;
  gap:10px;
  min-height:68px;
  padding:14px 16px;
  border-radius:18px;
  border:1px solid rgba(219,228,239,.95);
  background:#fbfdff;
  cursor:pointer;
  font-weight:800;
  transition:.18s ease;
}
.init-check-card:hover{
  background:#fff;
  border-color:rgba(201,162,39,.45);
  transform:translateY(-2px);
}
.init-check-card input{
  width:18px;
  height:18px;
  accent-color:var(--uob-navy);
  margin-top:3px;
  flex-shrink:0;
}
.init-help{
  margin-top:8px;
  color:var(--muted);
  font-size:12px;
  font-weight:800;
}
.init-hidden{ display:none !important; }

.init-agreement-box{
  margin-top:14px;
  padding:18px;
  border-radius:22px;
  border:1px solid rgba(230,235,242,.95);
  background:
    radial-gradient(420px 120px at 12% 0%, rgba(201,162,39,.12), transparent 60%),
    linear-gradient(180deg, #fff, #f9fbff);
  box-shadow:0 12px 28px rgba(2,8,23,.06);
}
.init-agreement-grid{
  display:grid;
  grid-template-columns:repeat(2, minmax(0,1fr));
  gap:12px;
}
.init-agreement-item{
  padding:12px 14px;
  border-radius:16px;
  background:#fff;
  border:1px solid rgba(230,235,242,.95);
}
.init-agreement-item span{
  display:block;
  color:var(--muted);
  font-size:12px;
  font-weight:800;
}
.init-agreement-item strong{
  display:block;
  margin-top:4px;
  color:var(--uob-navy);
  font-size:15px;
  font-weight:950;
}

.init-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  justify-content:space-between;
  align-items:center;
  margin-top:18px;
  padding-top:18px;
  border-top:1px solid rgba(230,235,242,.95);
}
.init-actions-left,
.init-actions-right{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  align-items:center;
}
.init-btn{
  min-width:150px;
  min-height:52px;
  border-radius:16px !important;
  font-weight:900;
}
.init-btn-nav{
  min-width:120px;
}
.init-alert-success,
.init-alert-danger{
  border:none;
  border-radius:18px;
  box-shadow:0 12px 28px rgba(2,8,23,.08);
  font-weight:800;
  max-width:1180px;
  margin-inline:auto;
}

@media (max-width: 992px){
  .init-admin-hero-inner{
    grid-template-columns:1fr;
    align-items:start;
  }
  .init-form-shell{
    margin-top:-18px;
    padding:22px 18px;
  }
  .init-choice-grid,
  .init-check-grid,
  .init-agreement-grid{
    grid-template-columns:1fr;
  }
  .init-tabs{
    grid-template-columns:repeat(3,minmax(0,1fr));
    padding-top:12px;
  }
  .init-tab-btn{font-size:13px}
}
@media (max-width: 576px){
  .init-form-top{
    flex-direction:column;
  }
  .init-icon-box{
    width:70px; height:70px;
  }
  .init-actions{
    flex-direction:column;
    align-items:stretch;
  }
  .init-actions-left,
  .init-actions-right{
    width:100%;
  }
  .init-btn{
    width:100%;
  }
  .init-tabs{
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:7px;
    padding:10px 7px 0;
  }
  .init-tab-btn{
    min-height:62px;
    font-size:12px;
  }
  .init-progress{
    gap:10px;
    padding:12px;
  }
}

/* Tom Select: isolated, stable RTL/LTR styling */
.init-form-shell,
.init-tab-pane,
.init-tab-pane .row,
.init-tab-pane [class*="col-"]{
  overflow:visible;
}

.ts-wrapper.init-searchable{
  width:100%;
  position:relative;
}

.ts-wrapper.init-searchable.focus,
.ts-wrapper.init-searchable.dropdown-active{
  z-index:1050;
}

.ts-wrapper.init-searchable .ts-control{
  min-height:56px;
  width:100%;
  border:1px solid #d9e3ef;
  border-radius:16px;
  background:#fbfdff;
  padding:9px 12px;
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:7px;
  line-height:1.4;
  box-shadow:none;
}

.ts-wrapper.init-searchable.focus .ts-control{
  border-color:rgba(201,162,39,.75);
  box-shadow:0 0 0 .22rem rgba(201,162,39,.16);
}

html[dir="rtl"] .ts-wrapper.init-searchable .ts-control,
body.rtl .ts-wrapper.init-searchable .ts-control{
  direction:rtl;
  text-align:right;
}

.ts-wrapper.init-searchable.multi .ts-control > .item{
  display:inline-flex;
  align-items:center;
  gap:7px;
  max-width:100%;
  min-height:36px;
  margin:0;
  padding:6px 10px;
  border:0;
  border-radius:999px;
  background:#eef2f7;
  color:#1f2937;
  white-space:normal;
  overflow-wrap:anywhere;
  line-height:1.3;
}

.ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  position:static;
  flex:0 0 auto;
  width:22px;
  height:22px;
  margin:0;
  padding:0;
  border:0;
  border-radius:50%;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  line-height:1;
  background:transparent;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item{
  flex-direction:row-reverse;
}

.ts-wrapper.init-searchable .ts-control > input{
  position:static !important;
  flex:1 1 170px;
  min-width:140px;
  width:auto !important;
  height:34px;
  margin:0 !important;
  padding:3px 5px !important;
  border:0 !important;
  background:transparent !important;
  box-shadow:none !important;
  line-height:1.35;
}

html[dir="rtl"] .ts-wrapper.init-searchable .ts-control > input,
body.rtl .ts-wrapper.init-searchable .ts-control > input{
  direction:rtl;
  text-align:right;
}

/* Keep selected multi-selects compact when the dropdown is closed. */
.ts-wrapper.init-searchable.multi.has-items:not(.dropdown-active) .ts-control > input{
  display:none !important;
}

.ts-wrapper.init-searchable.multi .ts-control{
  height:auto !important;
  min-height:56px !important;
  align-content:center;
}

.ts-wrapper.init-searchable.multi.has-items:not(.dropdown-active) .ts-control{
  padding-top:9px !important;
  padding-bottom:9px !important;
}

/* Closed dropdowns stay closed. Only Tom Select's active dropdown is shown. */
.ts-wrapper.init-searchable:not(.dropdown-active) .ts-dropdown{
  display:none !important;
}

.ts-wrapper.init-searchable.dropdown-active .ts-dropdown{
  display:block;
}

.ts-wrapper.init-searchable .ts-dropdown{
  position:absolute;
  top:calc(100% + 5px);
  right:0;
  left:0;
  width:100%;
  min-width:100%;
  margin:0;
  padding:0;
  border:1px solid #d9e3ef;
  border-radius:14px;
  background:#fff;
  box-shadow:0 16px 36px rgba(2,8,23,.16);
  overflow:hidden;
  z-index:1100;
}

.ts-wrapper.init-searchable .ts-dropdown-content{
  display:block;
  max-height:280px;
  margin:0;
  padding:6px;
  overflow-y:auto;
  overflow-x:hidden;
  background:#fff;
  overscroll-behavior:contain;
}

.ts-wrapper.init-searchable .ts-dropdown .option,
.ts-wrapper.init-searchable .ts-dropdown .create,
.ts-wrapper.init-searchable .ts-dropdown .no-results{
  position:static !important;
  float:none !important;
  display:block !important;
  width:100% !important;
  min-height:44px;
  height:auto !important;
  margin:0 0 3px;
  padding:10px 12px;
  border:0;
  border-radius:9px;
  background:#fff;
  color:#1f2937;
  text-align:start;
  white-space:normal;
  overflow-wrap:anywhere;
  line-height:1.45;
  font-size:15px;
  transform:none !important;
  box-shadow:none !important;
}

.ts-wrapper.init-searchable .ts-dropdown .option:last-child{
  margin-bottom:0;
}

.ts-wrapper.init-searchable .ts-dropdown .option.active,
.ts-wrapper.init-searchable .ts-dropdown .option:hover{
  background:#f1f5f9;
  color:#0b1f3a;
}

.ts-wrapper.init-searchable .ts-dropdown .option.selected{
  background:#eef4ff;
  color:#0b1f3a;
}

html[dir="rtl"] .ts-wrapper.init-searchable .ts-dropdown,
body.rtl .ts-wrapper.init-searchable .ts-dropdown,
html[dir="rtl"] .ts-wrapper.init-searchable .ts-dropdown .option,
body.rtl .ts-wrapper.init-searchable .ts-dropdown .option{
  direction:rtl;
  text-align:right;
}

/* All searchable lists are attached to <body> so they consistently cover
   the fields underneath instead of being clipped by tabs, rows, or cards. */
body > .ts-dropdown.init-searchable-dropdown{
  position:absolute !important;
  margin:5px 0 0 !important;
  padding:0 !important;
  border:1px solid #d9e3ef !important;
  border-radius:14px !important;
  background:#fff !important;
  box-shadow:0 18px 42px rgba(2,8,23,.22) !important;
  overflow:hidden !important;
  z-index:99999 !important;
  isolation:isolate;
}

body > .ts-dropdown.init-searchable-dropdown .ts-dropdown-content{
  display:block !important;
  max-height:280px !important;
  margin:0 !important;
  padding:6px !important;
  overflow-y:auto !important;
  overflow-x:hidden !important;
  background:#fff !important;
  overscroll-behavior:contain;
}

body > .ts-dropdown.init-searchable-dropdown .option,
body > .ts-dropdown.init-searchable-dropdown .create,
body > .ts-dropdown.init-searchable-dropdown .no-results{
  position:static !important;
  float:none !important;
  display:block !important;
  width:100% !important;
  min-height:44px !important;
  height:auto !important;
  margin:0 0 3px !important;
  padding:10px 12px !important;
  border:0 !important;
  border-radius:9px !important;
  background:#fff !important;
  color:#1f2937 !important;
  white-space:normal !important;
  overflow-wrap:anywhere !important;
  line-height:1.45 !important;
  font-size:15px !important;
  transform:none !important;
  box-shadow:none !important;
}

body > .ts-dropdown.init-searchable-dropdown .option.active,
body > .ts-dropdown.init-searchable-dropdown .option:hover{
  background:#f1f5f9 !important;
  color:#0b1f3a !important;
}

body > .ts-dropdown.init-searchable-dropdown .option.selected{
  background:#eef4ff !important;
  color:#0b1f3a !important;
}

html[dir="rtl"] body > .ts-dropdown.init-searchable-dropdown,
body.rtl > .ts-dropdown.init-searchable-dropdown{
  direction:rtl !important;
  text-align:right !important;
}

@media (max-width:576px){
  .ts-wrapper.init-searchable.multi .ts-control > .item{
    max-width:100%;
  }
  .ts-wrapper.init-searchable .ts-control > input{
    flex-basis:100%;
    min-width:100%;
  }
}

.init-suggestions{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  margin-bottom:10px;
}

.init-suggestion-btn{
  border:none;
  background:#eef4ff;
  color:#0b1f3a;
  padding:8px 14px;
  border-radius:999px;
  font-size:13px;
  font-weight:800;
  cursor:pointer;
  transition:.2s;
}

.init-suggestion-btn:hover{
  background:#c9a227;
  color:#fff;
  transform:translateY(-2px);
}


/* Initiative contributors */
.initiative-contributors-section{margin-top:8px;padding:20px;border:1px solid #dfe7f1;border-radius:20px;background:#f9fbfe;}
.contributors-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px;}
.contributors-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.contributor-add-btn{white-space:nowrap;border-radius:14px;font-weight:900;}
.contributor-clear-btn{display:none;white-space:nowrap;border-radius:14px;font-weight:900;align-items:center;gap:10px;padding-inline:14px;}
.contributor-clear-btn.is-visible{display:inline-flex;align-items:center;gap:10px;}
.contributor-clear-btn .btn-icon{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:#fff1f2;box-shadow:inset 0 0 0 1px rgba(190,24,93,.10);flex:0 0 auto;}
.contributor-clear-btn .btn-icon svg{width:17px;height:17px;display:block;}
.contributor-clear-btn:hover .btn-icon{background:#ffe4e6;box-shadow:inset 0 0 0 1px rgba(190,24,93,.18);} 
.contributors-empty{padding:16px;text-align:center;color:#718096;border:1px dashed #cbd5e1;border-radius:14px;background:#fff;font-weight:700;}
.contributor-card{position:relative;margin-top:14px;padding:18px;border:1px solid #d9e3ef;border-radius:18px;background:#fff;box-shadow:0 8px 22px rgba(15,23,42,.05);}
.contributor-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;}
.contributor-card-title{margin:0;color:var(--uob-navy);font-size:15px;font-weight:950;}
.contributor-remove-btn{border:0;background:#fff1f2;color:#be123c;border-radius:10px;padding:7px 11px;font-weight:900;}
.contributor-remove-btn:hover{background:#ffe4e6;}
.contributor-other-wrap{margin-top:10px;}
@media(max-width:576px){.contributors-header{align-items:stretch;flex-direction:column}.contributors-actions{width:100%;flex-direction:column}.contributor-add-btn,.contributor-clear-btn{width:100%;justify-content:center}.contributor-card{padding:15px}}


/* Clear-all icon for multi searchable selects */
.ts-wrapper.init-searchable.multi .init-ts-clear-all{
  display:none;
  align-items:center;
  justify-content:center;
  flex:0 0 auto;
  order:999;
  width:32px;
  height:32px;
  margin:0 2px;
  padding:0;
  border:0;
  border-radius:999px;
  background:#fff1f2;
  color:#be123c;
  cursor:pointer;
  box-shadow:inset 0 0 0 1px rgba(190,24,93,.14);
  transition:background .18s ease, transform .18s ease, box-shadow .18s ease;
}
.ts-wrapper.init-searchable.multi .init-ts-clear-all.is-visible{
  display:inline-flex;
}
.ts-wrapper.init-searchable.multi .init-ts-clear-all:hover{
  background:#ffe4e6;
  box-shadow:inset 0 0 0 1px rgba(190,24,93,.22);
  transform:translateY(-1px);
}
.ts-wrapper.init-searchable.multi .init-ts-clear-all:focus-visible{
  outline:none;
  box-shadow:0 0 0 .18rem rgba(190,24,93,.15), inset 0 0 0 1px rgba(190,24,93,.22);
}
.ts-wrapper.init-searchable.multi .init-ts-clear-all svg{
  width:16px;
  height:16px;
  display:block;
  pointer-events:none;
}

</style>

<section class="init-admin-hero">
  <div class="init-admin-hero-inner">
    <div class="init-admin-copy">
       <h1><?= t('add_initiative_hero_title') ?></h1>
       
    </div>

    <div class="init-admin-stats">
      <div class="init-stat">
        <span><?= t('available_agreements') ?></span>
        <strong><?= count($agreements) ?></strong>
      </div>
      <div class="init-stat">
        <span><?= t('approved_types') ?></span>
        <strong><?= count($initiativeTypes) ?></strong>
      </div>
      <div class="init-stat">
        <span><?= t('multiple_targets') ?></span>
        <strong><?= count($targetGroups) ?></strong>
      </div>
      <div class="init-stat">
        <span><?= t('sdg_goals') ?></span>
        <strong>17 <?= t('goals') ?></strong>
      </div>
    </div>
  </div>
</section>

<?php if ($success): ?>
  <div class="alert alert-success init-alert-success mt-4">
    <?= t('initiative_added_success') ?>
    <a href="../initiatives.php"><?= t('go_to_initiatives') ?></a>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger init-alert-danger mt-4">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="init-form-shell">
 

  <div class="init-tabs" id="initTabs">
    <button type="button" class="init-tab-btn active" data-tab="tab-general"><span class="init-tab-number">1</span><span><?= t('general_info') ?></span></button>
    <button type="button" class="init-tab-btn" data-tab="tab-time"><span class="init-tab-number">2</span><span><?= t('timing_location') ?></span></button>
    <button type="button" class="init-tab-btn" data-tab="tab-beneficiaries"><span class="init-tab-number">3</span><span><?= t('beneficiaries_impact') ?></span></button>
    <button type="button" class="init-tab-btn" data-tab="tab-ranking"><span class="init-tab-number">4</span><span><?= t('rankings_sdgs') ?></span></button>
    <button type="button" class="init-tab-btn" data-tab="tab-docs"><span class="init-tab-number">5</span><span><?= t('documentation_notes') ?></span></button>
  </div>

  <form method="post" id="initiativeForm" enctype="multipart/form-data" novalidate>
    <div class="init-progress">
      <div class="init-progress-track" id="initiativeProgressTrack" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="init-progress-fill" id="initiativeProgressFill"></div>
      </div>
      <span class="init-progress-percent" id="initiativeProgressPercent">0%</span>
    </div>

    <!-- TAB 1 -->
    <div class="init-tab-pane active" id="tab-general">
      <div class="init-section-title"><?= t('basic_data_agreement') ?></div>
      <div class="row g-4 mb-3">
  <div class="col-md-6">
    <label class="init-label">
      <?= $isArabic ? 'رقم طلب الموافقة' : 'Approval Request ID' ?>
    </label>
    <input class="form-control init-input"
           name="approval_request_id"
           value="<?= h($_POST['approval_request_id'] ?? $requestIdFromUrl) ?>"
           placeholder="REQ-20260430225708">
    <div class="init-help">
      <?= $isArabic ? 'يجب إدخال رقم طلب موافق عليه من صفحة طلب الموافقة.' : 'Enter an approved request ID from the approval request page.' ?>
    </div>
  </div>
</div>

      <div class="row g-4">
        <div class="col-12">
          <label class="init-label"><?= $isArabic ? 'هل المبادرة / الفعالية / النشاط المجتمعي مرتبط باتفاقية أو مذكرة تفاهم قائمة؟' : 'Is the initiative / event / community activity linked to an existing agreement or memorandum of understanding?' ?></label>
          <div class="init-choice-grid">
            <?php $rel = $_POST['related_agreement'] ?? (($agreementPrefill !== '') ? 'نعم' : ''); ?>
            <label class="init-radio-card">
              <input type="radio" name="related_agreement" value="نعم" <?= $rel === 'نعم' ? 'checked' : '' ?>>
              <?= t('yes_related') ?>
            </label>
            <label class="init-radio-card">
              <input type="radio" name="related_agreement" value="لا" <?= $rel === 'لا' ? 'checked' : '' ?>>
              <?= t('no_related') ?>
            </label>
          </div>
        </div>

        <div class="col-12 <?= ($rel === 'نعم') ? '' : 'init-hidden' ?>" id="agreementSelectWrap">
          <label class="init-label"><?= $isArabic ? 'اسم الاتفاقية أو الجهة الشريكة' : 'Agreement Name or Partner Entity' ?></label>
          <select class="form-select init-input init-searchable" name="_agreement_code" id="agreementSelect">
            <option value=""><?= t('select_placeholder') ?></option>
            <?php foreach ($agreements as $code => $a): ?>
              <option value="<?= h($code) ?>" <?= $selectedAgreementCode === $code ? 'selected' : '' ?>>
                <?= h($code) ?> — <?= h($a['اسم الاتفاقية'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= $isArabic ? 'يرجى اختيار اسم الاتفاقية أو الجهة الشريكة المرتبطة بالمبادرة / الفعالية من القائمة.' : 'Please select the agreement name or partner entity related to the initiative / event from the available list.' ?></div>

          <div class="init-agreement-box <?= ($selectedAgreementCode && isset($agreements[$selectedAgreementCode])) ? '' : 'init-hidden' ?>" id="agreementInfoBox">
            <div class="init-agreement-grid">
              <div class="init-agreement-item">
                <span><?= t('agreement_name') ?></span>
                <strong id="ag_name"><?= h($agreements[$selectedAgreementCode]['اسم الاتفاقية'] ?? '—') ?></strong>
              </div>
              <div class="init-agreement-item">
                <span><?= t('agreement_type') ?></span>
                <strong id="ag_type"><?= h($agreements[$selectedAgreementCode]['نوع الاتفاقية'] ?? '—') ?></strong>
              </div>
              <div class="init-agreement-item">
                <span><?= t('partner_entity') ?></span>
                <strong id="ag_partner"><?= h($agreements[$selectedAgreementCode]['الجهة المتعاونة'] ?? '—') ?></strong>
              </div>
              <div class="init-agreement-item">
                <span><?= t('country') ?></span>
                <strong id="ag_country"><?= h($agreements[$selectedAgreementCode]['الدولة'] ?? '—') ?></strong>
              </div>
              <div class="init-agreement-item">
                <span><?= t('responsible_entity') ?></span>
                <strong id="ag_owner"><?= h($agreements[$selectedAgreementCode]['الجهة المعنية بتنفيذ الاتفاقية'] ?? '—') ?></strong>
              </div>
              <div class="init-agreement-item">
                <span><?= t('agreement_status') ?></span>
                <strong id="ag_status"><?= h($agreements[$selectedAgreementCode]['الحالة'] ?? '—') ?></strong>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <label class="init-label"><?= t('initiative_number') ?></label>
          <input class="form-control init-input" name="رقم المبادرة" value="<?= h($_POST['رقم المبادرة'] ?? '') ?>" placeholder="<?= t('example_number') ?>">
        </div>

        <div class="col-12">
          <label class="init-label"><?= $isArabic ? 'عنوان المبادرة / الفعالية / النشاط المجتمعي' : 'Initiative / Event / Community Activity Title' ?></label>
          <input class="form-control init-input" name="عنوان المبادرة" value="<?= h($_POST['عنوان المبادرة'] ?? '') ?>" placeholder="<?= t('write_title') ?>">
          <div class="init-help"><?= $isArabic?'اكتب عنوانًا واضحًا ومختصرًا. قد تكون المبادرة فعالية أو ورشة عمل أو مشروعًا مجتمعيًا.':'Enter a clear, concise title. The initiative may be an event, workshop, or community project.' ?></div>
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'نوع المبادرة الرئيسي':'Primary Initiative Type' ?></label>
          <select class="form-select init-input init-searchable" name="نوع المبادرة" id="initiativeTypeSelect">
            <option value=""><?= t('select_type') ?></option>
            <?php $vType = $_POST['نوع المبادرة'] ?? ''; ?>
            <?php foreach ($initiativeTypes as $opt): ?>
              <option value="<?= h($opt['value']) ?>" <?= $vType === $opt['value'] ? 'selected' : '' ?>><?= h($isArabic ? $opt['ar'] : $opt['en']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="init-inline-other-wrap <?= $vType==='other'?'':'init-hidden' ?>" id="initiativeTypeOtherWrap">
            <input class="form-control init-input init-inline-other-input" name="initiative_type_other" value="<?= h($_POST['initiative_type_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب النوع الرئيسي الآخر':'Enter the other primary type' ?>">
          </span>
          <div class="init-help"><?= $isArabic?'اختر النوع الأساسي الذي يعبّر عن المبادرة بشكل أدق.':'Choose the single main type that best represents the initiative.' ?></div>
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'أنواع المبادرة الثانوية (اختياري)':'Secondary Initiative Types (Optional)' ?></label>
          <?php $secondaryTypesSelected=$_POST['secondary_initiative_types']??[]; if(!is_array($secondaryTypesSelected))$secondaryTypesSelected=[]; ?>
          <select class="form-select init-input init-searchable" name="secondary_initiative_types[]" id="secondaryInitiativeTypesSelect" multiple data-allow-create="true" data-add-many="true" data-placeholder="<?= $isArabic?'ابحث أو اكتب نوعًا ثانويًا جديدًا ثم اضغط +':'Search or type a new secondary type, then press +' ?>">
            <?php foreach ($initiativeTypes as $opt): ?>
              <?php if($opt['value']==='other') continue; ?>
              <option value="<?= h($opt['value']) ?>" <?= in_array($opt['value'],$secondaryTypesSelected,true)?'selected':'' ?>><?= h($isArabic ? $opt['ar'] : $opt['en']) ?></option>
            <?php endforeach; ?>
            <?php $knownInitiativeTypeValues=array_column($initiativeTypes,'value'); foreach($secondaryTypesSelected as $customSecondaryType): ?>
              <?php if($customSecondaryType==='' || $customSecondaryType==='other' || in_array($customSecondaryType,$knownInitiativeTypeValues,true)) continue; ?>
              <option value="<?= h($customSecondaryType) ?>" selected><?= h($customSecondaryType) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= $isArabic?'يمكن اختيار أكثر من نوع، أو كتابة نوع جديد ثم الضغط على +.':'You may select multiple types, or type a new one and press +.' ?></div>
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('executing_entity') ?></label>
          <input class="form-control init-input" name="الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)" value="<?= h($_POST['الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)'] ?? '') ?>" placeholder="<?= t('example_entity') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'الجهات الخارجية المشاركة في النشاط أو الداعمة له (إن وجدت)':'External Entities Participating in or Supporting the Activity (if any)' ?></label>
          <input class="form-control init-input" name="external_entities" value="<?= h($_POST['external_entities']??'') ?>" placeholder="<?= $isArabic?'مثال: مدرسة، جهة حكومية، شركة أو جمعية أهلية':'Example: a school, government entity, company, or NGO' ?>">
        </div>

        <div class="col-12">
          <label class="init-label"><?= $isArabic?'من الفئات المقدمة أو المنفذة للنشاط؟ (اختر كل ما ينطبق)':'Which Categories Deliver or Implement the Activity? (Select All That Apply)' ?></label>
          <?php $selectedProviderCategories=$_POST['provider_categories']??[]; if(!is_array($selectedProviderCategories))$selectedProviderCategories=[]; ?>
          <div class="init-check-grid">
            <?php foreach([
              'academic'=>['أعضاء الهيئة الأكاديمية','Academic Staff'],
              'administrative'=>['الهيئة الإدارية','Administrative Staff'],
              'students'=>['الطلبة','Students'],
              'student_group'=>['مجموعة أو نادٍ طلابي','Student Group or Club'],
              'joint'=>['تنفيذ مشترك بين أكثر من فئة','Joint Delivery by Multiple Categories'],
              'external_partner'=>['بالتعاون مع شريك خارجي','With an External Partner'],
            ] as $value=>$labels): ?>
              <label class="init-check-card"><input type="checkbox" name="provider_categories[]" value="<?= h($value) ?>" <?= in_array($value,$selectedProviderCategories,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'القسم / المركز / الوحدة':'Department / Center / Unit' ?></label>
          <?php $selectedDepartment=$_POST['department_unit']??''; ?>
          <select class="form-select init-input init-searchable" name="department_unit" id="departmentUnitSelect">
            <option value=""><?= $isArabic?'اختر الجهة':'Select an option' ?></option>
            <?php foreach($departmentOptions as $department): ?>
              <option value="<?= h($department['value']) ?>" <?= $selectedDepartment===$department['value']?'selected':'' ?>><?= h($isArabic?$department['ar']:$department['en']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="init-inline-other-wrap <?= $selectedDepartment==='other'?'':'init-hidden' ?>" id="departmentUnitOtherWrap">
            <input class="form-control init-input init-inline-other-input" name="department_unit_other" value="<?= h($_POST['department_unit_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب القسم أو المركز أو الوحدة الأخرى':'Enter the other department, center, or unit' ?>">
          </span>
        </div>
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic ? 'القسم التابع للكلية أو الجهة (إن وجد)' : 'Department within the College or Entity (if applicable)' ?></label>
          <?php $selectedDepartmentWithinCollege=$_POST['department_within_college']??''; ?>
          <select class="form-select init-input init-searchable" name="department_within_college" id="departmentWithinCollegeSelect" data-allow-create="true" data-placeholder="<?= $isArabic ? 'ابحث عن القسم أو اكتب قسمًا آخر' : 'Search for a department or enter another one' ?>">
            <option value=""><?= $isArabic?'ابحث عن القسم أو اختره':'Search for or select a department' ?></option>
            <?php foreach($academicDepartmentOptions as $department): ?>
              <?php if($department['value']==='other') continue; ?>
              <option value="<?= h($department['value']) ?>" <?= $selectedDepartmentWithinCollege===$department['value']?'selected':'' ?>><?= h($isArabic?$department['ar']:$department['en']) ?></option>
            <?php endforeach; ?>
            <?php if($selectedDepartmentWithinCollege!=='' && !in_array($selectedDepartmentWithinCollege,array_column($academicDepartmentOptions,'value'),true)): ?>
              <option value="<?= h($selectedDepartmentWithinCollege) ?>" selected><?= h($selectedDepartmentWithinCollege) ?></option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-12 initiative-contributors-section">
          <div class="contributors-header">
            <div>
              <label class="init-label mb-1"><?= $isArabic ? 'الأشخاص المسؤولون عن المبادرة' : 'People Responsible for the Initiative' ?></label>
              <div class="init-help mt-0"><?= $isArabic ? 'أضف جميع الأشخاص المسؤولين، ثم حدد مسؤولًا رئيسيًا للتواصل ومنسقًا للمبادرة.' : 'Add all responsible people, then select a primary contact and an initiative coordinator.' ?></div>
            </div>
            <div class="contributors-actions">
              <button type="button" class="btn btn-outline-danger contributor-clear-btn" id="clearAllContributorsBtn" title="<?= $isArabic ? 'حذف جميع المسؤولين' : 'Remove all responsible people' ?>">
                <span class="btn-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M9 4.75h6a1 1 0 0 1 .95.68L16.3 7H7.7l.35-1.57A1 1 0 0 1 9 4.75Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M8 10v6.5M12 10v6.5M16 10v6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M6.8 7h10.4l-.72 10.1A2 2 0 0 1 14.49 19H9.51a2 2 0 0 1-1.99-1.9L6.8 7Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>
                </span>
                <?= $isArabic ? 'حذف جميع المسؤولين' : 'Remove All Responsible People' ?>
              </button>
              <button type="button" class="btn btn-outline-primary contributor-add-btn" id="addContributorBtn">+ <?= $isArabic ? 'إضافة شخص مسؤول' : 'Add Responsible Person' ?></button>
            </div>
          </div>
          <input type="hidden" name="initiative_contributors_json" id="initiativeContributorsJson" value="">
          <div id="contributorsContainer"></div>
          <div class="contributors-empty" id="contributorsEmpty"><?= $isArabic ? 'لم تتم إضافة أشخاص مسؤولين.' : 'No responsible people have been added.' ?></div>
        </div>

        <div class="col-12 initiative-contributors-section">
          <div class="contributors-header">
            <div>
              <label class="init-label mb-1"><?= $isArabic ? 'المشاركون في تنفيذ المبادرة' : 'Participants in Initiative Implementation' ?></label>
              <div class="init-help mt-0"><?= $isArabic ? 'أضف الأشخاص الذين ساهموا في قيادة المبادرة أو تنظيمها أو تنفيذها. لا يشمل ذلك الجمهور أو المستفيدين.' : 'Add people who helped lead, organize, or implement the initiative. This does not include the audience or beneficiaries.' ?></div>
            </div>
            <div class="contributors-actions">
              <button type="button" class="btn btn-outline-danger contributor-clear-btn" id="clearAllParticipantsBtn" title="<?= $isArabic ? 'حذف جميع المشاركين' : 'Remove all participants' ?>">
                <span class="btn-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M9 4.75h6a1 1 0 0 1 .95.68L16.3 7H7.7l.35-1.57A1 1 0 0 1 9 4.75Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M8 10v6.5M12 10v6.5M16 10v6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M6.8 7h10.4l-.72 10.1A2 2 0 0 1 14.49 19H9.51a2 2 0 0 1-1.99-1.9L6.8 7Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>
                </span>
                <?= $isArabic ? 'حذف جميع المشاركين' : 'Remove All Participants' ?>
              </button>
              <button type="button" class="btn btn-outline-primary contributor-add-btn" id="addParticipantBtn">+ <?= $isArabic ? 'إضافة مشارك' : 'Add Participant' ?></button>
            </div>
          </div>
          <input type="hidden" name="initiative_participants_json" id="initiativeParticipantsJson" value="">
          <div id="participantsContainer"></div>
          <div class="contributors-empty" id="participantsEmpty"><?= $isArabic ? 'لم تتم إضافة مشاركين.' : 'No participants have been added.' ?></div>
        </div>

      </div>
    </div>

    <!-- TAB 2 -->
    <div class="init-tab-pane" id="tab-time">
      <div class="init-section-title"><?= t('timing_location') ?></div>

      <div class="row g-4">
        <div class="col-md-6">
          <label class="init-label"><?= t('start_date') ?></label>
          <input type="date" class="form-control init-input" name="تاريخ تنفيذ المبادرة" value="<?= h($_POST['تاريخ تنفيذ المبادرة'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('end_date') ?></label>
          <input type="date" class="form-control init-input" name="تاريخ انتهاء المبادرة" value="<?= h($_POST['تاريخ انتهاء المبادرة'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="init-label"><?= $isArabic?'حالة النشاط':'Activity Status' ?></label>
          <?php $selectedActivityStatus=$_POST['activity_status']??''; ?>
          <select class="form-select init-input" name="activity_status">
            <option value=""><?= $isArabic?'اختر الحالة':'Select status' ?></option>
            <?php foreach(['planned'=>['مخطط','Planned'],'ongoing'=>['جارٍ','Ongoing'],'completed'=>['مكتمل','Completed'],'cancelled'=>['ملغى','Cancelled']] as $value=>$labels): ?>
              <option value="<?= h($value) ?>" <?= $selectedActivityStatus===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="init-label"><?= $isArabic?'تكرار النشاط':'Activity Recurrence' ?></label>
          <?php $selectedRecurrence=$_POST['activity_recurrence']??''; ?>
          <select class="form-select init-input" name="activity_recurrence">
            <option value=""><?= $isArabic?'اختر التكرار':'Select recurrence' ?></option>
            <?php foreach(['once'=>['مرة واحدة','One-time'],'weekly'=>['أسبوعي','Weekly'],'monthly'=>['شهري','Monthly'],'annual'=>['سنوي','Annual'],'continuous'=>['مستمر','Continuous']] as $value=>$labels): ?>
              <option value="<?= h($value) ?>" <?= $selectedRecurrence===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="init-label"><?= $isArabic?'السنة الأكاديمية / فترة التقرير':'Academic Year / Reporting Period' ?></label>
          <input class="form-control init-input" name="academic_year" value="<?= h($_POST['academic_year']??'') ?>" placeholder="<?= $isArabic?'مثال: 2025/2026':'Example: 2025/2026' ?>">
        </div>
        <div class="col-md-3">
          <label class="init-label"><?= $isArabic?'مدة النشاط بالساعات (اختياري)':'Duration in Hours (Optional)' ?></label>
          <input type="number" min="0" step="0.5" class="form-control init-input" name="duration_hours" value="<?= h($_POST['duration_hours']??'') ?>">
        </div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'نطاق تنفيذ المبادرة':'Initiative Implementation Scope' ?></label>
          <?php $locationMode = $_POST['location_mode'] ?? ''; ?>
          <div class="init-check-grid">
            <?php $scopeOptions=[
              'within_uob'=>['ar'=>'داخل جامعة البحرين','en'=>'Within the University of Bahrain'],
              'outside_uob'=>['ar'=>'خارج جامعة البحرين','en'=>'Outside the University of Bahrain'],
              'virtual'=>['ar'=>'افتراضي أو عن بُعد','en'=>'Virtual'],
              'hybrid'=>['ar'=>'مختلط','en'=>'Hybrid'],
            ]; foreach($scopeOptions as $value=>$label): ?>
              <label class="init-radio-card">
                <input type="radio" name="location_mode" value="<?= h($value) ?>" <?= $locationMode===$value?'checked':'' ?>>
                <span><?= h($isArabic?$label['ar']:$label['en']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12 <?= $locationMode==='outside_uob'?'':'init-hidden' ?>" id="outsideLocationWrap">
          <label class="init-label"><?= $isArabic?'مكان التنفيذ / اسم موقع المبادرة':'Venue / Location Name' ?></label>
          <input class="form-control init-input" name="outside_location" value="<?= h($_POST['outside_location'] ?? '') ?>" placeholder="<?= $isArabic?'مثال: جامعة البحرين، اسم المدرسة، المؤسسة الحكومية، الشركة، المركز التدريبي أو غيرها':'Example: University of Bahrain, school name, government entity name, company name, training center name, or others.' ?>">
          <div class="init-help"><?= $isArabic?'موقع تنفيذ المبادرة / الفعالية / النشاط المجتمعي.':'Community Initiative / Event / Activity Location.' ?></div>
        </div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'هل تتضمن المبادرة مشاركة أو تعاونًا دوليًا؟':'Does the Initiative Include International Participation or Collaboration?' ?></label>
          <?php $selectedInternationalParticipation=$_POST['international_participation']??''; ?>
          <div class="init-choice-grid">
            <label class="init-radio-card"><input type="radio" name="international_participation" value="yes" <?= $selectedInternationalParticipation==='yes'?'checked':'' ?>><span><?= t('yes') ?></span></label>
            <label class="init-radio-card"><input type="radio" name="international_participation" value="no" <?= $selectedInternationalParticipation==='no'?'checked':'' ?>><span><?= t('no') ?></span></label>
          </div>
        </div>
        <div class="col-12 <?= $selectedInternationalParticipation==='yes'?'':'init-hidden' ?>" id="internationalDetailsWrap">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'الدول المشاركة':'Participating Countries' ?></label>
              <?php $selectedInternationalCountries=$_POST['international_countries']??[]; if(!is_array($selectedInternationalCountries))$selectedInternationalCountries=[]; ?>
              <select class="form-select init-input init-searchable" name="international_countries[]" id="internationalCountriesSelect" multiple data-allow-create="true" data-add-many="true" data-placeholder="<?= $isArabic?'اكتب اسم الدولة ثم اضغط +':'Enter a country, then press +' ?>">
                <?php foreach($selectedInternationalCountries as $country): ?><option value="<?= h($country) ?>" selected><?= h($country) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'عدد الدول المشاركة':'Number of Participating Countries' ?></label><input type="number" min="0" class="form-control init-input" id="internationalCountryCount" value="<?= count($selectedInternationalCountries) ?>" readonly></div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'عدد المشاركين الدوليين':'Number of International Participants' ?></label><input type="number" min="0" class="form-control init-input" name="international_participants" value="<?= h($_POST['international_participants']??'0') ?>"></div>
            <div class="col-md-6"><label class="init-label"><?= $isArabic?'اسم الشريك الدولي (إن وجد)':'International Partner Name (if any)' ?></label><input class="form-control init-input" name="international_partner" value="<?= h($_POST['international_partner']??'') ?>"></div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'نوع الشريك الدولي':'International Partner Type' ?></label>
              <?php $selectedInternationalPartnerType=$_POST['international_partner_type']??''; ?>
              <select class="form-select init-input" name="international_partner_type">
                <option value=""><?= $isArabic?'اختر النوع':'Select type' ?></option>
                <?php foreach(['university'=>['جامعة','University'],'research_center'=>['مركز بحثي','Research Center'],'international_organization'=>['منظمة دولية','International Organization'],'government'=>['جهة حكومية','Government Entity'],'company'=>['شركة','Company'],'ngo'=>['منظمة غير ربحية','NGO'],'other'=>['أخرى','Other']] as $value=>$labels): ?>
                  <option value="<?= h($value) ?>" <?= $selectedInternationalPartnerType===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="init-label"><?= $isArabic?'طبيعة التعاون الدولي (اختر كل ما ينطبق)':'Nature of International Collaboration (Select All That Apply)' ?></label>
              <?php $selectedInternationalCollaboration=$_POST['international_collaboration_nature']??[]; if(!is_array($selectedInternationalCollaboration))$selectedInternationalCollaboration=[]; ?>
              <div class="init-check-grid">
                <?php foreach(['research'=>['بحث','Research'],'teaching'=>['تعليم','Teaching'],'training'=>['تدريب','Training'],'exchange'=>['تبادل','Exchange'],'funding'=>['تمويل','Funding'],'joint_organization'=>['تنظيم مشترك','Joint Organization'],'knowledge_transfer'=>['نقل معرفة','Knowledge Transfer']] as $value=>$labels): ?>
                  <label class="init-check-card"><input type="checkbox" name="international_collaboration_nature[]" value="<?= h($value) ?>" <?= in_array($value,$selectedInternationalCollaboration,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'مجالات مساهمة المبادرة (اختر كل ما ينطبق)':'Initiative Contribution Areas (Select All That Apply)' ?></label>
          <?php $selectedDescriptors=$_POST['initiative_descriptors']??[]; if(!is_array($selectedDescriptors))$selectedDescriptors=[]; ?>
          <select class="form-select init-input init-searchable" name="initiative_descriptors[]" id="initiativeDescriptorsSelect" multiple data-allow-create="true" data-add-many="true" data-placeholder="<?= $isArabic?'اختر مجالًا أو اكتب مجالًا إضافيًا':'Select or enter an additional area' ?>">
            <?php foreach($initiativeDescriptors as $descriptor): ?>
              <?php if($descriptor['value']==='other') continue; ?>
              <option value="<?= h($descriptor['value']) ?>" <?= in_array($descriptor['value'],$selectedDescriptors,true)?'selected':'' ?>><?= h($isArabic?$descriptor['ar']:$descriptor['en']) ?></option>
            <?php endforeach; ?>
            <?php $knownDescriptorValues=array_column($initiativeDescriptors,'value'); foreach($selectedDescriptors as $customDescriptor): ?>
              <?php if($customDescriptor==='' || in_array($customDescriptor,$knownDescriptorValues,true)) continue; ?>
              <option value="<?= h($customDescriptor) ?>" selected><?= h($customDescriptor) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= $isArabic?'هذا الحقل مخصص للتصنيف والتقارير، بينما الحقل التالي للوصف التفصيلي.':'This field is used for classification and reporting; use the next field for the narrative description.' ?></div>
        </div>
<!-- 🔥 الوصف + suggestions -->
    <div class="col-12">

      <label class="init-label"><?= $isArabic ? 'وصف المبادرة / الفعالية / النشاط المجتمعي وأهدافها' : 'Description and Objectives of the Community Initiative / Event / Activity' ?></label>
      <div class="init-help mb-2"><?= $isArabic ? 'اختر الوصف الأنسب من الاقتراحات، ثم أكمل بوصف مختصر يوضح طبيعة المبادرة وأهدافها الرئيسية.' : 'Choose the most suitable description from the suggestions, then add a brief explanation of the initiative and its main objectives.' ?></div>

      <!-- suggestions -->
      <div class="init-suggestions">
        <?php $descriptionSuggestions = [
          ['ar'=>'تطوير مهارات الطلبة','en'=>'Developing Students’ Skills'],
          ['ar'=>'تعزيز البحث العلمي','en'=>'Advancing Scientific Research'],
          ['ar'=>'دعم الابتكار','en'=>'Supporting Innovation'],
        ]; ?>
        <?php foreach ($descriptionSuggestions as $suggestion): ?>
          <button type="button" class="init-suggestion-btn" onclick="addText(this,'descBox')">
            <?= h($isArabic ? $suggestion['ar'] : $suggestion['en']) ?>
          </button>
        <?php endforeach; ?>
        <?php foreach ($initiativeDescriptors as $descriptor): ?>
          <?php if ($descriptor['value'] === 'other') continue; ?>
          <button type="button" class="init-suggestion-btn" onclick="addText(this,'descBox')">
            <?= h($isArabic ? $descriptor['ar'] : $descriptor['en']) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <textarea id="descBox"
        class="form-control init-input"
        name="نبذة عن المبادرة وأهدافها"
        rows="5"
        placeholder="<?= t('write_description') ?>"><?= h($_POST['نبذة عن المبادرة وأهدافها'] ?? '') ?></textarea>

    </div>

  </div>
</div> <!-- ✅ مهم جداً: اقفال TAB 2 -->



    <!-- TAB 3 -->
    <div class="init-tab-pane" id="tab-beneficiaries">
      <div class="init-section-title"><?= t('beneficiaries_impact') ?></div>

      <div class="row g-4">
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'من هم الجمهور المستهدف أو المشاركون أو المستفيدون؟ (اختر كل ما ينطبق)':'Who are the target audience, participants, or beneficiaries? (Select all that apply)' ?></label>
          <?php $selTargets = $_POST['target_groups'] ?? []; if (!is_array($selTargets)) $selTargets = []; ?>
          <select class="form-select init-input init-searchable" name="target_groups[]" id="targetGroupsSelect" multiple data-allow-create="true" data-add-many="true" data-placeholder="<?= $isArabic?'ابحث أو اكتب فئة جديدة ثم اضغط +':'Search or type a new audience, then press +' ?>">
            <?php foreach ($targetGroups as $tg): ?>
              <?php if($tg['value']==='other') continue; ?>
              <option value="<?= h($tg['value']) ?>" <?= in_array($tg['value'], $selTargets, true) ? 'selected' : '' ?>><?= h($isArabic ? $tg['ar'] : $tg['en']) ?></option>
            <?php endforeach; ?>
            <?php $knownTargetValues=array_column($targetGroups,'value'); foreach($selTargets as $customTarget): ?>
              <?php if($customTarget==='' || $customTarget==='other' || in_array($customTarget,$knownTargetValues,true)) continue; ?>
              <option value="<?= h($customTarget) ?>" selected><?= h($customTarget) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= $isArabic?'لإضافة فئة غير موجودة: اكتبها في خانة البحث واضغط زر +. يمكنك إضافة أي عدد من الفئات.':'To add a new audience: type it in the search field and press +. You can add as many as needed.' ?></div>
        </div>

        <div class="col-md-4">
          <label class="init-label"><?= t('beneficiaries_male') ?></label>
          <input type="number" min="0" class="form-control init-input" name="male_count" id="maleBeneficiariesCount" value="<?= h($_POST['male_count'] ?? '0') ?>">
        </div>

        <div class="col-md-4">
          <label class="init-label"><?= t('beneficiaries_female') ?></label>
          <input type="number" min="0" class="form-control init-input" name="female_count" id="femaleBeneficiariesCount" value="<?= h($_POST['female_count'] ?? '0') ?>">
        </div>
        <div class="col-md-4">
          <label class="init-label"><?= $isArabic?'عدد المستفيدين الذين لم تتوفر بيانات الجنس عنهم':'Beneficiaries Without Available Gender Data' ?></label>
          <input type="number" min="0" class="form-control init-input" name="unspecified_count" id="unspecifiedBeneficiariesCount" value="<?= h($_POST['unspecified_count'] ?? '0') ?>">
          <div class="init-help"><?= $isArabic?'يُستخدم عند توفر العدد الإجمالي دون توفر توزيع المستفيدين إلى ذكور وإناث.':'Use this when the total number is available but its distribution between males and females is not.' ?></div>
        </div>

        <div class="col-12">
          <?php $youth = $_POST['youth_18_35'] ?? ''; ?>
          <label class="init-label"><?= t('youth_question') ?></label>
          <div class="init-choice-grid">
            <label class="init-radio-card">
              <input type="radio" name="youth_18_35" value="نعم" <?= $youth === 'نعم' ? 'checked' : '' ?>>
              <?= t('yes') ?>
            </label>
            <label class="init-radio-card">
              <input type="radio" name="youth_18_35" value="لا" <?= $youth === 'لا' ? 'checked' : '' ?>>
              <?= t('no') ?>
            </label>
          </div>
        </div>
        <div class="col-md-6"><label class="init-label"><?= $isArabic?'إجمالي عدد الحضور أو المشاركين أو المستفيدين':'Total Number of Attendees, Participants, or Beneficiaries' ?></label><input type="number" min="0" class="form-control init-input" name="total_attendees" id="totalAttendeesCount" value="<?= h((string)((int)($_POST['male_count'] ?? 0) + (int)($_POST['female_count'] ?? 0) + (int)($_POST['unspecified_count'] ?? 0))) ?>" readonly></div>
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'أساس احتساب العدد':'Count Basis' ?></label>
          <?php $selectedCountBasis=$_POST['beneficiary_count_basis']??''; ?>
          <div class="init-choice-grid">
            <label class="init-radio-card"><input type="radio" name="beneficiary_count_basis" value="actual" <?= $selectedCountBasis==='actual'?'checked':'' ?>><span><?= $isArabic?'فعلي من سجل أو حضور':'Actual from Registration or Attendance' ?></span></label>
            <label class="init-radio-card"><input type="radio" name="beneficiary_count_basis" value="estimated" <?= $selectedCountBasis==='estimated'?'checked':'' ?>><span><?= $isArabic?'تقديري':'Estimated' ?></span></label>
          </div>
        </div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'الموارد المستخدمة في تنفيذ المبادرة (اختر كل ما ينطبق)':'Resources Used to Deliver the Initiative (Select All That Apply)' ?></label>
          <?php $selectedResources=$_POST['resources_mobilized_options']??[]; if(!is_array($selectedResources))$selectedResources=[]; ?>
          <div class="init-check-grid">
            <?php foreach([
              'budget'=>['ميزانية داخلية','Internal Budget'],
              'external_funding'=>['تمويل خارجي','External Funding'],
              'volunteers'=>['متطوعون','Volunteers'],
              'staff_hours'=>['ساعات عمل موظفين أو أعضاء هيئة أكاديمية','Staff or Academic Work Hours'],
              'facilities'=>['مرافق أو قاعات','Facilities or Venues'],
              'equipment'=>['معدات أو مواد','Equipment or Materials'],
              'partnerships'=>['شراكات','Partnerships'],
            ] as $value=>$labels): ?>
              <label class="init-check-card"><input type="checkbox" name="resources_mobilized_options[]" value="<?= h($value) ?>" <?= in_array($value,$selectedResources,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
          <input class="form-control init-input mt-2" name="resources_mobilized_other" value="<?= h($_POST['resources_mobilized_other']??'') ?>" placeholder="<?= $isArabic?'موارد أخرى أو تفاصيل إضافية (اختياري)':'Other resources or additional details (optional)' ?>">
        </div>
        <div class="col-md-6 <?= in_array('budget',$selectedResources,true)?'':'init-hidden' ?>" id="internalFundingWrap">
          <label class="init-label"><?= $isArabic?'قيمة الميزانية الداخلية (دينار بحريني)':'Internal Budget Amount (BHD)' ?></label>
          <input type="number" min="0" step="0.001" class="form-control init-input" name="internal_funding_bhd" value="<?= h($_POST['internal_funding_bhd']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'قيمة الدعم العيني التقديرية (دينار بحريني — اختياري)':'Estimated In-Kind Support Value (BHD — Optional)' ?></label>
          <input type="number" min="0" step="0.001" class="form-control init-input" name="in_kind_support_bhd" value="<?= h($_POST['in_kind_support_bhd']??'') ?>">
        </div>
        <div class="col-12 <?= in_array('external_funding',$selectedResources,true)?'':'init-hidden' ?>" id="externalFundingWrap">
          <div class="row g-3">
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'قيمة التمويل الخارجي':'External Funding Amount' ?></label><input type="number" min="0" step="0.001" class="form-control init-input" name="external_funding_amount" value="<?= h($_POST['external_funding_amount']??'') ?>"></div>
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'عملة التمويل':'Funding Currency' ?></label><input class="form-control init-input" name="external_funding_currency" value="<?= h($_POST['external_funding_currency']??'') ?>" placeholder="<?= $isArabic?'مثال: BHD أو USD':'Example: BHD or USD' ?>"></div>
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'اسم الجهة الممولة':'Funding Entity' ?></label><input class="form-control init-input" name="funding_entity" value="<?= h($_POST['funding_entity']??'') ?>"></div>
          </div>
        </div>
        <?php
          $postedMainType=$_POST['نوع المبادرة']??'';
          $postedSecondaryTypes=$_POST['secondary_initiative_types']??[]; if(!is_array($postedSecondaryTypes))$postedSecondaryTypes=[];
          $trainingUiTypes=['workshop_training','capacity_building_training','tutoring_coaching_mentorship','volunteer_teaching_training'];
          $volunteerUiTypes=['volunteering_program','volunteer_teaching_training'];
          $showTrainingFields=in_array($postedMainType,$trainingUiTypes,true)||(bool)array_intersect($postedSecondaryTypes,$trainingUiTypes);
          $showVolunteerFields=in_array($postedMainType,$volunteerUiTypes,true)||(bool)array_intersect($postedSecondaryTypes,$volunteerUiTypes)||in_array('volunteers',$selectedResources,true);
        ?>
        <div class="col-12 <?= $showTrainingFields?'':'init-hidden' ?>" id="trainingMetricsWrap">
          <div class="row g-3">
            <div class="col-md-6"><label class="init-label"><?= $isArabic?'عدد ساعات التدريب':'Training Hours' ?></label><input type="number" min="0" step="0.5" class="form-control init-input" name="training_hours" value="<?= h($_POST['training_hours']??'') ?>"></div>
            <div class="col-md-6"><label class="init-label"><?= $isArabic?'عدد المتدربين':'Number of Trainees' ?></label><input type="number" min="0" class="form-control init-input" name="trainees_count" value="<?= h($_POST['trainees_count']??'0') ?>"></div>
          </div>
        </div>
        <div class="col-12 <?= $showVolunteerFields?'':'init-hidden' ?>" id="volunteerMetricsWrap">
          <div class="row g-3">
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'عدد المتطوعين':'Number of Volunteers' ?></label><input type="number" min="0" class="form-control init-input" name="volunteers_count" id="volunteersCount" value="<?= h($_POST['volunteers_count']??'0') ?>"></div>
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'متوسط ساعات التطوع للفرد':'Average Volunteer Hours per Person' ?></label><input type="number" min="0" step="0.5" class="form-control init-input" name="volunteer_hours_per_person" id="volunteerHoursPerPerson" value="<?= h($_POST['volunteer_hours_per_person']??'') ?>"></div>
            <div class="col-md-4"><label class="init-label"><?= $isArabic?'إجمالي ساعات التطوع':'Total Volunteer Hours' ?></label><input type="number" min="0" step="0.5" class="form-control init-input" id="totalVolunteerHours" value="<?= h((string)((int)($_POST['volunteers_count']??0)*(float)($_POST['volunteer_hours_per_person']??0))) ?>" readonly></div>
          </div>
        </div>


        <div class="col-12">
          <label class="init-label"><?= $isArabic
            ? 'المخرجات المباشرة التي تم تحقيقها'
            : 'Direct Outputs Achieved' ?></label>

           

  <?php if (!empty($outputSuggestions)): ?>
    <div class="init-suggestions">
      <?php foreach ($outputSuggestions as $out): ?>
        <button type="button"
                class="init-suggestion-btn"
                onclick="addOutputSuggestion('<?= h($out) ?>')">
          + <?= h($out) ?>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <textarea id="outputsBox"
    class="form-control init-input"
    name="المخرجات التي تم تحقيقها "
    rows="5"
    placeholder="<?= t('example_outputs') ?>"><?= h($_POST['المخرجات التي تم تحقيقها '] ?? '') ?></textarea>

  <div class="init-help">
    <?= $isArabic ? 'اضغط على أي مخرج شائع لإضافته مباشرة داخل الصندوق.' : 'Click any common output to add it directly.' ?>
  </div>
</div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'الأثر أو التغيير الناتج على المجتمع أو البيئة أو الصحة (بحد أقصى 200 كلمة)':'Resulting Impact or Change on Society, the Environment, or Health (Maximum 200 Words)' ?></label>
          <textarea class="form-control init-input" name="societal_impact" rows="5" placeholder="<?= $isArabic?'وضح التغيير الناتج مع أرقام أو مؤشرات إن توفرت.':'Describe the resulting change and include figures or indicators where available.' ?>"><?= h($_POST['societal_impact']??'') ?></textarea>
        </div>
      </div>
    </div>

    <!-- TAB 4 -->
    <div class="init-tab-pane" id="tab-ranking">
      <div class="init-section-title"><?= t('rankings_sdgs') ?></div>

      <div class="row g-4">
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'ارتباط النشاط بالتصنيفات العالمية':'Activity Relevance to Global Rankings' ?></label>
          <?php $selectedRankingFramework=$_POST['ranking_framework']??''; ?>
          <div class="init-check-grid">
            <?php $rankingOptions=[
              'the'=>['THE Impact Rankings','THE Impact Rankings'],
              'qs'=>['QS Sustainability','QS Sustainability'],
              'both'=>['كلاهما: THE وQS','Both: THE and QS'],
              'unsure'=>['غير متأكد — يراجعه فريق التصنيفات','Not Sure — Rankings Team to Review'],
              'none'=>['لا ينطبق','Not Applicable'],
            ]; foreach($rankingOptions as $value=>$labels): ?>
              <label class="init-radio-card"><input type="radio" name="ranking_framework" value="<?= h($value) ?>" <?= $selectedRankingFramework===$value?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="init-help"><?= $isArabic?'لا يلزم معرفة رقم المؤشر؛ يقوم فريق التصنيفات بالمراجعة النهائية.':'You do not need to know the exact indicator; the rankings team will complete the final mapping.' ?></div>
        </div>
        <?php $selectedTheAreas=$_POST['the_areas']??[]; if(!is_array($selectedTheAreas))$selectedTheAreas=[]; ?>
        <div class="col-12 <?= in_array($selectedRankingFramework,['the','both'],true)?'':'init-hidden' ?>" id="theAreasWrap">
          <label class="init-label"><?= $isArabic?'مجالات THE التي يدعمها النشاط (اختر كل ما ينطبق)':'THE Areas Supported by the Activity (Select All That Apply)' ?></label>
          <div class="init-check-grid">
            <?php foreach(['teaching'=>['التعليم','Teaching'],'research'=>['البحث','Research'],'outreach'=>['التواصل وخدمة المجتمع','Outreach'],'stewardship'=>['الإدارة والعمليات المؤسسية','Stewardship']] as $value=>$labels): ?>
              <label class="init-check-card"><input type="checkbox" name="the_areas[]" value="<?= h($value) ?>" <?= in_array($value,$selectedTheAreas,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php $selectedQsCategories=$_POST['qs_categories']??[]; if(!is_array($selectedQsCategories))$selectedQsCategories=[]; ?>
        <div class="col-12 <?= in_array($selectedRankingFramework,['qs','both'],true)?'':'init-hidden' ?>" id="qsCategoriesWrap">
          <label class="init-label"><?= $isArabic?'فئات QS Sustainability التي يدعمها النشاط (اختر كل ما ينطبق)':'QS Sustainability Categories Supported (Select All That Apply)' ?></label>
          <div class="init-check-grid">
            <?php foreach(['environmental'=>['الأثر البيئي','Environmental Impact'],'social'=>['الأثر الاجتماعي','Social Impact'],'governance'=>['الحوكمة','Governance']] as $value=>$labels): ?>
              <label class="init-check-card"><input type="checkbox" name="qs_categories[]" value="<?= h($value) ?>" <?= in_array($value,$selectedQsCategories,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php
          $postedDescriptors=$_POST['initiative_descriptors']??[]; if(!is_array($postedDescriptors))$postedDescriptors=[];
          $postedSecondaryTypesForEnvironment=$_POST['secondary_initiative_types']??[]; if(!is_array($postedSecondaryTypesForEnvironment))$postedSecondaryTypesForEnvironment=[];
          $showEnvironmentalModule=in_array('environmental',$selectedQsCategories,true)
            || in_array('campus_operations',$postedDescriptors,true)
            || ($_POST['نوع المبادرة']??'')==='sustainability_activities'
            || in_array('sustainability_activities',$postedSecondaryTypesForEnvironment,true);
          $selectedEnvironmentalTypes=$_POST['environmental_impact_types']??[]; if(!is_array($selectedEnvironmentalTypes))$selectedEnvironmentalTypes=[];
        ?>
        <div class="col-12 <?= $showEnvironmentalModule?'':'init-hidden' ?>" id="environmentalImpactWrap">
          <div class="init-section-title"><?= $isArabic?'قياس الأثر البيئي':'Environmental Impact Measurement' ?></div>
          <label class="init-label"><?= $isArabic?'نوع الأثر البيئي (اختر كل ما ينطبق)':'Environmental Impact Type (Select All That Apply)' ?></label>
          <div class="init-check-grid">
            <?php foreach([
              'energy'=>['الطاقة','Energy'],'water'=>['المياه','Water'],'waste'=>['النفايات','Waste'],
              'emissions'=>['الانبعاثات','Emissions'],'trees'=>['التشجير','Tree Planting'],
              'transport'=>['النقل المستدام','Sustainable Transport'],'biodiversity'=>['التنوع الحيوي','Biodiversity'],
              'procurement'=>['المشتريات أو الاستهلاك المسؤول','Sustainable Procurement or Consumption'],
            ] as $value=>$labels): ?>
              <label class="init-check-card"><input type="checkbox" name="environmental_impact_types[]" value="<?= h($value) ?>" <?= in_array($value,$selectedEnvironmentalTypes,true)?'checked':'' ?>><span><?= h($isArabic?$labels[0]:$labels[1]) ?></span></label>
            <?php endforeach; ?>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'القيمة قبل المبادرة':'Value Before the Initiative' ?></label><input type="number" step="any" class="form-control init-input" name="environmental_before_value" value="<?= h($_POST['environmental_before_value']??'') ?>"></div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'القيمة بعد المبادرة':'Value After the Initiative' ?></label><input type="number" step="any" class="form-control init-input" name="environmental_after_value" value="<?= h($_POST['environmental_after_value']??'') ?>"></div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'مقدار التحسن':'Improvement Value' ?></label><input type="number" step="any" class="form-control init-input" name="environmental_improvement_value" value="<?= h($_POST['environmental_improvement_value']??'') ?>"></div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'وحدة القياس':'Unit of Measurement' ?></label><input class="form-control init-input" name="environmental_unit" value="<?= h($_POST['environmental_unit']??'') ?>" placeholder="<?= $isArabic?'مثال: ك.و.س، لتر، كجم، طن CO₂':'Example: kWh, litres, kg, tCO₂e' ?>"></div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'أساس القياس':'Measurement Basis' ?></label>
              <?php $selectedEnvironmentalBasis=$_POST['environmental_measurement_basis']??''; ?>
              <div class="init-choice-grid">
                <label class="init-radio-card"><input type="radio" name="environmental_measurement_basis" value="actual" <?= $selectedEnvironmentalBasis==='actual'?'checked':'' ?>><span><?= $isArabic?'فعلي':'Actual' ?></span></label>
                <label class="init-radio-card"><input type="radio" name="environmental_measurement_basis" value="estimated" <?= $selectedEnvironmentalBasis==='estimated'?'checked':'' ?>><span><?= $isArabic?'تقديري':'Estimated' ?></span></label>
              </div>
            </div>
            <div class="col-md-6"><label class="init-label"><?= $isArabic?'مصدر البيانات أو القياس':'Data or Measurement Source' ?></label><input class="form-control init-input" name="environmental_data_source" value="<?= h($_POST['environmental_data_source']??'') ?>" placeholder="<?= $isArabic?'فاتورة، عداد، سجل نفايات، تقرير، تقدير الجهة':'Invoice, meter, waste record, report, or entity estimate' ?>"></div>
            <div class="col-12"><label class="init-label"><?= $isArabic?'اشرح الأثر البيئي المحقق أو المتوقع':'Describe the Achieved or Expected Environmental Impact' ?></label><textarea class="form-control init-input" name="environmental_impact" rows="4"><?= h($_POST['environmental_impact']??'') ?></textarea></div>
          </div>
        </div>

        <div class="col-12">
          <?php $supportsSdg = $_POST['supports_sdg'] ?? ''; ?>
          <label class="init-label"><?= t('sdg_question') ?></label>
          <div class="init-choice-grid">
            <label class="init-radio-card">
              <input type="radio" name="supports_sdg" value="نعم" <?= $supportsSdg === 'نعم' ? 'checked' : '' ?>>
              <?= t('yes') ?>
            </label>
            <label class="init-radio-card">
              <input type="radio" name="supports_sdg" value="لا" <?= $supportsSdg === 'لا' ? 'checked' : '' ?>>
              <?= t('no') ?>
            </label>
          </div>
        </div>
          
        <div class="col-12 <?= ($supportsSdg === 'نعم') ? '' : 'init-hidden' ?>" id="sdgGoalsWrap">
          <button type="button" id="aiSuggestBtn" class="btn btn-dark">
🤖 اقتراح ذكي للأهداف
</button>
          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'هدف التنمية المستدامة الرئيسي':'Primary Sustainable Development Goal' ?></label>
              <?php $selectedPrimarySdg=$_POST['primary_sdg']??''; ?>
              <select class="form-select init-input init-searchable" name="primary_sdg" id="primarySdgSelect">
                <option value=""><?= $isArabic?'اختر الهدف الرئيسي':'Select the primary goal' ?></option>
                <?php foreach ($sdgGoals as $goal): ?>
                  <option value="<?= h($goal['value']) ?>" <?= $selectedPrimarySdg===$goal['value']?'selected':'' ?>><?= h($isArabic?$goal['ar']:$goal['en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'أهداف التنمية المستدامة الثانوية (اختياري)':'Secondary Sustainable Development Goals (Optional)' ?></label>
              <?php $selectedSecondarySdgs=$_POST['secondary_sdgs']??[]; if(!is_array($selectedSecondarySdgs))$selectedSecondarySdgs=[]; ?>
              <select class="form-select init-input init-searchable" name="secondary_sdgs[]" id="secondarySdgsSelect" multiple>
                <?php foreach ($sdgGoals as $goal): ?>
                  <option value="<?= h($goal['value']) ?>" <?= in_array($goal['value'],$selectedSecondarySdgs,true)?'selected':'' ?>><?= h($isArabic?$goal['ar']:$goal['en']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="init-help"><?= $isArabic?'اختر هدفًا رئيسيًا واحدًا، ثم أضف الأهداف الثانوية عند الحاجة.':'Select one primary goal, then add secondary goals where relevant.' ?></div>
        </div>
      </div>
    </div>

    <!-- TAB 5 -->
    <div class="init-tab-pane" id="tab-docs">
      <div class="init-section-title"><?= t('documentation_notes') ?></div>


      <div class="row g-4">
        <?php
          $pub=$_POST['هل نُشرت على موقع الجامعة؟']??'';
          $publishYesValues=['uob','partner'];
          $mediaCoverageActiveValues=['uob','partner','in_progress'];
          $selectedMediaCoverageType=$_POST['media_coverage_type']??'';
        ?>
        <div class="col-12 init-doc-media-block">
          <label class="init-label"><?= $isArabic?'هل توجد تغطية إعلامية للمبادرة أو الفعالية أو النشاط المجتمعي؟':'Is There Media Coverage of the Initiative, Event, or Community Activity?' ?></label>
          <div class="init-check-grid">
            <?php $publishOptions=[
              'uob'=>['ar'=>'نعم، عبر منصات جامعة البحرين','en'=>'Yes through the University of Bahrain'],
              'partner'=>['ar'=>'نعم، عبر منصات جهة شريكة أو خارجية','en'=>'Yes through a partner or external entity platform'],
              'no'=>['ar'=>'لا','en'=>'No'],
              'in_progress'=>['ar'=>'قيد النشر','en'=>'In Progress'],
            ]; foreach($publishOptions as $value=>$label): ?>
              <label class="init-radio-card">
                <input type="radio" name="هل نُشرت على موقع الجامعة؟" value="<?= h($value) ?>" <?= $pub===$value?'checked':'' ?>>
                <span><?= h($isArabic?$label['ar']:$label['en']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12 init-doc-media-block <?= in_array($pub,$mediaCoverageActiveValues,true) ? '' : 'init-hidden' ?>" id="mediaCoverageTypeWrap">
          <label class="init-label"><?= $isArabic?'ما نوع التغطية الإعلامية؟':'What Is the Media Coverage Type?' ?></label>
          <div class="init-choice-grid">
            <label class="init-radio-card">
              <input type="radio" name="media_coverage_type" value="news" <?= $selectedMediaCoverageType==='news'?'checked':'' ?>>
              <span><?= $isArabic?'خبر صحفي أو إلكتروني':'Press or Online News Article' ?></span>
            </label>
            <label class="init-radio-card">
              <input type="radio" name="media_coverage_type" value="tv_interview" <?= $selectedMediaCoverageType==='tv_interview'?'checked':'' ?>>
              <span><?= $isArabic?'مقابلة تلفزيونية':'Television Interview' ?></span>
            </label>
          </div>
        </div>

        <div class="col-12 init-doc-media-block <?= $selectedMediaCoverageType==='news' && in_array($pub,$mediaCoverageActiveValues,true) ? '' : 'init-hidden' ?>" id="newsCoverageWrap">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'اسم المنصة أو الجهة الناشرة':'Publishing Platform or Outlet Name' ?></label>
              <input class="form-control init-input" name="media_outlet_name" value="<?= h($_POST['media_outlet_name']??'') ?>" placeholder="<?= $isArabic?'مثال: موقع جامعة البحرين أو صحيفة محلية':'Example: University of Bahrain website or a local newspaper' ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'عنوان الخبر':'News Headline' ?></label>
              <input class="form-control init-input" name="media_headline" value="<?= h($_POST['media_headline']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'تاريخ نشر الخبر':'News Publication Date' ?></label>
              <input type="date" class="form-control init-input" name="media_publication_date" value="<?= h($_POST['media_publication_date']??'') ?>">
            </div>
            <div class="col-md-6 <?= in_array($pub,$publishYesValues,true) ? '' : 'init-hidden' ?>" id="newsLinkWrap">
              <label class="init-label"><?= t('news_link') ?></label>
              <input type="url" class="form-control init-input" name="رابط خبر المبادرة" value="<?= h($_POST['رابط خبر المبادرة'] ?? '') ?>" placeholder="https://...">
            </div>
          </div>
        </div>

        <div class="col-12 init-doc-media-block <?= $selectedMediaCoverageType==='tv_interview' && in_array($pub,$mediaCoverageActiveValues,true) ? '' : 'init-hidden' ?>" id="tvInterviewWrap">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'اسم القناة التلفزيونية':'TV Channel Name' ?></label>
              <input class="form-control init-input" name="tv_channel" value="<?= h($_POST['tv_channel']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'اسم البرنامج':'Program Name' ?></label>
              <input class="form-control init-input" name="tv_program" value="<?= h($_POST['tv_program']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'عنوان أو موضوع المقابلة':'Interview Title or Topic' ?></label>
              <input class="form-control init-input" name="tv_interview_topic" value="<?= h($_POST['tv_interview_topic']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'اسم مقدم البرنامج أو المحاور (اختياري)':'Presenter or Interviewer Name (Optional)' ?></label>
              <input class="form-control init-input" name="tv_interviewer" value="<?= h($_POST['tv_interviewer']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'ممثلو جامعة البحرين وصفاتهم':'University of Bahrain Representatives and Their Titles' ?></label>
              <input class="form-control init-input" name="tv_uob_representatives" value="<?= h($_POST['tv_uob_representatives']??'') ?>">
            </div>
            <div class="col-md-3">
              <label class="init-label"><?= $isArabic?'تاريخ المقابلة':'Interview Date' ?></label>
              <input type="date" class="form-control init-input" name="tv_interview_date" value="<?= h($_POST['tv_interview_date']??'') ?>">
            </div>
            <div class="col-md-3">
              <label class="init-label"><?= $isArabic?'مدة المقابلة بالدقائق (اختياري)':'Duration in Minutes (Optional)' ?></label>
              <input type="number" min="1" step="1" class="form-control init-input" name="tv_duration_minutes" value="<?= h($_POST['tv_duration_minutes']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'حالة البث':'Broadcast Status' ?></label>
              <?php $selectedTvBroadcastStatus=$_POST['tv_broadcast_status']??''; ?>
              <select class="form-select init-input" name="tv_broadcast_status">
                <option value=""><?= $isArabic?'اختر الحالة':'Select Status' ?></option>
                <?php foreach([
                  'aired'=>['بُثّت','Aired'],
                  'recorded_pending'=>['مسجلة ولم تُبث بعد','Recorded but Not Yet Aired'],
                  'live'=>['بث مباشر','Live Broadcast'],
                ] as $value=>$labels): ?>
                  <option value="<?= h($value) ?>" <?= $selectedTvBroadcastStatus===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'نطاق البث':'Broadcast Scope' ?></label>
              <?php $selectedTvBroadcastScope=$_POST['tv_broadcast_scope']??''; ?>
              <select class="form-select init-input" name="tv_broadcast_scope">
                <option value=""><?= $isArabic?'اختر النطاق':'Select Scope' ?></option>
                <?php foreach([
                  'local'=>['محلي','Local'],
                  'regional'=>['إقليمي','Regional'],
                  'international'=>['دولي','International'],
                ] as $value=>$labels): ?>
                  <option value="<?= h($value) ?>" <?= $selectedTvBroadcastScope===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="init-label"><?= $isArabic?'لغة المقابلة':'Interview Language' ?></label>
              <?php $selectedTvInterviewLanguage=$_POST['tv_interview_language']??''; ?>
              <select class="form-select init-input" name="tv_interview_language">
                <option value=""><?= $isArabic?'اختر اللغة':'Select Language' ?></option>
                <?php foreach([
                  'ar'=>['العربية','Arabic'],
                  'en'=>['الإنجليزية','English'],
                  'both'=>['العربية والإنجليزية','Arabic and English'],
                  'other'=>['لغة أخرى','Other Language'],
                ] as $value=>$labels): ?>
                  <option value="<?= h($value) ?>" <?= $selectedTvInterviewLanguage===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 <?= in_array($pub,$publishYesValues,true) ? '' : 'init-hidden' ?>" id="tvInterviewLinkWrap">
              <label class="init-label"><?= $isArabic?'رابط مشاهدة المقابلة':'Interview Viewing Link' ?></label>
              <input type="url" class="form-control init-input" name="tv_interview_link" value="<?= h($_POST['tv_interview_link']??'') ?>" placeholder="https://...">
            </div>
            <div class="col-12">
              <label class="init-label"><?= $isArabic?'أبرز الموضوعات والرسائل التي تناولتها المقابلة':'Main Topics and Messages Covered in the Interview' ?></label>
              <textarea class="form-control init-input" name="tv_interview_highlights" rows="3"><?= h($_POST['tv_interview_highlights']??'') ?></textarea>
            </div>
          </div>
        </div>

        <?php $selectedEvidenceTypes=$_POST['evidence_type']??[]; if(!is_array($selectedEvidenceTypes))$selectedEvidenceTypes=[]; ?>
        <div class="col-12 init-doc-evidence-block"><label class="init-label"><?= $isArabic?'ما أقوى دليل متوفر على تنفيذ المبادرة؟':'What Is the Strongest Available Evidence of the Initiative’s Implementation?' ?></label><div class="init-help mb-2"><?= $isArabic?'يمكن اختيار تحميل ملف وتوفير رابط معًا. استخدم الشرح المختصر فقط إذا لم يتوفر ملف أو رابط.':'You may select both file upload and public URL. Use a short written explanation only when neither is available.' ?></div><div class="init-check-grid"><?php foreach($evidenceTypes as $k=>$v): ?><label class="init-check-card"><input type="checkbox" name="evidence_type[]" value="<?= h($k) ?>" <?= in_array($k,$selectedEvidenceTypes,true)?'checked':'' ?>><span><?= h($isArabic ? $v['ar'] : $v['en']) ?></span></label><?php endforeach; ?></div></div>
        <div class="col-12 init-doc-evidence-block <?= $selectedEvidenceTypes?'':'init-hidden' ?>" id="evidenceMetadataWrap">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="init-label"><?= $isArabic?'نوع الدليل':'Evidence Document Type' ?></label>
              <?php $selectedEvidenceDocumentType=$_POST['evidence_document_type']??''; ?>
              <select class="form-select init-input" name="evidence_document_type">
                <option value=""><?= $isArabic?'اختر النوع':'Select type' ?></option>
                <?php foreach(['news'=>['خبر أو صفحة رسمية','News or Official Webpage'],'report'=>['تقرير','Report'],'policy'=>['سياسة أو إجراء','Policy or Procedure'],'data'=>['بيانات أو إحصاءات','Data or Statistics'],'attendance'=>['قائمة حضور أو تسجيل','Attendance or Registration List'],'media'=>['صور أو فيديو','Photos or Video'],'agreement'=>['اتفاقية أو خطاب رسمي','Agreement or Official Letter'],'other'=>['أخرى','Other']] as $value=>$labels): ?>
                  <option value="<?= h($value) ?>" <?= $selectedEvidenceDocumentType===$value?'selected':'' ?>><?= h($isArabic?$labels[0]:$labels[1]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'تاريخ الدليل':'Evidence Date' ?></label><input type="date" class="form-control init-input" name="evidence_date" value="<?= h($_POST['evidence_date']??'') ?>"></div>
            <div class="col-md-3"><label class="init-label"><?= $isArabic?'الجهة المالكة للدليل':'Evidence Owner' ?></label><input class="form-control init-input" name="evidence_owner" value="<?= h($_POST['evidence_owner']??'') ?>" placeholder="<?= $isArabic?'الكلية أو الإدارة أو الجهة الشريكة':'College, department, or partner entity' ?>"></div>
            <div class="col-md-3">
              <label class="init-label"><?= $isArabic?'هل الدليل متاح للعامة دون تسجيل دخول؟':'Is the Evidence Publicly Accessible Without Login?' ?></label>
              <?php $selectedEvidencePublicAccess=$_POST['evidence_public_access']??''; ?>
              <div class="init-choice-grid">
                <label class="init-radio-card"><input type="radio" name="evidence_public_access" value="yes" <?= $selectedEvidencePublicAccess==='yes'?'checked':'' ?>><span><?= t('yes') ?></span></label>
                <label class="init-radio-card"><input type="radio" name="evidence_public_access" value="no" <?= $selectedEvidencePublicAccess==='no'?'checked':'' ?>><span><?= t('no') ?></span></label>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 init-doc-evidence-block <?= in_array('upload',$selectedEvidenceTypes,true)?'':'init-hidden' ?>" id="evidenceUploadWrap">
          <div class="init-evidence-links-head">
            <label class="init-label mb-0"><?= $isArabic?'تحميل الملفات الداعمة (بحد أقصى 10 ملفات)':'Upload Supporting Documents (Maximum 10 Files)' ?></label>
            <div class="init-evidence-links-actions">
              <span class="init-evidence-count" id="evidenceFilesCount">0 / 10 <?= $isArabic?'ملفات':'Files' ?></span>
              <button type="button" class="init-evidence-links-clear init-hidden" id="clearEvidenceFilesBtn"><?= $isArabic?'حذف كل الملفات':'Clear All Files' ?></button>
            </div>
          </div>
          <div class="init-help mb-2"><?= $isArabic?'يمكن تحميل الصور، الفيديو، التقرير، الخطاب الرسمي، الدعوة، قائمة الحضور، الشهادات، أو أي مستندات ذات صلة.':'You may upload photos, videos, reports, official letters, invitations, attendance lists, certificates, or other relevant documents.' ?></div>
          <input type="file" class="form-control init-input init-evidence-file-picker" name="supporting_files[]" id="supportingEvidenceFiles" multiple>
          <input type="hidden" name="evidence_files_json" id="evidenceFilesJson" value="<?= h($_POST['evidence_files_json']??'[]') ?>">
          <div class="init-evidence-upload-status" id="evidenceUploadStatus"></div>
          <div class="init-evidence-files-list" id="evidenceFilesList"></div>
        </div>
        <?php $postedEvidenceUrls=$_POST['evidence_url']??['']; if(!is_array($postedEvidenceUrls))$postedEvidenceUrls=[$postedEvidenceUrls]; if(!$postedEvidenceUrls)$postedEvidenceUrls=['']; ?>
        <div class="col-12 init-doc-evidence-block <?= in_array('url',$selectedEvidenceTypes,true)?'':'init-hidden' ?>" id="evidenceUrlWrap">
          <div class="init-evidence-links-head">
            <label class="init-label mb-0"><?= $isArabic?'روابط الصور أو الأدلة':'Images or Evidence Links' ?></label>
            <div class="init-evidence-links-actions">
              <span class="init-evidence-count" id="evidenceLinksCount">0 / 10 <?= $isArabic?'روابط':'Links' ?></span>
              <button type="button" class="init-evidence-link-add" id="addEvidenceLinkBtn">+ <?= $isArabic?'إضافة رابط':'Add Link' ?></button>
              <button type="button" class="init-evidence-links-clear init-hidden" id="clearEvidenceLinksBtn"><?= $isArabic?'حذف الكل':'Clear All' ?></button>
            </div>
          </div>
          <div class="init-evidence-links-list" id="evidenceLinksList" data-placeholder="<?= h(t('google_drive_link')) ?>">
            <?php foreach($postedEvidenceUrls as $postedEvidenceUrl): ?>
              <div class="init-evidence-link-row">
                <input type="url" class="form-control init-input init-evidence-url" name="evidence_url[]" value="<?= h($postedEvidenceUrl) ?>" placeholder="<?= t('google_drive_link') ?>">
                <button type="button" class="init-evidence-link-remove" aria-label="<?= $isArabic?'حذف الرابط':'Remove link' ?>">×</button>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="evidence_links_json" id="evidenceLinksJson" value="<?= h($_POST['evidence_links_json']??'[]') ?>">
        </div>
        <div class="col-12 init-doc-evidence-block <?= in_array('explanation',$selectedEvidenceTypes,true)?'':'init-hidden' ?>" id="evidenceExplanationWrap"><label class="init-label"><?= $isArabic?'شرح كتابي مختصر للدليل':'Short Written Explanation' ?></label><textarea class="form-control init-input" name="evidence_explanation"><?= h($_POST['evidence_explanation']??'') ?></textarea></div>
        <div class="col-12 init-doc-evidence-block <?= $selectedEvidenceTypes?'':'init-hidden' ?>" id="publicSharingWrap"><label class="init-label"><?= $isArabic?'هل تسمح الجهة لجامعة البحرين باستخدام هذا الدليل في الموقع الإلكتروني والتقارير وملفات التصنيفات؟':'Does the Entity Allow the University of Bahrain to Use This Evidence on Its Website, in Reports, and in Ranking Submissions?' ?></label><div class="init-choice-grid"><?php $selectedPublicSharing=$_POST['public_sharing']??''; foreach($publicSharingOptions as $x): ?><label class="init-radio-card"><input type="radio" name="public_sharing" value="<?= h($x['value']) ?>" <?= $selectedPublicSharing===$x['value']?'checked':'' ?>><span><?= h($isArabic?$x['ar']:$x['en']) ?></span></label><?php endforeach; ?></div></div>


        <div class="col-md-6 init-doc-notes-block">
          <label class="init-label"><?= t('entity_notes') ?></label>
          <textarea class="form-control init-input" name="m_notes_entity" rows="5" placeholder="<?= t('additional_notes') ?>"><?= h($_POST['m_notes_entity'] ?? '') ?></textarea>
        </div>

        
      </div>
    </div>

    <div class="init-actions">
      <div class="init-actions-left">
        <button type="button" class="btn btn-outline-primary init-btn init-btn-nav" id="prevTabBtn">
          <?= t('previous') ?>
        </button>

        <button type="button" class="btn btn-outline-primary init-btn init-btn-nav" id="nextTabBtn">
          <?= t('next') ?>
        </button>
      </div>

      <div class="init-actions-right">
        <a class="btn btn-outline-secondary init-btn" href="../initiatives.php">
          <?= t('cancel') ?>
        </a>

        <button class="btn btn-primary init-btn" type="submit">
          <?= t('save') ?>
        </button>
      </div>
    </div>
  </form>
</div>

<script>
window.__AGREEMENTS__ = <?= json_encode($agreements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

(function(){
  const contributorsContainer = document.getElementById('contributorsContainer');
  const contributorsEmpty = document.getElementById('contributorsEmpty');
  const addContributorBtn = document.getElementById('addContributorBtn');
  const clearAllContributorsBtn = document.getElementById('clearAllContributorsBtn');
  const initiativeContributorsJson = document.getElementById('initiativeContributorsJson');
  let contributorInitialData = <?= json_encode(array_values($initiativeContributors ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  if (!contributorInitialData.length) {
    try {
      const savedState = JSON.parse(sessionStorage.getItem('uob_initiative_form_state:' + window.location.pathname) || 'null');
      const savedJson = savedState?.initiative_contributors_json?.value || initiativeContributorsJson?.value || '';
      if (savedJson) contributorInitialData = JSON.parse(savedJson) || [];
    } catch (_) {}
  }
  const contributorLabels = {
    participant: <?= json_encode($isArabic ? 'الشخص المسؤول' : 'Responsible Person', JSON_UNESCAPED_UNICODE) ?>,
    name: <?= json_encode($isArabic ? 'اسم الشخص المسؤول' : 'Responsible Person Name', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($isArabic ? 'البريد الإلكتروني' : 'Email Address', JSON_UNESCAPED_UNICODE) ?>,
    mobile: <?= json_encode($isArabic ? 'رقم الهاتف (اختياري)' : 'Mobile Number (Optional)', JSON_UNESCAPED_UNICODE) ?>,
    role: <?= json_encode($isArabic ? 'الدور في المبادرة' : 'Role in the Initiative', JSON_UNESCAPED_UNICODE) ?>,
    primary: <?= json_encode($isArabic ? 'المسؤول الرئيسي للتواصل' : 'Primary Contact', JSON_UNESCAPED_UNICODE) ?>,
    coordinator: <?= json_encode($isArabic ? 'منسق المبادرة' : 'Initiative Coordinator', JSON_UNESCAPED_UNICODE) ?>,
    remove: <?= json_encode($isArabic ? 'حذف' : 'Remove', JSON_UNESCAPED_UNICODE) ?>,
    select: <?= json_encode($isArabic ? 'اختر' : 'Select', JSON_UNESCAPED_UNICODE) ?>,
    specify: <?= json_encode($isArabic ? 'يرجى التحديد' : 'Please specify', JSON_UNESCAPED_UNICODE) ?>
  };
  const contributorRoleOptions = <?= json_encode($isArabic ? [
    ['value'=>'lead','label'=>'قائد المبادرة'],['value'=>'organizer','label'=>'منظم'],['value'=>'trainer_speaker','label'=>'مدرب / متحدث'],['value'=>'researcher','label'=>'باحث'],['value'=>'volunteer','label'=>'متطوع'],['value'=>'partner','label'=>'شريك'],['value'=>'other','label'=>'أخرى']
  ] : [
    ['value'=>'lead','label'=>'Initiative Lead'],['value'=>'organizer','label'=>'Organizer'],['value'=>'trainer_speaker','label'=>'Trainer / Speaker'],['value'=>'researcher','label'=>'Researcher'],['value'=>'volunteer','label'=>'Volunteer'],['value'=>'partner','label'=>'Partner'],['value'=>'other','label'=>'Other']
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function esc(value){ return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  function optionsHtml(options, selected){ return `<option value="">${esc(contributorLabels.select)}</option>` + options.map(o => `<option value="${esc(o.value)}" ${o.value===selected?'selected':''}>${esc(o.label)}</option>`).join(''); }
  function syncContributorsJson(){
    if(!initiativeContributorsJson || !contributorsContainer) return;
    const rows = Array.from(contributorsContainer.querySelectorAll('.contributor-card')).map(card => ({
      name: card.querySelector('[name="contributor_name[]"]')?.value || '',
      email: card.querySelector('[name="contributor_email[]"]')?.value || '',
      mobile: card.querySelector('[name="contributor_mobile[]"]')?.value || '',
      role: card.querySelector('[name="contributor_role[]"]')?.value || '',
      role_other: card.querySelector('[name="contributor_role_other[]"]')?.value || '',
      is_primary: !!card.querySelector('[name="primary_responsible"]')?.checked,
      is_coordinator: !!card.querySelector('[name="initiative_coordinator"]')?.checked
    }));
    initiativeContributorsJson.value = JSON.stringify(rows);
  }
  function updateContributorNumbers(){
    const cards = Array.from(contributorsContainer?.querySelectorAll('.contributor-card') || []);
    cards.forEach((card,index)=>{
      const title=card.querySelector('.contributor-card-title');
      const primary=card.querySelector('[name="primary_responsible"]');
      const coordinator=card.querySelector('[name="initiative_coordinator"]');
      if(title) title.textContent=`${contributorLabels.participant} ${index+1}`;
      if(primary) primary.value=String(index);
      if(coordinator) coordinator.value=String(index);
    });
    if(cards.length && !cards.some(card => card.querySelector('[name="primary_responsible"]')?.checked)){
      const firstPrimary=cards[0].querySelector('[name="primary_responsible"]');
      if(firstPrimary) firstPrimary.checked=true;
    }
    if(cards.length && !cards.some(card => card.querySelector('[name="initiative_coordinator"]')?.checked)){
      const firstCoordinator=cards[0].querySelector('[name="initiative_coordinator"]');
      if(firstCoordinator) firstCoordinator.checked=true;
    }
    const hasContributors = !!contributorsContainer?.children.length;
    if(contributorsEmpty) contributorsEmpty.style.display = hasContributors ? 'none' : 'block';
    clearAllContributorsBtn?.classList.toggle('is-visible', hasContributors);
    syncContributorsJson();
    window.setTimeout(() => updateInitiativeProgress(), 0);
  }
  function toggleContributorOther(card, kind){
    const select=card.querySelector(`[name="contributor_${kind}[]"]`);
    const wrap=card.querySelector(`[data-other-wrap="${kind}"]`);
    if(wrap) wrap.classList.toggle('init-hidden', select?.value !== 'other');
  }
  function addContributor(data={}){
    if(!contributorsContainer) return;
    const card=document.createElement('div'); card.className='contributor-card';
    card.innerHTML=`
      <div class="contributor-card-head"><h4 class="contributor-card-title"></h4><button type="button" class="contributor-remove-btn">${esc(contributorLabels.remove)}</button></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.name)}</label><input class="form-control init-input" name="contributor_name[]" value="${esc(data.name)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.email)}</label><input type="email" class="form-control init-input" name="contributor_email[]" value="${esc(data.email)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.mobile)}</label><input class="form-control init-input" name="contributor_mobile[]" value="${esc(data.mobile)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.role)}</label><div class="init-native-inline-shell"><select class="form-select init-input" name="contributor_role[]">${optionsHtml(contributorRoleOptions,data.role)}</select><span class="init-inline-other-wrap ${data.role==='other'?'':'init-hidden'}" data-other-wrap="role"><input class="form-control init-input init-inline-other-input" name="contributor_role_other[]" value="${esc(data.role_other)}" placeholder="${esc(contributorLabels.specify)}"></span></div></div>
        <div class="col-md-6"><label class="init-radio-card"><input type="radio" name="primary_responsible" ${data.is_primary?'checked':''}><span>${esc(contributorLabels.primary)}</span></label></div>
        <div class="col-md-6"><label class="init-radio-card"><input type="radio" name="initiative_coordinator" ${data.is_coordinator?'checked':''}><span>${esc(contributorLabels.coordinator)}</span></label></div>
      </div>`;
    card.querySelector('.contributor-remove-btn').addEventListener('click',()=>{card.remove();updateContributorNumbers();});
    card.addEventListener('input', syncContributorsJson);
    card.addEventListener('change', syncContributorsJson);
    ['role'].forEach(kind=>card.querySelector(`[name="contributor_${kind}[]"]`)?.addEventListener('change',()=>toggleContributorOther(card,kind)));
    contributorsContainer.appendChild(card); updateContributorNumbers();
  }
  addContributorBtn?.addEventListener('click',()=>addContributor());
  clearAllContributorsBtn?.addEventListener('click',()=>{
    if(!contributorsContainer?.children.length) return;
    const confirmed = window.confirm(<?= json_encode($isArabic ? 'هل أنت متأكد من حذف جميع الأشخاص المسؤولين؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to remove all responsible people? This action cannot be undone.', JSON_UNESCAPED_UNICODE) ?>);
    if(!confirmed) return;
    contributorsContainer.replaceChildren();
    updateContributorNumbers();
  });
  contributorInitialData.forEach(addContributor);
  if(!contributorInitialData.length) addContributor({is_primary:true,is_coordinator:true});
  updateContributorNumbers();

  // People who participated in implementation (separate from responsible people).
  const participantsContainer = document.getElementById('participantsContainer');
  const participantsEmpty = document.getElementById('participantsEmpty');
  const addParticipantBtn = document.getElementById('addParticipantBtn');
  const clearAllParticipantsBtn = document.getElementById('clearAllParticipantsBtn');
  const initiativeParticipantsJson = document.getElementById('initiativeParticipantsJson');
  let participantInitialData = <?= json_encode(array_values($initiativeParticipants ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  if(!participantInitialData.length){
    try{
      const savedState = JSON.parse(sessionStorage.getItem('uob_initiative_form_state:' + window.location.pathname) || 'null');
      const savedJson = savedState?.initiative_participants_json?.value || initiativeParticipantsJson?.value || '';
      if(savedJson) participantInitialData = JSON.parse(savedJson) || [];
    }catch(_){}
  }
  const participantLabels = {
    participant: <?= json_encode($isArabic ? 'المشارك' : 'Participant', JSON_UNESCAPED_UNICODE) ?>,
    name: <?= json_encode($isArabic ? 'اسم المشارك' : 'Participant Name', JSON_UNESCAPED_UNICODE) ?>,
    type: <?= json_encode($isArabic ? 'نوع المشارك' : 'Participant Type', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($isArabic ? 'البريد الإلكتروني' : 'Email Address', JSON_UNESCAPED_UNICODE) ?>,
    mobile: <?= json_encode($isArabic ? 'رقم الهاتف (اختياري)' : 'Mobile Number (Optional)', JSON_UNESCAPED_UNICODE) ?>,
    department: <?= json_encode($isArabic ? 'الكلية / العمادة / الجهة' : 'College / Deanship / Entity', JSON_UNESCAPED_UNICODE) ?>,
    subdepartment: <?= json_encode($isArabic ? 'القسم التابع للكلية أو الجهة (إن وجد)' : 'Department within the College or Entity (if applicable)', JSON_UNESCAPED_UNICODE) ?>,
    role: <?= json_encode($isArabic ? 'الدور في المبادرة' : 'Role in the Initiative', JSON_UNESCAPED_UNICODE) ?>,
    remove: <?= json_encode($isArabic ? 'حذف' : 'Remove', JSON_UNESCAPED_UNICODE) ?>,
    select: <?= json_encode($isArabic ? 'اختر' : 'Select', JSON_UNESCAPED_UNICODE) ?>,
    specify: <?= json_encode($isArabic ? 'يرجى التحديد' : 'Please specify', JSON_UNESCAPED_UNICODE) ?>
  };
  const participantTypeOptions = <?= json_encode($isArabic ? [
    ['value'=>'faculty','label'=>'عضو هيئة تدريس'],['value'=>'staff','label'=>'موظف'],['value'=>'student','label'=>'طالب'],['value'=>'student_group','label'=>'مجموعة طلابية'],['value'=>'external','label'=>'ممثل جهة خارجية'],['value'=>'other','label'=>'أخرى']
  ] : [
    ['value'=>'faculty','label'=>'Faculty Member'],['value'=>'staff','label'=>'Staff Member'],['value'=>'student','label'=>'Student'],['value'=>'student_group','label'=>'Student Group'],['value'=>'external','label'=>'External Entity Representative'],['value'=>'other','label'=>'Other']
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const participantRoleOptions = <?= json_encode($isArabic ? [
    ['value'=>'lead','label'=>'قائد المبادرة'],['value'=>'coordinator','label'=>'منسق'],['value'=>'organizer','label'=>'منظم'],['value'=>'trainer_speaker','label'=>'مدرب / متحدث'],['value'=>'researcher','label'=>'باحث'],['value'=>'volunteer','label'=>'متطوع'],['value'=>'partner','label'=>'شريك'],['value'=>'other','label'=>'أخرى']
  ] : [
    ['value'=>'lead','label'=>'Initiative Lead'],['value'=>'coordinator','label'=>'Coordinator'],['value'=>'organizer','label'=>'Organizer'],['value'=>'trainer_speaker','label'=>'Trainer / Speaker'],['value'=>'researcher','label'=>'Researcher'],['value'=>'volunteer','label'=>'Volunteer'],['value'=>'partner','label'=>'Partner'],['value'=>'other','label'=>'Other']
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const participantDepartmentOptions = <?= json_encode(array_map(fn($d)=>['value'=>$d['value'],'label'=>$isArabic?$d['ar']:$d['en']], $departmentOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function participantOptionsHtml(options, selected){
    return `<option value="">${esc(participantLabels.select)}</option>` + options.map(option => `<option value="${esc(option.value)}" ${option.value===selected?'selected':''}>${esc(option.label)}</option>`).join('');
  }
  function syncParticipantsJson(){
    if(!initiativeParticipantsJson || !participantsContainer) return;
    const rows = Array.from(participantsContainer.querySelectorAll('.contributor-card')).map(card => ({
      name: card.querySelector('[name="participant_name[]"]')?.value || '',
      type: card.querySelector('[name="participant_type[]"]')?.value || '',
      email: card.querySelector('[name="participant_email[]"]')?.value || '',
      mobile: card.querySelector('[name="participant_mobile[]"]')?.value || '',
      department: card.querySelector('[name="participant_department[]"]')?.value || '',
      subdepartment: card.querySelector('[name="participant_subdepartment[]"]')?.value || '',
      role: card.querySelector('[name="participant_role[]"]')?.value || '',
      type_other: card.querySelector('[name="participant_type_other[]"]')?.value || '',
      department_other: card.querySelector('[name="participant_department_other[]"]')?.value || '',
      role_other: card.querySelector('[name="participant_role_other[]"]')?.value || ''
    }));
    initiativeParticipantsJson.value = JSON.stringify(rows);
  }
  function updateParticipantNumbers(){
    const cards = Array.from(participantsContainer?.querySelectorAll('.contributor-card') || []);
    cards.forEach((card,index)=>{
      const title = card.querySelector('.contributor-card-title');
      if(title) title.textContent = `${participantLabels.participant} ${index+1}`;
    });
    const hasParticipants = cards.length > 0;
    if(participantsEmpty) participantsEmpty.style.display = hasParticipants ? 'none' : 'block';
    clearAllParticipantsBtn?.classList.toggle('is-visible', hasParticipants);
    syncParticipantsJson();
    window.setTimeout(() => updateInitiativeProgress(), 0);
  }
  function toggleParticipantOther(card, kind){
    const select = card.querySelector(`[name="participant_${kind}[]"]`);
    const wrap = card.querySelector(`[data-participant-other-wrap="${kind}"]`);
    if(wrap) wrap.classList.toggle('init-hidden', select?.value !== 'other');
  }
  function addParticipant(data={}){
    if(!participantsContainer) return;
    const card = document.createElement('div');
    card.className = 'contributor-card';
    card.innerHTML = `
      <div class="contributor-card-head"><h4 class="contributor-card-title"></h4><button type="button" class="contributor-remove-btn">${esc(participantLabels.remove)}</button></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.name)}</label><input class="form-control init-input" name="participant_name[]" value="${esc(data.name)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.type)}</label><select class="form-select init-input" name="participant_type[]">${participantOptionsHtml(participantTypeOptions,data.type)}</select><div class="contributor-other-wrap ${data.type==='other'?'':'init-hidden'}" data-participant-other-wrap="type"><input class="form-control init-input" name="participant_type_other[]" value="${esc(data.type_other)}" placeholder="${esc(participantLabels.specify)}"></div></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.email)}</label><input type="email" class="form-control init-input" name="participant_email[]" value="${esc(data.email)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.mobile)}</label><input class="form-control init-input" name="participant_mobile[]" value="${esc(data.mobile)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.department)}</label><select class="form-select init-input" name="participant_department[]">${participantOptionsHtml(participantDepartmentOptions,data.department)}</select><div class="contributor-other-wrap ${data.department==='other'?'':'init-hidden'}" data-participant-other-wrap="department"><input class="form-control init-input" name="participant_department_other[]" value="${esc(data.department_other)}" placeholder="${esc(participantLabels.specify)}"></div></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.subdepartment)}</label><input class="form-control init-input" name="participant_subdepartment[]" value="${esc(data.subdepartment)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(participantLabels.role)}</label><select class="form-select init-input" name="participant_role[]">${participantOptionsHtml(participantRoleOptions,data.role)}</select><div class="contributor-other-wrap ${data.role==='other'?'':'init-hidden'}" data-participant-other-wrap="role"><input class="form-control init-input" name="participant_role_other[]" value="${esc(data.role_other)}" placeholder="${esc(participantLabels.specify)}"></div></div>
      </div>`;
    card.querySelector('.contributor-remove-btn')?.addEventListener('click',()=>{card.remove();updateParticipantNumbers();});
    card.addEventListener('input', syncParticipantsJson);
    card.addEventListener('change', syncParticipantsJson);
    ['type','department','role'].forEach(kind=>card.querySelector(`[name="participant_${kind}[]"]`)?.addEventListener('change',()=>toggleParticipantOther(card,kind)));
    participantsContainer.appendChild(card);
    updateParticipantNumbers();
  }
  addParticipantBtn?.addEventListener('click',()=>addParticipant());
  clearAllParticipantsBtn?.addEventListener('click',()=>{
    if(!participantsContainer?.children.length) return;
    const confirmed = window.confirm(<?= json_encode($isArabic ? 'هل أنت متأكد من حذف جميع المشاركين؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to remove all participants? This action cannot be undone.', JSON_UNESCAPED_UNICODE) ?>);
    if(!confirmed) return;
    participantsContainer.replaceChildren();
    updateParticipantNumbers();
  });
  participantInitialData.forEach(addParticipant);
  updateParticipantNumbers();

  // Preserve all entered values when the site language is switched.
  // File inputs cannot be restored by browsers for security reasons.
  const initiativeForm = document.getElementById('initiativeForm');
  const formStateKey = 'uob_initiative_form_state:' + window.location.pathname;
  const isPostRequest = <?= json_encode($_SERVER['REQUEST_METHOD'] === 'POST') ?>;
  const submissionSucceeded = <?= json_encode((bool)$success) ?>;

  function captureFormState(){
    if(!initiativeForm) return;
    const state = {};
    initiativeForm.querySelectorAll('[name]').forEach(field => {
      if(field.disabled || field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
      const name = field.name;
      if(!name) return;

      if(field.type === 'radio'){
        if(field.checked) state[name] = {kind:'radio', value:field.value};
        else if(!(name in state)) state[name] = {kind:'radio', value:null};
        return;
      }
      if(field.type === 'checkbox'){
        if(!state[name]) state[name] = {kind:'checkbox', values:[]};
        if(field.checked) state[name].values.push(field.value);
        return;
      }
      if(field.tagName === 'SELECT' && field.multiple){
        state[name] = {kind:'multi', values:Array.from(field.selectedOptions).map(option => option.value)};
        return;
      }
      state[name] = {kind:'value', value:field.value};
    });
    sessionStorage.setItem(formStateKey, JSON.stringify(state));
  }

  function restoreFormState(){
    if(!initiativeForm || isPostRequest || submissionSucceeded) return;
    let state;
    try { state = JSON.parse(sessionStorage.getItem(formStateKey) || 'null'); }
    catch (_) { state = null; }
    if(!state) return;

    initiativeForm.querySelectorAll('[name]').forEach(field => {
      const saved = state[field.name];
      if(!saved || field.type === 'file') return;
      if(field.type === 'radio') field.checked = saved.value === field.value;
      else if(field.type === 'checkbox') field.checked = Array.isArray(saved.values) && saved.values.includes(field.value);
      else if(field.tagName === 'SELECT' && field.multiple){
        const values = Array.isArray(saved.values) ? saved.values : [];
        Array.from(field.options).forEach(option => option.selected = values.includes(option.value));
      } else if(saved.value !== undefined && saved.value !== null) field.value = saved.value;
    });
  }

  if(submissionSucceeded){
    sessionStorage.removeItem(formStateKey);
  } else {
    restoreFormState();
    initiativeForm?.addEventListener('input', captureFormState);
    initiativeForm?.addEventListener('change', captureFormState);
    window.addEventListener('pagehide', captureFormState);
    window.addEventListener('beforeunload', captureFormState);
  }

  // tabs
  const tabButtons = Array.from(document.querySelectorAll('.init-tab-btn'));
  const tabPanes   = Array.from(document.querySelectorAll('.init-tab-pane'));
  const prevBtn    = document.getElementById('prevTabBtn');
  const nextBtn    = document.getElementById('nextTabBtn');

  let current = Math.max(0, tabButtons.findIndex(btn => btn.classList.contains('active')));
  if (current < 0) current = 0;

  function showTab(index){
    if(index < 0) index = 0;
    if(index >= tabButtons.length) index = tabButtons.length - 1;
    current = index;

    tabButtons.forEach((btn, i) => btn.classList.toggle('active', i === current));
    tabPanes.forEach((pane, i) => pane.classList.toggle('active', i === current));

    if(prevBtn) prevBtn.disabled = current === 0;
    if(nextBtn) nextBtn.disabled = current === tabButtons.length - 1;
    window.scrollTo({ top: 220, behavior: 'smooth' });
  }

  tabButtons.forEach((btn, i) => {
    btn.addEventListener('click', () => showTab(i));
  });

  prevBtn && prevBtn.addEventListener('click', () => showTab(current - 1));
  nextBtn && nextBtn.addEventListener('click', () => showTab(current + 1));

  const progressFill = document.getElementById('initiativeProgressFill');
  const progressPercent = document.getElementById('initiativeProgressPercent');
  const progressTrack = document.getElementById('initiativeProgressTrack');

  function updateInitiativeProgress(){
    if(!initiativeForm) return;

    const fields = Array.from(initiativeForm.querySelectorAll('input[name], select[name], textarea[name]'))
      .filter(field =>
        !field.disabled &&
        field.type !== 'hidden' &&
        field.type !== 'submit' &&
        field.type !== 'button' &&
        !field.closest('.init-hidden')
      );
    const units = new Map();

    fields.forEach(field => {
      const isChoice = field.type === 'radio' || field.type === 'checkbox';
      const key = isChoice ? `group:${field.name}` : `field:${field.name}`;
      if(!units.has(key)) units.set(key, []);
      units.get(key).push(field);
    });

    let completed = 0;
    units.forEach(group => {
      const first = group[0];
      let complete = false;
      if(first.type === 'radio' || first.type === 'checkbox'){
        complete = group.some(field => field.checked);
      } else if(first.type === 'file'){
        complete = Boolean(first.files && first.files.length);
      } else if(first.tagName === 'SELECT' && first.multiple){
        complete = first.selectedOptions.length > 0;
      } else {
        complete = String(first.value || '').trim() !== '';
      }
      if(complete) completed++;
    });

    const percentage = units.size ? Math.round((completed / units.size) * 100) : 0;
    if(progressFill) progressFill.style.width = `${percentage}%`;
    if(progressPercent) progressPercent.textContent = `${percentage}%`;
    progressTrack?.setAttribute('aria-valuenow', String(percentage));
  }

  initiativeForm?.addEventListener('input', updateInitiativeProgress);
  initiativeForm?.addEventListener('change', () => window.setTimeout(updateInitiativeProgress, 0));

  showTab(current);
  updateInitiativeProgress();

  // dynamic fields
  const relatedRadios = document.querySelectorAll('input[name="related_agreement"]');
  const agreementWrap = document.getElementById('agreementSelectWrap');
  const agreementSelect = document.getElementById('agreementSelect');
  const agreementInfoBox = document.getElementById('agreementInfoBox');

  const locationRadios = document.querySelectorAll('input[name="location_mode"]');
  const outsideLocationWrap = document.getElementById('outsideLocationWrap');
  const targetGroupsSelect = document.getElementById('targetGroupsSelect');
  const initiativeTypeSelect = document.getElementById('initiativeTypeSelect');
  const initiativeTypeOtherWrap = document.getElementById('initiativeTypeOtherWrap');
  const secondaryInitiativeTypesSelect = document.getElementById('secondaryInitiativeTypesSelect');
  const initiativeDescriptorsSelect = document.getElementById('initiativeDescriptorsSelect');
  const departmentUnitSelect = document.getElementById('departmentUnitSelect');
  const departmentUnitOtherWrap = document.getElementById('departmentUnitOtherWrap');
  const maleBeneficiariesCount = document.getElementById('maleBeneficiariesCount');
  const femaleBeneficiariesCount = document.getElementById('femaleBeneficiariesCount');
  const unspecifiedBeneficiariesCount = document.getElementById('unspecifiedBeneficiariesCount');
  const totalAttendeesCount = document.getElementById('totalAttendeesCount');

  const sdgRadios = document.querySelectorAll('input[name="supports_sdg"]');
  const sdgGoalsWrap = document.getElementById('sdgGoalsWrap');
  const rankingFrameworkRadios = document.querySelectorAll('input[name="ranking_framework"]');
  const theAreasWrap = document.getElementById('theAreasWrap');
  const qsCategoriesWrap = document.getElementById('qsCategoriesWrap');
  const qsCategoryChecks = document.querySelectorAll('input[name="qs_categories[]"]');
  const environmentalImpactWrap = document.getElementById('environmentalImpactWrap');
  const internationalParticipationRadios = document.querySelectorAll('input[name="international_participation"]');
  const internationalDetailsWrap = document.getElementById('internationalDetailsWrap');
  const internationalCountriesSelect = document.getElementById('internationalCountriesSelect');
  const internationalCountryCount = document.getElementById('internationalCountryCount');
  const resourceChecks = document.querySelectorAll('input[name="resources_mobilized_options[]"]');
  const internalFundingWrap = document.getElementById('internalFundingWrap');
  const externalFundingWrap = document.getElementById('externalFundingWrap');
  const trainingMetricsWrap = document.getElementById('trainingMetricsWrap');
  const volunteerMetricsWrap = document.getElementById('volunteerMetricsWrap');
  const volunteersCount = document.getElementById('volunteersCount');
  const volunteerHoursPerPerson = document.getElementById('volunteerHoursPerPerson');
  const totalVolunteerHours = document.getElementById('totalVolunteerHours');

  const publishRadios = document.querySelectorAll('input[name="هل نُشرت على موقع الجامعة؟"]');
  const mediaCoverageTypeRadios = document.querySelectorAll('input[name="media_coverage_type"]');
  const mediaCoverageTypeWrap = document.getElementById('mediaCoverageTypeWrap');
  const newsCoverageWrap = document.getElementById('newsCoverageWrap');
  const newsLinkWrap = document.getElementById('newsLinkWrap');
  const tvInterviewWrap = document.getElementById('tvInterviewWrap');
  const tvInterviewLinkWrap = document.getElementById('tvInterviewLinkWrap');
  const evidenceTypeChecks=document.querySelectorAll('input[name="evidence_type[]"]');
  const evidenceUploadWrap=document.getElementById('evidenceUploadWrap');
  const evidenceUrlWrap=document.getElementById('evidenceUrlWrap');
  const evidenceExplanationWrap=document.getElementById('evidenceExplanationWrap');
  const evidenceMetadataWrap=document.getElementById('evidenceMetadataWrap');
  const publicSharingWrap=document.getElementById('publicSharingWrap');
  const evidenceLinksList=document.getElementById('evidenceLinksList');
  const addEvidenceLinkBtn=document.getElementById('addEvidenceLinkBtn');
  const clearEvidenceLinksBtn=document.getElementById('clearEvidenceLinksBtn');
  const evidenceLinksCount=document.getElementById('evidenceLinksCount');
  const evidenceLinksJson=document.getElementById('evidenceLinksJson');
  const supportingEvidenceFiles=document.getElementById('supportingEvidenceFiles');
  const evidenceFilesJson=document.getElementById('evidenceFilesJson');
  const evidenceFilesList=document.getElementById('evidenceFilesList');
  const clearEvidenceFilesBtn=document.getElementById('clearEvidenceFilesBtn');
  const evidenceFilesCount=document.getElementById('evidenceFilesCount');
  const evidenceUploadStatus=document.getElementById('evidenceUploadStatus');
  const maxEvidenceItems=10;

  function updateTotalAttendees(){
    const male=Math.max(0,parseInt(maleBeneficiariesCount?.value || '0',10) || 0);
    const female=Math.max(0,parseInt(femaleBeneficiariesCount?.value || '0',10) || 0);
    const unspecified=Math.max(0,parseInt(unspecifiedBeneficiariesCount?.value || '0',10) || 0);
    if(totalAttendeesCount) totalAttendeesCount.value=String(male+female+unspecified);
  }
  maleBeneficiariesCount?.addEventListener('input',updateTotalAttendees);
  femaleBeneficiariesCount?.addEventListener('input',updateTotalAttendees);
  unspecifiedBeneficiariesCount?.addEventListener('input',updateTotalAttendees);
  updateTotalAttendees();

  function selectedRadioValue(name){
    const checked = document.querySelector(`input[name="${name}"]:checked`);
    return checked ? checked.value : '';
  }

  function toggleAgreementWrap(){
    const value = selectedRadioValue('related_agreement');
    if(agreementWrap){
      agreementWrap.classList.toggle('init-hidden', value !== 'نعم');
    }
    if(value !== 'نعم' && agreementInfoBox){
      agreementInfoBox.classList.add('init-hidden');
    }
    updateAgreementInfo();
  }

  function updateAgreementInfo(){
    if(!agreementSelect || !agreementInfoBox) return;
    const code = agreementSelect.value.trim();
    const data = (window.__AGREEMENTS__ || {})[code];
    if(!data){
      agreementInfoBox.classList.add('init-hidden');
      return;
    }

    agreementInfoBox.classList.remove('init-hidden');
    const setText = (id, value) => {
      const el = document.getElementById(id);
      if(el) el.textContent = value || '—';
    };

    setText('ag_name', data['اسم الاتفاقية'] || '—');
    setText('ag_type', data['نوع الاتفاقية'] || '—');
    setText('ag_partner', data['الجهة المتعاونة'] || '—');
    setText('ag_country', data['الدولة'] || '—');
    setText('ag_owner', data['الجهة المعنية بتنفيذ الاتفاقية'] || '—');
    setText('ag_status', data['الحالة'] || '—');
  }

  function toggleOutsideLocation(){
    const value = selectedRadioValue('location_mode');
    if(outsideLocationWrap) outsideLocationWrap.classList.toggle('init-hidden', value !== 'outside_uob');
  }

  function toggleSelectOther(select, wrap){
    if(wrap) wrap.classList.toggle('init-hidden', !(select && select.value === 'other'));
  }

  function toggleSdgGoals(){
    const value = selectedRadioValue('supports_sdg');
    if(sdgGoalsWrap){
      sdgGoalsWrap.classList.toggle('init-hidden', value !== 'نعم');
    }
  }

  function toggleMediaCoverage(){
    const publicationStatus = selectedRadioValue('هل نُشرت على موقع الجامعة؟');
    const coverageType = selectedRadioValue('media_coverage_type');
    const hasCoverage = ['uob','partner','in_progress'].includes(publicationStatus);
    const isPublished = ['uob','partner'].includes(publicationStatus);
    if(mediaCoverageTypeWrap) mediaCoverageTypeWrap.classList.toggle('init-hidden', !hasCoverage);
    if(newsCoverageWrap) newsCoverageWrap.classList.toggle('init-hidden', !(hasCoverage && coverageType === 'news'));
    if(tvInterviewWrap) tvInterviewWrap.classList.toggle('init-hidden', !(hasCoverage && coverageType === 'tv_interview'));
    if(newsLinkWrap) newsLinkWrap.classList.toggle('init-hidden', !(isPublished && coverageType === 'news'));
    if(tvInterviewLinkWrap) tvInterviewLinkWrap.classList.toggle('init-hidden', !(isPublished && coverageType === 'tv_interview'));
  }

  function toggleEvidenceFields(){
    const selected=Array.from(evidenceTypeChecks).filter(input=>input.checked).map(input=>input.value);
    if(evidenceMetadataWrap) evidenceMetadataWrap.classList.toggle('init-hidden',selected.length===0);
    if(publicSharingWrap) publicSharingWrap.classList.toggle('init-hidden',selected.length===0);
    if(evidenceUploadWrap) evidenceUploadWrap.classList.toggle('init-hidden',!selected.includes('upload'));
    if(evidenceUrlWrap) evidenceUrlWrap.classList.toggle('init-hidden',!selected.includes('url'));
    if(evidenceExplanationWrap) evidenceExplanationWrap.classList.toggle('init-hidden',!selected.includes('explanation'));
  }

  function toggleRankingAreas(){
    const value=selectedRadioValue('ranking_framework');
    if(theAreasWrap) theAreasWrap.classList.toggle('init-hidden',!['the','both'].includes(value));
    if(qsCategoriesWrap) qsCategoriesWrap.classList.toggle('init-hidden',!['qs','both'].includes(value));
    toggleEnvironmentalImpact();
  }

  function selectedValues(select){
    return Array.from(select?.selectedOptions || []).map(option=>option.value);
  }

  function toggleInternationalDetails(){
    const enabled=selectedRadioValue('international_participation')==='yes';
    internationalDetailsWrap?.classList.toggle('init-hidden',!enabled);
  }

  function updateInternationalCountryCount(){
    if(internationalCountryCount) internationalCountryCount.value=String(selectedValues(internationalCountriesSelect).filter(Boolean).length);
  }

  function selectedResourceValues(){
    return Array.from(resourceChecks).filter(input=>input.checked).map(input=>input.value);
  }

  function toggleResourceDetails(){
    const resources=selectedResourceValues();
    internalFundingWrap?.classList.toggle('init-hidden',!resources.includes('budget'));
    externalFundingWrap?.classList.toggle('init-hidden',!resources.includes('external_funding'));
    toggleActivityMetrics();
  }

  function toggleActivityMetrics(){
    const mainType=initiativeTypeSelect?.value || '';
    const secondaryTypes=selectedValues(secondaryInitiativeTypesSelect);
    const trainingTypes=['workshop_training','capacity_building_training','tutoring_coaching_mentorship','volunteer_teaching_training'];
    const volunteerTypes=['volunteering_program','volunteer_teaching_training'];
    const showTraining=trainingTypes.includes(mainType)||secondaryTypes.some(value=>trainingTypes.includes(value));
    const showVolunteer=volunteerTypes.includes(mainType)||secondaryTypes.some(value=>volunteerTypes.includes(value))||selectedResourceValues().includes('volunteers');
    trainingMetricsWrap?.classList.toggle('init-hidden',!showTraining);
    volunteerMetricsWrap?.classList.toggle('init-hidden',!showVolunteer);
  }

  function updateTotalVolunteerHours(){
    const people=Math.max(0,parseInt(volunteersCount?.value||'0',10)||0);
    const hours=Math.max(0,parseFloat(volunteerHoursPerPerson?.value||'0')||0);
    if(totalVolunteerHours) totalVolunteerHours.value=String(people*hours);
  }

  function toggleEnvironmentalImpact(){
    const framework=selectedRadioValue('ranking_framework');
    const qsEnvironmental=['qs','both'].includes(framework)&&Array.from(qsCategoryChecks).some(input=>input.checked&&input.value==='environmental');
    const descriptors=selectedValues(initiativeDescriptorsSelect);
    const secondaryTypes=selectedValues(secondaryInitiativeTypesSelect);
    const typeEnvironmental=(initiativeTypeSelect?.value||'')==='sustainability_activities'||secondaryTypes.includes('sustainability_activities');
    environmentalImpactWrap?.classList.toggle('init-hidden',!(qsEnvironmental||descriptors.includes('campus_operations')||typeEnvironmental));
  }

  function createEvidenceLinkRow(value=''){
    const row=document.createElement('div');
    row.className='init-evidence-link-row';

    const input=document.createElement('input');
    input.type='url';
    input.className='form-control init-input init-evidence-url';
    input.name='evidence_url[]';
    input.value=value;
    input.placeholder=evidenceLinksList?.dataset.placeholder || 'https://...';

    const removeButton=document.createElement('button');
    removeButton.type='button';
    removeButton.className='init-evidence-link-remove';
    removeButton.textContent='×';
    removeButton.setAttribute('aria-label',<?= json_encode($isArabic?'حذف الرابط':'Remove link',JSON_UNESCAPED_UNICODE) ?>);
    removeButton.addEventListener('click',()=>{
      row.remove();
      ensureEvidenceLinkRow();
      syncEvidenceLinks();
    });

    row.append(input,removeButton);
    return row;
  }

  function ensureEvidenceLinkRow(){
    if(evidenceLinksList && !evidenceLinksList.querySelector('.init-evidence-link-row')){
      evidenceLinksList.appendChild(createEvidenceLinkRow());
    }
  }

  function evidenceLinkValues(){
    return Array.from(evidenceLinksList?.querySelectorAll('input[name="evidence_url[]"]') || [])
      .map(input=>input.value.trim())
      .filter(Boolean)
      .slice(0,maxEvidenceItems);
  }

  function updateEvidenceLinkControls(){
    const rows=Array.from(evidenceLinksList?.querySelectorAll('.init-evidence-link-row') || []);
    const linksCount=evidenceLinkValues().length;
    const hasLinks=linksCount>0;
    if(evidenceLinksCount){
      evidenceLinksCount.textContent=`${linksCount} / ${maxEvidenceItems} ${<?= json_encode($isArabic?'روابط':'Links',JSON_UNESCAPED_UNICODE) ?>}`;
    }
    rows.forEach(row=>{
      const input=row.querySelector('input[name="evidence_url[]"]');
      const remove=row.querySelector('.init-evidence-link-remove');
      const hideRemove=rows.length===1 && !(input?.value || '').trim();
      remove?.classList.toggle('init-hidden',hideRemove);
    });
    clearEvidenceLinksBtn?.classList.toggle('init-hidden',!hasLinks);
  }

  function syncEvidenceLinks(){
    if(evidenceLinksJson) evidenceLinksJson.value=JSON.stringify(evidenceLinkValues());
    updateEvidenceLinkControls();
    captureFormState();
    updateInitiativeProgress();
  }

  try{
    const restoredLinks=JSON.parse(evidenceLinksJson?.value || '[]');
    if(Array.isArray(restoredLinks) && restoredLinks.length && evidenceLinksList){
      evidenceLinksList.replaceChildren(...restoredLinks.slice(0,maxEvidenceItems).map(value=>createEvidenceLinkRow(String(value))));
    }
  }catch(_){}
  updateEvidenceLinkControls();

  evidenceLinksList?.querySelectorAll('.init-evidence-link-remove').forEach(button=>{
    button.addEventListener('click',()=>{
      button.closest('.init-evidence-link-row')?.remove();
      ensureEvidenceLinkRow();
      syncEvidenceLinks();
    });
  });
  evidenceLinksList?.addEventListener('input',syncEvidenceLinks);
  addEvidenceLinkBtn?.addEventListener('click',()=>{
    if(!evidenceLinksList) return;
    if(evidenceLinksList.querySelectorAll('.init-evidence-link-row').length>=maxEvidenceItems){
      alert(<?= json_encode($isArabic?'الحد الأقصى 10 روابط.':'The maximum is 10 links.',JSON_UNESCAPED_UNICODE) ?>);
      return;
    }
    const row=createEvidenceLinkRow();
    evidenceLinksList.appendChild(row);
    row.querySelector('input')?.focus();
    syncEvidenceLinks();
  });
  clearEvidenceLinksBtn?.addEventListener('click',()=>{
    if(!evidenceLinksList) return;
    evidenceLinksList.replaceChildren(createEvidenceLinkRow());
    evidenceLinksList.querySelector('input')?.focus();
    syncEvidenceLinks();
  });

  let evidenceFiles=[];
  try{
    const restoredFiles=JSON.parse(evidenceFilesJson?.value || '[]');
    if(Array.isArray(restoredFiles)) evidenceFiles=restoredFiles.filter(file=>file && file.path && file.name).slice(0,maxEvidenceItems);
  }catch(_){}

  function syncEvidenceFiles(){
    if(evidenceFilesJson) evidenceFilesJson.value=JSON.stringify(evidenceFiles);
    captureFormState();
    updateInitiativeProgress();
  }

  function renderEvidenceFiles(){
    if(!evidenceFilesList) return;
    evidenceFilesList.replaceChildren();
    if(evidenceFilesCount){
      evidenceFilesCount.textContent=`${evidenceFiles.length} / ${maxEvidenceItems} ${<?= json_encode($isArabic?'ملفات':'Files',JSON_UNESCAPED_UNICODE) ?>}`;
    }
    clearEvidenceFilesBtn?.classList.toggle('init-hidden',evidenceFiles.length===0);
    evidenceFiles.forEach(file=>{
      const row=document.createElement('div');
      row.className='init-evidence-file-row';

      const name=document.createElement('span');
      name.className='init-evidence-file-name';
      name.textContent=file.name;
      name.title=file.name;

      const download=document.createElement('a');
      download.className='init-evidence-file-download';
      download.href=file.url || ('../'+file.path);
      download.download=file.name;
      download.textContent=<?= json_encode($isArabic?'تنزيل':'Download',JSON_UNESCAPED_UNICODE) ?>;

      const remove=document.createElement('button');
      remove.type='button';
      remove.className='init-evidence-link-remove';
      remove.textContent='×';
      remove.setAttribute('aria-label',<?= json_encode($isArabic?'حذف الملف':'Remove file',JSON_UNESCAPED_UNICODE) ?>);
      remove.addEventListener('click',async()=>{
        const body=new FormData();
        body.append('_evidence_file_action','delete');
        body.append('path',file.path);
        try{ await fetch(window.location.href,{method:'POST',body}); }catch(_){}
        evidenceFiles=evidenceFiles.filter(item=>item.path!==file.path);
        syncEvidenceFiles();
        renderEvidenceFiles();
      });

      row.append(name,download,remove);
      evidenceFilesList.appendChild(row);
    });
  }

  supportingEvidenceFiles?.addEventListener('change',async()=>{
    const selected=Array.from(supportingEvidenceFiles.files || []);
    const available=Math.max(0,maxEvidenceItems-evidenceFiles.length);
    if(selected.length>available){
      alert(<?= json_encode($isArabic?'الحد الأقصى 10 ملفات.':'The maximum is 10 files.',JSON_UNESCAPED_UNICODE) ?>);
    }
    const filesToUpload=selected.slice(0,available);
    if(evidenceUploadStatus) evidenceUploadStatus.textContent=<?= json_encode($isArabic?'جارٍ رفع الملفات...':'Uploading files...',JSON_UNESCAPED_UNICODE) ?>;

    for(const file of filesToUpload){
      const body=new FormData();
      body.append('_evidence_file_action','upload');
      body.append('evidence_file',file);
      try{
        const response=await fetch(window.location.href,{method:'POST',body});
        const result=await response.json();
        if(result.ok && result.file) evidenceFiles.push(result.file);
      }catch(_){}
    }

    evidenceFiles=evidenceFiles.slice(0,maxEvidenceItems);
    supportingEvidenceFiles.value='';
    syncEvidenceFiles();
    renderEvidenceFiles();
    if(evidenceUploadStatus) evidenceUploadStatus.textContent=evidenceFiles.length
      ? <?= json_encode($isArabic?'تم حفظ الملفات المرفقة.':'Attached files have been saved.',JSON_UNESCAPED_UNICODE) ?>
      : '';
  });

  clearEvidenceFilesBtn?.addEventListener('click',async()=>{
    const filesToDelete=[...evidenceFiles];
    evidenceFiles=[];
    syncEvidenceFiles();
    renderEvidenceFiles();
    await Promise.all(filesToDelete.map(file=>{
      const body=new FormData();
      body.append('_evidence_file_action','delete');
      body.append('path',file.path);
      return fetch(window.location.href,{method:'POST',body}).catch(()=>null);
    }));
    if(evidenceUploadStatus) evidenceUploadStatus.textContent='';
  });

  renderEvidenceFiles();
  initiativeForm?.addEventListener('submit',()=>{
    syncEvidenceLinks();
    syncEvidenceFiles();
  });

  function handleEvidenceChange(event){
    const changed=event.currentTarget;
    if(changed.checked && changed.value==='explanation'){
      evidenceTypeChecks.forEach(input=>{ if(input.value!=='explanation') input.checked=false; });
    }else if(changed.checked){
      evidenceTypeChecks.forEach(input=>{ if(input.value==='explanation') input.checked=false; });
    }
    toggleEvidenceFields();
  }

  relatedRadios.forEach(r => r.addEventListener('change', toggleAgreementWrap));
  locationRadios.forEach(r => r.addEventListener('change', toggleOutsideLocation));
  initiativeTypeSelect && initiativeTypeSelect.addEventListener('change', () => {
    toggleSelectOther(initiativeTypeSelect, initiativeTypeOtherWrap);
    toggleActivityMetrics();
    toggleEnvironmentalImpact();
  });
  secondaryInitiativeTypesSelect?.addEventListener('change',()=>{toggleActivityMetrics();toggleEnvironmentalImpact();});
  initiativeDescriptorsSelect?.addEventListener('change',toggleEnvironmentalImpact);
  departmentUnitSelect && departmentUnitSelect.addEventListener('change', () => toggleSelectOther(departmentUnitSelect, departmentUnitOtherWrap));
  sdgRadios.forEach(r => r.addEventListener('change', toggleSdgGoals));
  rankingFrameworkRadios.forEach(r => r.addEventListener('change', toggleRankingAreas));
  qsCategoryChecks.forEach(input=>input.addEventListener('change',toggleEnvironmentalImpact));
  internationalParticipationRadios.forEach(input=>input.addEventListener('change',toggleInternationalDetails));
  internationalCountriesSelect?.addEventListener('change',updateInternationalCountryCount);
  resourceChecks.forEach(input=>input.addEventListener('change',toggleResourceDetails));
  volunteersCount?.addEventListener('input',updateTotalVolunteerHours);
  volunteerHoursPerPerson?.addEventListener('input',updateTotalVolunteerHours);
  publishRadios.forEach(r => r.addEventListener('change', toggleMediaCoverage));
  mediaCoverageTypeRadios.forEach(r => r.addEventListener('change', toggleMediaCoverage));
  evidenceTypeChecks.forEach(input => input.addEventListener('change', handleEvidenceChange));
  agreementSelect && agreementSelect.addEventListener('change', updateAgreementInfo);

  toggleAgreementWrap();
  toggleOutsideLocation();
  toggleSelectOther(initiativeTypeSelect, initiativeTypeOtherWrap);
  toggleSelectOther(departmentUnitSelect, departmentUnitOtherWrap);
  toggleSdgGoals();
  toggleRankingAreas();
  toggleInternationalDetails();
  updateInternationalCountryCount();
  toggleResourceDetails();
  toggleActivityMetrics();
  toggleEnvironmentalImpact();
  updateTotalVolunteerHours();
  toggleMediaCoverage();
  toggleEvidenceFields();
  updateAgreementInfo();

  if(window.TomSelect){
    const common = {create:false, allowEmptyOption:true};
    const clearAllLabel = '<?= $isArabic ? 'مسح جميع الخيارات' : 'Clear all selected options' ?>';
    const inlineOtherWraps = {
      initiativeTypeSelect: initiativeTypeOtherWrap,
      departmentUnitSelect: departmentUnitOtherWrap
    };

    function syncTomSelectClearAll(instance){
      if(!instance || !instance.wrapper || !instance.wrapper.classList.contains('multi')) return;
      let clearBtn = instance.control.querySelector('.init-ts-clear-all');
      if(!clearBtn){
        clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'init-ts-clear-all';
        clearBtn.setAttribute('title', clearAllLabel);
        clearBtn.setAttribute('aria-label', clearAllLabel);
        clearBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M9 4.75h6a1 1 0 0 1 .95.68L16.3 7H7.7l.35-1.57A1 1 0 0 1 9 4.75Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M8 10v6.5M12 10v6.5M16 10v6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M6.8 7h10.4l-.72 10.1A2 2 0 0 1 14.49 19H9.51a2 2 0 0 1-1.99-1.9L6.8 7Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>';
        clearBtn.addEventListener('mousedown', function(e){
          e.preventDefault();
          e.stopPropagation();
        });
        clearBtn.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          instance.clear(true);
          instance.close();
          if(instance.control_input){
            instance.control_input.placeholder = instance.settings.placeholder;
          }
          if(instance.input){
            instance.input.dispatchEvent(new Event('change', {bubbles:true}));
          }
        });
        instance.control.appendChild(clearBtn);
      }
      clearBtn.classList.toggle('is-visible', instance.items.length > 0);
    }

    document.querySelectorAll('select.init-searchable').forEach(select => {
      const isMulti = select.multiple;
      const allowCreate = select.getAttribute('data-allow-create') === 'true';
      const instance = new TomSelect(select, {
        ...common,
        create: allowCreate,
        dropdownParent: 'body',
        dropdownClass: 'ts-dropdown init-searchable-dropdown',
        plugins: isMulti ? ['remove_button'] : ['clear_button'],
        maxItems: isMulti ? null : 1,
        hidePlaceholder: true,
        placeholder: select.getAttribute('data-placeholder') || (isMulti ? '<?= $isArabic ? 'ابحث واختر خيارًا أو أكثر' : 'Search and select one or more options' ?>' : '<?= $isArabic ? 'ابحث أو اختر' : 'Search or select' ?>'),
        onInitialize: function(){
          const inlineOtherWrap = inlineOtherWraps[select.id];
          if(inlineOtherWrap){
            this.control.appendChild(inlineOtherWrap);
            const inlineInput = inlineOtherWrap.querySelector('input');
            if(inlineInput){
              inlineInput.addEventListener('mousedown', e => e.stopPropagation());
              inlineInput.addEventListener('click', e => e.stopPropagation());
            }
          }
          if(select.getAttribute('data-add-many') === 'true'){
            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'init-ts-add-many';
            addButton.textContent = '+';
            addButton.setAttribute('title', '<?= $isArabic?'إضافة النص المكتوب':'Add the typed value' ?>');
            addButton.setAttribute('aria-label', '<?= $isArabic?'إضافة النص المكتوب':'Add the typed value' ?>');
            addButton.addEventListener('mousedown', e => {
              e.preventDefault();
              e.stopPropagation();
            });
            addButton.addEventListener('click', e => {
              e.preventDefault();
              e.stopPropagation();
              const value = this.control_input.value.trim();
              if(!value){
                this.focus();
                return;
              }
              if(!this.options[value]){
                this.addOption({value:value, text:value});
              }
              this.addItem(value, true);
              this.setTextboxValue('');
              this.refreshOptions(false);
              this.focus();
            });
            this.control.appendChild(addButton);
          }
          this.control_input.placeholder = this.items.length ? '' : this.settings.placeholder;
          if(isMulti) syncTomSelectClearAll(this);
        },
        onItemAdd: function(){
          this.control_input.placeholder = '';
          this.setTextboxValue('');
          this.refreshOptions(false);
          if(isMulti) syncTomSelectClearAll(this);
        },
        onItemRemove: function(){
          this.control_input.placeholder = this.items.length ? '' : this.settings.placeholder;
          if(isMulti) syncTomSelectClearAll(this);
        },
        onClear: function(){
          this.control_input.placeholder = this.settings.placeholder;
          if(isMulti) syncTomSelectClearAll(this);
        },
        onBlur: function(){
          this.setTextboxValue('');
          this.refreshOptions(false);
        },
        onChange: function(){
          if(isMulti) syncTomSelectClearAll(this);
          select.dispatchEvent(new Event('change', {bubbles:true}));
        }
      });
      select.tomselectInstance = instance;
    });
  }
})();


function addOutputSuggestion(text){
  const textarea = document.getElementById('outputsBox');
  if (!textarea) return;

  text = text.trim();
  if (!text) return;

  const current = textarea.value.trim();

  if (current.includes(text)) return;

  textarea.value = current ? current + '، ' + text : text;
}



function addText(btn, textareaId){
  const textarea = document.getElementById(textareaId);

  let text = btn.innerText.trim();
  if(!text) return;

  if(textarea.value.includes(text)) return;

  if(textarea.value.trim() !== ''){
    textarea.value += '، ' + text;
  } else {
    textarea.value = text;
  }
}

document.getElementById('aiSuggestBtn')?.addEventListener('click', async () => {

  // 1. نجيب البيانات من الفورم
  const title = document.querySelector('input[name="عنوان المبادرة"]').value;
  const desc  = document.getElementById('descBox').value;

  if (!title && !desc) {
    alert('اكتب عنوان أو وصف أول');
    return;
  }

  // 2. نغير شكل الزر
  const btn = document.getElementById('aiSuggestBtn');
  btn.innerText = "⏳ جاري التحليل...";
  btn.disabled = true;

  // 3. نرسل البيانات لـ PHP
  const res = await fetch('ai-sdg.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title: title,
      desc: desc
    })
  });

  const data = await res.json();

  // 4-5. تعيين الهدف الرئيسي ثم الأهداف الثانوية
  const suggested = (data.sdgs || []).map(num => `SDG ${num}`);
  const primarySdgSelect = document.getElementById('primarySdgSelect');
  const secondarySdgsSelect = document.getElementById('secondarySdgsSelect');
  if (suggested.length && primarySdgSelect) {
    if (primarySdgSelect.tomselect) primarySdgSelect.tomselect.setValue(suggested[0]);
    else primarySdgSelect.value = suggested[0];
  }
  const secondaryValues = suggested.slice(1);
  if (secondarySdgsSelect) {
    if (secondarySdgsSelect.tomselect) secondarySdgsSelect.tomselect.setValue(secondaryValues);
    else Array.from(secondarySdgsSelect.options).forEach(option => option.selected = secondaryValues.includes(option.value));
  }

  // 6. نرجع الزر طبيعي
  btn.innerText = "🤖 اقتراح ذكي للأهداف";
  btn.disabled = false;
});


</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
<style>
/* v12: Arabic selected tags alignment */
html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item{
  direction:rtl;
  flex-direction:row;
  justify-content:center;
  text-align:center;
  padding-inline:14px 10px;
  white-space:normal;
  overflow:visible;
  word-break:normal;
  overflow-wrap:break-word;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  order:2;
  margin-inline-start:8px;
  margin-inline-end:0;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item.active,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item.active{
  text-align:center;
}
</style>
<style>
/* v13: keep the remove button comfortably inside selected tags */
.ts-wrapper.init-searchable.multi .ts-control > .item{
  padding-inline:16px 14px;
}

.ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-inline-start:8px;
  margin-inline-end:2px;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item{
  padding-inline:16px 14px;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-inline-start:8px;
  margin-inline-end:2px;
}
</style>
<style>
/* v14: explicit physical spacing for remove icon in LTR and RTL tags */
.ts-wrapper.init-searchable.multi .ts-control > .item{
  column-gap:12px !important;
  padding:8px 18px !important;
}

/* English/LTR: remove icon sits on the right with room before the edge */
html:not([dir="rtl"]) .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body:not(.rtl) .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-left:10px !important;
  margin-right:6px !important;
}

/* Arabic/RTL: remove icon sits on the left with room before the edge */
html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-right:10px !important;
  margin-left:6px !important;
}

.ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  width:24px !important;
  height:24px !important;
  min-width:24px !important;
  flex:0 0 24px !important;
}
</style>
<style>
/* v15: balanced remove-icon spacing inside selected tags */
.ts-wrapper.init-searchable.multi .ts-control > .item{
  column-gap:6px !important;
  padding:8px 13px !important;
}

.ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  width:20px !important;
  height:20px !important;
  min-width:20px !important;
  flex:0 0 20px !important;
  margin:0 !important;
  padding:0 !important;
  display:inline-flex !important;
  align-items:center !important;
  justify-content:center !important;
}

html:not([dir="rtl"]) .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body:not(.rtl) .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-left:4px !important;
  margin-right:0 !important;
}

html[dir="rtl"] .ts-wrapper.init-searchable.multi .ts-control > .item .remove,
body.rtl .ts-wrapper.init-searchable.multi .ts-control > .item .remove{
  margin-right:4px !important;
  margin-left:0 !important;
}
</style>