<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader('Lifecycle request', 'lifecycle');
?>

<div class="mb-4"><a href="lifecycle-requests.php" class="back-link">← Back to Lifecycle requests</a></div>
<div id="lifecycle-form-alert" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
<div id="lifecycle-form-loading" class="loading-state"><div class="spinner-border text-primary"></div><span>Loading request form…</span></div>

<div id="lifecycle-form-content" class="d-none">
    <section class="page-heading">
        <p class="eyebrow mb-2">Official Agreement lifecycle request</p>
        <h1 class="display-6 mb-2" data-form-title>New lifecycle request</h1>
        <p class="text-secondary mb-0">Original Agreement: <strong data-agreement-title></strong></p>
    </section>

    <form id="lifecycle-request-form" class="mt-4" novalidate>
        <section class="workspace-card form-section">
            <h2 class="h5 mb-3">1. Request type and justification</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="request_type">Request type</label><select class="form-select" id="request_type" required><option value="">Choose…</option><option value="RENEWAL">Renewal</option><option value="AMENDMENT">Amendment</option><option value="TERMINATION">Termination</option></select></div>
                <div class="col-12"><label class="form-label" for="justification">Justification</label><textarea class="form-control" id="justification" rows="4" maxlength="5000"></textarea></div>
            </div>
        </section>

        <section class="workspace-card form-section mt-4 d-none" data-type-section="RENEWAL">
            <h2 class="h5 mb-3">2. Renewal evaluation and proposed term</h2>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="activities_summary">Activities completed under the Agreement</label><textarea class="form-control" id="activities_summary" rows="4"></textarea></div>
                <div class="col-12"><label class="form-label" for="achieved_value">Value and outcomes achieved</label><textarea class="form-control" id="achieved_value" rows="4"></textarea></div>
                <div class="col-md-6"><label class="form-label" for="proposed_start_date">Proposed start date</label><input class="form-control" id="proposed_start_date" type="date"></div>
                <div class="col-md-6"><label class="form-label" for="proposed_end_date">Proposed end date</label><input class="form-control" id="proposed_end_date" type="date"></div>
            </div>
        </section>

        <section class="workspace-card form-section mt-4 d-none" data-type-section="AMENDMENT">
            <h2 class="h5 mb-3">2. Proposed amendment</h2>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="amendment_type">Amendment type</label><input class="form-control" id="amendment_type" maxlength="255" placeholder="Scope, term, party, financial, clause…"></div>
                <div class="col-12"><label class="form-label" for="amendment_reason">Reason for amendment</label><textarea class="form-control" id="amendment_reason" rows="4"></textarea></div>
                <div class="col-12"><label class="form-label" for="terms_to_amend">Clauses or terms to amend</label><textarea class="form-control" id="terms_to_amend" rows="5"></textarea></div>
            </div>
        </section>

        <section class="workspace-card form-section mt-4 d-none" data-type-section="TERMINATION">
            <h2 class="h5 mb-3">2. Proposed termination</h2>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="termination_reason">Reason for termination</label><textarea class="form-control" id="termination_reason" rows="5"></textarea></div>
                <div class="col-md-6"><label class="form-label" for="proposed_termination_date">Proposed termination date</label><input class="form-control" id="proposed_termination_date" type="date"></div>
                <div class="col-md-6"><label class="form-label" for="previous_initiatives">Were initiatives implemented under this Agreement?</label><select class="form-select" id="previous_initiatives"><option value="">Choose…</option><option value="true">Yes</option><option value="false">No</option></select></div>
            </div>
        </section>

        <section class="workspace-card form-section mt-4" data-financial-section>
            <h2 class="h5 mb-3">3. Financial implications</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="financial_amount">Amount</label><input class="form-control" id="financial_amount" type="number" min="0" step="0.01"></div>
                <div class="col-md-2"><label class="form-label" for="financial_currency">Currency</label><input class="form-control" id="financial_currency" maxlength="3" value="BHD"></div>
                <div class="col-md-6"><label class="form-label" for="financial_description">Description</label><textarea class="form-control" id="financial_description" rows="3"></textarea></div>
            </div>
        </section>

        <section class="workspace-card form-section mt-4">
            <label class="form-label" for="change_summary">Version change summary</label>
            <input class="form-control" id="change_summary" maxlength="500" placeholder="Required when saving a revision; helpful for every update">
        </section>

        <div class="form-actions mt-4">
            <a href="lifecycle-requests.php" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary" type="submit"><span data-save-label>Save draft</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-save-spinner></span></button>
        </div>
    </form>
</div>

<?php workspaceFooter(['assets/js/lifecycle-form.js']); ?>
