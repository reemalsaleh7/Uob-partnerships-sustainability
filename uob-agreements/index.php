<?php
// index.php (Landing Home)
$pageTitle = "الرئيسية";
$pageSubtitle = "";
$breadcrumb = [];

// Landing layout: hide title band + use full width
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

/* ========= helpers ========= */
function read_csv_assoc(string $path, int $headerRowIndex = 1): array {
  if (!file_exists($path)) return [];
  $rows = [];
  $fh = fopen($path, 'r');
  if (!$fh) return [];
  $i = 0; $headers = [];
  while (($data = fgetcsv($fh)) !== false) {
    $i++;
    if ($i < $headerRowIndex) continue;
    if ($i === $headerRowIndex) {
      $headers = array_map('trim', $data);
      continue;
    }
    if (!array_filter($data, fn($x)=> trim((string)$x) !== '')) continue;
    $row = [];
    foreach ($headers as $idx => $h) $row[$h] = trim($data[$idx] ?? '');
    $rows[] = $row;
  }
  fclose($fh);
  return $rows;
}

function topN(array $assoc, int $n=3): array {
  arsort($assoc);
  return array_slice($assoc, 0, $n, true);
}

function norm_country(string $c): string {
  $c = trim($c);
  $c = str_replace(['ـ','،'], ['',''], $c);
  return $c;
}

function tl(string $ar, string $en): string {
  $lang = $_SESSION['lang'] ?? 'ar';
  return ($lang === 'en') ? $en : $ar;
}

/* ========= load data ========= */
$agreementsPath = __DIR__ . '/data/agreements.csv';
$agreements = read_csv_assoc($agreementsPath, 1); // header at line 2

$initiativeFiles = glob(__DIR__ . '/data/initiatives*.csv') ?: [];
$allInitiatives = [];
foreach ($initiativeFiles as $file) {
  $lines = file($file);
  if (!$lines) continue;

  $headerLineIndex = 0;
  foreach ($lines as $idx => $line) {
    if (mb_strpos($line, 'رقم المبادرة') !== false) { $headerLineIndex = $idx; break; }
  }
  if ($headerLineIndex === -1) continue;

  $fh = fopen($file, 'r');
  if (!$fh) continue;

  $rowIndex = -1; $headers = [];
  while (($data = fgetcsv($fh)) !== false) {
    $rowIndex++;
    if ($rowIndex < $headerLineIndex) continue;
    if ($rowIndex === $headerLineIndex) {
      $headers = array_map('trim', $data);
      continue;
    }
    if (!array_filter($data, fn($x)=> trim((string)$x) !== '')) continue;
    $row = [];
    foreach ($headers as $hIdx => $h) $row[$h] = trim($data[$hIdx] ?? '');
    $allInitiatives[] = $row;
  }
  fclose($fh);
}

/* ========= totals ========= */
$totalAgreements = count($agreements);
$totalAgreementsActive = 0;
foreach ($agreements as $a) if (trim($a['status'] ?? '') === 'سارية') $totalAgreementsActive++;

$totalInitiatives = count($allInitiatives);

$partnersSet = [];
foreach ($agreements as $a) {
  $p = trim($a['partner_entity'] ?? '');
  if ($p !== '') $partnersSet[$p] = true;
}
$totalPartners = count($partnersSet);

/* ========= country coords (from json + fallback) ========= */
$coordsPath = __DIR__ . '/data/country_coords.json';
$countryCoords = [];
if (file_exists($coordsPath)) {
  $countryCoords = json_decode(file_get_contents($coordsPath), true);
  if (!is_array($countryCoords)) $countryCoords = [];
}

// fallback بسيط (علشان ما تتعطل الخريطة لو ملف json فاضي)
$fallbackCoords = [
  'البحرين' => ['lat'=>26.0667,'lng'=>50.5577],
  'الكويت' => ['lat'=>29.3759,'lng'=>47.9774],
  'السعودية' => ['lat'=>23.8859,'lng'=>45.0792],
  'الإمارات' => ['lat'=>23.4241,'lng'=>53.8478],
  'الإمارات العربية المتحدة' => ['lat'=>23.4241,'lng'=>53.8478],
  'قبرص' => ['lat'=>35.1264,'lng'=>33.4299],
  'الصين' => ['lat'=>35.8617,'lng'=>104.1954],
  'الولايات المتحدة' => ['lat'=>39.8283,'lng'=>-98.5795],
  'إندونيسيا' => ['lat'=>-0.7893,'lng'=>113.9213],
];

