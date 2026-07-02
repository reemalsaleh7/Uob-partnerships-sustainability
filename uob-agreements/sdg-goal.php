<?php
$pageTitle = "SDG Goal Details";
$pageSubtitle = "";
$breadcrumb = [];
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

function parseDateAnySdg(string $s): int {
  $s = trim($s);
  if ($s === '') return 0;
  $ts = strtotime(str_replace('/', '-', $s));
  return $ts !== false ? $ts : 0;
}

function extractSdgNumbersFromText(string $text): array {
  $out = [];
  if (preg_match_all('/(?:SDG\s*)?([1-9]|1[0-7])\b/u', $text, $m)) {
    foreach ($m[1] as $n) {
      $v = (int)$n;
      if ($v >= 1 && $v <= 17) $out[$v] = true;
    }
  }
  return array_keys($out);
}

function extractInitiativeSdgs(array $it): array {
  $texts = [
    (string)($it['sdg_primary'] ?? ''),
    (string)($it['sdg_secondary'] ?? ''),
    (string)($it['SDGs'] ?? ''),
  ];

  $nums = [];
  foreach ($texts as $txt) {
    foreach (extractSdgNumbersFromText($txt) as $n) {
      $nums[$n] = true;
    }
  }
  return array_keys($nums);
}

function extractAgreementSdgs(array $ag): array {
  $candidateKeys = [
    'أهداف التنمية المستدامة المرتبطة',
    'الاهداف المرتبطة',
    'الأهداف المرتبطة',
    'SDGs',
    'SDG',
    'SDG الأساسي',
    'SDG ثانوي',
    'sdg_primary',
    'sdg_secondary',
  ];

  $nums = [];
  foreach ($candidateKeys as $key) {
    if (!empty($ag[$key])) {
      foreach (extractSdgNumbersFromText((string)$ag[$key]) as $n) {
        $nums[$n] = true;
      }
    }
  }
  return array_keys($nums);
}

function getArrSdg($name): array {
  $v = $_GET[$name] ?? [];
  if (!is_array($v)) $v = [$v];
  return array_values(array_filter(array_map('trim', $v), fn($x) => $x !== ''));
}

function isCheckedSdg($name, $value): string {
  return in_array($value, getArrSdg($name), true) ? 'checked' : '';
}

$sdgNames = [
  1  => ['ar' => 'القضاء على الفقر', 'en' => 'No Poverty'],
  2  => ['ar' => 'القضاء على الجوع', 'en' => 'Zero Hunger'],
  3  => ['ar' => 'الصحة الجيدة والرفاه', 'en' => 'Good Health and Well-being'],
  4  => ['ar' => 'التعليم الجيد', 'en' => 'Quality Education'],
  5  => ['ar' => 'المساواة بين الجنسين', 'en' => 'Gender Equality'],
  6  => ['ar' => 'المياه النظيفة والصرف الصحي', 'en' => 'Clean Water and Sanitation'],
  7  => ['ar' => 'طاقة نظيفة وبأسعار معقولة', 'en' => 'Affordable and Clean Energy'],
  8  => ['ar' => 'العمل اللائق ونمو الاقتصاد', 'en' => 'Decent Work and Economic Growth'],
  9  => ['ar' => 'الصناعة والابتكار والبنية التحتية', 'en' => 'Industry, Innovation and Infrastructure'],
  10 => ['ar' => 'الحد من أوجه عدم المساواة', 'en' => 'Reduced Inequalities'],
  11 => ['ar' => 'مدن ومجتمعات مستدامة', 'en' => 'Sustainable Cities and Communities'],
  12 => ['ar' => 'الاستهلاك والإنتاج المسؤولان', 'en' => 'Responsible Consumption and Production'],
  13 => ['ar' => 'العمل المناخي', 'en' => 'Climate Action'],
  14 => ['ar' => 'الحياة تحت الماء', 'en' => 'Life Below Water'],
  15 => ['ar' => 'الحياة في البر', 'en' => 'Life on Land'],
  16 => ['ar' => 'السلام والعدل والمؤسسات القوية', 'en' => 'Peace, Justice and Strong Institutions'],
  17 => ['ar' => 'عقد الشراكات لتحقيق الأهداف', 'en' => 'Partnerships for the Goals'],
];

