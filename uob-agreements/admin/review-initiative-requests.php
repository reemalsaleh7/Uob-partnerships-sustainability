<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
  header("Location: ../login.php?to=admin/review-initiative-requests.php");
  exit;
}

if (($_SESSION['user_email'] ?? '') !== 'admin@uob.edu.bh') {
  die('غير مصرح لك بالدخول لهذه الصفحة.');
}

$pageTitle = 'مراجعة طلبات الموافقة على المبادرات';
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/../header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$requestsFile = __DIR__ . '/../data/initiative_requests.csv';
$structureFile = __DIR__ . '/../data/UOB_Colleges_Departments.csv';

$headers = [
  'request_id','submitted_by','full_name','email','college','department',
  'initiative_title','initiative_type','description','expected_date',
  'status','admin_notes','approved_by','approved_at','used','created_at'
];

function readCsvRowsAdmin($file) {
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

function writeRequestRowsAdmin($file, $headers, $rows) {
  $fp = fopen($file, 'w');
  fputcsv($fp, $headers);

  foreach ($rows as $r) {
    $line = [];
    foreach ($headers as $h) {
      $line[] = $r[$h] ?? '';
    }
    fputcsv($fp, $line);
  }

  fclose($fp);
}

function readUobStructureAdmin($file, $isArabic) {
  $structure = [];

  if (!file_exists($file)) return $structure;

  if (($fp = fopen($file, 'r')) !== false) {
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

        $college = $isArabic ? $collegeAr : $collegeEn;
        $department = $isArabic ? $deptAr : $deptEn;

        if ($college !== '' && $department !== '') {
          if (!isset($structure[$college])) $structure[$college] = [];
          if (!in_array($department, $structure[$college], true)) {
            $structure[$college][] = $department;
          }
        }
      }
    }

    fclose($fp);
  }

  foreach ($structure as &$departments) {
    sort($departments);
  }
  unset($departments);

  ksort($structure);
  return $structure;
}

function statusClassAdmin($status) {
  if ($status === 'approved') return 'success';
  if ($status === 'rejected') return 'danger';
  return 'warning';
}

$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $requestId = trim($_POST['request_id'] ?? '');
  $action = trim($_POST['action'] ?? '');
  $adminNotes = trim($_POST['admin_notes'] ?? '');

  $rows = readCsvRowsAdmin($requestsFile);

  foreach ($rows as &$r) {
    if (($r['request_id'] ?? '') === $requestId) {
      if ($action === 'approve') {
        $r['status'] = 'approved';
        $r['approved_by'] = $_SESSION['user_email'] ?? '';
        $r['approved_at'] = date('Y-m-d H:i:s');
        $r['admin_notes'] = $adminNotes;
      }

      if ($action === 'reject') {
        $r['status'] = 'rejected';
        $r['approved_by'] = $_SESSION['user_email'] ?? '';
        $r['approved_at'] = date('Y-m-d H:i:s');
        $r['admin_notes'] = $adminNotes;
      }

      break;
    }
  }
  unset($r);

  writeRequestRowsAdmin($requestsFile, $headers, $rows);
  $successMsg = $isArabic ? 'تم تحديث حالة الطلب بنجاح.' : 'Request status updated successfully.';
}

$requests = readCsvRowsAdmin($requestsFile);
$structure = readUobStructureAdmin($structureFile, $isArabic);

$total = count($requests);
$pending = 0;
$approved = 0;
$rejected = 0;

foreach ($requests as $r) {
  $st = trim($r['status'] ?? 'pending');
  if ($st === '') $st = 'pending';

  if ($st === 'pending') $pending++;
  elseif ($st === 'approved') $approved++;
  elseif ($st === 'rejected') $rejected++;
}

$statusLabels = [
  'pending' => $isArabic ? 'قيد المراجعة' : 'Pending',
  'approved' => $isArabic ? 'موافق عليه' : 'Approved',
  'rejected' => $isArabic ? 'مرفوض' : 'Rejected',
];

