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
    'total_agreements' => 'إجمــــالي الاتفاقيات',
    'active_agreements' => 'الاتفاقيات السارية',
    'countries' => 'الدول',
    'partners' => 'الجهات المتعاونة',
    'view_agreements' => 'استعراض الاتفاقيات',
    'add_agreement' => 'إضافة اتفاقية جديدة',
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
    'add_agreement' => 'Add New Agreement',
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
$heroBg  = 'assets/image/THEM/agreements (3).png';
$aboutImg = 'assets/image/THEM/agreement1.png';
?>

<style>
.sdg-heroX{
  min-height:650px !important;
  padding:0 !important;
  margin:0 !important;
  background:#0b1f3a !important;
  text-align:center !important;
}

.sdg-heroX-bg{
  inset:0 !important;
  background-size:cover !important;
  background-position:center !important;
  background-repeat:no-repeat !important;
}

.sdg-heroX-container{
  min-height:650px !important;
  padding:0 20px !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
}

.sdg-heroX-card{
  background:transparent !important;
  border:0 !important;
  box-shadow:none !important;
  padding:0 !important;
  margin:0 auto !important;
  max-width:1300px !important;
  text-align:center !important;
}

.sdg-heroX-card h1{
  color:#ffffff !important;
  font-size:56px !important;
  font-weight:950 !important;
  line-height:1.2 !important;
  margin:0 !important;
  white-space:nowrap !important;
}

.sdg-heroX-line{
  width:110px !important;
  height:5px !important;
  border-radius:999px !important;
  background:#b89a68 !important;
  margin:18px auto 36px !important;
}

.sdg-heroX-mini{
  display:grid !important;
  grid-template-columns:repeat(4, 170px) !important;
  justify-content:center !important;
  gap:40px !important;
  margin:0 auto 36px !important;
  direction:rtl !important;
}

.sdg-heroX-mini .mini{
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  justify-content:center !important;
  text-align:center !important;
  gap:10px !important;
  background:transparent !important;
  border:0 !important;
  box-shadow:none !important;
  padding:0 !important;
}

.sdg-heroX-mini .mini .lbl{
  order:1 !important;
  color:rgba(255,255,255,.85) !important;
  font-size:20px !important;
  font-weight:900 !important;
  line-height:1.4 !important;
}

.sdg-heroX-mini .mini .val{
  order:2 !important;
  color:#ffffff !important;
  font-size:54px !important;
  font-weight:950 !important;
  line-height:1 !important;
}

.agreement-hero-actions{
  display:flex !important;
  justify-content:center !important;
  align-items:center !important;
  gap:16px !important;
  flex-wrap:wrap !important;
}

.agreement-hero-btn{
  min-width:499px !important;
  min-height:54px !important;
  border-radius:16px !important;
  font-size:20px !important;
  font-weight:950 !important;
  display:inline-flex !important;
  align-items:center !important;
  justify-content:center !important;
  text-decoration:none !important;
}

.agreement-btn-dark{
  background:#b89a68 !important;
  color:#ffffff !important;
  border:0 !important;
}

.agreement-btn-light{
  background:transparent !important;
  color:#ffffff !important;
border:2px solid rgba(255,255,255,.45) !important;}

@media(max-width:992px){
  .sdg-heroX-card h1{
    white-space:normal !important;
    font-size:42px !important;
  }

  .sdg-heroX-mini{
    gap:28px !important;
  }

  .sdg-heroX-mini .mini .val{
    font-size:42px !important;
  }
}
html body .sdg-heroX-container{
  width:100% !important;
  max-width:100% !important;
  margin:0 auto !important;
  padding:0 !important;
  display:flex !important;
  justify-content:center !important;
  align-items:center !important;
}

html body .sdg-heroX-card{
  width:100% !important;
  max-width:950px !important;
  margin:0 auto !important;
  display:flex !important;
  flex-direction:column !important;
  align-items:center !important;
  text-align:center !important;
}

html body .sdg-heroX-mini{
  width:fit-content !important;
  max-width:100% !important;
  display:grid !important;
  grid-template-columns:repeat(4, 150px) !important;
  gap:36px !important;
  justify-content:center !important;
  align-items:start !important;
  margin:0 auto 34px !important;
}

