<?php
$pageTitle = "الاتفاقيات";
$pageSubtitle = "";
$breadcrumb = [
  ['label' => 'الاتفاقيات', 'href' => 'agreements.php', 'active' => true],
];
$hidePageHeader = true;
$mainContainer = false;
require_once __DIR__ . '/header.php';

$agreements = readAgreements(true);

/* ======= language ======= */
$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$T = [
  'ar' => [
    'page_title' => 'اتفاقيات جامعة البحرين',
    'about_desc' => 'منصة رقمية لعرض وإدارة اتفاقيات جامعة البحرين، تتيح الوصول إلى بيانات الاتفاقيات واستعراض تفاصيلها وربطها بالمبادرات والأهداف ذات الصلة.',
    'total_agreements' => 'إجمالي الاتفاقيات',
    'active_agreements' => 'الاتفاقيات السارية',
    'countries' => 'الدول',
    'partners' => 'الجهات المتعاونة',
    'view_agreements' => 'استعراض الاتفاقيات',
    'view_initiatives' => 'استعراض المبادرات',
    'add_agreement' => 'إضافة اتفاقية جديدة',
    'about_title' => 'عن اتفاقيات جامعة البحرين',
    'tag_bilingual' => 'واجهة ثنائية اللغة',
    'tag_search' => 'بحث واستعراض',
    'tag_related' => 'بيانات مترابطة',
    'tag_institutional' => 'عرض مؤسسي',
    'latest_agreements' => 'آخر الاتفاقيات',
    'partner_label' => 'الجهة',
    'country_label' => 'الدولة',
    'period_label' => 'الفترة',
    'status_label' => 'الحالة',
    'agreements_section_title' => 'الاتفاقيات',
    'agreements_section_desc' => 'استعرض الاتفاقيات من خلال واجهة تفاعلية، مع إمكانية فتح الجدول الكامل للبحث والتصفية والوصول إلى البيانات التفصيلية.',
    'details' => 'التفاصيل',
    'no_agreements' => 'لا توجد اتفاقيات لعرضها.',
    'partnerships_title' => 'الشراكات والاتفاقيات',
    'partnerships_label' => 'شراكات جامعة البحرين',
    'read_news' => 'قراءة الخبر',
    'modal_title' => 'قائمة الاتفاقيات',
    'modal_sub' => 'جدول الاتفاقيات مع أدوات البحث والتصفية',
    'filter' => 'تصفية',
    'close' => 'إغلاق',
    'search_placeholder' => 'البحث باسم الاتفاقية أو كودها أو الجهة المتعاونة أو الدولة أو النوع',
    'clear_filters' => 'مسح التصفية',
    'agreement_type' => 'نوع الاتفاقية',
    'code' => 'الكود',
    'agreement_name' => 'اسم الاتفاقية',
    'partner' => 'الجهة المتعاونة',
    'country' => 'الدولة',
    'status' => 'الحالة',
    'actions' => 'إجراءات',
    'previous' => 'السابق',
    'next' => 'التالي',
    'play_pause' => 'تشغيل/إيقاف',
    'full_table' => 'الجدول الكامل',
    'search_filter' => 'بحث وتصفية',
    'agreement' => 'اتفاقية',
    'open_filters' => 'فتح أدوات التصفية',
    'agreement_image_alt' => 'اتفاقية',
    'uob_agreements_alt' => 'اتفاقيات جامعة البحرين',
  ],
  'en' => [
    'page_title' => 'University of Bahrain Agreements ',
    'about_desc' => 'A digital platform for presenting and managing University of Bahrain agreements, providing access to agreement data, detailed information, and links to related initiatives and goals.',
    'total_agreements' => 'Total Agreements',
    'active_agreements' => 'Active Agreements',
    'countries' => 'Countries',
    'partners' => 'Partner Entities',
    'view_agreements' => 'View Agreements',
    'view_initiatives' => 'View Initiatives',
    'add_agreement' => 'Add New Agreement',
    'about_title' => 'About the University of Bahrain Agreements',
    'tag_bilingual' => 'Bilingual Interface',
    'tag_search' => 'Search & Browse',
    'tag_related' => 'Linked Data',
    'tag_institutional' => 'Institutional Presentation',
    'latest_agreements' => 'Latest Agreements',
    'partner_label' => 'Partner',
    'country_label' => 'Country',
    'period_label' => 'Period',
    'status_label' => 'Status',
    'agreements_section_title' => 'Agreements',
    'agreements_section_desc' => 'Browse agreements through an interactive interface, with access to the full table for searching, filtering, and viewing detailed information.',
    'details' => 'Details',
    'no_agreements' => 'No agreements available to display.',
    'partnerships_title' => 'Partnerships and Agreements',
    'partnerships_label' => 'University of Bahrain Partnerships',
    'read_news' => 'Read News',
    'modal_title' => 'Agreements List',
    'modal_sub' => 'Agreements table with search and filtering tools',
    'filter' => 'Filter',
    'close' => 'Close',
    'search_placeholder' => 'Search by agreement name, code, partner, country, or type',
    'clear_filters' => 'Clear Filters',
    'agreement_type' => 'Agreement Type',
    'code' => 'Code',
    'agreement_name' => 'Agreement Name',
    'partner' => 'Partner Entity',
    'country' => 'Country',
    'status' => 'Status',
    'actions' => 'Actions',
    'previous' => 'Previous',
    'next' => 'Next',
    'play_pause' => 'Play/Pause',
    'full_table' => 'Full Table',
    'search_filter' => 'Search & Filter',
    'agreement' => 'Agreement',
    'open_filters' => 'Open Filters',
    'agreement_image_alt' => 'Agreement',
    'uob_agreements_alt' => 'University of Bahrain Agreements',
  ],
];