$allColleges = array_keys($structure);
$allDepartments = [];

foreach ($structure as $college => $departments) {
  foreach ($departments as $dep) {
    $allDepartments[$dep] = true;
  }
}

foreach ($requests as $r) {
  $c = trim($r['college'] ?? '');
  $d = trim($r['department'] ?? '');
  if ($c !== '' && !in_array($c, $allColleges, true)) $allColleges[] = $c;
  if ($d !== '') $allDepartments[$d] = true;
}

sort($allColleges);
$allDepartments = array_keys($allDepartments);
sort($allDepartments);
?>

<style>
.admin-table-hero{
  position:relative;
  overflow:hidden;
  background:
    radial-gradient(850px 280px at 12% 5%, rgba(201,162,39,.28), transparent 60%),
    radial-gradient(900px 320px at 90% 5%, rgba(255,255,255,.09), transparent 45%),
    linear-gradient(135deg, #0b1f3a 0%, #102a4c 55%, #113c63 100%);
  color:#fff;
  padding:54px 20px 78px;
}

.admin-table-hero-inner{
  max-width:1220px;
  margin:auto;
  display:grid;
  grid-template-columns:1.1fr .9fr;
  gap:24px;
  align-items:center;
}

.admin-table-hero h1{
  margin:0;
  font-size:clamp(34px,4vw,56px);
  font-weight:950;
}

.admin-table-hero p{
  margin:14px 0 0;
  color:rgba(255,255,255,.86);
  font-weight:800;
  line-height:1.9;
}

.admin-stats-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}

.admin-stat-card{
  border:1px solid rgba(255,255,255,.16);
  background:rgba(255,255,255,.12);
  border-radius:22px;
  padding:17px 18px;
  backdrop-filter:blur(10px);
  box-shadow:0 16px 36px rgba(2,8,23,.16);
  cursor:pointer;
  transition:.18s ease;
}

.admin-stat-card:hover{
  transform:translateY(-2px);
  background:rgba(255,255,255,.16);
}

.admin-stat-card span{
  display:block;
  color:rgba(255,255,255,.72);
  font-size:13px;
  font-weight:850;
}

.admin-stat-card strong{
  display:block;
  color:#fff;
  font-size:30px;
  font-weight:950;
  margin-top:4px;
}

.admin-shell{
  max-width:1220px;
  margin:-45px auto 48px;
  padding:0 20px;
  position:relative;
  z-index:5;
}

.admin-panel{
  background:#fff;
  border:1px solid rgba(230,235,242,.96);
  border-radius:28px;
  box-shadow:0 24px 60px rgba(2,8,23,.12);
  overflow:hidden;
}

.admin-panel-head{
  padding:22px 24px;
  border-bottom:1px solid rgba(230,235,242,.95);
  background:linear-gradient(180deg,#fff,#f8fbff);
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  flex-wrap:wrap;
}

.admin-panel-title{
  color:var(--uob-navy);
  font-size:24px;
  font-weight:950;
  margin:0;
}

.admin-panel-sub{
  color:#64748b;
  font-size:13px;
  font-weight:800;
  margin-top:4px;
}

.admin-tools{
  padding:18px 24px;
  border-bottom:1px solid rgba(230,235,242,.95);
  background:#fbfdff;
}

.admin-filter-grid{
  display:grid;
  grid-template-columns:2fr 1.2fr 1.2fr 1fr auto;
  gap:12px;
  align-items:end;
}

.admin-input{
  min-height:48px;
  border-radius:14px !important;
  border:1px solid #d9e3ef !important;
  background:#fff !important;
  color:#0f172a !important;
  font-weight:800;
}

.admin-input:focus{
  border-color:rgba(201,162,39,.75) !important;
  box-shadow:0 0 0 .2rem rgba(201,162,39,.14) !important;
}

.admin-clear{
  min-height:48px;
  border-radius:14px !important;
  font-weight:900;
  white-space:nowrap;
}

.admin-alert{
  border:none;
  border-radius:18px;
  box-shadow:0 12px 28px rgba(2,8,23,.08);
  font-weight:850;
  margin-bottom:18px;
}

.admin-table-wrap{
  width:100%;
  overflow:auto;
}

.admin-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width:1100px;
}

