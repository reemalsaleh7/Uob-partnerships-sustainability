<?php
$pageTitle = "SDG";
$pageSubtitle = "";
$breadcrumb = [
  ['label' => 'SDG', 'href' => 'sdg.php', 'active' => true],
];

$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

if (!function_exists('h')) {
  function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
  }
}

/* =========================
   Language
   ========================= */
$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$T = [
  'ar' => [
    'page_title' => 'أهداف التنمية المستدامة ',
    'total_initiatives' => 'إجمالي المبادرات',
    'total_agreements' => 'إجمالي الاتفاقيات',
    'undefined_items' => 'غير محدد',
    'supported_goals' => 'أهداف مدعومة',
    'filter_all' => 'الكل',
    'filter_has' => 'عليها عناصر',
    'filter_none' => 'بدون عناصر',
    'filter_undef' => 'غير محدد',
    'about_title' => 'عن أهداف التنمية المستدامة',
    'goal_label' => 'هدف التنمية المستدامة',
    'goal_items' => 'إجمالي العناصر',
    'goal_initiatives' => 'المبادرات',
    'goal_agreements' => 'الاتفاقيات',
    'view_details' => 'عرض التفاصيل',
    'no_image' => 'لا توجد صورة',
    'unspecified_title' => 'عناصر غير محددة الهدف',
    'unspecified_desc' => 'هذه العناصر لا تحتوي على هدف SDG واضح في البيانات.',
    'initiative' => 'مبادرة',
    'agreement' => 'اتفاقية',
    'details' => 'التفاصيل',
    'no_unspecified' => 'لا توجد عناصر غير محددة.',
  ],
  'en' => [
    'page_title' => 'Sustainable Development Goals at UOB',
    'total_initiatives' => 'Total Initiatives',
    'total_agreements' => 'Total Agreements',
    'undefined_items' => 'Unspecified',
    'supported_goals' => 'Supported Goals',
    'filter_all' => 'All',
    'filter_has' => 'With Items',
    'filter_none' => 'Without Items',
    'filter_undef' => 'Unspecified',
    'about_title' => 'About the SDGs',
    'goal_label' => 'Sustainable Development Goal',
    'goal_items' => 'Total Items',
    'goal_initiatives' => 'Initiatives',
    'goal_agreements' => 'Agreements',
    'view_details' => 'View Details',
    'no_image' => 'No Image Available',
    'unspecified_title' => 'Unspecified Goal Items',
    'unspecified_desc' => 'These items do not contain a clear SDG in the data.',
    'initiative' => 'Initiative',
    'agreement' => 'Agreement',
    'details' => 'Details',
    'no_unspecified' => 'No unspecified items.',
  ],
];

function tt($key){
  global $T, $lang;
  return $T[$lang][$key] ?? $key;
}

/* =========================
   Helpers
   ========================= */
function normalizeSdgDigits(string $text): string {
  $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩','۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  $latin  = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
  return str_replace($arabic, $latin, $text);
}

function extractSdgNumbersFromTextPage(string $text): array {
  $text = normalizeSdgDigits(trim($text));
  if ($text === '') return [];

  $out = [];

  /*
    يدعم:
    SDG 4 / SDG4 / Goal 4 / الهدف 4 / 4
    والقيم المفصولة بفاصلة أو | أو ;
  */
  if (preg_match_all('/(?:SDG|Goal|الهدف|هدف)?\s*#?\s*(1[0-7]|[1-9])(?=\D|$)/iu', $text, $matches)) {
    foreach ($matches[1] as $n) {
      $v = (int)$n;
      if ($v >= 1 && $v <= 17) {
        $out[$v] = true;
      }
    }
  }

  return array_keys($out);
}

function extractInitiativeSdgsPage(array $it): array {
  $candidateKeys = [
    'sdgs',
    'SDGs',
    'SDG',
    'sdg_primary',
    'sdg_secondary',
    'sdg_goals',
    'SDG الأساسي',
    'SDG ثانوي',
    'الأهداف المرتبطة',
    'الاهداف المرتبطة',
    'أهداف التنمية المستدامة المرتبطة',
  ];

  $nums = [];

  foreach ($candidateKeys as $key) {
    if (!empty($it[$key])) {
      foreach (extractSdgNumbersFromTextPage((string)$it[$key]) as $n) {
        $nums[$n] = true;
      }
    }
  }

  return array_keys($nums);
}

function extractAgreementSdgsPage(array $ag): array {
  $candidateKeys = [
    'sdgs',
    'SDGs',
    'SDG',
    'sdg_primary',
    'sdg_secondary',
    'sdg_goals',
    'SDG الأساسي',
    'SDG ثانوي',
    'الأهداف المرتبطة',
    'الاهداف المرتبطة',
    'أهداف التنمية المستدامة المرتبطة',
  ];

  $nums = [];

  foreach ($candidateKeys as $key) {
    if (!empty($ag[$key])) {
      foreach (extractSdgNumbersFromTextPage((string)$ag[$key]) as $n) {
        $nums[$n] = true;
      }
    }
  }

  return array_keys($nums);
}

