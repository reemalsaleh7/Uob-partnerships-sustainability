<?php

$hidePageHeader = true;
$mainContainer = false;
require_once __DIR__ . '/header.php';

$lang = $_SESSION['lang'] ?? ($_GET['lang'] ?? 'ar');
$code = trim($_GET['code'] ?? '');

// Canonical public Agreements come from PostgreSQL. Approved legacy CSV
// details remain resolvable temporarily so existing Initiative links do not
// break before that separate module is migrated.
$agreements = readPublishedAgreements($lang);

// نجيب الاتفاقية
$a = null;
foreach ($agreements as $ag) {
  if (($ag['agreement_code'] ?? '') === $code) {
    $a = $ag;
    break;
  }
}

if ($a === null && $code !== '') {
  $legacyAgreements = readAgreements(true);
  $legacy = $legacyAgreements[$code] ?? null;

  if (is_array($legacy)) {
    $legacy['source'] = 'legacy_csv_compatibility';
    $a = $legacy;
  }
}

// نجيب كل المبادرات
$all = loadAllInitiatives();

// فلترة المبادرات المرتبطة
$related = array_values(array_filter($all, function($it) use ($code){
  return trim($it['_agreement_code'] ?? $it['agreement_code'] ?? '') === trim($code);
}));

$heroBg = 'assets/image/THEM/agreements.png';

function agreementDetailValue(mixed $value): string {
  $text = trim((string)$value);
  return $text === '' ? '—' : $text;
}
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

            <div><strong>الجهة:</strong> <?= h(agreementDetailValue($a['partner_entity'] ?? '')) ?></div>
            <div><strong>الدولة:</strong> <?= h(agreementDetailValue($a['country'] ?? '')) ?></div>
            <div><strong>البدء:</strong> <?= h(agreementDetailValue($a['start_date'] ?? '')) ?></div>
            <div><strong>الانتهاء:</strong> <?= h(agreementDetailValue($a['end_date'] ?? '')) ?></div>
            <div><strong>التجديد:</strong> <?= h(agreementDetailValue($a['auto_renew'] ?? '')) ?></div>
            <div><strong>الجهة المنفذة:</strong> <?= h(agreementDetailValue($a['owner_entity'] ?? '')) ?></div>

            <?php if (!empty($a['approved_at'])): ?>
              <div><strong>تاريخ الاعتماد:</strong> <?= h((string)$a['approved_at']) ?></div>
            <?php endif; ?>

            <?php $agreementSummary = trim((string)($a['agreement_summary'] ?? $a['summary'] ?? '')); ?>
            <?php if ($agreementSummary !== ''): ?>
              <hr>
              <div><strong>الملخص:</strong> <?= h($agreementSummary) ?></div>
            <?php endif; ?>

            <?php if (!empty($a['goals'])): ?><hr><div><strong>الأهداف:</strong> <?= nl2br(h((string)$a['goals'])) ?></div><?php endif; ?>
            <?php if (!empty($a['expected_value'])): ?><div class="mt-2"><strong>القيمة والأثر:</strong> <?= nl2br(h((string)$a['expected_value'])) ?></div><?php endif; ?>
            <?php if (!empty($a['focus_area'])): ?><div class="mt-2"><strong>مجالات التركيز:</strong> <?= h((string)$a['focus_area']) ?></div><?php endif; ?>
            <?php if (!empty($a['sdgs'])): ?><div class="mt-2"><strong>أهداف التنمية المستدامة:</strong> <?= h((string)$a['sdgs']) ?></div><?php endif; ?>
            <?php if (!empty($a['rankings'])): ?><div class="mt-2"><strong>التصنيفات:</strong> <?= h(str_replace('_', ' ', (string)$a['rankings'])) ?></div><?php endif; ?>
            <?php if (!empty($a['agreement_signing_link'])): ?><div class="mt-3"><a href="<?= h((string)$a['agreement_signing_link']) ?>" target="_blank" rel="noopener noreferrer">خبر أو رابط توقيع الاتفاقية</a></div><?php endif; ?>
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
