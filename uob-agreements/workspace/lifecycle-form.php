<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader(
    'Lifecycle request',
    'lifecycle',
    ['https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css']
);
?>

<div id="lifecycle-form-alert" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
<div id="lifecycle-form-loading" class="loading-state"><div class="spinner-border text-primary"></div><span>Loading request form…</span></div>

<div id="lifecycle-form-content" class="d-none">
    <section class="workspace-form-hero">
        <div>
            <div class="workspace-form-hero-meta">
                <a href="lifecycle-requests.php" class="workspace-form-hero-back">← Back to Lifecycle requests</a>
                <span aria-hidden="true"></span>
                <p class="eyebrow mb-0">Official Agreement lifecycle request</p>
            </div>
            <h1 class="display-6 mb-2" data-form-title>New lifecycle request</h1>
            <p class="mb-0">Original Agreement: <strong data-agreement-title></strong></p>
        </div>
        <div class="workspace-form-hero-badge" aria-label="4 guided sections">
            <span>4</span><small>guided sections</small>
        </div>
    </section>

    <form id="lifecycle-request-form" class="mt-4 guided-lifecycle-form workspace-standard-form" novalidate>
        <div class="agreement-form-toolbar lifecycle-form-toolbar" aria-label="Lifecycle request sections">
            <div class="agreement-form-progress">
                <span data-lifecycle-progress>0 of 4 sections complete</span>
                <nav class="agreement-step-timeline lifecycle-step-timeline" aria-label="Lifecycle request progress">
                    <?php foreach ([1 => 'Request', 2 => 'Details', 3 => 'Financial', 4 => 'Summary'] as $number => $label): ?>
                        <button type="button" class="agreement-step" data-lifecycle-step="<?= $number ?>">
                            <span class="agreement-step-marker"><?= $number ?></span>
                            <span class="agreement-step-label"><?= $label ?></span>
                        </button>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <details class="workspace-card lifecycle-form-card mt-3" open data-lifecycle-section="1">
            <summary><span class="agreement-section-number">1</span><span><strong>Request type and justification</strong><small>Choose the formal lifecycle action and explain why it is needed.</small></span><b data-lifecycle-status>Not started</b></summary>
            <div class="form-section">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="request_type">Request type *</label><select class="form-select" id="request_type" required><option value="">Choose…</option><option value="RENEWAL">Renewal</option><option value="AMENDMENT">Amendment</option><option value="TERMINATION">Termination</option></select></div>
                <div class="col-12"><label class="form-label" for="justification">Justification *</label><textarea class="form-control" id="justification" rows="4" maxlength="5000" required></textarea></div>
            </div>
            </div>
        </details>

        <details class="workspace-card lifecycle-form-card mt-3 d-none" data-type-section="RENEWAL" data-lifecycle-section="2">
            <summary><span class="agreement-section-number">2</span><span><strong>Renewal evaluation and proposed term</strong><small>Record delivery to date and choose the full proposed duration.</small></span><b data-lifecycle-status>Not started</b></summary>
            <div class="form-section">
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="activities_summary">Activities completed under the Agreement *</label><textarea class="form-control" id="activities_summary" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label" for="achieved_value">Value and outcomes achieved *</label><textarea class="form-control" id="achieved_value" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label">Proposed duration *</label><div class="date-range-pair"><input class="form-control" id="proposed_start_date" type="text" readonly required placeholder="Select start"><span class="date-range-separator">to</span><input class="form-control" id="proposed_end_date" type="text" readonly required placeholder="Select end"></div><input id="renewal_duration" class="visually-hidden" type="text" tabindex="-1" aria-hidden="true" data-lifecycle-range required></div>
            </div>
            </div>
        </details>

        <details class="workspace-card lifecycle-form-card mt-3 d-none" data-type-section="AMENDMENT" data-lifecycle-section="2">
            <summary><span class="agreement-section-number">2</span><span><strong>Proposed amendment</strong><small>Identify the affected scope and the exact terms that need revision.</small></span><b data-lifecycle-status>Not started</b></summary>
            <div class="form-section">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="amendment_type">Amendment type *</label><input class="form-control" id="amendment_type" maxlength="255" required placeholder="Scope, term, party, financial, clause…"></div>
                <div class="col-12"><label class="form-label" for="amendment_reason">Reason for amendment *</label><textarea class="form-control" id="amendment_reason" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label" for="terms_to_amend">Clauses or terms to amend *</label><textarea class="form-control" id="terms_to_amend" rows="5" required></textarea></div>
            </div>
            </div>
        </details>

        <details class="workspace-card lifecycle-form-card mt-3 d-none" data-type-section="TERMINATION" data-lifecycle-section="2">
            <summary><span class="agreement-section-number">2</span><span><strong>Proposed termination</strong><small>Document the reason, date, and operational history.</small></span><b data-lifecycle-status>Not started</b></summary>
            <div class="form-section">
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="termination_reason">Reason for termination *</label><textarea class="form-control" id="termination_reason" rows="5" required></textarea></div>
                <div class="col-md-6"><label class="form-label" for="proposed_termination_date">Proposed termination date *</label><input class="form-control" id="proposed_termination_date" type="date" required></div>
                <div class="col-md-6"><label class="form-label" for="previous_initiatives">Were initiatives implemented under this Agreement? *</label><select class="form-select" id="previous_initiatives" required><option value="">Choose…</option><option value="true">Yes</option><option value="false">No</option></select></div>
            </div>
            </div>
        </details>

        <details class="workspace-card lifecycle-form-card mt-3" data-financial-section data-lifecycle-section="3">
            <summary><span class="agreement-section-number">3</span><span><strong>Financial implications</strong><small>Confirm whether the request changes financial commitments.</small></span><b data-lifecycle-status>Review</b></summary>
            <div class="form-section">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="financial_amount">Amount</label><input class="form-control" id="financial_amount" type="number" min="0" step="0.01"></div>
                <div class="col-md-2"><label class="form-label" for="financial_currency">Currency</label><input class="form-control" id="financial_currency" maxlength="3" value="BHD"></div>
                <div class="col-md-6"><label class="form-label" for="financial_description">Description</label><textarea class="form-control" id="financial_description" rows="3"></textarea></div>
            </div>
            </div>
        </details>

        <details class="workspace-card lifecycle-form-card mt-3" data-lifecycle-section="4">
            <summary><span class="agreement-section-number">4</span><span><strong>Version summary</strong><small>Explain what this saved version contains.</small></span><b data-lifecycle-status>Review</b></summary>
            <div class="form-section"><label class="form-label" for="change_summary">Version change summary</label><input class="form-control" id="change_summary" maxlength="500" placeholder="Required when saving a revision; helpful for every update"></div>
        </details>

        <div class="form-actions mt-4">
            <a href="lifecycle-requests.php" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-primary" type="submit"><span data-save-label>Save draft</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-save-spinner></span></button>
        </div>
    </form>
</div>

<?php workspaceFooter([
    'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
    'assets/js/lifecycle-form.js?v=20260729-portal-workflow-v5',
]); ?>