function sdgOfficialImagePathPage(int $n): string {
  $padded = str_pad((string)$n, 2, '0', STR_PAD_LEFT);

  $candidates = [
    "assets/image/sdg/SDG-LOGO/sdg{$n}/E_Elyx_{$padded}.png",
    "assets/image/sdg/SDG-LOGO/sdg{$n}/E_GIF_{$padded}.gif",
    "assets/image/sdg/SDG-LOGO/sdg{$n}/E-WEB-Goal-{$padded}.png",
    "assets/image/sdg/SDG-LOGO/sdg{$n}/E_WEB_{$padded}.png",
    "assets/image/sdg/SDG{$n}.png",
    "assets/image/sdg/sdg{$n}.png",
  ];

  foreach ($candidates as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
      return $path;
    }
  }

  return '';
}

function sdgTextColorPage(string $hex): string {
  $hex = ltrim($hex, '#');

  if (strlen($hex) !== 6) {
    return '#ffffff';
  }

  $r = hexdec(substr($hex, 0, 2));
  $g = hexdec(substr($hex, 2, 2));
  $b = hexdec(substr($hex, 4, 2));

  $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

  return $brightness > 150 ? '#0b1f3a' : '#ffffff';
}

function firstValue(array $row, array $keys): string {
  foreach ($keys as $key) {
    if (!empty($row[$key])) {
      return trim((string)$row[$key]);
    }
  }
  return '';
}

/* =========================
   Data
   ========================= */
$allInitiatives = function_exists('loadAllInitiatives') ? loadAllInitiatives() : [];
$allAgreements  = function_exists('readAgreements') ? readAgreements() : [];

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

if (!$isAdmin) {
  $allInitiatives = array_values(array_filter($allInitiatives, function($it){
    $status1 = trim((string)($it['status'] ?? ''));
    $status2 = trim((string)($it['الحالة الإدارية'] ?? ''));

    if ($status1 !== '') {
      return $status1 === 'معتمد' || strtolower($status1) === 'approved';
    }

    if ($status2 !== '') {
      return $status2 === 'معتمد' || strtolower($status2) === 'approved';
    }

    return true;
  }));
}

/* =========================
   SDG Names + Descriptions
   ========================= */
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

$sdgDescriptions = [
  1 => ['ar' => 'القضاء على الفقر بجميع أشكاله وتحسين فرص العيش الكريم للفئات المختلفة.', 'en' => 'End poverty in all its forms and improve opportunities for a dignified life.'],
  2 => ['ar' => 'القضاء على الجوع وتعزيز الأمن الغذائي والتغذية المستدامة.', 'en' => 'End hunger, improve food security, and support sustainable nutrition.'],
  3 => ['ar' => 'ضمان حياة صحية وتعزيز الرفاه لجميع أفراد المجتمع.', 'en' => 'Ensure healthy lives and promote well-being for all people.'],
  4 => ['ar' => 'توفير تعليم جيد ومنصف وتعزيز فرص التعلم مدى الحياة.', 'en' => 'Ensure inclusive, equitable quality education and lifelong learning opportunities.'],
  5 => ['ar' => 'تعزيز المساواة بين الجنسين وتمكين النساء والفتيات.', 'en' => 'Promote gender equality and empower women and girls.'],
  6 => ['ar' => 'ضمان توفر المياه النظيفة وخدمات الصرف الصحي وإدارتها بشكل مستدام.', 'en' => 'Ensure clean water, sanitation, and sustainable water management.'],
  7 => ['ar' => 'توفير طاقة حديثة وموثوقة ومستدامة بتكلفة مناسبة.', 'en' => 'Ensure access to affordable, reliable, sustainable, and modern energy.'],
  8 => ['ar' => 'تعزيز النمو الاقتصادي والعمل اللائق والفرص الإنتاجية.', 'en' => 'Promote sustained economic growth, productive employment, and decent work.'],
  9 => ['ar' => 'دعم الصناعة والابتكار والبنية التحتية القادرة على التطور.', 'en' => 'Support resilient infrastructure, innovation, and sustainable industry.'],
  10 => ['ar' => 'الحد من أوجه عدم المساواة داخل المجتمعات وبينها.', 'en' => 'Reduce inequalities within and among communities.'],
  11 => ['ar' => 'جعل المدن والمجتمعات أكثر شمولًا وأمانًا واستدامة.', 'en' => 'Make cities and communities inclusive, safe, resilient, and sustainable.'],
  12 => ['ar' => 'تعزيز أنماط الاستهلاك والإنتاج المسؤولة والمستدامة.', 'en' => 'Promote responsible and sustainable consumption and production patterns.'],
  13 => ['ar' => 'اتخاذ إجراءات للتصدي لتغير المناخ وآثاره.', 'en' => 'Take action to address climate change and its impacts.'],
  14 => ['ar' => 'حماية المحيطات والبحار والموارد البحرية واستخدامها بشكل مستدام.', 'en' => 'Protect oceans, seas, and marine resources for sustainable use.'],
  15 => ['ar' => 'حماية النظم البيئية البرية والتنوع الحيوي وتعزيز الاستخدام المستدام لها.', 'en' => 'Protect terrestrial ecosystems, biodiversity, and sustainable land use.'],
  16 => ['ar' => 'تعزيز السلام والعدل والمؤسسات الفعالة والشاملة.', 'en' => 'Promote peace, justice, and effective inclusive institutions.'],
  17 => ['ar' => 'تعزيز الشراكات والتعاون لتحقيق أهداف التنمية المستدامة.', 'en' => 'Strengthen partnerships and cooperation to achieve the SDGs.'],
];