/* ========= aggregate by country from agreements ========= */
$byCountry = [];
foreach ($agreements as $a) {
  $country = norm_country($a['country'] ?? '');
  if ($country === '' || $country === 'دولية') continue;

  if (!isset($byCountry[$country])) {
    $byCountry[$country] = [
      'country' => $country,
      'agreements' => 0,
      'partners' => [],
      'active' => 0,
    ];
  }

  $byCountry[$country]['agreements']++;
  if (trim($a['status'] ?? '') === 'سارية') $byCountry[$country]['active']++;

  $partner = trim($a['partner_entity']  ?? '');
  if ($partner !== '') $byCountry[$country]['partners'][$partner] = ($byCountry[$country]['partners'][$partner] ?? 0) + 1;
}

/* ✅ initiatives by country (خارج اللوب) */
$initByCountry = [];
foreach ($allInitiatives as $it) {
  $agreementCode = trim($it['agreement_code'] ?? '');

  if ($agreementCode === '' || !isset($agreements[$agreementCode])) continue;

  $country = norm_country($agreements[$agreementCode]['country'] ?? '');

  if ($country === '' || $country === 'دولية') continue;

  if (!isset($initByCountry[$country])) $initByCountry[$country] = [];

  $initByCountry[$country][] = $it;
}

/* ========= build countries payload ========= */
$countriesPayload = [];

$allCountries = array_unique(array_merge(array_keys($byCountry), array_keys($initByCountry)));
foreach ($allCountries as $country) {
  if ($country === '' || $country === 'دولية') continue;

  $coord = $countryCoords[$country] ?? $fallbackCoords[$country] ?? null;

  // اتفاقيات الدولة
  $agreementsList = [];
  foreach ($agreements as $a) {
    $c = norm_country($a['country'] ?? '');
    if ($c !== $country) continue;
    $agreementsList[] = [
     'code' => $a['agreement_code'] ?? '',
     'name' => $a['agreement_name'] ?? '',
     'partner' => $a['partner_entity'] ?? '',
     'type' => $a['agreement_type'] ?? '',
     'status' => $a['status'] ?? '',
     'start' => $a['start_date'] ?? '',
     'end' => $a['end_date'] ?? '',
     'owner' => $a['owner_entity'] ?? '',
   ];
  }

  // مبادرات الدولة
  $initList = [];
  foreach (($initByCountry[$country] ?? []) as $it) {
    $initList[] = [
      'no' => $it['رقم المبادرة'] ?? '',
      'title' => $it['عنوان المبادرة'] ?? '',
      'org' => $it['الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)'] ?? '',
      'date' => $it['تاريخ تنفيذ المبادرة'] ?? '',
      'sdg' => $it['SDG الأساسي'] ?? '',
      'qs' => $it['هل تدعم QS؟'] ?? '',
      'gm' => $it['هل تدعم GreenMetric؟'] ?? '',
      'the' => $it['هل تدعم THE Impact Ranking؟'] ?? '',
    ];
  }

  $countriesPayload[] = [
    'country' => $country,
    'hasCoord' => $coord ? true : false,
    'lat' => $coord['lat'] ?? null,
    'lng' => $coord['lng'] ?? null,
    'agreementsCount' => count($agreementsList),
    'initiativesCount' => count($initList),
    'agreements' => $agreementsList,
    'initiatives' => $initList,
  ];
}

?>
<style>
  /* HERO VIDEO FOR WEBSITE */
 .uob-hero-unified {
  width: 100%;
  padding: 0 0 100px;
  background: #f7f9fd;
  overflow: hidden;
}
  .uob-hero-unified .container {
  position: relative;
  max-width: 100%;
  width: 100%;
  padding: 0   ;
  margin: 0;
}

  .uob-hero-video-box {
    width: 100vw;
height: clamp(430px, 52vw, 700px);
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    overflow: hidden;
    background: #0b1f3a;
  }

  .uob-hero-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }

