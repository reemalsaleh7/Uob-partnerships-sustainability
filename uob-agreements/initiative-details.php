<?php
$id = trim($_GET['id'] ?? '');

$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$all = loadAllInitiatives();
$it = null;

foreach ($all as $x) {
  if (trim((string)($x['_id'] ?? '')) === $id) {
    $it = $x;
    break;
  }
}

if (!$it):
?>
  <div class="container my-4">
    <div class="alert alert-warning"><?= $isArabic ? 'لم يتم العثور على المبادرة.' : 'Initiative not found.' ?></div>
  </div>
<?php
require_once __DIR__ . '/footer.php';
exit;
endif;

$s = computeReadyScore($it);

function yesNoBadge(string $v, bool $isArabic = true): string {
  $v = strtolower(trim($v));
  $ok = in_array($v, ['نعم', 'yes', '1', 'true'], true);
  $cls = $ok ? 'text-bg-success' : 'text-bg-secondary';
  $txt = $ok
    ? ($isArabic ? 'نعم' : 'Yes')
    : ($isArabic ? 'لا' : 'No');

  return '<span class="badge ' . $cls . '">' . $txt . '</span>';
}

function listMedia(string $dirAbs, array $exts): array {
  if (!is_dir($dirAbs)) return [];
  $out = [];

  foreach (scandir($dirAbs) as $f) {
    if ($f === '.' || $f === '..') continue;
    $path = $dirAbs . DIRECTORY_SEPARATOR . $f;
    if (!is_file($path)) continue;

    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($e, $exts, true)) $out[] = $f;
  }

  return $out;
}

function pickRandom(array $arr, int $n = 1): array {
  if (!$arr) return [];
  shuffle($arr);
  return array_slice($arr, 0, $n);
}

$bgUrl = 'assets/image/THEM/initiative-details-hero.jpeg';

$title       = trim((string)($it['title'] ?? ''));
$description = trim((string)($it['description'] ?? ''));
$primarySdg  = trim((string)($it['sdg_primary'] ?? ''));
$secondarySdg= trim((string)($it['sdg_secondary'] ?? ''));
$entity      = trim((string)($it['entity'] ?? ''));
$coordinator = trim((string)($it['coordinator'] ?? ''));
$startDate   = trim((string)($it['start_date'] ?? ''));
$endDate     = trim((string)($it['end_date'] ?? ''));
$location    = trim((string)($it['location'] ?? ''));
$type        = trim((string)($it['type'] ?? ''));
$targetGroup = trim((string)($it['target_group'] ?? ''));
$beneficiaries = trim((string)($it['beneficiaries'] ?? ''));
$outputs     = trim((string)($it['outputs'] ?? ''));
$published   = trim((string)($it['published'] ?? ''));
$newsLink    = trim((string)($it['news_link'] ?? ''));
$imagesLink  = trim((string)($it['images_link'] ?? ''));
$supportsQS  = trim((string)($it['supports_qs'] ?? ''));
$supportsGM  = trim((string)($it['supports_greenmetric'] ?? ''));
$supportsTHE = trim((string)($it['supports_the'] ?? ''));
?>

<style>
.detail-grid{
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap:12px;
}
@media (max-width: 768px){
  .detail-grid{ grid-template-columns:1fr; }
}

.info-box{
  background:#fff;
  border:1px solid rgba(230,235,242,.95);
  border-radius:14px;
  box-shadow:var(--shadow-sm);
  padding:12px 14px;
  font-weight:850;
  color:var(--text);
}
.info-box b{ color:var(--uob-navy); }

.link-btns{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:10px;
}
</style>