html body .sdg-heroX-mini .mini{
  width:150px !important;
  text-align:center !important;
}

html body .agreement-hero-actions{
  width:100% !important;
  display:flex !important;
  justify-content:center !important;
}

.agreement-hero-btn{
  transition:transform .45s ease !important;
}

.agreement-hero-btn:hover{
  transform:scale(1.03) !important;
}
/* ===== Latest Agreements News Slider ===== */

.ag-news-section{
  background:#f8fafc !important;
  padding-top:90px !important;
  padding-bottom:90px !important;
}

.ag-news-wrap{
  position:relative !important;
  margin-top:38px !important;
}

.ag-news-viewport{
  width:100% !important;
  overflow:hidden !important;
  padding:8px 4px 18px !important;
}

.ag-news-track{
  display:flex !important;
  gap:26px !important;
  direction:ltr !important;
  transition:transform .55s ease !important;
  will-change:transform !important;
}

.ag-news-card{
  flex:0 0 calc((100% - 52px) / 3) !important;
  height:520px !important;
  background:#ffffff !important;
  border:1px solid rgba(184,154,104,.45) !important;
  border-radius:22px !important;
  overflow:hidden !important;
  text-decoration:none !important;
  box-shadow:0 14px 34px rgba(2,8,23,.08) !important;
  display:flex !important;
  flex-direction:column !important;
  transition:transform .35s ease, box-shadow .35s ease !important;
}

.ag-news-card:hover{
  transform:translateY(-6px) !important;
  box-shadow:0 22px 48px rgba(2,8,23,.14) !important;
}

.ag-news-image{
  width:100% !important;
  height:220px !important;
  background:#eef2f6 !important;
  overflow:hidden !important;
  flex-shrink:0 !important;
}

.ag-news-image img{
  width:100% !important;
  height:100% !important;
  object-fit:cover !important;
  display:block !important;
}