function tt($key) {
  global $T, $lang;
  return $T[$lang][$key] ?? $key;
}

/* ======= search ======= */
$q = trim($_GET['q'] ?? '');
$items = array_values($agreements);
if ($q !== '') {
  $items = array_values(array_filter($items, function($a) use ($q){
    $hay = implode(' | ', $a);
    return mb_stripos($hay, $q) !== false;
  }));
}

/* ======= urls ======= */
$listUrl = 'agreements.php?lang=' . urlencode($lang);
$addUrl  = 'admin/add-agreement.php?lang=' . urlencode($lang);

/* ======= stats ======= */
$totalAgreements = count($agreements);

$activeAgreements = 0;
$countries = [];
$partners  = [];
foreach ($agreements as $a) {
  $status = trim((string)($a['status']?? ''));
  if ($status === 'سارية') $activeAgreements++;

  $c = trim((string)($a['country']?? ''));
  if ($c !== '' && $c !== 'دولية') $countries[$c] = true;

  $p = trim((string)($a['partner_entity'] ?? ''));
  if ($p !== '') $partners[$p] = true;
}
$uniqueCountries = count($countries);
$uniquePartners  = count($partners);

/* ======= filters/options for modal ======= */
$agreementTypes = [];
$agreementCountries = [];
$agreementStatuses = [];

foreach ($agreements as $a) {
  $t = trim((string)($a['agreement_type'] ?? ''));
  $c = trim((string)($a['country'] ?? ''));
  $s = trim((string)($a['status'] ?? ''));

  if ($t !== '') $agreementTypes[$t] = true;
  if ($c !== '') $agreementCountries[$c] = true;
  if ($s !== '') $agreementStatuses[$s] = true;
}

$agreementTypeOptions = array_keys($agreementTypes);
$agreementCountryOptions = array_keys($agreementCountries);
$agreementStatusOptions = array_keys($agreementStatuses);

sort($agreementTypeOptions);
sort($agreementCountryOptions);
sort($agreementStatusOptions);

/* ======= date parser ======= */
function parseDateAny2(string $s): int {
  $s = trim($s);
  if ($s === '') return 0;

  $ts = strtotime($s);
  if ($ts !== false) return $ts;

  $s = str_replace('-', '/', $s);
  $p = explode('/', $s);
  if (count($p) === 3) {
    $m = (int)$p[0];
    $d = (int)$p[1];
    $y = (int)$p[2];
    if ($y > 1900 && $m >= 1 && $m <= 12 && $d >= 1 && $d <= 31) {
      return mktime(0, 0, 0, $m, $d, $y);
    }
  }
  return 0;
}

/* ======= slider items ======= */
$sliderItems = array_values($agreements);
usort($sliderItems, function($a, $b){
  $ta = parseDateAny2((string)($a['start_date'] ?? ''));
  $tb = parseDateAny2((string)($b['start_date'] ?? ''));
  return $tb <=> $ta;
});
$sliderItems = array_slice($sliderItems, 0, 12);

/* ======= latest agreements ======= */
$latest = array_values($agreements);
usort($latest, function($a, $b){
  $ta = parseDateAny2((string)($a['start_date'] ?? ''));
  $tb = parseDateAny2((string)($b['start_date'] ?? ''));
  return $tb <=> $ta;
});
$latest = array_slice($latest, 0, 3);

/* ======= images ======= */
$heroBg  = 'assets/image/THEM/agreements.png';
$aboutImg = 'assets/image/THEM/agreement1.png';
?>

