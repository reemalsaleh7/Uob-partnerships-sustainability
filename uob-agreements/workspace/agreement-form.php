<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreement form', 'agreements');

function field(string $id, string $label, string $type = 'text', string $class = 'col-md-6', string $attributes = ''): void
{
    echo '<div class="' . $class . '"><label for="' . $id . '" class="form-label">' . $label
        . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type
        . '" class="form-control" ' . $attributes . '></div>';
}

function textArea(string $id, string $label, string $help = '', int $rows = 4): void
{
    echo '<div class="col-12"><label for="' . $id . '" class="form-label">' . $label . '</label>'
        . '<textarea id="' . $id . '" name="' . $id . '" class="form-control" rows="'
        . $rows . '"></textarea>';
    if ($help !== '') {
        echo '<div class="form-text">' . $help . '</div>';
    }
    echo '</div>';
}
?>

<div class="mb-4">
    <a href="agreements.php" class="back-link" data-cancel-link>← Back to Agreements</a>
</div>

<section class="page-heading">
    <p class="eyebrow mb-2" data-form-eyebrow>Agreement management</p>
    <h1 class="display-6 mb-2" data-form-title>Create comprehensive Agreement</h1>
    <p class="text-secondary mb-0" data-form-description>
        Complete the cooperation request, MOU, reporting, ranking, and implementation information in one record.
        Applicant identity, organizational unit, submission time, status, and approvals are recorded automatically.
    </p>
</section>

<div id="form-alert" class="alert alert-danger mt-4 d-none" role="alert" aria-live="polite"></div>
<div id="form-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Preparing Agreement form…</span>
</div>

