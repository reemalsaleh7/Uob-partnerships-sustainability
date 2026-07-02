<?php
$pageTitle = "SDG";
$pageSubtitle = "";
$breadcrumb = [
  ['label' => 'SDG', 'href' => 'sdg.php', 'active' => true],
];

$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

/* =========================
   language
   ========================= */
$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$T = [
  'ar' => [
    'page_title' => 'أهداف التنمية المستدامة في جامعة البحرين',
    'hero_desc' => 'بوابة تفاعلية لاستعراض أهداف التنمية المستدامة وربطها بمبادرات الجامعة واتفاقياتها ذات الصلة.',
    'total_initiatives' => 'إجمالي المبادرات',
    'total_agreements' => 'إجمالي الاتفاقيات',
    'undefined_items' => 'غير محدد',
    'supported_goals' => 'أهداف مدعومة',
    'filter_all' => 'الكل',
    'filter_has' => 'عليها عناصر',
    'filter_none' => 'بدون عناصر',
    'filter_undef' => 'غير محدد',
    'about_title' => 'عن أهداف التنمية المستدامة',
    'about_desc' => 'تستعرض هذه الصفحة مساهمة جامعة البحرين في أهداف التنمية المستدامة من خلال المبادرات والاتفاقيات والأنشطة المرتبطة بكل هدف. اضغط على أي هدف لعرض جميع العناصر الداعمة له.',
    'tag_bilingual' => 'واجهة ثنائية اللغة',
    'tag_slider' => 'عرض تفاعلي',
    'tag_related' => 'ربط بالمبادرات والاتفاقيات',
    'tag_visual' => 'هوية بصرية موحدة',
    'explore_title' => 'استكشف الأهداف السبعة عشر',
    'goal_label' => 'هدف التنمية المستدامة',
    'goal_items' => 'إجمالي العناصر',
    'goal_initiatives' => 'مبادرات',
    'goal_agreements' => 'اتفاقيات',
    'view_details' => 'عرض التفاصيل',
    'previous' => 'السابق',
    'next' => 'التالي',
    'selected_goal' => 'تفاصيل الهدف',
    'initiatives_for_goal' => 'المبادرات المرتبطة',
    'agreements_for_goal' => 'الاتفاقيات المرتبطة',
    'no_initiatives' => 'لا توجد مبادرات مرتبطة بهذا الهدف.',
    'no_agreements' => 'لا توجد اتفاقيات مرتبطة بهذا الهدف.',
    'initiative' => 'مبادرة',
    'agreement' => 'اتفاقية',
    'unit' => 'الجهة',
    'type' => 'النوع',
    'date' => 'التاريخ',
    'status' => 'الحالة',
    'partner' => 'الجهة المتعاونة',
    'details' => 'التفاصيل',
    'open_goal' => 'فتح الهدف',
    'undefined_title' => 'عناصر غير محددة الهدف',
    'undefined_desc' => 'هذه العناصر لا تحتوي على SDG واضح في البيانات.',
  ],
  'en' => [
    'page_title' => 'Sustainable Development Goals at UOB',
    'hero_desc' => 'An interactive portal to explore the Sustainable Development Goals and connect them with related University of Bahrain initiatives and agreements.',
    'total_initiatives' => 'Total Initiatives',
    'total_agreements' => 'Total Agreements',
    'undefined_items' => 'Undefined',
    'supported_goals' => 'Supported Goals',
    'filter_all' => 'All',
    'filter_has' => 'With Items',
    'filter_none' => 'Without Items',
    'filter_undef' => 'Undefined',
    'about_title' => 'About the SDGs',
    'about_desc' => 'This page presents the University of Bahrain’s contribution to the Sustainable Development Goals through initiatives, agreements, and related activities. Click any goal to view all supporting items.',
    'tag_bilingual' => 'Bilingual Interface',
    'tag_slider' => 'Interactive Display',
    'tag_related' => 'Linked to Initiatives & Agreements',
    'tag_visual' => 'Unified Visual Identity',
    'explore_title' => 'Explore the 17 SDGs',
    'goal_label' => 'Sustainable Development Goal',
    'goal_items' => 'Total Items',
    'goal_initiatives' => 'Initiatives',
    'goal_agreements' => 'Agreements',
    'view_details' => 'View Details',
    'previous' => 'Previous',
    'next' => 'Next',
    'selected_goal' => 'Selected Goal Details',
    'initiatives_for_goal' => 'Related Initiatives',
    'agreements_for_goal' => 'Related Agreements',
    'no_initiatives' => 'No initiatives are linked to this goal.',
    'no_agreements' => 'No agreements are linked to this goal.',
    'initiative' => 'Initiative',
    'agreement' => 'Agreement',
    'unit' => 'Unit',
    'type' => 'Type',
    'date' => 'Date',
    'status' => 'Status',
    'partner' => 'Partner',
    'details' => 'Details',
    'open_goal' => 'Open Goal',
    'undefined_title' => 'Undefined Goal Items',
    'undefined_desc' => 'These items do not contain a clear SDG in the data.',
  ],
];