.uob-hero-card {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);

  width: min(900px, 90%);
  margin: 0;
  background: transparent;
  border-radius: 0;
  padding: 0 20px;
  text-align: center;
  box-shadow: none;
  z-index: 5;
}
.uob-hero-card .landing-underline {
  margin: 16px auto 0;
}

  .landing-hero-title {
  color: #fdfdfd;
  font-size: 60px;
  font-weight: 950;
  line-height: 1.35;
  margin: 0;
}

  @media (max-width: 768px) {
    .uob-hero-video-box {
      height: 240px;
    }

    .uob-hero-card {
      margin: 22px 16px 0;
      padding: 22px;
    }

  .landing-hero-title {
  color: #c9a227;
  font-size: 52px;
  font-weight: 950;
  line-height: 1.25;
  margin: 0;
  text-shadow: 0 4px 18px rgba(0,0,0,.45);
}

  }
  /* HERO BUTTONS */
.hero-actions-main {
  margin-top: 24px !important;
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 14px !important;
  justify-content: center !important;
  align-items: center !important;
}

.hero-btn-main {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-width: 190px !important;
  min-height: 50px !important;
  padding: 10px 24px !important;
  border-radius: 14px !important;
  font-size: 16px !important;
  font-weight: 950 !important;
  text-decoration: none !important;
  box-shadow: 0 10px 24px rgba(2,8,23,.20) !important;
  transition: .2s ease !important;
}

/* استعراض الاتفاقيات: كحلي والكلام أبيض */
.hero-btn-agreements {
  background: #0b1f3a !important;
  color: #ffffff !important;
  border: 1px solid #0b1f3a !important;
}

/* استعراض المبادرات: أبيض والكلام كحلي */
.hero-btn-initiatives {
  background: #ffffff !important;
  color: #0b1f3a !important;
  border: 1px solid #ffffff !important;
}

.hero-btn-agreements:hover {
  background: #102a4c !important;
  color: #ffffff !important;
}

.hero-btn-initiatives:hover {
  background: #f8fafc !important;
  color: #0b1f3a !important;
}
</style>

<!-- HERO -->
<section class="uob-hero-unified">

  <div class="container">

    <div class="uob-hero-video-box">
      <video class="uob-hero-video" autoplay muted loop playsinline>
<source src="assets/video/hero-video (3).mp4" type="video/mp4">
      </video>
    </div>

    <div class="uob-hero-card">
      <h1 class="landing-hero-title">
        <?= h(tl('بوابة الشراكات والاستدامة في جامعة البحرين', 'University of Bahrain Partnerships and Sustainability Portal')) ?>
      </h1>

      <div class="landing-underline"></div>
<div class="hero-actions-main">
  <a class="hero-btn-main hero-btn-agreements" href="agreements.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>">
    <?= h(tl('استعراض الاتفاقيات', 'Browse Agreements')) ?>
  </a>

  <a class="hero-btn-main hero-btn-initiatives" href="initiatives.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>">
    <?= h(tl('استعراض المبادرات', 'Browse Initiatives')) ?>
  </a>
</div>

</section>

<!-- ABOUT -->
<section class="landing-section" id="about">
  <div class="container">

<div class="landing-text about-text-only uob-reveal zoom">
        <h2 class="landing-h2"><?= h(tl('عن البوابة', 'About the Portal')) ?></h2>

      <div class="landing-underline"></div>

      <p class="landing-p">
        <?= h(tl(
          'بوابة الاستدامة والشراكات في جامعة البحرين هي منصة رسمية لعرض الشراكات والاتفاقيات والمبادرات الأكاديمية المرتبطة بالاستدامة. تساعد البوابة على تنظيم البيانات، رفع الشفافية، وتسهيل الوصول للمعلومات للطلبة والجهات المعنية.',
          'The portal is the official hub for showcasing UOB partnerships, agreements, and sustainability initiatives. It supports transparency, structured documentation, and meaningful collaboration.'
        )) ?>
      </p>
    </div>

  </div>
</section>

