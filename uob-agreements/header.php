<?php
// header.php (ROOT)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/** Language (session + ?lang=ar/en) */
$supportedLang = ['ar', 'en'];
if (isset($_GET['lang'])) {
  $lg = strtolower(trim($_GET['lang']));
  if (in_array($lg, $supportedLang, true)) $_SESSION['lang'] = $lg;
}
$lang = $_SESSION['lang'] ?? 'ar';
$isRtl = ($lang === 'ar');






// دالة الترجمة المحسنة - تدعم معامل واحد أو معاملين
function t(string $key, string $english = null): string {
  global $lang;
  $currentLang = $_SESSION['lang'] ?? 'ar';
  
  // إذا كانت اللغة إنجليزية وتم توفير نص إنجليزي مباشر
  if ($currentLang === 'en' && $english !== null) {
    return $english;
  }
  
  $dict = [
    'ar' => [
      'app_name' => 'الشراكات والأثر المستدام – جامعة البحرين',
      'search_initiative' => 'بحث عن مبادرة...',
      'home' => 'الرئيسية',
      'agreements' => 'الاتفاقيات',
      'initiatives' => 'المبادرات',
      'sdg' => 'SDG',
      'add_agreement' => 'إضافة اتفاقية',
      'add_initiative' => 'إضافة مبادرة',
      'login' => 'تسجيل الدخول',
      'logout' => 'تسجيل الخروج',
      'english' => 'English',
      'arabic' => 'العربية',
      // إضافة المفاتيح الجديدة للصفحة
      'add_initiative_page_title' => 'إضافة مبادرة',
      'add_initiative_hero_title' => 'إضافة مبادرة جديدة',
      'add_initiative_hero_desc' => 'استخدم النموذج الشامل لإضافة مبادرة جديدة وربطها بالاتفاقيات والفئات المستهدفة وأهداف التنمية المستدامة.',
      'available_agreements' => 'عدد الاتفاقيات المتاحة للربط',
      'approved_types' => 'أنواع المبادرات المعتمدة',
      'multiple_targets' => 'فئات مستهدفة متعددة',
      'sdg_goals' => 'أهداف التنمية المستدامة',
      'goals' => 'هدف',
      'initiative_form' => 'نموذج إدخال المبادرة',
      'fill_required_fields' => 'يرجى تعبئة جميع الحقول الإلزامية بدقة',
      'general_info' => 'معلومات عامة',
      'timing_location' => 'التوقيت والموقع',
      'beneficiaries_impact' => 'المستفيدون والأثر',
      'rankings_sdgs' => 'التصنيفات والتنمية المستدامة',
      'documentation_notes' => 'التوثيق والملاحظات',
      'basic_data_agreement' => 'البيانات الأساسية وربط الاتفاقية',
      'related_agreement_question' => 'هل هذه المبادرة متعلقة باتفاقية قائمة؟',
      'yes_related' => 'نعم، المبادرة مرتبطة باتفاقية قائمة',
      'no_related' => 'لا، المبادرة مستقلة وغير مرتبطة باتفاقية',
      'select_agreement' => 'اختيار الاتفاقية',
      'select_placeholder' => 'اختر اتفاقية...',
      'agreement_select_help' => 'عند اختيار الاتفاقية ستظهر بياناتها أسفل الحقل مباشرة.',
      'agreement_name' => 'اسم الاتفاقية',
      'agreement_type' => 'نوع الاتفاقية',
      'partner_entity' => 'الجهة المتعاونة',
      'country' => 'الدولة',
      'responsible_entity' => 'الجهة المعنية بالتنفيذ',
      'agreement_status' => 'حالة الاتفاقية',
      'initiative_number' => 'رقم المبادرة',
      'initiative_title' => 'عنوان المبادرة',
      'executing_entity' => 'الجهة المنفذة داخل الجامعة',
      'initiative_coordinator' => 'منسق المبادرة',
      'initiative_type' => 'نوع المبادرة',
      'brief_summary' => 'نبذة مختصرة عن المبادرة',
      'start_date' => 'تاريخ تنفيذ المبادرة',
      'end_date' => 'تاريخ انتهاء المبادرة',
      'location_question' => 'أين تم تنفيذ المبادرة؟',
      'inside_university' => 'داخل الجامعة',
      'outside_university' => 'خارج الجامعة',
      'outside_location_label' => 'إذا كانت خارج الجامعة، أين؟',
      'description_objectives' => 'نبذة عن المبادرة وأهدافها',
      'target_group' => 'الفئة المستهدفة (يمكن اختيار أكثر من فئة)',
      'beneficiaries_male' => 'عدد المستفيدين - الذكور',
      'beneficiaries_female' => 'عدد المستفيدين - الإناث',
      'youth_question' => 'هل الفئة المستفيدة من فئة الشباب (18 إلى 35)؟',
      'yes' => 'نعم',
      'no' => 'لا',
      'achieved_outputs' => 'المخرجات التي تم تحقيقها',
      'qs_support' => 'هل تدعم QS؟',
      'greenmetric_support' => 'هل تدعم GreenMetric؟',
      'sdg_question' => 'هل هذه المبادرة تخدم أهداف التنمية المستدامة؟',
      'select_sdg_goals' => 'اختر الأهداف المناسبة (اختيار متعدد)',
      'published_question' => 'هل نُشرت المبادرة على موقع الجامعة؟',
      'news_link' => 'رابط خبر المبادرة',
      'images_link' => 'رابط الصور / الأدلة',
      'entity_notes' => 'ملاحظات الجهة المنفذة',
      'vppd_notes' => 'ملاحظات VPPD',
      'previous' => 'السابق',
      'next' => 'التالي',
      'cancel' => 'إلغاء',
      'save' => 'حفظ المبادرة',
      'initiative_added_success' => 'تمت إضافة المبادرة بنجاح.',
      'go_to_initiatives' => 'الذهاب إلى قائمة المبادرات',
      'example_number' => 'مثال: 1 أو 2025001',
      'write_title' => 'اكتب عنوان المبادرة بشكل واضح',
      'example_entity' => 'مثال: كلية الهندسة',
      'coordinator_name' => 'اسم منسق المبادرة',
      'select_type' => 'اختر النوع...',
      'optional' => 'اختياري',
      'example_location' => 'مثال: مقر الجهة الشريكة / محافظة / مدينة / دولة',
      'write_description' => 'اكتب وصف المبادرة، أهدافها، نطاقها، وطريقة التنفيذ',
      'example_outputs' => 'مثال: عدد الورش المنفذة، الأدلة، التقارير، الشهادات، التوصيات...',
      'select' => 'اختر...',
      'google_drive_link' => 'رابط Google Drive أو أي رابط أدلة',
      'additional_notes' => 'أي ملاحظات إضافية خاصة بالجهة المنفذة',
      'required_field' => 'يرجى تحديد هل المبادرة مرتبطة باتفاقية قائمة أم لا.',
      'agreement_required' => 'اختيار الاتفاقية مطلوب.',
      'title_required' => 'عنوان المبادرة مطلوب.',
      'type_required' => 'نوع المبادرة مطلوب.',
      'entity_required' => 'الجهة المنفذة مطلوبة.',
      'date_required' => 'تاريخ تنفيذ المبادرة مطلوب.',
      'location_required' => 'يرجى تحديد موقع تنفيذ المبادرة.',
      'outside_location_required' => 'يرجى كتابة مكان تنفيذ المبادرة خارج الجامعة.',
      'sdg_required' => 'اختر هدف تنمية مستدامة واحدًا على الأقل.',
      'news_link_required' => 'رابط خبر المبادرة مطلوب عند اختيار نعم.',
    ],
    'en' => [
      'app_name' => 'UOB Partnerships & Sustainable Impact',
      'search_initiative' => 'Search initiatives...',
      'home' => 'Home',
      'agreements' => 'Agreements',
      'initiatives' => 'Initiatives',
      'sdg' => 'SDGs',
      'add_agreement' => 'Add Agreement',
      'add_initiative' => 'Add Initiative',
      'login' => 'Login',
      'logout' => 'Logout',
      'english' => 'English',
      'arabic' => 'Arabic',
      'add_initiative_page_title' => 'Add Initiative',
      'add_initiative_hero_title' => 'Add New Initiative',
      'add_initiative_hero_desc' => 'Use the comprehensive form to add a new initiative and link it to agreements, target groups, and sustainable development goals.',
      'available_agreements' => 'Available agreements to link',
      'approved_types' => 'Approved initiative types',
      'multiple_targets' => 'Multiple target groups',
      'sdg_goals' => 'Sustainable Development Goals',
      'goals' => 'Goals',
      'initiative_form' => 'Initiative Entry Form',
      'fill_required_fields' => 'Please fill all required fields accurately',
      'general_info' => 'General Info',
      'timing_location' => 'Timing & Location',
      'beneficiaries_impact' => 'Beneficiaries & Impact',
      'rankings_sdgs' => 'Rankings & SDGs',
      'documentation_notes' => 'Documentation & Notes',
      'basic_data_agreement' => 'Basic Data & Agreement Linking',
      'related_agreement_question' => 'Is this initiative related to an existing agreement?',
      'yes_related' => 'Yes, the initiative is linked to an existing agreement',
      'no_related' => 'No, the initiative is independent and not linked to an agreement',
      'select_agreement' => 'Select Agreement',
      'select_placeholder' => 'Select an agreement...',
      'agreement_select_help' => 'When you select an agreement, its data will appear below the field.',
      'agreement_name' => 'Agreement Name',
      'agreement_type' => 'Agreement Type',
      'partner_entity' => 'Partner Entity',
      'country' => 'Country',
      'responsible_entity' => 'Responsible Entity',
      'agreement_status' => 'Agreement Status',
      'initiative_number' => 'Initiative Number',
      'initiative_title' => 'Initiative Title',
      'executing_entity' => 'Executing Entity within the University',
      'initiative_coordinator' => 'Initiative Coordinator',
      'initiative_type' => 'Initiative Type',
      'brief_summary' => 'Brief Summary about the initiative',
      'start_date' => 'Initiative Start Date',
      'end_date' => 'Initiative End Date',
      'location_question' => 'Where was the initiative implemented?',
      'inside_university' => 'Inside the University',
      'outside_university' => 'Outside the University',
      'outside_location_label' => 'If outside the university, where?',
      'description_objectives' => 'Initiative Description & Objectives',
      'target_group' => 'Target Group (Multiple selections allowed)',
      'beneficiaries_male' => 'Number of Beneficiaries - Male',
      'beneficiaries_female' => 'Number of Beneficiaries - Female',
      'youth_question' => 'Are the beneficiaries from the youth category (18-35)?',
      'yes' => 'Yes',
      'no' => 'No',
      'achieved_outputs' => 'Achieved Outputs',
      'qs_support' => 'Does it support QS?',
      'greenmetric_support' => 'Does it support GreenMetric?',
      'sdg_question' => 'Does this initiative serve the Sustainable Development Goals?',
      'select_sdg_goals' => 'Select appropriate goals (Multiple selections)',
      'published_question' => 'Has the initiative been published on the university website?',
      'news_link' => 'Initiative News Link',
      'images_link' => 'Images / Evidence Link',
      'entity_notes' => 'Executing Entity Notes',
      'vppd_notes' => 'VPPD Notes',
      'previous' => 'Previous',
      'next' => 'Next',
      'cancel' => 'Cancel',
      'save' => 'Save Initiative',
      'initiative_added_success' => 'Initiative added successfully.',
      'go_to_initiatives' => 'Go to initiatives list',
      'example_number' => 'Example: 1 or 2025001',
      'write_title' => 'Write the initiative title clearly',
      'example_entity' => 'Example: College of Engineering',
      'coordinator_name' => 'Initiative coordinator name',
      'select_type' => 'Select type...',
      'optional' => 'Optional',
      'example_location' => 'Example: Partner headquarters / Governorate / City / Country',
      'write_description' => 'Write the initiative description, objectives, scope, and implementation method',
      'example_outputs' => 'Example: Number of workshops conducted, evidence, reports, certificates, recommendations...',
      'select' => 'Select...',
      'google_drive_link' => 'Google Drive link or any evidence link',
      'additional_notes' => 'Any additional notes from the executing entity',
      'required_field' => 'Please specify whether the initiative is related to an existing agreement or not.',
      'agreement_required' => 'Agreement selection is required.',
      'title_required' => 'Initiative title is required.',
      'type_required' => 'Initiative type is required.',
      'entity_required' => 'Executing entity is required.',
      'date_required' => 'Initiative start date is required.',
      'location_required' => 'Please specify the initiative location.',
      'outside_location_required' => 'Please write the location outside the university.',
      'sdg_required' => 'Select at least one Sustainable Development Goal.',
      'news_link_required' => 'Initiative news link is required when selecting Yes.',
    ],
  ];
  
  // إذا كان المفتاح موجوداً في القاموس
  if (isset($dict[$currentLang][$key])) {
    return $dict[$currentLang][$key];
  }
  
  // إذا كان المفتاح غير موجود ولم يتم توفير نص إنجليزي بديل
  if ($english !== null) {
    return $english;
  }
  
  return $key;
}

