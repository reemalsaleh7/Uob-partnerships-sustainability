<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN) {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Location: ../workspace/agreements.php', true, 302);
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SESSION['user_email'] ?? '') !== 'admin@uob.edu.bh') {
  header("Location: ../login.php");
  exit;
}

$pageTitle = "إدارة الاتفاقيات";
$hidePageHeader = true;
$mainContainer = false;
require_once __DIR__ . '/../header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');
$tab = $_GET['tab'] ?? 'all';

function safe_text($v){ return trim((string)($v ?? '')); }
function val_ag(array $row, array $keys, string $fallback = '—'): string {
  foreach ($keys as $k) {
    $v = safe_text($row[$k] ?? '');
    if ($v !== '') return $v;
  }
  return $fallback;
}
function low_safe($v){
  $v = safe_text($v);
  return function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
}
function status_class($status){
  $s = safe_text($status);
  $l = strtolower($s);
  if ($s === 'سارية' || $l === 'active') return 'ok';
  if ($s === 'منتهية' || $l === 'expired') return 'bad';
  return 'wait';
}

$allRaw = array_values(readAgreements(false));
$initiativesAll = loadAllInitiatives(false);

$initiativeCountByAgreement = [];
$initiativeApprovedByAgreement = [];
$initiativePendingByAgreement = [];

foreach ($initiativesAll as $init) {
  $code = safe_text($init['agreement_code'] ?? '');
  if ($code === '') continue;
  $initiativeCountByAgreement[$code] = ($initiativeCountByAgreement[$code] ?? 0) + 1;
  $st = safe_text($init['status'] ?? '');
  $stLow = strtolower($st);
  if ($st === 'معتمد' || $stLow === 'approved') $initiativeApprovedByAgreement[$code] = ($initiativeApprovedByAgreement[$code] ?? 0) + 1;
  if (strpos($st, 'قيد') !== false || $stLow === 'pending') $initiativePendingByAgreement[$code] = ($initiativePendingByAgreement[$code] ?? 0) + 1;
}

$total = count($allRaw);
$activeCount = $expiredCount = $withInitiativesCount = $noInitiativesCount = $notesCount = 0;

foreach ($allRaw as $ag) {
  $code = safe_text($ag['agreement_code'] ?? '');
  $status = safe_text($ag['status'] ?? '');
  $statusLow = strtolower($status);
  $note = safe_text($ag['notes_vppd'] ?? '');

  if ($status === 'سارية' || $statusLow === 'active') $activeCount++;
  if ($status === 'منتهية' || $statusLow === 'expired') $expiredCount++;
  if (!empty($initiativeCountByAgreement[$code])) $withInitiativesCount++; else $noInitiativesCount++;
  if ($note !== '') $notesCount++;
}

$all = array_values(array_filter($allRaw, function($ag) use ($tab, $initiativeCountByAgreement) {
  $code = safe_text($ag['agreement_code'] ?? '');
  $status = safe_text($ag['status'] ?? '');
  $statusLow = strtolower($status);
  $note = safe_text($ag['notes_vppd'] ?? '');

  if ($tab === 'all') return true;
  if ($tab === 'active') return $status === 'سارية' || $statusLow === 'active';
  if ($tab === 'expired') return $status === 'منتهية' || $statusLow === 'expired';
  if ($tab === 'with_initiatives') return !empty($initiativeCountByAgreement[$code]);
  if ($tab === 'no_initiatives') return empty($initiativeCountByAgreement[$code]);
  if ($tab === 'notes') return $note !== '';
  return true;
}));

$countries = $partners = $types = $owners = [];
foreach ($allRaw as $ag) {
  $country = val_ag($ag, ['country','الدولة'], '');
  $partner = val_ag($ag, ['partner_entity','الجهة المتعاونة'], '');
  $type = val_ag($ag, ['agreement_type','نوع الاتفاقية'], '');
  $owner = val_ag($ag, ['owner_entity','الجهة المعنية بتنفيذ الاتفاقية'], '');
  if ($country !== '') $countries[$country] = true;
  if ($partner !== '') $partners[$partner] = true;
  if ($type !== '') $types[$type] = true;
  if ($owner !== '') $owners[$owner] = true;
}
$countries = array_keys($countries); sort($countries);
$partners = array_keys($partners); sort($partners);
$types = array_keys($types); sort($types);
$owners = array_keys($owners); sort($owners);
?>