<style>
/* FIX: remove white gap under navbar in Agreements page */
.sdg-heroX{
  padding:0 !important;
  margin:0 !important;
  min-height:520px !important;
  background:#0b1f3a !important;
}

.sdg-heroX-bg{
  inset:0 !important;
  background-size:cover !important;
  background-position:center center !important;
  background-repeat:no-repeat !important;
}

.sdg-heroX-container{
  min-height:520px !important;
  padding:0 !important;
  display:flex !important;
  align-items:center !important;
}
/* AGREEMENTS HERO BUTTONS - LIKE HOME PAGE */
.agreement-hero-actions {
  margin-top: 18px !important;
  display: flex !important;
  flex-wrap: wrap !important;
  gap: 14px !important;
  justify-content: center !important;
  align-items: center !important;
}

.agreement-hero-btn {
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
  border: 0 !important;
  box-shadow: 0 10px 24px rgba(2,8,23,.20) !important;
  transition: .2s ease !important;
}

.agreement-btn-dark {
  background: #0b1f3a !important;
  color: #ffffff !important;
}

.agreement-btn-light {
  background: #ffffff !important;
  color: #0b1f3a !important;
  border: 1px solid rgba(11,31,58,.20) !important;
}

.agreement-btn-dark:hover {
  background: #102a4c !important;
  color: #ffffff !important;
}

.agreement-btn-light:hover {
  background: #f8fafc !important;
  color: #0b1f3a !important;
}

</style>



<section class="sdg-heroX">
  <div class="sdg-heroX-bg" style="background-image:url('<?= h($heroBg) ?>')"></div>
  <div class="sdg-heroX-overlay"></div>

  <div class="container sdg-heroX-container" style="justify-content:flex-start;">
    <div class="sdg-heroX-card uob-reveal in">
      <h1><?= h(tt('page_title')) ?></h1>
      <div class="sdg-heroX-line"></div>

      

      <div class="sdg-heroX-mini">
        <div class="mini">
          <div class="lbl"><?= h(tt('total_agreements')) ?></div>
          <div class="val"><?= (int)$totalAgreements ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('active_agreements')) ?></div>
          <div class="val"><?= (int)$activeAgreements ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('countries')) ?></div>
          <div class="val"><?= (int)$uniqueCountries ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('partners')) ?></div>
          <div class="val"><?= (int)$uniquePartners ?></div>
        </div>
      </div>

      <div class="agreement-hero-actions">
  <button type="button" class="agreement-hero-btn agreement-btn-dark" id="openAgreementModal">
    <?= h(tt('view_agreements')) ?>
  </button>

  <a class="agreement-hero-btn agreement-btn-light" href="initiatives.php?lang=<?= h($lang) ?>">
    <?= h(tt('view_initiatives')) ?>
  </a>
</div>
    </div>
  </div>
</section>

<section class="sdg-sectionX sdg-sectionX-alt">
  <div class="container">
    <div class="sdg-aboutX">
      <div class="sdg-aboutX-media uob-reveal left">
        <img src="<?= h($aboutImg) ?>" alt="<?= h(tt('uob_agreements_alt')) ?>">
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
      <h2><?= h(tt('latest_agreements')) ?></h2>
      <div class="sdg-lineX center"></div>
    </div>

    <div class="sdg-servicesX">
      <?php foreach ($latest as $index => $ag): ?>
  <?php
    $code    = (string)($ag['agreement_code'] ?? '');
    $name    = (string)($ag['agreement_name'] ?? '');
    $partner = (string)($ag['partner_entity'] ?? '');
    $country = (string)($ag['country'] ?? '');
    $status  = (string)($ag['status'] ?? '');
    $start   = (string)($ag['start_date'] ?? '');
    $end     = (string)($ag['end_date'] ?? '');

    $latestImages = [
      'assets/image/THEM/agreement-background1.png',
      'assets/image/THEM/agreement-background2.png',
      'assets/image/THEM/agreement-background3.png',
    ];

    $img = $latestImages[$index] ?? 'assets/image/THEM/agreement-background1.png';
  ?>

        <a class="sdg-serviceX uob-reveal"
           href="agreement-details.php?code=<?= urlencode($code) ?>&lang=<?= urlencode($lang) ?>">

          <img src="<?= h($img) ?>" alt="<?= h(tt('agreement_image_alt')) ?>">

          <div class="body">
            <h3><?= h($name ?: $code) ?></h3>

            <p style="margin-top:10px;">
              <b><?= h(tt('partner_label')) ?>:</b> <?= h($partner ?: '—') ?><br>
              <b><?= h(tt('country_label')) ?>:</b> <?= h($country ?: '—') ?><br>
              <b><?= h(tt('period_label')) ?>:</b> <?= h($start ?: '—') ?> — <?= h($end ?: '—') ?><br>
              <b><?= h(tt('status_label')) ?>:</b> <?= h($status ?: '—') ?>
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
  <div class="ag-side-box">
    <h2><?= h(tt('agreements_section_title')) ?></h2>
    <div class="line"></div>

    <div class="actions hero-actions-init">
      <button type="button" class="hero-btn-init primary" id="openAgreementModalSide">
        <?= h(tt('view_agreements')) ?>
      </button>
    </div>
  </div>