function tt($key){
  global $T, $lang;
  return $T[$lang][$key] ?? $key;
}

/* =========================
   helpers
   ========================= */
function parseDateAnySdg(string $s): int {
  $s = trim($s);
  if ($s === '') return 0;
  $ts = strtotime($s);
  return $ts !== false ? $ts : 0;
}

function extractSdgNumbersFromText(string $text): array {
  $out = [];
  $text = trim($text);

  if ($text === '') return [];

  // يدعم كل الحالات (SDG 4 / 4 / الهدف 4)
  if (preg_match_all('/(1[0-7]|[1-9])/u', $text, $m)) {
    foreach ($m[1] as $n) {
      $v = (int)$n;
      if ($v >= 1 && $v <= 17) {
        $out[$v] = true;
      }
    }
  }

  return array_keys($out);
}

function extractInitiativeSdgs(array $it): array {
  $candidateKeys = [
    'sdg_primary',
    'sdg_secondary',
    'SDG الأساسي',
    'SDG ثانوي',
    'sdgs',
    'SDGs',
  ];

  $nums = [];

  foreach ($candidateKeys as $key) {
    if (!empty($it[$key])) {
      foreach (extractSdgNumbersFromText((string)$it[$key]) as $n) {
        $nums[$n] = true;
      }
    }
  }

  return array_keys($nums);
}

function extractAgreementSdgs(array $ag): array {
  $candidateKeys = [
    'SDG الأساسي',
    'SDG ثانوي',
    'SDGs',
    'sdgs',
    'الأهداف المرتبطة',
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

/* =========================
   data
   ========================= */
$allInitiatives = loadAllInitiatives();
$allAgreements  = function_exists('readAgreements') ? readAgreements() : [];

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
if (!$isAdmin) {
  $allInitiatives = array_values(array_filter($allInitiatives, function($it){
    return trim((string)($it['الحالة الإدارية'] ?? 'معتمد')) === 'معتمد';
  }));
}

/* =========================
   SDG names
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

/* =========================
   counts
   ========================= */
$initiativeCounts = array_fill(1, 17, 0);
$agreementCounts  = array_fill(1, 17, 0);
$undefinedInitiatives = 0;
$undefinedAgreements = 0;

foreach ($allInitiatives as $it) {
  $nums = extractInitiativeSdgs($it);
  if (!$nums) {
    $undefinedInitiatives++;
  } else {
    foreach ($nums as $n) $initiativeCounts[$n]++;
  }
}

foreach ($allAgreements as $ag) {
  $nums = extractAgreementSdgs($ag);
  if (!$nums) {
    $undefinedAgreements++;
  } else {
    foreach ($nums as $n) $agreementCounts[$n]++;
  }
}

$totalUndefined = $undefinedInitiatives + $undefinedAgreements;
$supportedGoals = 0;
for ($i=1; $i<=17; $i++) {
  if (($initiativeCounts[$i] + $agreementCounts[$i]) > 0) $supportedGoals++;
}

/* =========================
   filters
   ========================= */
$active = trim($_GET['only'] ?? 'all'); // all | has | none | undef
$selectedSdg = (int)($_GET['sdg'] ?? 0);
if ($selectedSdg < 0 || $selectedSdg > 17) $selectedSdg = 0;

/* =========================
   selected goal items
   ========================= */
$selectedInitiatives = [];
$selectedAgreements = [];

if ($selectedSdg >= 1 && $selectedSdg <= 17) {
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
    return parseDateAnySdg((string)($b['تاريخ تنفيذ المبادرة'] ?? '')) <=> parseDateAnySdg((string)($a['تاريخ تنفيذ المبادرة'] ?? ''));
  });

  usort($selectedAgreements, function($a, $b){
    return parseDateAnySdg((string)($b['تاريخ البدء'] ?? '')) <=> parseDateAnySdg((string)($a['تاريخ البدء'] ?? ''));
  });
}

/* =========================
   images
   ========================= */
$heroImg  = 'assets/image/sdg/sdg.png';
$aboutImg = 'assets\image\sdg\SDG1.png';

function sdgGifPath(int $n): string {
  $padded = str_pad((string)$n, 2, '0', STR_PAD_LEFT);
  return "assets/image/sdg/SDG-LOGO/sdg{$n}/E_GIF_{$padded}.gif";
}
?>