.admin-table thead th{
  position:sticky;
  top:0;
  z-index:2;
  background:#0b1f3a;
  color:#fff;
  padding:14px 14px;
  font-size:13px;
  font-weight:950;
  white-space:nowrap;
}

.admin-table tbody td{
  padding:14px;
  border-bottom:1px solid #e8eef6;
  color:#0f172a;
  font-weight:800;
  vertical-align:middle;
}

.admin-table tbody tr{
  transition:.15s ease;
}

.admin-table tbody tr:hover{
  background:#f8fbff;
}

.admin-title-cell{
  max-width:260px;
  color:var(--uob-navy);
  font-weight:950;
  line-height:1.55;
}

.admin-small{
  font-size:12px;
  color:#64748b;
  font-weight:800;
}

.admin-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:6px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:950;
  white-space:nowrap;
}

.admin-badge.pending{
  background:#fff7ed;
  color:#9a3412;
  border:1px solid #fed7aa;
}

.admin-badge.approved{
  background:#dcfce7;
  color:#166534;
  border:1px solid #bbf7d0;
}

.admin-badge.rejected{
  background:#fee2e2;
  color:#991b1b;
  border:1px solid #fecaca;
}

.admin-actions{
  display:flex;
  align-items:center;
  gap:8px;
  flex-wrap:wrap;
}

.admin-action-btn{
  border:none;
  border-radius:12px;
  min-height:38px;
  padding:8px 12px;
  font-weight:950;
  font-size:13px;
  cursor:pointer;
  transition:.15s ease;
}

.admin-view-btn{
  background:#eef4ff;
  color:#0b1f3a;
}

.admin-view-btn:hover{
  background:#dbeafe;
}

.admin-approve-btn{
  background:#16a34a;
  color:#fff;
}

.admin-approve-btn:hover{
  background:#15803d;
}

.admin-reject-btn{
  background:#dc2626;
  color:#fff;
}

.admin-reject-btn:hover{
  background:#b91c1c;
}

.admin-inline-form{
  display:inline;
}

.admin-empty{
  padding:34px;
  text-align:center;
  color:#64748b;
  font-weight:950;
}

.admin-count-note{
  color:#64748b;
  font-weight:850;
  font-size:13px;
}

.admin-modal{
  position:fixed;
  inset:0;
  z-index:9999;
  display:none;
}

.admin-modal.is-open{
  display:block;
}

.admin-modal-backdrop{
  position:absolute;
  inset:0;
  background:rgba(2,8,23,.55);
  backdrop-filter:blur(3px);
}

.admin-modal-panel{
  position:relative;
  max-width:850px;
  max-height:86vh;
  overflow:auto;
  background:#fff;
  margin:6vh auto;
  border-radius:26px;
  box-shadow:0 30px 80px rgba(2,8,23,.28);
  padding:24px;
}

.admin-modal-head{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:14px;
  border-bottom:1px solid #e8eef6;
  padding-bottom:16px;
  margin-bottom:16px;
}

.admin-modal-head h2{
  margin:0;
  color:var(--uob-navy);
  font-weight:950;
  line-height:1.5;
}

.admin-close{
  border:none;
  background:#eef4ff;
  color:#0b1f3a;
  width:42px;
  height:42px;
  border-radius:14px;
  font-weight:950;
  cursor:pointer;
}

.admin-detail-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}

.admin-detail-box{
  background:#fbfdff;
  border:1px solid #e8eef6;
  border-radius:16px;
  padding:12px 14px;
}

