<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Location: ../workspace/agreement-form.php', true, 302);
  exit;
}

if (($_SESSION['user_email'] ?? '') !== 'admin@uob.edu.bh') {
  header('Location: ../index.php?lang=' . urlencode($_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar')));
  exit;
}

/* =========================
   Language Handler
   ========================= */
// Get language from session, GET, or default to Arabic
$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$lang = ($lang === 'en') ? 'en' : 'ar';
$isArabic = ($lang === 'ar');

// Set session language for persistence
$_SESSION['lang'] = $lang;

// Translation function that uses the global $lang variable
function __($text_ar, $text_en) {
    global $lang;
    return $lang === 'ar' ? $text_ar : $text_en;
}

function posted($key) {
  return trim($_POST[$key] ?? '');
}

/* =========================
   Layout
   ========================= */
$hidePageHeader = true;
$mainContainer = false;


/* =========================
   UI Text Translations
   ========================= */
$T = [
  'page_title' => __('إضافة اتفاقية جديدة', 'Add New Agreement'),
  'form_title' => __('بيانات الاتفاقية', 'Agreement Information'),
  'form_text' => __('يرجى تعبئة الحقول التالية بشكل واضح ومنظم.', 'Please fill in the following fields clearly and properly.'),
  'section_basic' => __('البيانات الأساسية', 'Basic Information'),
  'section_duration' => __('المدة والتنفيذ', 'Duration & Implementation'),
  'section_desc' => __('وصف الاتفاقية', 'Agreement Description'),
  'section_sdg' => __('الاستدامة والارتباط بـ SDGs', 'Sustainability & SDG Alignment'),
  'agreement_code' => __('كود الاتفاقية', 'Agreement Code'),
  'agreement_name' => __('اسم الاتفاقية', 'Agreement Name'),
  'partner' => __('الجهة المتعاونة', 'Partner Organization'),
  'agreement_type' => __('نوع مشروع التعاون', 'Type of Cooperation'),
  'entity_type' => __('نوع الجهة المتعاونة', 'Entity Type'),
  'country' => __('الدولة', 'Country'),
  'start_date' => __('تاريخ البدء', 'Start Date'),
  'end_date' => __('تاريخ الانتهاء', 'End Date'),
  'auto_renew' => __('تجديد تلقائي', 'Auto Renewal'),
  'responsible_unit' => __('الجهة المعنية بتنفيذ الاتفاقية (داخل الجامعة)', 'Responsible Unit (Inside University)'),
  'status' => __('الحالة', 'Status'),
  'summary' => __('نبذة عن الاتفاقية', 'Agreement Summary'),
  'goals' => __('أهداف الاتفاقية', 'Agreement Objectives'),
  'supports_sdg' => __('هل هذه الاتفاقية تدعم أهداف التنمية المستدامة؟', 'Does this agreement support the SDGs?'),
  'choose_sdgs' => __('اختاري أهداف التنمية المستدامة المرتبطة بالاتفاقية', 'Select the SDGs related to this agreement'),
  'multi_select' => __('يمكنك اختيار أكثر من هدف واحد.', 'You can select more than one goal.'),
  'save' => __('حفظ الاتفاقية', 'Save Agreement'),
  'cancel' => __('إلغاء', 'Cancel'),
  'back_to_agreements' => __('العودة إلى صفحة الاتفاقيات', 'Back to Agreements Page'),
  'success' => __('تمت إضافة الاتفاقية بنجاح.', 'Agreement added successfully.'),
  'review_fields' => __('يرجى مراجعة الحقول التالية:', 'Please review the following fields:'),
  'placeholder_code' => __('مثال: UOB-MOU-2026-001', 'Example: UOB-MOU-2026-001'),
  'placeholder_name' => __('أدخلي اسم الاتفاقية', 'Enter agreement name'),
  'placeholder_partner' => __('اسم الجهة الخارجية', 'External organization name'),
  'placeholder_unit' => __('مثال: كلية الهندسة / عمادة البحث العلمي', 'Example: College of Engineering / Deanship of Scientific Research'),
  'placeholder_summary' => __('اكتبي وصفاً مختصراً يوضح فكرة الاتفاقية ومجالها وأهميتها', 'Write a short description of the agreement, its scope and importance'),
  'placeholder_goals' => __('اكتبي أهداف الاتفاقية، ويمكن كتابة كل هدف في سطر مستقل', 'Write the agreement objectives, one objective per line if needed'),
  'choose' => __('-- اختاري --', '-- Select --'),
  'choose_agreement_type' => __('-- اختاري نوع مشروع التعاون --', '-- Select cooperation type --'),
  'choose_entity_type' => __('-- اختاري نوع الجهة --', '-- Select entity type --'),
  'choose_country' => __('-- اختاري الدولة --', '-- Select country --'),
  'choose_status' => __('-- اختاري الحالة --', '-- Select status --'),
  'switch_lang_ar' => __('العربية', 'Arabic'),
  'switch_lang_en' => __('English', 'English'),
  'required_field' => __('هذا الحقل مطلوب', 'This field is required'),
];

/* =========================
   Options with translations
   ========================= */
$entityTypes = [
  'ar' => [
    'قطاع حكومي' => 'قطاع حكومي',
    'قطاع خاص' => 'قطاع خاص',
    'مؤسسة أكاديمية' => 'مؤسسة أكاديمية',
    'منظمة غير ربحية' => 'منظمة غير ربحية',
  ],
  'en' => [
    'قطاع حكومي' => 'Government Sector',
    'قطاع خاص' => 'Private Sector',
    'مؤسسة أكاديمية' => 'Academic Institution',
    'منظمة غير ربحية' => 'Non-profit Organization',
  ]
];

$agreementTypes = [
  'ar' => [
    'إطار عمل (للجهات الحكومية)' => 'إطار عمل (للجهات الحكومية)',
    'مذكرة تفاهم' => 'مذكرة تفاهم',
    'اتفاقية تعاون' => 'اتفاقية تعاون',
    'أخرى' => 'أخرى',
  ],
  'en' => [
    'إطار عمل (للجهات الحكومية)' => 'Framework Agreement (Government Entities)',
    'مذكرة تفاهم' => 'Memorandum of Understanding',
    'اتفاقية تعاون' => 'Cooperation Agreement',
    'أخرى' => 'Other',
  ]
];

$yesNo = [
  'ar' => ['نعم' => 'نعم', 'لا' => 'لا'],
  'en' => ['نعم' => 'Yes', 'لا' => 'No'],
];

$statusOptions = [
  'ar' => ['نشطة' => 'نشطة', 'منتهية' => 'منتهية'],
  'en' => ['نشطة' => 'Active', 'منتهية' => 'Expired'],
];

$sdgSupportOptions = [
  'ar' => ['نعم' => 'نعم', 'لا' => 'لا'],
  'en' => ['نعم' => 'Yes', 'لا' => 'No'],
];

$sdgGoals = [
  'ar' => [
    'الهدف 1: القضاء على الفقر' => 'الهدف 1: القضاء على الفقر',
    'الهدف 2: القضاء التام على الجوع' => 'الهدف 2: القضاء التام على الجوع',
    'الهدف 3: الصحة الجيدة والرفاه' => 'الهدف 3: الصحة الجيدة والرفاه',
    'الهدف 4: التعليم الجيد' => 'الهدف 4: التعليم الجيد',
    'الهدف 5: المساواة بين الجنسين' => 'الهدف 5: المساواة بين الجنسين',
    'الهدف 6: المياه النظيفة والنظافة الصحية' => 'الهدف 6: المياه النظيفة والنظافة الصحية',
    'الهدف 7: طاقة نظيفة وبأسعار معقولة' => 'الهدف 7: طاقة نظيفة وبأسعار معقولة',
    'الهدف 8: العمل اللائق ونمو الاقتصاد' => 'الهدف 8: العمل اللائق ونمو الاقتصاد',
    'الهدف 9: الصناعة والابتكار والهياكل الأساسية' => 'الهدف 9: الصناعة والابتكار والهياكل الأساسية',
    'الهدف 10: الحد من أوجه عدم المساواة' => 'الهدف 10: الحد من أوجه عدم المساواة',
    'الهدف 11: مدن ومجتمعات محلية مستدامة' => 'الهدف 11: مدن ومجتمعات محلية مستدامة',
    'الهدف 12: الاستهلاك والإنتاج المسؤولان' => 'الهدف 12: الاستهلاك والإنتاج المسؤولان',
    'الهدف 13: العمل المناخي' => 'الهدف 13: العمل المناخي',
    'الهدف 14: الحياة تحت الماء' => 'الهدف 14: الحياة تحت الماء',
    'الهدف 15: الحياة في البر' => 'الهدف 15: الحياة في البر',
    'الهدف 16: السلام والعدل والمؤسسات القوية' => 'الهدف 16: السلام والعدل والمؤسسات القوية',
    'الهدف 17: عقد الشراكات لتحقيق الأهداف' => 'الهدف 17: عقد الشراكات لتحقيق الأهداف',
  ],
  'en' => [
    'الهدف 1: القضاء على الفقر' => 'Goal 1: No Poverty',
    'الهدف 2: القضاء التام على الجوع' => 'Goal 2: Zero Hunger',
    'الهدف 3: الصحة الجيدة والرفاه' => 'Goal 3: Good Health and Well-being',
    'الهدف 4: التعليم الجيد' => 'Goal 4: Quality Education',
    'الهدف 5: المساواة بين الجنسين' => 'Goal 5: Gender Equality',
    'الهدف 6: المياه النظيفة والنظافة الصحية' => 'Goal 6: Clean Water and Sanitation',
    'الهدف 7: طاقة نظيفة وبأسعار معقولة' => 'Goal 7: Affordable and Clean Energy',
    'الهدف 8: العمل اللائق ونمو الاقتصاد' => 'Goal 8: Decent Work and Economic Growth',
    'الهدف 9: الصناعة والابتكار والهياكل الأساسية' => 'Goal 9: Industry, Innovation and Infrastructure',
    'الهدف 10: الحد من أوجه عدم المساواة' => 'Goal 10: Reduced Inequalities',
    'الهدف 11: مدن ومجتمعات محلية مستدامة' => 'Goal 11: Sustainable Cities and Communities',
    'الهدف 12: الاستهلاك والإنتاج المسؤولان' => 'Goal 12: Responsible Consumption and Production',
    'الهدف 13: العمل المناخي' => 'Goal 13: Climate Action',
    'الهدف 14: الحياة تحت الماء' => 'Goal 14: Life Below Water',
    'الهدف 15: الحياة في البر' => 'Goal 15: Life on Land',
    'الهدف 16: السلام والعدل والمؤسسات القوية' => 'Goal 16: Peace, Justice and Strong Institutions',
    'الهدف 17: عقد الشراكات لتحقيق الأهداف' => 'Goal 17: Partnerships for the Goals',
  ]
];

$countries = [
  'ar' => [
    'البحرين' => 'البحرين',
    'مصر' => 'مصر',
    'السعودية' => 'السعودية',
    'الإمارات' => 'الإمارات',
    'الكويت' => 'الكويت',
    'قطر' => 'قطر',
    'عمان' => 'عمان',
    'الأردن' => 'الأردن',
    'لبنان' => 'لبنان',
    'العراق' => 'العراق',
    'فلسطين' => 'فلسطين',
    'سوريا' => 'سوريا',
    'اليمن' => 'اليمن',
    'السودان' => 'السودان',
    'ليبيا' => 'ليبيا',
    'تونس' => 'تونس',
    'الجزائر' => 'الجزائر',
    'المغرب' => 'المغرب',
    'موريتانيا' => 'موريتانيا',
    'الصومال' => 'الصومال',
    'جيبوتي' => 'جيبوتي',
    'جزر القمر' => 'جزر القمر',
    'الولايات المتحدة' => 'الولايات المتحدة',
    'المملكة المتحدة' => 'المملكة المتحدة',
    'فرنسا' => 'فرنسا',
    'ألمانيا' => 'ألمانيا',
    'إيطاليا' => 'إيطاليا',
    'إسبانيا' => 'إسبانيا',
    'كندا' => 'كندا',
    'أستراليا' => 'أستراليا',
    'اليابان' => 'اليابان',
    'الصين' => 'الصين',
    'الهند' => 'الهند',
    'روسيا' => 'روسيا',
    'تركيا' => 'تركيا',
  ],
  'en' => [
    'البحرين' => 'Bahrain',
    'مصر' => 'Egypt',
    'السعودية' => 'Saudi Arabia',
    'الإمارات' => 'United Arab Emirates',
    'الكويت' => 'Kuwait',
    'قطر' => 'Qatar',
    'عمان' => 'Oman',
    'الأردن' => 'Jordan',
    'لبنان' => 'Lebanon',
    'العراق' => 'Iraq',
    'فلسطين' => 'Palestine',
    'سوريا' => 'Syria',
    'اليمن' => 'Yemen',
    'السودان' => 'Sudan',
    'ليبيا' => 'Libya',
    'تونس' => 'Tunisia',
    'الجزائر' => 'Algeria',
    'المغرب' => 'Morocco',
    'موريتانيا' => 'Mauritania',
    'الصومال' => 'Somalia',
    'جيبوتي' => 'Djibouti',
    'جزر القمر' => 'Comoros',
    'الولايات المتحدة' => 'United States',
    'المملكة المتحدة' => 'United Kingdom',
    'فرنسا' => 'France',
    'ألمانيا' => 'Germany',
    'إيطاليا' => 'Italy',
    'إسبانيا' => 'Spain',
    'كندا' => 'Canada',
    'أستراليا' => 'Australia',
    'اليابان' => 'Japan',
    'الصين' => 'China',
    'الهند' => 'India',
    'روسيا' => 'Russia',
    'تركيا' => 'Turkey',
  ]
];

$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $selectedSdgGoals = $_POST['sdg_goals'] ?? [];
  if (!is_array($selectedSdgGoals)) {
    $selectedSdgGoals = [];
  }

  $data = [
  'agreement_code' => posted('agreement_code'),
  'agreement_name' => posted('agreement_name'),
  'agreement_type' => posted('agreement_type'),
  'partner_entity' => posted('partner_entity'),
  'entity_type' => posted('entity_type'),
  'country' => posted('country'),
  'start_date' => posted('start_date'),
  'end_date' => posted('end_date'),
  'auto_renew' => posted('auto_renew'),
  'owner_entity' => posted('owner_entity'),
  'status' => posted('status'),
  'summary' => posted('summary'),
  'goals' => posted('goals'),
  'supports_sdg' => $_POST['supports_sdg'] ?? '',
  'sdg_goals' => implode(' | ', $selectedSdgGoals),
  // ✅ أهم سطر (هذا اللي ناقصك)
  'admin_status' => 'معتمد',

  // ✅ نخلي الوصف داخل notes_vppd بدل ما يخرب الأعمدة
  'notes_vppd' => posted('summary'),

  // ❌ احذفي هذول (يسببون المشكلة)
  // 'summary'
  // 'goals'
  // 'supports_sdg'
  // 'sdg_goals'

  'submitted_by' => $_SESSION['user_email'] ?? 'guest',
  'submitted_at' => date('Y-m-d H:i:s'),
];

  // Validation
  if ($data['agreement_code'] === '') {
    $errors[] = __('كود الاتفاقية مطلوب.', 'Agreement code is required.');
  }

  if ($data['agreement_name'] === '') {
    $errors[] = __('اسم الاتفاقية مطلوب.', 'Agreement name is required.');
  }

  if ($data['agreement_type'] === '') {
    $errors[] = __('نوع مشروع التعاون مطلوب.', 'Cooperation type is required.');
  }

  if ($data['partner_entity'] === '') {
    $errors[] = __('الجهة المتعاونة مطلوبة.', 'Partner organization is required.');
  }

  if ($data['entity_type'] === '') {
    $errors[] = __('نوع الجهة المتعاونة مطلوب.', 'Entity type is required.');
  }

  if ($data['country'] === '') {
    $errors[] = __('الدولة مطلوبة.', 'Country is required.');
  }

  if ($data['start_date'] === '') {
    $errors[] = __('تاريخ البدء مطلوب.', 'Start date is required.');
  }

  if ($data['end_date'] === '') {
    $errors[] = __('تاريخ الانتهاء مطلوب.', 'End date is required.');
  }

  if ($data['auto_renew'] === '') {
    $errors[] = __('يرجى اختيار حالة التجديد التلقائي.', 'Please select auto renewal status.');
  }

  if ($data['owner_entity'] === '') {
    $errors[] = __('الجهة المعنية بتنفيذ الاتفاقية مطلوبة.', 'Responsible unit is required.');
  }

  if ($data['status'] === '') {
    $errors[] = __('الحالة مطلوبة.', 'Status is required.');
  }

  $supports_sdg = $_POST['supports_sdg'] ?? '';

if ($supports_sdg === '') {
  $errors[] = __('يرجى تحديد ما إذا كانت الاتفاقية تدعم أهداف التنمية المستدامة.', 'Please specify whether the agreement supports the SDGs.');
}

if ($supports_sdg === 'نعم' && empty($selectedSdgGoals)) {
  $errors[] = __('يرجى اختيار هدف تنمية مستدامة واحد على الأقل.', 'Please select at least one SDG.');
}

  if (
    $data['start_date'] !== '' &&
    $data['end_date'] !== '' &&
    strtotime($data['end_date']) < strtotime($data['start_date'])
  ) {
    $errors[] = __('تاريخ الانتهاء يجب أن يكون بعد تاريخ البدء.', 'End date must be after start date.');
  }

  if (!$errors) {
    addAgreement($data);

   addNotification([
  'target_type' => 'all',
  'title' => $isArabic ? 'تمت إضافة اتفاقية جديدة' : 'New Agreement Added',
  'message' => $isArabic
    ? 'تمت إضافة اتفاقية جديدة بعنوان: ' . $data['agreement_name']
    : 'A new agreement has been added: ' . $data['agreement_name'],
  'type' => 'agreement',
  'reference_id' => $data['agreement_code'],
  'created_by' => $_SESSION['user_email'] ?? 'admin@uob.edu.bh'
   ]);

    $success = true;
    $_POST = [];
  }
}

$goalSuggestions = [];

$agreementsForGoals = readAgreements(false);

foreach ($agreementsForGoals as $ag) {
  $goalsText = trim($ag['goals'] ?? '');

  if ($goalsText === '') continue;

  $parts = preg_split('/\r\n|\r|\n|،|,|؛|;/', $goalsText);

  foreach ($parts as $p) {
    $p = trim($p);
    if ($p !== '' && mb_strlen($p) > 8) {
      $goalSuggestions[] = $p;
    }
  }
}

$goalSuggestions = array_count_values($goalSuggestions);
arsort($goalSuggestions);
$goalSuggestions = array_slice(array_keys($goalSuggestions), 0, 8);



require_once __DIR__ . '/../header.php';


?>
<style>
.agx-hero{
  padding:48px 0 72px !important;
  background:
    radial-gradient(800px 260px at 15% 0%, rgba(201,162,39,.25), transparent 60%),
    linear-gradient(135deg,#0b1f3a,#123c63);
  color:#fff;
}

.agx-hero-inner{
  width:min(1180px, calc(100% - 32px));
  margin:auto;
  display:grid;
  grid-template-columns:1.2fr .8fr;
  gap:24px;
  align-items:center;
}

.agx-hero h1{
  margin:0;
  font-size:clamp(34px,4vw,54px);
  font-weight:950;
}

.agx-hero p{
  margin:12px 0 0;
  color:rgba(255,255,255,.86);
  font-weight:800;
}

.agx-stats{
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:12px;
}

.agx-stat{
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.16);
  border-radius:22px;
  padding:18px;
  backdrop-filter:blur(10px);
}

.agx-stat span{
  display:block;
  color:rgba(255,255,255,.75);
  font-size:13px;
  font-weight:800;
}

.agx-stat strong{
  display:block;
  margin-top:5px;
  font-size:24px;
  font-weight:950;
}

.agx-shell{
  width:min(1180px, calc(100% - 32px));
  margin:-42px auto 50px;
  background:#fff;
  border:1px solid #e6ebf2;
  border-radius:30px;
  padding:30px;
  box-shadow:0 24px 65px rgba(2,8,23,.14);
  position:relative;
  z-index:3;
}

.agx-form-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:18px;
  padding-bottom:20px;
  border-bottom:1px solid #e6ebf2;
  margin-bottom:22px;
}