$sdgColors = [
  1=>'#E5243B', 2=>'#DDA63A', 3=>'#4C9F38', 4=>'#C5192D', 5=>'#FF3A21',
  6=>'#26BDE2', 7=>'#FCC30B', 8=>'#A21942', 9=>'#FD6925', 10=>'#DD1367',
  11=>'#FD9D24', 12=>'#BF8B2E', 13=>'#3F7E44', 14=>'#0A97D9',
  15=>'#56C02B', 16=>'#00689D', 17=>'#19486A',
];

/* =========================
   Counts
   ========================= */
$initiativeCounts = array_fill(1, 17, 0);
$agreementCounts  = array_fill(1, 17, 0);

$undefinedInitiatives = 0;
$undefinedAgreements = 0;
$undefinedItems = [];

foreach ($allInitiatives as $it) {
  $nums = extractInitiativeSdgsPage($it);

  if (!$nums) {
    $undefinedInitiatives++;

    $title = firstValue($it, ['title', 'initiative_title', 'اسم المبادرة', 'عنوان المبادرة']);
    $id = firstValue($it, ['_id', 'id', 'initiative_id']);

    $undefinedItems[] = [
      'kind' => tt('initiative'),
      'title' => $title ?: tt('initiative'),
      'url' => $id !== '' ? 'initiative-details.php?id=' . urlencode($id) . '&lang=' . urlencode($lang) : 'initiatives.php?lang=' . urlencode($lang),
    ];
  } else {
    foreach ($nums as $n) {
      $initiativeCounts[$n]++;
    }
  }
}

foreach ($allAgreements as $ag) {
  $nums = extractAgreementSdgsPage($ag);

  if (!$nums) {
    $undefinedAgreements++;

    $name = firstValue($ag, ['agreement_name', 'name', 'اسم الاتفاقية']);
    $code = firstValue($ag, ['agreement_code', 'code', 'كود الاتفاقية']);

    $undefinedItems[] = [
      'kind' => tt('agreement'),
      'title' => $name ?: ($code ?: tt('agreement')),
      'url' => $code !== '' ? 'agreement-details.php?code=' . urlencode($code) . '&lang=' . urlencode($lang) : 'agreements.php?lang=' . urlencode($lang),
    ];
  } else {
    foreach ($nums as $n) {
      $agreementCounts[$n]++;
    }
  }
}

$totalUndefined = $undefinedInitiatives + $undefinedAgreements;

$supportedGoals = 0;
$withItemsCount = 0;
$withoutItemsCount = 0;
$sdgCards = [];

for ($i = 1; $i <= 17; $i++) {
  $initCount = (int)($initiativeCounts[$i] ?? 0);
  $agCount = (int)($agreementCounts[$i] ?? 0);
  $totalCount = $initCount + $agCount;
  $hasItems = $totalCount > 0;

  if ($hasItems) {
    $supportedGoals++;
    $withItemsCount++;
  } else {
    $withoutItemsCount++;
  }

  $color = $sdgColors[$i];

  $sdgCards[] = [
    'num' => $i,
    'name' => $sdgNames[$i][$lang] ?? $sdgNames[$i]['ar'],
    'desc' => $sdgDescriptions[$i][$lang] ?? $sdgDescriptions[$i]['ar'],
    'initCount' => $initCount,
    'agCount' => $agCount,
    'totalCount' => $totalCount,
    'hasItems' => $hasItems,
    'color' => $color,
    'textColor' => sdgTextColorPage($color),
    'image' => sdgOfficialImagePathPage($i),
  ];
}
?>

<style>
/* =========================
   SDG Section Only
   ========================= */

.sdgPageHero{
  position:relative;
  background:#f8fafc;
  padding:84px 0 86px;
  overflow:hidden;
}

.sdgPageHero::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at 12% 18%, rgba(42,169,255,.09), transparent 30%),
    radial-gradient(circle at 88% 12%, rgba(184,154,104,.11), transparent 28%),
    linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
  pointer-events:none;
}

.sdgPageHero .container{
  position:relative;
  z-index:2;
}

.sdgHeroTitleWrap{
  width:100%;
  max-width:none;
  margin:0 auto;
  text-align:center;
}

.sdgHeroTitle{
  margin:0;
  color:#0b1f3a;
  font-weight:950;
  line-height:1.12;
  font-size:clamp(32px, 4vw, 58px);
  white-space:nowrap;
  letter-spacing:-.3px;
}