<!-- SERVICES WITHOUT IMAGES -->
<section class="landing-section landing-alt" id="services">
  <div class="container">

    <h2 class="landing-h2 text-center"><?= h(tl('الخدمات', 'Services')) ?></h2>
    <div class="landing-underline mx-auto"></div>

    <style>
      .clean-services-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 28px;
        margin-top: 35px;
      }

      .clean-service-card {
        min-height: 310px;
        background: #ffffff;
        border: 1px solid rgba(230,235,242,.95);
        border-radius: 24px;
        padding: 38px 30px;
        text-align: center;
        text-decoration: none !important;
        box-shadow: 0 14px 35px rgba(2,8,23,.08);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: .25s ease;
      }

      .clean-service-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 22px 50px rgba(2,8,23,.14);
      }

      .clean-service-icon {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        background: #0b1f3a;
        color: #faf8f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        font-weight: 950;
        margin-bottom: 24px;
        box-shadow: 0 12px 28px rgba(11,31,58,.20);
      }

      .clean-service-card h3 {
        color: #0b1f3a;
        font-size: 26px;
        font-weight: 950;
        margin-bottom: 16px;
        line-height: 1.4;
      }

      .clean-service-card p {
        color: #334155;
        font-size: 17px;
        font-weight: 800;
        line-height: 1.9;
        margin: 0;
      }

      @media (max-width: 992px) {
        .clean-services-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>

    <div class="clean-services-grid">

      <a class="clean-service-card" href="sdg.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>">
        <div class="clean-service-icon">SDG</div>
        <h3><?= h(tl('عرض SDGs والأثر', 'SDG Reporting & Impact')) ?></h3>
        <p>
          <?= h(tl(
            'صفحات منظمة لأهداف التنمية المستدامة وربطها بالمبادرات والنتائج.',
            'Structured SDG pages linked to initiatives, outcomes, and impact.'
          )) ?>
        </p>
      </a>

      <a class="clean-service-card" href="agreements.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>">
        <div class="clean-service-icon">AGR</div>
        <h3><?= h(tl('دليل الاتفاقيات والشراكات', 'Partnerships & Agreements')) ?></h3>
        <p>
          <?= h(tl(
            'مساحة مركزية لعرض الاتفاقيات ومذكرات التفاهم والجهات المتعاونة.',
            'A centralized directory to browse agreements, MoUs, and partners.'
          )) ?>
        </p>
      </a>

      <a class="clean-service-card" href="initiatives.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>">
        <div class="clean-service-icon">INI</div>
        <h3><?= h(tl('مكتبة المبادرات', 'Initiatives Library')) ?></h3>
        <p>
          <?= h(tl(
            'عرض المبادرات الأكاديمية ومشاريع الاستدامة مع تفاصيل واضحة.',
            'A curated catalogue of initiatives with clear details and evidence.'
          )) ?>
        </p>
      </a>

    </div>

  </div>
</section>
<?php
/* ========= charts aggregates (from agreements) ========= */
$byType = [];
$byStatus = [];
$countryCounts = [];


$sdgCounts = [];

/* ========= SDG counts from initiatives ========= */
foreach ($allInitiatives as $it) {
  $sdgRaw = trim((string)($it['sdg_primary'] ?? ''));

  if ($sdgRaw === '') continue;

  // استخراج أول رقم من النص مثل: "4" أو "SDG 4" أو "الهدف 4: ..."
  if (preg_match('/\d+/', $sdgRaw, $m)) {
    $sdgNo = (int)$m[0];
    if ($sdgNo >= 1 && $sdgNo <= 17) {
      $label = 'SDG ' . $sdgNo;
      $sdgCounts[$label] = ($sdgCounts[$label] ?? 0) + 1;
    }
  }
}

/* رتبي الأهداف من 1 إلى 17 */
uksort($sdgCounts, function($a, $b){
  preg_match('/\d+/', $a, $ma);
  preg_match('/\d+/', $b, $mb);
  return ((int)($ma[0] ?? 0)) <=> ((int)($mb[0] ?? 0));
});

$chart_sdg_labels = array_keys($sdgCounts);
$chart_sdg_values = array_values($sdgCounts);




foreach ($agreements as $a){
  $type = trim($a['agreement_type'] ?? '');
  if ($type === '') $type = 'غير محدد';
  $byType[$type] = ($byType[$type] ?? 0) + 1;

  $st = trim($a['status'] ?? '');
  if ($st === '') $st = 'غير محدد';
  $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;

  $c = norm_country($a['country'] ?? '');
  if ($c === '' ) $c = 'غير محدد';
  if ($c !== 'دولية') $countryCounts[$c] = ($countryCounts[$c] ?? 0) + 1;
}
arsort($countryCounts);
$topCountries = array_slice($countryCounts, 0, 8, true);

$chart_type_labels = array_keys($byType);
$chart_type_values = array_values($byType);

$chart_status_labels = array_keys($byStatus);
$chart_status_values = array_values($byStatus);

$chart_country_labels = array_keys($topCountries);
$chart_country_values = array_values($topCountries);
?>

<!-- INSIGHTS (CHARTS) -->
<section class="landing-section insights-section" id="insights">
  <div class="container">
    <h2 class="landing-h2 text-center uob-reveal"><?= h(tl('لوحة المؤشرات', 'Insights Dashboard')) ?></h2>
    <div class="landing-underline mx-auto uob-reveal"></div>

    <div class="insights-kpis uob-reveal">
      <div class="ins-kpi">
        <div class="ins-num" data-count="<?= (int)$totalAgreements ?>">0</div>
        <div class="ins-lbl"><?= h(tl('إجمالي الاتفاقيات', 'Total Agreements')) ?></div>
      </div>
      <div class="ins-kpi">
        <div class="ins-num" data-count="<?= (int)$totalAgreementsActive ?>">0</div>
        <div class="ins-lbl"><?= h(tl('الاتفاقيات السارية', 'Active Agreements')) ?></div>
      </div>
      <div class="ins-kpi">
        <div class="ins-num" data-count="<?= (int)$totalInitiatives ?>">0</div>
        <div class="ins-lbl"><?= h(tl('المبادرات', 'Initiatives')) ?></div>
      </div>
      <div class="ins-kpi">
        <div class="ins-num" data-count="<?= (int)$totalPartners ?>">0</div>
        <div class="ins-lbl"><?= h(tl('جهات متعاونة', 'Partners')) ?></div>
      </div>
    </div>

    <div class="insights-grid mt-4">
        <div class="ins-card ins-wide uob-reveal">
  <div class="ins-head">
    <div class="ins-title"><?= h(tl('المبادرات حسب أهداف التنمية المستدامة', 'Initiatives by SDGs')) ?></div>
    <div class="ins-sub"><?= h(tl('عدد المبادرات المرتبطة بكل هدف', 'Number of initiatives linked to each SDG')) ?></div>
  </div>
  <div class="ins-body ins-body-lg">
    <canvas id="sdgChart"></canvas>
  </div>
</div>




      <div class="ins-card uob-reveal">
        <div class="ins-head">
          <div class="ins-title"><?= h(tl('توزيع الاتفاقيات حسب الحالة', 'Agreements by Status')) ?></div>
          <div class="ins-sub"><?= h(tl('سارية/مقترحة/غير محددة', 'Active / Proposed / Undefined')) ?></div>
        </div>
        <div class="ins-body">
          <canvas id="statusChart"></canvas>
        </div>
      </div>

      <div class="ins-card uob-reveal">
        <div class="ins-head">
          <div class="ins-title"><?= h(tl('توزيع الاتفاقيات حسب النوع', 'Agreements by Type')) ?></div>
          <div class="ins-sub"><?= h(tl('مذكرة تفاهم/إطار عمل...', 'MOU / Framework ...')) ?></div>
        </div>
        <div class="ins-body">
          <canvas id="typeChart"></canvas>
        </div>
      </div>

      <div class="ins-card ins-wide uob-reveal">
        <div class="ins-head">
          <div class="ins-title"><?= h(tl('أعلى الدول حسب عدد الاتفاقيات', 'Top Countries by Agreements')) ?></div>
          <div class="ins-sub"><?= h(tl('أفضل 8 دول (باستثناء دولية)', 'Top 8 (excluding “International”)')) ?></div>
        </div>
        <div class="ins-body">
          <canvas id="countryChart"></canvas>
        </div>
      </div>
    </div>

  </div>
</section>

<script>
  // PHP -> JS data
  const STATUS_LABELS  = <?= json_encode($chart_status_labels, JSON_UNESCAPED_UNICODE) ?>;
  const STATUS_VALUES  = <?= json_encode($chart_status_values, JSON_UNESCAPED_UNICODE) ?>;

  const TYPE_LABELS    = <?= json_encode($chart_type_labels, JSON_UNESCAPED_UNICODE) ?>;
  const TYPE_VALUES    = <?= json_encode($chart_type_values, JSON_UNESCAPED_UNICODE) ?>;

  const COUNTRY_LABELS = <?= json_encode($chart_country_labels, JSON_UNESCAPED_UNICODE) ?>;
  const COUNTRY_VALUES = <?= json_encode($chart_country_values, JSON_UNESCAPED_UNICODE) ?>;

  const SDG_LABELS     = <?= json_encode($chart_sdg_labels, JSON_UNESCAPED_UNICODE) ?>;
  const SDG_VALUES     = <?= json_encode($chart_sdg_values, JSON_UNESCAPED_UNICODE) ?>;
</script>
<!-- INSIGHTS (CHARTS) -->

</section>
<!-- PARTNERS -->
<section class="landing-section partners-logo-section" id="partners" style="position: relative !important;">

  <a class="partners-view-all"
     style="position: absolute !important; top: 330px !important; left: 55px !important;"
     href="partners.php?lang=<?= h($_SESSION['lang'] ?? 'ar') ?>"
     title="<?= h(tl('عرض كل الشركاء', 'View all partners')) ?>">
    ›
  </a>

  <div class="container">

    <div class="partners-logo-head uob-reveal zoom">
      <h2 class="landing-h2"><?= h(tl('شركاؤنا', 'Our Partners')) ?></h2>
      <div class="landing-underline"></div>

      <p class="partners-logo-sub">
        <?= h(tl(
          'نفخر بشراكاتنا مع مؤسسات أكاديمية وحكومية ومهنية تدعم التعاون والاستدامة.',
          'We are proud of our partnerships with academic, government, and professional organizations that support collaboration and sustainability.'
        )) ?>
      </p>
    </div>

    <div class="partners-marquee uob-reveal zoom">
      <div class="partners-marquee-track">

        <a class="partner-logo-card" href="https://www.customs.gov.bh/" target="_blank" rel="noopener">
          <img src="assets/image/logo/الجمارك.png" alt="الجمارك">
        </a>

        <a class="partner-logo-card" href="https://www.moo.gov.bh/moo/ar/" target="_blank" rel="noopener">
          <img src="assets/image/logo/وزارة النفط والبيئة.png" alt="وزارة النفط والبيئة">
        </a>

        <a class="partner-logo-card" href="https://www.works.gov.bh/English/Pages/home2.aspx" target="_blank" rel="noopener">
          <img src="assets/image/logo/وزارة الاشغال.png" alt="وزارة الاشغال">
        </a>

        <a class="partner-logo-card" href="https://www.hw.ac.uk/" target="_blank" rel="noopener">
          <img src="assets/image/logo/Heriot Watt University.png" alt="Heriot Watt University">
        </a>

        <a class="partner-logo-card" href="https://www.stc.com.bh/" target="_blank" rel="noopener">
          <img src="assets/image/logo/STC.png" alt="STC">
        </a>


        <!-- مكررين عشان الحركة تصير ناعمة -->
        <a class="partner-logo-card" href="https://www.customs.gov.bh/" target="_blank" rel="noopener">
          <img src="assets/image/logo/الجمارك.png" alt="الجمارك">
        </a>

        <a class="partner-logo-card" href="https://www.moo.gov.bh/moo/ar/" target="_blank" rel="noopener">
          <img src="assets/image/logo/وزارة النفط والبيئة.png" alt="وزارة النفط والبيئة">
        </a>

        <a class="partner-logo-card" href="https://www.works.gov.bh/English/Pages/home2.aspx" target="_blank" rel="noopener">
          <img src="assets/image/logo/وزارة الاشغال.png" alt="وزارة الاشغال">
        </a>

        <a class="partner-logo-card" href="https://www.hw.ac.uk/" target="_blank" rel="noopener">
          <img src="assets/image/logo/Heriot Watt University.png" alt="Heriot Watt University">
        </a>

        <a class="partner-logo-card" href="https://www.stc.com.bh/" target="_blank" rel="noopener">
          <img src="assets/image/logo/STC.png" alt="STC">
        </a>

      </div>
    </div>

  </div>
</section>



<!-- Leaflet (pretty tiles) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>


<script>
const COUNTRIES_PAYLOAD = <?= json_encode($countriesPayload, JSON_UNESCAPED_UNICODE) ?>;

function esc(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function openCountryModal(item){
  const ag = item.agreements || [];
  const init = item.initiatives || [];

  let html = `
    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="uob-badge">🤝 اتفاقيات: <b>${ag.length}</b></span>
      <span class="uob-badge">🚀 مبادرات: <b>${init.length}</b></span>
    </div>
  `;

  html += `<h6 style="font-weight:950;color:var(--uob-navy);margin-top:10px;">الاتفاقيات</h6>`;
  if(!ag.length){
    html += `<div class="text-muted" style="font-weight:800;">لا توجد اتفاقيات لهذه الدولة.</div>`;
  }else{
    html += `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
      <thead><tr>
        <th>الكود</th><th>اسم الاتفاقية</th><th>الجهة</th><th>الحالة</th><th>المدة</th>
      </tr></thead><tbody>`;
    ag.forEach(a=>{
      html += `<tr>
        <td style="direction:ltr;font-weight:900;">${esc(a.code)}</td>
        <td style="font-weight:900;">${esc(a.name)}</td>
        <td>${esc(a.partner)}</td>
        <td>${esc(a.status)}</td>
        <td>${esc(a.start)} → ${esc(a.end)}</td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
  }

  html += `<h6 style="font-weight:950;color:var(--uob-navy);margin-top:16px;">المبادرات</h6>`;
  if(!init.length){
    html += `<div class="text-muted" style="font-weight:800;">لا توجد مبادرات لهذه الدولة.</div>`;
  }else{
    html += `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
      <thead><tr>
        <th>#</th><th>عنوان المبادرة</th><th>الجهة</th><th>التاريخ</th><th>SDG</th>
      </tr></thead><tbody>`;
    init.forEach(x=>{
      html += `<tr>
        <td style="font-weight:900;">${esc(x.no)}</td>
        <td style="font-weight:900;">${esc(x.title)}</td>
        <td>${esc(x.org)}</td>
        <td>${esc(x.date)}</td>
        <td>${esc(x.sdg)}</td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
  }

  document.getElementById('countryModalTitle').textContent = item.country;
  document.getElementById('countryModalBody').innerHTML = html;

  const modal = new bootstrap.Modal(document.getElementById('countryModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', () => {
  const mapEl = document.getElementById('worldMap');
  if(!mapEl) return;

  const map = L.map('worldMap', { scrollWheelZoom: false }).setView([20, 15], 2);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  const bounds = [];

  COUNTRIES_PAYLOAD.forEach(item => {
    if(item.lat == null || item.lng == null) return;

    const marker = L.circleMarker([item.lat, item.lng], {
      radius: Math.min(16, 6 + (item.agreementsCount || 0)),
      weight: 2
    }).addTo(map);

    marker.bindTooltip(
      `<b>${esc(item.country)}</b><br>اتفاقيات: ${item.agreementsCount || 0} — مبادرات: ${item.initiativesCount || 0}`,
      { direction:'top', sticky:true, opacity:0.95 }
    );

    marker.on('click', () => openCountryModal(item));
    bounds.push([item.lat, item.lng]);
  });

  if(bounds.length){
    map.fitBounds(bounds, { padding:[30,30] });
  }

  // ✅ مهم: أحياناً ليفلت يحتاج invalidateSize بعد ما يظهر العنصر
  setTimeout(() => map.invalidateSize(), 250);
});
</script>


<!-- Chart.js (مرة واحدة فقط) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
/* ===== Reveal on scroll ===== */
(function(){
  const els = document.querySelectorAll('.uob-reveal');
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if(e.isIntersecting){
        e.target.classList.add('in');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  els.forEach(el=> io.observe(el));
})();

/* ===== Count-up KPIs ===== */
(function(){
  const nums = document.querySelectorAll('.ins-num[data-count]');
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if(!e.isIntersecting) return;
      const el = e.target;
      const to = parseInt(el.getAttribute('data-count') || '0', 10);
      const dur = 900;
      const start = performance.now();
      function tick(t){
        const p = Math.min(1, (t - start)/dur);
        const val = Math.floor(to * (1 - Math.pow(1-p, 3)));
        el.textContent = val.toString();
        if(p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
      io.unobserve(el);
    });
  }, { threshold: 0.5 });
  nums.forEach(n => io.observe(n));
})();

/* ===== Chart defaults ===== */
function chartDefaults(){
  const muted = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#64748b';
  const grid  = 'rgba(15,23,42,.10)';

  Chart.defaults.color = muted;
  Chart.defaults.font.family = '"Cairo", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
  Chart.defaults.plugins.legend.labels.color = muted;

  return { muted, grid };
}

/* ===== Charts (init on view) ===== */
const __charts = {};

function buildStatusChart(){
  const el = document.getElementById('statusChart');
  if(!el || __charts.status) return;

  __charts.status = new Chart(el, {
    type: 'doughnut',
    data: {
      labels: STATUS_LABELS,
      datasets: [{ data: STATUS_VALUES, borderWidth: 2, hoverOffset: 8 }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '62%',
      animation: { duration: 1100, easing: 'easeOutQuart' },
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { rtl: document.documentElement.dir === 'rtl' }
      }
    }
  });
}

function buildTypeColumnChart(){
  const el = document.getElementById('typeChart');
  if(!el || __charts.type) return;
  const { muted, grid } = chartDefaults();

  __charts.type = new Chart(el, {
    type: 'bar',
    data: {
      labels: TYPE_LABELS,
      datasets: [{ data: TYPE_VALUES, borderWidth: 1, borderRadius: 12, maxBarThickness: 56 }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1200,
        easing: 'easeOutQuart',
        delay: (ctx) => (ctx.type === 'data' ? ctx.dataIndex * 120 : 0)
      },
      scales: {
        x: { grid: { display:false }, ticks: { color: muted } },
        y: { beginAtZero:true, grid:{ color:grid }, ticks:{ color: muted, precision:0 } }
      },
      plugins: {
        legend: { display:false },
        tooltip: { rtl: document.documentElement.dir === 'rtl' }
      }
    }
  });
}

function buildCountryAreaChart(){
  const el = document.getElementById('countryChart');
  if(!el || __charts.country) return;
  const { muted, grid } = chartDefaults();

  __charts.country = new Chart(el, {
    type: 'line',
    data: {
      labels: COUNTRY_LABELS,
      datasets: [{
        label: (document.documentElement.dir === 'rtl') ? 'عدد الاتفاقيات' : 'Agreements',
        data: COUNTRY_VALUES,
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 6,
        borderWidth: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 1400, easing: 'easeOutQuart' },
      scales: {
        x: { grid: { display:false }, ticks: { color: muted } },
        y: { beginAtZero:true, grid: { color: grid }, ticks: { color: muted, precision: 0 } }
      },
      plugins: {
        legend: { display: false },
        tooltip: { rtl: document.documentElement.dir === 'rtl' }
      }
    }
  });
}

function buildSdgChart(){
  const el = document.getElementById('sdgChart');
  if(!el || __charts.sdg) return;
  const { muted, grid } = chartDefaults();

  __charts.sdg = new Chart(el, {
    type: 'bar',
    data: {
      labels: SDG_LABELS,
      datasets: [{
        label: (document.documentElement.dir === 'rtl') ? 'عدد المبادرات' : 'Initiatives',
        data: SDG_VALUES,
        borderWidth: 1,
        borderRadius: 14,
        maxBarThickness: 42
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: {
        duration: 1400,
        easing: 'easeOutQuart',
        delay: (ctx) => (ctx.type === 'data' ? ctx.dataIndex * 70 : 0)
      },
      scales: {
        x: {
          grid: { display:false },
          ticks: {
            color: muted,
            maxRotation: 0,
            minRotation: 0
          }
        },
        y: {
          beginAtZero:true,
          grid:{ color:grid },
          ticks:{ color: muted, precision:0 }
        }
      },
      plugins: {
        legend: { display:false },
        tooltip: { rtl: document.documentElement.dir === 'rtl' }
      }
    }
  });
}





function revealChartsOnView(){
  const targets = [
  { id:'statusChart',  build: buildStatusChart },
  { id:'typeChart',    build: buildTypeColumnChart },
  { id:'countryChart', build: buildCountryAreaChart },
  { id:'sdgChart',     build: buildSdgChart },
];


  const io = new IntersectionObserver((entries)=>{
    entries.forEach(e=>{
      if(!e.isIntersecting) return;
      const t = targets.find(x => x.id === e.target.id);
      if(t) t.build();
      io.unobserve(e.target);
    });
  }, { threshold: 0.25 });

  targets.forEach(t=>{
    const el = document.getElementById(t.id);
    if(el) io.observe(el);
  });
}

document.addEventListener('DOMContentLoaded', revealChartsOnView);
</script>


<?php require_once __DIR__ . '/footer.php'; ?>