.agx-form-head h2{
  margin:0;
  color:#0b1f3a;
  font-size:clamp(28px,3vw,40px);
  font-weight:950;
}

.agx-form-head p{
  margin:8px 0 0;
  color:#64748b;
  font-weight:800;
}

.agx-icon{
  width:76px;
  height:76px;
  border-radius:24px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:32px;
  background:linear-gradient(180deg,#fff,#f7f9fd);
  border:1px solid #e6ebf2;
  box-shadow:0 12px 28px rgba(2,8,23,.08);
}

.agx-section-title{
  margin:12px 0 4px;
  padding:12px 15px;
  border-radius:16px;
  background:linear-gradient(180deg,rgba(11,31,58,.06),rgba(11,31,58,.03));
  border:1px solid rgba(11,31,58,.08);
  color:#0b1f3a;
  font-size:15px;
  font-weight:950;
}

.agx-label{
  display:block;
  margin-bottom:8px;
  color:#0b1f3a;
  font-size:14px;
  font-weight:900;
}

.agx-input{
  min-height:56px;
  border-radius:16px !important;
  border:1px solid #d9e3ef !important;
  background:#fbfdff !important;
  color:#0f172a !important;
  font-size:15px;
  font-weight:800;
  padding-inline:16px;
  box-shadow:none !important;
}

.agx-input:focus{
  background:#fff !important;
  border-color:#c9a227 !important;
  box-shadow:0 0 0 .22rem rgba(201,162,39,.18) !important;
}

textarea.agx-input{
  min-height:130px;
  padding-top:14px;
}

.agx-choice-grid,
.agx-check-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}