.ag-news-placeholder{
  width:100% !important;
  height:100% !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
  background:linear-gradient(135deg,#eef2f6 0%,#ffffff 100%) !important;
  color:#0b1f3a !important;
  font-size:20px !important;
  font-weight:950 !important;
}

.ag-news-body{
  padding:24px 24px 22px !important;
  flex:1 !important;
  display:flex !important;
  flex-direction:column !important;
  direction:rtl !important;
  text-align:right !important;
}

html[dir="ltr"] .ag-news-body{
  direction:ltr !important;
  text-align:left !important;
}

.ag-news-label{
  color:#b89a68 !important;
  font-size:14px !important;
  font-weight:950 !important;
  margin-bottom:10px !important;
}

.ag-news-body h3{
  color:#0b1f3a !important;
  font-size:22px !important;
  font-weight:950 !important;
  line-height:1.55 !important;
  margin:0 0 12px !important;
  min-height:68px !important;

  display:-webkit-box !important;
  -webkit-line-clamp:2 !important;
  -webkit-box-orient:vertical !important;
  overflow:hidden !important;
}

.ag-news-body p{
  color:#475569 !important;
  font-size:15px !important;
  font-weight:750 !important;
  line-height:1.8 !important;
  margin:0 0 16px !important;
  min-height:80px !important;

  display:-webkit-box !important;
  -webkit-line-clamp:3 !important;
  -webkit-box-orient:vertical !important;
  overflow:hidden !important;
}

.ag-news-empty-desc{
  visibility:hidden !important;
}

.ag-news-badges{
  display:flex !important;
  gap:8px !important;
  flex-wrap:wrap !important;
  margin-bottom:18px !important;
}

.ag-news-badge{
  background:#eef2f6 !important;
  color:#0b1f3a !important;
  border-radius:999px !important;
  padding:7px 14px !important;
  font-size:13px !important;
  font-weight:900 !important;
}

.ag-news-badge.gold{
  background:rgba(184,154,104,.16) !important;
  color:#8f6f3f !important;
}

.ag-news-footer{
  margin-top:auto !important;
}

.ag-news-read{
  color:#b89a68 !important;
  font-size:16px !important;
  font-weight:950 !important;
  text-decoration:none !important;
}

.ag-news-read:hover{
  color:#0b1f3a !important;
}

.ag-news-arrow{
  position:absolute !important;
  top:50% !important;
  transform:translateY(-50%) !important;
  width:52px !important;
  height:52px !important;
  border-radius:50% !important;
  border:2px solid #b89a68 !important;
  background:#ffffff !important;
  color:#8f6f3f !important;
  font-size:36px !important;
  font-weight:900 !important;
  line-height:1 !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
  z-index:10 !important;
  cursor:pointer !important;
  box-shadow:0 10px 24px rgba(2,8,23,.10) !important;
}

.ag-news-arrow:hover{
  background:#b89a68 !important;
  color:#ffffff !important;
}

html[dir="rtl"] .ag-news-prev{
  right:-26px !important;
  left:auto !important;
}

html[dir="rtl"] .ag-news-next{
  left:-26px !important;
  right:auto !important;
}

html[dir="ltr"] .ag-news-prev{
  left:-26px !important;
  right:auto !important;
}

html[dir="ltr"] .ag-news-next{
  right:-26px !important;
  left:auto !important;
}

@media(max-width:992px){
  .ag-news-card{
    flex:0 0 calc((100% - 26px) / 2) !important;
  }
}

@media(max-width:700px){
  .ag-news-card{
    flex:0 0 100% !important;
  }

  .ag-news-arrow{
    width:44px !important;
    height:44px !important;
    font-size:30px !important;
  }

  html[dir="rtl"] .ag-news-prev{
    right:8px !important;
  }

  html[dir="rtl"] .ag-news-next{
    left:8px !important;
  }

  html[dir="ltr"] .ag-news-prev{
    left:8px !important;
  }

  html[dir="ltr"] .ag-news-next{
    right:8px !important;
  }
}
/* ===== Smaller Professional Latest Agreements Cards ===== */

html body .ag-news-section{
  background:#f8fafc !important;
  padding-top:65px !important;
  padding-bottom:65px !important;
}

html body .ag-news-section .sdg-centerX h2{
  font-size:38px !important;
  font-weight:950 !important;
  color:#0b1f3a !important;
}

html body .ag-news-section .sdg-lineX.center{
  width:95px !important;
  height:4px !important;
  background:#b89a68 !important;
  margin-top:12px !important;
}

html body .ag-news-wrap{
  position:relative !important;
  margin-top:32px !important;
}

html body .ag-news-viewport{
  width:100% !important;
  overflow:hidden !important;
  padding:6px 2px 14px !important;
}

html body .ag-news-track{
  display:flex !important;
  gap:22px !important;
  direction:ltr !important;
  transition:transform .55s ease !important;
  will-change:transform !important;
}

html body .ag-news-card{
  flex:0 0 calc((100% - 44px) / 3) !important;
  height:430px !important;
  background:#ffffff !important;
  border:none !important;
  border-radius:18px !important;
  overflow:hidden !important;
  text-decoration:none !important;
  box-shadow:0 8px 22px rgba(2,8,23,.07) !important;
  display:flex !important;
  flex-direction:column !important;
  transition:transform .35s ease, box-shadow .35s ease, outline .35s ease !important;
  outline:1px solid transparent !important;
}

html body .ag-news-card:hover{
  transform:translateY(-4px) !important;
  box-shadow:0 12px 28px rgba(2,8,23,.10) !important;
  outline:1px solid rgba(184,154,104,.55) !important;
}

html body .ag-news-image{
  width:100% !important;
  aspect-ratio:16 / 9 !important;
  height:auto !important;
  max-height:175px !important;
  background:#F1F3F5 !important;
  overflow:hidden !important;
  flex-shrink:0 !important;
}

html body .ag-news-image img{
  width:100% !important;
  height:100% !important;
  object-fit:cover !important;
  display:block !important;
}

html body .ag-news-placeholder{
  width:100% !important;
  height:100% !important;
  background:#F1F3F5 !important;
  color:#8a94a3 !important;
  font-size:14px !important;
  font-weight:700 !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
}

html body .ag-news-body{
  padding:17px 18px 18px !important;
  flex:1 !important;
  display:flex !important;
  flex-direction:column !important;
  direction:rtl !important;
  text-align:right !important;
}

html[dir="ltr"] body .ag-news-body{
  direction:ltr !important;
  text-align:left !important;
}

html body .ag-news-label{
  color:#b89a68 !important;
  font-size:12px !important;
  font-weight:850 !important;
  margin-bottom:7px !important;
  line-height:1.4 !important;
}

html body .ag-news-body h3{
  color:#0b1f3a !important;
  font-size:18px !important;
  font-weight:950 !important;
  line-height:1.45 !important;
  margin:0 0 10px !important;
  min-height:52px !important;

  display:-webkit-box !important;
  -webkit-line-clamp:2 !important;
  -webkit-box-orient:vertical !important;
  overflow:hidden !important;
}

html body .ag-news-body p{
  color:#64748b !important;
  font-size:13.5px !important;
  font-weight:650 !important;
  line-height:1.65 !important;
  margin:0 0 12px !important;
  min-height:44px !important;

  display:-webkit-box !important;
  -webkit-line-clamp:2 !important;
  -webkit-box-orient:vertical !important;
  overflow:hidden !important;
}

html body .ag-news-empty-desc{
  visibility:hidden !important;
}

html body .ag-news-badges{
  display:flex !important;
  gap:7px !important;
  flex-wrap:wrap !important;
  margin-bottom:12px !important;
}

html body .ag-news-badge{
  background:#eef2f6 !important;
  color:#0b1f3a !important;
  border-radius:999px !important;
  padding:5px 11px !important;
  font-size:11.5px !important;
  font-weight:850 !important;
  line-height:1.3 !important;
}

html body .ag-news-badge.gold{
  background:rgba(184,154,104,.14) !important;
  color:#8f6f3f !important;
}

html body .ag-news-footer{
  margin-top:auto !important;
}

html body .ag-news-read{
  color:#8f6f3f !important;
  font-size:14px !important;
  font-weight:900 !important;
  text-decoration:none !important;
}

html body .ag-news-card:hover .ag-news-read{
  color:#0b1f3a !important;
}

html body .ag-news-arrow{
  position:absolute !important;
  top:50% !important;
  transform:translateY(-50%) !important;
  width:42px !important;
  height:42px !important;
  border-radius:50% !important;
  border:1.5px solid #b89a68 !important;
  background:#ffffff !important;
  color:#8f6f3f !important;
  font-size:28px !important;
  font-weight:900 !important;
  line-height:1 !important;
  display:flex !important;
  align-items:center !important;
  justify-content:center !important;
  z-index:10 !important;
  cursor:pointer !important;
  box-shadow:0 8px 18px rgba(2,8,23,.08) !important;
  transition:.25s ease !important;
}

html body .ag-news-arrow:hover{
  background:#b89a68 !important;
  color:#ffffff !important;
}

html[dir="rtl"] body .ag-news-prev{
  right:-21px !important;
  left:auto !important;
}

html[dir="rtl"] body .ag-news-next{
  left:-21px !important;
  right:auto !important;
}

html[dir="ltr"] body .ag-news-prev{
  left:-21px !important;
  right:auto !important;
}

html[dir="ltr"] body .ag-news-next{
  right:-21px !important;
  left:auto !important;
}

@media(max-width:992px){
  html body .ag-news-card{
    flex:0 0 calc((100% - 22px) / 2) !important;
  }
}

@media(max-width:700px){
  html body .ag-news-section{
    padding-top:50px !important;
    padding-bottom:50px !important;
  }

  html body .ag-news-section .sdg-centerX h2{
    font-size:30px !important;
  }

  html body .ag-news-card{
    flex:0 0 100% !important;
    height:410px !important;
  }

  html body .ag-news-arrow{
    width:38px !important;
    height:38px !important;
    font-size:25px !important;
  }

  html[dir="rtl"] body .ag-news-prev{
    right:6px !important;
  }

  html[dir="rtl"] body .ag-news-next{
    left:6px !important;
  }

  html[dir="ltr"] body .ag-news-prev{
    left:6px !important;
  }

  html[dir="ltr"] body .ag-news-next{
    right:6px !important;
  }
}
</style>



<section class="sdg-heroX">
  <div class="sdg-heroX-bg" style="background-image:url('<?= h($heroBg) ?>')"></div>
  <div class="sdg-heroX-overlay"></div>

<div class="container sdg-heroX-container">
      <div class="sdg-heroX-card uob-reveal in">
      <h1><?= h(tt('page_title')) ?></h1>
      <div class="sdg-heroX-line"></div>

      
<div class="sdg-heroX-mini">

  <div class="mini">
    <span class="lbl"><?= h(tt('total_agreements')) ?></span>
    <span class="val counter" data-target="<?= (int)$totalAgreements ?>">0</span>
  </div>

  <div class="mini">
    <span class="lbl"><?= h(tt('active_agreements')) ?></span>
    <span class="val counter" data-target="<?= (int)$activeAgreements ?>">0</span>
  </div>

  <div class="mini">
    <span class="lbl"><?= h($isArabic ? 'عدد الدول المشاركة' : 'Countries') ?></span>
    <span class="val counter" data-target="<?= (int)$uniqueCountries ?>">0</span>
  </div>

  <div class="mini">
    <span class="lbl"><?= h($isArabic ? 'الجهـــات الشريكة' : 'Partner Entities') ?></span>
    <span class="val counter" data-target="<?= (int)$uniquePartners ?>">0</span>
  </div>

</div>
  
      <div class="agreement-hero-actions">
  <button type="button" class="agreement-hero-btn agreement-btn-dark" id="openAgreementModal">
    <?= h(tt('view_agreements')) ?>
  </button>

  
    </div>
  </div>
</section>
<section class="sdg-sectionX ag-news-section">
  <div class="container">

    <div class="sdg-centerX">
      <h2><?= h(tt('latest_agreements')) ?></h2>
      <div class="sdg-lineX center"></div>
    </div>

    <div class="ag-news-wrap" id="agNewsWrap">

      <button class="ag-news-arrow ag-news-prev" type="button" id="agNewsPrev">‹</button>

      <div class="ag-news-viewport">
        <div class="ag-news-track" id="agNewsTrack">

          <?php foreach ($sliderItems as $ag): ?>
            <?php
              $code    = trim((string)($ag['agreement_code'] ?? ''));
              $name    = trim((string)($ag['agreement_name'] ?? ''));
              $country = trim((string)($ag['country'] ?? ''));
              $status  = trim((string)($ag['status'] ?? ''));

              /* استخدم صورة حقيقية فقط إذا كانت موجودة في بيانات الاتفاقية */
              $imagePath = '';
              foreach (['image','image_url','image_path','cover_image','agreement_image','news_image','photo'] as $imgKey) {
                if (!empty($ag[$imgKey])) {
                  $imagePath = trim((string)$ag[$imgKey]);
                  break;
                }
              }

              /* نبذة قصيرة فقط إذا كانت موجودة في البيانات */
              $desc = '';
              foreach (['description','summary','brief','agreement_description','notes'] as $descKey) {
                if (!empty($ag[$descKey])) {
                  $desc = trim((string)$ag[$descKey]);
                  break;
                }
              }
            ?>

            <a class="ag-news-card"
               href="agreement-details.php?code=<?= urlencode($code) ?>&lang=<?= urlencode($lang) ?>">

              <div class="ag-news-image">
                <?php if ($imagePath !== ''): ?>
                  <img src="<?= h($imagePath) ?>" alt="<?= h($name ?: tt('agreement_image_alt')) ?>">
                <?php else: ?>
                  <div class="ag-news-placeholder">
                    <?= h($isArabic ? 'لا توجد صورة' : 'No Image Available') ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="ag-news-body">
                <div class="ag-news-label">
                  <?= h($isArabic ? 'اتفاقية' : 'Agreement') ?>
                </div>

                <h3><?= h($name ?: $code) ?></h3>

                <?php if ($desc !== ''): ?>
                  <p><?= h($desc) ?></p>
                <?php else: ?>
                  <p class="ag-news-empty-desc">.</p>
                <?php endif; ?>

                <div class="ag-news-badges">
                  <?php if ($status !== ''): ?>
                    <span class="ag-news-badge gold"><?= h($status) ?></span>
                  <?php endif; ?>

                  <?php if ($country !== ''): ?>
                    <span class="ag-news-badge"><?= h($country) ?></span>
                  <?php endif; ?>
                </div>

                <div class="ag-news-footer">
                  <span class="ag-news-read">
                    <?= h($isArabic ? 'اقرأ المزيد' : 'Read More') ?>
                  </span>
                </div>
              </div>

            </a>

          <?php endforeach; ?>

          <?php if (!$sliderItems): ?>
            <div class="alert alert-warning w-100"><?= h(tt('no_agreements')) ?></div>
          <?php endif; ?>

        </div>
      </div>

      <button class="ag-news-arrow ag-news-next" type="button" id="agNewsNext">›</button>

    </div>

  </div>
</section>
<script>
(function(){
  const wrap = document.getElementById('agNewsWrap');
  const track = document.getElementById('agNewsTrack');
  const prev = document.getElementById('agNewsPrev');
  const next = document.getElementById('agNewsNext');

  if(!wrap || !track) return;

  let originalCards = Array.from(track.querySelectorAll('.ag-news-card'));
  if(originalCards.length === 0) return;

  let index = 0;
  let timer = null;

  function visibleCount(){
    if(window.innerWidth <= 700) return 1;
    if(window.innerWidth <= 992) return 2;
    return 3;
  }

  function cleanClones(){
    track.querySelectorAll('.ag-news-card.clone').forEach(card => card.remove());
  }

  function buildClones(){
    cleanClones();

    const count = visibleCount();
    originalCards.slice(0, count).forEach(card => {
      const clone = card.cloneNode(true);
      clone.classList.add('clone');
      track.appendChild(clone);
    });
  }

  function cardStep(){
    const first = track.querySelector('.ag-news-card');
    if(!first) return 0;

    const style = window.getComputedStyle(track);
    const gap = parseFloat(style.gap || style.columnGap || 26) || 26;

    return first.offsetWidth + gap;
  }

  function move(animate = true){
    const step = cardStep();

    track.style.transition = animate ? 'transform .55s ease' : 'none';
    track.style.transform = `translateX(-${index * step}px)`;
  }

  function nextSlide(){
    index++;
    move(true);
  }

  function prevSlide(){
    if(index === 0){
      index = originalCards.length;
      move(false);

      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          index--;
          move(true);
        });
      });
    }else{
      index--;
      move(true);
    }
  }

  track.addEventListener('transitionend', function(){
    if(index >= originalCards.length){
      index = 0;
      move(false);
    }
  });

  function startAuto(){
    stopAuto();
    timer = setInterval(nextSlide, 5000);
  }

  function stopAuto(){
    if(timer){
      clearInterval(timer);
      timer = null;
    }
  }

  next && next.addEventListener('click', nextSlide);
  prev && prev.addEventListener('click', prevSlide);

  wrap.addEventListener('mouseenter', stopAuto);
  wrap.addEventListener('mouseleave', startAuto);

  window.addEventListener('resize', function(){
    buildClones();
    index = 0;
    move(false);
  });

  buildClones();
  move(false);
  startAuto();
})();
</script>
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

<section class="uob-partnerSliderX" style="background:#0b1f3a !important;">  <div class="container">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  const counters = document.querySelectorAll('.counter');

  counters.forEach(counter => {
    const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
    let current = 0;
    const duration = 1200;
    const stepTime = 20;
    const steps = Math.ceil(duration / stepTime);
    const increment = target / steps;

    const timer = setInterval(() => {
      current += increment;

      if (current >= target) {
        counter.textContent = target;
        clearInterval(timer);
      } else {
        counter.textContent = Math.floor(current);
      }
    }, stepTime);
  });
});
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
