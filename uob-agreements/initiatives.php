<?php
$pageTitle = "المبادرات";
$pageSubtitle = "";
$breadcrumb = [
  ['label' => 'المبادرات', 'href' => 'initiatives.php', 'active' => true],
];

$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

/* ======= role ======= */
$isAdmin = (
  (($_SESSION['role'] ?? '') === 'admin') ||
  (($_SESSION['user_email'] ?? '') === 'admin@uob.edu.bh')
);

/* للأدمن: عرض الكل / للمستخدم: عرض المعتمد فقط */
$all = loadAllInitiatives($isAdmin ? false : true);

$initiatives = array_filter($all, function($row) {
  return !empty($row['id']) || !empty($row['_id']);
});

$totalInitiatives = count($initiatives);

/* ======= language ======= */
$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$T = [
  'ar' => [
    'page_title' => 'مبادرات جامعة البحرين',
    'about_title' => 'عن مبادرات جامعة البحرين',
    'about_desc' => 'منصة رقمية لعرض مبادرات جامعة البحرين وربطها بأهداف التنمية المستدامة والاتفاقيات والتصنيفات العالمية، بما يسهّل الاستعراض والتحليل والوصول إلى المعلومات التفصيلية.',
    'total_initiatives' => 'إجمالي المبادرات',
    'filtered_results' => 'نتائج الفلترة',
    'sdgs_count' => 'أهداف التنمية',
    'units_count' => 'الجهات المنفذة',
    'view_initiatives' => 'استعراض المبادرات',
    'view_agreements' => 'استعراض الاتفاقيات',
    'view_sdg' => 'استعراض SDG',
    'add_initiative' => 'إضافة مبادرة جديدة',
    'tag_bilingual' => 'واجهة ثنائية اللغة',
    'tag_search' => 'بحث وتصفية',
    'tag_related' => 'ربط بالاتفاقيات وSDG',
    'tag_institutional' => 'عرض مؤسسي',
    'latest_initiatives' => 'آخر المبادرات',
    'initiative_label' => 'المبادرة',
    'unit_label' => 'الجهة',
    'type_label' => 'النوع',
    'sdg_label' => 'SDG',
    'date_label' => 'تاريخ التنفيذ',
    'agreement_label' => 'الاتفاقية',
    'details' => 'التفاصيل',
    'agreement_details' => 'عرض الاتفاقية',
    'initiatives_section_title' => 'المبادرات',
    'initiatives_section_desc' => 'استعرض مبادرات الجامعة من خلال واجهة تفاعلية مع إمكانية الوصول إلى الجدول الكامل والبحث والتصفية.',
    'play_pause' => 'تشغيل/إيقاف',
    'previous' => 'السابق',
    'next' => 'التالي',
    'no_results' => 'لا توجد نتائج مطابقة.',
    'modal_title' => 'قائمة المبادرات',
    'modal_sub' => 'جدول كامل للمبادرات مع البحث والتصفية',
    'open_filters' => 'فتح أدوات التصفية',
    'close' => 'إغلاق',
    'clear_filters' => 'مسح الفلاتر',
    'search_placeholder' => 'ابحث في المبادرات بالعنوان أو الجهة أو النوع أو SDG',
    'rankings' => 'التصنيفات',
    'initiative_type' => 'نوع المبادرة',
    'unit' => 'الجهة المنفذة',
    'actions' => 'إجراءات',
    'gallery_title' => 'معرض المبادرات',
    'gallery_role' => 'مبادرات جامعة البحرين',
    'gallery_text' => 'مجموعة مختارة من صور المبادرات والأنشطة المرتبطة بجامعة البحرين والاستدامة والمشاركة المجتمعية.',
    'initiative_image_alt' => 'صورة مبادرة',
    'uob_initiatives_alt' => 'مبادرات جامعة البحرين',
  ],
  'en' => [
    'page_title' => 'University of Bahrain Initiatives',
    'about_title' => 'About UOB Initiatives',
    'about_desc' => 'A digital platform for presenting University of Bahrain initiatives and linking them with the Sustainable Development Goals, agreements, and global rankings for easier browsing and analysis.',
    'total_initiatives' => 'Total Initiatives',
    'filtered_results' => 'Filtered Results',
    'sdgs_count' => 'SDGs',
    'units_count' => 'Implementing Units',
    'view_initiatives' => 'View Initiatives',
    'view_agreements' => 'View Agreements',
    'view_sdg' => 'View SDG',
    'add_initiative' => 'Add New Initiative',
    'tag_bilingual' => 'Bilingual Interface',
    'tag_search' => 'Search & Filter',
    'tag_related' => 'Linked to Agreements & SDGs',
    'tag_institutional' => 'Institutional Presentation',
    'latest_initiatives' => 'Latest Initiatives',
    'initiative_label' => 'Initiative',
    'unit_label' => 'Unit',
    'type_label' => 'Type',
    'sdg_label' => 'SDG',
    'date_label' => 'Implementation Date',
    'agreement_label' => 'Agreement',
    'details' => 'Details',
    'agreement_details' => 'View Agreement',
    'initiatives_section_title' => 'Initiatives',
    'initiatives_section_desc' => 'Browse university initiatives through an interactive interface with access to the full table for searching and filtering.',
    'play_pause' => 'Play/Pause',
    'previous' => 'Previous',
    'next' => 'Next',
    'no_results' => 'No matching results found.',
    'modal_title' => 'Initiatives List',
    'modal_sub' => 'Full initiatives table with search and filtering',
    'open_filters' => 'Open Filters',
    'close' => 'Close',
    'clear_filters' => 'Clear Filters',
    'search_placeholder' => 'Search initiatives by title, unit, type, or SDG',
    'rankings' => 'Rankings',
    'initiative_type' => 'Initiative Type',
    'unit' => 'Implementing Unit',
    'actions' => 'Actions',
    'gallery_title' => 'Initiatives Gallery',
    'gallery_role' => 'University of Bahrain Initiatives',
    'gallery_text' => 'A curated selection of initiative and activity images related to UOB, sustainability, and community engagement.',
    'initiative_image_alt' => 'Initiative image',
    'uob_initiatives_alt' => 'University of Bahrain Initiatives',
  ],
];