.agx-choice,
.agx-check{
  display:flex;
  align-items:flex-start;
  gap:10px;
  min-height:62px;
  padding:14px 16px;
  border-radius:18px;
  border:1px solid #dbe4ef;
  background:#fbfdff;
  color:#0f172a;
  font-weight:850;
  cursor:pointer;
}

.agx-choice:hover,
.agx-check:hover{
  background:#fff;
  border-color:rgba(201,162,39,.55);
}

.agx-choice input,
.agx-check input{
  width:18px;
  height:18px;
  accent-color:#0b1f3a;
  margin-top:3px;
}

.agx-help{
  margin:8px 0 12px;
  color:#64748b;
  font-size:13px;
  font-weight:800;
}

.agx-hidden{
  display:none !important;
}

.agx-actions{
  display:flex;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  padding-top:20px;
  margin-top:10px;
  border-top:1px solid #e6ebf2;
}

.agx-btn{
  min-width:160px;
  min-height:52px;
  border-radius:16px !important;
  font-weight:900 !important;
}

.agx-alert{
  width:min(1180px, calc(100% - 32px));
  margin:22px auto;
  border:none;
  border-radius:18px;
  box-shadow:0 12px 28px rgba(2,8,23,.08);
  font-weight:800;
}

@media(max-width:992px){
  .agx-hero-inner{grid-template-columns:1fr;}
  .agx-choice-grid,.agx-check-grid,.agx-stats{grid-template-columns:1fr;}
  .agx-shell{padding:22px 18px;}
}
.goal-chip{
  border:1px solid #dbe6f3;
  background:#f8fbff;
  color:#0b1f3a;
  padding:8px 14px;
  border-radius:999px;
  font-weight:900;
  cursor:pointer;
  transition:.2s;
}