$selectedSdg = (int)($_GET['sdg'] ?? 0);

if ($selectedSdg < 1 || $selectedSdg > 17) {
  echo '<div class="container py-5"><div class="alert alert-danger">Invalid SDG.</div></div>';
  require_once __DIR__ . '/footer.php';
  exit;
}

$allInitiatives = loadAllInitiatives();
$allAgreements  = function_exists('readAgreements') ? readAgreements() : [];

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

if (!$isAdmin) {
  $allInitiatives = array_values(array_filter($allInitiatives, function($it){
    return trim((string)($it['status'] ?? 'معتمد')) === 'معتمد';
  }));
}

$selectedInitiatives = [];
$selectedAgreements = [];

foreach ($allInitiatives as $it) {
  if (in_array($selectedSdg, extractInitiativeSdgs($it), true)) {
    $selectedInitiatives[] = $it;
  }
}

foreach ($allAgreements as $ag) {
  if (in_array($selectedSdg, extractAgreementSdgs($ag), true)) {
    $selectedAgreements[] = $ag;
  }
}

usort($selectedInitiatives, function($a, $b){
  return parseDateAnySdg((string)($b['start_date'] ?? '')) <=> parseDateAnySdg((string)($a['start_date'] ?? ''));
});

usort($selectedAgreements, function($a, $b){
  return parseDateAnySdg((string)($b['start_date'] ?? ($b['تاريخ البدء'] ?? ''))) <=> parseDateAnySdg((string)($a['start_date'] ?? ($a['تاريخ البدء'] ?? '')));
});

$initiativeTypes = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)($x['type'] ?? '')), $selectedInitiatives))));
$initiativeEntities = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)($x['entity'] ?? '')), $selectedInitiatives))));

$agreementStatuses = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)($x['status'] ?? '')), $selectedAgreements))));
$agreementTypes = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)($x['agreement_type'] ?? '')), $selectedAgreements))));
$countries = array_values(array_unique(array_filter(array_map(fn($x) => trim((string)($x['country'] ?? '')), $selectedAgreements))));

$fInitType = getArrSdg('initiative_type');
$fEntity = getArrSdg('entity');
$fAgStatus = getArrSdg('agreement_status');
$fAgType = getArrSdg('agreement_type');
$fCountry = getArrSdg('country');

$activeFiltersCount = count($fInitType) + count($fEntity) + count($fAgStatus) + count($fAgType) + count($fCountry);

if ($fInitType) {
  $selectedInitiatives = array_values(array_filter($selectedInitiatives, fn($x) =>
    in_array(trim((string)($x['type'] ?? '')), $fInitType, true)
  ));
}

if ($fEntity) {
  $selectedInitiatives = array_values(array_filter($selectedInitiatives, fn($x) =>
    in_array(trim((string)($x['entity'] ?? '')), $fEntity, true)
  ));
}

if ($fAgStatus) {
  $selectedAgreements = array_values(array_filter($selectedAgreements, fn($x) =>
    in_array(trim((string)($x['status'] ?? '')), $fAgStatus, true)
  ));
}

if ($fAgType) {
  $selectedAgreements = array_values(array_filter($selectedAgreements, fn($x) =>
    in_array(trim((string)($x['agreement_type'] ?? '')), $fAgType, true)
  ));
}

if ($fCountry) {
  $selectedAgreements = array_values(array_filter($selectedAgreements, fn($x) =>
    in_array(trim((string)($x['country'] ?? '')), $fCountry, true)
  ));
}

$goalName = $sdgNames[$selectedSdg][$lang] ?? '';
$padded   = str_pad((string)$selectedSdg, 2, '0', STR_PAD_LEFT);
$goalImg  = "assets/image/sdg/SDG-LOGO/sdg{$selectedSdg}/E_Elyx_{$padded}.png";

$currentFile = basename($_SERVER['PHP_SELF']);
?>