function tt($key){
  global $T, $lang;
  return $T[$lang][$key] ?? $key;
}

/* ======= helpers ======= */
function parseDateLoose2(string $s): ?DateTime {
  $s = trim($s);
  if ($s === '') return null;

  $formats = ['j-M-y', 'd-M-y', 'j-M-Y', 'd-M-Y', 'Y-m-d', 'm/d/Y', 'n/j/Y', 'd/m/Y', 'j/n/Y'];
  foreach ($formats as $f) {
    $dt = DateTime::createFromFormat($f, $s);
    if ($dt instanceof DateTime) return $dt;
  }

  $ts = strtotime($s);
  if ($ts) return (new DateTime())->setTimestamp($ts);
  return null;
}

function parseDateTs2(string $s): int {
  $dt = parseDateLoose2($s);
  return $dt ? $dt->getTimestamp() : 0;
}

function yesNoLike($v): bool {
  $v = trim((string)$v);
  return in_array($v, ['نعم','Yes','YES','1','true','True'], true);
}

/* ======= filters ======= */
$q = trim($_GET['q'] ?? '');
$ranking = trim($_GET['ranking'] ?? '');
$sdg = trim($_GET['sdg'] ?? '');
$type = trim($_GET['type'] ?? '');
$unit = trim($_GET['unit'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

$filtered = $all;

if ($q !== '') {
  $filtered = array_values(array_filter($filtered, function($it) use ($q){
    $hay = implode(' | ', array_map('strval', $it));
    return mb_stripos($hay, $q) !== false;
  }));
}

if ($sdg !== '') {
  $filtered = array_values(array_filter($filtered, function($it) use ($sdg){
    $sdgs = trim((string)($it['sdgs'] ?? ''));
    return ($sdgs !== '' && mb_stripos($sdgs, $sdg) !== false);
  }));
}

if ($ranking !== '') {
  $filtered = array_values(array_filter($filtered, function($it) use ($ranking){
    $v = '';
    if ($ranking === 'THE') $v = $it['the_support'] ?? '';
    if ($ranking === 'QS') $v = $it['qs_support'] ?? '';
    if ($ranking === 'GreenMetric') $v = $it['greenmetric_support'] ?? '';
    return yesNoLike($v);
  }));
}

if ($type !== '') {
  $filtered = array_values(array_filter($filtered, fn($it) => trim((string)($it['type'] ?? '')) === $type));
}

if ($unit !== '') {
  $filtered = array_values(array_filter($filtered, fn($it) => trim((string)($it['entity'] ?? '')) === $unit));
}

$fromDt = $from ? DateTime::createFromFormat('Y-m-d', $from) : null;
$toDt   = $to ? DateTime::createFromFormat('Y-m-d', $to) : null;

if ($fromDt || $toDt) {
  $filtered = array_values(array_filter($filtered, function($it) use ($fromDt, $toDt){
    $d = parseDateLoose2((string)($it['start_date'] ?? ''));
    if (!$d) return false;
    if ($fromDt && $d < $fromDt) return false;
    if ($toDt) {
      $toEnd = clone $toDt;
      $toEnd->setTime(23,59,59);
      if ($d > $toEnd) return false;
    }
    return true;
  }));
}

/* ======= pagination ======= */
$page = (int)($_GET['page'] ?? 1);
$pg = paginate($filtered, $page, 9);
$items = $pg['items'];

/* ======= options ======= */
$sdgs = [];
$types = [];
$units = [];

foreach ($all as $it) {
  $sdgVal = trim((string)(
  ($it['sdg_primary'] ?? '') . ' | ' . ($it['sdg_secondary'] ?? '')));
  $ttt  = trim((string)($it['type'] ?? ''));
  $uuu  = trim((string)($it['entity'] ?? ''));

  if ($sdgVal) {
    $parts = preg_split('/\s*\|\s*/', $sdgVal);
    foreach ($parts as $p) {
      $p = trim($p);
      if ($p !== '') $sdgs[$p] = true;
    }
  }
  if ($ttt)  $types[$ttt] = true;
  if ($uuu)  $units[$uuu] = true;
}

$sdgOptions  = array_keys($sdgs);
$typeOptions = array_keys($types);
$unitOptions = array_keys($units);

sort($sdgOptions);
sort($typeOptions);
sort($unitOptions);

/* ======= stats ======= */
$totalInitiatives = count($all);
$totalFiltered = count($filtered);
$uniqueSdgs = count($sdgOptions);
$uniqueUnits = count($unitOptions);

/* ======= links ======= */
$listUrl = 'initiatives.php?lang=' . urlencode($lang);
$addUrl  = 'admin/add-initiative.php?lang=' . urlencode($lang);

function listMedia2(string $dirAbs, array $exts): array {
  if (!is_dir($dirAbs)) return [];

  $out = [];

  foreach (scandir($dirAbs) as $f) {

    if ($f === '.' || $f === '..') continue;

    $path = $dirAbs . DIRECTORY_SEPARATOR . $f;

    if (!is_file($path)) continue;

    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));

    if (in_array($e, $exts, true)) {
      $out[] = $f;
    }
  }

  return $out;
}
/* ======= hero image ======= */
$heroImageUrl = 'assets/image/THEM/initinesheader.png';

