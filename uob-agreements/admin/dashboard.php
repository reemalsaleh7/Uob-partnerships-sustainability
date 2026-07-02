<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SESSION['user_email'] ?? '') !== 'admin@uob.edu.bh') {
  header("Location: ../login.php");
  exit;
}

$pageTitle = "لوحة الأدمن";
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/../header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$agreements = array_values(readAgreements(false));
$initiatives = array_values(loadAllInitiatives(false));

/* =========================
   Initiative Requests
========================= */
$requestsFile = __DIR__ . '/../data/initiative_requests.csv';
$requests = [];

if (file_exists($requestsFile) && ($fp = fopen($requestsFile, 'r')) !== false) {
  $header = fgetcsv($fp);
  if ($header) {
    while (($row = fgetcsv($fp)) !== false) {
      $row = array_pad($row, count($header), '');
      $requests[] = array_combine($header, $row);
    }
  }
  fclose($fp);
}

$reqPending = $reqApproved = $reqRejected = 0;

foreach ($requests as $r) {
  $st = trim($r['status'] ?? 'pending');
  if ($st === 'pending' || str_contains($st, 'قيد')) $reqPending++;
  elseif ($st === 'approved' || $st === 'معتمد') $reqApproved++;
  elseif ($st === 'rejected' || $st === 'مرفوض') $reqRejected++;
}

/* =========================
   Initiatives Counts
========================= */
$initPending = $initApproved = $initRejected = 0;
$departmentActivity = [];
$sdgActivity = [];

foreach ($initiatives as $it) {
  $st = trim($it['status'] ?? 'قيد المراجعة');

  if (str_contains($st, 'قيد')) $initPending++;
  elseif ($st === 'معتمد' || $st === 'approved') $initApproved++;
  elseif ($st === 'مرفوض' || $st === 'rejected') $initRejected++;

  $entity = trim($it['entity'] ?? '');
  if ($entity !== '') {
    $departmentActivity[$entity] = ($departmentActivity[$entity] ?? 0) + 1;
  }

  $sdgText = trim(($it['sdg_primary'] ?? '') . ' ' . ($it['sdg_secondary'] ?? '') . ' ' . ($it['sdgs'] ?? ''));

  for ($i = 1; $i <= 17; $i++) {
    if (mb_stripos($sdgText, 'SDG ' . $i) !== false || preg_match('/\b' . $i . '\b/u', $sdgText)) {
      $sdgActivity[$i] = ($sdgActivity[$i] ?? 0) + 1;
    }
  }
}

arsort($departmentActivity);
arsort($sdgActivity);

$topDepartments = array_slice($departmentActivity, 0, 6, true);

$missingSdgs = [];
for ($i = 1; $i <= 17; $i++) {
  if (empty($sdgActivity[$i])) $missingSdgs[] = $i;
}

/* =========================
   UOB Structure for inactive departments
========================= */
$structureFile = __DIR__ . '/../data/UOB_Colleges_Departments.csv';
$uobDepartments = [];

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

      $college = $isArabic
        ? trim($data['اسم الكلية بالعربي'] ?? '')
        : trim($data['College Name in English'] ?? '');

      $department = $isArabic
        ? trim($data['اسم القسم بالعربي'] ?? '')
        : trim($data['Department Name in English'] ?? '');

      if ($college !== '' && $department !== '') {
        $uobDepartments[$college][$department] = 0;
      }
    }
  }

  fclose($fp);
}

$collegeActivity = [];

foreach ($initiatives as $it) {
  $entity = trim($it['entity'] ?? '');

  foreach ($uobDepartments as $college => $deps) {
    foreach ($deps as $dep => $count) {
      if ($entity !== '' && (mb_stripos($entity, $dep) !== false || mb_stripos($dep, $entity) !== false)) {
        $uobDepartments[$college][$dep]++;
        $collegeActivity[$college] = ($collegeActivity[$college] ?? 0) + 1;
      }
    }
  }
}

$inactiveDepartments = [];

foreach ($uobDepartments as $college => $deps) {
  foreach ($deps as $dep => $count) {
    if ((int)$count === 0) {
      $inactiveDepartments[] = [
        'college' => $college,
        'department' => $dep
      ];
    }
  }
}

arsort($collegeActivity);
$topColleges = array_slice($collegeActivity, 0, 6, true);