<section class="sdg-detailHeroX">
  <div class="bg" style="background-image:url('<?= h($bgUrl) ?>')"></div>
  <div class="ov"></div>

  <div class="container" style="position:relative; z-index:1; padding-top:26px; padding-bottom:26px;">
    <div class="card" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
      <div class="kicker"><?= $isArabic ? 'تفاصيل المبادرة' : 'Initiative Details' ?></div>
      <h1><?= h($title ?: '—') ?></h1>
      <div class="line"></div>
      <p><?= h($description ?: ($isArabic ? 'لا يوجد وصف متوفر.' : 'No description available.')) ?></p>

      <div class="meta">
        <span class="pill">
          <?= h($isArabic ? 'SDG الأساسي' : 'Primary SDG') ?>:
          <?= h($primarySdg ?: '—') ?>
        </span>
        <a class="back" href="initiatives.php?lang=<?= urlencode($lang) ?>">
          <?= $isArabic ? '← الرجوع للمبادرات' : '← Back to Initiatives' ?>
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container my-4">

  <div class="row g-4">
    <div class="col-lg-8">

      <div class="uob-card mb-4">
        <div class="uob-card-body" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">

          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="uob-badge"><?= h($primarySdg ?: '—') ?></span>
            <?php if ($secondarySdg !== ''): ?>
              <span class="uob-badge"><?= h($secondarySdg) ?></span>
            <?php endif; ?>
            <?php foreach ($s['tags'] as $t): ?>
              <span class="uob-badge"><?= h($t) ?></span>
            <?php endforeach; ?>
          </div>

          <div class="detail-grid">
            <div class="info-box"><b><?= $isArabic ? 'الجهة المنفذة:' : 'Entity:' ?></b> <?= h($entity ?: '—') ?></div>
            <div class="info-box"><b><?= $isArabic ? 'منسق المبادرة:' : 'Coordinator:' ?></b> <?= h($coordinator ?: '—') ?></div>

            <div class="info-box"><b><?= $isArabic ? 'تاريخ التنفيذ:' : 'Start Date:' ?></b> <?= h($startDate ?: '—') ?></div>
            <div class="info-box"><b><?= $isArabic ? 'تاريخ الانتهاء:' : 'End Date:' ?></b> <?= h($endDate ?: '—') ?></div>

            <div class="info-box"><b><?= $isArabic ? 'الموقع:' : 'Location:' ?></b> <?= h($location ?: '—') ?></div>
            <div class="info-box"><b><?= $isArabic ? 'نوع المبادرة:' : 'Type:' ?></b> <?= h($type ?: '—') ?></div>

            <div class="info-box"><b><?= $isArabic ? 'الفئة المستهدفة:' : 'Target Group:' ?></b> <?= h($targetGroup ?: '—') ?></div>
            <div class="info-box"><b><?= $isArabic ? 'عدد المستفيدين:' : 'Beneficiaries:' ?></b> <?= h($beneficiaries ?: '—') ?></div>
          </div>

        </div>
      </div>

      <div class="uob-card mb-4">
        <div class="uob-card-body" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
          <div class="fw-bold mb-2" style="color:var(--uob-navy); font-weight:950;">
            <?= $isArabic ? 'المخرجات والنتائج' : 'Outputs & Results' ?>
          </div>
          <p class="mb-0" style="font-weight:800; line-height:1.9;">
            <?= h($outputs ?: '—') ?>
          </p>
        </div>
      </div>

      <div class="uob-card">
        <div class="uob-card-body" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
          <div class="fw-bold mb-2" style="color:var(--uob-navy); font-weight:950;">
            <?= $isArabic ? 'التوثيق والنشر' : 'Documentation & Publishing' ?>
          </div>

          <div class="row g-3">
            <div class="col-md-4">
              <div class="info-box">
                <b><?= $isArabic ? 'منشور؟' : 'Published?' ?></b><br>
                <?= yesNoBadge($published, $isArabic) ?>
              </div>
            </div>

            <div class="col-md-4">
              <div class="info-box">
                <b><?= $isArabic ? 'رابط الخبر' : 'News Link' ?></b><br>
                <?= $newsLink
                  ? '<a class="btn btn-outline-primary btn-sm mt-2" href="' . h($newsLink) . '" target="_blank">' . ($isArabic ? 'فتح الرابط' : 'Open Link') . '</a>'
                  : '<span class="text-muted">' . ($isArabic ? 'غير متوفر' : 'Not available') . '</span>' ?>
              </div>
            </div>

            <div class="col-md-4">
              <div class="info-box">
                <b><?= $isArabic ? 'رابط الأدلة' : 'Evidence Link' ?></b><br>
                <?= $imagesLink
                  ? '<a class="btn btn-outline-primary btn-sm mt-2" href="' . h($imagesLink) . '" target="_blank">' . ($isArabic ? 'فتح الرابط' : 'Open Link') . '</a>'
                  : '<span class="text-muted">' . ($isArabic ? 'غير متوفر' : 'Not available') . '</span>' ?>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>

    <div class="col-lg-4">

      <div class="uob-card mb-4">
        <div class="uob-card-body" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
          <div class="fw-bold mb-2" style="color:var(--uob-navy); font-weight:950;">
            <?= $isArabic ? 'الربط بالتصنيفات' : 'Rankings Alignment' ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span><?= $isArabic ? 'يدعم QS؟' : 'Supports QS?' ?></span>
            <?= yesNoBadge($supportsQS, $isArabic) ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-2">
            <span><?= $isArabic ? 'يدعم GreenMetric؟' : 'Supports GreenMetric?' ?></span>
            <?= yesNoBadge($supportsGM, $isArabic) ?>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <span><?= $isArabic ? 'يدعم THE؟' : 'Supports THE?' ?></span>
            <?= yesNoBadge($supportsTHE, $isArabic) ?>
          </div>

          <div class="fw-bold mb-2" style="color:var(--uob-navy); font-weight:950;">
            <?= $isArabic ? 'جاهزية تقريبية' : 'Estimated Readiness' ?>
          </div>

          <div class="mb-2">
            <div class="small text-muted">THE Impact</div>
            <div class="d-flex align-items-center gap-2">
              <div class="progress w-100"><div class="progress-bar" style="width: <?= (int)$s['the'] ?>%"></div></div>
              <span class="small text-muted"><?= (int)$s['the'] ?>%</span>
            </div>
          </div>

          <div class="mb-2">
            <div class="small text-muted">QS</div>
            <div class="d-flex align-items-center gap-2">
              <div class="progress w-100"><div class="progress-bar" style="width: <?= (int)$s['qs'] ?>%"></div></div>
              <span class="small text-muted"><?= (int)$s['qs'] ?>%</span>
            </div>
          </div>

          <div class="mb-0">
            <div class="small text-muted">GreenMetric</div>
            <div class="d-flex align-items-center gap-2">
              <div class="progress w-100"><div class="progress-bar" style="width: <?= (int)$s['gm'] ?>%"></div></div>
              <span class="small text-muted"><?= (int)$s['gm'] ?>%</span>
            </div>
          </div>

        </div>
      </div>

      <div class="uob-card">
        <div class="uob-card-body" style="direction:<?= $isArabic ? 'rtl' : 'ltr' ?>; text-align:<?= $isArabic ? 'right' : 'left' ?>;">
          <div class="fw-bold mb-2" style="color:var(--uob-navy); font-weight:950;">
            <?= $isArabic ? 'الاتفاقية المرتبطة' : 'Related Agreement' ?>
          </div>

          <div class="mb-2"><strong><?= $isArabic ? 'الكود:' : 'Code:' ?></strong> <?= h($it['agreement_code'] ?? $it['_agreement_code'] ?? '—') ?></div>
          <div class="mb-2"><strong><?= $isArabic ? 'الاسم:' : 'Name:' ?></strong> <?= h($it['_agreement_name'] ?? '—') ?></div>

          <?php if (!empty($it['agreement_code']) || !empty($it['_agreement_code'])): ?>
            <a class="btn btn-outline-primary btn-sm" href="agreement-details.php?code=<?= urlencode($it['agreement_code'] ?? $it['_agreement_code']) ?>&lang=<?= urlencode($lang) ?>">
              <?= $isArabic ? 'عرض الاتفاقية' : 'View Agreement' ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>