$pageTitle = $pageTitle ?? t('app_name');
$pageSubtitle = $pageSubtitle ?? '';
$breadcrumb = $breadcrumb ?? [];

// Layout flags (optional)
$hidePageHeader = $hidePageHeader ?? false;
$mainContainer = $mainContainer ?? true;

// admin base
$currentFilePath = $_SERVER['PHP_SELF'] ?? '';

$isAdmin = str_contains($currentFilePath, '/admin/');
$isPartnership = str_contains($currentFilePath, '/partnership/');

$base = ($isAdmin || $isPartnership) ? '../' : '';
$agreementWorkspaceEnabled = defined('AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN')
  && AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN;
$agreementCreatePath = $agreementWorkspaceEnabled
  ? 'workspace/agreement-form.php'
  : 'admin/add-agreement.php?lang=' . urlencode($lang);
$agreementReviewPath = $agreementWorkspaceEnabled
  ? 'workspace/agreements.php'
  : 'admin/review-agreements.php?lang=' . urlencode($lang);

$logoPath = $base . 'assets/image/THEM/uob_logo.png';
$isLoggedIn = !empty($_SESSION['user_email']);


$updatesCount = 0;

if ($isLoggedIn) {
  $notificationsFile = __DIR__ . '/data/notifications.csv';

  if (file_exists($notificationsFile) && ($fp = fopen($notificationsFile, 'r')) !== false) {
    $header = fgetcsv($fp);

    if ($header) {
      $header = array_map('trim', $header);

      while (($row = fgetcsv($fp)) !== false) {
        $row = array_pad($row, count($header), '');
        $n = array_combine($header, $row);

        if (
          trim($n['status'] ?? '') === 'active' &&
          trim($n['target_type'] ?? '') === 'all' &&
          trim($n['is_read'] ?? '0') === '0'
        ) {
          $updatesCount++;
        }
      }
    }

    fclose($fp);
  }
}

