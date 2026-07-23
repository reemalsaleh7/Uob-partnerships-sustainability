<?php

$hidePageHeader = true;
$mainContainer = false;
require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$code = trim($_GET['code'] ?? '');

// ✅ النظام الجديد
$agreements = readAgreements();

// نجيب الاتفاقية
$a = null;
foreach ($agreements as $ag) {
  if (($ag['agreement_code'] ?? '') === $code) {
    $a = $ag;
    break;
  }
}

// نجيب كل المبادرات
$all = loadAllInitiatives();

// فلترة المبادرات المرتبطة
$related = array_values(array_filter($all, function($it) use ($code){
  return trim($it['_agreement_code'] ?? $it['agreement_code'] ?? '') === trim($code);
}));

$heroBg = 'assets/image/THEM/agreements.png';
?>

<?php if (!$a): ?>
  <div class="container" style="padding:28px 0;">
    <div class="alert alert-warning">لم يتم العثور على الاتفاقية.</div>
  </div>
<?php else: ?>

<section class="sdg-detailHeroX">
  <div class="bg" style="background-image:url('<?= h($heroBg) ?>')"></div>
  <div class="ov"></div>

  <div class="container">
    <div class="card uob-reveal">
      <h1><?= h($a['agreement_name'] ?? '') ?></h1>

      <p>
        <?= h($a['partner_entity'] ?? '') ?> — <?= h($a['country'] ?? '') ?>
      </p>

      <div class="meta">
        <span class="pill">كود: <?= h($a['agreement_code'] ?? '') ?></span>
        <span class="pill">الحالة: <?= h($a['status'] ?? '') ?></span>
        <span class="pill">النوع: <?= h($a['agreement_type'] ?? '') ?></span>

        <a class="back" href="agreements.php">← رجوع</a>
      </div>
    </div>
  </div>
</section>

<section class="sdg-sectionX">
  <div class="container">
    <div class="row g-4">

      <!-- Summary -->
      <div class="col-lg-4">
        <div class="uob-card">
          <div class="uob-card-body">
            <div class="fw-bold mb-2">ملخص الاتفاقية</div>

            <div><strong>الجهة:</strong> <?= h($a['partner_entity'] ?? '') ?></div>
            <div><strong>الدولة:</strong> <?= h($a['country'] ?? '') ?></div>
            <div><strong>البدء:</strong> <?= h($a['start_date'] ?? '') ?></div>
            <div><strong>الانتهاء:</strong> <?= h($a['end_date'] ?? '') ?></div>
            <div><strong>التجديد:</strong> <?= h($a['auto_renew'] ?? '') ?></div>
            <div><strong>الجهة المنفذة:</strong> <?= h($a['owner_entity'] ?? '') ?></div>
          </div>
        </div>
      </div>

      <!-- Initiatives -->
      <div class="col-lg-8">
        <div class="uob-card">
          <div class="uob-card-body">

            <div class="fw-bold mb-3">المبادرات المرتبطة</div>

            <table class="table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>العنوان</th>
                  <th>الجهة</th>
                  <th>SDG</th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($related as $it): ?>
                  <tr>
                    <td><?= h($it['initiative_number'] ?? '') ?></td>
                    <td><?= h($it['title'] ?? '') ?></td>
                    <td><?= h($it['entity'] ?? '') ?></td>
                    <td><?= h($it['sdg_primary'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>

                <?php if (!$related): ?>
                  <tr>
                    <td colspan="4">لا توجد مبادرات</td>
                  </tr>
                <?php endif; ?>
              </tbody>

            </table>

          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>