<style>
.admin-ag-hero{background:linear-gradient(135deg,#0b1f3a,#113c63);color:#fff;padding:54px 20px 78px}.admin-ag-hero-inner{max-width:1220px;margin:auto;display:grid;grid-template-columns:1.1fr .9fr;gap:24px;align-items:center}.admin-ag-hero h1{margin:0;font-size:clamp(34px,4vw,56px);font-weight:950}.admin-ag-hero p{margin:14px 0 0;color:rgba(255,255,255,.86);font-weight:800;line-height:1.9}.admin-ag-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-ag-stat{border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.12);border-radius:22px;padding:17px 18px;cursor:pointer}.admin-ag-stat span{display:block;color:rgba(255,255,255,.72);font-size:13px;font-weight:850}.admin-ag-stat strong{display:block;color:#fff;font-size:30px;font-weight:950;margin-top:4px}.admin-ag-shell{max-width:1220px;margin:-45px auto 48px;padding:0 20px;position:relative;z-index:5}.admin-ag-panel{background:#fff;border:1px solid #e6ebf2;border-radius:28px;box-shadow:0 24px 60px rgba(2,8,23,.12);overflow:hidden}.admin-ag-head{padding:22px 24px;border-bottom:1px solid #e6ebf2;background:#fff}.admin-ag-title{color:#0b1f3a;font-size:24px;font-weight:950;margin:0}.admin-small{font-size:12px;color:#64748b;font-weight:800}.admin-ag-tabs{display:flex;gap:10px;flex-wrap:wrap;padding:18px 24px 0;background:#fbfdff}.admin-ag-tab{padding:10px 16px;border-radius:999px;background:#eef4ff;color:#0b1f3a;text-decoration:none;font-weight:950;border:1px solid #d9e6f7}.admin-ag-tab.active{background:#0b1f3a;color:#fff}.admin-ag-tools{padding:18px 24px;border-bottom:1px solid #e6ebf2;background:#fbfdff}.admin-ag-filter-grid{display:grid;grid-template-columns:2fr 1.1fr 1.1fr 1.1fr 1.1fr auto;gap:12px}.admin-ag-input{min-height:48px;border-radius:14px!important;border:1px solid #d9e3ef!important;background:#fff!important;font-weight:800}.admin-ag-clear{min-height:48px;border-radius:14px!important;font-weight:900}
/* هذا الجزء مهم: لا نستخدم table عشان أي CSS خارجي ما يخفي الجدول */
.ag-list{display:block!important;background:#fff}.ag-header,.ag-row{display:grid!important;grid-template-columns:130px 2fr 1.8fr 110px 1.4fr 1.4fr 100px 140px 130px;gap:0;min-width:1320px}.ag-header{background:#0b1f3a;color:#fff;font-weight:950;position:sticky;top:0;z-index:2}.ag-header div,.ag-row div{padding:14px;border-bottom:1px solid #e8eef6;word-break:break-word}.ag-row{color:#0f172a;font-weight:800;background:#fff}.ag-row:hover{background:#f8fbff}.status-pill{display:inline-flex;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:950}.status-pill.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}.status-pill.bad{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.status-pill.wait{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}.btn-view{border:none;border-radius:12px;min-height:38px;padding:8px 12px;font-weight:950;background:#eef4ff;color:#0b1f3a;cursor:pointer}.req-hidden{display:none!important}.admin-empty{padding:34px;text-align:center;color:#64748b;font-weight:950}.ag-scroll{overflow-x:auto;width:100%;display:block!important;min-height:120px;background:#fff}.admin-modal{position:fixed;inset:0;z-index:9999;display:none}.admin-modal.is-open{display:block}.admin-modal-backdrop{position:absolute;inset:0;background:rgba(2,8,23,.55)}.admin-modal-panel{position:relative;max-width:980px;max-height:86vh;overflow:auto;background:#fff;margin:6vh auto;border-radius:26px;box-shadow:0 30px 80px rgba(2,8,23,.28);padding:24px}.admin-modal-head{display:flex;justify-content:space-between;gap:14px;border-bottom:1px solid #e8eef6;padding-bottom:16px;margin-bottom:16px}.admin-modal-head h2{margin:0;color:#0b1f3a;font-weight:950}.admin-close{border:none;background:#eef4ff;color:#0b1f3a;width:42px;height:42px;border-radius:14px;font-weight:950}.admin-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-detail-box{background:#fbfdff;border:1px solid #e8eef6;border-radius:16px;padding:12px 14px}.admin-detail-box span{display:block;color:#64748b;font-size:12px;font-weight:850}.admin-detail-box strong{display:block;color:#0f172a;font-weight:950;line-height:1.6}.admin-desc{margin-top:12px;background:#fbfdff;border:1px solid #e8eef6;border-radius:16px;padding:14px;color:#334155;font-weight:850;line-height:1.9;white-space:pre-wrap}@media(max-width:992px){.admin-ag-hero-inner{grid-template-columns:1fr}.admin-ag-filter-grid{grid-template-columns:1fr 1fr}}@media(max-width:768px){.admin-ag-filter-grid,.admin-detail-grid{grid-template-columns:1fr}.admin-modal-panel{margin:3vh 12px}}
</style>

<section class="admin-ag-hero">
  <div class="admin-ag-hero-inner">
    <div>
      <h1><?= $isArabic ? 'إدارة الاتفاقيات' : 'Manage Agreements' ?></h1>
      <p><?= $isArabic ? 'صفحة إدارية لعرض جميع الاتفاقيات وتفاصيلها، ومعرفة الاتفاقيات المرتبطة بالمبادرات والاتفاقيات التي تحتاج مبادرات.' : 'Admin page to view all agreements, their details, linked initiatives, and agreements that still need initiatives.' ?></p>
    </div>
    <div class="admin-ag-stats">
      <div class="admin-ag-stat" data-tab-go="all"><span><?= $isArabic ? 'كل الاتفاقيات' : 'All Agreements' ?></span><strong><?= (int)$total ?></strong></div>
      <div class="admin-ag-stat" data-tab-go="active"><span><?= $isArabic ? 'السارية' : 'Active' ?></span><strong><?= (int)$activeCount ?></strong></div>
      <div class="admin-ag-stat" data-tab-go="with_initiatives"><span><?= $isArabic ? 'لديها مبادرات' : 'With Initiatives' ?></span><strong><?= (int)$withInitiativesCount ?></strong></div>
      <div class="admin-ag-stat" data-tab-go="no_initiatives"><span><?= $isArabic ? 'بدون مبادرات' : 'Without Initiatives' ?></span><strong><?= (int)$noInitiativesCount ?></strong></div>
    </div>
  </div>
</section>

<div class="admin-ag-shell">
  <div class="admin-ag-panel">
    <div class="admin-ag-head">
      <h2 class="admin-ag-title"><?= $isArabic ? 'جدول الاتفاقيات' : 'Agreements Table' ?></h2>
      <div class="admin-small"><span id="visibleCount"><?= count($all) ?></span> <?= $isArabic ? 'اتفاقية ظاهرة' : 'visible agreements' ?></div>
    </div>

    <div class="admin-ag-tabs">
      <a class="admin-ag-tab <?= $tab==='all'?'active':'' ?>" href="?tab=all&lang=<?= h($lang) ?>">📋 <?= $isArabic ? 'الكل' : 'All' ?></a>
      <a class="admin-ag-tab <?= $tab==='active'?'active':'' ?>" href="?tab=active&lang=<?= h($lang) ?>">✅ <?= $isArabic ? 'السارية' : 'Active' ?></a>
      <a class="admin-ag-tab <?= $tab==='expired'?'active':'' ?>" href="?tab=expired&lang=<?= h($lang) ?>">⏳ <?= $isArabic ? 'المنتهية' : 'Expired' ?></a>
      <a class="admin-ag-tab <?= $tab==='with_initiatives'?'active':'' ?>" href="?tab=with_initiatives&lang=<?= h($lang) ?>">🔗 <?= $isArabic ? 'لديها مبادرات' : 'With Initiatives' ?></a>
      <a class="admin-ag-tab <?= $tab==='no_initiatives'?'active':'' ?>" href="?tab=no_initiatives&lang=<?= h($lang) ?>">⚠️ <?= $isArabic ? 'بدون مبادرات' : 'Without Initiatives' ?></a>
      <a class="admin-ag-tab <?= $tab==='notes'?'active':'' ?>" href="?tab=notes&lang=<?= h($lang) ?>">📝 <?= $isArabic ? 'ملاحظات' : 'Notes' ?></a>
    </div>

    <div class="admin-ag-tools">
      <div class="admin-ag-filter-grid">
        <input id="searchInput" autocomplete="off" class="form-control admin-ag-input" type="search" placeholder="<?= $isArabic ? 'بحث بالاسم، الكود، الشريك، الدولة...' : 'Search by name, code, partner, country...' ?>">
        <select id="countryFilter" autocomplete="off" class="form-select admin-ag-input"><option value=""><?= $isArabic ? 'كل الدول' : 'All Countries' ?></option><?php foreach ($countries as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?></select>
        <select id="partnerFilter" autocomplete="off" class="form-select admin-ag-input"><option value=""><?= $isArabic ? 'كل الشركاء' : 'All Partners' ?></option><?php foreach ($partners as $p): ?><option value="<?= h($p) ?>"><?= h($p) ?></option><?php endforeach; ?></select>
        <select id="typeFilter" autocomplete="off" class="form-select admin-ag-input"><option value=""><?= $isArabic ? 'كل الأنواع' : 'All Types' ?></option><?php foreach ($types as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?></select>
        <select id="ownerFilter" autocomplete="off" class="form-select admin-ag-input"><option value=""><?= $isArabic ? 'كل الجهات' : 'All Owners' ?></option><?php foreach ($owners as $o): ?><option value="<?= h($o) ?>"><?= h($o) ?></option><?php endforeach; ?></select>
        <button id="clearFilters" type="button" class="btn btn-outline-secondary admin-ag-clear"><?= $isArabic ? 'مسح' : 'Clear' ?></button>
      </div>
    </div>

    <div class="ag-scroll">
      <div class="ag-list" id="agreementsList">
        <div class="ag-header">
          <div><?= $isArabic ? 'الكود' : 'Code' ?></div><div><?= $isArabic ? 'الاتفاقية' : 'Agreement' ?></div><div><?= $isArabic ? 'الشريك' : 'Partner' ?></div><div><?= $isArabic ? 'الدولة' : 'Country' ?></div><div><?= $isArabic ? 'النوع' : 'Type' ?></div><div><?= $isArabic ? 'الجهة المالكة' : 'Owner' ?></div><div><?= $isArabic ? 'الحالة' : 'Status' ?></div><div><?= $isArabic ? 'المبادرات' : 'Initiatives' ?></div><div><?= $isArabic ? 'إجراءات' : 'Actions' ?></div>
        </div>

        <?php if (!$all): ?>
          <div class="admin-empty"><?= $isArabic ? 'لا توجد بيانات' : 'No data found' ?></div>
        <?php endif; ?>

        <?php foreach ($all as $ag): ?>
          <?php
            $code = val_ag($ag, ['agreement_code', 'code'], '');
            $name = val_ag($ag, ['agreement_name', 'اسم الاتفاقية'], '');
            $partner = val_ag($ag, ['partner_entity', 'الجهة المتعاونة'], '');
            $country = val_ag($ag, ['country', 'الدولة'], '');
            $type = val_ag($ag, ['agreement_type', 'نوع الاتفاقية'], '');
            $owner = val_ag($ag, ['owner_entity', 'الجهة المعنية بتنفيذ الاتفاقية'], '');
            $status = val_ag($ag, ['status', 'الحالة'], '');
            $start = val_ag($ag, ['start_date', 'تاريخ البداية'], '');
            $end = val_ag($ag, ['end_date', 'تاريخ النهاية'], '');
            $summary = val_ag($ag, ['summary', 'agreement_summary', 'ملخص الاتفاقية'], '');
            $goals = val_ag($ag, ['goals', 'أهداف الاتفاقية'], '');
            $sdgs = val_ag($ag, ['sdg_goals', 'SDGs', 'SDG'], '');
            $initCount = (int)($initiativeCountByAgreement[$code] ?? 0);
            $approvedInit = (int)($initiativeApprovedByAgreement[$code] ?? 0);
            $pendingInit = (int)($initiativePendingByAgreement[$code] ?? 0);
            $searchText = low_safe($code.' '.$name.' '.$partner.' '.$country.' '.$type.' '.$owner.' '.$summary.' '.$goals.' '.$sdgs);
            $json = h(json_encode($ag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
          ?>
          <div class="ag-row agreement-row"
            data-search="<?= h($searchText) ?>"
            data-country="<?= h($country) ?>"
            data-partner="<?= h($partner) ?>"
            data-type="<?= h($type) ?>"
            data-owner="<?= h($owner) ?>">
            <div><strong><?= h($code ?: '—') ?></strong><div class="admin-small"><?= h($start) ?></div></div>
            <div><strong><?= h($name ?: '—') ?></strong><div class="admin-small"><?= h($end) ?></div></div>
            <div><?= h($partner ?: '—') ?></div>
            <div><?= h($country ?: '—') ?></div>
            <div><?= h($type ?: '—') ?></div>
            <div><?= h($owner ?: '—') ?></div>
            <div><span class="status-pill <?= h(status_class($status)) ?>"><?= h($status ?: '—') ?></span></div>
            <div><?php if ($initCount > 0): ?><span class="status-pill ok"><?= $initCount ?> <?= $isArabic ? 'مبادرة' : 'Initiatives' ?></span><div class="admin-small"><?= $isArabic ? 'معتمد' : 'Approved' ?>: <?= $approvedInit ?> | <?= $isArabic ? 'قيد' : 'Pending' ?>: <?= $pendingInit ?></div><?php else: ?><span class="status-pill bad"><?= $isArabic ? 'لا توجد مبادرات' : 'No Initiatives' ?></span><?php endif; ?></div>
            <div><button type="button" class="btn-view" data-view='<?= $json ?>' data-init-count="<?= $initCount ?>" data-init-approved="<?= $approvedInit ?>" data-init-pending="<?= $pendingInit ?>"><?= $isArabic ? 'عرض التفاصيل' : 'View Details' ?></button></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<div class="admin-modal" id="agreementModal">
  <div class="admin-modal-backdrop" id="modalBackdrop"></div>
  <div class="admin-modal-panel">
    <div class="admin-modal-head"><div><h2 id="modalTitle">—</h2><div class="admin-small" id="modalCode">—</div></div><button class="admin-close" id="modalClose" type="button">✕</button></div>
    <div class="admin-detail-grid">
      <div class="admin-detail-box"><span><?= $isArabic ? 'الشريك' : 'Partner' ?></span><strong id="modalPartner">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'الدولة' : 'Country' ?></span><strong id="modalCountry">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'نوع الاتفاقية' : 'Type' ?></span><strong id="modalType">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'الجهة المالكة' : 'Owner' ?></span><strong id="modalOwner">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'الحالة' : 'Status' ?></span><strong id="modalStatus">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'التجديد التلقائي' : 'Auto Renew' ?></span><strong id="modalRenew">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'تاريخ البداية' : 'Start Date' ?></span><strong id="modalStart">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'تاريخ النهاية' : 'End Date' ?></span><strong id="modalEnd">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'عدد المبادرات' : 'Initiatives Count' ?></span><strong id="modalInitCount">—</strong></div><div class="admin-detail-box"><span><?= $isArabic ? 'أهداف التنمية' : 'SDGs' ?></span><strong id="modalSdgs">—</strong></div>
    </div>
    <div class="admin-desc" id="modalSummary">—</div><div class="admin-desc" id="modalGoals">—</div><div class="admin-desc" id="modalNotes">—</div>
  </div>
</div>

<script>
const searchInput=document.getElementById('searchInput'),countryFilter=document.getElementById('countryFilter'),partnerFilter=document.getElementById('partnerFilter'),typeFilter=document.getElementById('typeFilter'),ownerFilter=document.getElementById('ownerFilter'),clearFilters=document.getElementById('clearFilters'),visibleCount=document.getElementById('visibleCount');
function norm(v){return String(v||'').toLowerCase().trim().replace(/\s+/g,' ').replace('مملكة البحرين','bahrain').replace('البحرين','bahrain');}
function applyFilters(){const q=norm(searchInput.value),country=norm(countryFilter.value),partner=norm(partnerFilter.value),type=norm(typeFilter.value),owner=norm(ownerFilter.value);let count=0;document.querySelectorAll('.agreement-row').forEach(row=>{const show=(!q||norm(row.dataset.search).includes(q))&&(!country||norm(row.dataset.country)===country)&&(!partner||norm(row.dataset.partner)===partner)&&(!type||norm(row.dataset.type)===type)&&(!owner||norm(row.dataset.owner)===owner);row.classList.toggle('req-hidden',!show);if(show)count++;});visibleCount.textContent=count;}
[searchInput,countryFilter,partnerFilter,typeFilter,ownerFilter].forEach(el=>{el.addEventListener('input',applyFilters);el.addEventListener('change',applyFilters);});
clearFilters.addEventListener('click',()=>{searchInput.value='';countryFilter.value='';partnerFilter.value='';typeFilter.value='';ownerFilter.value='';applyFilters();});
document.querySelectorAll('[data-tab-go]').forEach(card=>{card.addEventListener('click',()=>{window.location.href='review-agreements.php?tab='+card.dataset.tabGo+'&lang=<?= h($lang) ?>';});});
const modal=document.getElementById('agreementModal'),modalBackdrop=document.getElementById('modalBackdrop'),modalClose=document.getElementById('modalClose');
function getField(data,keys){for(const key of keys){if(data[key]!==undefined&&String(data[key]).trim()!=='')return data[key];}return '';}function setText(id,value){const el=document.getElementById(id);if(el)el.textContent=value&&String(value).trim()!==''?value:'—';}
document.querySelectorAll('.btn-view').forEach(btn=>{btn.addEventListener('click',()=>{const data=JSON.parse(btn.dataset.view||'{}');setText('modalTitle',getField(data,['agreement_name','اسم الاتفاقية']));setText('modalCode',getField(data,['agreement_code','code']));setText('modalPartner',getField(data,['partner_entity','الجهة المتعاونة']));setText('modalCountry',getField(data,['country','الدولة']));setText('modalType',getField(data,['agreement_type','نوع الاتفاقية']));setText('modalOwner',getField(data,['owner_entity','الجهة المعنية بتنفيذ الاتفاقية']));setText('modalStatus',getField(data,['status','الحالة']));setText('modalRenew',getField(data,['auto_renew']));setText('modalStart',getField(data,['start_date','تاريخ البداية']));setText('modalEnd',getField(data,['end_date','تاريخ النهاية']));setText('modalSdgs',getField(data,['sdg_goals','SDGs','SDG']));setText('modalSummary','<?= $isArabic ? 'الملخص: ' : 'Summary: ' ?>'+(getField(data,['summary','agreement_summary','ملخص الاتفاقية'])||'—'));setText('modalGoals','<?= $isArabic ? 'الأهداف: ' : 'Goals: ' ?>'+(getField(data,['goals','أهداف الاتفاقية'])||'—'));setText('modalNotes','<?= $isArabic ? 'ملاحظات: ' : 'Notes: ' ?>'+(getField(data,['notes_vppd'])||'—'));setText('modalInitCount',(btn.dataset.initCount||'0')+' | <?= $isArabic ? 'معتمد' : 'Approved' ?>: '+(btn.dataset.initApproved||'0')+' | <?= $isArabic ? 'قيد' : 'Pending' ?>: '+(btn.dataset.initPending||'0'));modal.classList.add('is-open');document.body.style.overflow='hidden';});});
function closeModal(){modal.classList.remove('is-open');document.body.style.overflow='';}modalBackdrop.addEventListener('click',closeModal);modalClose.addEventListener('click',closeModal);document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
window.addEventListener('DOMContentLoaded',()=>{applyFilters();});
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>
