<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader('Lifecycle request details', 'lifecycle');
?>

<div class="mb-4"><a href="lifecycle-requests.php" class="back-link">← Back to Lifecycle requests</a></div>
<div id="lifecycle-detail-alert" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
<div id="lifecycle-detail-feedback" class="alert alert-success d-none" role="status"></div>
<div id="lifecycle-detail-loading" class="loading-state"><div class="spinner-border text-primary"></div><span>Loading lifecycle request…</span></div>

<div id="lifecycle-detail-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div><p class="eyebrow mb-2"><span data-request-type></span> request <span data-request-id></span></p><h1 class="display-6 mb-3" data-agreement-title></h1><span data-request-status></span></div>
        <div class="detail-actions align-self-lg-end">
            <a class="btn btn-outline-primary d-none" data-edit-request>Edit request</a>
            <button class="btn btn-primary d-none" type="button" data-submit-request><span data-submit-request-label>Submit request</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-submit-request-spinner></span></button>
        </div>
    </section>

    <section class="workspace-card mt-4">
        <div class="workspace-card-header"><h2 class="h5 mb-0">Request record</h2></div>
        <dl class="detail-grid" data-request-fields></dl>
    </section>

    <section class="workspace-card mt-4">
        <div class="workspace-card-header"><div><h2 class="h5 mb-1">Request version history</h2><p class="small text-secondary mb-0">Immutable snapshots of each saved draft and revision.</p></div></div>
        <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Version</th><th>Summary</th><th>Created by</th><th>Created</th></tr></thead><tbody data-version-rows></tbody></table></div>
    </section>
</div>

<?php workspaceFooter(['assets/js/lifecycle-request.js']); ?>
