<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$language = workspaceResolveLanguage();
$isArabic = $language === 'ar';
$text = static fn (string $english, string $arabic): string =>
    $isArabic ? $arabic : $english;

workspaceHeader(
    $text('Workflow review', 'مراجعة سير العمل'),
    'workflow',
    ['assets/css/workflow-generic-review.css']
);
?>

<section class="page-heading generic-review-heading">
    <div>
        <p class="eyebrow mb-2"><?= htmlspecialchars($text('Configurable Workflow', 'سير عمل قابل للتخصيص'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="display-6 mb-2" data-review-page-title><?= htmlspecialchars($text('Agreement review', 'مراجعة الاتفاقية'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0" data-review-page-meta></p>
    </div>
    <a class="btn btn-outline-secondary" href="workflow-inbox.php"><?= htmlspecialchars($text('Back to review inbox', 'العودة لصندوق المراجعة'), ENT_QUOTES, 'UTF-8') ?></a>
</section>

<div class="alert alert-danger d-none mt-4" role="alert" tabindex="-1" data-review-alert></div>
<div class="alert alert-success d-none mt-4" role="status" tabindex="-1" data-review-success></div>

<div class="generic-review-loading mt-4" data-review-loading>
    <span class="spinner-border" aria-hidden="true"></span>
    <p class="mb-0"><?= htmlspecialchars($text('Loading review assignment…', 'جاري تحميل مهمة المراجعة…'), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="generic-review-layout d-none mt-4" data-review-content>
    <main class="workspace-card generic-review-main">
        <div class="generic-review-stage-header">
            <div class="generic-review-stage-number" data-review-phase></div>
            <div>
                <p class="small text-secondary mb-1"><?= htmlspecialchars($text('Your current stage', 'مرحلتك الحالية'), ENT_QUOTES, 'UTF-8') ?></p>
                <h2 class="h4 mb-1" data-review-stage-label></h2>
                <p class="small text-secondary mb-0" data-review-responsibility></p>
            </div>
        </div>

        <section class="generic-agreement-summary mt-4">
            <h3 class="h5" data-review-agreement-title></h3>
            <p class="text-secondary mb-0" data-review-agreement-description></p>
            <a class="btn btn-sm btn-outline-primary mt-3" data-review-agreement-link><?= htmlspecialchars($text('Open Agreement record', 'فتح سجل الاتفاقية'), ENT_QUOTES, 'UTF-8') ?></a>
        </section>

        <section class="mt-4">
            <h3 class="h6 mb-3"><?= htmlspecialchars($text('Reviews in this phase', 'المراجعات في هذه المجموعة'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="generic-phase-list" data-review-phase-steps></div>
        </section>

        <section class="generic-optional-panel d-none mt-4" data-review-optional-panel>
            <div>
                <h3 class="h6 mb-1"><?= htmlspecialchars($text('Optional stages in the next phase', 'المراحل الاختيارية في المجموعة التالية'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p class="small text-secondary mb-0"><?= htmlspecialchars(
                    $text(
                        'Select the optional reviews to include before approving this stage.',
                        'حددي المراجعات الاختيارية المراد إدخالها قبل اعتماد هذه المرحلة.'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></p>
            </div>
            <div class="generic-optional-list mt-3" data-review-optional-list></div>
        </section>

        <form class="generic-decision-form mt-4" data-review-form>
            <label class="form-label" for="generic-review-comment"><?= htmlspecialchars($text('Decision note', 'ملاحظة القرار'), ENT_QUOTES, 'UTF-8') ?></label>
            <textarea
                class="form-control"
                id="generic-review-comment"
                rows="4"
                maxlength="4000"
                placeholder="<?= htmlspecialchars($text('A reason is required when returning or rejecting', 'السبب مطلوب عند الإرجاع أو الرفض'), ENT_QUOTES, 'UTF-8') ?>"
                data-review-comment
            ></textarea>

            <div class="generic-decision-actions mt-3">
                <button class="btn btn-success" type="button" data-review-action="APPROVE"><?= htmlspecialchars($text('Approve and continue', 'اعتماد ومتابعة'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn btn-outline-warning" type="button" data-review-action="REQUEST_CHANGES"><?= htmlspecialchars($text('Return for changes', 'إرجاع للتعديل'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn btn-outline-danger" type="button" data-review-action="REJECT"><?= htmlspecialchars($text('Reject', 'رفض'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
    </main>

    <aside class="workspace-card generic-review-help">
        <h2 class="h6"><?= htmlspecialchars($text('How this route works', 'طريقة عمل هذا المسار'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="small text-secondary"><?= htmlspecialchars(
            $text(
                'Sequential stages wait for the previous phase. Parallel stages in the same phase must all finish before the route continues.',
                'المراحل المتسلسلة تنتظر المجموعة السابقة. المراحل المتوازية في المجموعة نفسها يجب أن تنتهي جميعها قبل انتقال المسار.'
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
        <div class="generic-help-item">
            <strong><?= htmlspecialchars($text('Approve', 'اعتماد'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($text('Completes your stage and advances when the phase is complete.', 'ينهي مرحلتك وينقل المسار عند اكتمال المجموعة.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="generic-help-item">
            <strong><?= htmlspecialchars($text('Return', 'إرجاع'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($text('Closes this Workflow version and returns the Agreement to draft.', 'يغلق نسخة سير العمل الحالية ويعيد الاتفاقية إلى المسودة.'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </aside>
</div>

<?php workspaceFooter(['assets/js/workflow-generic-review.js']); ?>