<style>
.sdgGoalPage{
  background:#f4f7fb;
  padding:24px 0 60px;
}

.sdgBackRow{
  margin-bottom:18px;
}

.sdgBackBtn{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 18px;
  border-radius:999px;
  background:#fff;
  border:1px solid #d7e0ea;
  color:#0b1f3a;
  font-weight:850;
  text-decoration:none;
  box-shadow:0 6px 16px rgba(15,23,42,.05);
}

.sdgGoalHeroWrap{
  width:100%;
  margin-bottom:24px;
}

.sdgGoalHeroBox{
  width:100%;
  background:#fff;
  border:1px solid #e6ebf2;
  border-radius:28px;
  overflow:hidden;
  box-shadow:0 18px 42px rgba(15,23,42,.08);
}

.sdgGoalHeroImage{
  width:100%;
  height:560px;
  background:#fff url('<?= h($goalImg) ?>') center center no-repeat;
  background-size:contain;
}

.sdgGoalTitleWrap{
  background:#fff;
  border-top:1px solid #e6ebf2;
  padding:28px;
}

.sdgGoalTitleWrap h1{
  margin:0 0 12px;
  font-size:42px;
  font-weight:950;
  color:#0b1f3a;
  line-height:1.2;
}

.sdgGoalHeroLine{
  width:96px;
  height:5px;
  border-radius:999px;
  background:#2aa9ff;
  margin-bottom:18px;
}

.sdgGoalHeroStats{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.sdgGoalHeroStat{
  background:#f8fbff;
  border:1px solid #dbe7f3;
  border-radius:16px;
  padding:14px 16px;
}

.sdgGoalHeroStat .lbl{
  color:#64748b;
  font-size:13px;
  font-weight:800;
  margin-bottom:4px;
}

.sdgGoalHeroStat .val{
  color:#0b1f3a;
  font-size:28px;
  font-weight:950;
  line-height:1.1;
}

.sdgFilterActions{
  position:relative;
  display:flex;
  gap:14px;
  margin:8px 0 28px;
  direction:ltr;
  z-index:50;
}

.sdgFilterBtn,
.sdgClearBtn{
  height:66px;
  min-width:145px;
  background:#fff;
  border:1px solid #d9e4ef;
  color:#002b5c;
  padding:0 24px;
  border-radius:12px;
  font-weight:850;
  font-size:18px;
  text-decoration:none;
  box-shadow:0 4px 12px rgba(0,0,0,.08);
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:12px;
  cursor:pointer;
}

.sdgFilterBtn .filterIcon{
  font-size:24px;
  color:#002b5c;
}

.sdgFilterBtn b{
  color:#0050a4;
  font-size:15px;
  margin-inline-start:6px;
}

.sdgClearBtn{
  color:#b32017;
}

.sdgClearBtn .clearIcon{
  font-size:24px;
  color:#b32017;
}

.sdgFilterPanel{
  display:none;
  position:absolute;
  top:82px;
  left:0;
  width:430px;
  max-height:640px;
  overflow-y:auto;
  background:#fff;
  border:2px solid #0050a4;
  border-radius:10px;
  padding:24px;
  z-index:9999;
  box-shadow:0 12px 28px rgba(15,23,42,.18);
}

.sdgFilterPanel.active{
  display:block;
}

.sdgFilterHeader{
  display:flex;
  align-items:center;
  justify-content:space-between;
  border-bottom:1px solid #ddd;
  padding-bottom:12px;
  margin-bottom:18px;
}

.sdgFilterHeader h3{
  margin:0;
  color:#003b7a;
  font-size:24px;
  font-weight:950;
}

.sdgCloseFilter{
  width:38px;
  height:38px;
  border:1px solid #d9e4ef;
  background:#fff;
  color:#b32017;
  border-radius:8px;
  font-size:22px;
  font-weight:900;
  cursor:pointer;
}

.sdgFilterGroup{
  border-bottom:1px solid #d8d8d8;
  padding-bottom:18px;
  margin-bottom:22px;
}

.sdgFilterGroup h4{
  color:#003b7a;
  font-size:20px;
  font-weight:900;
  margin-bottom:16px;
}

.sdgFilterGroup label{
  display:flex;
  align-items:center;
  gap:12px;
  margin:16px 0;
  color:#111;
  font-size:17px;
  font-weight:700;
}

.sdgFilterGroup input[type="checkbox"]{
  width:22px;
  height:22px;
  accent-color:#003b7a;
}

.sdgApplyBtn{
  width:100%;
  background:#002b5c;
  color:#fff;
  border:0;
  border-radius:10px;
  padding:14px;
  font-weight:900;
  font-size:17px;
}

.sdgPanelClear{
  display:block;
  text-align:center;
  margin-top:12px;
  color:#b32017;
  font-weight:900;
  text-decoration:none;
}

.sdgGoalGrid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:24px;
}

