<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
  header("Location: login.php?to=submit-initiative.php");
  exit;
}

$agreements = readAgreements();

$pageTitle = "إرسال مبادرة";
$pageSubtitle = "سيتم حفظ المبادرة (قيد المراجعة) حتى يعتمدها الأدمن.";
$breadcrumb = [
  ['label' => 'إرسال مبادرة', 'href' => 'submit-initiative.php', 'active' => true],
];
require_once __DIR__ . '/header.php';

$errors = [];
$success = false;

// نفس الحقول الأساسية + نضيف حقول إدارية في النهاية
$fields = [
  '_id',
  '_agreement_code',
  '_agreement_name',
  'رقم المبادرة',
  'الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)',
  'منسق المبادرة',
  'عنوان المبادرة',
  'نوع المبادرة',
  'تاريخ تنفيذ المبادرة',
  'تاريخ انتهاء المبادرة',
  'موقع تنفيذ المبادرة',
  'نبذة عن المبادرة وأهدافها',
  'الفئة المستهدفة',
  'عدد المستفيدين / المشاركين',
  'المخرجات التي تم تحقيقها ',
  'هل تدعم QS؟',
  'هل تدعم GreenMetric؟',
  'هل تدعم THE Impact Ranking؟',
  'SDG الأساسي',
  'SDG ثانوي',
  'هل نُشرت على موقع الجامعة؟',
  'رابط خبر المبادرة',
  'رابط الصور / والأدلة',
  'ملاحظات الجهة المنفذة',
  'ملاحظات VPPD',

  // ✅ admin workflow fields
  'الحالة الإدارية',
  'مرسل بواسطة',
  'تاريخ الإرسال'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $agreementCode = trim($_POST['_agreement_code'] ?? '');
  $agreementName = $agreements[$agreementCode]['اسم الاتفاقية'] ?? '';

  $data = [];
  foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

  $data['_agreement_code'] = $agreementCode;
  $data['_agreement_name'] = $agreementName;
  $data['_id'] = 'MASTER-' . time() . '-' . rand(1000, 9999);

  // set workflow defaults
  $data['الحالة الإدارية'] = 'قيد المراجعة';
  $data['مرسل بواسطة'] = $_SESSION['user_email'];
  $data['تاريخ الإرسال'] = date('Y-m-d H:i');

  if ($agreementCode === '') $errors[] = "اختيار الاتفاقية مطلوب.";
  if (($data['عنوان المبادرة'] ?? '') === '') $errors[] = "عنوان المبادرة مطلوب.";
  if (($data['SDG الأساسي'] ?? '') === '') $errors[] = "SDG الأساسي مطلوب.";

  if (!$errors) {
    // create file with header if missing
    if (!file_exists(INITIATIVES_MASTER)) {
      $fp = fopen(INITIATIVES_MASTER, 'w');
      fputcsv($fp, $fields);
      fclose($fp);
    } else {
      // ensure header contains workflow fields (upgrade-safe)
      $rows = readCsvRows(INITIATIVES_MASTER);
      $header = $rows[0] ?? [];
      $missing = array_values(array_diff($fields, $header));
      if ($missing) {
        // rewrite file with updated header, keep data
        $newHeader = array_values(array_unique(array_merge($header, $missing)));
        $outRows = [];
        $outRows[] = $newHeader;

        for ($i=1; $i<count($rows); $i++) {
          $old = $rows[$i];
          $assoc = [];
          foreach ($header as $idx=>$k) $assoc[$k] = $old[$idx] ?? '';
          $newRow = [];
          foreach ($newHeader as $k) $newRow[] = $assoc[$k] ?? '';
          $outRows[] = $newRow;
        }

        $fp = fopen(INITIATIVES_MASTER, 'w');
        foreach ($outRows as $r) fputcsv($fp, $r);
        fclose($fp);
      }
    }

    // append row
    $currentRows = readCsvRows(INITIATIVES_MASTER);
    $header = $currentRows[0] ?? $fields;

    $fp = fopen(INITIATIVES_MASTER, 'a');
    $row = [];
    foreach ($header as $k) $row[] = $data[$k] ?? '';
    fputcsv($fp, $row);
    fclose($fp);

    $success = true;
  }
}
?>

<?php if ($success): ?>
  <div class="alert alert-success">
    تم إرسال المبادرة بنجاح ✅ وهي الآن <strong>قيد المراجعة</strong>.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>".h($e)."</li>"; ?></ul>
  </div>
<?php endif; ?>

<div class="uob-card">
  <div class="uob-card-body">
    <form method="post" class="row g-3">

      <div class="col-md-6">
        <label class="form-label">الاتفاقية</label>
        <select class="form-select" name="_agreement_code">
          <option value="">اختر اتفاقية...</option>
          <?php foreach ($agreements as $code => $a): ?>
            <?php $sel = ($_POST['_agreement_code'] ?? '') === $code ? 'selected' : ''; ?>
            <option value="<?= h($code) ?>" <?= $sel ?>>
              <?= h($code) ?> — <?= h($a['اسم الاتفاقية'] ?? '') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">رقم المبادرة</label>
        <input class="form-control" name="رقم المبادرة" value="<?= h($_POST['رقم المبادرة'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">عنوان المبادرة</label>
        <input class="form-control" name="عنوان المبادرة" value="<?= h($_POST['عنوان المبادرة'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">الجهة المنفذة</label>
        <input class="form-control" name="الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)"
          value="<?= h($_POST['الجهة المنفذة داخل الجامعة (كلية / عمادة / إدارة)'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">نوع المبادرة</label>
        <input class="form-control" name="نوع المبادرة" value="<?= h($_POST['نوع المبادرة'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">تاريخ تنفيذ المبادرة</label>
        <input class="form-control" name="تاريخ تنفيذ المبادرة" value="<?= h($_POST['تاريخ تنفيذ المبادرة'] ?? '') ?>" placeholder="1-Mar-24">
      </div>

      <div class="col-md-6">
        <label class="form-label">SDG الأساسي</label>
        <input class="form-control" name="SDG الأساسي" value="<?= h($_POST['SDG الأساسي'] ?? '') ?>" placeholder="SDG 4">
      </div>

      <div class="col-md-6">
        <label class="form-label">SDG ثانوي</label>
        <input class="form-control" name="SDG ثانوي" value="<?= h($_POST['SDG ثانوي'] ?? '') ?>" placeholder="SDG 8">
      </div>

      <div class="col-12">
        <label class="form-label">نبذة عن المبادرة وأهدافها</label>
        <textarea class="form-control" name="نبذة عن المبادرة وأهدافها" rows="3"><?= h($_POST['نبذة عن المبادرة وأهدافها'] ?? '') ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">إرسال للمراجعة</button>
        <a class="btn btn-outline-secondary" href="initiatives.php">إلغاء</a>
      </div>

    </form>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>