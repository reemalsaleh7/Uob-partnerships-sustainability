<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader(
    'Agreement form',
    'agreements',
    ['https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css']
);

function field(
    string $id,
    string $label,
    string $type = 'text',
    string $class = 'col-md-6',
    string $attributes = '',
    string $help = ''
): void {
    echo '<div class="' . $class . '"><label for="' . $id
        . '" class="form-label">' . $label . '</label><input id="' . $id
        . '" name="' . $id . '" type="' . $type
        . '" class="form-control" ' . $attributes . '>';
    if ($help !== '') {
        echo '<div class="form-text">' . $help . '</div>';
    }
    echo '<div class="invalid-feedback">Complete this field correctly.</div></div>';
}

function textArea(
    string $id,
    string $label,
    string $help = '',
    int $rows = 4,
    string $attributes = ''
): void {
    echo '<div class="col-12"><label for="' . $id
        . '" class="form-label">' . $label . '</label><textarea id="' . $id
        . '" name="' . $id . '" class="form-control" rows="' . $rows . '" '
        . $attributes . '></textarea>';
    if ($help !== '') {
        echo '<div class="form-text">' . $help . '</div>';
    }
    echo '<div class="invalid-feedback">Complete this field correctly.</div></div>';
}

function sectionHeader(
    int $number,
    string $id,
    string $title,
    string $description,
    bool $open = false
): void {
    $expanded = $open ? 'true' : 'false';
    echo <<<HTML
<section class="workspace-card agreement-form-section mt-3" data-form-section data-section-number="{$number}">
    <button
        class="agreement-section-toggle"
        type="button"
        aria-expanded="{$expanded}"
        aria-controls="{$id}-content"
        data-section-toggle
    >
        <span class="agreement-section-number">{$number}</span>
        <span class="agreement-section-heading">
            <strong id="{$id}-title">{$title}</strong>
            <small>{$description}</small>
        </span>
        <span class="section-status section-status-pending" data-section-status>Not started</span>
        <span class="agreement-section-chevron" aria-hidden="true"></span>
    </button>
    <div
        id="{$id}-content"
        class="agreement-section-body"
        aria-labelledby="{$id}-title"
        data-section-body
HTML;
    echo $open ? ">\n" : " hidden>\n";
}

function sectionFooter(): void
{
    echo "</div>\n</section>\n";
}

$sdgs = [
    1 => ['No Poverty', 'End poverty in all its forms everywhere.'],
    2 => ['Zero Hunger', 'End hunger, improve nutrition, and promote sustainable agriculture.'],
    3 => ['Good Health and Well-being', 'Ensure healthy lives and promote well-being for all.'],
    4 => ['Quality Education', 'Ensure inclusive, equitable quality education and lifelong learning.'],
    5 => ['Gender Equality', 'Achieve gender equality and empower all women and girls.'],
    6 => ['Clean Water and Sanitation', 'Ensure sustainable water and sanitation for all.'],
    7 => ['Affordable and Clean Energy', 'Ensure access to affordable, reliable, sustainable energy.'],
    8 => ['Decent Work and Economic Growth', 'Promote inclusive growth and decent work for all.'],
    9 => ['Industry, Innovation and Infrastructure', 'Build resilient infrastructure and foster innovation.'],
    10 => ['Reduced Inequalities', 'Reduce inequality within and among countries.'],
    11 => ['Sustainable Cities and Communities', 'Make settlements inclusive, safe, resilient, and sustainable.'],
    12 => ['Responsible Consumption and Production', 'Ensure sustainable consumption and production patterns.'],
    13 => ['Climate Action', 'Take urgent action to combat climate change and its impacts.'],
    14 => ['Life Below Water', 'Conserve and sustainably use oceans and marine resources.'],
    15 => ['Life on Land', 'Protect terrestrial ecosystems and biodiversity.'],
    16 => ['Peace, Justice and Strong Institutions', 'Promote peaceful societies, justice, and accountable institutions.'],
    17 => ['Partnerships for the Goals', 'Strengthen implementation through effective global partnerships.'],
];
?>