/* ======= sorted items ======= */
$sliderItems = array_values($all);
usort($sliderItems, function($a, $b){
  $ta = parseDateTs2((string)($a['start_date'] ?? ''));
  $tb = parseDateTs2((string)($b['start_date'] ?? ''));
  return $tb <=> $ta;
});
$sliderItems = array_slice($sliderItems, 0, 12);

$latest = array_values($all);
usort($latest, function($a, $b){
  $ta = parseDateTs2((string)($a['start_date'] ?? ''));
  $tb = parseDateTs2((string)($b['start_date'] ?? ''));
  return $tb <=> $ta;
});
$latest = array_slice($latest, 0, 3);

/* ======= images ======= */
$aboutImg = 'assets/image/hero/UoB_PSSO-1024x576.jpg';

$latestImages = [
  'assets/image/THEM/Initiatives-background1.png',
  'assets/image/THEM/Initiatives-background2.png',
  'assets/image/THEM/Initiatives-background3.png',
];

$eventsDirAbs = __DIR__ . '/assets/image/events';
$eventFiles = listMedia2($eventsDirAbs, ['jpg','jpeg','png','webp','gif']);

$gallerySlides = array_map(function($file){
  return [
    'image' => 'assets/image/events/' . $file,
  ];
}, $eventFiles);
?>

<style>
.sdg-heroX .sdg-heroX-bg{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  object-fit:cover;
  transform:scale(1.03);
}