/* =========================
   Agreements Analytics
========================= */
$agActive = $agExpired = $agWithInit = $agNoInit = $agEndingSoon = 0;

$initiativeByAgreement = [];

foreach ($initiatives as $it) {
  $code = trim((string)($it['agreement_code'] ?? ''));
  if ($code !== '') {
    $initiativeByAgreement[$code] = ($initiativeByAgreement[$code] ?? 0) + 1;
  }
}

$ownerCounts = [];
$ownerNoInitCounts = [];
$countryCounts = [];
$partnerCounts = [];
$typeCounts = [];
$endingSoonList = [];
$noInitiativeList = [];

$today = strtotime(date('Y-m-d'));
$soonLimit = strtotime('+6 months');

foreach ($agreements as $ag) {
  $code = trim((string)($ag['agreement_code'] ?? ''));
  $name = trim((string)($ag['agreement_name'] ?? ''));
  $status = trim((string)($ag['status'] ?? ''));
  $owner = trim((string)($ag['owner_entity'] ?? ''));
  $country = trim((string)($ag['country'] ?? ''));
  $partner = trim((string)($ag['partner_entity'] ?? ''));
  $type = trim((string)($ag['agreement_type'] ?? ''));
  $endDate = trim((string)($ag['end_date'] ?? ''));

  if ($status === 'سارية' || strtolower($status) === 'active') $agActive++;
  if ($status === 'منتهية' || strtolower($status) === 'expired') $agExpired++;

  if ($owner !== '') $ownerCounts[$owner] = ($ownerCounts[$owner] ?? 0) + 1;
  if ($country !== '') $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
  if ($partner !== '') $partnerCounts[$partner] = ($partnerCounts[$partner] ?? 0) + 1;
  if ($type !== '') $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

  if (!empty($initiativeByAgreement[$code])) {
    $agWithInit++;
  } else {
    $agNoInit++;

    if ($owner !== '') {
      $ownerNoInitCounts[$owner] = ($ownerNoInitCounts[$owner] ?? 0) + 1;
    }

    $noInitiativeList[] = [
      'code' => $code,
      'name' => $name,
      'owner' => $owner
    ];
  }

  $endTs = strtotime(str_replace('/', '-', $endDate));
  if ($endTs && $endTs >= $today && $endTs <= $soonLimit) {
    $agEndingSoon++;

    $endingSoonList[] = [
      'code' => $code,
      'name' => $name,
      'end_date' => $endDate
    ];
  }
}

arsort($ownerCounts);
arsort($ownerNoInitCounts);
arsort($countryCounts);
arsort($partnerCounts);
arsort($typeCounts);

$topOwners = array_slice($ownerCounts, 0, 6, true);
$topOwnersNoInit = array_slice($ownerNoInitCounts, 0, 6, true);
$topCountries = array_slice($countryCounts, 0, 6, true);
$topPartners = array_slice($partnerCounts, 0, 6, true);
$topTypes = array_slice($typeCounts, 0, 6, true);

$totalPending = $reqPending + $initPending;
?>

<style>
.admin-dashboard-hero{
  background:
    radial-gradient(850px 280px at 12% 5%, rgba(201,162,39,.28), transparent 60%),
    linear-gradient(135deg,#0b1f3a 0%,#102a4c 55%,#113c63 100%);
  color:#fff;
  padding:58px 20px 88px;
}

.admin-dashboard-inner{
  max-width:1220px;
  margin:auto;
  display:grid;
  grid-template-columns:1.1fr .9fr;
  gap:24px;
  align-items:center;
}

.admin-dashboard-hero h1{
  font-size:clamp(36px,4vw,58px);
  font-weight:950;
  margin:0;
}

.admin-dashboard-hero p{
  margin-top:14px;
  color:rgba(255,255,255,.86);
  font-weight:800;
  line-height:1.9;
}

.admin-main-stats{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:12px;
}

.admin-main-stat{
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.16);
  border-radius:22px;
  padding:18px;
}

.admin-main-stat span{
  display:block;
  color:rgba(255,255,255,.72);
  font-size:13px;
  font-weight:850;
}

.admin-main-stat strong{
  display:block;
  font-size:32px;
  font-weight:950;
  margin-top:4px;
}

.admin-dashboard-shell{
  max-width:1220px;
  margin:-48px auto 50px;
  padding:0 20px;
  position:relative;
  z-index:5;
}