</aside>

      <div class="ag-vline" aria-hidden="true"></div>

      <div class="ag-sliderBox">
        <div class="ag-controls">
          <button type="button" class="ag-btn" id="agPlay" title="<?= h(tt('play_pause')) ?>">⏸</button>
          <button type="button" class="ag-btn" id="agPrev" title="<?= h(tt('previous')) ?>">‹</button>
          <button type="button" class="ag-btn" id="agNext" title="<?= h(tt('next')) ?>">›</button>
        </div>

        <div class="ag-sliderViewport" id="agSliderViewport">
          <div class="ag-track" id="agTrack">
            <?php foreach ($sliderItems as $a): ?>
              <?php
                $code    = trim((string)($a['agreement_code'] ?? ''));
                $name    = trim((string)($a['agreement_name'] ?? ''));
                $type    = trim((string)($a['agreement_type'] ?? ''));
                $partner = trim((string)($a['partner_entity'] ?? ''));
                $country = trim((string)($a['country'] ?? ''));
                $status  = trim((string)($a['status'] ?? ''));
                $start   = trim((string)($a['start_date'] ?? ''));
                $end     = trim((string)($a['end_date'] ?? ''));
              ?>
              <article class="ag-card">
                <div class="ag-top">
                  <div class="ag-country"><?= h($country ?: '—') ?></div>
                  <div class="ag-divider"></div>
                </div>

                <div class="ag-badges">
                  <?php if ($type): ?>
                    <span class="ag-badge"><?= h($type) ?></span>
                  <?php endif; ?>

                  <?php if ($status): ?>
                    <span class="ag-badge status"><?= h($status) ?></span>
                  <?php endif; ?>

                  <?php if ($code): ?>
                    <span class="ag-badge code"><?= h($code) ?></span>
                  <?php endif; ?>
                </div>

                <div class="ag-title"><?= h($name ?: '—') ?></div>

                <div class="ag-desc">
                  <?= h($partner ?: '—') ?>
                </div>

                <div class="ag-meta">
                  <div><b><?= h(tt('partner_label')) ?>:</b> <?= h($partner ?: '—') ?></div>
                  <div><b><?= h(tt('period_label')) ?>:</b> <?= h($start ?: '—') ?> — <?= h($end ?: '—') ?></div>
                </div>

                <div class="ag-actions">
                  <a class="btn btn-primary btn-sm"
                     href="agreement-details.php?code=<?= urlencode($code) ?>&lang=<?= urlencode($lang) ?>">
                    <?= h(tt('details')) ?>
                  </a>
                </div>
              </article>
            <?php endforeach; ?>

            <?php if (!$sliderItems): ?>
              <div class="alert alert-warning w-100"><?= h(tt('no_agreements')) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </section>
  </div>
</section>