.init-card .init-top{display:flex;align-items:center;gap:10px;}
.init-card .init-unit{font-weight:950;font-size:12px;color:var(--muted);white-space:nowrap;}
.init-card .init-divider{flex:1;height:1px;background:rgba(13,110,253,.28);}
.init-card .init-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;}
.init-card .init-badge{
  padding:.25rem .65rem;border-radius:999px;background:rgba(201,162,39,.12);
  border:1px solid rgba(201,162,39,.22);color:#6a5200;font-weight:950;font-size:12px;
}
.init-card .init-title{
  margin-top:10px;font-weight:950;color:var(--uob-navy);font-size:18px;line-height:1.6;
  min-height:58px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.init-card .init-desc{
  margin-top:8px;color:#334155;font-weight:800;line-height:1.8;min-height:64px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.init-card .init-meta{margin-top:10px;color:var(--muted);font-weight:850;font-size:13px;line-height:1.9;}
.init-card .init-meta b{color:var(--uob-navy);}
.init-card .init-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;}

.uob-initGallery{
  position:relative;
  padding:64px 0;
  background:#3e7288;
  overflow:hidden;
}

.initGalleryWrap{
  position:relative;
  margin-top:26px;
  overflow:hidden;
  border-radius:10px;
}

.initGalleryTrack{
  display:flex;
  transition:transform .45s ease;
  will-change:transform;
}

.initGallerySlide{
  min-width:100%;
}

.initGalleryCard.only-image{
  background:rgba(255,255,255,.92);
  border:1px solid rgba(255,255,255,.18);
  border-radius:10px;
  box-shadow:0 18px 44px rgba(2,8,23,.22);
  overflow:hidden;
  max-width:1280px;
  margin:0 auto;
  min-height:520px;
}

.initGalleryMedia.only-image{
  width:100%;
  min-height:520px;
  background:#dfe6ec;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}

.initGalleryMedia.only-image img{
  width:100%;
  height:520px;
  object-fit:contain;
  display:block;
  background:#dfe6ec;
}

.initGalleryNav{
  position:absolute;
  top:50%;
  transform:translateY(-50%);
  z-index:2;
  width:42px;
  height:42px;
  border-radius:999px;
  border:1px solid rgba(8,32,51,.35);
  background:rgba(255,255,255,.7);
  color:#3a3a3a;
  font-size:28px;
  line-height:1;
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  transition:.2s ease;
}

.initGalleryNav:hover{
  background:#fff;
  transform:translateY(-50%) scale(1.04);
}

.initGalleryNav.prev{ left:12px; }
.initGalleryNav.next{ right:12px; }

.initGalleryDots{
  display:flex;
  justify-content:center;
  gap:8px;
  margin-top:14px;
}

.initGalleryDots .dotX{
  width:10px;
  height:10px;
  border-radius:999px;
  border:none;
  background:rgba(255,255,255,.5);
  cursor:pointer;
  transition:.2s ease;
}

.initGalleryDots .dotX.on{
  background:#fff;
  transform:scale(1.08);
}

@media (max-width:992px){
  .initGalleryCard.only-image{
    min-height:320px;
  }

  .initGalleryMedia.only-image{
    min-height:320px;
  }

  .initGalleryMedia.only-image img{
    height:320px;
  }
}
.initGalleryEyebrow{
  font-weight:950;
  color:#64748b;
  font-size:12px;
  text-transform:uppercase;
}
.initGalleryContent h3{
  margin:8px 0 0;
  font-weight:950;
  color:#0b1f3a;
  font-size:22px;
  line-height:1.8;
}
.initGalleryContent p{
  margin:14px 0 0;
  color:#0f172a;
  font-weight:800;
  line-height:1.9;
}
.initGalleryActions{
  margin-top:18px;
  display:flex;
  align-items:center;
  justify-content:flex-end;
  gap:12px;
  flex-wrap:wrap;
}
.initGalleryCount{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:92px;
  padding:10px 16px;
  border-radius:999px;
  background:#e7eef5;
  color:#0b1f3a;
  font-weight:900;
  font-size:16px;
}
.initGalleryNav{
  position:absolute;
  top:50%;
  transform:translateY(-50%);
  z-index:2;
  width:42px;
  height:42px;
  border-radius:999px;
  border:1px solid rgba(8,32,51,.35);
  background:rgba(255,255,255,.7);
  color:#3a3a3a;
  font-size:28px;
  line-height:1;
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
}
.initGalleryNav.prev{left:12px;}
.initGalleryNav.next{right:12px;}
.initGalleryDots{
  display:flex;
  justify-content:center;
  gap:8px;
  margin-top:14px;
}
.initGalleryDots .dotX{
  width:10px;height:10px;border-radius:999px;border:none;
  background:rgba(255,255,255,.5);cursor:pointer;
}
.initGalleryDots .dotX.on{background:#fff;transform:scale(1.08);}
@media (max-width:992px){
  .initGalleryCard{grid-template-columns:1fr;}
  .initGalleryMedia{min-height:260px;}
  .initGalleryContent{padding:22px;}
}


</style>

<section class="sdg-heroX">
  <div 
    class="sdg-heroX-bg"
    style="
      background-image:url('<?= h($heroImageUrl) ?>');
      background-size:cover;
      background-position:center;
      background-repeat:no-repeat;
    ">
  </div>

  <div class="sdg-heroX-overlay"></div>

  <div class="container sdg-heroX-container" style="justify-content:flex-start;">
    <div class="sdg-heroX-card uob-reveal in">
      <h1><?= h(tt('page_title')) ?></h1>
      <div class="sdg-heroX-line"></div>

      <div class="sdg-heroX-mini">
        <div class="mini">
          <div class="lbl"><?= h(tt('total_initiatives')) ?></div>
          <div class="val"><?= (int)$totalInitiatives ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('filtered_results')) ?></div>
          <div class="val"><?= (int)$totalFiltered ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('sdgs_count')) ?></div>
          <div class="val"><?= (int)$uniqueSdgs ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('units_count')) ?></div>
          <div class="val"><?= (int)$uniqueUnits ?></div>
        </div>
      </div>

      <div class="landing-actions" style="margin-top:14px;">
  <button type="button" class="btn btn-primary" id="openInitModalChip">
    <?= h(tt('view_initiatives')) ?>
  </button>

  <a class="btn btn-outline-primary" href="request-initiative.php?lang=<?= h($lang) ?>">
    <?= $isArabic ? 'طلب موافقة مبادرة' : 'Request Initiative Approval' ?>
  </a>

  <a class="btn btn-outline-primary" href="admin/add-initiative-approved.php?lang=<?= h($lang) ?>">
    <?= $isArabic ? 'إضافة مبادرة معتمدة' : 'Add Approved Initiative' ?>
  </a>

  <a class="btn btn-outline-primary" href="<?= h($addUrl) ?>">
    <?= $isArabic ? 'إضافة مبادرة قديمة' : 'Add Old Initiative' ?>
  </a>
</div>
    </div>
  </div>
</section>

