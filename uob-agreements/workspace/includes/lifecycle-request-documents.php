<?php

declare(strict_types=1);

function lifecycleRequestDocumentsPanel(
    string $idParameter = 'id',
    string $defaultType = 'SUPPORTING'
): void {
    $parameter = htmlspecialchars($idParameter, ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($defaultType, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<section class="workspace-card mt-4" data-lifecycle-documents
    data-id-parameter="{$parameter}" data-default-type="{$type}">
    <div class="workspace-card-header"><div>
        <h2 class="h5 mb-1">Request documents</h2>
        <p class="small text-secondary mb-0">Private evidence linked to the request version active at upload time.</p>
    </div></div>
    <div class="alert alert-danger m-3 mb-0 d-none" role="alert" tabindex="-1" data-lifecycle-document-alert></div>
    <div class="alert alert-success m-3 mb-0 d-none" role="status" tabindex="-1" data-lifecycle-document-feedback></div>
    <form class="document-upload-panel d-none" data-lifecycle-document-form novalidate>
        <div class="row g-3 align-items-end">
            <div class="col-lg-6">
                <label class="form-label fw-semibold" for="lifecycle-document-file">Choose document</label>
                <input id="lifecycle-document-file" class="form-control" type="file"
                    accept=".pdf,.doc,.docx" required data-lifecycle-document-file>
                <div class="form-text">PDF, DOC, or DOCX; maximum 10 MB.</div>
                <div class="invalid-feedback">Choose a supported document.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-semibold" for="lifecycle-document-type">Document type</label>
                <select id="lifecycle-document-type" class="form-select" data-lifecycle-document-type>
                    <option value="REQUEST_FORM">Request form</option>
                    <option value="SUPPORTING">Supporting evidence</option>
                    <option value="PROPOSED_AMENDMENT">Proposed amendment</option>
                    <option value="RENEWAL_EVIDENCE">Renewal evidence</option>
                    <option value="TERMINATION_EVIDENCE">Termination evidence</option>
                    <option value="LEGAL_REVIEW">Legal review document</option>
                    <option value="FINANCE_REVIEW">Finance review document</option>
                    <option value="PRESIDENT_DECISION">President decision</option>
                    <option value="OTHER">Other</option>
                </select>
            </div>
            <div class="col-lg-3 d-grid">
                <button class="btn btn-primary" type="submit" data-lifecycle-document-upload>
                    <span data-lifecycle-document-upload-label>Upload securely</span>
                    <span class="spinner-border spinner-border-sm ms-2 d-none"
                        aria-hidden="true" data-lifecycle-document-spinner></span>
                </button>
            </div>
        </div>
    </form>
    <div class="loading-state compact" data-lifecycle-documents-loading>
        <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
        <span>Loading request documents…</span>
    </div>
    <div class="empty-state compact d-none" data-lifecycle-documents-empty>
        <p class="text-secondary mb-0">No request documents have been added.</p>
    </div>
    <div class="table-responsive d-none" data-lifecycle-documents-table>
        <table class="table workspace-table align-middle mb-0">
            <thead><tr><th>Document</th><th>Type and version</th><th>Uploaded</th><th>Size</th><th class="text-end">Actions</th></tr></thead>
            <tbody data-lifecycle-documents-body></tbody>
        </table>
    </div>
</section>
HTML;
}
