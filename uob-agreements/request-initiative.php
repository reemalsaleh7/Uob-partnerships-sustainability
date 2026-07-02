<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
  header("Location: login.php?to=request-initiative.php");
  exit;
}

$pageTitle = "طلب موافقة مبادرة";
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');


$structureFile = __DIR__ . '/data/UOB_Colleges_Departments.csv';
$uobStructure = [];
$collegeOptions = [];

if (file_exists($structureFile) && ($fp = fopen($structureFile, 'r')) !== false) {
  $header = null;

  while (($row = fgetcsv($fp)) !== false) {
    if (isset($row[0]) && trim($row[0]) === 'اسم الكلية بالعربي') {
      $header = $row;
      break;
    }
  }

  if ($header) {
    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($header), '');
      $data = array_combine($header, $row);

      $collegeAr = trim($data['اسم الكلية بالعربي'] ?? '');
      $collegeEn = trim($data['College Name in English'] ?? '');
      $deptAr = trim($data['اسم القسم بالعربي'] ?? '');
      $deptEn = trim($data['Department Name in English'] ?? '');

      $collegeName = $isArabic ? $collegeAr : $collegeEn;
      $deptName = $isArabic ? $deptAr : $deptEn;

      if ($collegeName !== '' && $deptName !== '') {
        if (!isset($uobStructure[$collegeName])) {
          $uobStructure[$collegeName] = [];
        }
        $uobStructure[$collegeName][] = $deptName;
      }
    }
  }

  fclose($fp);
}

$collegeOptions = array_keys($uobStructure);
sort($collegeOptions);

$errors = [];
$success = false;

function reqv(string $key): string {
  return trim($_POST[$key] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fullName = reqv('full_name');
  $email = $_SESSION['user_email'] ?? '';
  $college = reqv('college');
  $department = reqv('department');
  $title = reqv('initiative_title');
  $type = reqv('initiative_type');
  $description = reqv('description');
  $expectedDate = reqv('expected_date');

  if ($fullName === '') $errors[] = $isArabic ? 'الاسم الكامل مطلوب.' : 'Full name is required.';
  if ($college === '') $errors[] = $isArabic ? 'الكلية مطلوبة.' : 'College is required.';
  if ($title === '') $errors[] = $isArabic ? 'عنوان المبادرة مطلوب.' : 'Initiative title is required.';
  if ($description === '') $errors[] = $isArabic ? 'وصف المبادرة مطلوب.' : 'Description is required.';

  if (!$errors) {
    $file = __DIR__ . '/data/initiative_requests.csv';

    if (!file_exists($file) || filesize($file) === 0) {
      $fp = fopen($file, 'w');
      fputcsv($fp, [
        'request_id','submitted_by','full_name','email','college','department',
        'initiative_title','initiative_type','description','expected_date',
        'status','admin_notes','approved_by','approved_at','used','created_at'
      ]);
      fclose($fp);
    }

    $requestId = 'REQ-' . date('YmdHis');

    $fp = fopen($file, 'a');
    fputcsv($fp, [
      $requestId,
      $email,
      $fullName,
      $email,
      $college,
      $department,
      $title,
      $type,
      $description,
      $expectedDate,
      'pending',
      '',
      '',
      '',
      '0',
      date('Y-m-d H:i:s')
    ]);
    fclose($fp);

    $success = true;
    $_POST = [];
  }
}
?>

<style>
.request-hero{
  position:relative;
  overflow:hidden;
  min-height:290px;
  background:
    radial-gradient(800px 260px at 12% 10%, rgba(201,162,39,.28), transparent 60%),
    radial-gradient(900px 320px at 88% 10%, rgba(255,255,255,.08), transparent 45%),
    linear-gradient(135deg, #0b1f3a 0%, #102a4c 55%, #113c63 100%);
  border-bottom:1px solid rgba(255,255,255,.08);
}

.request-hero-inner{
  position:relative;
  z-index:2;
  max-width:1180px;
  margin-inline:auto;
  padding:55px 20px 62px;
  display:grid;
  grid-template-columns: 1.1fr .9fr;
  gap:24px;
  align-items:center;
}

.request-copy h1{
  color:#fff;
  font-size:clamp(34px, 4vw, 56px);
  font-weight:950;
  margin:0;
}

.request-copy p{
  color:rgba(255,255,255,.86);
  font-weight:800;
  line-height:1.9;
  margin-top:14px;
  max-width:720px;
}

.request-stats{
  display:grid;
  grid-template-columns:repeat(2, minmax(0,1fr));
  gap:12px;
}

.request-stat{
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.14);
  border-radius:22px;
  padding:18px;
  backdrop-filter:blur(10px);
  box-shadow:0 16px 34px rgba(2,8,23,.14);
}

.request-stat span{
  display:block;
  color:rgba(255,255,255,.72);
  font-size:13px;
  font-weight:800;
}

.request-stat strong{
  display:block;
  color:#fff;
  font-size:22px;
  font-weight:950;
  margin-top:5px;
}

.request-shell{
  max-width:1180px;
  margin:-35px auto 40px;
  position:relative;
  z-index:5;
  background:rgba(255,255,255,.97);
  border:1px solid rgba(230,235,242,.96);
  border-radius:28px;
  box-shadow:0 24px 60px rgba(2,8,23,.12);
  padding:30px;
}

.request-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  padding-bottom:18px;
  margin-bottom:22px;
  border-bottom:1px solid rgba(230,235,242,.95);
}