/* preserve current query when switching language */
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ''), '?');
$currentQuery = $_GET ?? [];
unset($currentQuery['lang']);

$langSwitchQueryEn = http_build_query(array_merge($currentQuery, ['lang' => 'en']));
$langSwitchQueryAr = http_build_query(array_merge($currentQuery, ['lang' => 'ar']));

$langSwitchUrlEn = $currentPath . ($langSwitchQueryEn ? ('?' . $langSwitchQueryEn) : '');
$langSwitchUrlAr = $currentPath . ($langSwitchQueryAr ? ('?' . $langSwitchQueryAr) : '');
?>
<!doctype html>
<html lang="<?= h($lang) ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= $base ?>assets/image/THEM/uob.png">

  <?php if ($isRtl): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
  <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php endif; ?>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">

  <link href="<?= $base ?>css/style.css?v=3005" rel="stylesheet">

  <?php if (!empty($extraCss)): ?>
  <?php foreach ($extraCss as $cssFile): ?>
    <link href="<?= $base . h($cssFile) ?>?v=1" rel="stylesheet">
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($extraHead)) echo $extraHead; ?>

<style>
.updates-dot{
  position:absolute;
  top:4px;
  right:-2px;
  width:9px;
  height:9px;
  background:#d4af37;
  border-radius:50%;
  display:inline-block;
  box-shadow:0 0 0 3px rgba(212,175,55,.18);
}
[dir="rtl"] .updates-dot{
  right:auto;
  left:-2px;
}
</style>
</head>
<body>