<style>
/* page-specific only */
.sdgHeroBtnRow{
  margin-top:14px;
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.sdgHeroBtnRow a{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:44px;
  padding:10px 16px;
  border-radius:999px;
  border:1px solid rgba(15,95,116,.25);
  background:#fff;
  color:#0b1f3a !important;
  font-weight:950;
  font-size:12px;
}
.sdgHeroBtnRow a.on{
  border-color:rgba(42,169,255,.7);
  box-shadow:0 0 0 .2rem rgba(42,169,255,.18);
}

.sdgDetailSection{
  padding:64px 0;
  background:rgba(255,255,255,.55);
  border-top:1px solid rgba(230,235,242,.85);
  border-bottom:1px solid rgba(230,235,242,.85);
}
.sdgDetailHead{
  text-align:center;
  margin-bottom:24px;
}
.sdgDetailHead h2{
  margin:0;
  font-weight:950;
  color:#0b1f3a;
  font-size:42px;
}
.sdgDetailWrap{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
}
.sdgDetailBlock{
  background:#fff;
  border:1px solid rgba(230,235,242,.95);
  border-radius:18px;
  box-shadow:var(--shadow-sm);
  padding:18px;
}
.sdgDetailBlock h3{
  margin:0 0 14px;
  font-weight:950;
  color:var(--uob-navy);
  font-size:22px;
}
.sdgMiniGrid{
  display:grid;
  gap:12px;
}
.sdgMiniCard{
  border:1px solid rgba(230,235,242,.95);
  border-radius:14px;
  padding:14px;
  background:#fbfdff;
}
.sdgMiniCard .t{
  font-weight:950;
  color:var(--uob-navy);
  line-height:1.6;
}
.sdgMiniCard .m{
  margin-top:8px;
  color:var(--muted);
  font-weight:850;
  line-height:1.9;
}
.sdgMiniCard .a{
  margin-top:10px;
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.sdgEmpty{
  border:1px dashed rgba(100,116,139,.35);
  border-radius:14px;
  padding:18px;
  color:var(--muted);
  font-weight:850;
  background:#fff;
}

.sdgSlideImage{
  background:#dfe6ec;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
}
.sdgSlideImage img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}

@media (max-width:992px){
  .sdgDetailWrap{
    grid-template-columns:1fr;
  }
}

.sdgSliderTrack{
  display:flex;
  transition:transform .5s ease;
  direction:ltr; /* مهم جدا */
  will-change: transform;
}

.sdgSlide{
  min-width:100%;
  flex:0 0 100%;
}

.sdgSlideCard{
  direction: <?= $isArabic ? 'rtl' : 'ltr' ?>;
  text-align: <?= $isArabic ? 'right' : 'left' ?>;
}


</style>

<section class="sdg-heroX">
   <div class="sdg-heroX-bg" style="background-image:url('<?= h($heroImg) ?>')"></div>

  <div class="sdg-heroX-overlay"></div>

  <div class="container sdg-heroX-container" style="justify-content:flex-start;">
    <div class="sdg-heroX-card uob-reveal in">
      <h1><?= h(tt('page_title')) ?></h1>
      <div class="sdg-heroX-line"></div>
      

      <div class="sdg-heroX-mini">
        <div class="mini">
          <div class="lbl"><?= h(tt('total_initiatives')) ?></div>
          <div class="val"><?= (int)count($allInitiatives) ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('total_agreements')) ?></div>
          <div class="val"><?= (int)count($allAgreements) ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('undefined_items')) ?></div>
          <div class="val"><?= (int)$totalUndefined ?></div>
        </div>
        <div class="mini">
          <div class="lbl"><?= h(tt('supported_goals')) ?></div>
          <div class="val"><?= (int)$supportedGoals ?></div>
        </div>
      </div>

      <div class="sdgHeroBtnRow">
        <a class="<?= $active==='all' ? 'on' : '' ?>" href="sdg.php?only=all&lang=<?= urlencode($lang) ?>"><?= h(tt('filter_all')) ?></a>
        <a class="<?= $active==='has' ? 'on' : '' ?>" href="sdg.php?only=has&lang=<?= urlencode($lang) ?>"><?= h(tt('filter_has')) ?></a>
        <a class="<?= $active==='none' ? 'on' : '' ?>" href="sdg.php?only=none&lang=<?= urlencode($lang) ?>"><?= h(tt('filter_none')) ?></a>
        <a class="<?= $active==='undef' ? 'on' : '' ?>" href="sdg.php?only=undef&lang=<?= urlencode($lang) ?>"><?= h(tt('filter_undef')) ?></a>
      </div>
    </div>
  </div>
</section>

