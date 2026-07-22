<?php
$hidePageHeader = true;
$mainContainer = false;

require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$isArabic = ($lang === 'ar');

$logoDir = __DIR__ . '/assets/image/logo';
$logoUrl = 'assets/image/logo/';

$logos = [];

if (is_dir($logoDir)) {
  foreach (scandir($logoDir) as $file) {
    if ($file === '.' || $file === '..') continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
      $logos[] = $file;
    }
  }
}

sort($logos);
$partnerLinks = [
  'ACCA.png' => 'https://www.accaglobal.com/middle-east/en.html',
  'AOU.png' => 'https://www.aou.org.bh/Pages/default.aspx',
  'GM.png' => 'https://www.gmu.edu/',
  'CERN.png' => 'https://home.cern/',
  'STC.png' => 'https://www.stc.com.bh/',
  'UN.png' => 'https://www.unep.org/',
  'XPMG.png' => 'https://kpmg.com/bh/en.html',

  'مدرسة  عبدالرحمن كانو ديار.png' => 'https://arks.edu.bh/',
  'جامعة الخليج العربي.png' => 'https://www.agu.edu.bh/en',
  'جمعية المهندسيين البحرينية.png' => 'https://bse.bh/en/',
  'Colorado School Of MINES.png' => 'https://www.mines.edu/',
  'Heriot Watt University.png' => 'https://www.hw.ac.uk/',
  'جامعة الملك سعود.png' => 'https://ksu.edu.sa/en',
  'Lancaster University.png' => 'https://www.lancaster.ac.uk/',
  'Shanghai University.png' => 'https://en.shu.edu.cn/',
  'St Christopher\'s School.png' => 'https://www.st-chris.net/',
  'جامعة السلطان قابوس.png' => 'https://www.squ.edu.om/',
  'Technical University Of Crete.png' => 'https://www.tuc.gr/en/home',
  'Technology Engineering.png' => 'https://www.techengbh.com/index',
  'University of Houston.png' => 'https://www.uh.edu/',
  'University Sains Malaysia.png' => 'https://www.usm.my/en/',
  'West Virginia University.png' => 'https://www.wvu.edu/',

  'كلية الإمام مالك للشريعة و القانون.png' => 'https://imc.gov.ae/en/',
  'Incheon National University.png' => 'https://www.inu.ac.kr/inuengl/8509/subview.do',
  'الجمعية الخليجية للصيانة و الأعتمادية.png' => 'https://gsmrgulf.org/',
  'الاتحاد الخليجي للبتروكيماويات والكيماويات.png' => 'https://gpcachem.org/',
  'الجامعة الملكية للبنات.png' => 'https://www.ruw.edu.bh/',
  'مجلس التعاون لدول الخليج العربي.png' => 'https://www.gcc-sg.org/',
  'الخدمات الطبية الملكية.png' => 'https://rms.bh/',
  'الجمارك.png' => 'https://www.customs.gov.bh/',
  'هيئة المحاسبة و المراجعة للمؤسسات المالية والإسلامية.png' => 'https://jlsi.gov.bh/',
  'المركز الوطني للأمن السيبراني.png' => 'https://www.ncsc.gov.bh/ar/index.html',
  'المؤسسة الملكية للأعمال الإنسانية.png' => 'https://www.rhf.gov.bh/ar/page/New-Home',
  'وزارة الاشغال.png' => 'https://www.works.gov.bh/English/Pages/home2.aspx',
  'وزارة النفط والبيئة.png' => 'https://www.moo.gov.bh/moo/ar/',
  'معهد الدراسات القضائية والقانونية.png' => 'https://aaoifi.com/',


  ];
?>

<style>
.all-partners-page {
  padding: 90px 0 !important;
  background: #f7f9fd;
}

.partners-logo-head {
  text-align: center;
  margin-bottom: 45px;
}

.partners-logo-head .landing-underline {
  margin: 18px auto 0;
}

.all-partners-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 22px;
}

.partner-logo-card {
  height: 150px;
  background: #ffffff;
  border: 1px solid rgba(230,235,242,.95);
  border-radius: 20px;
  box-shadow: 0 10px 26px rgba(2,8,23,.08);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
  cursor: pointer;
  transition: .25s ease;
  overflow: hidden;
  text-decoration: none !important;
}

.partner-logo-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 18px 44px rgba(2,8,23,.13);
}

.partner-logo-card img {
  max-width: 90%;
  max-height: 120px;
  object-fit: contain;
  transform: scale(1.45);
  filter: grayscale(100%);
  opacity: .72;
  transition: .25s ease;
}

.partner-logo-card:hover img {
  filter: grayscale(0%);
  opacity: 1;
  transform: scale(1.6);
}

@media (max-width: 992px) {
  .all-partners-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 576px) {
  .all-partners-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .partner-logo-card {
    height: 135px;
  }

  .partner-logo-card img {
    transform: scale(1.25);
  }

  .partner-logo-card:hover img {
    transform: scale(1.35);
  }
}
</style>

<section class="landing-section all-partners-page">
  <div class="container">

    <div class="partners-logo-head">
      <h1 class="landing-h2"><?= h($isArabic ? 'كل الشركاء' : 'All Partners') ?></h1>
      <div class="landing-underline"></div>
    </div>

    <div class="all-partners-grid">

      <?php foreach ($logos as $logo): ?>
        <?php
          $name = pathinfo($logo, PATHINFO_FILENAME);
          $displayName = str_replace(['-', '_'], ' ', $name);
          $src = $logoUrl . rawurlencode($logo);
          $website = $partnerLinks[$logo] ?? '#';
        ?>

        <a class="partner-logo-card" href="<?= h($website) ?>" target="_blank" rel="noopener">
          <img src="<?= h($src) ?>" alt="<?= h($displayName) ?>">
        </a>

      <?php endforeach; ?>

    </div>

  </div>
</section>

<?php
require_once __DIR__ . '/footer.php';
?>