.admin-dashboard-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:18px;
}

.admin-dash-card,
.admin-analytics-section,
.admin-quick-section{
  background:#fff;
  border:1px solid rgba(230,235,242,.96);
  border-radius:28px;
  box-shadow:0 18px 44px rgba(2,8,23,.08);
  overflow:hidden;
}

.admin-dash-head{
  padding:22px;
  border-bottom:1px solid #e8eef6;
  background:linear-gradient(180deg,#fff,#f8fbff);
}

.admin-dash-head h2,
.admin-analytics-section h2,
.admin-quick-section h2{
  margin:0;
  color:#0b1f3a;
  font-size:24px;
  font-weight:950;
}

.admin-dash-head p,
.admin-analytics-section p{
  margin:8px 0 0;
  color:#64748b;
  font-weight:800;
}

.admin-dash-body{
  padding:20px 22px;
}

.admin-mini-row{
  display:flex;
  justify-content:space-between;
  padding:12px 0;
  border-bottom:1px solid #eef2f7;
  font-weight:900;
}

.admin-mini-row:last-child{border-bottom:none;}

.admin-pill{
  min-width:52px;
  text-align:center;
  border-radius:999px;
  padding:6px 12px;
  font-weight:950;
}

.pending{background:#fff7ed;color:#9a3412;}
.approved{background:#dcfce7;color:#166534;}
.rejected{background:#fee2e2;color:#991b1b;}
.info-pill{background:#eef4ff;color:#0b1f3a;}

.admin-card-actions{
  padding:18px 22px 22px;
}

.admin-action-btn{
  display:flex;
  justify-content:center;
  align-items:center;
  min-height:48px;
  border-radius:16px;
  background:#0b1f3a;
  color:#fff;
  text-decoration:none;
  font-weight:950;
}

.admin-action-btn:hover{
  color:#fff;
  background:#113c63;
}

.admin-analytics-section,
.admin-quick-section{
  margin-top:22px;
  padding:24px;
}

.analytics-header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:16px;
  flex-wrap:wrap;
  margin-bottom:18px;
}

.analytics-badge{
  background:#eef4ff;
  color:#0b1f3a;
  border:1px solid #dbe6f3;
  padding:9px 14px;
  border-radius:999px;
  font-weight:950;
}

.analytics-title-strip{
  margin:24px 0 14px;
  padding:14px 18px;
  border-radius:18px;
  background:linear-gradient(135deg,#0b1f3a,#113c63);
  color:#fff;
  font-size:20px;
  font-weight:950;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.analytics-title-strip span{
  font-size:13px;
  opacity:.8;
}

.admin-analytics-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:16px;
}

.analytics-box{
  background:#f8fbff;
  border:1px solid #e8eef6;
  border-radius:22px;
  padding:18px;
  min-height:240px;
}

.analytics-box h3{
  color:#0b1f3a;
  font-size:18px;
  font-weight:950;
  margin-bottom:14px;
}

.analytics-list{
  display:flex;
  flex-direction:column;
  gap:10px;
}

.analytics-item{
  background:#fff;
  border:1px solid #e8eef6;
  border-radius:14px;
  padding:10px 12px;
  font-weight:850;
  color:#0f172a;
}

.analytics-item small{
  display:block;
  color:#64748b;
  margin-top:3px;
}

.analytics-count{
  display:inline-flex;
  min-width:34px;
  justify-content:center;
  background:#eef4ff;
  color:#0b1f3a;
  border-radius:999px;
  padding:4px 10px;
  font-weight:950;
  margin-inline-start:6px;
}

.sdg-missing-wrap{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.sdg-missing-pill{
  background:#fee2e2;
  color:#991b1b;
  border:1px solid #fecaca;
  border-radius:999px;
  padding:7px 12px;
  font-weight:950;
  font-size:13px;
}

.admin-quick-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:14px;
  margin-top:16px;
}

.admin-quick-link{
  text-decoration:none;
  background:#f8fbff;
  border:1px solid #e8eef6;
  border-radius:20px;
  padding:18px;
  color:#0b1f3a;
  font-weight:950;
}

.admin-quick-link:hover{
  background:#eef4ff;
}

@media(max-width:992px){
  .admin-dashboard-inner,
  .admin-dashboard-grid,
  .admin-analytics-grid,
  .admin-quick-grid{
    grid-template-columns:1fr;
  }

  .admin-main-stats{
    grid-template-columns:1fr 1fr;
  }
}

@media(max-width:576px){
  .admin-main-stats{
    grid-template-columns:1fr;
  }
}
</style>

<section class="admin-dashboard-hero">
  <div class="admin-dashboard-inner">
    <div>
      <h1><?= $isArabic ? 'لوحة الأدمن' : 'Admin Dashboard' ?></h1>
      <p>
        <?= $isArabic
          ? 'لوحة مركزية لمتابعة المبادرات، طلبات الموافقة، والاتفاقيات مع تحليلات تساعد في معرفة النشاط والنواقص والفرص.'
          : 'A central dashboard for initiatives, approval requests, and agreements with analytics for activity, gaps, and opportunities.'
        ?>
      </p>
    </div>

    <div class="admin-main-stats">
      <div class="admin-main-stat">
        <span><?= $isArabic ? 'طلبات تحتاج مراجعة' : 'Pending Items' ?></span>
        <strong><?= (int)$totalPending ?></strong>
      </div>

      <div class="admin-main-stat">
        <span><?= $isArabic ? 'إجمالي المبادرات' : 'Total Initiatives' ?></span>
        <strong><?= count($initiatives) ?></strong>
      </div>

      <div class="admin-main-stat">
        <span><?= $isArabic ? 'إجمالي الاتفاقيات' : 'Total Agreements' ?></span>
        <strong><?= count($agreements) ?></strong>
      </div>

      <div class="admin-main-stat">
        <span><?= $isArabic ? 'اتفاقيات بدون مبادرات' : 'Agreements Without Initiatives' ?></span>
        <strong><?= (int)$agNoInit ?></strong>
      </div>
    </div>
  </div>
</section>

<div class="admin-dashboard-shell">

  <div class="admin-dashboard-grid">

    <div class="admin-dash-card">
      <div class="admin-dash-head">
        <h2>📨 <?= $isArabic ? 'طلبات موافقة المبادرات' : 'Initiative Requests' ?></h2>
        <p><?= $isArabic ? 'طلبات قبل إضافة المبادرة' : 'Requests before adding initiatives' ?></p>
      </div>

      <div class="admin-dash-body">
        <div class="admin-mini-row"><span><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?></span><span class="admin-pill pending"><?= $reqPending ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'مقبولة' : 'Approved' ?></span><span class="admin-pill approved"><?= $reqApproved ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'مرفوضة' : 'Rejected' ?></span><span class="admin-pill rejected"><?= $reqRejected ?></span></div>
      </div>

      <div class="admin-card-actions">
        <a class="admin-action-btn" href="review-initiative-requests.php?lang=<?= h($lang) ?>">
          <?= $isArabic ? 'فتح الصفحة' : 'Open Page' ?>
        </a>
      </div>
    </div>

    <div class="admin-dash-card">
      <div class="admin-dash-head">
        <h2>📊 <?= $isArabic ? 'مراجعة المبادرات' : 'Review Initiatives' ?></h2>
        <p><?= $isArabic ? 'اعتماد ورفض المبادرات' : 'Approve and reject initiatives' ?></p>
      </div>

      <div class="admin-dash-body">
        <div class="admin-mini-row"><span><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?></span><span class="admin-pill pending"><?= $initPending ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'معتمدة' : 'Approved' ?></span><span class="admin-pill approved"><?= $initApproved ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'مرفوضة' : 'Rejected' ?></span><span class="admin-pill rejected"><?= $initRejected ?></span></div>
      </div>

      <div class="admin-card-actions">
        <a class="admin-action-btn" href="review-initiatives.php?lang=<?= h($lang) ?>">
          <?= $isArabic ? 'فتح الصفحة' : 'Open Page' ?>
        </a>
      </div>
    </div>

    <div class="admin-dash-card">
      <div class="admin-dash-head">
        <h2>📄 <?= $isArabic ? 'إدارة الاتفاقيات' : 'Manage Agreements' ?></h2>
        <p><?= $isArabic ? 'عرض وتحليل الاتفاقيات' : 'View and analyze agreements' ?></p>
      </div>

      <div class="admin-dash-body">
        <div class="admin-mini-row"><span><?= $isArabic ? 'سارية' : 'Active' ?></span><span class="admin-pill approved"><?= $agActive ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'منتهية' : 'Expired' ?></span><span class="admin-pill rejected"><?= $agExpired ?></span></div>
        <div class="admin-mini-row"><span><?= $isArabic ? 'تنتهي قريباً' : 'Ending Soon' ?></span><span class="admin-pill pending"><?= $agEndingSoon ?></span></div>
      </div>

      <div class="admin-card-actions">
        <a class="admin-action-btn" href="review-agreements.php?tab=all&lang=<?= h($lang) ?>">
          <?= $isArabic ? 'فتح الصفحة' : 'Open Page' ?>
        </a>
      </div>
    </div>

  </div>

  <div class="admin-analytics-section">
    

    <div class="analytics-title-strip">
      📊 <?= $isArabic ? 'تحليلات المبادرات' : 'Initiative Analytics' ?>
      <span><?= count($initiatives) ?> <?= $isArabic ? 'مبادرة' : 'initiatives' ?></span>
    </div>

    <div class="admin-analytics-grid">

      <div class="analytics-box">
        <h3>🏆 <?= $isArabic ? 'أكثر الجهات نشاطًا' : 'Most Active Units' ?></h3>
        <div class="analytics-list">
          <?php if (!$topDepartments): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد بيانات كافية.' : 'Not enough data.' ?></div>
          <?php endif; ?>

          <?php foreach ($topDepartments as $dep => $count): ?>
            <div class="analytics-item">
              <?= h($dep) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🏛 <?= $isArabic ? 'أكثر الكليات نشاطًا' : 'Most Active Colleges' ?></h3>
        <div class="analytics-list">
          <?php if (!$topColleges): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد بيانات كافية.' : 'Not enough data.' ?></div>
          <?php endif; ?>

          <?php foreach ($topColleges as $college => $count): ?>
            <div class="analytics-item">
              <?= h($college) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>⚠️ <?= $isArabic ? 'أقسام بلا مبادرات' : 'Departments Without Initiatives' ?></h3>
        <div class="analytics-list">
          <?php if (!$inactiveDepartments): ?>
            <div class="analytics-item"><?= $isArabic ? 'كل الأقسام لديها نشاط.' : 'All departments have activity.' ?></div>
          <?php endif; ?>

          <?php foreach (array_slice($inactiveDepartments, 0, 8) as $item): ?>
            <div class="analytics-item">
              <?= h($item['department']) ?>
              <small><?= h($item['college']) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🎯 <?= $isArabic ? 'أهداف التنمية غير المغطاة' : 'Uncovered SDGs' ?></h3>

        <?php if (!$missingSdgs): ?>
          <div class="analytics-item"><?= $isArabic ? 'كل أهداف التنمية مغطاة.' : 'All SDGs are covered.' ?></div>
        <?php else: ?>
          <div class="sdg-missing-wrap">
            <?php foreach ($missingSdgs as $num): ?>
              <span class="sdg-missing-pill">SDG <?= (int)$num ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="analytics-box">
        <h3>✅ <?= $isArabic ? 'حالة المبادرات' : 'Initiative Status' ?></h3>
        <div class="analytics-list">
          <div class="analytics-item"><?= $isArabic ? 'معتمدة' : 'Approved' ?> <span class="analytics-count"><?= $initApproved ?></span></div>
          <div class="analytics-item"><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?> <span class="analytics-count"><?= $initPending ?></span></div>
          <div class="analytics-item"><?= $isArabic ? 'مرفوضة' : 'Rejected' ?> <span class="analytics-count"><?= $initRejected ?></span></div>
        </div>
      </div>

      <div class="analytics-box">
        <h3>📨 <?= $isArabic ? 'طلبات الموافقة' : 'Approval Requests' ?></h3>
        <div class="analytics-list">
          <div class="analytics-item"><?= $isArabic ? 'مقبولة' : 'Approved' ?> <span class="analytics-count"><?= $reqApproved ?></span></div>
          <div class="analytics-item"><?= $isArabic ? 'قيد المراجعة' : 'Pending' ?> <span class="analytics-count"><?= $reqPending ?></span></div>
          <div class="analytics-item"><?= $isArabic ? 'مرفوضة' : 'Rejected' ?> <span class="analytics-count"><?= $reqRejected ?></span></div>
        </div>
      </div>

    </div>

    <div class="analytics-title-strip">
      📄 <?= $isArabic ? 'تحليلات الاتفاقيات' : 'Agreement Analytics' ?>
      <span><?= count($agreements) ?> <?= $isArabic ? 'اتفاقية' : 'agreements' ?></span>
    </div>

    <div class="admin-analytics-grid">

      <div class="analytics-box">
        <h3>⚠️ <?= $isArabic ? 'اتفاقيات بدون مبادرات' : 'Agreements Without Initiatives' ?></h3>
        <div class="analytics-list">
          <?php if (!$noInitiativeList): ?>
            <div class="analytics-item"><?= $isArabic ? 'كل الاتفاقيات لديها مبادرات.' : 'All agreements have initiatives.' ?></div>
          <?php endif; ?>

          <?php foreach (array_slice($noInitiativeList, 0, 8) as $item): ?>
            <div class="analytics-item">
              <?= h($item['name'] ?: $item['code']) ?>
              <small><?= h($item['owner'] ?: '—') ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🏛 <?= $isArabic ? 'أكثر الجهات المالكة للاتفاقيات' : 'Top Agreement Owners' ?></h3>
        <div class="analytics-list">
          <?php if (!$topOwners): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد بيانات كافية.' : 'Not enough data.' ?></div>
          <?php endif; ?>

          <?php foreach ($topOwners as $owner => $count): ?>
            <div class="analytics-item">
              <?= h($owner) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🚫 <?= $isArabic ? 'جهات لديها اتفاقيات بلا مبادرات' : 'Owners Missing Initiatives' ?></h3>
        <div class="analytics-list">
          <?php if (!$topOwnersNoInit): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد نواقص واضحة.' : 'No clear gaps.' ?></div>
          <?php endif; ?>

          <?php foreach ($topOwnersNoInit as $owner => $count): ?>
            <div class="analytics-item">
              <?= h($owner) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🌍 <?= $isArabic ? 'أكثر الدول' : 'Top Countries' ?></h3>
        <div class="analytics-list">
          <?php if (!$topCountries): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد بيانات كافية.' : 'Not enough data.' ?></div>
          <?php endif; ?>

          <?php foreach ($topCountries as $country => $count): ?>
            <div class="analytics-item">
              <?= h($country) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>🤝 <?= $isArabic ? 'أكثر الشركاء' : 'Top Partners' ?></h3>
        <div class="analytics-list">
          <?php if (!$topPartners): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد بيانات كافية.' : 'Not enough data.' ?></div>
          <?php endif; ?>

          <?php foreach ($topPartners as $partner => $count): ?>
            <div class="analytics-item">
              <?= h($partner) ?>
              <span class="analytics-count"><?= (int)$count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="analytics-box">
        <h3>⏳ <?= $isArabic ? 'اتفاقيات تنتهي خلال 6 أشهر' : 'Ending Within 6 Months' ?></h3>
        <div class="analytics-list">
          <?php if (!$endingSoonList): ?>
            <div class="analytics-item"><?= $isArabic ? 'لا توجد اتفاقيات قريبة الانتهاء.' : 'No agreements ending soon.' ?></div>
          <?php endif; ?>

          <?php foreach (array_slice($endingSoonList, 0, 8) as $item): ?>
            <div class="analytics-item">
              <?= h($item['name'] ?: $item['code']) ?>
              <small><?= $isArabic ? 'تاريخ النهاية:' : 'End Date:' ?> <?= h($item['end_date']) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </div>

  <div class="admin-quick-section">
    <h2><?= $isArabic ? 'روابط سريعة' : 'Quick Links' ?></h2>

    <div class="admin-quick-grid">
      <a class="admin-quick-link" href="review-initiative-requests.php?tab=pending&lang=<?= h($lang) ?>">
        📨 <?= $isArabic ? 'طلبات الموافقة الجديدة' : 'New Approval Requests' ?>
      </a>

      <a class="admin-quick-link" href="review-initiatives.php?tab=new&lang=<?= h($lang) ?>">
        📊 <?= $isArabic ? 'المبادرات قيد المراجعة' : 'Pending Initiatives' ?>
      </a>

      <a class="admin-quick-link" href="review-agreements.php?tab=no_initiatives&lang=<?= h($lang) ?>">
        ⚠️ <?= $isArabic ? 'اتفاقيات بدون مبادرات' : 'Agreements Without Initiatives' ?>
      </a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../footer.php'; ?>