.admin-detail-box span{
  display:block;
  color:#64748b;
  font-size:12px;
  font-weight:850;
  margin-bottom:4px;
}

.admin-detail-box strong{
  display:block;
  color:#0f172a;
  font-weight:950;
  line-height:1.6;
}

.admin-description{
  margin-top:12px;
  background:#fbfdff;
  border:1px solid #e8eef6;
  border-radius:16px;
  padding:14px;
  color:#334155;
  font-weight:850;
  line-height:1.9;
  white-space:pre-wrap;
}

.admin-note-box{
  margin-top:14px;
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.admin-note-input{
  flex:1;
  min-width:260px;
  min-height:46px;
  border-radius:14px !important;
  border:1px solid #d9e3ef !important;
  font-weight:800;
}

.req-hidden{
  display:none !important;
}

@media(max-width:992px){
  .admin-table-hero-inner{
    grid-template-columns:1fr;
  }

  .admin-filter-grid{
    grid-template-columns:1fr 1fr;
  }
}

@media(max-width:768px){
  .admin-filter-grid,
  .admin-detail-grid{
    grid-template-columns:1fr;
  }

  .admin-modal-panel{
    margin:3vh 12px;
    max-height:92vh;
  }

  .admin-stats-grid{
    grid-template-columns:1fr 1fr;
  }
}

@media(max-width:520px){
  .admin-stats-grid{
    grid-template-columns:1fr;
  }
}
</style>

<section class="admin-table-hero">
  <div class="admin-table-hero-inner">
    <div>
      <h1><?= $isArabic ? 'طلبات موافقة المبادرات' : 'Initiative Approval Requests' ?></h1>
      <p>
        <?= $isArabic
          ? 'لوحة إدارية منظمة لمراجعة طلبات المبادرات حسب الكليات والأقسام، مع البحث والتصفية والإجراءات السريعة.'
          : 'A structured admin panel for reviewing initiative requests by colleges and departments with search, filters, and quick actions.'
        ?>
      </p>
    </div>

    <div class="admin-stats-grid">
      <div class="admin-stat-card" data-status-click="">
        <span><?= $isArabic ? 'كل الطلبات' : 'All Requests' ?></span>
        <strong><?= (int)$total ?></strong>
      </div>
      <div class="admin-stat-card" data-status-click="pending">
        <span><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?></span>
        <strong><?= (int)$pending ?></strong>
      </div>
      <div class="admin-stat-card" data-status-click="approved">
        <span><?= $isArabic ? 'موافق عليها' : 'Approved' ?></span>
        <strong><?= (int)$approved ?></strong>
      </div>
      <div class="admin-stat-card" data-status-click="rejected">
        <span><?= $isArabic ? 'مرفوضة' : 'Rejected' ?></span>
        <strong><?= (int)$rejected ?></strong>
      </div>
    </div>
  </div>
</section>

<div class="admin-shell">

  <?php if ($successMsg): ?>
    <div class="alert alert-success admin-alert">
      <?= h($successMsg) ?>
    </div>
  <?php endif; ?>

  <div class="admin-panel">
    <div class="admin-panel-head">
      <div>
        <h2 class="admin-panel-title">
          <?= $isArabic ? 'جدول الطلبات' : 'Requests Table' ?>
        </h2>
        <div class="admin-panel-sub">
          <?= $isArabic ? 'استخدم الفلاتر للوصول السريع لأي طلب.' : 'Use filters to quickly find any request.' ?>
        </div>
      </div>

      <div class="admin-count-note">
        <span id="visibleCount"><?= (int)$total ?></span>
        <?= $isArabic ? 'طلب ظاهر' : 'visible requests' ?>
      </div>
    </div>

    <div class="admin-tools">
      <div class="admin-filter-grid">
        <input id="adminSearchInput" class="form-control admin-input" type="search"
          placeholder="<?= $isArabic ? 'بحث: رقم الطلب، العنوان، الاسم، البريد...' : 'Search: request ID, title, name, email...' ?>">

        <select id="adminCollegeFilter" class="form-select admin-input">
          <option value=""><?= $isArabic ? 'كل الكليات' : 'All Colleges' ?></option>
          <?php foreach ($allColleges as $college): ?>
            <option value="<?= h($college) ?>"><?= h($college) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="adminDepartmentFilter" class="form-select admin-input">
          <option value=""><?= $isArabic ? 'كل الأقسام' : 'All Departments' ?></option>
          <?php foreach ($allDepartments as $dep): ?>
            <option value="<?= h($dep) ?>"><?= h($dep) ?></option>
          <?php endforeach; ?>
        </select>

        <select id="adminStatusFilter" class="form-select admin-input">
          <option value=""><?= $isArabic ? 'كل الحالات' : 'All Statuses' ?></option>
          <option value="pending"><?= h($statusLabels['pending']) ?></option>
          <option value="approved"><?= h($statusLabels['approved']) ?></option>
          <option value="rejected"><?= h($statusLabels['rejected']) ?></option>
        </select>

        <button type="button" id="adminClearFilters" class="btn btn-outline-secondary admin-clear">
          <?= $isArabic ? 'مسح' : 'Clear' ?>
        </button>
      </div>
    </div>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th><?= $isArabic ? 'رقم الطلب' : 'Request ID' ?></th>
            <th><?= $isArabic ? 'عنوان المبادرة' : 'Initiative Title' ?></th>
            <th><?= $isArabic ? 'الكلية' : 'College' ?></th>
            <th><?= $isArabic ? 'القسم' : 'Department' ?></th>
            <th><?= $isArabic ? 'مقدم الطلب' : 'Applicant' ?></th>
            <th><?= $isArabic ? 'الحالة' : 'Status' ?></th>
            <th><?= $isArabic ? 'التاريخ المتوقع' : 'Expected Date' ?></th>
            <th><?= $isArabic ? 'إجراءات' : 'Actions' ?></th>
          </tr>
        </thead>

        <tbody id="adminRequestsBody">
          <?php if (!$requests): ?>
            <tr>
              <td colspan="8" class="admin-empty">
                <?= $isArabic ? 'لا توجد طلبات حالياً.' : 'No requests found.' ?>
              </td>
            </tr>
          <?php endif; ?>

          <?php foreach ($requests as $index => $r): ?>
            <?php
              $status = trim($r['status'] ?? 'pending');
              if ($status === '') $status = 'pending';

              $college = trim($r['college'] ?? '');
              $department = trim($r['department'] ?? '');

              $searchText = mb_strtolower(
                ($r['request_id'] ?? '') . ' ' .
                ($r['initiative_title'] ?? '') . ' ' .
                ($r['full_name'] ?? '') . ' ' .
                ($r['email'] ?? '') . ' ' .
                ($college ?? '') . ' ' .
                ($department ?? '') . ' ' .
                ($r['initiative_type'] ?? '') . ' ' .
                ($r['description'] ?? '')
              );
            ?>

            <tr
              class="admin-request-row"
              data-search="<?= h($searchText) ?>"
              data-status="<?= h($status) ?>"
              data-college="<?= h($college) ?>"
              data-department="<?= h($department) ?>"
            >
              <td>
                <strong><?= h($r['request_id'] ?? '—') ?></strong>
                <div class="admin-small"><?= h($r['created_at'] ?? '') ?></div>
              </td>

              <td>
                <div class="admin-title-cell"><?= h($r['initiative_title'] ?? '—') ?></div>
                <div class="admin-small"><?= h($r['initiative_type'] ?? '—') ?></div>
              </td>

              <td><?= h($college ?: '—') ?></td>
              <td><?= h($department ?: '—') ?></td>

              <td>
                <strong><?= h($r['full_name'] ?? '—') ?></strong>
                <div class="admin-small"><?= h($r['email'] ?? '—') ?></div>
              </td>

              <td>
                <span class="admin-badge <?= h($status) ?>">
                  <?= h($statusLabels[$status] ?? $status) ?>
                </span>
              </td>

              <td><?= h($r['expected_date'] ?? '—') ?></td>

              <td>
                <div class="admin-actions">
                  <button
                    type="button"
                    class="admin-action-btn admin-view-btn"
                    data-view='<?= h(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'
                  >
                    <?= $isArabic ? 'عرض' : 'View' ?>
                  </button>

                  <?php if ($status === 'pending'): ?>
                    <form method="post" class="admin-inline-form">
                      <input type="hidden" name="request_id" value="<?= h($r['request_id'] ?? '') ?>">
                      <input type="hidden" name="admin_notes" value="">
                      <button class="admin-action-btn admin-approve-btn" type="submit" name="action" value="approve">
                        <?= $isArabic ? 'قبول' : 'Approve' ?>
                      </button>
                    </form>

                    <form method="post" class="admin-inline-form">
                      <input type="hidden" name="request_id" value="<?= h($r['request_id'] ?? '') ?>">
                      <input type="hidden" name="admin_notes" value="">
                      <button class="admin-action-btn admin-reject-btn" type="submit" name="action" value="reject">
                        <?= $isArabic ? 'رفض' : 'Reject' ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="admin-modal" id="requestModal" aria-hidden="true">
  <div class="admin-modal-backdrop" id="modalBackdrop"></div>

  <div class="admin-modal-panel">
    <div class="admin-modal-head">
      <div>
        <h2 id="modalTitle">—</h2>
        <div class="admin-small" id="modalId">—</div>
      </div>
      <button class="admin-close" type="button" id="modalClose">✕</button>
    </div>

    <div class="admin-detail-grid">
      <div class="admin-detail-box">
        <span><?= $isArabic ? 'مقدم الطلب' : 'Applicant' ?></span>
        <strong id="modalApplicant">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'البريد الإلكتروني' : 'Email' ?></span>
        <strong id="modalEmail">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'الكلية' : 'College' ?></span>
        <strong id="modalCollege">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'القسم' : 'Department' ?></span>
        <strong id="modalDepartment">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'نوع المبادرة' : 'Initiative Type' ?></span>
        <strong id="modalType">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'التاريخ المتوقع' : 'Expected Date' ?></span>
        <strong id="modalDate">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'الحالة' : 'Status' ?></span>
        <strong id="modalStatus">—</strong>
      </div>

      <div class="admin-detail-box">
        <span><?= $isArabic ? 'تاريخ التقديم' : 'Submitted At' ?></span>
        <strong id="modalCreated">—</strong>
      </div>
    </div>

    <div class="admin-description" id="modalDescription">—</div>
    <div class="admin-description" id="modalNotesBox" style="display:none;"></div>

    <div class="admin-note-box" id="modalActionBox">
      <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
        <input type="hidden" name="request_id" id="modalRequestId">
        <input class="form-control admin-note-input" name="admin_notes"
          placeholder="<?= $isArabic ? 'ملاحظات الأدمن اختياري' : 'Admin notes optional' ?>">

        <button class="admin-action-btn admin-approve-btn" type="submit" name="action" value="approve">
          <?= $isArabic ? 'قبول الطلب' : 'Approve Request' ?>
        </button>

        <button class="admin-action-btn admin-reject-btn" type="submit" name="action" value="reject">
          <?= $isArabic ? 'رفض الطلب' : 'Reject Request' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
const isArabic = <?= $isArabic ? 'true' : 'false' ?>;
const statusLabels = <?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const searchInput = document.getElementById('adminSearchInput');
const collegeFilter = document.getElementById('adminCollegeFilter');
const departmentFilter = document.getElementById('adminDepartmentFilter');
const statusFilter = document.getElementById('adminStatusFilter');
const clearBtn = document.getElementById('adminClearFilters');
const visibleCount = document.getElementById('visibleCount');

function applyAdminFilters(){
  const q = (searchInput.value || '').toLowerCase().trim();
  const college = collegeFilter.value;
  const department = departmentFilter.value;
  const status = statusFilter.value;

  let count = 0;

  document.querySelectorAll('.admin-request-row').forEach(row => {
    const matchSearch = !q || (row.dataset.search || '').includes(q);
    const matchCollege = !college || row.dataset.college === college;
    const matchDepartment = !department || row.dataset.department === department;
    const matchStatus = !status || row.dataset.status === status;

    const show = matchSearch && matchCollege && matchDepartment && matchStatus;
    row.classList.toggle('req-hidden', !show);

    if(show) count++;
  });

  if(visibleCount) visibleCount.textContent = count;
}

searchInput.addEventListener('input', applyAdminFilters);
collegeFilter.addEventListener('change', applyAdminFilters);
departmentFilter.addEventListener('change', applyAdminFilters);
statusFilter.addEventListener('change', applyAdminFilters);

clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  collegeFilter.value = '';
  departmentFilter.value = '';
  statusFilter.value = '';
  applyAdminFilters();
});