.sdgHeroLine{
  width:105px;
  height:5px;
  border-radius:999px;
  background:#b89a68;
  margin:18px auto 28px;
}

/* Statistics without boxes */
.sdgStatsGrid{
  display:grid;
  grid-template-columns:repeat(4, minmax(0, 150px));
  justify-content:center;
  gap:28px;
  max-width:760px;
  margin:0 auto 28px;
}

.sdgStatItem{
  text-align:center;
  min-width:0;
}

.sdgStatItem .lbl{
  color:#64748b;
  font-size:15px;
  font-weight:900;
  line-height:1.35;
  min-height:42px;
  display:flex;
  align-items:flex-end;
  justify-content:center;
}

.sdgStatItem .val{
  color:#0b1f3a;
  font-size:42px;
  font-weight:950;
  line-height:1;
  margin-top:8px;
  font-variant-numeric:tabular-nums;
  min-width:3ch;
  display:inline-block;
}

/* Filters */
.sdgFilterBar{
  display:grid;
  grid-template-columns:repeat(4, minmax(130px, 1fr));
  gap:12px;
  max-width:760px;
  margin:0 auto 40px;
}

.sdgFilterBtn{
  border:1px solid #d7e0ea;
  background:#ffffff;
  color:#0b1f3a;
  border-radius:999px;
  padding:12px 16px;
  font-size:15px;
  font-weight:900;
  cursor:pointer;
  transition:background .25s ease, color .25s ease, border-color .25s ease, transform .25s ease;
  white-space:nowrap;
}

.sdgFilterBtn span{
  color:#b89a68;
  font-weight:950;
}

.sdgFilterBtn:hover{
  transform:translateY(-2px);
  border-color:#b89a68;
}

.sdgFilterBtn.active{
  background:#0b1f3a;
  color:#ffffff;
  border-color:#0b1f3a;
}

.sdgFilterBtn.active span{
  color:#ffffff;
}

/* Stack + Grid */
.sdgDeckStage{
  position:relative;
  min-height:430px;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:visible;
}

.sdgCardsWrap{
  position:relative;
  overflow:visible;
}

/* Initial stack */
.sdgCardsWrap.stack-mode{
  width:260px;
  height:390px;
}

.sdgGoalCard{
  text-decoration:none;
  border-radius:22px;
  outline:none;
  display:block;
  transition:
    transform .75s cubic-bezier(.22,1,.36,1),
    opacity .45s ease;
}

.sdgCardsWrap.stack-mode .sdgGoalCard{
  position:absolute;
  inset:0;
  width:260px;
  height:390px;
  opacity:1;
  transform:
    translateX(calc(var(--stack-x, 0) * 1px))
    translateY(calc(var(--stack-y, 0) * 1px))
    rotate(calc(var(--stack-r, 0) * 1deg))
    scale(1);
}

.sdgCardsWrap.stack-mode .sdgGoalCard:first-child{
  transform:translateX(0) translateY(0) rotate(0deg) scale(1);
  z-index:999 !important;
}

/* Stronger stack hover, without grid */
.sdgCardsWrap.stack-mode:hover .sdgGoalCard:not(:first-child){
  transform:
    translateX(calc(var(--stack-hover-x, 0) * 1px))
    translateY(calc(var(--stack-hover-y, 0) * 1px))
    rotate(calc(var(--stack-hover-r, 0) * 1deg))
    scale(1);
}

/* Grid after filter */
.sdgCardsWrap.grid-mode{
  width:100%;
  display:grid;
  grid-template-columns:repeat(4, minmax(0, 1fr));
  gap:22px;
}

.sdgCardsWrap.grid-mode .sdgGoalCard{
  position:relative;
  width:100%;
  height:390px;
  min-height:390px;
  perspective:1100px;
  opacity:1;
  transform:translate(0,0) scale(1) rotate(0deg);
}

.sdgCardsWrap.grid-mode .sdgGoalCard.is-hidden{
  display:none;
}

.sdgCardsWrap.grid-mode .sdgGoalCard.from-center,
.sdgCardsWrap.grid-mode .sdgGoalCard.to-center{
  opacity:0;
  transform:
    translate(var(--from-x), var(--from-y))
    scale(.45)
    rotate(var(--from-r));
}

/* Flip */
.sdgGoalCardInner{
  position:relative;
  width:100%;
  height:100%;
  border-radius:22px;
  transform-style:preserve-3d;
  transition:transform .8s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow:0 12px 28px rgba(2,8,23,.08);
}

.sdgCardsWrap.grid-mode .sdgGoalCard:hover .sdgGoalCardInner,
.sdgCardsWrap.grid-mode .sdgGoalCard:focus .sdgGoalCardInner,
.sdgCardsWrap.grid-mode .sdgGoalCard.is-flipped .sdgGoalCardInner{
  transform:rotateY(180deg);
}

.sdgGoalFace{
  position:absolute;
  inset:0;
  width:100%;
  height:100%;
  border-radius:22px;
  backface-visibility:hidden;
  overflow:hidden;
  background:#ffffff;
  border:1px solid rgba(226,232,240,.95);
}