<section class="sdg-sectionX sdg-sectionX-alt">
  <div class="container">
    <div class="sdg-aboutX">
      <div class="sdg-aboutX-media uob-reveal left">
        <img src="<?= h($aboutImg) ?>" alt="<?= h(tt('uob_initiatives_alt')) ?>">
      </div>

      <div class="sdg-aboutX-content uob-reveal right">
        <h2><?= h(tt('about_title')) ?></h2>
        <div class="sdg-lineX"></div>
        <p><?= h(tt('about_desc')) ?></p>

        
      </div>
    </div>
  </div>
</section>

<section class="sdg-sectionX">
  <div class="container">
    <div class="sdg-centerX">
      <h2><?= h(tt('latest_initiatives')) ?></h2>
      <div class="sdg-lineX center"></div>
    </div>

    <div class="sdg-servicesX">
      <?php foreach ($latest as $index => $it): ?>
        <?php
          $title = trim((string)($it['title'] ?? ''));
          $unitVal = trim((string)($it['entity'] ?? ''));
          $typeVal = trim((string)($it['type'] ?? ''));
          $sdgVal = trim((string)(
         ($it['sdg_primary'] ?? '') . ' | ' . ($it['sdg_secondary'] ?? '')));
          $dateVal = trim((string)($it['start_date'] ?? ''));
          $id = trim((string)($it['id'] ?? ''));
          $img = $latestImages[$index] ?? $latestImages[0];
        ?>
        <a class="sdg-serviceX uob-reveal" href="initiative-details.php?id=<?= urlencode($id) ?>&lang=<?= urlencode($lang) ?>">
          <img src="<?= h($img) ?>" alt="<?= h(tt('initiative_image_alt')) ?>">
          <div class="body">
            <h3><?= h($title ?: '—') ?></h3>
            <p style="margin-top:10px;">
              <b><?= h(tt('unit_label')) ?>:</b> <?= h($unitVal ?: '—') ?><br>
              <b><?= h(tt('type_label')) ?>:</b> <?= h($typeVal ?: '—') ?><br>
              <b><?= h(tt('sdg_label')) ?>:</b> <?= h($sdgVal ?: '—') ?><br>
              <b><?= h(tt('date_label')) ?>:</b> <?= h($dateVal ?: '—') ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sdg-sectionX sdg-sectionX-alt">
  <div class="container">
    <section class="ag-split">
      <aside class="ag-side">
        <h2><?= h(tt('initiatives_section_title')) ?></h2>
        <div class="line"></div>

        <div class="actions hero-actions-init">
  <button type="button" class="hero-btn-init primary" id="openInitModal">
    <?= h(tt('view_initiatives')) ?>
  </button>

  <a class="hero-btn-init secondary" href="request-initiative.php?lang=<?= h($lang) ?>">
    <?= $isArabic ? 'طلب موافقة' : 'Request Approval' ?>
  </a>

  <a class="hero-btn-init secondary" href="admin/add-initiative-approved.php?lang=<?= h($lang) ?>">
    <?= $isArabic ? 'إضافة معتمدة' : 'Add Approved' ?>
  </a>

  <a class="hero-btn-init secondary" href="<?= h($addUrl) ?>">
    <?= $isArabic ? 'إضافة قديمة' : 'Add Old' ?>
  </a>
