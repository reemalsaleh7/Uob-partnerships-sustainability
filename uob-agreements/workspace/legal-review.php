<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/agreement-documents.php';

workspaceHeader('Legal Agreement review', 'workflow');
?>

<div class="mb-4">
    <a href="workflow-inbox.php" class="back-link">← Back to Workflow inbox</a>
</div>

<div
    id="legal-review-alert"
    class="alert alert-danger d-none"
    role="alert"
    aria-live="polite"
    tabindex="-1"
></div>

<div
    id="legal-review-feedback"
    class="alert alert-success d-none"
    role="status"
    aria-live="polite"
    tabindex="-1"
></div>

<div id="legal-review-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading Legal review…</span>
</div>

<div id="legal-review-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Legal review · Agreement <span data-agreement-id></span></p>
            <h1 class="display-6 mb-3" data-agreement-title></h1>
            <span data-agreement-status></span>
        </div>

        <div class="align-self-lg-end">
            <a href="#" class="btn btn-outline-primary" data-open-agreement>
                Review fields and add comments
            </a>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="workspace-card h-100" aria-labelledby="legal-overview-title">
                <div class="workspace-card-header">
                    <h2 id="legal-overview-title" class="h5 mb-0">Agreement overview</h2>
                </div>
                <dl class="detail-grid">
                    <div>
                        <dt>Agreement type</dt>
                        <dd data-agreement-type></dd>
                    </div>
                    <div>
                        <dt>Partner organization</dt>
                        <dd data-partner-name></dd>
                    </div>
                    <div class="detail-grid-wide">
                        <dt>Description</dt>
                        <dd data-agreement-description></dd>
                    </div>
                </dl>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="workspace-card h-100" aria-labelledby="legal-assignment-title">
                <div class="workspace-card-header">
                    <h2 id="legal-assignment-title" class="h5 mb-0">Review assignment</h2>
                </div>
                <dl class="record-list">
                    <div>
                        <dt>Workflow instance</dt>
                        <dd data-instance-id></dd>
                    </div>
                    <div>
                        <dt>Assigned office</dt>
                        <dd data-assigned-unit></dd>
                    </div>
                    <div>
                        <dt>Review started</dt>
                        <dd data-review-started></dd>
                    </div>
                    <div>
                        <dt>Latest version</dt>
                        <dd data-latest-version></dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>

    <?php agreementDocumentsPanel('agreement_id', 'LEGAL_REVIEW'); ?>

    <section class="workspace-card mt-4" aria-labelledby="legal-decision-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="legal-decision-title" class="h5 mb-1">Legal decision</h2>
                <p class="small text-secondary mb-0">
                    Approve the legal review or return a documented change request to the VP.
                </p>
            </div>
        </div>

        <div class="review-decision-panel">
            <label class="form-label fw-semibold" for="legal-comments">
                Legal comments <span class="text-secondary fw-normal">(optional)</span>
            </label>
            <textarea
                id="legal-comments"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Record the legal review outcome or conditions."
            ></textarea>

            <div class="review-actions mt-4">
                <button class="btn btn-primary" type="button" data-approve-legal>
                    <span data-approve-legal-label>Approve Legal review</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        data-legal-spinner
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>

        <div class="review-decision-panel review-danger-zone">
            <h3 class="h6 mb-2">Request changes</h3>
            <p class="review-help">
                The request goes to the VP for routing. It does not silently edit or reject the Agreement.
            </p>

            <label class="form-label fw-semibold" for="legal-change-reason">
                Required legal changes <span class="text-danger">*</span>
            </label>
            <textarea
                id="legal-change-reason"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="State the clauses or obligations that must be revised."
            ></textarea>
            <div class="invalid-feedback">
                Enter the required legal changes before sending the request.
            </div>

            <div class="review-actions mt-4">
                <button class="btn btn-outline-primary" type="button" data-request-legal-changes>
                    Request changes through VP
                </button>
            </div>
        </div>
    </section>
</div>

<?php workspaceFooter([
    'assets/js/legal-review.js',
    'assets/js/agreement-documents.js',
]); ?>
