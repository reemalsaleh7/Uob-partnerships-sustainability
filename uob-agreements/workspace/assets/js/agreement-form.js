(function () {
    'use strict';

    const form = document.getElementById('agreement-form');
    const elements = {
        alert: document.getElementById('form-alert'),
        loading: document.getElementById('form-loading'),
        title: document.querySelector('[data-form-title]'),
        eyebrow: document.querySelector('[data-form-eyebrow]'),
        description: document.querySelector('[data-form-description]'),
        partners: document.getElementById('partner_ids'),
        partnerHelp: document.querySelector('[data-partner-help]'),
        save: document.getElementById('save-agreement'),
        saveLabel: document.querySelector('[data-save-label]'),
        saveSpinner: document.querySelector('[data-save-spinner]'),
        changeReasonSection: document.querySelector('[data-change-reason-section]'),
        changeSummary: document.getElementById('change_summary')
    };

    const state = { agreementId: null, isEdit: false, isRevision: false };
    const scalarFields = [
        'title', 'title_ar', 'agreement_type', 'description', 'geographic_scope',
        'start_date', 'end_date', 'effective_date', 'signing_date',
        'renewal_term_months', 'non_renewal_notice_months',
        'termination_notice_months', 'need_justification', 'expected_value',
        'objectives', 'focus_areas', 'collaboration_areas',
        'implementation_methods', 'financial_amount', 'financial_currency',
        'financial_description', 'human_resources_description',
        'training_programs_description', 'monitoring_plan',
        'confidentiality_terms', 'intellectual_property_terms',
        'compliance_terms', 'relationship_disclaimer', 'amendment_terms',
        'dispute_resolution_terms', 'other_terms', 'legal_binding_status', 'signing_link'
    ];
    const booleanFields = [
        'auto_renew', 'financial_commitments', 'human_resources_commitments',
        'training_programs', 'annual_report_required'
    ];

    function control(name) {
        return form.elements.namedItem(name);
    }

    function booleanValue(value) {
        return value === true || value === 1 || value === '1' || value === 't' || value === 'true';
    }

    function readAgreementId() {
        const value = new URLSearchParams(window.location.search).get('id');
        if (!value) return null;
        if (!/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }
        return value;
    }

    function setBusy(isBusy) {
        elements.save.disabled = isBusy;
        elements.saveLabel.textContent = isBusy
            ? 'Saving…'
            : (state.isRevision ? 'Save revised version' : (state.isEdit ? 'Save changes' : 'Save draft'));
        elements.saveSpinner.classList.toggle('d-none', !isBusy);
    }

    function showError(error) {
        elements.alert.textContent = error.message || 'The Agreement could not be saved.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function populatePartners(partners, selectedIds = []) {
        const selected = new Set((selectedIds || []).map(String));
        (Array.isArray(partners) ? partners : []).forEach((partner) => {
            const option = document.createElement('option');
            option.value = String(partner.partner_id);
            option.textContent = [
                partner.organization_name,
                partner.partner_type,
                partner.country
            ].filter(Boolean).join(' — ');
            option.selected = selected.has(option.value);
            elements.partners.appendChild(option);
        });

        if (elements.partners.options.length === 0) {
            elements.partners.disabled = true;
            elements.partnerHelp.textContent = 'No active partners are available. Add a partner before creating an Agreement.';
            elements.save.disabled = true;
        }
    }

    function populateAgreement(agreement) {
        scalarFields.forEach((name) => {
            if (control(name)) control(name).value = agreement[name] ?? '';
        });
        booleanFields.forEach((name) => {
            if (control(name)) control(name).checked = booleanValue(agreement[name]);
        });
        if (!agreement.financial_currency) control('financial_currency').value = 'BHD';
        if (agreement.termination_notice_months == null) control('termination_notice_months').value = '6';

        document.querySelectorAll('input[name="rankings[]"]').forEach((checkbox) => {
            checkbox.checked = (agreement.rankings || []).includes(checkbox.value);
        });
        document.querySelectorAll('input[name="sdgs[]"]').forEach((checkbox) => {
            checkbox.checked = (agreement.sdgs || []).map(Number).includes(Number(checkbox.value));
        });

        (agreement.contacts || []).forEach((contact) => {
            const row = document.querySelector(
                `[data-contact-row][data-party-type="${contact.party_type}"][data-contact-role="${contact.contact_role}"]`
            );
            if (!row) return;
            const index = [...document.querySelectorAll('[data-contact-row]')].indexOf(row);
            control(`contact_${index}_name`).value = contact.full_name || '';
            control(`contact_${index}_title`).value = contact.job_title || '';
            control(`contact_${index}_email`).value = contact.email || '';
            control(`contact_${index}_phone`).value = contact.phone || '';
        });

        const program = (agreement.executive_programs || [])[0];
        if (program) {
            ['title', 'description', 'objectives', 'expected_outputs', 'start_date', 'end_date', 'responsible_entity', 'applicant_name'].forEach((name) => {
                const target = control(`program_${name}`);
                if (target) target.value = program[name] || '';
            });
        }

        (agreement.metrics || []).forEach((metric) => {
            const prefix = `metric_${metric.metric_code.toLowerCase()}`;
            ['planned', 'actual', 'notes'].forEach((suffix) => {
                const sourceName = suffix === 'planned' ? 'planned_value' : (suffix === 'actual' ? 'actual_value' : 'notes');
                const target = control(`${prefix}_${suffix}`);
                if (target) target.value = metric[sourceName] ?? '';
            });
        });

        syncConditionalSections();
    }

    function configureEditMode(agreement) {
        if (!['DRAFT', 'REVISION_REQUIRED'].includes(agreement.status)) {
            throw new AgreementApi.ApiError('Only draft or returned Agreements can be edited from this screen.', 409, null);
        }
        state.isRevision = agreement.status === 'REVISION_REQUIRED';
        elements.changeReasonSection.classList.remove('d-none');
        elements.changeSummary.required = true;
        elements.eyebrow.textContent = `Agreement #${agreement.agreement_id}`;
        elements.title.textContent = state.isRevision ? 'Revise returned Agreement' : 'Edit comprehensive Agreement';
        elements.description.textContent = state.isRevision
            ? 'Apply the requested changes. Saving creates a complete immutable version before resubmission.'
            : 'Update the Agreement record. Saving creates a complete immutable version snapshot.';
        document.querySelectorAll('[data-cancel-link]').forEach((link) => {
            link.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        });
    }

    function selectedValues(name) {
        return [...document.querySelectorAll(`input[name="${name}[]"]:checked`)].map((input) => input.value);
    }

    function contactsPayload() {
        return [...document.querySelectorAll('[data-contact-row]')].map((row, index) => ({
            party_type: row.dataset.partyType,
            contact_role: row.dataset.contactRole,
            full_name: control(`contact_${index}_name`).value.trim(),
            job_title: control(`contact_${index}_title`).value.trim(),
            email: control(`contact_${index}_email`).value.trim(),
            phone: control(`contact_${index}_phone`).value.trim(),
            is_primary: true
        })).filter((contact) => contact.full_name !== '');
    }

    function programsPayload() {
        const title = control('program_title').value.trim();
        if (!title) return [];
        return [{
            title,
            responsible_entity: control('program_responsible_entity').value.trim(),
            description: control('program_description').value.trim(),
            objectives: control('program_objectives').value.trim(),
            expected_outputs: control('program_expected_outputs').value.trim(),
            start_date: control('program_start_date').value,
            end_date: control('program_end_date').value,
            applicant_name: control('program_applicant_name').value.trim()
        }];
    }

    function metricsPayload() {
        return [...document.querySelectorAll('[data-metric-row]')].map((row) => {
            const prefix = `metric_${row.dataset.metricCode.toLowerCase()}`;
            return {
                metric_code: row.dataset.metricCode,
                planned_value: control(`${prefix}_planned`).value,
                actual_value: control(`${prefix}_actual`).value,
                notes: control(`${prefix}_notes`).value.trim()
            };
        }).filter((metric) => metric.planned_value !== '' || metric.actual_value !== '' || metric.notes !== '');
    }

    function payload() {
        const data = {};
        scalarFields.forEach((name) => { data[name] = control(name)?.value.trim() ?? ''; });
        booleanFields.forEach((name) => { data[name] = Boolean(control(name)?.checked); });
        data.partner_ids = [...elements.partners.selectedOptions].map((option) => Number(option.value));
        data.rankings = selectedValues('rankings');
        data.sdgs = selectedValues('sdgs').map(Number);
        data.contacts = contactsPayload();
        data.executive_programs = programsPayload();
        data.metrics = metricsPayload();
        data.change_summary = state.isEdit
            ? elements.changeSummary.value.trim()
            : undefined;
        return data;
    }

    function syncConditionalSections() {
        document.querySelectorAll('[data-toggle-section]').forEach((checkbox) => {
            const section = document.getElementById(checkbox.dataset.toggleSection);
            if (section) section.classList.toggle('d-none', !checkbox.checked);
        });
    }

    async function initialize() {
        try {
            state.agreementId = readAgreementId();
            state.isEdit = state.agreementId !== null;
            const user = await AgreementApi.requireSession(state.isEdit ? 'EDIT_AGREEMENT' : 'CREATE_AGREEMENT');
            const requests = [AgreementApi.partners()];
            if (state.isEdit) requests.push(AgreementApi.agreement(state.agreementId));
            const [partners, agreement = null] = await Promise.all(requests);

            if (agreement && Number(agreement.created_by) !== Number(user.user_id)) {
                throw new AgreementApi.ApiError('Only the original Agreement creator may edit this Agreement.', 403, null);
            }
            populatePartners(partners, agreement?.partner_ids || []);
            if (agreement) {
                configureEditMode(agreement);
                populateAgreement(agreement);
            }
            syncConditionalSections();
            elements.loading.classList.add('d-none');
            form.classList.remove('d-none');
        } catch (error) {
            elements.loading.classList.add('d-none');
            showError(error);
        }
    }

    document.querySelectorAll('[data-toggle-section]').forEach((checkbox) => {
        checkbox.addEventListener('change', syncConditionalSections);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        elements.alert.classList.add('d-none');
        form.classList.add('was-validated');
        if (!form.checkValidity()) return;
        setBusy(true);
        try {
            if (state.isEdit) {
                await AgreementApi.updateAgreement(state.agreementId, payload());
                window.location.assign(`agreement.php?id=${encodeURIComponent(state.agreementId)}&${state.isRevision ? 'revised=1' : 'updated=1'}`);
                return;
            }
            const result = await AgreementApi.createAgreement(payload());
            window.location.assign(`agreement.php?id=${encodeURIComponent(result.agreement_id)}&created=1`);
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    initialize();
})();