.goal-chip:hover{
  background:#0b1f3a;
  color:#fff;
  border-color:#0b1f3a;
}

.goal-chip{
  border:1px solid #dbe6f3;
  background:#f8fbff;
  color:#0b1f3a;
  padding:8px 14px;
  border-radius:999px;
  font-weight:900;
  cursor:pointer;
  transition:.2s;
}

.goal-chip:hover{
  background:#0b1f3a;
  color:#fff;
  border-color:#0b1f3a;
}
</style>

<section class="agx-hero" dir="<?= $isArabic ? 'rtl' : 'ltr' ?>">
  <div class="agx-hero-inner">
    <div>
      <h1><?= h($T['page_title']) ?></h1>
      <p><?= h($T['form_text']) ?></p>
    </div>

    <div class="agx-stats">
      <div class="agx-stat">
        <span><?= h($T['agreement_code']) ?></span>
        <strong>UOB</strong>
      </div>
      <div class="agx-stat">
        <span><?= h($T['agreement_type']) ?></span>
        <strong>4</strong>
      </div>
      <div class="agx-stat">
        <span><?= h($T['country']) ?></span>
        <strong><?= count($countries[$lang]) ?></strong>
      </div>
      <div class="agx-stat">
        <span>SDGs</span>
        <strong>17</strong>
      </div>
    </div>
  </div>
