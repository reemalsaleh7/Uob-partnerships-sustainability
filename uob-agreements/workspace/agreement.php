<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/agreement-documents.php';

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
    tabindex="-1"
></div>

<div
    id="detail-feedback"
    class="alert alert-success d-none"
    role="status"
    aria-live="polite"
    tabindex="-1"
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
            <a
                href="#"
                class="btn btn-outline-primary d-none"
                data-lifecycle-request
            >
                Start lifecycle request
            </a>
            <a
                href="#"
                class="btn btn-outline-primary d-none"
                data-edit-agreement
            >
                Edit Agreement
            </a>
            <button
                class="btn btn-primary d-none"
                type="button"
                data-submit-agreement
            >
                <span data-submit-label>Submit for review</span>
                <span
                    class="spinner-border spinner-border-sm ms-2 d-none"
                    data-submit-spinner
                    aria-hidden="true"
                ></span>
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

    <section class="workspace-card mt-4" aria-labelledby="complete-record-title">
        <div class="workspace-card-header"><div>
            <h2 id="complete-record-title" class="h5 mb-1">Complete Agreement record</h2>
            <p class="small text-secondary mb-0">Official request, MOU, ranking, commitment, and legacy reporting fields.</p>
        </div></div>
        <dl class="detail-grid comprehensive-detail-grid">
            <div><dt>Arabic title</dt><dd data-field="title_ar"></dd></div>
            <div><dt>Geographic scope</dt><dd data-field="geographic_scope"></dd></div>
            <div><dt>Start date</dt><dd data-field="start_date"></dd></div>
            <div><dt>End date</dt><dd data-field="end_date"></dd></div>
            <div><dt>Signing date</dt><dd data-field="signing_date"></dd></div>
            <div><dt>Effective date</dt><dd data-field="effective_date"></dd></div>
            <div><dt>Automatic renewal</dt><dd data-field="auto_renew"></dd></div>
            <div><dt>Renewal term</dt><dd data-field="renewal_term_months"></dd></div>
            <div><dt>Non-renewal notice</dt><dd data-field="non_renewal_notice_months"></dd></div>
            <div><dt>Termination notice</dt><dd data-field="termination_notice_months"></dd></div>
            <div><dt>Legal effect</dt><dd data-field="legal_binding_status"></dd></div>
            <div><dt>Responsible unit</dt><dd data-field="responsible_unit_name"></dd></div>
            <div class="detail-grid-wide"><dt>Need and justification</dt><dd data-field="need_justification"></dd></div>
            <div class="detail-grid-wide"><dt>Objectives</dt><dd data-field="objectives"></dd></div>
            <div class="detail-grid-wide"><dt>Expected University value and impact</dt><dd data-field="expected_value"></dd></div>
            <div class="detail-grid-wide"><dt>Focus areas</dt><dd data-field="focus_areas"></dd></div>
            <div class="detail-grid-wide"><dt>Fields of cooperation</dt><dd data-field="collaboration_areas"></dd></div>
            <div class="detail-grid-wide"><dt>Implementation methods</dt><dd data-field="implementation_methods"></dd></div>
            <div><dt>Financial commitments</dt><dd data-field="financial_summary"></dd></div>
            <div><dt>Human-resources commitments</dt><dd data-field="human_resources_summary"></dd></div>
            <div><dt>Training programs</dt><dd data-field="training_programs_summary"></dd></div>
            <div><dt>Rankings</dt><dd data-field="rankings_summary"></dd></div>
            <div><dt>SDGs</dt><dd data-field="sdgs_summary"></dd></div>
            <div><dt>Annual report</dt><dd data-field="annual_report_required"></dd></div>
            <div class="detail-grid-wide"><dt>Monitoring plan</dt><dd data-field="monitoring_plan"></dd></div>
            <div class="detail-grid-wide"><dt>Confidentiality terms</dt><dd data-field="confidentiality_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Intellectual-property terms</dt><dd data-field="intellectual_property_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Legal and regulatory compliance</dt><dd data-field="compliance_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Relationship disclaimer</dt><dd data-field="relationship_disclaimer"></dd></div>
            <div class="detail-grid-wide"><dt>Amendment terms</dt><dd data-field="amendment_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Dispute-resolution terms</dt><dd data-field="dispute_resolution_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Other terms</dt><dd data-field="other_terms"></dd></div>
            <div class="detail-grid-wide"><dt>Public signing link</dt><dd data-field="signing_link"></dd></div>
        </dl>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-6"><section class="workspace-card h-100" aria-labelledby="contact-summary-title">
            <div class="workspace-card-header"><h2 id="contact-summary-title" class="h5 mb-0">Coordinators and signatories</h2></div>
            <div class="form-section" data-contact-summary></div>
        </section></div>
        <div class="col-lg-6"><section class="workspace-card h-100" aria-labelledby="program-summary-title">
            <div class="workspace-card-header"><h2 id="program-summary-title" class="h5 mb-0">Executive program and outcomes</h2></div>
            <div class="form-section" data-program-summary></div>
        </section></div>
    </div>

    <section class="workspace-card mt-4 d-none" data-relationship-section aria-labelledby="relationship-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="relationship-title" class="h5 mb-1">Agreement lineage</h2>
                <p class="small text-secondary mb-0">Approved renewals and amendments linked without replacing the original record.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table workspace-table align-middle mb-0">
                <thead><tr><th>Relationship</th><th>Agreement</th><th>Status</th><th></th></tr></thead>
                <tbody data-relationship-rows></tbody>
            </table>
        </div>
    </section>

    <?php agreementDocumentsPanel('id', 'AGREEMENT_DRAFT'); ?>

    <section
        class="workspace-card mt-4"
        aria-labelledby="agreement-operations-title"
        data-agreement-operations
    >
        <div class="workspace-card-header">
            <div>
                <h2 id="agreement-operations-title" class="h5 mb-1">
                    Signing and operational status
                </h2>
                <p class="small text-secondary mb-0">
                    Finalized signing evidence and date-controlled activation or expiry.
                </p>
            </div>
            <span class="status-badge status-default" data-operational-state>Loading</span>
        </div>

        <div class="alert alert-danger m-3 mb-0 d-none" role="alert" tabindex="-1" data-operation-alert></div>
        <div class="alert alert-success m-3 mb-0 d-none" role="status" tabindex="-1" data-operation-feedback></div>

        <div class="loading-state compact" data-operation-loading>
            <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
            <span>Loading signing record…</span>
        </div>

        <div class="form-section d-none" data-signing-summary>
            <dl class="detail-grid mb-0">
                <div><dt>Signing date</dt><dd data-signing-field="signing_date"></dd></div>
                <div><dt>Effective date</dt><dd data-signing-field="effective_date"></dd></div>
                <div><dt>Expiry date</dt><dd data-signing-field="expiry_date"></dd></div>
                <div><dt>Venue</dt><dd data-signing-field="venue"></dd></div>
                <div><dt>Finalized by</dt><dd data-signing-field="finalized_by"></dd></div>
                <div><dt>Finalized at</dt><dd data-signing-field="finalized_at"></dd></div>
                <div class="detail-grid-wide"><dt>Signed document</dt><dd data-signing-field="document"></dd></div>
                <div class="detail-grid-wide"><dt>Public announcement</dt><dd data-signing-field="public_announcement_url"></dd></div>
                <div class="detail-grid-wide"><dt>Ceremony notes</dt><dd data-signing-field="ceremony_notes"></dd></div>
            </dl>
            <h3 class="h6 mt-4">Final signatories</h3>
            <div class="table-responsive">
                <table class="table workspace-table align-middle mb-0">
                    <thead><tr><th>Party</th><th>Name</th><th>Job title</th><th>Organization</th></tr></thead>
                    <tbody data-final-signatory-rows></tbody>
                </table>
            </div>
            <h3 class="h6 mt-4">Status history</h3>
            <div data-status-event-list></div>
        </div>

        <div class="empty-state compact d-none" data-signing-empty>
            <p class="text-secondary mb-0">
                No finalized digital signing record is available. The Agreement's existing status is preserved.
            </p>
        </div>

        <form class="form-section border-top d-none" data-signing-form novalidate>
            <div class="alert alert-info">
                Upload the executed file above as <strong>Final signed Agreement</strong>, then select it here.
                Finalization is permanent.
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="final-signing-date">Signing date</label>
                    <input id="final-signing-date" class="form-control" type="date" required data-final-signing-date>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="final-effective-date">Effective date</label>
                    <input id="final-effective-date" class="form-control" type="date" required data-final-effective-date>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="final-expiry-date">Expiry date</label>
                    <input id="final-expiry-date" class="form-control" type="date" required data-final-expiry-date>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="final-signed-document">Signed document</label>
                    <select id="final-signed-document" class="form-select" required data-final-signed-document></select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="final-signing-venue">Signing venue</label>
                    <input id="final-signing-venue" class="form-control" maxlength="255" data-final-signing-venue>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="final-announcement-url">Public signing/news URL</label>
                    <input id="final-announcement-url" class="form-control" type="url" placeholder="https://..." data-final-announcement-url>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="final-ceremony-notes">Signing notes</label>
                    <textarea id="final-ceremony-notes" class="form-control" rows="3" data-final-ceremony-notes></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center gap-3 mt-4">
                <div>
                    <h3 class="h6 mb-1">Final signatories</h3>
                    <p class="small text-secondary mb-0">At least one UOB and one partner signatory are required.</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-final-signatory>Add signatory</button>
            </div>
            <div class="mt-3" data-signatory-editor></div>
            <div class="d-flex justify-content-end mt-4">
                <button class="btn btn-primary" type="submit" data-finalize-signing>
                    <span data-finalize-signing-label>Finalize signing</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true" data-finalize-signing-spinner></span>
                </button>
            </div>
        </form>
    </section>

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

<?php workspaceFooter([
    'assets/js/agreement-detail.js',
    'assets/js/agreement-documents.js',
    'assets/js/agreement-operations.js',
]); ?>