<section class="uob-partnerSliderX">
  <div class="container">
    <div class="sdg-centerX light">
      <h2><?= h(tt('partnerships_title')) ?></h2>
      <div class="sdg-lineX center white"></div>
    </div>

    <?php
    $uobPartnerships = [
      [
        'title' => 'جامعة البحرين تنظم برنامجاً لتعزيز القيادة الأكاديمية بالتعاون مع أكاديمية التعليم العالي البريطانية',
        'desc'  => 'خبر شراكة أكاديمية يركز على تطوير القيادة الأكاديمية وتعزيز التعاون الدولي.',
        'title_en' => 'University of Bahrain organizes a program to strengthen academic leadership in cooperation with the British Higher Education Academy',
        'desc_en'  => 'An academic partnership news item focused on developing academic leadership and strengthening international cooperation.',
        'image' => 'assets/image/partnerships/british-academy-leadership.jpg.png',
        'url'   => 'https://www.uob.edu.bh/the-president-of-the-university-of-bahrain-receives-the-director-of-international-partnerships-at-the-british-higher-education-academy/'
      ],
      [
        'title' => 'في إطار التعاون العلمي والتقني مع “سيرن”',
        'desc'  => 'تعاون علمي وتقني يفتح فرصاً بحثية وتدريبية لطلبة الجامعة والباحثين.',
        'title_en' => 'Within the framework of scientific and technical cooperation with CERN',
        'desc_en'  => 'Scientific and technical cooperation that opens research and training opportunities for students and researchers.',
        'image' => 'assets/image/partnerships/cern-research-collaboration.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D9%81%D9%8A-%D8%A5%D8%B7%D8%A7%D8%B1-%D8%A7%D9%84%D8%AA%D8%B9%D8%A7%D9%88%D9%86-%D8%A7%D9%84%D8%B9%D9%84%D9%85%D9%8A-%D9%88%D8%A7%D9%84%D8%AA%D9%82%D9%86%D9%8A-%D9%85%D8%B9-%D8%B3%D9%8A%D8%B1%D9%86/?lang=ar'
      ],
      [
        'title' => 'جامعة الخليج العربي وجامعة البحرين توقعان مذكرة تفاهم لتوسيع آفاق النشر البحثي',
        'desc'  => 'مذكرة تفاهم أكاديمية لتعزيز التعاون في النشر العلمي والبحث المشترك.',
        'title_en' => 'Arabian Gulf University and the University of Bahrain sign an MoU to expand research publishing horizons',
        'desc_en'  => 'An academic memorandum of understanding to enhance cooperation in scientific publishing and joint research.',
        'image' => 'assets/image/partnerships/gulf-university-mou.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-%D8%A7%D9%84%D8%AE%D9%84%D9%8A%D8%AC-%D8%A7%D9%84%D8%B9%D8%B1%D8%A8%D9%8A-%D9%88%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-%D8%A7%D9%84%D8%A8%D8%AD%D8%B1%D9%8A%D9%86-%D8%AA%D9%88/?lang=ar'
      ],
      [
        'title' => 'ورشتين لتعزيز مهارات الطلبة الوظيفية بالتعاون مع إنجاز البحرين',
        'desc'  => 'شراكة مجتمعية تهدف إلى رفع جاهزية الطلبة لسوق العمل وتنمية المهارات المهنية.',
        'title_en' => 'Two workshops to enhance students’ employability skills in cooperation with INJAZ Bahrain',
        'desc_en'  => 'A community partnership aimed at improving student readiness for the labor market and developing professional skills.',
        'image' => 'assets/image/partnerships/injaz-bahrain-workshop.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D9%88%D8%B1%D8%B4%D8%AA%D9%8A%D9%86-%D9%84%D8%AA%D8%B9%D8%B2%D9%8A%D8%B2-%D9%85%D9%87%D8%A7%D8%B1%D8%A7%D8%AA-%D8%A7%D9%84%D8%B7%D9%84%D8%A8%D8%A9-%D8%A7%D9%84%D9%88%D8%B8%D9%8A%D9%81%D9%8A%D8%A9/?lang=ar'
      ],
      [
        'title' => 'جامعة البحرين توقع عقد شراكة مع منصة “أوردرجيت” لخدمة استلام طلبات شراء الوجبات',
        'desc'  => 'شراكة خدمية لتطوير تجربة الطلبة وتحسين الخدمات اليومية داخل الحرم الجامعي.',
        'title_en' => 'University of Bahrain signs a partnership agreement with Orderjet platform for meal pickup services',
        'desc_en'  => 'A service partnership to improve student experience and daily campus services.',
        'image' => 'assets/image/partnerships/orderjet-partnership.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-%D8%A7%D9%84%D8%A8%D8%AD%D8%B1%D9%8A%D9%86-%D8%AA%D9%88%D9%82%D8%B9-%D8%B9%D9%82%D8%AF-%D8%B4%D8%B1%D8%A7%D9%83%D8%A9-%D9%85%D8%B9-%D9%85%D9%86%D8%B5%D8%A9-%D8%A3/?lang=ar'
      ],
      [
        'title' => 'RCSI جامعة البحرين الطبية وجامعة البحرين تجددان شراكتهما الاستراتيجية لتعزيز التعاون الأكاديمي والبحثي',
        'desc'  => 'تجديد شراكة استراتيجية في التعليم والبحث بما يخدم التطوير الأكاديمي والصحي.',
        'title_en' => 'RCSI Medical University of Bahrain and the University of Bahrain renew their strategic partnership to strengthen academic and research cooperation',
        'desc_en'  => 'Renewal of a strategic partnership in education and research supporting academic and health development.',
        'image' => 'assets/image/partnerships/rcsi-bahrain-partnership.jpg.png',
        'url'   => 'https://www.uob.edu.bh/rcsi-%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-%D8%A7%D9%84%D8%A8%D8%AD%D8%B1%D9%8A%D9%86-%D8%A7%D9%84%D8%B7%D8%A8%D9%8A%D8%A9-%D9%88%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-%D8%A7%D9%84%D8%A8%D8%AD%D8%B1%D9%8A%D9%86/?lang=ar'
      ],
      [
        'title' => 'شراكة استراتيجية بين الخدمات الطبية الملكية وجامعة البحرين لتعزيز التعليم الطبي في البحرين',
        'desc'  => 'شراكة استراتيجية تهدف إلى دعم التدريب والتعليم الطبي وتبادل الخبرات.',
        'title_en' => 'A strategic partnership between Royal Medical Services and the University of Bahrain to support medical education in Bahrain',
        'desc_en'  => 'A strategic partnership aimed at supporting medical training, education, and knowledge exchange.',
        'image' => 'assets/image/partnerships/royal-medical-services-uob.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D8%B4%D8%B1%D8%A7%D9%83%D8%A9-%D8%A7%D8%B3%D8%AA%D8%B1%D8%A7%D8%AA%D9%8A%D8%AC%D9%8A%D8%A9-%D8%A8%D9%8A%D9%86-%D8%A7%D9%84%D8%AE%D8%AF%D9%85%D8%A7%D8%AA-%D8%A7%D9%84%D8%B7%D8%A8%D9%8A%D8%A9-%D8%A7/?lang=ar'
      ],
      [
        'title' => 'رئيس جامعة البحرين يبحث تعزيز التعاون الأكاديمي مع جمعية مهندسي البترول العالمية وكلية كولورادو للمعادن',
        'desc'  => 'تعاون أكاديمي متخصص يدعم مجالات الهندسة والطاقة والبحث التطبيقي.',
        'title_en' => 'The President of the University of Bahrain discusses strengthening academic cooperation with the Society of Petroleum Engineers and Colorado School of Mines',
        'desc_en'  => 'Specialized academic cooperation supporting engineering, energy, and applied research.',
        'image' => 'assets/image/partnerships/spe-colorado-mines.jpg.png',
        'url'   => 'https://www.uob.edu.bh/category/partnerships-ar/?lang=ar'
      ],
      [
        'title' => 'برنامج الأمم المتحدة الإنمائي وجامعة البحرين يطلقان برنامجاً أكاديمياً رائداً لأهداف التنمية المستدامة',
        'desc'  => 'تعاون نوعي يربط التعليم الجامعي بأهداف التنمية المستدامة والمبادرات المستقبلية.',
        'title_en' => 'UNDP and the University of Bahrain launch a pioneering academic program for the Sustainable Development Goals',
        'desc_en'  => 'A qualitative collaboration linking higher education with the SDGs and future initiatives.',
        'image' => 'assets/image/partnerships/undp-sdg-program.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D8%A8%D8%B1%D9%86%D8%A7%D9%85%D8%AC-%D8%A7%D9%84%D8%A3%D9%85%D9%85-%D8%A7%D9%84%D9%85%D8%AA%D8%AD%D8%AF%D8%A9-%D8%A7%D9%84%D8%A5%D9%86%D9%85%D8%A7%D8%A6%D9%8A-%D9%88%D8%AC%D8%A7%D9%85%D8%B9%D8%A9-3/?lang=ar'
      ],
      [
        'title' => 'جامعة البحرين تتيح خدمات تطبيق “يونيبال” وخصوماته لطلبتها',
        'desc'  => 'اتفاق يخدم الطلبة عبر توفير خصومات وخدمات متنوعة تدعم الحياة الجامعية.',
        'title_en' => 'University of Bahrain offers Unipal services and discounts to its students',
        'desc_en'  => 'An agreement serving students through discounts and services that support campus life.',
        'image' => 'assets/image/partnerships/unipal-student-services.jpg.png',
        'url'   => 'https://www.uob.edu.bh/%D9%88%D9%82%D8%B9%D8%AA-%D8%A7%D8%AA%D9%81%D8%A7%D9%82%D8%A7%D9%8B-%D9%8A%D9%82%D8%B6%D9%8A-%D8%A8%D8%A7%D9%84%D8%A7%D8%B3%D8%AA%D9%81%D8%A7%D8%AF%D8%A9-%D9%85%D9%86-%D8%B9%D8%B1%D9%88%D8%B6-%D9%86/?lang=ar'
      ],
    ];
    ?>

    <div class="partnerSliderWrapX uob-reveal">
      <div class="partnerSliderTrackX" id="partnerSliderTrackX">
        <?php foreach ($uobPartnerships as $i => $post): ?>
          <div class="partnerSlideX">
            <div class="partnerCardHeroX">
              <div class="partnerMediaX">
                <img src="<?= h($post['image']) ?>" alt="<?= h($isArabic ? $post['title'] : $post['title_en']) ?>">
              </div>

              <div class="partnerContentX">
                <div class="partnerEyebrowX"><?= h(tt('partnerships_label')) ?></div>
                <h3><?= h($isArabic ? $post['title'] : $post['title_en']) ?></h3>
                <p><?= h($isArabic ? $post['desc'] : $post['desc_en']) ?></p>

                <div class="partnerActionsX">
                  <a class="btn btn-primary"
                     href="<?= h($post['url']) ?>"
                     target="_blank"
                     rel="noopener">
                    <?= h(tt('read_news')) ?>
                  </a>

                  <span class="partnerCountX"><?= $i + 1 ?> / <?= count($uobPartnerships) ?></span>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <button class="partnerNavX prev" id="partnerPrevX" type="button" aria-label="<?= h(tt('previous')) ?>">‹</button>
      <button class="partnerNavX next" id="partnerNextX" type="button" aria-label="<?= h(tt('next')) ?>">›</button>
    </div>

    <div class="partnerDotsX" id="partnerDotsX"></div>
  </div>