</div>
      </aside>

      <div class="ag-vline" aria-hidden="true"></div>

      <div class="ag-sliderBox">
        <div class="ag-controls">
          <button type="button" class="ag-btn" id="initPlay" title="<?= h(tt('play_pause')) ?>">⏸</button>
          <button type="button" class="ag-btn" id="initPrev" title="<?= h(tt('previous')) ?>">‹</button>
          <button type="button" class="ag-btn" id="initNext" title="<?= h(tt('next')) ?>">›</button>
        </div>

        <div class="ag-sliderViewport" id="initSliderViewport">
          <div class="ag-track" id="initTrack">
            <?php foreach ($sliderItems as $it): ?>
              <?php
                $title = trim((string)($it['title'] ?? ''));
                $desc  = trim((string)($it['description'] ?? ''));
                $sdgVal = trim((string)($it['sdgs'] ?? ''));
                $typeVal = trim((string)($it['type'] ?? ''));
                $unitVal = trim((string)($it['entity'] ?? ''));
                $dateVal = trim((string)($it['start_date'] ?? ''));
                $agreementCode = trim((string)($it['agreement_code'] ?? ''));
                $id = trim((string)($it['id'] ?? ''));
              ?>
              <article class="ag-card init-card">
                <div class="init-top">
                  <div class="init-unit"><?= h($unitVal ?: '—') ?></div>
                  <div class="init-divider"></div>
                </div>

                <div class="init-badges">
                  <?php if ($sdgVal): ?>
                    <span class="init-badge"><?= h($sdgVal) ?></span>
                  <?php endif; ?>
                  <?php if ($typeVal): ?>
                    <span class="init-badge"><?= h($typeVal) ?></span>
                  <?php endif; ?>
                </div>

                <div class="init-title"><?= h($title ?: '—') ?></div>
                <div class="init-desc"><?= h($desc ?: '—') ?></div>

                <div class="init-meta">
                  <div><b><?= h(tt('date_label')) ?>:</b> <?= h($dateVal ?: '—') ?></div>
                  <div><b><?= h(tt('agreement_label')) ?>:</b> <?= h($agreementCode ?: '—') ?></div>
                </div>

                <div class="init-actions">
                  <a class="btn btn-primary btn-sm" href="initiative-details.php?id=<?= urlencode($id) ?>&lang=<?= urlencode($lang) ?>">
                    <?= h(tt('details')) ?>
                  </a>

                  <?php if ($agreementCode): ?>
                    <a class="btn btn-outline-primary btn-sm" href="agreement-details.php?code=<?= urlencode($agreementCode) ?>&lang=<?= urlencode($lang) ?>">
                      <?= h(tt('agreement_details')) ?>
                    </a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>

            <?php if (!$sliderItems): ?>
              <div class="alert alert-warning w-100"><?= h(tt('no_results')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>

<section class="uob-initGallery">
  <div class="container">
    <div class="sdg-centerX light">
      <h2><?= h(tt('gallery_title')) ?></h2>
      <div class="sdg-lineX center white"></div>
    </div>

    <div class="initGalleryWrap uob-reveal">
      <div class="initGalleryTrack" id="initGalleryTrack">
        <?php foreach ($gallerySlides as $slide): ?>
          <div class="initGallerySlide">
            <div class="initGalleryCard only-image">
              <div class="initGalleryMedia only-image">
                <img src="<?= h($slide['image']) ?>" alt="Event Image">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="initGalleryNav prev" id="initGalleryPrev" type="button" aria-label="<?= h(tt('previous')) ?>">‹</button>
      <button class="initGalleryNav next" id="initGalleryNext" type="button" aria-label="<?= h(tt('next')) ?>">›</button>
    </div>

    <div class="initGalleryDots" id="initGalleryDots"></div>
  </div>
</section>

<div class="initModal" id="initModal" aria-hidden="true">
  <div class="backdrop" id="initModalBackdrop"></div>

  <div class="panel" role="dialog" aria-modal="true" aria-label="<?= h(tt('modal_title')) ?>">
    <div class="head">
      <div class="titleWrap">
        <div>
          <div class="title"><?= h(tt('modal_title')) ?></div>
          <div class="sub"><?= h(tt('modal_sub')) ?></div>
        </div>
      </div>

      <div class="headRight">
        <button class="iconBtn" type="button" id="toggleInitFilters" title="<?= h(tt('open_filters')) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M3 5h18M6 12h12M10 19h4" stroke="#0d4aa7" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
        <button class="iconBtn closeBtn" type="button" id="closeInitModal" title="<?= h(tt('close')) ?>">✕</button>
      </div>
    </div>

    <div class="tools">
      <input class="search" id="initSearch" type="text" placeholder="<?= h(tt('search_placeholder')) ?>" />
      <button class="btn btn-outline-primary" type="button" id="clearInitFilters"><?= h(tt('clear_filters')) ?></button>
    </div>

    <div class="filterDrawer" id="initFilterDrawer">
      <div class="fgrid">
        <div class="fbox">
          <div class="lbl"><?= h(tt('rankings')) ?></div>
          <div class="checks">
            <label class="chk"><input type="checkbox" class="f-rank" value="THE"> THE</label>
            <label class="chk"><input type="checkbox" class="f-rank" value="QS"> QS</label>
            <label class="chk"><input type="checkbox" class="f-rank" value="GreenMetric"> GreenMetric</label>
          </div>
        </div>

        <div class="fbox">
          <div class="lbl"><?= h(tt('sdg_label')) ?></div>
          <div class="checks">
            <?php foreach ($sdgOptions as $opt): ?>
              <label class="chk"><input type="checkbox" class="f-sdg" value="<?= h($opt) ?>"> <?= h($opt) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fbox">
          <div class="lbl"><?= h(tt('initiative_type')) ?></div>
          <div class="checks">
            <?php foreach ($typeOptions as $opt): ?>
              <label class="chk"><input type="checkbox" class="f-type" value="<?= h($opt) ?>"> <?= h($opt) ?></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fbox">
          <div class="lbl"><?= h(tt('unit')) ?></div>
          <div class="checks">
            <?php foreach ($unitOptions as $opt): ?>
              <label class="chk"><input type="checkbox" class="f-unit" value="<?= h($opt) ?>"> <?= h($opt) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="body">
      <table>
        <thead>
          <tr>
            <th style="width:34%"><?= h(tt('initiative_label')) ?></th>
            <th style="width:18%"><?= h(tt('unit_label')) ?></th>
            <th style="width:14%"><?= h(tt('type_label')) ?></th>
            <th style="width:10%"><?= h(tt('sdg_label')) ?></th>
            <th style="width:12%"><?= h(tt('date_label')) ?></th>
            <th style="width:12%"><?= h(tt('actions')) ?></th>
          </tr>
        </thead>

        <tbody id="initTableBody">
          <?php foreach ($all as $it): ?>
            <?php
              $sdgMain = trim((string)($it['sdgs'] ?? ''));
              $typeVal = trim((string)($it['type'] ?? ''));
              $unitVal = trim((string)($it['entity'] ?? ''));
              $title   = trim((string)($it['title'] ?? ''));
              $dateVal = trim((string)($it['start_date'] ?? ''));
              $id = trim((string)($it['id'] ?? ''));

              $the = yesNoLike($it['the_support'] ?? '') ? '1' : '0';
              $qs  = yesNoLike($it['qs_support'] ?? '') ? '1' : '0';
              $gm  = yesNoLike($it['greenmetric_support'] ?? '') ? '1' : '0';

              $searchHay = mb_strtolower($title.' '.$unitVal.' '.$typeVal.' '.$sdgMain.' '.$dateVal);
            ?>
            <tr
              data-search="<?= h($searchHay) ?>"
              data-sdg="<?= h($sdgMain) ?>"
              data-type="<?= h($typeVal) ?>"
              data-unit="<?= h($unitVal) ?>"
              data-the="<?= h($the) ?>"
              data-qs="<?= h($qs) ?>"
              data-gm="<?= h($gm) ?>"
            >
              <td>
                <div style="font-weight:950;color:var(--uob-navy);line-height:1.6">
                  <?= h($title ?: '—') ?>
                </div>
              </td>
              <td><?= h($unitVal ?: '—') ?></td>
              <td><?= h($typeVal ?: '—') ?></td>
              <td>
                <?php if ($sdgMain): ?>
                  <span class="pill"><?= h($sdgMain) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?= h($dateVal ?: '—') ?></td>
              <td>
                <a class="btn btn-primary btn-sm" href="initiative-details.php?id=<?= urlencode($id) ?>&lang=<?= urlencode($lang) ?>">
                  <?= h(tt('details')) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const els = document.querySelectorAll('.uob-reveal');
  if (!('IntersectionObserver' in window)) {
    els.forEach(e => e.classList.add('in'));
    return;
  }

  const io = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.classList.add('in');
      } else {
        entry.target.classList.remove('in');
      }
    });
  }, { threshold: 0.12 });

  els.forEach(el => io.observe(el));
})();
</script>

