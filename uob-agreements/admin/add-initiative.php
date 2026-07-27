<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_email'])) {
  header("Location: ../login.php?to=admin/add-initiative-approved.php");
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
  ['value'=>'field_visit','ar'=>'زيارة ميدانية','en'=>'Field Visit'],
  ['value'=>'exchange_program','ar'=>'برنامج تبادل','en'=>'Exchange Program'],
  ['value'=>'joint_research','ar'=>'بحث مشترك','en'=>'Joint Research'],
  ['value'=>'coordination_professional_meeting','ar'=>'اجتماع تنسيقي / مهني','en'=>'Coordination / Professional Meeting'],
  ['value'=>'student_initiative','ar'=>'مبادرة طلابية','en'=>'Student Initiative'],
  ['value'=>'community_engagement','ar'=>'مشاركة مجتمعية','en'=>'Community Engagement'],
  ['value'=>'volunteering_program','ar'=>'برنامج تطوعي','en'=>'Volunteering Program'],
  ['value'=>'consultation_advisory','ar'=>'استشارة / دور استشاري','en'=>'Consultation / Advisory Role'],
  ['value'=>'awareness_campaign','ar'=>'حملة توعوية','en'=>'Awareness Campaign'],
  ['value'=>'capacity_building_training','ar'=>'بناء القدرات وتدريب المجتمع','en'=>'Capacity Building & Community Training'],
  ['value'=>'awareness_media','ar'=>'حملات توعوية ومشاركة إعلامية','en'=>'Awareness Campaigns & Media Engagement'],
  ['value'=>'community_support','ar'=>'دعم ومشاركة مجتمعية','en'=>'Community Support & Engagement'],
  ['value'=>'community_partnerships','ar'=>'شراكات مجتمعية','en'=>'Community Partnerships'],
  ['value'=>'volunteering_activities','ar'=>'أنشطة تطوعية','en'=>'Volunteering Activities'],
  ['value'=>'knowledge_transfer','ar'=>'نقل المعرفة','en'=>'Knowledge Transfer'],
  ['value'=>'tutoring_coaching_mentorship','ar'=>'إرشاد / تدريب / توجيه','en'=>'Tutoring / Coaching / Mentorship'],
  ['value'=>'professional_membership','ar'=>'عضوية / لجنة / تحكيم','en'=>'Professional Membership / Committee / Jury'],
  ['value'=>'media_article','ar'=>'مقال / نشر إعلامي','en'=>'Media / Newspaper Article'],
  ['value'=>'school_outreach','ar'=>'أنشطة تعليمية للمدارس','en'=>'School Outreach Activities'],
  ['value'=>'vulnerable_groups','ar'=>'دعم الفئات المحتاجة','en'=>'Support for Vulnerable Groups'],
  ['value'=>'sustainability_activities','ar'=>'أنشطة الاستدامة','en'=>'Sustainability Activities'],
  ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
  ['value'=>'training_session','ar'=>'جلسة تدريبية','en'=>'Training Session'],
  ['value'=>'university_community_project','ar'=>'مشروع شراكة بين الجامعة والمجتمع','en'=>'University–Community Partnership Project'],
  ['value'=>'student_youth_engagement','ar'=>'مبادرة لمشاركة الطلبة أو الشباب','en'=>'Student or Youth Engagement Initiative'],
  ['value'=>'volunteer_teaching_training','ar'=>'تعليم أو تدريب تطوعي للفئات المحتاجة','en'=>'Volunteer Teaching or Training for Vulnerable Groups'],
  ['value'=>'no_activity','ar'=>'لا يوجد نشاط','en'=>'No Activity'],
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
 ['value'=>'uob_upload','ar'=>'نعم، بعد رفعه على موقع جامعة البحرين / الكلية','en'=>'Yes, once uploaded by UOB/College website'],
 ['value'=>'already_public','ar'=>'نعم، منشور للعامة بالفعل','en'=>'Yes, already public'],
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
  'external_entities','supporting_files','initiative_contributors',
  'status'
];

