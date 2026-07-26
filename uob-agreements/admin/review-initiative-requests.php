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
/* REVIEW INITIATIVE REQUESTS - CLEAN ADMIN DESIGN */

:root {
  --admin-navy: #0b1f3a;
  --admin-primary: #094fa3;
  --admin-gold: #c9a227;
  --admin-bg: #f6f8fc;
  --admin-border: #dfe7f2;
  --admin-muted: #64748b;
}

/* HERO */

.admin-table-hero {
  position: relative;
  background: #b89a68 !important;
  background-image:
    linear-gradient(135deg, #8f6f3f 0%, #b89a68 55%, #8f6f3f 100%) !important;
  color: #ffffff;
  padding: 58px 22px 95px;
  overflow: hidden;
}

.admin-table-hero-inner {
  max-width: 1320px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.1fr .9fr;
  gap: 28px;
  align-items: center;
}

.admin-table-hero h1 {
  margin: 0;
  font-size: clamp(36px, 4vw, 58px);
  font-weight: 950;
  letter-spacing: -.5px;
}

.admin-table-hero p {
  margin: 14px 0 0;
  max-width: 720px;
  color: rgba(255,255,255,.86);
  font-size: 18px;
  font-weight: 800;
  line-height: 1.9;
}


/* STATS */
.admin-stats-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.admin-stat-card {
  background: rgba(255,255,255,.13);
  border: 1px solid rgba(255,255,255,.20);
  border-radius: 22px;
  padding: 20px 22px;
  backdrop-filter: blur(10px);
  box-shadow: 0 16px 36px rgba(2,8,23,.18);
  cursor: pointer;
  transition: .22s ease;
}

.admin-stat-card:hover {
  transform: translateY(-4px);
  background: rgba(255,255,255,.18);
}

.admin-stat-card span {
  display: block;
  color: rgba(255,255,255,.76);
  font-size: 14px;
  font-weight: 850;
}

.admin-stat-card strong {
  display: block;
  margin-top: 4px;
  color: #ffffff;
  font-size: 34px;
  font-weight: 950;
}

/* MAIN SHELL */
.admin-shell {
  max-width: 1320px;
  margin: -58px auto 55px;
  padding: 0 22px;
  position: relative;
  z-index: 5;
}

.admin-panel {
  background: #ffffff;
  border: 1px solid var(--admin-border);
  border-radius: 30px;
  box-shadow: 0 28px 70px rgba(2,8,23,.13);
  overflow: hidden;
}

/* PANEL HEADER */
.admin-panel-head {
  padding: 28px 32px;
  background: #ffffff;
  border-bottom: 1px solid var(--admin-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  flex-wrap: wrap;
}

.admin-panel-title {
  margin: 0;
  color: var(--admin-navy);
  font-size: 30px;
  font-weight: 950;
}

.admin-panel-sub {
  margin-top: 6px;
  color: var(--admin-muted);
  font-size: 15px;
  font-weight: 800;
}

.admin-count-note {
  background: #eef4ff;
  color: var(--admin-primary);
  padding: 10px 16px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 950;
}

/* FILTERS */
.admin-tools {
  padding: 22px 32px;
  background: #fbfdff;
  border-bottom: 1px solid var(--admin-border);
}

.admin-filter-grid {
  display: grid;
  grid-template-columns: 2fr 1.15fr 1.15fr 1fr auto;
  gap: 14px;
  align-items: center;
}

.admin-input {
  min-height: 52px;
  border-radius: 16px !important;
  border: 1px solid #d7e0ed !important;
  background: #ffffff !important;
  color: var(--admin-navy) !important;
  font-size: 15px;
  font-weight: 850;
  padding: 0 16px !important;
  box-shadow: none !important;
}

.admin-input:focus {
  border-color: var(--admin-primary) !important;
  box-shadow: 0 0 0 .22rem rgba(9,79,163,.12) !important;
}

.admin-clear {
  min-height: 52px;
  border-radius: 16px !important;
  padding: 0 22px !important;
  font-weight: 950 !important;
  color: var(--admin-navy) !important;
  border: 1px solid #d7e0ed !important;
  background: #ffffff !important;
}

/* ALERT */
.admin-alert {
  border: 0;
  border-radius: 18px;
  box-shadow: 0 12px 26px rgba(2,8,23,.08);
  font-weight: 850;
  margin-bottom: 18px;
}

/* TABLE */
.admin-table-wrap {
  width: 100%;
  overflow: auto;
  background: #ffffff;
}

.admin-table {
  width: 100%;
  min-width: 1120px;
  border-collapse: separate;
  border-spacing: 0;
}

.admin-table thead th {
  background: var(--admin-navy);
  color: #ffffff;
  padding: 17px 16px;
  font-size: 14px;
  font-weight: 950;
  text-align: center;
  white-space: nowrap;
  border: 0;
}

.admin-table tbody td {
  padding: 20px 16px;
  border-bottom: 1px solid #edf2f7;
  color: #0f172a;
  font-size: 15px;
  font-weight: 800;
  line-height: 1.65;
  text-align: center;
  vertical-align: middle;
}

.admin-table tbody tr {
  transition: .18s ease;
}

.admin-table tbody tr:hover {
  background: #f8fbff;
}

.admin-title-cell {
  max-width: 280px;
  margin: 0 auto;
  color: var(--admin-navy);
  font-size: 17px;
  font-weight: 950;
  line-height: 1.65;
}

.admin-small {
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 800;
  margin-top: 4px;
}

/* BADGES */
.admin-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 105px;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 950;
  white-space: nowrap;
}

.admin-badge.pending {
  background: #fff7ed;
  color: #9a3412;
  border: 1px solid #fed7aa;
}

.admin-badge.approved {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.admin-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* ACTIONS */
.admin-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.admin-action-btn {
  border: 0;
  border-radius: 13px;
  min-height: 40px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 950;
  cursor: pointer;
  transition: .18s ease;
}

.admin-action-btn:hover {
  transform: translateY(-2px);
}

.admin-view-btn {
  background: #eef4ff;
  color: var(--admin-navy);
}

.admin-approve-btn {
  background: #16a34a;
  color: #ffffff;
}

.admin-reject-btn {
  background: #dc2626;
  color: #ffffff;
}

.admin-inline-form {
  display: inline;
}

/* EMPTY */
.admin-empty {
  padding: 40px;
  text-align: center;
  color: var(--admin-muted);
  font-weight: 950;
}

/* MODAL */
.admin-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: none;
}

.admin-modal.is-open {
  display: block;
}

.admin-modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(2,8,23,.58);
  backdrop-filter: blur(4px);
}