<script>
(function(){
  const viewport = document.getElementById('initSliderViewport');
  const track = document.getElementById('initTrack');
  const btnPlay = document.getElementById('initPlay');
  const btnPrev = document.getElementById('initPrev');
  const btnNext = document.getElementById('initNext');

  if(!viewport || !track) return;

  const cards = Array.from(track.children).filter(el => el.classList.contains('init-card'));
  if(cards.length === 0) return;

  const isRTL = document.documentElement.dir === 'rtl';
  let playing = true;
  let stepSize = 390;
  let pos = 0;

  function updateStep(){
    const firstCard = track.querySelector('.init-card');
    if(firstCard){
      const style = getComputedStyle(track);
      const gap = parseFloat(style.columnGap || style.gap || 16) || 16;
      stepSize = firstCard.offsetWidth + gap;
    }
  }

  cards.forEach(card => track.appendChild(card.cloneNode(true)));

  function maxOffset(){
    return track.scrollWidth / 2;
  }

  function normalize(){
    const max = maxOffset();
    if (pos >= max) pos = 0;
    if (pos < 0) pos = max - stepSize;
  }

  function apply(){
    track.style.transform = `translate3d(${isRTL ? pos : -pos}px,0,0)`;
  }

  function animate(){
    if(playing){
      pos += 0.45;
      normalize();
      apply();
    }
    requestAnimationFrame(animate);
  }

  function stop(){
    playing = false;
    if(btnPlay) btnPlay.textContent = '▶';
  }

  function start(){
    playing = true;
    if(btnPlay) btnPlay.textContent = '⏸';
  }

  function jumpNext(){
    stop();
    pos += stepSize;
    normalize();
    apply();
  }

  function jumpPrev(){
    stop();
    pos -= stepSize;
    normalize();
    apply();
  }

  updateStep();
  apply();
  animate();

  viewport.addEventListener('mouseenter', stop);
  viewport.addEventListener('mouseleave', start);

  btnPlay && btnPlay.addEventListener('click', ()=>{
    playing = !playing;
    btnPlay.textContent = playing ? '⏸' : '▶';
  });

  btnNext && btnNext.addEventListener('click', ()=>{
    if(isRTL){ jumpPrev(); } else { jumpNext(); }
  });

  btnPrev && btnPrev.addEventListener('click', ()=>{
    if(isRTL){ jumpNext(); } else { jumpPrev(); }
  });

  window.addEventListener('resize', ()=>{
    updateStep();
    normalize();
    apply();
  });
})();
</script>