<form id="agreement-form" class="d-none" novalidate>
    <section class="workspace-card mt-4" aria-labelledby="identity-title">
        <div class="workspace-card-header"><div>
            <h2 id="identity-title" class="h5 mb-1">1. Cooperation project information</h2>
            <p class="small text-secondary mb-0">Official request form, MOU template, and legacy catalogue identity fields.</p>
        </div></div>
        <div class="form-section"><div class="row g-4">
            <?php field('title', 'Agreement title (English) *', 'text', 'col-md-6', 'maxlength="255" required'); ?>
            <?php field('title_ar', 'اسم مشروع التعاون (العربية)', 'text', 'col-md-6', 'maxlength="255" dir="rtl"'); ?>
            <div class="col-md-4">
                <label for="agreement_type" class="form-label">Type of cooperation *</label>
                <select id="agreement_type" name="agreement_type" class="form-select" required>
                    <option value="">Select a type</option>
                    <option value="Cooperation Framework">Cooperation Framework</option>
                    <option value="Memorandum of Understanding">Memorandum of Understanding</option>
                    <option value="Cooperation Agreement">Cooperation Agreement</option>
                    <option value="Research Agreement">Research Agreement</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="geographic_scope" class="form-label">Partner scope *</label>
                <select id="geographic_scope" name="geographic_scope" class="form-select" required>
                    <option value="">Select scope</option>
                    <option value="LOCAL">Local / Bahrain</option>
                    <option value="INTERNATIONAL">International</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="legal_binding_status" class="form-label">Legal effect</label>
                <select id="legal_binding_status" name="legal_binding_status" class="form-select">
                    <option value="NON_BINDING">Non-binding MOU</option>
                    <option value="BINDING">Legally binding Agreement</option>
                    <option value="MIXED">Mixed / program-specific obligations</option>
                </select>
            </div>
            <div class="col-12">
                <label for="partner_ids" class="form-label">Partner organization(s) *</label>
                <select id="partner_ids" name="partner_ids[]" class="form-select" size="5" multiple required></select>
                <div class="form-text" data-partner-help>Use Ctrl (Windows) or Command (Mac) to select more than one partner.</div>
            </div>
            <?php textArea('description', 'Brief Agreement profile / summary *', 'A concise public-facing description of the cooperation.', 4); ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="duration-title">
        <div class="workspace-card-header"><div><h2 id="duration-title" class="h5 mb-1">2. Duration, signing, and renewal</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <?php field('start_date', 'Project start date *', 'date', 'col-md-3'); ?>
            <?php field('end_date', 'Project end date *', 'date', 'col-md-3'); ?>
            <?php field('signing_date', 'Signing date', 'date', 'col-md-3'); ?>
            <?php field('effective_date', 'Effective date', 'date', 'col-md-3'); ?>
            <div class="col-md-3 form-check-wrap">
                <div class="form-check mt-4"><input id="auto_renew" name="auto_renew" class="form-check-input" type="checkbox">
                    <label for="auto_renew" class="form-check-label">Automatically renewable</label></div>
            </div>
            <?php field('renewal_term_months', 'Renewal term (months)', 'number', 'col-md-3', 'min="0"'); ?>
            <?php field('non_renewal_notice_months', 'Non-renewal notice (months)', 'number', 'col-md-3', 'min="0"'); ?>
            <?php field('termination_notice_months', 'Termination notice (months)', 'number', 'col-md-3', 'min="0" value="6"'); ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="purpose-title">
        <div class="workspace-card-header"><div><h2 id="purpose-title" class="h5 mb-1">3. Need, objectives, and impact</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <?php textArea('need_justification', 'Statement of need and justification *'); ?>
            <?php textArea('objectives', 'Cooperation objectives *', 'Enter one objective per line where practical.'); ?>
            <?php textArea('expected_value', 'Expected value and impact for the University *'); ?>
            <?php textArea('focus_areas', 'Focus areas', 'Examples: research, academic collaboration, training, innovation, student exchange.'); ?>
            <?php textArea('collaboration_areas', 'Fields of cooperation / MOU Article 1 *'); ?>
            <?php textArea('implementation_methods', 'Implementation methods / MOU Article 2 *'); ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="commitment-title">
        <div class="workspace-card-header"><div><h2 id="commitment-title" class="h5 mb-1">4. Commitments and resources</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <div class="col-md-4"><div class="form-check"><input id="financial_commitments" name="financial_commitments" class="form-check-input" type="checkbox" data-toggle-section="financial-fields"><label for="financial_commitments" class="form-check-label">Financial commitments</label></div></div>
            <div class="col-md-8 row g-3 d-none" id="financial-fields">
                <?php field('financial_amount', 'Amount', 'number', 'col-md-4', 'min="0" step="0.01"'); ?>
                <?php field('financial_currency', 'Currency', 'text', 'col-md-3', 'maxlength="3" value="BHD"'); ?>
                <?php field('financial_description', 'Financial arrangement / description', 'text', 'col-md-5'); ?>
            </div>
            <div class="col-md-4"><div class="form-check"><input id="human_resources_commitments" name="human_resources_commitments" class="form-check-input" type="checkbox" data-toggle-section="hr-fields"><label for="human_resources_commitments" class="form-check-label">Human-resources commitments</label></div></div>
            <div class="col-md-8 d-none" id="hr-fields"><?php field('human_resources_description', 'Human-resources description', 'text', 'col-12'); ?></div>
            <div class="col-md-4"><div class="form-check"><input id="training_programs" name="training_programs" class="form-check-input" type="checkbox" data-toggle-section="training-fields"><label for="training_programs" class="form-check-label">Training programs</label></div></div>
            <div class="col-md-8 d-none" id="training-fields"><?php field('training_programs_description', 'Training program description', 'text', 'col-12'); ?></div>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="alignment-title">
        <div class="workspace-card-header"><div><h2 id="alignment-title" class="h5 mb-1">5. Rankings and Sustainable Development Goals</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <div class="col-12"><span class="form-label d-block">Ranking contribution</span>
                <?php foreach (['QS_WORLD' => 'QS World Rankings', 'THE_IMPACT' => 'THE Impact Rankings', 'UI_GREENMETRIC' => 'UI GreenMetric'] as $value => $label): ?>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="rankings[]" value="<?= $value ?>" id="ranking-<?= strtolower($value) ?>"><label class="form-check-label" for="ranking-<?= strtolower($value) ?>"><?= $label ?></label></div>
                <?php endforeach; ?>
            </div>
            <div class="col-12"><span class="form-label d-block">Supported SDGs</span><div class="sdg-grid">
                <?php for ($sdg = 1; $sdg <= 17; $sdg++): ?><label class="form-check sdg-option"><input class="form-check-input" type="checkbox" name="sdgs[]" value="<?= $sdg ?>"><span class="form-check-label">SDG <?= $sdg ?></span></label><?php endfor; ?>
            </div></div>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="governance-title">
        <div class="workspace-card-header"><div><h2 id="governance-title" class="h5 mb-1">6. Governance and MOU clauses</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <div class="col-md-4"><div class="form-check"><input id="annual_report_required" name="annual_report_required" class="form-check-input" type="checkbox" checked><label for="annual_report_required" class="form-check-label">Annual joint performance report required</label></div></div>
            <?php textArea('monitoring_plan', 'Monitoring, evaluation, obstacles, and reporting plan'); ?>
            <?php textArea('confidentiality_terms', 'Confidentiality and announcement terms'); ?>
            <?php textArea('intellectual_property_terms', 'Intellectual-property terms'); ?>
            <?php textArea('compliance_terms', 'National/international rights, obligations, laws, and regulations'); ?>
            <?php textArea('relationship_disclaimer', 'No partnership, joint venture, employment, or franchise disclaimer'); ?>
            <?php textArea('amendment_terms', 'How the Agreement may be amended'); ?>
            <?php textArea('dispute_resolution_terms', 'Dispute-resolution terms'); ?>
            <?php textArea('other_terms', 'Other agreed terms'); ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="contacts-title">
        <div class="workspace-card-header"><div><h2 id="contacts-title" class="h5 mb-1">7. Coordinators and signatories</h2><p class="small text-secondary mb-0">Leave an optional row blank if it is not yet known.</p></div></div>
        <div class="form-section"><div class="row g-4">
            <?php foreach ([['UOB','COORDINATOR','UOB coordinator'],['PARTNER','COORDINATOR','Partner coordinator'],['UOB','SIGNATORY','UOB signatory'],['PARTNER','SIGNATORY','Partner signatory']] as $index => $contact): ?>
                <fieldset class="col-12 contact-row" data-contact-row data-party-type="<?= $contact[0] ?>" data-contact-role="<?= $contact[1] ?>">
                    <legend class="h6 mb-3"><?= $contact[2] ?></legend><div class="row g-3">
                        <?php field('contact_' . $index . '_name', 'Full name', 'text', 'col-md-3'); ?>
                        <?php field('contact_' . $index . '_title', 'Job title', 'text', 'col-md-3'); ?>
                        <?php field('contact_' . $index . '_email', 'Email', 'email', 'col-md-3'); ?>
                        <?php field('contact_' . $index . '_phone', 'Phone', 'tel', 'col-md-3'); ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="program-title">
        <div class="workspace-card-header"><div><h2 id="program-title" class="h5 mb-1">8. Proposed executive program</h2><p class="small text-secondary mb-0">Optional at draft stage; it can also be uploaded as a document.</p></div></div>
        <div class="form-section"><div class="row g-4" data-program-row>
            <?php field('program_title', 'Proposed program title', 'text', 'col-md-6'); ?>
            <?php field('program_responsible_entity', 'Responsible implementing entity', 'text', 'col-md-6'); ?>
            <?php textArea('program_description', 'Brief program description'); ?>
            <?php textArea('program_objectives', 'General program objectives'); ?>
            <?php textArea('program_expected_outputs', 'Expected outputs and outcomes'); ?>
            <?php field('program_start_date', 'Program start date', 'date', 'col-md-4'); ?>
            <?php field('program_end_date', 'Program end date', 'date', 'col-md-4'); ?>
            <?php field('program_applicant_name', 'Program applicant name', 'text', 'col-md-4'); ?>
        </div></div>
    </section>

    <section class="workspace-card mt-4" aria-labelledby="outcomes-title">
        <div class="workspace-card-header"><div><h2 id="outcomes-title" class="h5 mb-1">9. Outcomes and publication</h2></div></div>
        <div class="form-section"><div class="row g-4">
            <?php foreach (['STUDENTS_EXCHANGED' => 'Students exchanged', 'FACULTY_EXCHANGED' => 'Faculty exchanged', 'JOINT_PROGRAMS' => 'Joint programs'] as $code => $label): ?>
                <div class="col-12 metric-row" data-metric-row data-metric-code="<?= $code ?>"><div class="row g-3 align-items-end">
                    <div class="col-md-3"><span class="form-label d-block mb-2"><?= $label ?></span></div>
                    <?php field('metric_' . strtolower($code) . '_planned', 'Planned number', 'number', 'col-md-2', 'min="0"'); ?>
                    <?php field('metric_' . strtolower($code) . '_actual', 'Actual number', 'number', 'col-md-2', 'min="0"'); ?>
                    <?php field('metric_' . strtolower($code) . '_notes', 'Notes', 'text', 'col-md-5'); ?>
                </div></div>
            <?php endforeach; ?>
            <?php field('signing_link', 'Public signing/news link', 'url', 'col-12', 'placeholder="https://..."'); ?>
        </div></div>
    </section>

    <div class="form-actions mt-4">
        <a href="agreements.php" class="btn btn-outline-secondary" data-cancel-link>Cancel</a>
        <button id="save-agreement" class="btn btn-primary" type="submit"><span data-save-label>Save draft</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-save-spinner aria-hidden="true"></span></button>
    </div>
</form>

<?php workspaceFooter(['assets/js/agreement-form.js']); ?>