<section class="sdg-sectionX sdg-sectionX-alt">
  <div class="container">
    <div class="sdg-aboutX">
      <div class="sdg-aboutX-media uob-reveal left">
        <img src="<?= h($aboutImg) ?>" alt="SDG">
      </div>

      <div class="sdg-aboutX-content uob-reveal right">
        <h2><?= h(tt('about_title')) ?></h2>
        <div class="sdg-lineX"></div>
        <p><?= h(tt('about_desc')) ?></p>

        
      </div>
    </div>
  </div>
</section>

<section class="sdg-sliderSection">
  <div class="container">
    <div class="sdg-centerX light">
      <h2><?= h(tt('explore_title')) ?></h2>
      <div class="sdg-lineX center white"></div>
    </div>

    <div class="sdgSliderWrap" data-sdg-wrap>
      <button class="sdgNav prev" type="button" aria-label="<?= h(tt('previous')) ?>" data-prev>‹</button>
      <button class="sdgNav next" type="button" aria-label="<?= h(tt('next')) ?>" data-next>›</button>

      <div class="sdgSliderTrack" data-track dir="ltr">
        <?php for ($i=1; $i<=17; $i++): ?>
          <?php
            $initCount = (int)($initiativeCounts[$i] ?? 0);
            $agCount   = (int)($agreementCounts[$i] ?? 0);
            $totalCount = $initCount + $agCount;

            if ($active === 'has'  && $totalCount === 0) continue;
            if ($active === 'none' && $totalCount > 0) continue;
            if ($active === 'undef') continue;

            $goalName = $sdgNames[$i][$lang] ?? '';
            $gif = sdgGifPath($i);
          ?>
          <div class="sdgSlide">
            <div class="sdgSlideCard">
              <div class="sdgSlideContent">
                <div class="sdgRole"><?= h(tt('goal_label')) ?></div>
                <div class="sdgTitle">SDG <?= $i ?> — <?= h($goalName) ?></div>

                <p class="sdgDesc">
                  <?= h(tt('goal_items')) ?>: <?= $totalCount ?> |
                  <?= h(tt('goal_initiatives')) ?>: <?= $initCount ?> |
                  <?= h(tt('goal_agreements')) ?>: <?= $agCount ?>
                </p>

                <div class="sdgMeta">
                  <span class="sdgCount"><?= $totalCount ?> <?= h($isArabic ? 'عنصر' : 'Items') ?></span>
                  <a class="sdgBtn" href="sdg-goal.php?sdg=<?= $i ?>&lang=<?= urlencode($lang) ?>">
                    <?= h(tt('view_details')) ?>
                   </a>
                </div>
              </div>

              <div class="sdgSlideImage">
                <img src="<?= h($gif) ?>" alt="SDG <?= $i ?>" loading="lazy">
              </div>
            </div>
          </div>
        <?php endfor; ?>

        <?php if ($active === 'undef'): ?>
          <div class="sdgSlide">
            <div class="sdgSlideCard">
              <div class="sdgSlideContent">
                <div class="sdgRole"><?= h(tt('undefined_title')) ?></div>
                <div class="sdgTitle"><?= h(tt('undefined_title')) ?></div>
                <p class="sdgDesc"><?= h(tt('undefined_desc')) ?></p>
                <div class="sdgMeta">
                  <span class="sdgCount"><?= (int)$totalUndefined ?></span>
                </div>
              </div>

              <div class="sdgSlideImage sdgUndefinedImg">
                <div class="sdgUndefinedBox">—</div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="sdgDots" data-dots></div>
    </div>
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
  const wrap = document.querySelector('[data-sdg-wrap]');
  if(!wrap) return;

  const track = wrap.querySelector('[data-track]');
  const slides = Array.from(track.children);
  const dotsWrap = wrap.querySelector('[data-dots]');
  const prev = wrap.querySelector('[data-prev]');
  const next = wrap.querySelector('[data-next]');
  let i = 0;
  let autoPlay;

  function renderDots(){
    dotsWrap.innerHTML = '';
    slides.forEach((_, k) => {
      const b = document.createElement('button');
      b.type = 'button';
      if(k === i) b.classList.add('active');
      b.addEventListener('click', () => go(k));
      dotsWrap.appendChild(b);
    });
  }

 function go(n){
   i = (n + slides.length) % slides.length;
   track.style.transform = 'translateX(' + (-i * 100) + '%)';
   renderDots();
  }

  function nextSlide(){ go(i + 1); }
  function prevSlide(){ go(i - 1); }

  function startAuto(){
    stopAuto();
    autoPlay = setInterval(nextSlide, 5000);
  }

  function stopAuto(){
    if(autoPlay) clearInterval(autoPlay);
  }

  prev && prev.addEventListener('click', prevSlide);
  next && next.addEventListener('click', nextSlide);

  wrap.addEventListener('mouseenter', stopAuto);
  wrap.addEventListener('mouseleave', startAuto);

  go(0);
  startAuto();
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>