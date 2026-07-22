<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/agreement-documents.php';

workspaceHeader('VP Agreement review', 'workflow');
?>

<div class="mb-4">
    <a href="workflow-inbox.php" class="back-link">← Back to Workflow inbox</a>
</div>

<div
    id="vp-review-alert"
    class="alert alert-danger d-none"
    role="alert"
    aria-live="polite"
    tabindex="-1"
></div>

<div
    id="vp-review-feedback"
    class="alert alert-success d-none"
    role="status"
    aria-live="polite"
    tabindex="-1"
></div>

<div id="vp-review-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading VP assignment…</span>
</div>

<div id="vp-review-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">
                <span data-vp-task-label>Final VP review</span>
                · Agreement <span data-agreement-id></span>
            </p>
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
            <section class="workspace-card h-100" aria-labelledby="vp-overview-title">
                <div class="workspace-card-header">
                    <h2 id="vp-overview-title" class="h5 mb-0">Agreement overview</h2>
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
            <section class="workspace-card h-100" aria-labelledby="vp-assignment-title">
                <div class="workspace-card-header">
                    <h2 id="vp-assignment-title" class="h5 mb-0">VP assignment</h2>
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

    <?php agreementDocumentsPanel('agreement_id', 'SUPPORTING'); ?>

    <section class="workspace-card mt-4" aria-labelledby="specialist-summary-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="specialist-summary-title" class="h5 mb-1">Review summary</h2>
                <p class="small text-secondary mb-0">
                    Confirm the specialist outcomes before making the VP decision.
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
        </dl>
    </section>

    <section
        class="workspace-card mt-4 d-none"
        data-mediation-context
        aria-labelledby="mediation-context-title"
    >
        <div class="workspace-card-header">
            <div>
                <h2 id="mediation-context-title" class="h5 mb-1">Change request awaiting mediation</h2>
                <p class="small text-secondary mb-0">
                    The VP must choose where the Agreement goes next.
                </p>
            </div>
        </div>
        <dl class="detail-grid">
            <div>
                <dt>Requesting review stage</dt>
                <dd data-change-source></dd>
            </div>
            <div class="detail-grid-wide">
                <dt>Recorded reason</dt>
                <dd data-change-reason></dd>
            </div>
        </dl>
    </section>

    <section
        class="workspace-card mt-4 d-none"
        data-final-review-panel
        aria-labelledby="final-vp-decision-title"
    >
        <div class="workspace-card-header">
            <div>
                <h2 id="final-vp-decision-title" class="h5 mb-1">Final VP decision</h2>
                <p class="small text-secondary mb-0">
                    Approve the reviewed Agreement for President consideration, return it for revision, or reject it.
                </p>
            </div>
        </div>

        <div class="review-decision-panel" data-final-approval-panel>
            <label class="form-label fw-semibold" for="final-vp-comments">
                Final review comments <span class="text-secondary fw-normal">(optional)</span>
            </label>
            <textarea
                id="final-vp-comments"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Record the final VP review outcome or conditions for the President."
            ></textarea>
            <div class="review-actions mt-4">
                <button class="btn btn-primary" type="button" data-approve-final-vp>
                    <span data-approve-final-vp-label>Approve and send to President</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        data-final-vp-spinner
                        aria-hidden="true"
                    ></span>
                </button>
            </div>
        </div>

        <div class="review-decision-panel review-danger-zone">
            <h3 class="h6 mb-2">Return or reject</h3>
            <p class="review-help">
                Returning creates a controlled creator-redraft task. Rejection permanently ends this workflow.
            </p>
            <label class="form-label fw-semibold" for="final-vp-negative-reason">
                Decision reason <span class="text-danger">*</span>
            </label>
            <textarea
                id="final-vp-negative-reason"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Explain the required revision or reason for rejection."
            ></textarea>
            <div class="invalid-feedback">
                Enter a reason before returning or rejecting the Agreement.
            </div>
            <div class="review-actions mt-4">
                <button class="btn btn-outline-primary" type="button" data-return-final-vp>
                    Return to creator
                </button>
                <button class="btn btn-outline-danger" type="button" data-reject-final-vp>
                    Reject Agreement
                </button>
            </div>
        </div>
    </section>

    <section
        class="workspace-card mt-4 d-none"
        data-mediation-panel
        aria-labelledby="vp-mediation-title"
    >
        <div class="workspace-card-header">
            <div>
                <h2 id="vp-mediation-title" class="h5 mb-1">VP routing decision</h2>
                <p class="small text-secondary mb-0">
                    Select one controlled destination for the change request.
                </p>
            </div>
        </div>

        <form id="vp-mediation-form" novalidate>
            <div class="review-decision-panel">
                <fieldset>
                    <legend class="h6 mb-3">Route the Agreement to</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="review-choice">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="destination" id="route-creator" value="CREATOR">
                                    <label class="form-check-label fw-semibold" for="route-creator">Creator redraft</label>
                                </div>
                                <p class="review-help mb-0 mt-2">The creator revises the Agreement and submits a new version.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="review-choice">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="destination" id="route-legal" value="LEGAL">
                                    <label class="form-check-label fw-semibold" for="route-legal">Legal review</label>
                                </div>
                                <p class="review-help mb-0 mt-2">Legal receives a new active clarification task.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="review-choice">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="destination" id="route-finance" value="FINANCE">
                                    <label class="form-check-label fw-semibold" for="route-finance">Finance review</label>
                                </div>
                                <p class="review-help mb-0 mt-2">Finance becomes required and receives a review task.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="review-choice review-danger-zone">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="destination" id="route-reject" value="REJECT">
                                    <label class="form-check-label fw-semibold" for="route-reject">Reject Agreement</label>
                                </div>
                                <p class="review-help mb-0 mt-2">Permanently ends the Agreement workflow.</p>
                            </div>
                        </div>
                    </div>
                    <div class="invalid-feedback d-block d-none" data-destination-error>
                        Select a routing destination.
                    </div>
                </fieldset>

                <div class="mt-4">
                    <label class="form-label fw-semibold" for="vp-routing-reason">
                        VP routing reason <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="vp-routing-reason"
                        class="form-control"
                        rows="4"
                        maxlength="2000"
                        placeholder="Explain the selected destination and the action required next."
                    ></textarea>
                    <div class="invalid-feedback">Enter the VP routing reason.</div>
                </div>

                <div class="review-actions mt-4">
                    <button class="btn btn-primary" type="submit" data-submit-mediation>
                        <span data-submit-mediation-label>Save routing decision</span>
                        <span
                            class="spinner-border spinner-border-sm ms-2 d-none"
                            data-mediation-spinner
                            aria-hidden="true"
                        ></span>
                    </button>
                </div>
            </div>
        </form>
    </section>
</div>

<?php workspaceFooter([
    'assets/js/vp-review.js',
    'assets/js/agreement-documents.js',
]); ?>
