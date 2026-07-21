<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Performance report', 'performance');
?>

<div class="mb-4"><a href="performance-reports.php" class="back-link">← Back to performance reports</a></div>
<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-performance-alert></div>
<div class="alert alert-success d-none" role="status" tabindex="-1" data-performance-feedback></div>
<div class="loading-state" data-performance-loading><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading performance report…</span></div>

<div class="d-none" data-performance-content>
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Annual Agreement report</p>
            <h1 class="display-6 mb-3" data-report-agreement-title></h1>
            <div class="d-flex flex-wrap align-items-center gap-3"><span data-report-status></span><span class="text-secondary" data-report-period></span><span class="text-danger fw-semibold d-none" data-report-overdue>Overdue</span></div>
        </div>
        <a class="btn btn-outline-primary align-self-lg-end" href="#" data-report-agreement-link>Open Agreement</a>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="report-summary-title">
        <div class="workspace-card-header"><div><h2 id="report-summary-title" class="h5 mb-1">1. Annual summary</h2><p class="small text-secondary mb-0">Report achievements, challenges, corrective actions, and next-period priorities.</p></div></div>
        <div class="form-section"><div class="row g-3">
            <div class="col-12"><label class="form-label fw-semibold" for="executive-summary">Executive summary</label><textarea id="executive-summary" class="form-control" rows="4" data-report-field="executive_summary"></textarea></div>
            <div class="col-md-6"><label class="form-label fw-semibold" for="achievements">Achievements</label><textarea id="achievements" class="form-control" rows="5" data-report-field="achievements"></textarea></div>
            <div class="col-md-6"><label class="form-label fw-semibold" for="challenges">Challenges and obstacles</label><textarea id="challenges" class="form-control" rows="5" data-report-field="challenges"></textarea></div>
            <div class="col-md-6"><label class="form-label fw-semibold" for="corrective-actions">Corrective actions</label><textarea id="corrective-actions" class="form-control" rows="4" data-report-field="corrective_actions"></textarea></div>
            <div class="col-md-6"><label class="form-label fw-semibold" for="next-period-plan">Next-period plan</label><textarea id="next-period-plan" class="form-control" rows="4" data-report-field="next_period_plan"></textarea></div>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="report-metrics-title">
        <div class="workspace-card-header"><div><h2 id="report-metrics-title" class="h5 mb-1">2. Outcome metrics</h2><p class="small text-secondary mb-0">Original targets are preserved; enter results achieved in this reporting period.</p></div></div>
        <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Metric</th><th>Planned</th><th>Actual</th><th>Unit</th><th>Notes</th></tr></thead><tbody data-performance-metrics></tbody></table></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="program-progress-title">
        <div class="workspace-card-header"><div><h2 id="program-progress-title" class="h5 mb-1">3. Executive-program progress</h2><p class="small text-secondary mb-0">Track delivery status, completion, outputs, problems, and next steps.</p></div></div>
        <div class="form-section" data-performance-programs></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="report-evidence-title">
        <div class="workspace-card-header"><div><h2 id="report-evidence-title" class="h5 mb-1">4. Final report evidence</h2><p class="small text-secondary mb-0">Upload an Annual performance report from the Agreement page, then select it here.</p></div></div>
        <div class="form-section"><label class="form-label fw-semibold" for="report-document">Secure annual-report document</label><select id="report-document" class="form-select" data-report-document><option value="">Select the final annual report</option></select><div class="form-text"><a href="#" data-upload-report-document>Open the Agreement document panel</a> to upload PDF, DOC, or DOCX evidence.</div></div>
    </section>

    <section class="workspace-card mt-4 d-none" aria-labelledby="report-review-title" data-review-history>
        <div class="workspace-card-header"><h2 id="report-review-title" class="h5 mb-0">Review outcome</h2></div>
        <dl class="detail-grid"><div><dt>Reviewed by</dt><dd data-review-field="reviewer"></dd></div><div><dt>Reviewed at</dt><dd data-review-field="reviewed_at"></dd></div><div class="detail-grid-wide"><dt>Comments</dt><dd data-review-field="comments"></dd></div></dl>
    </section>

    <div class="form-actions mt-4 d-none" data-report-actions>
        <button class="btn btn-outline-primary" type="button" data-save-report>Save draft</button>
        <button class="btn btn-primary" type="button" data-submit-report>Submit for review</button>
    </div>

    <section class="workspace-card mt-4 d-none" aria-labelledby="review-decision-title" data-review-panel>
        <div class="workspace-card-header"><div><h2 id="review-decision-title" class="h5 mb-1">Reviewer decision</h2><p class="small text-secondary mb-0">Accept verified results or return the report with clear required changes.</p></div></div>
        <div class="review-decision-panel"><label class="form-label fw-semibold" for="review-comments">Reviewer comments</label><textarea id="review-comments" class="form-control" rows="4" data-review-comments></textarea><div class="review-actions mt-3"><button class="btn btn-outline-danger" type="button" data-return-report>Return for changes</button><button class="btn btn-primary" type="button" data-accept-report>Accept report</button></div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="report-history-title">
        <div class="workspace-card-header"><h2 id="report-history-title" class="h5 mb-0">Status history</h2></div>
        <div class="form-section" data-report-events></div>
    </section>
</div>

<?php workspaceFooter(['assets/js/performance-report.js']); ?>