<script>
(function(){
  const track = document.getElementById('initGalleryTrack');
  const prev  = document.getElementById('initGalleryPrev');
  const next  = document.getElementById('initGalleryNext');
  const dotsWrap = document.getElementById('initGalleryDots');

  if (!track) return;

  const slides = Array.from(track.children);
  const isRTL = (document.documentElement.dir || document.body.dir || 'rtl') === 'rtl';

  let index = 0;
  let autoPlay;

  function renderDots() {
    if (!dotsWrap) return;

    dotsWrap.innerHTML = slides.map((_, i) =>
      `<button class="dotX ${i === 0 ? 'on' : ''}" type="button" aria-label="slide ${i + 1}"></button>`
    ).join('');

    Array.from(dotsWrap.children).forEach((dot, i) => {
      dot.addEventListener('click', () => goTo(i));
    });
  }

  function updateDots() {
    if (!dotsWrap) return;
    Array.from(dotsWrap.children).forEach((dot, i) => {
      dot.classList.toggle('on', i === index);
    });
  }

  function goTo(i) {
    index = (i + slides.length) % slides.length;
    const move = index * 100;

    track.style.transform = isRTL
      ? `translateX(${move}%)`
      : `translateX(-${move}%)`;

    updateDots();
  }

  function nextSlide() { goTo(index + 1); }
  function prevSlide() { goTo(index - 1); }

  function startAuto() {
    stopAuto();
    autoPlay = setInterval(nextSlide, 5000);
  }

  function stopAuto() {
    if (autoPlay) clearInterval(autoPlay);
  }

  prev && prev.addEventListener('click', prevSlide);
  next && next.addEventListener('click', nextSlide);

  track.addEventListener('mouseenter', stopAuto);
  track.addEventListener('mouseleave', startAuto);

  prev && prev.addEventListener('mouseenter', stopAuto);
  next && next.addEventListener('mouseenter', stopAuto);
  prev && prev.addEventListener('mouseleave', startAuto);
  next && next.addEventListener('mouseleave', startAuto);

  renderDots();
  goTo(0);
  startAuto();
})();
</script>

<script>
(function(){
  const modal = document.getElementById('initModal');
  const backdrop = document.getElementById('initModalBackdrop');
  const btnClose = document.getElementById('closeInitModal');
  const btnOpen1 = document.getElementById('openInitModal');
  const btnOpen2 = document.getElementById('openInitModalChip');
  const btnToggleFilters = document.getElementById('toggleInitFilters');
  const drawer = document.getElementById('initFilterDrawer');
  const search = document.getElementById('initSearch');
  const btnClear = document.getElementById('clearInitFilters');
  const tbody = document.getElementById('initTableBody');

  if(!modal || !tbody) return;

  const rows = Array.from(tbody.querySelectorAll('tr'));

  function openModal(){
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
    setTimeout(()=> search && search.focus(), 50);
  }

  function closeModal(){
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
    drawer && drawer.classList.remove('is-open');
  }

  btnOpen1 && btnOpen1.addEventListener('click', openModal);
  btnOpen2 && btnOpen2.addEventListener('click', openModal);
  btnClose && btnClose.addEventListener('click', closeModal);
  backdrop && backdrop.addEventListener('click', closeModal);

  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape') closeModal();
  });

  btnToggleFilters && btnToggleFilters.addEventListener('click', ()=>{
    drawer && drawer.classList.toggle('is-open');
  });

  function getCheckedValues(selector){
    return Array.from(document.querySelectorAll(selector + ':checked')).map(x=>x.value);
  }

  function matchRow(row){
    const q = (search?.value || '').trim().toLowerCase();
    if(q){
      const hay = row.dataset.search || '';
      if(!hay.includes(q)) return false;
    }

    const sdgs  = getCheckedValues('.f-sdg');
    const types = getCheckedValues('.f-type');
    const units = getCheckedValues('.f-unit');
    const ranks = getCheckedValues('.f-rank');

    if(sdgs.length && !sdgs.some(v => (row.dataset.sdg || '').includes(v))) return false;
    if(types.length && !types.includes(row.dataset.type || '')) return false;
    if(units.length && !units.includes(row.dataset.unit || '')) return false;

    if(ranks.length){
      let ok = false;
      ranks.forEach(r=>{
        if(r === 'THE' && row.dataset.the === '1') ok = true;
        if(r === 'QS' && row.dataset.qs === '1') ok = true;
        if(r === 'GreenMetric' && row.dataset.gm === '1') ok = true;
      });
      if(!ok) return false;
    }

    return true;
  }

  function applyFilters(){
    rows.forEach(r=>{
      r.style.display = matchRow(r) ? '' : 'none';
    });
  }

  search && search.addEventListener('input', applyFilters);

  document.querySelectorAll('.f-sdg, .f-type, .f-unit, .f-rank').forEach(el=>{
    el.addEventListener('change', applyFilters);
  });

  btnClear && btnClear.addEventListener('click', ()=>{
    if(search) search.value = '';
    document.querySelectorAll('.f-sdg, .f-type, .f-unit, .f-rank').forEach(el => el.checked = false);
    applyFilters();
  });

  applyFilters();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>