</section>

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
  const track = document.getElementById('partnerSliderTrackX');
  const prev  = document.getElementById('partnerPrevX');
  const next  = document.getElementById('partnerNextX');
  const dotsWrap = document.getElementById('partnerDotsX');

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
  const viewport = document.getElementById('agSliderViewport');
  const track = document.getElementById('agTrack');
  const btnPlay = document.getElementById('agPlay');
  const btnPrev = document.getElementById('agPrev');
  const btnNext = document.getElementById('agNext');

  if(!viewport || !track) return;

  const cards = Array.from(track.children).filter(el => el.classList.contains('ag-card'));
  if(cards.length === 0) return;

  const isRTL = document.documentElement.dir === 'rtl';
  let playing = true;
  let stepSize = 390;
  let pos = 0;

  function updateStep(){
    const firstCard = track.querySelector('.ag-card');
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

<div class="initModal" id="agreementModal" aria-hidden="true">
  <div class="backdrop" id="agreementModalBackdrop"></div>

  <div class="panel" role="dialog" aria-modal="true" aria-label="<?= h(tt('modal_title')) ?>">
    <div class="head">
      <div class="titleWrap">
        <div>
          <div class="title"><?= h(tt('modal_title')) ?></div>
          <div class="sub"><?= h(tt('modal_sub')) ?></div>
        </div>
      </div>

      <div class="headRight">
        <button class="iconBtn" type="button" id="toggleAgreementFilters" title="<?= h(tt('open_filters')) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M3 5h18M6 12h12M10 19h4" stroke="#0d4aa7" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
        <button class="iconBtn closeBtn" type="button" id="closeAgreementModal" title="<?= h(tt('close')) ?>">✕</button>
      </div>
    </div>

    <div class="tools">
      <input class="search" id="agreementSearch" type="text" placeholder="<?= h(tt('search_placeholder')) ?>" />
      <button class="btn btn-outline-primary" type="button" id="clearAgreementFilters"><?= h(tt('clear_filters')) ?></button>
    </div>

    <div class="filterDrawer" id="agreementFilterDrawer">
      <div class="fgrid">

        <div class="fbox">
          <div class="lbl"><?= h(tt('agreement_type')) ?></div>
          <div class="checks">
            <?php foreach ($agreementTypeOptions as $opt): ?>
              <label class="chk">
                <input type="checkbox" class="f-ag-type" value="<?= h($opt) ?>"> <?= h($opt) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fbox">
          <div class="lbl"><?= h(tt('country')) ?></div>
          <div class="checks">
            <?php foreach ($agreementCountryOptions as $opt): ?>
              <label class="chk">
                <input type="checkbox" class="f-ag-country" value="<?= h($opt) ?>"> <?= h($opt) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="fbox">
          <div class="lbl"><?= h(tt('status')) ?></div>
          <div class="checks">
            <?php foreach ($agreementStatusOptions as $opt): ?>
              <label class="chk">
                <input type="checkbox" class="f-ag-status" value="<?= h($opt) ?>"> <?= h($opt) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>

    <div class="body">
      <table>
        <thead>
          <tr>
            <th style="width:16%"><?= h(tt('code')) ?></th>
            <th style="width:28%"><?= h(tt('agreement_name')) ?></th>
            <th style="width:16%"><?= h(tt('agreement_type')) ?></th>
            <th style="width:20%"><?= h(tt('partner')) ?></th>
            <th style="width:10%"><?= h(tt('country')) ?></th>
            <th style="width:10%"><?= h(tt('status')) ?></th>
            <th style="width:10%"><?= h(tt('actions')) ?></th>
          </tr>
        </thead>

        <tbody id="agreementTableBody">
          <?php foreach ($agreements as $a): ?>
            <?php
             $code    = trim((string)($a['agreement_code'] ?? ''));
             $name    = trim((string)($a['agreement_name'] ?? ''));
             $type    = trim((string)($a['agreement_type'] ?? ''));
             $partner = trim((string)($a['partner_entity'] ?? ''));
             $country = trim((string)($a['country'] ?? ''));
             $status  = trim((string)($a['status'] ?? ''));

              $searchHay = mb_strtolower($code . ' ' . $name . ' ' . $type . ' ' . $partner . ' ' . $country . ' ' . $status);
            ?>
            <tr
              data-search="<?= h($searchHay) ?>"
              data-type="<?= h($type) ?>"
              data-country="<?= h($country) ?>"
              data-status="<?= h($status) ?>"
            >
              <td>
                <?php if ($code): ?>
                  <span class="pill" style="direction:ltr;unicode-bidi:isolate;"><?= h($code) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:950;color:var(--uob-navy);line-height:1.6">
                  <?= h($name ?: '—') ?>
                </div>
              </td>
              <td><?= h($type ?: '—') ?></td>
              <td><?= h($partner ?: '—') ?></td>
              <td><?= h($country ?: '—') ?></td>
              <td><?= h($status ?: '—') ?></td>
              <td>
                <a class="btn btn-primary btn-sm"
                   href="agreement-details.php?code=<?= urlencode($code) ?>&lang=<?= urlencode($lang) ?>">
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
  const modal = document.getElementById('agreementModal');
  const backdrop = document.getElementById('agreementModalBackdrop');
  const btnClose = document.getElementById('closeAgreementModal');
  const btnOpenHero = document.getElementById('openAgreementModal');
  const btnOpenSide = document.getElementById('openAgreementModalSide');
  const btnToggleFilters = document.getElementById('toggleAgreementFilters');
  const drawer = document.getElementById('agreementFilterDrawer');
  const search = document.getElementById('agreementSearch');
  const btnClear = document.getElementById('clearAgreementFilters');
  const tbody = document.getElementById('agreementTableBody');

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

  btnOpenHero && btnOpenHero.addEventListener('click', openModal);
  btnOpenSide && btnOpenSide.addEventListener('click', openModal);
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

    const types = getCheckedValues('.f-ag-type');
    const countries = getCheckedValues('.f-ag-country');
    const statuses = getCheckedValues('.f-ag-status');

    if(types.length && !types.includes(row.dataset.type || '')) return false;
    if(countries.length && !countries.includes(row.dataset.country || '')) return false;
    if(statuses.length && !statuses.includes(row.dataset.status || '')) return false;

    return true;
  }

  function applyFilters(){
    rows.forEach(r=>{
      r.style.display = matchRow(r) ? '' : 'none';
    });
  }

  search && search.addEventListener('input', applyFilters);

  document.querySelectorAll('.f-ag-type, .f-ag-country, .f-ag-status').forEach(el=>{
    el.addEventListener('change', applyFilters);
  });

  btnClear && btnClear.addEventListener('click', ()=>{
    if(search) search.value = '';
    document.querySelectorAll('.f-ag-type, .f-ag-country, .f-ag-status').forEach(el=> el.checked = false);
    applyFilters();
  });

  applyFilters();
})();
</script>
<!-- AGREEMENTS MAP SECTION -->
<section class="sdg-sectionX sdg-sectionX-alt" id="agreements-map">
  <div class="container">

    <div class="sdg-centerX">
      <h2><?= h($isArabic ? 'خريطة الاتفاقيات' : 'Partnership Map') ?></h2>
      <div class="sdg-lineX center"></div>
    </div>

    <div style="margin-top:30px; border-radius:24px; overflow:hidden; background:#ffffff; box-shadow:0 14px 35px rgba(2,8,23,.12);">
      <iframe
        src="partnership/partners.php?embed=1&lang=<?= h($lang) ?>"
        style="width:100%; height:1150px; border:0; display:block;"
        loading="lazy">
      </iframe>
    </div>

  </div>
</section>
<?php require_once __DIR__ . '/footer.php'; ?>
