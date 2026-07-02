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
  'دورة قصيرة',
  'برنامج تدريبي',
  'برنامج تدريبي / ورش عمل',
  'ورشة عمل',
  'برنامج تبادل',
  'بحث مشترك',
  'تعاون مؤسسي / تطوير سياسات',
];

$targetGroups = [
  'طلبة السنة الأخيرة',
  'الخريجون',
  'أعضاء هيئة التدريس',
  'مجتمع محلي',
  'طلبة الجامعة',
  'طلبة المدارس',
  'رواد أعمال ناشئون',
  'الباحثون',
];

$sdgGoals = [
  'SDG 1 - القضاء على الفقر',
  'SDG 2 - القضاء على الجوع',
  'SDG 3 - الصحة الجيدة والرفاه',
  'SDG 4 - التعليم الجيد',
  'SDG 5 - المساواة بين الجنسين',
  'SDG 6 - المياه النظيفة والنظافة الصحية',
  'SDG 7 - طاقة نظيفة وبأسعار معقولة',
  'SDG 8 - العمل اللائق ونمو الاقتصاد',
  'SDG 9 - الصناعة والابتكار والهياكل الأساسية',
  'SDG 10 - الحد من أوجه عدم المساواة',
  'SDG 11 - مدن ومجتمعات محلية مستدامة',
  'SDG 12 - الاستهلاك والإنتاج المسؤولان',
  'SDG 13 - العمل المناخي',
  'SDG 14 - الحياة تحت الماء',
  'SDG 15 - الحياة في البر',
  'SDG 16 - السلام والعدل والمؤسسات القوية',
  'SDG 17 - عقد الشراكات لتحقيق الأهداف',
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
  $initiativeType = postv('نوع المبادرة');

  $startDate = postv('تاريخ تنفيذ المبادرة');
  $endDate = postv('تاريخ انتهاء المبادرة');

  $locationMode = postv('location_mode');
  $outsideLocation = postv('outside_location');
  $locationValue = $locationMode;
  if ($locationMode === 'خارج الجامعة' && $outsideLocation !== '') {
    $locationValue .= ' - ' . $outsideLocation;
  }

  $description = postv('نبذة عن المبادرة وأهدافها');
  $selectedTargets = $_POST['target_groups'] ?? [];
  if (!is_array($selectedTargets)) $selectedTargets = [];
  $targetText = implode('، ', array_map('trim', $selectedTargets));

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
  $newsLink = ($published === 'نعم') ? postv('رابط خبر المبادرة') : '';
  $imagesLink = postv('رابط الصور / والأدلة');
  $outputs = postv('المخرجات التي تم تحقيقها ');
  $qsSupport = postv('هل تدعم QS؟');
  $greenMetricSupport = postv('هل تدعم GreenMetric؟');
  $notesEntity = postv('m_notes_entity');
  $notesVppd = postv('m_notes_vppd');

  // Validation errors with translation
  if ($isRelated === '') $errors[] = t('required_field');
  if ($isRelated === 'نعم' && $agreementCode === '') $errors[] = t('agreement_required');
  if ($title === '') $errors[] = t('title_required');
  if ($initiativeType === '') $errors[] = t('type_required');
  if ($entity === '') $errors[] = t('entity_required');
  if ($startDate === '') $errors[] = t('date_required');
  if ($locationMode === '') $errors[] = t('location_required');
  if ($locationMode === 'خارج الجامعة' && $outsideLocation === '') $errors[] = t('outside_location_required');
  if ($supportsSdg === 'نعم' && count($selectedSdgs) === 0) $errors[] = t('sdg_required');
  if ($published === 'نعم' && $newsLink === '') $errors[] = t('news_link_required');
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

<style>
/* =========================
   Premium Add Initiative Page
   ========================= */
.init-admin-hero{
  position:relative;
  overflow:hidden;
  min-height:290px;
  background:
    radial-gradient(800px 260px at 12% 10%, rgba(201,162,39,.28), transparent 60%),
    radial-gradient(900px 320px at 88% 10%, rgba(255,255,255,.08), transparent 45%),
    linear-gradient(135deg, #0b1f3a 0%, #102a4c 55%, #113c63 100%);
  border-bottom:1px solid rgba(255,255,255,.08);
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
  background: radial-gradient(circle, rgba(42,169,255,.18), transparent 68%);
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
  color:#fff;
  border-color:var(--uob-navy);
  background:linear-gradient(180deg, var(--uob-navy), var(--uob-navy-2));
  box-shadow:0 14px 30px rgba(11,31,58,.16);
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
.init-radio-card input{
  width:18px;
  height:18px;
  accent-color:var(--uob-navy);
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

  <form method="post" id="initiativeForm" novalidate>
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
          <label class="init-label"><?= t('related_agreement_question') ?></label>
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
          <label class="init-label"><?= t('select_agreement') ?></label>
          <select class="form-select init-input" name="_agreement_code" id="agreementSelect">
            <option value=""><?= t('select_placeholder') ?></option>
            <?php foreach ($agreements as $code => $a): ?>
              <option value="<?= h($code) ?>" <?= $selectedAgreementCode === $code ? 'selected' : '' ?>>
                <?= h($code) ?> — <?= h($a['اسم الاتفاقية'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="init-help"><?= t('agreement_select_help') ?></div>

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

        <div class="col-md-8">
          <label class="init-label"><?= t('initiative_title') ?></label>
          <input class="form-control init-input" name="عنوان المبادرة" value="<?= h($_POST['عنوان المبادرة'] ?? '') ?>" placeholder="<?= t('write_title') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('executing_entity') ?></label>
          <input class="form-control init-input" name="الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)" value="<?= h($_POST['الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)'] ?? '') ?>" placeholder="<?= t('example_entity') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('initiative_coordinator') ?></label>
          <input class="form-control init-input" name="منسق المبادرة" value="<?= h($_POST['منسق المبادرة'] ?? '') ?>" placeholder="<?= t('coordinator_name') ?>">
        </div>

        <div class="col-md-6">
          <label class="init-label"><?= t('initiative_type') ?></label>
          <select class="form-select init-input" name="نوع المبادرة">
            <option value=""><?= t('select_type') ?></option>
            <?php $vType = $_POST['نوع المبادرة'] ?? ''; ?>
            <?php foreach ($initiativeTypes as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $vType === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
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
          <label class="init-label"><?= t('location_question') ?></label>
          <?php $locationMode = $_POST['location_mode'] ?? ''; ?>
          <div class="init-choice-grid">
            <label class="init-radio-card">
              <input type="radio" name="location_mode" value="داخل الجامعة" <?= $locationMode === 'داخل الجامعة' ? 'checked' : '' ?>>
              <?= t('inside_university') ?>
            </label>
            <label class="init-radio-card">
              <input type="radio" name="location_mode" value="خارج الجامعة" <?= $locationMode === 'خارج الجامعة' ? 'checked' : '' ?>>
              <?= t('outside_university') ?>
            </label>
          </div>
        </div>

        <div class="col-12 <?= ($locationMode === 'خارج الجامعة') ? '' : 'init-hidden' ?>" id="outsideLocationWrap">
          <label class="init-label"><?= t('outside_location_label') ?></label>
          <input class="form-control init-input" name="outside_location" value="<?= h($_POST['outside_location'] ?? '') ?>" placeholder="<?= t('example_location') ?>">
        </div>
<!-- 🔥 الوصف + suggestions -->
    <div class="col-12">

      <label class="init-label"><?= t('description_objectives') ?></label>

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
          <label class="init-label"><?= t('target_group') ?></label>
          <div class="init-check-grid">
            <?php $selTargets = $_POST['target_groups'] ?? []; if (!is_array($selTargets)) $selTargets = []; ?>
            <?php foreach ($targetGroups as $tg): ?>
              <label class="init-check-card">
                <input type="checkbox" name="target_groups[]" value="<?= h($tg) ?>" <?= in_array($tg, $selTargets, true) ? 'checked' : '' ?>>
                <span><?= h($tg) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
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
        <div class="col-md-6">
          
        </div>

        <div class="col-md-6">
          
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
          <label class="init-label"><?= t('select_sdg_goals') ?></label>
          <div class="init-check-grid">
            <?php $selSdgs = $_POST['sdg_goals'] ?? []; if (!is_array($selSdgs)) $selSdgs = []; ?>
            <?php foreach ($sdgGoals as $goal): ?>
              
              <label class="init-check-card">
                <input type="checkbox" name="sdg_goals[]" value="<?= h($goal) ?>" <?= in_array($goal, $selSdgs, true) ? 'checked' : '' ?>>
                <span><?= h($goal) ?></span>
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
        

        <div class="col-12 <?= ($pub === 'نعم') ? '' : 'init-hidden' ?>" id="newsLinkWrap">
          <label class="init-label"><?= t('news_link') ?></label>
          <input class="form-control init-input" name="رابط خبر المبادرة" value="<?= h($_POST['رابط خبر المبادرة'] ?? '') ?>" placeholder="https://...">
        </div>

        <div class="col-12">
          <label class="init-label"><?= t('images_link') ?></label>
          <input class="form-control init-input" name="رابط الصور / والأدلة" value="<?= h($_POST['رابط الصور / والأدلة'] ?? '') ?>" placeholder="<?= t('google_drive_link') ?>">
        </div>

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

  const sdgRadios = document.querySelectorAll('input[name="supports_sdg"]');
  const sdgGoalsWrap = document.getElementById('sdgGoalsWrap');

  const publishRadios = document.querySelectorAll('input[name="هل نُشرت على موقع الجامعة؟"]');
  const newsLinkWrap = document.getElementById('newsLinkWrap');

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
    if(outsideLocationWrap){
      outsideLocationWrap.classList.toggle('init-hidden', value !== 'خارج الجامعة');
    }
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
      newsLinkWrap.classList.toggle('init-hidden', value !== 'نعم');
    }
  }

  relatedRadios.forEach(r => r.addEventListener('change', toggleAgreementWrap));
  locationRadios.forEach(r => r.addEventListener('change', toggleOutsideLocation));
  sdgRadios.forEach(r => r.addEventListener('change', toggleSdgGoals));
  publishRadios.forEach(r => r.addEventListener('change', toggleNewsLink));
  agreementSelect && agreementSelect.addEventListener('change', updateAgreementInfo);

  toggleAgreementWrap();
  toggleOutsideLocation();
  toggleSdgGoals();
  toggleNewsLink();
  updateAgreementInfo();
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

document.getElementById('aiSuggestBtn').addEventListener('click', async () => {

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

  // 4. نمسح التحديد القديم
  document.querySelectorAll('input[name="sdg_goals[]"]').forEach(cb => {
    cb.checked = false;
  });

  // 5. نحدد الجديد
  data.sdgs.forEach(num => {
    document.querySelectorAll('input[name="sdg_goals[]"]').forEach(cb => {
      if (cb.value.includes(`SDG ${num}`)) {
        cb.checked = true;
      }
    });
  });

  // 6. نرجع الزر طبيعي
  btn.innerText = "🤖 اقتراح ذكي للأهداف";
  btn.disabled = false;
});


</script>

<?php require_once __DIR__ . '/../footer.php'; ?>