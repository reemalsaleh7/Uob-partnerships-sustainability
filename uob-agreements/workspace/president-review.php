<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('President Agreement review', 'workflow');
?>

<div class="mb-4">
    <a href="workflow-inbox.php" class="back-link">← Back to Workflow inbox</a>
</div>

<div
    id="president-review-alert"
    class="alert alert-danger d-none"
    role="alert"
    aria-live="polite"
    tabindex="-1"
></div>

<div
    id="president-review-feedback"
    class="alert alert-success d-none"
    role="status"
    aria-live="polite"
    tabindex="-1"
></div>

<div id="president-review-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading President assignment…</span>
</div>

<div id="president-review-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">
                President approval · Agreement <span data-agreement-id></span>
            </p>
            <h1 class="display-6 mb-3" data-agreement-title></h1>
            <span data-agreement-status></span>
        </div>

        <div class="align-self-lg-end">
            <a href="#" class="btn btn-outline-primary" data-open-agreement>
                Open full Agreement record
            </a>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="workspace-card h-100" aria-labelledby="president-overview-title">
                <div class="workspace-card-header">
                    <h2 id="president-overview-title" class="h5 mb-0">Agreement overview</h2>
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
            <section class="workspace-card h-100" aria-labelledby="president-assignment-title">
                <div class="workspace-card-header">
                    <h2 id="president-assignment-title" class="h5 mb-0">President assignment</h2>
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
                        <dt>Assignment started</dt>
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

    <section class="workspace-card mt-4" aria-labelledby="president-review-summary-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="president-review-summary-title" class="h5 mb-1">Previous review outcomes</h2>
                <p class="small text-secondary mb-0">
                    Review the specialist decisions and the Final VP recommendation before deciding.
                </p>
            </div>
        </div>
        <dl class="detail-grid">
            <div>
                <dt>Legal review</dt>
                <dd data-legal-status></dd>
                <dd class="small text-secondary mt-2" data-legal-comments></dd>
            </div>
            <div>
                <dt>Finance review</dt>
                <dd data-finance-status></dd>
                <dd class="small text-secondary mt-2" data-finance-comments></dd>
            </div>
            <div class="detail-grid-wide">
                <dt>Final VP review</dt>
                <dd data-final-vp-status></dd>
                <dd class="small text-secondary mt-2" data-final-vp-comments></dd>
            </div>
        </dl>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="president-decision-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="president-decision-title" class="h5 mb-1">President decision</h2>
                <p class="small text-secondary mb-0">
                    Approve the Agreement, request controlled changes through the VP, or reject it.
                </p>
            </div>
        </div>

        <div class="review-decision-panel" data-president-approval-panel>
            <h3 class="h6 mb-2">Approve Agreement</h3>
            <p class="review-help">
                Approval completes the workflow and changes the Agreement status to APPROVED.
            </p>
            <label class="form-label fw-semibold" for="president-comments">
                Approval comments <span class="text-secondary fw-normal">(optional)</span>
            </label>
            <textarea
                id="president-comments"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Record the President's final comments or conditions."
            ></textarea>
            <div class="review-actions mt-4">
                <button class="btn btn-primary" type="button" data-approve-president>
                    <span data-approve-president-label>Approve and complete workflow</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        data-approve-president-spinner
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>

        <div class="review-decision-panel" data-president-change-panel>
            <h3 class="h6 mb-2">Request changes</h3>
            <p class="review-help">
                The request returns to the VP for mediation. The VP then selects the controlled next destination.
            </p>
            <label class="form-label fw-semibold" for="president-change-reason">
                Required changes <span class="text-danger">*</span>
            </label>
            <textarea
                id="president-change-reason"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Explain what must be corrected or reconsidered."
            ></textarea>
            <div class="invalid-feedback">Enter a reason for the change request.</div>
            <div class="review-actions mt-4">
                <button class="btn btn-outline-primary" type="button" data-request-president-changes>
                    <span data-request-president-changes-label>Request changes through VP</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        data-president-changes-spinner
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>

        <div class="review-decision-panel review-danger-zone" data-president-rejection-panel>
            <h3 class="h6 mb-2">Reject Agreement</h3>
            <p class="review-help">
                Rejection permanently ends this workflow. Use a change request when revision is possible.
            </p>
            <label class="form-label fw-semibold" for="president-rejection-reason">
                Rejection reason <span class="text-danger">*</span>
            </label>
            <textarea
                id="president-rejection-reason"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Explain why the Agreement cannot proceed."
            ></textarea>
            <div class="invalid-feedback">Enter a reason before rejecting the Agreement.</div>
            <div class="review-actions mt-4">
                <button class="btn btn-outline-danger" type="button" data-reject-president>
                    <span data-reject-president-label>Reject Agreement</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        data-reject-president-spinner
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>
    </section>
</div>

<?php workspaceFooter(['assets/js/president-review.js']); ?>