<!-- Utility bar -->
<div class="uob-utility-bar border-bottom">
  <div class="container d-flex align-items-center gap-3 py-2">
    <form class="<?= $isRtl ? 'ms-auto' : 'me-auto' ?> uob-search" action="<?= $base ?>initiatives.php" method="get">
      <input name="q" class="form-control form-control-sm" type="search" placeholder="<?= h(t('search_initiative')) ?>">
      <input type="hidden" name="lang" value="<?= h($lang) ?>">
    </form>

    <?php if ($lang === 'ar'): ?>
      <a class="uob-link" href="<?= h($langSwitchUrlEn) ?>"><?= h(t('english')) ?></a>
    <?php else: ?>
      <a class="uob-link" href="<?= h($langSwitchUrlAr) ?>"><?= h(t('arabic')) ?></a>
    <?php endif; ?>
  </div>
</div>

<nav class="navbar navbar-expand-lg bg-white border-bottom uob-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>index.php?lang=<?= h($lang) ?>">
      <img src="<?= h($logoPath) ?>" alt="UOB Logo" style="height:40px;width:auto;">
      <span class="fw-bold"><?= h(t('app_name')) ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav <?= $isRtl ? 'me-auto' : 'ms-auto' ?> mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link" href="<?= $base ?>index.php?lang=<?= h($lang) ?>">
            <?= h(t('home')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= $base ?>agreements.php?lang=<?= h($lang) ?>">
            <?= h(t('agreements')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= $base ?>initiatives.php?lang=<?= h($lang) ?>">
            <?= h(t('initiatives')) ?>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= $base ?>sdg.php?lang=<?= h($lang) ?>">
            <?= h(t('sdg')) ?>
          </a>
        </li>

         <li class="nav-item">
            <a class="nav-link" href="<?= $base ?>partnership/partners.php?lang=<?= h($lang) ?>">
              <?= $lang === 'ar' ? 'خريطة الاتفاقيات' : 'Partnership Map' ?>
           </a>
         </li>


<?php if ($isLoggedIn): ?>
  <li class="nav-item">
    <a class="nav-link position-relative" href="<?= $base ?>notifications.php?lang=<?= h($lang) ?>">
      <?= $isRtl ? 'المستجدات' : 'Updates' ?>
      <?php if ($updatesCount > 0): ?>
        <span class="updates-dot"></span>
      <?php endif; ?>
    </a>
  </li>
<?php endif; ?>


<?php if (($_SESSION['user_email'] ?? '') === 'admin@uob.edu.bh'): ?>
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle admin-dropdown-btn"
       href="<?= $base ?>admin/dashboard.php?lang=<?= h($lang) ?>"
       role="button"
       data-bs-toggle="dropdown"
       aria-expanded="false">
      <?= $isRtl ? 'لوحة الأدمن' : 'Admin Dashboard' ?>
    </a>

    <ul class="dropdown-menu admin-dropdown-menu">
      <li>
        <a class="dropdown-item" href="<?= $base ?>admin/dashboard.php?lang=<?= h($lang) ?>">
          <?= $isRtl ? 'الرئيسية' : 'Dashboard Home' ?>
        </a>
      </li>

      <li><hr class="dropdown-divider"></li>

      <li>
        <a class="dropdown-item" href="<?= h($base . $agreementCreatePath) ?>">
          <?= $isRtl ? 'إضافة اتفاقية' : 'Add Agreement' ?>
        </a>
      </li>

      <li>
        <a class="dropdown-item" href="<?= $base ?>admin/review-initiative-requests.php?lang=<?= h($lang) ?>">
          <?= $isRtl ? 'طلبات موافقة المبادرات' : 'Initiative Approval Requests' ?>
        </a>
      </li>

      <li>
        <a class="dropdown-item" href="<?= $base ?>admin/review-initiatives.php?lang=<?= h($lang) ?>">
          <?= $isRtl ? 'مراجعة المبادرات' : 'Review Initiatives' ?>
        </a>
      </li>

      <li>
        <a class="dropdown-item" href="<?= h($base . $agreementReviewPath) ?>">
          <?= $isRtl ? 'مراجعة الاتفاقيات' : 'Review Agreements' ?>
        </a>
      </li>
    </ul>
  </li>


<?php endif; ?>
      </ul>

      <div class="d-flex gap-2">
        <?php if (!$isLoggedIn): ?>
          <a href="<?= $base ?>login.php?lang=<?= h($lang) ?>" class="btn btn-outline-primary btn-sm"><?= h(t('login')) ?></a>
        <?php else: ?>
          <a href="<?= $base ?>logout.php?lang=<?= h($lang) ?>" class="btn btn-outline-secondary btn-sm"><?= h(t('logout')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<?php if (!$hidePageHeader): ?>
<header class="uob-page-header">
  <div class="container py-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-2">
        <li class="breadcrumb-item"><a href="<?= $base ?>index.php?lang=<?= h($lang) ?>"><?= h(t('home')) ?></a></li>
        <?php foreach ($breadcrumb as $item): ?>
          <li class="breadcrumb-item <?= !empty($item['active']) ? 'active' : '' ?>">
            <?php if (!empty($item['active'])): ?>
              <?= h($item['label'] ?? '') ?>
            <?php else: ?>
              <a href="<?= h($base . ($item['href'] ?? '#')) ?>"><?= h($item['label'] ?? '') ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>

    <h1 class="h3 fw-bold mb-1"><?= h($pageTitle) ?></h1>
    <?php if ($pageSubtitle): ?>
      <p class="text-muted mb-0"><?= h($pageSubtitle) ?></p>
    <?php endif; ?>
  </div>
</header>
<?php endif; ?>

<?php if ($mainContainer): ?>
  <main class="container py-4">
<?php else: ?>
  <main class="py-0">
<?php endif; ?>