<div class="mb-4">
    <a href="agreements.php" class="back-link" data-cancel-link>← Back to Agreements</a>
</div>

<section class="page-heading agreement-form-heading">
    <div>
        <p class="eyebrow mb-2" data-form-eyebrow>Agreement management</p>
        <h1 class="display-6 mb-2" data-form-title>Create comprehensive Agreement</h1>
        <p class="text-secondary mb-0" data-form-description>
            Complete one section at a time. Applicant identity, organizational unit,
            submission time, status, and approvals are recorded automatically.
        </p>
    </div>
</section>

<div id="form-alert" class="alert alert-danger mt-4 d-none" role="alert" aria-live="assertive" tabindex="-1"></div>
<div id="form-feedback" class="alert alert-info mt-4 d-none" role="status" aria-live="polite" tabindex="-1"></div>
<div id="form-loading" class="loading-state" aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Preparing Agreement form…</span>
</div>

<form id="agreement-form" class="d-none" novalidate>
    <div class="agreement-form-toolbar mt-4" aria-label="Form section controls">
        <div class="agreement-form-progress">
            <span data-progress-label>0 of 10 sections complete</span>
            <nav class="agreement-step-timeline" aria-label="Agreement form sections" data-step-timeline>
                <?php foreach ([
                    1 => 'Partner',
                    2 => 'Duration',
                    3 => 'Purpose',
                    4 => 'Resources',
                    5 => 'Impact',
                    6 => 'MOU clauses',
                    7 => 'People',
                    8 => 'Programmes',
                    9 => 'Outcomes',
                    10 => 'Media',
                ] as $stepNumber => $stepLabel): ?>
                    <button
                        type="button"
                        class="agreement-step"
                        data-step-target="<?= $stepNumber ?>"
                        aria-label="Go to section <?= $stepNumber ?>: <?= $stepLabel ?>"
                    >
                        <span class="agreement-step-marker"><?= $stepNumber ?></span>
                        <span class="agreement-step-label"><?= $stepLabel ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="agreement-form-toolbar-actions">
            <button type="button" class="btn btn-sm btn-outline-primary" data-expand-all>Open all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-collapse-all>Collapse all</button>
        </div>
    </div>

    <section class="workspace-card mt-4 d-none" aria-labelledby="change-reason-title" data-change-reason-section>
        <div class="workspace-card-header"><div>
            <h2 id="change-reason-title" class="h5 mb-1">Reason for this revision</h2>
            <p class="small text-secondary mb-0">Previous reviewers will see this explanation beside the highlighted changes.</p>
        </div></div>
        <div class="form-section">
            <label for="change_summary" class="form-label">What changed, and why? *</label>
            <textarea id="change_summary" name="change_summary" class="form-control" rows="3" maxlength="1000" placeholder="Example: Updated the termination notice from 3 to 6 months to address Legal Office feedback."></textarea>
            <div class="form-text">Be specific enough for a reviewer to understand the revision without comparing the whole record.</div>
        </div>
    </section>

    <?php sectionHeader(
        1,
        'identity',
        'Cooperation and partner information',
        'Identify the project and select its partner organization.',
        true
    ); ?>
    <div class="form-section"><div class="row g-4">
        <?php field('title', 'Agreement title (English) *', 'text', 'col-md-6', 'maxlength="255" required autocomplete="off"'); ?>
        <?php field('title_ar', 'اسم مشروع التعاون (العربية) *', 'text', 'col-md-6', 'maxlength="255" dir="rtl" lang="ar" required'); ?>
        <div class="col-md-6">
            <label for="agreement_type" class="form-label">Type of cooperative project *</label>
            <select id="agreement_type" name="agreement_type" class="form-select" required>
                <option value="">Select project type</option>
                <option value="Cooperation Framework">Cooperation Framework</option>
                <option value="Memorandum of Understanding">Memorandum of Understanding (MOU)</option>
                <option value="Cooperation Agreement">Cooperation Agreement</option>
                <option value="Research Agreement">Research Agreement</option>
                <option value="Other">Other</option>
            </select>
            <div class="form-text">The formal instrument used for this cooperative project.</div>
        </div>
        <div class="col-12">
            <div class="partner-picker" data-partner-picker>
                <div class="partner-picker-heading">
                    <div>
                        <label for="partner-search" class="form-label mb-1">Partner organization *</label>
                        <p class="form-text mt-0 mb-0">Search the University directory and select one partner. Choosing another replaces the current selection.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-show-new-partner>+ Add missing partner</button>
                </div>
                <div class="partner-search-wrap">
                    <input id="partner-search" type="search" class="form-control" placeholder="Search by organization, type, country, or website" autocomplete="off" data-partner-search>
                    <span class="partner-search-count" data-partner-search-count></span>
                </div>
                <div class="partner-results" role="listbox" aria-label="Available partner organizations" data-partner-results hidden></div>
                <select id="partner_ids" name="partner_id" class="visually-hidden" tabindex="-1" aria-hidden="true"></select>
                <div class="partner-selection-heading">
                    <strong>Selected partner</strong>
                    <span><span data-selected-partner-count>None selected</span> · <span data-derived-partner-scope>Scope determined after selection</span></span>
                </div>
                <div class="selected-partners" data-selected-partners>
                    <p class="partner-empty mb-0">No partner selected yet.</p>
                </div>
                <div class="invalid-feedback d-block d-none" data-partner-error>Select at least one partner organization.</div>
                <div class="partner-agreement-context d-none" role="status" aria-live="polite" data-partner-agreement-context></div>
            </div>
        </div>

        <div class="col-12 d-none" data-new-partner-panel>
            <div class="new-partner-panel">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="h6 mb-1" data-partner-editor-title>Add a partner to the University directory</h3>
                        <p class="small text-secondary mb-0" data-partner-editor-help>The new profile becomes reusable in future Agreements. Selected profiles can also be corrected through their Edit details action.</p>
                    </div>
                    <button type="button" class="btn-close" aria-label="Close new partner form" data-close-new-partner></button>
                </div>
                <div class="row g-3 mt-1">
                    <?php field('new_partner_name', 'Organization name *', 'text', 'col-md-6', 'maxlength="255"'); ?>
                    <div class="col-md-3">
                        <label for="new_partner_type" class="form-label">Partner type *</label>
                        <select id="new_partner_type" class="form-select">
                            <option value="">Select type</option>
                            <option value="PUBLIC_GOVERNMENT">Public / government</option>
                            <option value="PRIVATE">Private</option>
                            <option value="ACADEMIC">Academic</option>
                            <option value="NON_PROFIT">Non-profit</option>
                        </select>
                    </div>
                    <?php field('new_partner_country', 'Country *', 'text', 'col-md-3', 'maxlength="100" autocomplete="country-name"'); ?>
                    <?php field('new_partner_website', 'Website *', 'url', 'col-md-6', 'maxlength="255" placeholder="https://..."'); ?>
                    <?php textArea('new_partner_profile', 'Brief partner profile', 'Describe the organization’s purpose, expertise, and relevance to the cooperation.', 3, 'maxlength="4000"'); ?>
                    <div class="col-12">
                        <div class="partner-lookup-actions">
                            <button type="button" class="btn btn-outline-primary" data-lookup-partner>
                                <span data-lookup-partner-label>Search the web for partner details</span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" data-lookup-partner-spinner aria-hidden="true"></span>
                            </button>
                            <span class="small text-secondary">Suggestions use public Wikidata records and fill only empty fields. Review them before saving.</span>
                        </div>
                        <div class="partner-lookup-feedback d-none mt-2" role="status" aria-live="polite" data-partner-lookup-feedback></div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" class="btn btn-primary" data-create-partner>
                            <span data-create-partner-label>Add and select partner</span>
                            <span class="spinner-border spinner-border-sm ms-2 d-none" data-create-partner-spinner aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php textArea(
            'description',
            'Brief Agreement profile / summary *',
            'A concise public-facing summary of this Agreement—not the partner profile.',
            4,
            'required'
        ); ?>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        2,
        'duration',
        'Duration, signing, and renewal',
        'Set each period once and distinguish delivery dates from legal activation.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-md-6">
            <span class="form-label d-block">Project duration *</span>
            <div class="date-range-pair" data-date-range-pair>
                <input id="start_date" name="start_date" type="text" class="form-control" required readonly aria-label="Project start date" placeholder="Select start" data-range-start>
                <span class="date-range-separator">to</span>
                <input id="end_date" name="end_date" type="text" class="form-control" required readonly aria-label="Project end date" placeholder="Select end" data-range-end>
            </div>
            <input id="project_duration" type="text" class="visually-hidden" tabindex="-1" aria-hidden="true" data-date-range data-start-target="start_date" data-end-target="end_date" data-range-trigger-start="start_date" data-range-trigger-end="end_date">
            <div class="form-text" data-project-duration-help>Project start is when cooperation activities begin; project end is when the planned delivery period finishes.</div>
            <div class="date-range-summary" data-project-duration-summary></div>
            <div class="invalid-feedback">Select both the project start and end dates.</div>
        </div>
        <?php field(
            'signing_date',
            'Signing date *',
            'date',
            'col-md-3',
            'required',
            'The date the authorized parties sign the approved instrument.'
        ); ?>
        <?php field(
            'effective_date',
            'Effective date *',
            'date',
            'col-md-3',
            'required',
            'The date the signed Agreement becomes operational/legal. It may be the signing date or a later date and is separate from activity start.'
        ); ?>

        <div class="col-12">
            <div class="renewal-choice">
                <div class="form-check form-switch">
                    <input id="auto_renew" name="auto_renew" class="form-check-input" type="checkbox" role="switch" data-auto-renew>
                    <label for="auto_renew" class="form-check-label fw-semibold">Automatically renewable</label>
                </div>
                <p class="small text-secondary mb-0">Enable only when the Agreement automatically begins another term unless a party gives notice.</p>
            </div>
        </div>
        <div class="col-12 d-none" data-renewal-fields>
            <div class="conditional-panel">
                <div class="row g-3">
                    <?php field('renewal_term_months', 'Each renewal term (months) *', 'number', 'col-md-6', 'min="1"'); ?>
                    <?php field(
                        'non_renewal_notice_months',
                        'Non-renewal notice (months) *',
                        'number',
                        'col-md-6',
                        'min="0"',
                        'Notice that stops the next automatic term; it does not end the current term early.'
                    ); ?>
                </div>
            </div>
        </div>
        <div class="col-12" data-fixed-term-fields>
            <div class="conditional-panel">
                <div class="row g-3">
                    <?php field(
                        'fixed_term_months',
                        'Agreement term (months) *',
                        'number',
                        'col-md-6',
                        'min="1" step="1" required',
                        'Required when the Agreement is not automatically renewable. It may be prefilled from the selected start and end dates and can be adjusted to the formal MOU term.'
                    ); ?>
                </div>
            </div>
        </div>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        3,
        'purpose',
        'Need, objectives, and impact',
        'Explain why the cooperation is needed and what value it will create.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <?php textArea('need_justification', 'Statement of need and justification *', '', 4, 'required'); ?>
        <?php textArea('objectives', 'Cooperation objectives *', 'Enter one objective per line where practical.', 4, 'required'); ?>
        <?php textArea('expected_value', 'Expected value and impact for the University *', '', 4, 'required'); ?>
        <?php textArea('focus_areas', 'Focus areas', 'Examples: research, academic collaboration, training, innovation, student exchange.'); ?>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        4,
        'commitments',
        'Commitments and resources',
        'Record only the commitments that apply to this Agreement.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-md-4"><div class="form-check"><input id="financial_commitments" name="financial_commitments" class="form-check-input" type="checkbox" data-toggle-section="financial-fields"><label for="financial_commitments" class="form-check-label">Financial commitments</label></div></div>
        <div class="col-md-8 row g-3 d-none" id="financial-fields">
            <?php field('financial_amount', 'Amount', 'number', 'col-md-4', 'min="0" step="0.01"'); ?>
            <?php field('financial_currency', 'Currency', 'text', 'col-md-3', 'maxlength="3" value="BHD"'); ?>
            <?php field('financial_description', 'Financial arrangement / description *', 'text', 'col-md-5'); ?>
        </div>
        <div class="col-md-4"><div class="form-check"><input id="human_resources_commitments" name="human_resources_commitments" class="form-check-input" type="checkbox" data-toggle-section="hr-fields"><label for="human_resources_commitments" class="form-check-label">Human-resources commitments</label></div></div>
        <div class="col-md-8 d-none" id="hr-fields"><?php field('human_resources_description', 'Human-resources description *', 'text', 'col-12'); ?></div>
        <div class="col-md-4"><div class="form-check"><input id="training_programs" name="training_programs" class="form-check-input" type="checkbox" data-toggle-section="training-fields"><label for="training_programs" class="form-check-label">Training programs</label></div></div>
        <div class="col-md-8 d-none" id="training-fields"><?php field('training_programs_description', 'Training program description *', 'text', 'col-12'); ?></div>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        5,
        'alignment',
        'Sustainable Development Goals',
        'Select only the goals supported by the planned work.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-12">
            <span class="form-label d-block">Supported Sustainable Development Goals</span>
            <p class="form-text mt-0">Hover or focus a goal for a brief explanation.</p>
            <div class="sdg-grid">
                <?php foreach ($sdgs as $number => [$title, $description]): ?>
                    <label
                        class="form-check sdg-option"
                        title="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                    >
                        <input class="form-check-input" type="checkbox" name="sdgs[]" value="<?= $number ?>">
                        <span class="form-check-label">
                            <strong>SDG <?= $number ?></strong>
                            <small><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        6,
        'governance',
        'Governance and MOU clauses',
        'Upload the required DOCX instrument; its clauses and named parties are extracted automatically for review.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-12">
            <div class="clause-upload-panel">
                <div>
                    <h3 class="h6 mb-1">Upload governance / MOU document</h3>
                    <p class="small text-secondary mb-0">The DOCX file is required. Extraction starts automatically and preserves the document’s Arabic or English language. Review every populated field before saving.</p>
                </div>
                <div class="row g-3 mt-1 align-items-end">
                    <div class="col-lg-8">
                        <label for="governance_document" class="form-label">Governance / MOU clauses file *</label>
                        <input id="governance_document" class="form-control" type="file" accept=".docx" required data-queued-upload data-document-type="GOVERNANCE_CLAUSES">
                        <div class="form-text">Use a text-based DOCX file, maximum 10 MB. It uploads securely after the draft is saved.</div>
                        <div class="invalid-feedback">Upload the governance / MOU clauses DOCX file.</div>
                    </div>
                    <div class="col-lg-4 d-grid">
                        <button type="button" class="btn btn-outline-primary" data-extract-clauses>
                            <span data-extract-label>Extract again</span>
                            <span class="spinner-border spinner-border-sm ms-2 d-none" data-extract-spinner aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="clause-extraction-feedback d-none" role="status" aria-live="polite" data-clause-feedback></div>
            </div>
        </div>
        <?php textArea('collaboration_areas', 'Fields of cooperation / MOU Article 1', 'Automatically suggested from the uploaded file when the article can be identified. This extracted field is optional and may be corrected by the creator.', 4); ?>
        <?php textArea('implementation_methods', 'Implementation methods / MOU Article 2', 'Automatically suggested from the uploaded file when the article can be identified. This extracted field is optional and may be corrected by the creator.', 4); ?>
        <div class="col-12">
            <div class="form-check"><input id="annual_report_required" name="annual_report_required" class="form-check-input" type="checkbox" checked><label for="annual_report_required" class="form-check-label">Annual joint performance report required</label></div>
        </div>
        <?php textArea('monitoring_plan', 'Monitoring, evaluation, obstacles, and reporting plan'); ?>
        <?php textArea('confidentiality_terms', 'Confidentiality and announcement terms'); ?>
        <?php textArea('intellectual_property_terms', 'Intellectual-property terms'); ?>
        <?php textArea('compliance_terms', 'National/international rights, obligations, laws, and regulations'); ?>
        <?php textArea('relationship_disclaimer', 'No partnership, joint venture, employment, or franchise disclaimer'); ?>
        <?php textArea('amendment_terms', 'How the Agreement may be amended'); ?>
        <?php textArea('dispute_resolution_terms', 'Dispute-resolution terms'); ?>
        <?php textArea('other_terms', 'Other agreed terms'); ?>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        7,
        'contacts',
        'Coordinators and signatories',
        'Review the coordinators and signatories extracted from the uploaded governance / MOU file; every field is required.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <?php foreach ([['UOB','COORDINATOR','UOB coordinator'],['PARTNER','COORDINATOR','Partner coordinator'],['UOB','SIGNATORY','UOB signatory'],['PARTNER','SIGNATORY','Partner signatory']] as $index => $contact): ?>
            <fieldset class="col-12 contact-row" data-contact-row data-party-type="<?= $contact[0] ?>" data-contact-role="<?= $contact[1] ?>">
                <legend class="h6 mb-3"><?= $contact[2] ?></legend><div class="row g-3">
                    <?php field('contact_' . $index . '_name', 'Full name *', 'text', 'col-md-3', 'required'); ?>
                    <?php field('contact_' . $index . '_title', 'Job title *', 'text', 'col-md-3', 'required'); ?>
                    <?php field('contact_' . $index . '_email', 'Email *', 'email', 'col-md-3', 'required'); ?>
                    <?php field('contact_' . $index . '_phone', 'Phone *', 'tel', 'col-md-3', 'required'); ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        8,
        'program',
        'Executive programmes',
        'Add one or more programmes. Use Agreement-based suggestions, then adjust every programme before saving.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-12">
            <div class="executive-suggestion-panel">
                <div>
                    <strong>Suggested from the Agreement</strong>
                    <p class="small text-secondary mb-0" data-program-suggestion-text>Complete the title, partners, objectives, and duration to generate useful programme suggestions.</p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-apply-program-suggestions>Apply to empty fields</button>
            </div>
            <div class="program-suggestion-list mt-3" data-program-suggestion-list></div>
            <div class="program-suggestion-feedback d-none mt-2" role="status" aria-live="polite" data-program-suggestion-feedback></div>
        </div>
        <div class="col-12">
            <div class="executive-program-list" data-program-list></div>
            <div class="invalid-feedback d-block d-none" data-program-error>Add and complete at least one executive programme.</div>
            <button type="button" class="btn btn-outline-primary mt-3" data-add-program>+ Add another programme</button>
        </div>
    </div></div>
    <?php sectionFooter(); ?>

    <template id="executive-program-template">
        <article class="executive-program-card" data-program-row>
            <div class="executive-program-card-header">
                <div>
                    <span class="eyebrow">Executive programme</span>
                    <h3 class="h6 mb-0" data-program-heading>Programme 1</h3>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-program>Remove</button>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" data-label-for="title">Proposed programme title *</label>
                    <input type="text" class="form-control" maxlength="255" required data-program-field="title">
                    <div class="invalid-feedback">Programme title is required.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" data-label-for="responsible_entity">Responsible implementing entity *</label>
                    <select class="form-select" required data-program-field="responsible_entity" data-program-responsible-entity>
                        <option value="">Select responsible entity</option>
                    </select>
                    <div class="invalid-feedback">Responsible entity is required.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" data-label-for="description">Brief programme description *</label>
                    <textarea class="form-control" rows="3" required data-program-field="description"></textarea>
                    <div class="invalid-feedback">Programme description is required.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" data-label-for="objectives">General programme objectives *</label>
                    <textarea class="form-control" rows="3" required data-program-field="objectives"></textarea>
                    <div class="invalid-feedback">Programme objectives are required.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" data-label-for="expected_outputs">Expected outputs and outcomes *</label>
                    <textarea class="form-control" rows="3" required data-program-field="expected_outputs"></textarea>
                    <div class="invalid-feedback">Expected outputs and outcomes are required.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" data-label-for="duration">Programme duration *</label>
                    <div class="date-range-pair" data-date-range-pair>
                        <input type="text" class="form-control" required readonly placeholder="Select start" aria-label="Programme start date" data-program-field="start_date">
                        <span class="date-range-separator">to</span>
                        <input type="text" class="form-control" required readonly placeholder="Select end" aria-label="Programme end date" data-program-field="end_date">
                    </div>
                    <input type="text" class="visually-hidden" tabindex="-1" aria-hidden="true" required data-date-range data-program-range data-start-target="" data-end-target="" data-range-trigger-start="" data-range-trigger-end="">
                    <div class="date-range-summary" data-range-summary></div>
                    <div class="invalid-feedback">Select both programme dates.</div>
                </div>
            </div>
        </article>
    </template>

    <?php sectionHeader(
        9,
        'outcomes',
        'Planned outcomes',
        'Set measurable targets for monitoring and annual reporting.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <?php foreach ([
            'STUDENTS_EXCHANGED' => 'Students exchanged',
            'TRAINED_STUDENTS' => 'Trained students',
            'FACULTY_EXCHANGED' => 'Faculty exchanged',
            'JOINT_PROGRAMS' => 'Joint programmes',
        ] as $code => $label): ?>
            <div class="col-12 metric-row" data-metric-row data-metric-code="<?= $code ?>"><div class="row g-3 align-items-end">
                <div class="col-md-3"><span class="form-label d-block mb-2"><?= $label ?></span></div>
                <?php field('metric_' . strtolower($code) . '_planned', 'Planned number *', 'number', 'col-md-2', 'min="0" required'); ?>
                <?php field('metric_' . strtolower($code) . '_actual', 'Actual number *', 'number', 'col-md-2', 'min="0" required'); ?>
                <?php field('metric_' . strtolower($code) . '_notes', 'Notes *', 'text', 'col-md-5', 'required'); ?>
            </div></div>
        <?php endforeach; ?>
    </div></div>
    <?php sectionFooter(); ?>

    <?php sectionHeader(
        10,
        'media',
        'Supporting media',
        'Attach optional photos, graphics, or video separately from Agreement outcomes.'
    ); ?>
    <div class="form-section"><div class="row g-4">
        <div class="col-12">
            <label for="agreement_media" class="form-label">Supporting media</label>
            <input id="agreement_media" class="form-control" type="file" accept=".jpg,.jpeg,.png,.webp,.mp4" multiple data-queued-upload data-document-type="MEDIA">
            <div class="form-text">Optional photos, graphics, or short MP4 video that reviewers or future reports may need. Each file must be 10 MB or smaller and uploads after the draft is saved.</div>
            <div class="queued-files" data-media-file-list></div>
        </div>
    </div></div>
    <?php sectionFooter(); ?>

    <div class="form-actions sticky-form-actions mt-4">
        <span class="small text-secondary me-auto" data-save-readiness>Required sections still need attention.</span>
        <a href="agreements.php" class="btn btn-outline-secondary" data-cancel-link>Cancel</a>
        <button id="save-agreement" class="btn btn-primary" type="submit">
            <span data-save-label>Save draft</span>
            <span class="spinner-border spinner-border-sm ms-2 d-none" data-save-spinner aria-hidden="true"></span>
        </button>
    </div>
</form>

<?php workspaceFooter([
    'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
    'assets/js/agreement-form.js?v=20260729-guided-form-v4',
]); ?>