.request-top h2{
  color:var(--uob-navy);
  font-size:clamp(28px, 3vw, 40px);
  font-weight:950;
  margin:0;
}

.request-top p{
  margin:8px 0 0;
  color:var(--muted);
  font-weight:800;
}

.request-icon{
  width:82px;
  height:82px;
  border-radius:24px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:34px;
  background:linear-gradient(180deg,#fff,#f7f9fd);
  border:1px solid rgba(230,235,242,.95);
  box-shadow:0 12px 28px rgba(2,8,23,.08);
}

.request-section-title{
  margin:0 0 16px;
  padding:12px 14px;
  border-radius:16px;
  border:1px solid rgba(11,31,58,.08);
  background:linear-gradient(180deg, rgba(11,31,58,.05), rgba(11,31,58,.025));
  color:var(--uob-navy);
  font-size:15px;
  font-weight:950;
}

.request-label{
  display:block;
  color:var(--uob-navy);
  font-size:14px;
  font-weight:900;
  margin-bottom:8px;
}

.request-input{
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

.request-input:focus{
  background:#fff !important;
  border-color:rgba(201,162,39,.75) !important;
  box-shadow:0 0 0 .22rem rgba(201,162,39,.16) !important;
}

textarea.request-input{
  min-height:150px;
  padding-top:14px;
}

.request-actions{
  display:flex;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  margin-top:24px;
  padding-top:18px;
  border-top:1px solid rgba(230,235,242,.95);
}

.request-btn{
  min-width:170px;
  min-height:52px;
  border-radius:16px !important;
  font-weight:900;
}

.request-alert{
  max-width:1180px;
  margin:24px auto 55px;
  position: relative;
  z-index: 20;

  border:none;
  border-radius:18px;
  box-shadow:0 12px 28px rgba(2,8,23,.08);
  font-weight:800;
}

@media(max-width: 992px){
  .request-hero-inner{grid-template-columns:1fr;}
  .request-stats{grid-template-columns:1fr 1fr;}
  .request-shell{margin-top:-25px;padding:22px 18px;}
}

@media(max-width: 576px){
  .request-stats{grid-template-columns:1fr;}
  .request-top{flex-direction:column;}
  .request-btn{width:100%;}
}
</style>

<section class="request-hero">
  <div class="request-hero-inner">
    <div class="request-copy">
      <h1><?= $isArabic ? 'طلب موافقة مبادرة' : 'Initiative Approval Request' ?></h1>
      <p>
        <?= $isArabic
          ? 'قبل إضافة مبادرة جديدة، يرجى تقديم طلب موافقة ليتم مراجعته من قبل المسؤولين. بعد الموافقة ستحصل على رقم موافقة لاستخدامه في إضافة المبادرة.'
          : 'Before adding a new initiative, please submit an approval request for review. Once approved, you will receive an approval number to add the initiative.'
        ?>
      </p>
    </div>

    <div class="request-stats">
      <div class="request-stat">
        <span><?= $isArabic ? 'الخطوة الأولى' : 'Step One' ?></span>
        <strong><?= $isArabic ? 'طلب الموافقة' : 'Request' ?></strong>
      </div>
      <div class="request-stat">
        <span><?= $isArabic ? 'الخطوة الثانية' : 'Step Two' ?></span>
        <strong><?= $isArabic ? 'مراجعة الأدمن' : 'Admin Review' ?></strong>
      </div>
      <div class="request-stat">
        <span><?= $isArabic ? 'الخطوة الثالثة' : 'Step Three' ?></span>
        <strong><?= $isArabic ? 'رقم موافقة' : 'Approval ID' ?></strong>
      </div>
      <div class="request-stat">
        <span><?= $isArabic ? 'الخطوة الرابعة' : 'Step Four' ?></span>
        <strong><?= $isArabic ? 'إضافة المبادرة' : 'Add Initiative' ?></strong>
      </div>
    </div>
  </div>
</section>

<?php if ($success): ?>
  <div class="alert alert-success request-alert">
    <?= $isArabic ? 'تم إرسال طلب الموافقة بنجاح. رقم الطلب:' : 'Approval request submitted successfully. Request ID:' ?>
    <strong><?= h($requestId ?? '') ?></strong>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger request-alert">
    <ul class="mb-0">
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="request-shell">
  <div class="request-top">
    <div>
      <h2><?= $isArabic ? 'نموذج طلب الموافقة' : 'Approval Request Form' ?></h2>
      <p><?= $isArabic ? 'يرجى تعبئة البيانات الأساسية للمبادرة المقترحة.' : 'Please fill in the basic details of the proposed initiative.' ?></p>
    </div>
    
  </div>

  <form method="post">
    <div class="request-section-title">
      <?= $isArabic ? 'بيانات مقدم الطلب والمبادرة' : 'Applicant and Initiative Information' ?>
    </div>

    <div class="row g-4">
      <div class="col-md-6">
        <label class="request-label"><?= $isArabic ? 'الاسم الكامل' : 'Full Name' ?></label>
        <input class="form-control request-input" name="full_name" value="<?= h($_POST['full_name'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="request-label"><?= $isArabic ? 'البريد الإلكتروني' : 'Email' ?></label>
        <input class="form-control request-input" value="<?= h($_SESSION['user_email'] ?? '') ?>" disabled>
      </div>

      <div class="col-md-6">
  <label class="request-label"><?= $isArabic ? 'الكلية / العمادة / الإدارة' : 'College / Deanship / Department' ?></label>
  <?php $selectedCollege = $_POST['college'] ?? ''; ?>
  <select class="form-select request-input" name="college" id="collegeSelect">
    <option value=""><?= $isArabic ? 'اختر الكلية' : 'Select College' ?></option>
    <?php foreach ($collegeOptions as $college): ?>
      <option value="<?= h($college) ?>" <?= $selectedCollege === $college ? 'selected' : '' ?>>
        <?= h($college) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

      <div class="col-md-6">
  <label class="request-label"><?= $isArabic ? 'القسم' : 'Department' ?></label>
  <?php $selectedDepartment = $_POST['department'] ?? ''; ?>
  <select class="form-select request-input" name="department" id="departmentSelect">
    <option value=""><?= $isArabic ? 'اختر القسم' : 'Select Department' ?></option>
  </select>
</div>

      <div class="col-md-8">
        <label class="request-label"><?= $isArabic ? 'عنوان المبادرة المقترحة' : 'Proposed Initiative Title' ?></label>
        <input class="form-control request-input" name="initiative_title" value="<?= h($_POST['initiative_title'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="request-label"><?= $isArabic ? 'نوع المبادرة' : 'Initiative Type' ?></label>
        <select class="form-select request-input" name="initiative_type">
          <?php $type = $_POST['initiative_type'] ?? ''; ?>
          <option value=""><?= $isArabic ? 'اختر النوع' : 'Select type' ?></option>
          <?php foreach (['ورشة عمل','برنامج تدريبي','فعالية','بحث مشترك','تعاون مؤسسي','أخرى'] as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $type === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="request-label"><?= $isArabic ? 'تاريخ التنفيذ المتوقع' : 'Expected Implementation Date' ?></label>
        <input type="date" class="form-control request-input" name="expected_date" value="<?= h($_POST['expected_date'] ?? '') ?>">
      </div>

      <div class="col-12">
        <label class="request-label"><?= $isArabic ? 'وصف المبادرة وسبب طلب الموافقة' : 'Initiative Description and Approval Justification' ?></label>
        <textarea class="form-control request-input" name="description"><?= h($_POST['description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="request-actions">
      <a class="btn btn-outline-secondary request-btn" href="initiatives.php?lang=<?= urlencode($lang) ?>">
        <?= $isArabic ? 'إلغاء' : 'Cancel' ?>
      </a>

      <button class="btn btn-primary request-btn" type="submit">
        <?= $isArabic ? 'إرسال طلب الموافقة' : 'Submit Request' ?>
      </button>
    </div>
  </form>
</div>


<script>
const UOB_STRUCTURE = <?= json_encode($uobStructure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const selectedDepartment = <?= json_encode($_POST['department'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

const collegeSelect = document.getElementById('collegeSelect');
const departmentSelect = document.getElementById('departmentSelect');

function fillDepartments() {
  const college = collegeSelect.value;
  const departments = UOB_STRUCTURE[college] || [];

  departmentSelect.innerHTML = `<option value=""><?= $isArabic ? 'اختر القسم' : 'Select Department' ?></option>`;

  departments.forEach(dep => {
    const option = document.createElement('option');
    option.value = dep;
    option.textContent = dep;

    if (dep === selectedDepartment) {
      option.selected = true;
    }

    departmentSelect.appendChild(option);
  });
}

collegeSelect.addEventListener('change', fillDepartments);
fillDepartments();
</script>



<?php require_once __DIR__ . '/footer.php'; ?>