function postv(string $key, string $default = ''): string {
  return trim($_POST[$key] ?? $default);
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
  $coordinator = postv('منسق المبادرة');
  $coordinatorType = postv('coordinator_type');
  $coordinatorTypeOther = postv('coordinator_type_other');
  $initiativeType = postv('نوع المبادرة');

  $startDate = postv('تاريخ تنفيذ المبادرة');
  $endDate = postv('تاريخ انتهاء المبادرة');

  $locationMode = postv('location_mode');
  $outsideLocation = postv('outside_location');
  $locationValue = $locationMode;
  if ($locationMode === 'other' && postv('implementation_scope_other') !== '') {
    $locationValue .= ' - ' . postv('implementation_scope_other');
  }
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
  $totalBeneficiaries = (string)($male + $female);
  $youthFlag = postv('youth_18_35');

  $supportsSdg = postv('supports_sdg');
  $selectedSdgs = $_POST['sdg_goals'] ?? [];
  if (!is_array($selectedSdgs)) $selectedSdgs = [];
  $selectedSdgs = array_values(array_filter(array_map('trim', $selectedSdgs)));

  $sdgMain = '';
  $sdgSecondary = '';
  if ($supportsSdg === 'نعم' && count($selectedSdgs) > 0) {
    $sdgMain = $selectedSdgs[0];
    if (count($selectedSdgs) > 1) {
      $sdgSecondary = implode(' | ', array_slice($selectedSdgs, 1));
    }
  }

  $published = postv('هل نُشرت على موقع الجامعة؟');
  $publishedYesValues = ['uob','partner'];
  $newsLink = in_array($published, $publishedYesValues, true) ? postv('رابط خبر المبادرة') : '';
  $imagesLink = '';
  $outputs = postv('المخرجات التي تم تحقيقها ');
  $qsSupport = postv('هل تدعم QS؟');
  $greenMetricSupport = postv('هل تدعم GreenMetric؟');
  $notesEntity = postv('m_notes_entity');
  $notesVppd = postv('m_notes_vppd');
  $responsibleName = postv('responsible_name');
  $responsibleEmail = postv('responsible_email');
  $responsibleMobile = postv('responsible_mobile');
  $departmentUnit = postv('department_unit');
  $departmentUnitOther = postv('department_unit_other');
  $departmentWithinCollege = postv('department_within_college');
  $requesterDepartment = postv('requester_department');
  $requesterDepartmentOther = postv('requester_department_other');
  $implementationScope = $locationMode;
  $implementationScopeOther = postv('implementation_scope_other');
  $initiativeDescriptorsSelected = $_POST['initiative_descriptors'] ?? [];
  if (!is_array($initiativeDescriptorsSelected)) $initiativeDescriptorsSelected = [];
  $initiativeDescriptorsSelected = array_values(array_unique(array_filter(array_map('trim', $initiativeDescriptorsSelected), fn($value) => $value !== '' && $value !== 'other')));
  $initiativeDescriptorOther = '';
  $initiativeObjective = $description;
  $initiativeTypeOther = postv('initiative_type_other');
  $secondaryInitiativeTypes = $_POST['secondary_initiative_types'] ?? [];
  if (!is_array($secondaryInitiativeTypes)) $secondaryInitiativeTypes = [];
  $secondaryInitiativeTypes = array_values(array_unique(array_filter(array_map('trim', $secondaryInitiativeTypes), fn($value) => $value !== '' && $value !== 'other')));
  $secondaryInitiativeTypeOther = '';
  $publishedOther = postv('published_other');
  $targetGroupOther = '';
  $totalAttendees = (string)($male + $female);
  $societalImpact = postv('societal_impact');
  $resourcesMobilized = postv('resources_mobilized');
  $environmentalImpact = postv('environmental_impact');
  $evidenceType = postv('evidence_type');
  $evidenceValue = $evidenceType === 'url' ? postv('evidence_url') : ($evidenceType === 'explanation' ? postv('evidence_explanation') : '');
  $pressRelease = $newsLink;
  $publicSharingSelected = $_POST['public_sharing'] ?? [];
  if (!is_array($publicSharingSelected)) $publicSharingSelected = [];
  $facultyStaffGroup = '';
  $externalEntities = postv('external_entities');
  $entity = $departmentUnit === 'other' ? $departmentUnitOther : $departmentUnit;
  $coordinator = $responsibleName;

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
    ];
    if (implode('', $contributor) === '') continue;
    $initiativeContributors[] = $contributor;
  }

  $uploadedEvidencePaths = [];
  if (!empty($_FILES['supporting_files']['name']) && is_array($_FILES['supporting_files']['name'])) {
    $uploadDir = __DIR__ . '/../uploads/initiative-evidence/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    $allowedExtensions = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','mp4','mov'];
    $fileCount = min(count($_FILES['supporting_files']['name']), 10);
    for ($i=0; $i<$fileCount; $i++) {
      if (($_FILES['supporting_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      $originalName = basename((string)$_FILES['supporting_files']['name'][$i]);
      $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if (!in_array($extension, $allowedExtensions, true)) { $errors[] = 'نوع الملف غير مسموح: '.$originalName; continue; }
      $storedName = date('YmdHis').'-'.$i.'-'.preg_replace('/[^A-Za-z0-9_-]+/','-',pathinfo($originalName, PATHINFO_FILENAME)).'.'.$extension;
      if (move_uploaded_file($_FILES['supporting_files']['tmp_name'][$i], $uploadDir.$storedName)) $uploadedEvidencePaths[]='uploads/initiative-evidence/'.$storedName;
    }
  }
  if ($evidenceType === 'upload' && $uploadedEvidencePaths) $evidenceValue = $uploadedEvidencePaths[0];


  // Validation errors with translation
  if ($isRelated === '') $errors[] = t('required_field');
  if ($isRelated === 'نعم' && $agreementCode === '') $errors[] = t('agreement_required');
  if ($title === '') $errors[] = t('title_required');
  if ($initiativeType === '') $errors[] = t('type_required');
  if ($entity === '') $errors[] = t('entity_required');
  if ($startDate === '') $errors[] = t('date_required');
  if ($locationMode === '') $errors[] = t('location_required');
  if ($outsideLocation === '') $errors[] = $isArabic ? 'مكان التنفيذ أو اسم موقع المبادرة مطلوب.' : 'Venue / Location Name is required.';
  if ($supportsSdg === 'نعم' && count($selectedSdgs) === 0) $errors[] = t('sdg_required');
  if (in_array($published, $publishedYesValues, true) && $newsLink === '') $errors[] = t('news_link_required');
  if ($coordinatorType === '') $errors[] = $isArabic ? 'نوع منسق المبادرة مطلوب.' : 'Initiative coordinator type is required.';
  if ($coordinatorType === 'other' && $coordinatorTypeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نوع منسق المبادرة الآخر.' : 'Please specify the other initiative coordinator type.';
  if ($responsibleName === '') $errors[] = $isArabic ? 'اسم الشخص المسؤول مطلوب.' : 'Responsible person name is required.';
  if ($responsibleEmail === '' || !filter_var($responsibleEmail, FILTER_VALIDATE_EMAIL)) $errors[] = $isArabic ? 'البريد الإلكتروني للشخص المسؤول غير صحيح.' : 'A valid responsible person email is required.';
  if ($departmentUnit === '') $errors[] = $isArabic ? 'القسم / المركز / الوحدة مطلوب.' : 'Department / Center / Unit is required.';
  if ($departmentUnit === 'other' && $departmentUnitOther === '') $errors[] = $isArabic ? 'يرجى تحديد القسم / المركز / الوحدة الأخرى.' : 'Please specify the other Department / Center / Unit.';
  if ($requesterDepartment === 'other' && $requesterDepartmentOther === '') $errors[] = $isArabic ? 'يرجى تحديد قسم مقدم الطلب الآخر.' : 'Please specify the other applicant department.';
  if ($initiativeType === 'other' && $initiativeTypeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نوع المبادرة الآخر.' : 'Please specify the other initiative type.';
  if (in_array($initiativeType, $secondaryInitiativeTypes, true)) $errors[] = $isArabic ? 'لا يمكن اختيار النوع الرئيسي نفسه كنوع ثانوي.' : 'The primary initiative type cannot also be selected as a secondary type.';
  if ($published === 'other' && $publishedOther === '') $errors[] = $isArabic ? 'يرجى توضيح خيار النشر الآخر.' : 'Please specify the other publication status.';
  if ($locationMode === 'other' && $implementationScopeOther === '') $errors[] = $isArabic ? 'يرجى تحديد نطاق التنفيذ الآخر.' : 'Please specify the other implementation scope.';
  if ($initiativeObjective === '') $errors[] = $isArabic ? 'هدف المبادرة مطلوب.' : 'Objective of the initiative is required.';
  if ($evidenceType === 'url' && !filter_var($evidenceValue, FILTER_VALIDATE_URL)) $errors[] = $isArabic ? 'يرجى إدخال رابط دليل صحيح.' : 'Please enter a valid evidence URL.';
  if ($evidenceType === 'explanation' && $evidenceValue === '') $errors[] = $isArabic ? 'يرجى كتابة شرح مختصر للدليل.' : 'Please provide a short evidence explanation.';
  if ($evidenceType === 'upload' && !$uploadedEvidencePaths) $errors[] = $isArabic ? 'يرجى تحميل ملف دليل واحد على الأقل.' : 'Please upload at least one evidence file.';
  foreach ($initiativeContributors as $index => $contributor) {
    $number = $index + 1;
    if ($contributor['name'] === '') $errors[] = $isArabic ? "اسم المشارك رقم {$number} مطلوب." : "Contributor {$number} name is required.";
    if ($contributor['type'] === '') $errors[] = $isArabic ? "نوع المشارك رقم {$number} مطلوب." : "Contributor {$number} type is required.";
    if ($contributor['role'] === '') $errors[] = $isArabic ? "دور المشارك رقم {$number} مطلوب." : "Contributor {$number} role is required.";
    if ($contributor['email'] !== '' && !filter_var($contributor['email'], FILTER_VALIDATE_EMAIL)) $errors[] = $isArabic ? "البريد الإلكتروني للمشارك رقم {$number} غير صحيح." : "Contributor {$number} email is invalid.";
    if ($contributor['type'] === 'other' && $contributor['type_other'] === '') $errors[] = $isArabic ? "يرجى تحديد نوع المشارك الآخر رقم {$number}." : "Please specify the other type for contributor {$number}.";
    if ($contributor['department'] === 'other' && $contributor['department_other'] === '') $errors[] = $isArabic ? "يرجى تحديد جهة المشارك الأخرى رقم {$number}." : "Please specify the other department/entity for contributor {$number}.";
    if ($contributor['role'] === 'other' && $contributor['role_other'] === '') $errors[] = $isArabic ? "يرجى تحديد دور المشارك الآخر رقم {$number}." : "Please specify the other role for contributor {$number}.";
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
$data['coordinator_type']=$coordinatorType;
$data['coordinator_type_other']=$coordinatorTypeOther;
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
$data['press_release']=$pressRelease;
$data['public_sharing']=implode(' | ',$publicSharingSelected);
$data['faculty_staff_group']=$facultyStaffGroup;
$data['external_entities']=$externalEntities;
$data['supporting_files']=implode(' | ',$uploadedEvidencePaths);
$data['initiative_contributors']=json_encode($initiativeContributors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$data['status'] = 'قيد المراجعة';
$data['notes_vppd'] = '';
$data['submitted_by'] = $_SESSION['user_email'] ?? '';
$data['submitted_at'] = date('Y-m-d H:i:s');
 

    if (!file_exists(INITIATIVES_MASTER)) {
      $fp = fopen(INITIATIVES_MASTER, 'w');
      fputcsv($fp, $fields);
      fclose($fp);
    }

    $fp = fopen(INITIATIVES_MASTER, 'a');
    $row = [];
    foreach ($fields as $f) $row[] = $data[$f];
    fputcsv($fp, $row);
    fclose($fp);

    markApprovalRequestUsed($approvalRequestsFile, $approvalRequestId);

    $success = true;
    $_POST = [];
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
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin-bottom:18px;
}
.init-tab-btn{
  border:1px solid rgba(11,31,58,.10);
  background:#f8fbff;
  color:var(--uob-navy);
  border-radius:16px;
  min-height:48px;
  padding:10px 16px;
  font-weight:900;
  transition:.18s ease;
}
.init-tab-btn:hover{
  transform:translateY(-1px);
  box-shadow:0 10px 22px rgba(2,8,23,.06);
}
.init-tab-btn.active{
  color:#ffffff !important;
  border-color:#8f6f3f !important;
  background:linear-gradient(180deg, #b89a68, #8f6f3f) !important;
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
    <button type="button" class="init-tab-btn active" data-tab="tab-general">1. <?= t('general_info') ?></button>
    <button type="button" class="init-tab-btn" data-tab="tab-time">2. <?= t('timing_location') ?></button>
    <button type="button" class="init-tab-btn" data-tab="tab-beneficiaries">3. <?= t('beneficiaries_impact') ?></button>
    <button type="button" class="init-tab-btn" data-tab="tab-ranking">4. <?= t('rankings_sdgs') ?></button>
    <button type="button" class="init-tab-btn" data-tab="tab-docs">5. <?= t('documentation_notes') ?></button>
  </div>

  <form method="post" id="initiativeForm" enctype="multipart/form-data" novalidate>
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
          <label class="init-label"><?= $isArabic ? 'عنوان المبادرة / الفعالية / النشاط المجتمعي' : 'Initiative title (this could be an event, workshop or any project; for examples of initiatives, please view sustainability.uob.edu.bh)' ?></label>
          <input class="form-control init-input" name="عنوان المبادرة" value="<?= h($_POST['عنوان المبادرة'] ?? '') ?>" placeholder="<?= t('write_title') ?>">
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
          <label class="init-label"><?= $isArabic ? 'نوع منسق المبادرة' : 'Initiative Coordinator Type' ?></label>
          <?php $selectedCoordinatorType = $_POST['coordinator_type'] ?? ''; ?>
          <div class="init-native-inline-shell">
            <select class="form-select init-input" name="coordinator_type" id="coordinatorTypeSelect">
              <option value=""><?= $isArabic ? 'اختر نوع المنسق' : 'Select coordinator type' ?></option>
              <?php $coordinatorTypeOptions = [
                ['value'=>'faculty','ar'=>'عضو هيئة تدريس','en'=>'Faculty Member'],
                ['value'=>'staff','ar'=>'موظف','en'=>'Staff Member'],
                ['value'=>'student','ar'=>'طالب','en'=>'Student'],
                ['value'=>'student_group','ar'=>'مجموعة طلابية','en'=>'Student Group'],
                ['value'=>'external','ar'=>'ممثل جهة خارجية','en'=>'External Entity Representative'],
                ['value'=>'other','ar'=>'أخرى','en'=>'Other'],
              ]; ?>
              <?php foreach ($coordinatorTypeOptions as $option): ?>
                <option value="<?= h($option['value']) ?>" <?= $selectedCoordinatorType === $option['value'] ? 'selected' : '' ?>><?= h($isArabic ? $option['ar'] : $option['en']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="init-inline-other-wrap <?= $selectedCoordinatorType === 'other' ? '' : 'init-hidden' ?>" id="coordinatorTypeOtherWrap">
              <input class="form-control init-input init-inline-other-input" name="coordinator_type_other" value="<?= h($_POST['coordinator_type_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب نوع المنسق الآخر':'Enter the other coordinator type' ?>">
            </span>
          </div>
        </div>
        <div class="col-md-4"><label class="init-label"><?= $isArabic?'اسم منسق المبادرة / الشخص المسؤول':'Initiative Coordinator / Responsible Person Name' ?></label><input class="form-control init-input" name="responsible_name" value="<?= h($_POST['responsible_name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="init-label"><?= $isArabic?'البريد الإلكتروني للشخص المسؤول':'Responsible Person Email Address' ?></label><input type="email" class="form-control init-input" name="responsible_email" value="<?= h($_POST['responsible_email'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="init-label"><?= $isArabic?'رقم هاتف الشخص المسؤول':'Responsible Person Mobile Number' ?></label><input class="form-control init-input" name="responsible_mobile" value="<?= h($_POST['responsible_mobile'] ?? '') ?>"></div>
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'الكلية / الجهة / العمادة المنفذة':'Executing College / Entity / Deanship' ?></label>
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
        <div class="col-md-6">
          <label class="init-label"><?= $isArabic?'قسم مقدم الطلب (إذا كان مختلفًا)':'Applicant Department (if different)' ?></label>
          <?php $selectedRequesterDepartment=$_POST['requester_department']??''; ?>
          <select class="form-select init-input init-searchable" name="requester_department" id="requesterDepartmentSelect">
            <option value=""><?= $isArabic?'اختر قسم مقدم الطلب':'Select applicant department' ?></option>
            <?php foreach($academicDepartmentOptions as $department): ?>
              <option value="<?= h($department['value']) ?>" <?= $selectedRequesterDepartment===$department['value']?'selected':'' ?>><?= h($isArabic?$department['ar']:$department['en']) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="init-inline-other-wrap <?= $selectedRequesterDepartment==='other'?'':'init-hidden' ?>" id="requesterDepartmentOtherWrap">
            <input class="form-control init-input init-inline-other-input" name="requester_department_other" value="<?= h($_POST['requester_department_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب قسم مقدم الطلب الآخر':'Enter the other applicant department' ?>">
          </span>
        </div>
        <div class="col-12 initiative-contributors-section">
          <div class="contributors-header">
            <div>
              <label class="init-label mb-1"><?= $isArabic ? 'المشاركون في تنفيذ المبادرة' : 'Initiative Contributors' ?></label>
              <div class="init-help mt-0"><?= $isArabic ? 'أضف الأشخاص الذين ساهموا في قيادة المبادرة أو تنظيمها أو تنفيذها. لا يشمل ذلك الجمهور أو المستفيدين.' : 'Add people who helped lead, organize, or deliver the initiative. This does not include the audience or beneficiaries.' ?></div>
            </div>
            <div class="contributors-actions">
              <button type="button" class="btn btn-outline-danger contributor-clear-btn" id="clearAllContributorsBtn" title="<?= $isArabic ? 'حذف جميع المشاركين' : 'Remove all contributors' ?>">
                <span class="btn-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M9 4.75h6a1 1 0 0 1 .95.68L16.3 7H7.7l.35-1.57A1 1 0 0 1 9 4.75Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/><path d="M8 10v6.5M12 10v6.5M16 10v6.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/><path d="M6.8 7h10.4l-.72 10.1A2 2 0 0 1 14.49 19H9.51a2 2 0 0 1-1.99-1.9L6.8 7Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></svg>
                </span>
                <?= $isArabic ? 'حذف جميع المشاركين' : 'Remove All Contributors' ?>
              </button>
              <button type="button" class="btn btn-outline-primary contributor-add-btn" id="addContributorBtn">+ <?= $isArabic ? 'إضافة مشارك' : 'Add Contributor' ?></button>
            </div>
          </div>
          <input type="hidden" name="initiative_contributors_json" id="initiativeContributorsJson" value="">
          <div id="contributorsContainer"></div>
          <div class="contributors-empty" id="contributorsEmpty"><?= $isArabic ? 'لم تتم إضافة مشاركين آخرين.' : 'No additional contributors have been added.' ?></div>
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
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'نطاق تنفيذ المبادرة':'Initiative Implementation Scope' ?></label>
          <?php $locationMode = $_POST['location_mode'] ?? ''; ?>
          <div class="init-check-grid">
            <?php $scopeOptions=[
              'within_uob'=>['ar'=>'داخل جامعة البحرين','en'=>'Within the University of Bahrain'],
              'outside_uob'=>['ar'=>'خارج جامعة البحرين','en'=>'Outside the University of Bahrain'],
              'virtual'=>['ar'=>'افتراضي أو عن بُعد','en'=>'Virtual'],
              'hybrid'=>['ar'=>'مختلط','en'=>'Hybrid'],
              'other'=>['ar'=>'أخرى','en'=>'Other'],
            ]; foreach($scopeOptions as $value=>$label): ?>
              <label class="init-radio-card <?= $value==='other'?'init-radio-card-other':'' ?>">
                <input type="radio" name="location_mode" value="<?= h($value) ?>" <?= $locationMode===$value?'checked':'' ?>>
                <span><?= h($isArabic?$label['ar']:$label['en']) ?></span>
                <?php if($value==='other'): ?>
                  <span class="init-scope-other-inline <?= $locationMode==='other'?'':'init-hidden' ?>" id="implementationScopeOtherWrap">
                    <input class="form-control init-input init-scope-other-input" name="implementation_scope_other" value="<?= h($_POST['implementation_scope_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب نطاق التنفيذ الآخر':'Enter the other implementation scope' ?>">
                  </span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12" id="outsideLocationWrap">
          <label class="init-label"><?= $isArabic?'مكان التنفيذ / اسم موقع المبادرة':'Venue / Location Name' ?></label>
          <input class="form-control init-input" name="outside_location" value="<?= h($_POST['outside_location'] ?? '') ?>" placeholder="<?= $isArabic?'مثال: جامعة البحرين، اسم المدرسة، المؤسسة الحكومية، الشركة، المركز التدريبي أو غيرها':'Example: University of Bahrain, school name, government entity name, company name, training center name, or others.' ?>">
          <div class="init-help"><?= $isArabic?'موقع تنفيذ المبادرة / الفعالية / النشاط المجتمعي.':'Community Initiative / Event / Activity Location.' ?></div>
        </div>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'ما الوصف الأنسب لهذه المبادرة؟ (اختر كل ما ينطبق)':'What best describes this initiative? (tick all that apply)' ?></label>
          <?php $selectedDescriptors=$_POST['initiative_descriptors']??[]; if(!is_array($selectedDescriptors))$selectedDescriptors=[]; ?>
          <select class="form-select init-input init-searchable" name="initiative_descriptors[]" id="initiativeDescriptorsSelect" multiple data-allow-create="true" data-add-many="true" data-placeholder="<?= $isArabic?'ابحث أو اكتب وصفًا جديدًا ثم اضغط +':'Search or type a new description, then press +' ?>">
            <?php foreach($initiativeDescriptors as $descriptor): ?>
              <?php if($descriptor['value']==='other') continue; ?>
              <option value="<?= h($descriptor['value']) ?>" <?= in_array($descriptor['value'],$selectedDescriptors,true)?'selected':'' ?>><?= h($isArabic?$descriptor['ar']:$descriptor['en']) ?></option>
            <?php endforeach; ?>
            <?php $knownDescriptorValues=array_column($initiativeDescriptors,'value'); foreach($selectedDescriptors as $customDescriptor): ?>
              <?php if($customDescriptor==='' || $customDescriptor==='other' || in_array($customDescriptor,$knownDescriptorValues,true)) continue; ?>
              <option value="<?= h($customDescriptor) ?>" selected><?= h($customDescriptor) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= $isArabic?'لإضافة وصف غير موجود: اكتبه واضغط زر +. يمكنك إضافة أي عدد من الأوصاف.':'To add a new description: type it and press +. You can add as many as needed.' ?></div>
        </div>
<!-- 🔥 الوصف + suggestions -->
    <div class="col-12">

      <label class="init-label"><?= $isArabic ? 'وصف مختصر للمبادرة / الفعالية / النشاط المجتمعي وأهدافها' : 'Brief Description of the Community Initiative / Event / Activity and its Objectives' ?></label>
      <div class="init-help mb-2"><?= $isArabic ? 'يرجى تقديم وصف مختصر للمبادرة أو الفعالية، مع توضيح الهدف الرئيسي منها.' : 'Please provide a brief description of the initiative or event, including its main objective.' ?></div>

      <!-- suggestions -->
      <div class="init-suggestions">
        <button type="button" class="init-suggestion-btn" onclick="addText(this,'descBox')">
          تطوير مهارات الطلبة
        </button>

        <button type="button" class="init-suggestion-btn" onclick="addText(this,'descBox')">
          تعزيز البحث العلمي
        </button>

        <button type="button" class="init-suggestion-btn" onclick="addText(this,'descBox')">
          دعم الابتكار
        </button>
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
          <label class="init-label"><?= $isArabic?'من هم الجمهور المستهدف / المشاركون / المستفيدون؟ (اختر كل ما ينطبق)':'Who were target audience/participants/beneficiaries? (Select input)' ?></label>
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

        <div class="col-md-6">
          <label class="init-label"><?= t('beneficiaries_male') ?></label>
          <input type="number" min="0" class="form-control init-input" name="male_count" value="<?= h($_POST['male_count'] ?? '0') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('beneficiaries_female') ?></label>
          <input type="number" min="0" class="form-control init-input" name="female_count" value="<?= h($_POST['female_count'] ?? '0') ?>">
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
        <div class="col-12"><label class="init-label"><?= $isArabic?'اشرح الأثر المجتمعي أو الصحي للمبادرة':'Explain the social or health impact of the initiative (200 words)' ?></label><textarea class="form-control init-input" name="societal_impact" rows="5"><?= h($_POST['societal_impact']??'') ?></textarea></div>
        <div class="col-md-6"><label class="init-label"><?= $isArabic?'الموارد التي تم حشدها':'Resources mobilized (e.g., budget, funding, partnerships)' ?></label><textarea class="form-control init-input" name="resources_mobilized"><?= h($_POST['resources_mobilized']??'') ?></textarea></div>
        <div class="col-md-6"><label class="init-label"><?= $isArabic?'الأثر البيئي إن وجد':'Environmental impact (if applicable, e.g., energy savings, CO₂ reduced)' ?></label><textarea class="form-control init-input" name="environmental_impact"><?= h($_POST['environmental_impact']??'') ?></textarea></div>
        <div class="col-12"><label class="init-label"><?= $isArabic?'جهات خارجية إضافية مرتبطة بالنشاط (إن وجدت)':'Additional External Entities Associated with the Activity (if any)' ?></label><textarea class="form-control init-input" name="external_entities"><?= h($_POST['external_entities']??'') ?></textarea></div>


        <div class="col-12">
          <label class="init-label"><?= t('achieved_outputs') ?></label>

           

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
      </div>
    </div>

    <!-- TAB 4 -->
    <div class="init-tab-pane" id="tab-ranking">
      <div class="init-section-title"><?= t('rankings_sdgs') ?></div>

      <div class="row g-4">
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
          <label class="init-label"><?= t('select_sdg_goals') ?></label>
          <?php $selSdgs = $_POST['sdg_goals'] ?? []; if (!is_array($selSdgs)) $selSdgs = []; ?>
          <div class="init-check-grid" id="sdgGoalsCheckboxes">
            <?php foreach ($sdgGoals as $goal): ?>
              <?php
                $legacyArabic = $goal['value'] . ' - ' . $goal['ar'];
                $selected = in_array($goal['value'], $selSdgs, true) || in_array($legacyArabic, $selSdgs, true);
              ?>
              <label class="init-check-card">
                <input type="checkbox"
                       name="sdg_goals[]"
                       value="<?= h($goal['value']) ?>"
                       <?= $selected ? 'checked' : '' ?>>
                <span><?= h($isArabic ? $goal['ar'] : $goal['en']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 5 -->
    <div class="init-tab-pane" id="tab-docs">
      <div class="init-section-title"><?= t('documentation_notes') ?></div>


      <div class="row g-4">
        <?php $pub=$_POST['هل نُشرت على موقع الجامعة؟']??''; $publishYesValues=['uob','partner']; ?>
        <div class="col-12">
          <label class="init-label"><?= $isArabic?'هل تم نشر خبر عن المبادرة / الفعالية / النشاط المجتمعي؟':'Was news about the initiative / event / community activity published?' ?></label>
          <div class="init-check-grid">
            <?php $publishOptions=[
              'uob'=>['ar'=>'نعم، عبر منصات جامعة البحرين','en'=>'Yes through the University of Bahrain'],
              'partner'=>['ar'=>'نعم، عبر منصات جهة شريكة أو خارجية','en'=>'Yes through a partner or external entity platform'],
              'no'=>['ar'=>'لا','en'=>'No'],
              'in_progress'=>['ar'=>'قيد النشر','en'=>'In Progress'],
              'other'=>['ar'=>'أخرى','en'=>'Others'],
            ]; foreach($publishOptions as $value=>$label): ?>
              <label class="init-radio-card <?= $value==='other'?'init-radio-card-other':'' ?>">
                <input type="radio" name="هل نُشرت على موقع الجامعة؟" value="<?= h($value) ?>" <?= $pub===$value?'checked':'' ?>>
                <span><?= h($isArabic?$label['ar']:$label['en']) ?></span>
                <?php if($value==='other'): ?>
                  <span class="init-scope-other-inline <?= $pub==='other'?'':'init-hidden' ?>" id="publishedOtherWrap">
                    <input class="form-control init-input init-scope-other-input" name="published_other" value="<?= h($_POST['published_other'] ?? '') ?>" placeholder="<?= $isArabic?'اكتب خيار النشر الآخر':'Enter the other publication status' ?>">
                  </span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-12 <?= in_array($pub,$publishYesValues,true) ? '' : 'init-hidden' ?>" id="newsLinkWrap">
          <label class="init-label"><?= t('news_link') ?></label>
          <input class="form-control init-input" name="رابط خبر المبادرة" value="<?= h($_POST['رابط خبر المبادرة'] ?? '') ?>" placeholder="https://...">
        </div>

        <?php $selectedEvidenceType=$_POST['evidence_type']??''; ?>
        <div class="col-12"><label class="init-label"><?= $isArabic?'أقوى دليل متوفر':'Strongest Available Evidence' ?></label><div class="init-choice-grid"><?php foreach($evidenceTypes as $k=>$v): ?><label class="init-radio-card"><input type="radio" name="evidence_type" value="<?= h($k) ?>" <?= $selectedEvidenceType===$k?'checked':'' ?>><?= h($isArabic ? $v['ar'] : $v['en']) ?></label><?php endforeach; ?></div></div>
        <div class="col-12 <?= $selectedEvidenceType==='upload'?'':'init-hidden' ?>" id="evidenceUploadWrap">
          <label class="init-label"><?= $isArabic?'تحميل الدليل أو الملفات الداعمة (بحد أقصى 10 ملفات)':'Upload Evidence or Supporting Documents (Maximum 10 Files)' ?></label>
          <div class="init-help mb-2"><?= $isArabic?'يمكن رفع الصور أو الفيديو أو التقرير أو الخطاب أو الدعوة أو قائمة الحضور أو أي مستند ذي صلة.':'Upload photos, videos, reports, letters, invitations, attendance lists, or any relevant document.' ?></div>
          <input type="file" class="form-control init-input" name="supporting_files[]" multiple>
        </div>
        <div class="col-12 <?= $selectedEvidenceType==='url'?'':'init-hidden' ?>" id="evidenceUrlWrap"><label class="init-label"><?= $isArabic?'رابط عام للدليل':'Public Evidence URL' ?></label><input type="url" class="form-control init-input" name="evidence_url" value="<?= h($_POST['evidence_url']??'') ?>"></div>
        <div class="col-12 <?= $selectedEvidenceType==='explanation'?'':'init-hidden' ?>" id="evidenceExplanationWrap"><label class="init-label"><?= $isArabic?'شرح كتابي مختصر للدليل':'Short Written Explanation' ?></label><textarea class="form-control init-input" name="evidence_explanation"><?= h($_POST['evidence_explanation']??'') ?></textarea></div>
        <div class="col-12"><label class="init-label"><?= $isArabic?'هل يمكن مشاركة هذا الدليل للعامة بواسطة جامعة البحرين (الموقع الإلكتروني أو التقرير)؟':'Can this evidence be shared publicly by UOB (website or report)? (multi select)' ?></label><div class="init-check-grid"><?php $selectedPublicSharing=$_POST['public_sharing']??[]; if(!is_array($selectedPublicSharing))$selectedPublicSharing=[]; foreach($publicSharingOptions as $x): ?><label class="init-check-card"><input type="checkbox" name="public_sharing[]" value="<?= h($x['value']) ?>" <?= in_array($x['value'],$selectedPublicSharing,true)?'checked':'' ?>><span><?= h($isArabic?$x['ar']:$x['en']) ?></span></label><?php endforeach; ?></div></div>


        <div class="col-md-6">
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
    participant: <?= json_encode($isArabic ? 'المشارك' : 'Contributor', JSON_UNESCAPED_UNICODE) ?>,
    name: <?= json_encode($isArabic ? 'اسم المشارك' : 'Contributor Name', JSON_UNESCAPED_UNICODE) ?>,
    type: <?= json_encode($isArabic ? 'نوع المشارك' : 'Contributor Type', JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode($isArabic ? 'البريد الإلكتروني' : 'Email Address', JSON_UNESCAPED_UNICODE) ?>,
    mobile: <?= json_encode($isArabic ? 'رقم الهاتف (اختياري)' : 'Mobile Number (Optional)', JSON_UNESCAPED_UNICODE) ?>,
    department: <?= json_encode($isArabic ? 'الكلية / العمادة / الجهة' : 'College / Deanship / Entity', JSON_UNESCAPED_UNICODE) ?>,
    subdepartment: <?= json_encode($isArabic ? 'القسم التابع للكلية أو الجهة (إن وجد)' : 'Department within the College or Entity (if applicable)', JSON_UNESCAPED_UNICODE) ?>,
    role: <?= json_encode($isArabic ? 'الدور في المبادرة' : 'Role in the Initiative', JSON_UNESCAPED_UNICODE) ?>,
    remove: <?= json_encode($isArabic ? 'حذف' : 'Remove', JSON_UNESCAPED_UNICODE) ?>,
    select: <?= json_encode($isArabic ? 'اختر' : 'Select', JSON_UNESCAPED_UNICODE) ?>,
    specify: <?= json_encode($isArabic ? 'يرجى التحديد' : 'Please specify', JSON_UNESCAPED_UNICODE) ?>
  };
  const contributorTypeOptions = <?= json_encode($isArabic ? [
    ['value'=>'faculty','label'=>'عضو هيئة تدريس'],['value'=>'staff','label'=>'موظف'],['value'=>'student','label'=>'طالب'],['value'=>'student_group','label'=>'مجموعة طلابية'],['value'=>'external','label'=>'ممثل جهة خارجية'],['value'=>'other','label'=>'أخرى']
  ] : [
    ['value'=>'faculty','label'=>'Faculty Member'],['value'=>'staff','label'=>'Staff Member'],['value'=>'student','label'=>'Student'],['value'=>'student_group','label'=>'Student Group'],['value'=>'external','label'=>'External Entity Representative'],['value'=>'other','label'=>'Other']
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const contributorRoleOptions = <?= json_encode($isArabic ? [
    ['value'=>'lead','label'=>'قائد المبادرة'],['value'=>'coordinator','label'=>'منسق'],['value'=>'organizer','label'=>'منظم'],['value'=>'trainer_speaker','label'=>'مدرب / متحدث'],['value'=>'researcher','label'=>'باحث'],['value'=>'volunteer','label'=>'متطوع'],['value'=>'partner','label'=>'شريك'],['value'=>'other','label'=>'أخرى']
  ] : [
    ['value'=>'lead','label'=>'Initiative Lead'],['value'=>'coordinator','label'=>'Coordinator'],['value'=>'organizer','label'=>'Organizer'],['value'=>'trainer_speaker','label'=>'Trainer / Speaker'],['value'=>'researcher','label'=>'Researcher'],['value'=>'volunteer','label'=>'Volunteer'],['value'=>'partner','label'=>'Partner'],['value'=>'other','label'=>'Other']
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const contributorDepartmentOptions = <?= json_encode(array_map(fn($d)=>['value'=>$d['value'],'label'=>$isArabic?$d['ar']:$d['en']], $departmentOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function esc(value){ return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  function optionsHtml(options, selected){ return `<option value="">${esc(contributorLabels.select)}</option>` + options.map(o => `<option value="${esc(o.value)}" ${o.value===selected?'selected':''}>${esc(o.label)}</option>`).join(''); }
  function syncContributorsJson(){
    if(!initiativeContributorsJson || !contributorsContainer) return;
    const rows = Array.from(contributorsContainer.querySelectorAll('.contributor-card')).map(card => ({
      name: card.querySelector('[name="contributor_name[]"]')?.value || '',
      type: card.querySelector('[name="contributor_type[]"]')?.value || '',
      email: card.querySelector('[name="contributor_email[]"]')?.value || '',
      mobile: card.querySelector('[name="contributor_mobile[]"]')?.value || '',
      department: card.querySelector('[name="contributor_department[]"]')?.value || '',
      subdepartment: card.querySelector('[name="contributor_subdepartment[]"]')?.value || '',
      role: card.querySelector('[name="contributor_role[]"]')?.value || '',
      type_other: card.querySelector('[name="contributor_type_other[]"]')?.value || '',
      department_other: card.querySelector('[name="contributor_department_other[]"]')?.value || '',
      role_other: card.querySelector('[name="contributor_role_other[]"]')?.value || ''
    }));
    initiativeContributorsJson.value = JSON.stringify(rows);
  }
  function updateContributorNumbers(){
    contributorsContainer?.querySelectorAll('.contributor-card').forEach((card,index)=>{ const title=card.querySelector('.contributor-card-title'); if(title) title.textContent=`${contributorLabels.participant} ${index+1}`; });
    const hasContributors = !!contributorsContainer?.children.length;
    if(contributorsEmpty) contributorsEmpty.style.display = hasContributors ? 'none' : 'block';
    clearAllContributorsBtn?.classList.toggle('is-visible', hasContributors);
    syncContributorsJson();
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
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.type)}</label><div class="init-native-inline-shell"><select class="form-select init-input" name="contributor_type[]">${optionsHtml(contributorTypeOptions,data.type)}</select><span class="init-inline-other-wrap ${data.type==='other'?'':'init-hidden'}" data-other-wrap="type"><input class="form-control init-input init-inline-other-input" name="contributor_type_other[]" value="${esc(data.type_other)}" placeholder="${esc(contributorLabels.specify)}"></span></div></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.email)}</label><input type="email" class="form-control init-input" name="contributor_email[]" value="${esc(data.email)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.mobile)}</label><input class="form-control init-input" name="contributor_mobile[]" value="${esc(data.mobile)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.department)}</label><div class="init-native-inline-shell"><select class="form-select init-input" name="contributor_department[]">${optionsHtml(contributorDepartmentOptions,data.department)}</select><span class="init-inline-other-wrap ${data.department==='other'?'':'init-hidden'}" data-other-wrap="department"><input class="form-control init-input init-inline-other-input" name="contributor_department_other[]" value="${esc(data.department_other)}" placeholder="${esc(contributorLabels.specify)}"></span></div></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.subdepartment)}</label><input class="form-control init-input" name="contributor_subdepartment[]" value="${esc(data.subdepartment)}"></div>
        <div class="col-md-6"><label class="init-label">${esc(contributorLabels.role)}</label><div class="init-native-inline-shell"><select class="form-select init-input" name="contributor_role[]">${optionsHtml(contributorRoleOptions,data.role)}</select><span class="init-inline-other-wrap ${data.role==='other'?'':'init-hidden'}" data-other-wrap="role"><input class="form-control init-input init-inline-other-input" name="contributor_role_other[]" value="${esc(data.role_other)}" placeholder="${esc(contributorLabels.specify)}"></span></div></div>
      </div>`;
    card.querySelector('.contributor-remove-btn').addEventListener('click',()=>{card.remove();updateContributorNumbers();});
    card.addEventListener('input', syncContributorsJson);
    card.addEventListener('change', syncContributorsJson);
    ['type','department','role'].forEach(kind=>card.querySelector(`[name="contributor_${kind}[]"]`)?.addEventListener('change',()=>toggleContributorOther(card,kind)));
    contributorsContainer.appendChild(card); updateContributorNumbers();
  }
  addContributorBtn?.addEventListener('click',()=>addContributor());
  clearAllContributorsBtn?.addEventListener('click',()=>{
    if(!contributorsContainer?.children.length) return;
    const confirmed = window.confirm(<?= json_encode($isArabic ? 'هل أنت متأكد من حذف جميع المشاركين؟ لا يمكن التراجع عن هذا الإجراء.' : 'Are you sure you want to remove all contributors? This action cannot be undone.', JSON_UNESCAPED_UNICODE) ?>);
    if(!confirmed) return;
    contributorsContainer.replaceChildren();
    updateContributorNumbers();
  });
  contributorInitialData.forEach(addContributor);
  updateContributorNumbers();
  const coordinatorTypeSelect = document.getElementById('coordinatorTypeSelect');
  const coordinatorTypeOtherWrap = document.getElementById('coordinatorTypeOtherWrap');
  function toggleCoordinatorTypeOther(){
    coordinatorTypeOtherWrap?.classList.toggle('init-hidden', coordinatorTypeSelect?.value !== 'other');
  }
  coordinatorTypeSelect?.addEventListener('change', toggleCoordinatorTypeOther);
  toggleCoordinatorTypeOther();

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

  showTab(current);

  // dynamic fields
  const relatedRadios = document.querySelectorAll('input[name="related_agreement"]');
  const agreementWrap = document.getElementById('agreementSelectWrap');
  const agreementSelect = document.getElementById('agreementSelect');
  const agreementInfoBox = document.getElementById('agreementInfoBox');

  const locationRadios = document.querySelectorAll('input[name="location_mode"]');
  const outsideLocationWrap = document.getElementById('outsideLocationWrap');
  const implementationScopeOtherWrap = document.getElementById('implementationScopeOtherWrap');
  const initiativeDescriptorsSelect = document.getElementById('initiativeDescriptorsSelect');
  const targetGroupsSelect = document.getElementById('targetGroupsSelect');
  const initiativeTypeSelect = document.getElementById('initiativeTypeSelect');
  const initiativeTypeOtherWrap = document.getElementById('initiativeTypeOtherWrap');
  const secondaryInitiativeTypesSelect = document.getElementById('secondaryInitiativeTypesSelect');
  const departmentUnitSelect = document.getElementById('departmentUnitSelect');
  const departmentUnitOtherWrap = document.getElementById('departmentUnitOtherWrap');
  const requesterDepartmentSelect = document.getElementById('requesterDepartmentSelect');
  const requesterDepartmentOtherWrap = document.getElementById('requesterDepartmentOtherWrap');

  const sdgRadios = document.querySelectorAll('input[name="supports_sdg"]');
  const sdgGoalsWrap = document.getElementById('sdgGoalsWrap');

  const publishRadios = document.querySelectorAll('input[name="هل نُشرت على موقع الجامعة؟"]');
  const newsLinkWrap = document.getElementById('newsLinkWrap');
  const publishedOtherWrap = document.getElementById('publishedOtherWrap');
  const evidenceTypeRadios=document.querySelectorAll('input[name="evidence_type"]');
  const evidenceUploadWrap=document.getElementById('evidenceUploadWrap');
  const evidenceUrlWrap=document.getElementById('evidenceUrlWrap');
  const evidenceExplanationWrap=document.getElementById('evidenceExplanationWrap');

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
    if(outsideLocationWrap) outsideLocationWrap.classList.remove('init-hidden');
    if(implementationScopeOtherWrap) implementationScopeOtherWrap.classList.toggle('init-hidden', value !== 'other');
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

  function toggleNewsLink(){
    const value = selectedRadioValue('هل نُشرت على موقع الجامعة؟');
    if(newsLinkWrap){
      newsLinkWrap.classList.toggle('init-hidden', !['uob','partner'].includes(value));
    }
    if(publishedOtherWrap) publishedOtherWrap.classList.toggle('init-hidden', value !== 'other');
  }

  function toggleEvidenceFields(){
    const value=selectedRadioValue('evidence_type');
    if(evidenceUploadWrap) evidenceUploadWrap.classList.toggle('init-hidden',value!=='upload');
    if(evidenceUrlWrap) evidenceUrlWrap.classList.toggle('init-hidden',value!=='url');
    if(evidenceExplanationWrap) evidenceExplanationWrap.classList.toggle('init-hidden',value!=='explanation');
  }

  relatedRadios.forEach(r => r.addEventListener('change', toggleAgreementWrap));
  locationRadios.forEach(r => r.addEventListener('change', toggleOutsideLocation));
  initiativeTypeSelect && initiativeTypeSelect.addEventListener('change', () => toggleSelectOther(initiativeTypeSelect, initiativeTypeOtherWrap));
  departmentUnitSelect && departmentUnitSelect.addEventListener('change', () => toggleSelectOther(departmentUnitSelect, departmentUnitOtherWrap));
  requesterDepartmentSelect && requesterDepartmentSelect.addEventListener('change', () => toggleSelectOther(requesterDepartmentSelect, requesterDepartmentOtherWrap));
  sdgRadios.forEach(r => r.addEventListener('change', toggleSdgGoals));
  publishRadios.forEach(r => r.addEventListener('change', toggleNewsLink));
  evidenceTypeRadios.forEach(r => r.addEventListener('change', toggleEvidenceFields));
  agreementSelect && agreementSelect.addEventListener('change', updateAgreementInfo);

  toggleAgreementWrap();
  toggleOutsideLocation();
  toggleSelectOther(initiativeTypeSelect, initiativeTypeOtherWrap);
  toggleSelectOther(departmentUnitSelect, departmentUnitOtherWrap);
  toggleSelectOther(requesterDepartmentSelect, requesterDepartmentOtherWrap);
  toggleSdgGoals();
  toggleNewsLink();
  toggleEvidenceFields();
  updateAgreementInfo();

  if(window.TomSelect){
    const common = {create:false, allowEmptyOption:true};
    const clearAllLabel = '<?= $isArabic ? 'مسح جميع الخيارات' : 'Clear all selected options' ?>';
    const inlineOtherWraps = {
      initiativeTypeSelect: initiativeTypeOtherWrap,
      departmentUnitSelect: departmentUnitOtherWrap,
      requesterDepartmentSelect: requesterDepartmentOtherWrap
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

  // 4-5. تحديث مربعات أهداف التنمية المستدامة
  const sdgCheckboxes = document.querySelectorAll('#sdgGoalsCheckboxes input[name="sdg_goals[]"]');
  if (sdgCheckboxes.length) {
    const suggested = (data.sdgs || []).map(num => `SDG ${num}`);
    sdgCheckboxes.forEach(box => {
      box.checked = suggested.includes(box.value);
      box.dispatchEvent(new Event('change', {bubbles:true}));
    });
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