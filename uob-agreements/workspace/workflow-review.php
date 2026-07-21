<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Initial VP review', 'workflow');
?>

<div class="mb-4">
    <a href="workflow-inbox.php" class="back-link">← Back to Workflow inbox</a>
</div>

<div
    id="review-alert"
    class="alert alert-danger d-none"
    role="alert"
    aria-live="polite"
    tabindex="-1"
></div>

<div
    id="review-feedback"
    class="alert alert-success d-none"
    role="status"
    aria-live="polite"
></div>

<div id="review-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading assigned review…</span>
</div>

<div id="review-content" class="d-none">
    <section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Initial VP review · Agreement <span data-agreement-id></span></p>
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
            <section class="workspace-card h-100" aria-labelledby="review-overview-title">
                <div class="workspace-card-header">
                    <h2 id="review-overview-title" class="h5 mb-0">Agreement overview</h2>
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
            <section class="workspace-card h-100" aria-labelledby="review-record-title">
                <div class="workspace-card-header">
                    <h2 id="review-record-title" class="h5 mb-0">Review assignment</h2>
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

    <section class="workspace-card mt-4" aria-labelledby="review-documents-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="review-documents-title" class="h5 mb-1">Supporting documents</h2>
                <p class="small text-secondary mb-0">
                    Document records attached to the Agreement for this review.
                </p>
            </div>
        </div>

        <div id="review-documents-loading" class="loading-state compact" aria-live="polite">
            <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
            <span>Loading documents…</span>
        </div>

        <div id="review-documents-empty" class="empty-state compact d-none">
            <p class="text-secondary mb-0">No supporting documents are attached.</p>
        </div>

        <div id="review-documents-wrap" class="table-responsive d-none">
            <table class="table workspace-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">File name</th>
                        <th scope="col">Document type</th>
                        <th scope="col">Uploaded by</th>
                        <th scope="col">Uploaded</th>
                    </tr>
                </thead>
                <tbody id="review-documents-body"></tbody>
            </table>
        </div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="vp-decision-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="vp-decision-title" class="h5 mb-1">VP routing decision</h2>
                <p class="small text-secondary mb-0">
                    Legal review is mandatory. Decide whether Finance must also review this Agreement.
                </p>
            </div>
        </div>

        <form id="vp-approval-form" novalidate>
            <div class="review-decision-panel">
                <fieldset>
                    <legend class="h6 mb-3">Is Finance review required?</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="review-choice">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="include_finance"
                                        id="finance-no"
                                        value="false"
                                        checked
                                    >
                                    <label class="form-check-label fw-semibold" for="finance-no">
                                        Legal review only
                                    </label>
                                </div>
                                <p class="review-help mb-0 mt-2">
                                    Finance is skipped and Legal receives the next task.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="review-choice">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        name="include_finance"
                                        id="finance-yes"
                                        value="true"
                                    >
                                    <label class="form-check-label fw-semibold" for="finance-yes">
                                        Legal and Finance review
                                    </label>
                                </div>
                                <p class="review-help mb-0 mt-2">
                                    Legal and Finance receive parallel review tasks.
                                </p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="mt-4">
                    <label class="form-label fw-semibold" for="approval-comments">
                        Routing comments <span class="text-secondary fw-normal">(optional)</span>
                    </label>
                    <textarea
                        id="approval-comments"
                        class="form-control"
                        rows="4"
                        maxlength="2000"
                        placeholder="Add information for the Legal or Finance reviewers."
                    ></textarea>
                </div>

                <div class="review-actions mt-4">
                    <button class="btn btn-primary" type="submit" data-approve-review>
                        <span data-approve-label>Approve and route</span>
                        <span
                            class="spinner-border spinner-border-sm ms-2 d-none"
                            data-review-spinner
                            aria-hidden="true"
                        ></span>
                    </button>
                </div>
            </div>
        </form>

        <div class="review-decision-panel review-danger-zone">
            <h3 class="h6 mb-2">Return or reject</h3>
            <p class="review-help">
                Return sends the Agreement to its creator for redrafting. Reject permanently ends this workflow.
            </p>

            <label class="form-label fw-semibold" for="negative-reason">
                Reason <span class="text-danger">*</span>
            </label>
            <textarea
                id="negative-reason"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Explain what must be changed or why the Agreement cannot proceed."
            ></textarea>
            <div class="invalid-feedback" data-reason-error>
                Enter a reason before returning or rejecting the Agreement.
            </div>

            <div class="review-actions mt-4">
                <button class="btn btn-outline-primary" type="button" data-return-review>
                    Return to creator
                </button>
                <button class="btn btn-outline-danger" type="button" data-reject-review>
                    Reject Agreement
                </button>
            </div>
        </div>
    </section>
</div>

<?php workspaceFooter(['assets/js/workflow-review.js']); ?>
