<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader('Lifecycle request review', 'workflow');
?>

<div class="mb-4"><a href="workflow-inbox.php" class="back-link">← Back to Workflow inbox</a></div>
<div id="lifecycle-review-alert" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
<div id="lifecycle-review-loading" class="loading-state"><div class="spinner-border text-primary"></div><span>Loading assigned lifecycle review…</span></div>

<div id="lifecycle-review-content" class="d-none">
    <section class="page-heading">
        <p class="eyebrow mb-2" data-review-stage></p>
        <h1 class="display-6 mb-2"><span data-request-type></span>: <span data-agreement-title></span></h1>
        <p class="text-secondary mb-0">Request <span data-request-id></span> · Workflow <span data-instance-id></span></p>
    </section>

    <section class="workspace-card mt-4">
        <div class="workspace-card-header"><div><h2 class="h5 mb-1">Lifecycle request</h2><p class="small text-secondary mb-0">Review the request without changing the approved Agreement record.</p></div><a class="btn btn-sm btn-outline-primary" data-open-request>Open full request</a></div>
        <dl class="detail-grid" data-review-fields></dl>
    </section>

    <section class="workspace-card mt-4" data-decision-panel>
        <div class="workspace-card-header"><h2 class="h5 mb-0">Decision</h2></div>
        <div class="review-decision-panel">
            <div class="mb-4 d-none" data-finance-choice><label class="form-label fw-semibold" for="include_finance">Finance review</label><select class="form-select" id="include_finance"><option value="false">Legal review only</option><option value="true">Legal and Finance review</option></select></div>
            <label class="form-label fw-semibold" for="review_comments">Comments or reason</label>
            <textarea class="form-control" id="review_comments" rows="5" maxlength="2000" placeholder="Optional for approval; required for return or rejection."></textarea>
            <p class="small text-secondary mt-2 d-none" data-mediation-note>This task is VP mediation. The request may be returned to its creator or rejected; normal approval is unavailable until a revised request is resubmitted.</p>
            <div class="review-actions mt-4">
                <button class="btn btn-outline-danger" type="button" data-review-action="REJECT">Reject</button>
                <button class="btn btn-outline-primary" type="button" data-review-action="RETURN">Request changes</button>
                <button class="btn btn-primary" type="button" data-review-action="APPROVE">Approve</button>
            </div>
        </div>
    </section>
</div>

<?php workspaceFooter(['assets/js/lifecycle-review.js']); ?>
