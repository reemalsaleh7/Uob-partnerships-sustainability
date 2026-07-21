<?php

declare(strict_types=1);

function agreementDocumentsPanel(
    string $idParameter = 'id',
    string $defaultType = 'SUPPORTING'
): void {
    $safeIdParameter = htmlspecialchars(
        $idParameter,
        ENT_QUOTES,
        'UTF-8'
    );
    $safeDefaultType = htmlspecialchars(
        $defaultType,
        ENT_QUOTES,
        'UTF-8'
    );

    echo <<<HTML
<section
    class="workspace-card mt-4"
    aria-labelledby="agreement-documents-title"
    data-agreement-documents
    data-id-parameter="{$safeIdParameter}"
    data-default-type="{$safeDefaultType}"
>
    <div class="workspace-card-header">
        <div>
            <h2 id="agreement-documents-title" class="h5 mb-1">Documents</h2>
            <p class="small text-secondary mb-0">
                Secure files linked to the Agreement version active when each file was uploaded.
            </p>
        </div>
    </div>

    <div
        class="alert alert-danger m-3 mb-0 d-none"
        role="alert"
        aria-live="polite"
        tabindex="-1"
        data-document-alert
    ></div>

    <div
        class="alert alert-success m-3 mb-0 d-none"
        role="status"
        aria-live="polite"
        tabindex="-1"
        data-document-feedback
    ></div>

    <form class="document-upload-panel d-none" data-document-upload-form novalidate>
        <div class="row g-3 align-items-end">
            <div class="col-lg-6">
                <label class="form-label fw-semibold" for="agreement-document-file">
                    Choose document
                </label>
                <input
                    id="agreement-document-file"
                    class="form-control"
                    type="file"
                    accept=".pdf,.doc,.docx"
                    required
                    data-document-file
                >
                <div class="form-text">PDF, DOC, or DOCX; maximum 10 MB.</div>
                <div class="invalid-feedback">Choose a supported document.</div>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-semibold" for="agreement-document-type">
                    Document type
                </label>
                <select
                    id="agreement-document-type"
                    class="form-select"
                    required
                    data-document-type
                >
                    <option value="AGREEMENT_DRAFT">Agreement draft</option>
                    <option value="SUPPORTING">Supporting document</option>
                    <option value="LEGAL_REVIEW">Legal review document</option>
                    <option value="FINANCE_REVIEW">Finance review document</option>
                    <option value="SIGNED_AGREEMENT">Final signed Agreement</option>
                    <option value="OTHER">Other</option>
                </select>
            </div>
            <div class="col-lg-3 d-grid">
                <button class="btn btn-primary" type="submit" data-upload-document>
                    <span data-upload-document-label>Upload securely</span>
                    <span
                        class="spinner-border spinner-border-sm ms-2 d-none"
                        aria-hidden="true"
                        data-upload-document-spinner
                    ></span>
                </button>
            </div>
        </div>
    </form>

    <div class="loading-state compact" aria-live="polite" data-documents-loading>
        <div class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></div>
        <span>Loading documents…</span>
    </div>

    <div class="empty-state compact d-none" data-documents-empty>
        <p class="text-secondary mb-0">No documents have been added.</p>
    </div>

    <div class="table-responsive d-none" data-documents-table-wrap>
        <table class="table workspace-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Document</th>
                    <th scope="col">Type and version</th>
                    <th scope="col">Uploaded</th>
                    <th scope="col">Size</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody data-documents-body></tbody>
        </table>
    </div>
</section>
HTML;
}