</section>

<?php if ($success): ?>
  <div class="alert alert-success agx-alert">
    <?= h($T['success']) ?>
    <a href="../agreements.php?lang=<?= h($lang) ?>" class="fw-bold">
      <?= h($T['back_to_agreements']) ?>
    </a>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger agx-alert">
    <div class="fw-bold mb-2"><?= h($T['review_fields']) ?></div>
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="agx-shell" dir="<?= $isArabic ? 'rtl' : 'ltr' ?>">
  <div class="agx-form-head">
    <div>
      <h2><?= h($T['form_title']) ?></h2>
      
    </div>
   
  </div>

  <form method="post" class="row g-4">

    <div class="col-12">
      <div class="agx-section-title"><?= h($T['section_basic']) ?></div>
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['agreement_code']) ?></label>
      <input class="form-control agx-input" name="agreement_code"
        value="<?= h($_POST['agreement_code'] ?? '') ?>"
        placeholder="<?= h($T['placeholder_code']) ?>">
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['agreement_name']) ?></label>
      <input class="form-control agx-input" name="agreement_name"
        value="<?= h($_POST['agreement_name'] ?? '') ?>"
        placeholder="<?= h($T['placeholder_name']) ?>">
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['partner']) ?></label>
      <input class="form-control agx-input" name="partner_entity"
        value="<?= h($_POST['partner_entity'] ?? '') ?>"
        placeholder="<?= h($T['placeholder_partner']) ?>">
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['agreement_type']) ?></label>
      <select class="form-select agx-input" name="agreement_type">
        <option value=""><?= h($T['choose_agreement_type']) ?></option>
        <?php foreach ($agreementTypes[$lang] as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (($_POST['agreement_type'] ?? '') == $value ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['entity_type']) ?></label>
      <select class="form-select agx-input" name="entity_type">
        <option value=""><?= h($T['choose_entity_type']) ?></option>
        <?php foreach ($entityTypes[$lang] as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (($_POST['entity_type'] ?? '') == $value ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['country']) ?></label>
      <select class="form-select agx-input" name="country">
        <option value=""><?= h($T['choose_country']) ?></option>
        <?php foreach ($countries[$lang] as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (($_POST['country'] ?? '') == $value ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <div class="agx-section-title"><?= h($T['section_duration']) ?></div>
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['start_date']) ?></label>
      <input type="date" class="form-control agx-input" name="start_date"
        value="<?= h($_POST['start_date'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['end_date']) ?></label>
      <input type="date" class="form-control agx-input" name="end_date"
        value="<?= h($_POST['end_date'] ?? '') ?>">
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['auto_renew']) ?></label>
      <select class="form-select agx-input" name="auto_renew">
        <option value=""><?= h($T['choose']) ?></option>
        <?php foreach ($yesNo[$lang] as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (($_POST['auto_renew'] ?? '') == $value ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6">
      <label class="agx-label"><?= h($T['status']) ?></label>
      <select class="form-select agx-input" name="status">
        <option value=""><?= h($T['choose_status']) ?></option>
        <?php foreach ($statusOptions[$lang] as $value => $label): ?>
          <option value="<?= h($value) ?>" <?= (($_POST['status'] ?? '') == $value ? 'selected' : '') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="agx-label"><?= h($T['responsible_unit']) ?></label>
      <input class="form-control agx-input" name="owner_entity"
        value="<?= h($_POST['owner_entity'] ?? '') ?>"
        placeholder="<?= h($T['placeholder_unit']) ?>">
    </div>

    <div class="col-12">
      <div class="agx-section-title"><?= h($T['section_desc']) ?></div>
    </div>

    <div class="col-12">
      <label class="agx-label"><?= h($T['summary']) ?></label>
      <textarea class="form-control agx-input" name="summary" rows="5"
        placeholder="<?= h($T['placeholder_summary']) ?>"><?= h($_POST['summary'] ?? '') ?></textarea>
    </div>

    <div class="col-12">
  <label class="agx-label"><?= h($T['goals']) ?></label>

  <?php if (!empty($goalSuggestions)): ?>
    <div class="mb-2 d-flex flex-wrap gap-2" id="goalSuggestionBox">
      <?php foreach ($goalSuggestions as $goal): ?>
        <button type="button" class="goal-chip" data-goal="<?= h($goal) ?>">
          <?= h($goal) ?>
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <textarea class="form-control agx-input" name="goals" rows="5"
    placeholder="<?= h($T['placeholder_goals']) ?>"><?= h($_POST['goals'] ?? '') ?></textarea>
</div>



    <div class="col-12">
      <div class="agx-section-title"><?= h($T['section_sdg']) ?></div>
    </div>

    <div class="col-12">
      <label class="agx-label"><?= h($T['supports_sdg']) ?></label>

      <div class="agx-choice-grid">
        <label class="agx-choice">
          <input type="radio" name="supports_sdg" value="نعم" <?= (($_POST['supports_sdg'] ?? '') === 'نعم') ? 'checked' : '' ?>>
          <span><?= $isArabic ? 'نعم' : 'Yes' ?></span>
        </label>

        <label class="agx-choice">
          <input type="radio" name="supports_sdg" value="لا" <?= (($_POST['supports_sdg'] ?? '') === 'لا') ? 'checked' : '' ?>>
          <span><?= $isArabic ? 'لا' : 'No' ?></span>
        </label>
      </div>
    </div>

    <div class="col-12 agx-hidden" id="sdgGoalsWrap">
      <label class="agx-label"><?= h($T['choose_sdgs']) ?></label>
      <div class="agx-help"><?= h($T['multi_select']) ?></div>
         <button type="button" id="aiSuggestAgreementSdgBtn" class="btn btn-dark mb-3">
           🤖 اقتراح ذكي للأهداف
         </button>
      <div class="agx-check-grid">
        <?php foreach ($sdgGoals[$lang] as $value => $label): ?>
          <label class="agx-check">
            <input type="checkbox" name="sdg_goals[]" value="<?= h($value) ?>"
              <?= in_array($value, $_POST['sdg_goals'] ?? []) ? 'checked' : '' ?>>
            <span><?= h($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-12">
      <div class="agx-actions">
        <a class="btn btn-outline-secondary agx-btn" href="../agreements.php?lang=<?= h($lang) ?>">
          <?= h($T['cancel']) ?>
        </a>

        <button class="btn btn-primary agx-btn" type="submit">
          <?= h($T['save']) ?>
        </button>
      </div>
    </div>

  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const radios = document.querySelectorAll('input[name="supports_sdg"]');
  const sdgGoalsWrap = document.getElementById('sdgGoalsWrap');

  function toggleSdgGoals() {
    const checked = document.querySelector('input[name="supports_sdg"]:checked');
    const value = checked ? checked.value : '';

    if (value === 'نعم') {
      sdgGoalsWrap.classList.remove('agx-hidden');
    } else {
      sdgGoalsWrap.classList.add('agx-hidden');
      sdgGoalsWrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    }
  }

  radios.forEach(r => r.addEventListener('change', toggleSdgGoals));
  toggleSdgGoals();
});

const aiBtn = document.getElementById('aiSuggestAgreementSdgBtn');

if (aiBtn) {
  aiBtn.addEventListener('click', async function () {
    const name = document.querySelector('input[name="agreement_name"]')?.value || '';
    const summary = document.querySelector('textarea[name="summary"]')?.value || '';
    const goals = document.querySelector('textarea[name="goals"]')?.value || '';

    const text = (name + "\n" + summary + "\n" + goals).trim();

    if (!text) {
      alert('اكتبي اسم الاتفاقية أو النبذة أو الأهداف أولاً');
      return;
    }

    aiBtn.innerText = '⏳ جاري التحليل...';
    aiBtn.disabled = true;

    try {
      const res = await fetch('ai-sdg.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          title: name,
          desc: summary + "\n" + goals
        })
      });

      const data = await res.json();

      document.querySelectorAll('input[name="sdg_goals[]"]').forEach(cb => {
        cb.checked = false;
      });

      if (Array.isArray(data.sdgs)) {
        data.sdgs.forEach(num => {
          document.querySelectorAll('input[name="sdg_goals[]"]').forEach(cb => {
            const value = cb.value || '';
            if (
              value.includes('الهدف ' + num + ':') ||
              value.includes('Goal ' + num + ':') ||
              value.includes('SDG ' + num)
            ) {
              cb.checked = true;
            }
          });
        });
      }

      const yesRadio = document.querySelector('input[name="supports_sdg"][value="نعم"]');
      const sdgWrap = document.getElementById('sdgGoalsWrap');

      if (yesRadio) yesRadio.checked = true;
      if (sdgWrap) sdgWrap.classList.remove('agx-hidden');

    } catch (e) {
      alert('حدث خطأ أثناء التحليل. تأكدي أن ملف ai-sdg.php موجود ويعمل.');
    }

    aiBtn.innerText = '🤖 اقتراح ذكي للأهداف';
    aiBtn.disabled = false;
  });
}

document.querySelectorAll('.goal-chip').forEach(btn => {
  btn.addEventListener('click', () => {
    const textarea = document.querySelector('textarea[name="goals"]');
    const text = btn.dataset.goal || btn.innerText.trim();

    if (!textarea || !text) return;

    const existing = textarea.value
      .split(/\r?\n/)
      .map(x => x.trim())
      .filter(Boolean);

    if (!existing.includes(text)) {
      textarea.value = existing.length ? textarea.value.trim() + "\n" + text : text;
    }

    btn.remove();
  });
});
</script>


<?php require_once __DIR__ . '/../footer.php'; ?>