.admin-modal-panel {
  position: relative;
  max-width: 880px;
  max-height: 86vh;
  overflow: auto;
  background: #ffffff;
  margin: 6vh auto;
  border-radius: 28px;
  box-shadow: 0 35px 90px rgba(2,8,23,.32);
  padding: 28px;
}

.admin-modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  border-bottom: 1px solid #e8eef6;
  padding-bottom: 18px;
  margin-bottom: 18px;
}

.admin-modal-head h2 {
  margin: 0;
  color: var(--admin-navy);
  font-size: 26px;
  font-weight: 950;
  line-height: 1.5;
}

.admin-close {
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 14px;
  background: #eef4ff;
  color: var(--admin-navy);
  font-weight: 950;
  cursor: pointer;
}

/* MODAL DETAILS */
.admin-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.admin-detail-box {
  background: #fbfdff;
  border: 1px solid #e8eef6;
  border-radius: 18px;
  padding: 14px 16px;
}

.admin-detail-box span {
  display: block;
  color: var(--admin-muted);
  font-size: 12px;
  font-weight: 850;
  margin-bottom: 5px;
}

.admin-detail-box strong {
  display: block;
  color: #0f172a;
  font-weight: 950;
  line-height: 1.65;
}

.admin-description {
  margin-top: 14px;
  background: #fbfdff;
  border: 1px solid #e8eef6;
  border-radius: 18px;
  padding: 16px;
  color: #334155;
  font-weight: 850;
  line-height: 1.9;
  white-space: pre-wrap;
}

.admin-note-box {
  margin-top: 16px;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.admin-note-input {
  flex: 1;
  min-width: 260px;
  min-height: 46px;
  border-radius: 14px !important;
  border: 1px solid #d9e3ef !important;
  font-weight: 800;
}

.req-hidden {
  display: none !important;
}

/* RESPONSIVE */
@media (max-width: 992px) {
  .admin-table-hero-inner {
    grid-template-columns: 1fr;
  }

  .admin-filter-grid {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 768px) {
  .admin-filter-grid,
  .admin-detail-grid {
    grid-template-columns: 1fr;
  }

  .admin-panel-head {
    padding: 24px 22px;
  }

  .admin-tools {
    padding: 20px 22px;
  }

  .admin-modal-panel {
    margin: 3vh 12px;
    max-height: 92vh;
  }
}


@media (max-width: 520px) {
  .admin-stats-grid {
    grid-template-columns: 1fr;
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