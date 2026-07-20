<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreement details', 'agreements');
?>

<div class="mb-4">
    <a href="agreements.php" class="back-link">← Back to Agreements</a>
</div>

<div
    id="detail-alert"
    class="alert alert-danger d-none"
    role="alert"
    aria-live="polite"
></div>

<div id="detail-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading Agreement…</span>
</div>

<div id="detail-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Agreement <span data-agreement-id></span></p>
            <h1 class="display-6 mb-3" data-agreement-title></h1>
            <span data-agreement-status></span>
        </div>

        <div class="detail-actions align-self-lg-end">
            <button
                class="btn btn-outline-primary d-none"
                type="button"
                data-edit-agreement
                disabled
                title="Agreement editing is included in the next frontend slice"
            >
                Edit Agreement
            </button>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="workspace-card h-100" aria-labelledby="overview-title">
                <div class="workspace-card-header">
                    <h2 id="overview-title" class="h5 mb-0">Overview</h2>
                </div>
                <dl class="detail-grid">
                    <div>
                        <dt>Agreement type</dt>
                        <dd data-agreement-type></dd>
                    </div>
                    <div>
                        <dt>Partner ID</dt>
                        <dd data-partner-id></dd>
                    </div>
                    <div class="detail-grid-wide">
                        <dt>Description</dt>
                        <dd data-agreement-description></dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="workspace-card h-100" aria-labelledby="record-title">
                <div class="workspace-card-header">
                    <h2 id="record-title" class="h5 mb-0">Record information</h2>
                </div>
                <dl class="record-list">
                    <div>
                        <dt>Created by</dt>
                        <dd data-created-by></dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd data-created-at></dd>
                    </div>
                    <div>
                        <dt>Last updated</dt>
                        <dd data-updated-at></dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>

    <section class="workspace-card mt-4" aria-labelledby="versions-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="versions-title" class="h5 mb-1">Version history</h2>
                <p class="small text-secondary mb-0">
                    Immutable snapshots recorded for this Agreement.
                </p>
            </div>
        </div>

        <div id="version-loading" class="loading-state compact" aria-live="polite">
            <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
            <span>Loading versions…</span>
        </div>

        <div id="version-empty" class="empty-state compact d-none">
            <p class="text-secondary mb-0">No versions are available.</p>
        </div>

        <div id="version-table-wrap" class="table-responsive d-none">
            <table class="table workspace-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Version</th>
                        <th scope="col">Change summary</th>
                        <th scope="col">Created by</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody id="version-table-body"></tbody>
            </table>
        </div>
    </section>
</div>

<?php workspaceFooter(['assets/js/agreement-detail.js']); ?>