.sdgGoalFront{
  display:flex;
  flex-direction:column;
}

.sdgGoalBack{
  transform:rotateY(180deg);
  background:var(--goal-color);
  color:var(--back-text);
  padding:26px 24px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  direction:rtl;
  text-align:right;
}

html[dir="ltr"] .sdgGoalBack{
  direction:ltr;
  text-align:left;
}

.sdgGoalImage{
  width:100%;
  height:175px;
  background:#f1f3f5;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}

.sdgGoalImage img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

.sdgGoalPlaceholder{
  width:100%;
  height:100%;
  background:#f1f3f5;
  color:#8a94a3;
  font-size:14px;
  font-weight:700;
  display:flex;
  align-items:center;
  justify-content:center;
}

.sdgGoalFrontBody{
  flex:1;
  padding:18px 20px 20px;
  direction:rtl;
  text-align:right;
  display:flex;
  flex-direction:column;
}

html[dir="ltr"] .sdgGoalFrontBody{
  direction:ltr;
  text-align:left;
}

.sdgGoalType{
  color:#b89a68;
  font-size:12px;
  font-weight:900;
  margin-bottom:7px;
}

.sdgGoalFrontBody h3{
  color:#0b1f3a;
  font-size:18px;
  font-weight:950;
  line-height:1.45;
  margin:0 0 14px;
  min-height:52px;
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
}

.sdgGoalMiniStats{
  margin-top:auto;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:8px;
}

.sdgGoalMiniStats .mini{
  background:#f8fafc;
  border:1px solid #e2e8f0;
  border-radius:12px;
  padding:8px 9px;
}

.sdgGoalMiniStats .mini.full{
  grid-column:1 / -1;
}

.sdgGoalMiniStats .mini .mLbl{
  color:#64748b;
  font-size:11px;
  font-weight:800;
  margin-bottom:3px;
}

.sdgGoalMiniStats .mini .mVal{
  color:#0b1f3a;
  font-size:18px;
  font-weight:950;
  font-variant-numeric:tabular-nums;
}

.sdgBackTop{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  margin-bottom:18px;
}

.sdgBackNumber{
  font-size:28px;
  font-weight:950;
  line-height:1.1;
}

.sdgBackBadge{
  width:52px;
  height:52px;
  border-radius:16px;
  background:rgba(255,255,255,.20);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:22px;
  font-weight:950;
  border:1px solid rgba(255,255,255,.28);
}

.sdgGoalBack h3{
  margin:0 0 14px;
  font-size:24px;
  font-weight:950;
  line-height:1.35;
}

.sdgGoalBack p{
  margin:0;
  font-size:15px;
  font-weight:750;
  line-height:1.8;
}

.sdgBackAction{
  margin-top:20px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  align-self:flex-start;
  padding:10px 18px;
  border-radius:999px;
  background:rgba(255,255,255,.20);
  color:var(--back-text);
  border:1px solid rgba(255,255,255,.35);
  font-size:14px;
  font-weight:950;
}

/* Unspecified cards */
.sdgUnspecifiedGrid{
  display:none;
  width:100%;
  grid-template-columns:repeat(4, minmax(0, 1fr));
  gap:18px;
}

.sdgUnspecifiedGrid.is-visible{
  display:grid;
}

.sdgUnspecifiedCard{
  background:#ffffff;
  border:1px solid #e2e8f0;
  border-radius:18px;
  padding:18px;
  text-decoration:none;
  box-shadow:0 10px 22px rgba(2,8,23,.06);
  color:#0b1f3a;
  min-height:150px;
  direction:rtl;
  text-align:right;
}

html[dir="ltr"] .sdgUnspecifiedCard{
  direction:ltr;
  text-align:left;
}

.sdgUnspecifiedCard .kind{
  color:#b89a68;
  font-size:12px;
  font-weight:950;
  margin-bottom:8px;
}

.sdgUnspecifiedCard h3{
  font-size:17px;
  font-weight:950;
  line-height:1.5;
  margin:0 0 12px;
}

.sdgUnspecifiedCard span{
  font-size:14px;
  font-weight:900;
  color:#0b1f3a;
}

.sdgEmptyUnspecified{
  grid-column:1 / -1;
  background:#ffffff;
  border:1px dashed #cbd5e1;
  border-radius:16px;
  padding:24px;
  color:#64748b;
  font-weight:900;
  text-align:center;
}

/* About section */
.sdg-about-section{
  padding:70px 0;
  background:#ffffff;
}

.sdg-about-box{
  max-width:900px;
  margin:0 auto;
  text-align:center;
}

.sdg-about-box h2{
  margin:0;
  color:#0b1f3a;
  font-size:clamp(30px, 3.2vw, 46px);
  font-weight:950;
}

.sdg-about-box .line{
  width:92px;
  height:4px;
  border-radius:999px;
  background:#b89a68;
  margin:18px auto 24px;
}

.sdg-about-box p{
  margin:0 auto;
  max-width:780px;
  color:#475569;
  font-size:18px;
  font-weight:750;
  line-height:1.9;
}

@media(max-width:1200px){
  .sdgCardsWrap.grid-mode,
  .sdgUnspecifiedGrid{
    grid-template-columns:repeat(3, minmax(0, 1fr));
  }
}

@media(max-width:900px){
  .sdgStatsGrid{
    grid-template-columns:repeat(2, minmax(0, 150px));
  }

  .sdgFilterBar{
    grid-template-columns:repeat(2, minmax(130px, 1fr));
  }

  .sdgCardsWrap.grid-mode,
  .sdgUnspecifiedGrid{
    grid-template-columns:repeat(2, minmax(0, 1fr));
  }

  .sdgHeroTitle{
    font-size:clamp(32px, 5vw, 48px);
  }
}

@media(max-width:600px){
  .sdgPageHero{
    padding:66px 0 70px;
  }

  .sdgHeroTitle{
    white-space:normal;
    font-size:clamp(30px, 8vw, 40px);
  }

  .sdgStatsGrid{
    grid-template-columns:1fr;
    gap:18px;
  }

  .sdgFilterBar{
    grid-template-columns:1fr 1fr;
  }

  .sdgCardsWrap.grid-mode,
  .sdgUnspecifiedGrid{
    grid-template-columns:1fr;
  }

  .sdgCardsWrap.stack-mode{
    transform:scale(.82);
  }
}

@media (prefers-reduced-motion: reduce){
  .sdgGoalCardInner,
  .sdgFilterBtn,
  .sdgGoalCard{
    transition:none !important;
  }
}
</style>

<section class="sdgPageHero">
  <div class="container">

    <div class="sdgHeroTitleWrap">
      <h1 class="sdgHeroTitle"><?= h(tt('page_title')) ?></h1>
      <div class="sdgHeroLine"></div>
    </div>

    <div class="sdgStatsGrid" id="sdgStatsGrid">
      <div class="sdgStatItem">
        <div class="lbl"><?= h(tt('total_initiatives')) ?></div>
        <div class="val js-count" data-count="<?= (int)count($allInitiatives) ?>">0</div>
      </div>

      <div class="sdgStatItem">
        <div class="lbl"><?= h(tt('total_agreements')) ?></div>
        <div class="val js-count" data-count="<?= (int)count($allAgreements) ?>">0</div>
      </div>

      <div class="sdgStatItem">
        <div class="lbl"><?= h(tt('undefined_items')) ?></div>
        <div class="val js-count" data-count="<?= (int)$totalUndefined ?>">0</div>
      </div>

      <div class="sdgStatItem">
        <div class="lbl"><?= h(tt('supported_goals')) ?></div>
        <div class="val js-count" data-count="<?= (int)$supportedGoals ?>">0</div>
      </div>
    </div>

    <div class="sdgFilterBar" role="group" aria-label="<?= $isArabic ? 'فلترة أهداف التنمية' : 'SDG filters' ?>">
      <button type="button" class="sdgFilterBtn" data-filter="all" aria-pressed="false">
        <?= h(tt('filter_all')) ?> <span>(17)</span>
      </button>

      <button type="button" class="sdgFilterBtn" data-filter="with" aria-pressed="false">
        <?= h(tt('filter_has')) ?> <span>(<?= (int)$withItemsCount ?>)</span>
      </button>

      <button type="button" class="sdgFilterBtn" data-filter="without" aria-pressed="false">
        <?= h(tt('filter_none')) ?> <span>(<?= (int)$withoutItemsCount ?>)</span>
      </button>

      <button type="button" class="sdgFilterBtn" data-filter="unspecified" aria-pressed="false">
        <?= h(tt('filter_undef')) ?> <span>(<?= (int)$totalUndefined ?>)</span>
      </button>
    </div>

    <div class="sdgDeckStage" id="sdgDeckStage">
      <div class="sdgCardsWrap stack-mode" id="sdgCardsWrap">
        <?php foreach ($sdgCards as $index => $goal): ?>
          <?php
            $num = (int)$goal['num'];
            $detailsUrl = 'sdg-goal.php?sdg=' . urlencode((string)$num) . '&lang=' . urlencode($lang);

            $side = ($index % 2 === 0) ? 1 : -1;
            $step = min($index, 10);

            $stackX = $side * $step * 2.5;
            $stackY = $step * 1.3;
            $stackR = $side * $step * .8;

            $stackHoverX = $side * $step * 5.4;
            $stackHoverY = $step * 2.0;
            $stackHoverR = $side * $step * 1.5;
          ?>

          <a
            class="sdgGoalCard"
            href="<?= h($detailsUrl) ?>"
            data-has-items="<?= $goal['hasItems'] ? '1' : '0' ?>"
            style="
              --goal-color:<?= h($goal['color']) ?>;
              --back-text:<?= h($goal['textColor']) ?>;
              --stack-x:<?= $stackX ?>;
              --stack-y:<?= $stackY ?>;
              --stack-r:<?= $stackR ?>;
              --stack-hover-x:<?= $stackHoverX ?>;
              --stack-hover-y:<?= $stackHoverY ?>;
              --stack-hover-r:<?= $stackHoverR ?>;
              z-index:<?= 900 - $index ?>;
            "
            aria-label="<?= h(($isArabic ? 'فتح تفاصيل هدف التنمية رقم ' : 'Open details for SDG ') . $num . ' - ' . $goal['name']) ?>">

            <div class="sdgGoalCardInner">

              <div class="sdgGoalFace sdgGoalFront">
                <div class="sdgGoalImage">
                  <?php if (!empty($goal['image'])): ?>
                    <img src="<?= h($goal['image']) ?>" alt="SDG <?= $num ?>" loading="lazy">
                  <?php else: ?>
                    <div class="sdgGoalPlaceholder"><?= h(tt('no_image')) ?></div>
                  <?php endif; ?>
                </div>

                <div class="sdgGoalFrontBody">
                  <div class="sdgGoalType"><?= h(tt('goal_label')) ?></div>

                  <h3>SDG <?= $num ?> — <?= h($goal['name']) ?></h3>

                  <div class="sdgGoalMiniStats">
                    <div class="mini">
                      <div class="mLbl"><?= h(tt('goal_initiatives')) ?></div>
                      <div class="mVal"><?= (int)$goal['initCount'] ?></div>
                    </div>

                    <div class="mini">
                      <div class="mLbl"><?= h(tt('goal_agreements')) ?></div>
                      <div class="mVal"><?= (int)$goal['agCount'] ?></div>
                    </div>

                    <div class="mini full">
                      <div class="mLbl"><?= h(tt('goal_items')) ?></div>
                      <div class="mVal"><?= (int)$goal['totalCount'] ?></div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="sdgGoalFace sdgGoalBack">
                <div>
                  <div class="sdgBackTop">
                    <div class="sdgBackNumber">SDG <?= $num ?></div>
                    <div class="sdgBackBadge"><?= $num ?></div>
                  </div>

                  <h3><?= h($goal['name']) ?></h3>
                  <p><?= h($goal['desc']) ?></p>
                </div>

                <span class="sdgBackAction"><?= h(tt('view_details')) ?></span>
              </div>

            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="sdgUnspecifiedGrid" id="sdgUnspecifiedGrid">
        <?php if ($undefinedItems): ?>
          <?php foreach ($undefinedItems as $item): ?>
            <a class="sdgUnspecifiedCard" href="<?= h($item['url']) ?>">
              <div class="kind"><?= h($item['kind']) ?></div>
              <h3><?= h($item['title']) ?></h3>
              <span><?= h(tt('details')) ?></span>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sdgEmptyUnspecified"><?= h(tt('no_unspecified')) ?></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>



<script>
(function(){
  const counters = document.querySelectorAll('.js-count');
  const statsGrid = document.getElementById('sdgStatsGrid');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!counters.length || !statsGrid) return;

  let started = false;

  function easeOutCubic(t){
    return 1 - Math.pow(1 - t, 3);
  }

  function setFinalValues(){
    counters.forEach(counter => {
      const target = parseInt(counter.dataset.count || '0', 10);
      counter.textContent = target;
    });
  }

  function animateCounters(){
    if (started) return;
    started = true;

    if (reduceMotion) {
      setFinalValues();
      return;
    }

    const duration = 1500;
    const startTime = performance.now();

    counters.forEach(counter => {
      counter.textContent = '0';
    });

    function frame(now){
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = easeOutCubic(progress);

      counters.forEach(counter => {
        const target = parseInt(counter.dataset.count || '0', 10);
        counter.textContent = Math.round(target * eased);
      });

      if (progress < 1) {
        requestAnimationFrame(frame);
      } else {
        setFinalValues();
      }
    }

    requestAnimationFrame(frame);
  }

  if (!('IntersectionObserver' in window)) {
    animateCounters();
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounters();
        observer.unobserve(statsGrid);
      }
    });
  }, { threshold: 0.35 });

  observer.observe(statsGrid);
})();
</script>

