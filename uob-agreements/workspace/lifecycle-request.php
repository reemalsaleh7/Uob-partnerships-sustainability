<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/lifecycle-request-documents.php';
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
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Download</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item" type="button" data-export-lifecycle="pdf">PDF / print</button></li>
                    <li><button class="dropdown-item" type="button" data-export-lifecycle="csv">CSV</button></li>
                    <li><button class="dropdown-item" type="button" data-export-lifecycle="json">JSON</button></li>
                </ul>
            </div>
            <a class="btn btn-outline-primary d-none" data-edit-request>Edit request</a>
            <button class="btn btn-primary d-none" type="button" data-submit-request><span data-submit-request-label>Submit request</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-submit-request-spinner></span></button>
        </div>
    </section>

    <section class="record-section-grid mt-4" aria-label="Lifecycle request record">
        <details class="workspace-card record-accordion" open>
            <summary><span><strong>Request and decision</strong><small>Reason, requester, submission, and outcome</small></span></summary>
            <dl class="detail-grid" data-request-fields="request"></dl>
        </details>
        <details class="workspace-card record-accordion">
            <summary><span><strong>Proposed change</strong><small>Type-specific renewal, amendment, or termination details</small></span></summary>
            <dl class="detail-grid" data-request-fields="change"></dl>
        </details>
        <details class="workspace-card record-accordion">
            <summary><span><strong>Financial implications</strong><small>Amount, currency, and supporting description</small></span></summary>
            <dl class="detail-grid" data-request-fields="financial"></dl>
        </details>
    </section>

    <section class="workspace-card mt-4 d-none" data-successor-section>
        <div class="workspace-card-header"><div><h2 class="h5 mb-1">Finalized successor Agreement</h2><p class="small text-secondary mb-0">Created from this approved request while preserving the source Agreement.</p></div></div>
        <a class="btn btn-outline-primary" data-successor-link>Open successor Agreement</a>
    </section>

    <details class="workspace-card record-accordion mt-4">
        <summary><span><strong>Request version history</strong><small>Immutable snapshots of each saved draft and revision</small></span></summary>
        <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Version</th><th>Summary</th><th>Created by</th><th>Created</th></tr></thead><tbody data-version-rows></tbody></table></div>
    </details>

    <?php lifecycleRequestDocumentsPanel('id', 'SUPPORTING'); ?>
</div>

<?php workspaceFooter(['assets/js/lifecycle-request.js', 'assets/js/lifecycle-documents.js']); ?>