.sdgGoalBlock{
  background:#fff;
  border:1px solid #e6ebf2;
  border-radius:18px;
  padding:22px;
  box-shadow:0 8px 20px rgba(15,23,42,.04);
}

.sdgGoalBlock h2{
  margin:0 0 18px;
  font-size:22px;
  font-weight:900;
  color:#0b1f3a;
  border-bottom:2px solid #edf2f7;
  padding-bottom:10px;
}

.sdgItem{
  border:1px solid #e7edf5;
  border-radius:14px;
  padding:16px;
  margin-bottom:14px;
  background:#fbfdff;
}

.sdgItem .title{
  font-size:18px;
  font-weight:900;
  color:#0b1f3a;
  margin-bottom:10px;
  line-height:1.6;
}

.sdgItem .meta{
  color:#64748b;
  line-height:2;
  font-weight:750;
  font-size:15px;
}

.sdgItem .meta b{
  color:#334155;
}

.sdgEmpty{
  border:1px dashed #cbd5e1;
  border-radius:14px;
  padding:18px;
  color:#64748b;
  font-weight:800;
  background:#f8fafc;
}

@media (max-width: 992px){
  .sdgGoalHeroImage{
    height:320px;
  }

  .sdgGoalTitleWrap h1{
    font-size:28px;
  }

  .sdgGoalHeroStats,
  .sdgGoalGrid{
    grid-template-columns:1fr;
  }

  .sdgFilterPanel{
    width:92vw;
  }
}
</style>

