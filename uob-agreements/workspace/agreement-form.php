<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreement form', 'agreements');
?>

<div class="mb-4">
    <a href="agreements.php" class="back-link" data-cancel-link>← Back to Agreements</a>
</div>

<section class="page-heading">
    <p class="eyebrow mb-2" data-form-eyebrow>Agreement management</p>
    <h1 class="display-6 mb-2" data-form-title>Create Agreement</h1>
    <p class="text-secondary mb-0" data-form-description>
        Enter the core Agreement information. Additional fields can be added here in a later phase.
    </p>
</section>

<div
    id="form-alert"
    class="alert alert-danger mt-4 d-none"
    role="alert"
    aria-live="polite"
></div>

<div id="form-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Preparing Agreement form…</span>
</div>

<form id="agreement-form" class="d-none" novalidate>
    <section class="workspace-card mt-4" aria-labelledby="basic-information-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="basic-information-title" class="h5 mb-1">Basic information</h2>
                <p class="small text-secondary mb-0">
                    These fields are stored in PostgreSQL and included in every version snapshot.
                </p>
            </div>
        </div>

        <div class="form-section">
            <div class="row g-4">
                <div class="col-12">
                    <label for="agreement-title" class="form-label">Agreement name</label>
                    <input
                        id="agreement-title"
                        name="title"
                        type="text"
                        class="form-control"
                        maxlength="255"
                        placeholder="Enter a clear Agreement name"
                        required
                    >
                    <div class="invalid-feedback">Agreement name is required.</div>
                </div>

                <div class="col-md-6">
                    <label for="agreement-type" class="form-label">Type of cooperation</label>
                    <input
                        id="agreement-type"
                        name="agreement_type"
                        type="text"
                        class="form-control"
                        maxlength="100"
                        list="agreement-type-options"
                        placeholder="Select or enter a type"
                        required
                    >
                    <datalist id="agreement-type-options">
                        <option value="Memorandum of Understanding"></option>
                        <option value="Cooperation Agreement"></option>
                        <option value="Framework Agreement"></option>
                        <option value="Research Agreement"></option>
                        <option value="Other"></option>
                    </datalist>
                    <div class="invalid-feedback">Agreement type is required.</div>
                </div>

                <div class="col-md-6">
                    <label for="agreement-partner" class="form-label">Partner organization</label>
                    <select
                        id="agreement-partner"
                        name="partner_id"
                        class="form-select"
                        required
                    >
                        <option value="">Select a partner organization</option>
                    </select>
                    <div class="form-text" data-partner-help>
                        Only active partner records are available.
                    </div>
                    <div class="invalid-feedback">Partner organization is required.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="description-title">
        <div class="workspace-card-header">
            <div>
                <h2 id="description-title" class="h5 mb-1">Agreement summary</h2>
                <p class="small text-secondary mb-0">
                    Describe the purpose, scope, and expected cooperation.
                </p>
            </div>
        </div>

        <div class="form-section">
            <label for="agreement-description" class="form-label">Description</label>
            <textarea
                id="agreement-description"
                name="description"
                class="form-control"
                rows="7"
                placeholder="Write a concise description of the Agreement"
                required
            ></textarea>
            <div class="invalid-feedback">Agreement description is required.</div>
        </div>
    </section>

    <div class="form-actions mt-4">
        <a href="agreements.php" class="btn btn-outline-secondary" data-cancel-link>Cancel</a>
        <button id="save-agreement" class="btn btn-primary" type="submit">
            <span data-save-label>Save draft</span>
            <span
                class="spinner-border spinner-border-sm ms-2 d-none"
                data-save-spinner
                aria-hidden="true"
            ></span>
        </button>
    </div>
</form>

<?php workspaceFooter(['assets/js/agreement-form.js']); ?>