document.querySelectorAll('[data-status-click]').forEach(card => {
  card.addEventListener('click', () => {
    statusFilter.value = card.dataset.statusClick || '';
    applyAdminFilters();
    document.querySelector('.admin-panel')?.scrollIntoView({behavior:'smooth', block:'start'});
  });
});

const modal = document.getElementById('requestModal');
const modalBackdrop = document.getElementById('modalBackdrop');
const modalClose = document.getElementById('modalClose');

function setText(id, value){
  const el = document.getElementById(id);
  if(el) el.textContent = value && String(value).trim() !== '' ? value : '—';
}

function openRequestModal(data){
  setText('modalTitle', data.initiative_title);
  setText('modalId', data.request_id);
  setText('modalApplicant', data.full_name);
  setText('modalEmail', data.email);
  setText('modalCollege', data.college);
  setText('modalDepartment', data.department);
  setText('modalType', data.initiative_type);
  setText('modalDate', data.expected_date);
  setText('modalCreated', data.created_at);
  setText('modalDescription', data.description);

  const status = data.status || 'pending';
  setText('modalStatus', statusLabels[status] || status);

  const requestIdInput = document.getElementById('modalRequestId');
  if(requestIdInput) requestIdInput.value = data.request_id || '';

  const actionBox = document.getElementById('modalActionBox');
  if(actionBox) actionBox.style.display = status === 'pending' ? 'flex' : 'none';

  const notesBox = document.getElementById('modalNotesBox');
  if(notesBox){
    if(data.admin_notes && String(data.admin_notes).trim() !== ''){
      notesBox.style.display = 'block';
      notesBox.textContent = (isArabic ? 'ملاحظات الأدمن: ' : 'Admin Notes: ') + data.admin_notes;
    }else{
      notesBox.style.display = 'none';
      notesBox.textContent = '';
    }
  }

  modal.classList.add('is-open');
  modal.setAttribute('aria-hidden','false');
  document.body.style.overflow = 'hidden';
}

function closeRequestModal(){
  modal.classList.remove('is-open');
  modal.setAttribute('aria-hidden','true');
  document.body.style.overflow = '';
}

document.querySelectorAll('.admin-view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    try{
      const data = JSON.parse(btn.dataset.view || '{}');
      openRequestModal(data);
    }catch(e){
      alert(isArabic ? 'حدث خطأ في عرض التفاصيل.' : 'Error showing details.');
    }
  });
});

modalBackdrop.addEventListener('click', closeRequestModal);
modalClose.addEventListener('click', closeRequestModal);

document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape') closeRequestModal();
});

applyAdminFilters();
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>