<script>
(function(){
  const buttons = Array.from(document.querySelectorAll('.sdgFilterBtn'));
  const wrap = document.getElementById('sdgCardsWrap');
  const unspecifiedGrid = document.getElementById('sdgUnspecifiedGrid');

  if (!buttons.length || !wrap || !unspecifiedGrid) return;

  const cards = Array.from(wrap.querySelectorAll('.sdgGoalCard'));
  let activeFilter = null;

  function clearFlipped(){
    cards.forEach(card => {
      card.classList.remove('is-flipped');
      card.dataset.opened = '0';
    });
  }

  function resetToStack(){
    activeFilter = null;
    clearFlipped();

    buttons.forEach(btn => {
      btn.classList.remove('active');
      btn.setAttribute('aria-pressed', 'false');
    });

    unspecifiedGrid.classList.remove('is-visible');
    wrap.style.display = '';
    wrap.classList.remove('grid-mode');
    wrap.classList.add('stack-mode');

    cards.forEach(card => {
      card.style.display = '';
      card.classList.remove('is-hidden', 'from-center', 'to-center');
      card.style.opacity = '1';
    });
  }

  function currentVisibleCards(){
    return cards.filter(card => card.style.display !== 'none');
  }

  function matchingCards(filter){
    if (filter === 'all') {
      return cards;
    }

    return cards.filter(card => {
      const hasItems = card.dataset.hasItems === '1';

      if (filter === 'with') return hasItems;
      if (filter === 'without') return !hasItems;

      return true;
    });
  }

  function moveCurrentToCenter(callback){
    const visibleCards = currentVisibleCards();

    if (!wrap.classList.contains('grid-mode') || !visibleCards.length) {
      callback();
      return;
    }

    const wrapRect = wrap.getBoundingClientRect();
    const centerX = wrapRect.left + wrapRect.width / 2;
    const centerY = wrapRect.top + 180;

    visibleCards.forEach((card, index) => {
      const rect = card.getBoundingClientRect();
      const cardX = rect.left + rect.width / 2;
      const cardY = rect.top + rect.height / 2;

      card.style.setProperty('--from-x', (centerX - cardX) + 'px');
      card.style.setProperty('--from-y', (centerY - cardY) + 'px');
      card.style.setProperty('--from-r', ((index % 2 === 0 ? 1 : -1) * 8) + 'deg');
      card.style.transitionDelay = (index * 30) + 'ms';
      card.classList.add('to-center');
    });

    setTimeout(callback, 680);
  }

  function showGoalsGrid(filter){
    const visibleCards = matchingCards(filter);

    unspecifiedGrid.classList.remove('is-visible');
    wrap.style.display = '';
    wrap.classList.remove('stack-mode');
    wrap.classList.add('grid-mode');

    cards.forEach(card => {
      const show = visibleCards.includes(card);
      card.style.display = show ? '' : 'none';
      card.classList.remove('is-hidden', 'from-center', 'to-center', 'is-flipped');
      card.dataset.opened = '0';
    });

    requestAnimationFrame(() => {
      const wrapRect = wrap.getBoundingClientRect();
      const centerX = wrapRect.left + wrapRect.width / 2;
      const centerY = wrapRect.top + 180;

      visibleCards.forEach((card, index) => {
        const rect = card.getBoundingClientRect();
        const cardX = rect.left + rect.width / 2;
        const cardY = rect.top + rect.height / 2;

        card.style.setProperty('--from-x', (centerX - cardX) + 'px');
        card.style.setProperty('--from-y', (centerY - cardY) + 'px');
        card.style.setProperty('--from-r', ((index % 2 === 0 ? 1 : -1) * 8) + 'deg');
        card.style.transitionDelay = (index * 45) + 'ms';
        card.classList.add('from-center');
      });

      wrap.offsetHeight;

      requestAnimationFrame(() => {
        visibleCards.forEach(card => {
          card.classList.remove('from-center');
        });
      });
    });
  }

  function showUnspecified(){
    clearFlipped();

    wrap.classList.remove('grid-mode', 'stack-mode');
    wrap.style.display = 'none';
    unspecifiedGrid.classList.add('is-visible');
  }

  function setActiveButton(filter){
    buttons.forEach(btn => {
      const isActive = btn.dataset.filter === filter;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function applyFilter(filter){
    if (activeFilter === filter) {
      moveCurrentToCenter(resetToStack);
      return;
    }

    activeFilter = filter;
    setActiveButton(filter);

    if (filter === 'unspecified') {
      moveCurrentToCenter(showUnspecified);
      return;
    }

    moveCurrentToCenter(() => {
      showGoalsGrid(filter);
    });
  }

  buttons.forEach(button => {
    button.addEventListener('click', function(){
      applyFilter(this.dataset.filter);
    });
  });

  resetToStack();
})();
</script>

<script>
(function(){
  const cards = Array.from(document.querySelectorAll('.sdgGoalCard'));
  const wrap = document.getElementById('sdgCardsWrap');
  const canHover = window.matchMedia('(hover:hover)').matches;

  if (!cards.length || !wrap) return;

  let openedCard = null;

  function closeOtherCards(current){
    cards.forEach(card => {
      if (card !== current) {
        card.classList.remove('is-flipped');
        card.dataset.opened = '0';
      }
    });
  }

  cards.forEach(card => {
    card.dataset.opened = '0';

    card.addEventListener('click', function(e){
      if (!wrap.classList.contains('grid-mode')) {
        return;
      }

      if (canHover) {
        return;
      }

      const isOpened = card.dataset.opened === '1';

      if (!isOpened) {
        e.preventDefault();
        closeOtherCards(card);
        card.classList.add('is-flipped');
        card.dataset.opened = '1';
        openedCard = card;
      }
    });

    card.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        window.location.href = card.getAttribute('href');
      }

      if (e.key === 'Escape') {
        card.classList.remove('is-flipped');
        card.dataset.opened = '0';
      }
    });
  });

  document.addEventListener('click', function(e){
    if (canHover) return;

    if (openedCard && !openedCard.contains(e.target)) {
      openedCard.classList.remove('is-flipped');
      openedCard.dataset.opened = '0';
      openedCard = null;
    }
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>