<section class="sdgGoalPage">
  <div class="container">

    <div class="sdgBackRow" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
      <a class="sdgBackBtn" href="sdg.php?lang=<?= urlencode($lang) ?>">
        <?= $isArabic ? 'العودة إلى أهداف التنمية' : 'Back to SDGs' ?>
      </a>
    </div>

    <div class="sdgGoalHeroWrap">
      <div class="sdgGoalHeroBox">
        <div class="sdgGoalHeroImage"></div>

        <div class="sdgGoalTitleWrap" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
          <h1>SDG <?= $selectedSdg ?> — <?= h($goalName) ?></h1>
          <div class="sdgGoalHeroLine"></div>

          <div class="sdgGoalHeroStats">
            <div class="sdgGoalHeroStat">
              <div class="lbl"><?= $isArabic ? 'عدد المبادرات' : 'Total Initiatives' ?></div>
              <div class="val"><?= count($selectedInitiatives) ?></div>
            </div>

            <div class="sdgGoalHeroStat">
              <div class="lbl"><?= $isArabic ? 'عدد الاتفاقيات' : 'Total Agreements' ?></div>
              <div class="val"><?= count($selectedAgreements) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="sdgFilterActions">
      <button type="button" class="sdgFilterBtn" onclick="openSdgFilter(event)">
        <span class="filterIcon">▼</span>
        Filters
        <b><?= $activeFiltersCount ?></b>
      </button>

      <a class="sdgClearBtn" href="<?= h($currentFile) ?>?sdg=<?= urlencode($selectedSdg) ?>&lang=<?= urlencode($lang) ?>">
        <span class="clearIcon">×</span>
        Clear
      </a>

      <div id="sdgFilterPanel" class="sdgFilterPanel" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
        <form method="get">
          <input type="hidden" name="sdg" value="<?= h((string)$selectedSdg) ?>">
          <input type="hidden" name="lang" value="<?= h($lang) ?>">

          <div class="sdgFilterHeader">
            <h3><?= $isArabic ? 'الفلاتر' : 'Filters' ?></h3>
            <button type="button" class="sdgCloseFilter" onclick="closeSdgFilter()">×</button>
          </div>

          <div class="sdgFilterGroup">
            <h4><?= $isArabic ? 'نوع المبادرة' : 'Initiative Type' ?></h4>
            <?php if ($initiativeTypes): ?>
              <?php foreach ($initiativeTypes as $v): ?>
                <label>
                  <input type="checkbox" name="initiative_type[]" value="<?= h($v) ?>" <?= isCheckedSdg('initiative_type', $v) ?>>
                  <?= h($v) ?>
                </label>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="sdgEmpty"><?= $isArabic ? 'لا توجد خيارات.' : 'No options.' ?></div>
            <?php endif; ?>
          </div>

          <div class="sdgFilterGroup">
            <h4><?= $isArabic ? 'الجهة / الكلية' : 'Entity / College' ?></h4>
            <?php if ($initiativeEntities): ?>
              <?php foreach ($initiativeEntities as $v): ?>
                <label>
                  <input type="checkbox" name="entity[]" value="<?= h($v) ?>" <?= isCheckedSdg('entity', $v) ?>>
                  <?= h($v) ?>
                </label>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="sdgEmpty"><?= $isArabic ? 'لا توجد خيارات.' : 'No options.' ?></div>
            <?php endif; ?>
          </div>

          <div class="sdgFilterGroup">
            <h4><?= $isArabic ? 'حالة الاتفاقية' : 'Agreement Status' ?></h4>
            <?php if ($agreementStatuses): ?>
              <?php foreach ($agreementStatuses as $v): ?>
                <label>
                  <input type="checkbox" name="agreement_status[]" value="<?= h($v) ?>" <?= isCheckedSdg('agreement_status', $v) ?>>
                  <?= h($v) ?>
                </label>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="sdgEmpty"><?= $isArabic ? 'لا توجد خيارات.' : 'No options.' ?></div>
            <?php endif; ?>
          </div>

          <div class="sdgFilterGroup">
            <h4><?= $isArabic ? 'نوع الاتفاقية' : 'Agreement Type' ?></h4>
            <?php if ($agreementTypes): ?>
              <?php foreach ($agreementTypes as $v): ?>
                <label>
                  <input type="checkbox" name="agreement_type[]" value="<?= h($v) ?>" <?= isCheckedSdg('agreement_type', $v) ?>>
                  <?= h($v) ?>
                </label>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="sdgEmpty"><?= $isArabic ? 'لا توجد خيارات.' : 'No options.' ?></div>
            <?php endif; ?>
          </div>

          <div class="sdgFilterGroup">
            <h4><?= $isArabic ? 'الدولة' : 'Country' ?></h4>
            <?php if ($countries): ?>
              <?php foreach ($countries as $v): ?>
                <label>
                  <input type="checkbox" name="country[]" value="<?= h($v) ?>" <?= isCheckedSdg('country', $v) ?>>
                  <?= h($v) ?>
                </label>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="sdgEmpty"><?= $isArabic ? 'لا توجد خيارات.' : 'No options.' ?></div>
            <?php endif; ?>
          </div>

          <button class="sdgApplyBtn" type="submit">
            <?= $isArabic ? 'تطبيق الفلتر' : 'Apply Filters' ?>
          </button>

          <a class="sdgPanelClear" href="<?= h($currentFile) ?>?sdg=<?= urlencode($selectedSdg) ?>&lang=<?= urlencode($lang) ?>">
            <?= $isArabic ? 'مسح الفلاتر' : 'Clear Filters' ?>
          </a>
        </form>
      </div>
    </div>

    <div class="sdgGoalGrid">

      <div class="sdgGoalBlock" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
        <h2><?= $isArabic ? 'المبادرات المرتبطة' : 'Related Initiatives' ?></h2>

        <?php if ($selectedInitiatives): ?>
          <?php foreach ($selectedInitiatives as $it): ?>
            <?php
              $id = trim((string)($it['_id'] ?? ($it['id'] ?? '')));
              $title = $it['title'] ?? '';
              $unit  = $it['entity'] ?? '';
              $type  = $it['type'] ?? '';
              $date  = $it['start_date'] ?? '';
            ?>
            <div class="sdgItem">
              <div class="title"><?= h($title ?: '—') ?></div>
              <div class="meta">
                <b><?= $isArabic ? 'الجهة:' : 'Unit:' ?></b> <?= h($unit ?: '—') ?><br>
                <b><?= $isArabic ? 'النوع:' : 'Type:' ?></b> <?= h($type ?: '—') ?><br>
                <b><?= $isArabic ? 'التاريخ:' : 'Date:' ?></b> <?= h($date ?: '—') ?>
              </div>
              <div class="mt-3">
                <a class="btn btn-primary btn-sm" href="initiative-details.php?id=<?= urlencode($id) ?>&lang=<?= urlencode($lang) ?>">
                  <?= $isArabic ? 'التفاصيل' : 'Details' ?>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sdgEmpty"><?= $isArabic ? 'لا توجد مبادرات مطابقة للفلتر.' : 'No initiatives match the selected filters.' ?></div>
        <?php endif; ?>
      </div>

      <div class="sdgGoalBlock" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
        <h2><?= $isArabic ? 'الاتفاقيات المرتبطة' : 'Related Agreements' ?></h2>

        <?php if ($selectedAgreements): ?>
          <?php foreach ($selectedAgreements as $ag): ?>
            <?php
              $code = trim((string)($ag['agreement_code'] ?? ''));
              $name = trim((string)($ag['agreement_name'] ?? ''));
              $partner = trim((string)($ag['partner_entity'] ?? ''));
              $status = trim((string)($ag['status'] ?? ''));
              $type = trim((string)($ag['agreement_type'] ?? ''));
              $country = trim((string)($ag['country'] ?? ''));
              $date = trim((string)($ag['start_date'] ?? ($ag['تاريخ البدء'] ?? '')));
            ?>
            <div class="sdgItem">
              <div class="title"><?= h($name ?: '—') ?></div>
              <div class="meta">
                <b><?= $isArabic ? 'الجهة المتعاونة:' : 'Partner:' ?></b> <?= h($partner ?: '—') ?><br>
                <b><?= $isArabic ? 'النوع:' : 'Type:' ?></b> <?= h($type ?: '—') ?><br>
                <b><?= $isArabic ? 'الدولة:' : 'Country:' ?></b> <?= h($country ?: '—') ?><br>
                <b><?= $isArabic ? 'الحالة:' : 'Status:' ?></b> <?= h($status ?: '—') ?><br>
                <b><?= $isArabic ? 'التاريخ:' : 'Date:' ?></b> <?= h($date ?: '—') ?>
              </div>
              <div class="mt-3">
                <a class="btn btn-primary btn-sm" href="agreement-details.php?code=<?= urlencode($code) ?>&lang=<?= urlencode($lang) ?>">
                  <?= $isArabic ? 'التفاصيل' : 'Details' ?>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sdgEmpty"><?= $isArabic ? 'لا توجد اتفاقيات مطابقة للفلتر.' : 'No agreements match the selected filters.' ?></div>
        <?php endif; ?>
      </div>

    </div>

  </div>
</section>

<script>
function openSdgFilter(e){
  e.stopPropagation();
  document.getElementById('sdgFilterPanel').classList.toggle('active');
}

function closeSdgFilter(){
  document.getElementById('sdgFilterPanel').classList.remove('active');
}

document.addEventListener('click', function(e){
  const panel = document.getElementById('sdgFilterPanel');
  const btn = document.querySelector('.sdgFilterBtn');

  if (panel && btn && !panel.contains(e.target) && !btn.contains(e.target)) {
    panel.classList.remove('active');
  }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>