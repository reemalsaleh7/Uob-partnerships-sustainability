(function () {
    'use strict';

    const form = document.getElementById('agreement-form');

    if (!form) {
        return;
    }

    const elements = {
        alert: document.getElementById('form-alert'),
        feedback: document.getElementById('form-feedback'),
        loading: document.getElementById('form-loading'),
        title: document.querySelector('[data-form-title]'),
        eyebrow: document.querySelector('[data-form-eyebrow]'),
        description: document.querySelector('[data-form-description]'),
        partners: document.getElementById('partner_ids'),
        partnerSearch: document.querySelector('[data-partner-search]'),
        partnerSearchCount: document.querySelector('[data-partner-search-count]'),
        partnerResults: document.querySelector('[data-partner-results]'),
        selectedPartners: document.querySelector('[data-selected-partners]'),
        selectedPartnerCount: document.querySelector('[data-selected-partner-count]'),
        partnerError: document.querySelector('[data-partner-error]'),
        newPartnerPanel: document.querySelector('[data-new-partner-panel]'),
        createPartner: document.querySelector('[data-create-partner]'),
        createPartnerLabel: document.querySelector('[data-create-partner-label]'),
        createPartnerSpinner: document.querySelector('[data-create-partner-spinner]'),
        partnerEditorTitle: document.querySelector('[data-partner-editor-title]'),
        partnerEditorHelp: document.querySelector('[data-partner-editor-help]'),
        derivedPartnerScope: document.querySelector('[data-derived-partner-scope]'),
        save: document.getElementById('save-agreement'),
        saveLabel: document.querySelector('[data-save-label]'),
        saveSpinner: document.querySelector('[data-save-spinner]'),
        saveReadiness: document.querySelector('[data-save-readiness]'),
        changeReasonSection: document.querySelector('[data-change-reason-section]'),
        changeSummary: document.getElementById('change_summary'),
        progressLabel: document.querySelector('[data-progress-label]'),
        progressBar: document.querySelector('[data-progress-bar]'),
        extractClauses: document.querySelector('[data-extract-clauses]'),
        extractLabel: document.querySelector('[data-extract-label]'),
        extractSpinner: document.querySelector('[data-extract-spinner]'),
        clauseFeedback: document.querySelector('[data-clause-feedback]'),
        programSuggestionText: document.querySelector('[data-program-suggestion-text]'),
        programSuggestionList: document.querySelector('[data-program-suggestion-list]'),
        programSuggestionFeedback: document.querySelector('[data-program-suggestion-feedback]'),
        mediaFileList: document.querySelector('[data-media-file-list]')
    };

    const state = {
        agreementId: null,
        isEdit: false,
        isRevision: false,
        initialized: false,
        user: null,
        partners: new Map(),
        selectedPartnerIds: new Set(),
        editingPartnerId: null,
        hasGovernanceDocument: false,
        autoAdvancedSections: new Set(),
        programSuggestions: {}
    };

    const scalarFields = [
        'title', 'title_ar', 'agreement_type', 'description',
        'start_date', 'end_date', 'effective_date', 'signing_date',
        'fixed_term_months', 'renewal_term_months', 'non_renewal_notice_months',
        'need_justification', 'expected_value',
        'objectives', 'focus_areas', 'collaboration_areas',
        'implementation_methods', 'financial_amount', 'financial_currency',
        'financial_description', 'human_resources_description',
        'training_programs_description', 'monitoring_plan',
        'confidentiality_terms', 'intellectual_property_terms',
        'compliance_terms', 'relationship_disclaimer', 'amendment_terms',
        'dispute_resolution_terms', 'other_terms'
    ];
    const booleanFields = [
        'auto_renew', 'financial_commitments', 'human_resources_commitments',
        'training_programs', 'annual_report_required'
    ];
    const sectionNodes = [...document.querySelectorAll('[data-form-section]')];

    function control(name) {
        return form.elements.namedItem(name);
    }

    function booleanValue(value) {
        return value === true || value === 1 || value === '1'
            || value === 't' || value === 'true';
    }

    function normalized(value) {
        return String(value || '').trim().toLocaleLowerCase();
    }

    function escapeSelector(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(String(value));
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function readAgreementId() {
        const value = new URLSearchParams(window.location.search).get('id');
        if (!value) return null;
        if (!/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError(
                'A valid Agreement ID is required.',
                422,
                null
            );
        }
        return value;
    }

    function setBusy(isBusy) {
        elements.save.disabled = isBusy;
        elements.saveLabel.textContent = isBusy
            ? 'Saving…'
            : (state.isRevision
                ? 'Save revised version'
                : (state.isEdit ? 'Save changes' : 'Save draft'));
        elements.saveSpinner.classList.toggle('d-none', !isBusy);
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }

    function showError(error) {
        elements.alert.textContent = error.message
            || 'The Agreement could not be saved.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function showFeedback(message) {
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
        elements.feedback.focus();
    }

    function setPartnerCreateBusy(isBusy) {
        elements.createPartner.disabled = isBusy;
        elements.createPartnerLabel.textContent = isBusy
            ? (state.editingPartnerId ? 'Saving changes…' : 'Adding partner…')
            : (state.editingPartnerId ? 'Save partner changes' : 'Add and select partner');
        elements.createPartnerSpinner.classList.toggle('d-none', !isBusy);
    }

    const partnerTypeLabels = Object.freeze({
        PUBLIC_GOVERNMENT: 'Public / government',
        PRIVATE: 'Private',
        ACADEMIC: 'Academic',
        NON_PROFIT: 'Non-profit'
    });

    function partnerTypeLabel(value) {
        return partnerTypeLabels[value] || value || 'Partner organization';
    }

    function partnerSearchText(partner) {
        return normalized([
            partner.organization_name,
            partnerTypeLabel(partner.partner_type),
            partner.country,
            partner.website,
            partner.profile
        ].filter(Boolean).join(' '));
    }

    function partnerResultNode(partner) {
        const selected = state.selectedPartnerIds.has(
            String(partner.partner_id)
        );
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `partner-result${selected ? ' is-selected' : ''}`;
        button.dataset.partnerId = String(partner.partner_id);
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', String(selected));

        const name = document.createElement('strong');
        name.textContent = partner.organization_name || 'Unnamed organization';
        const meta = document.createElement('span');
        meta.textContent = [
            partnerTypeLabel(partner.partner_type),
            partner.country || 'Country not recorded'
        ].filter(Boolean).join(' · ');
        const action = document.createElement('span');
        action.className = 'partner-result-action';
        action.textContent = selected ? 'Selected ✓' : 'Select';
        button.append(name, meta, action);

        return button;
    }

    function renderPartnerResults() {
        const query = normalized(elements.partnerSearch.value);
        const matches = [...state.partners.values()].filter((partner) =>
            query === '' || partnerSearchText(partner).includes(query)
        );

        elements.partnerResults.replaceChildren();
        matches.slice(0, 30).forEach((partner) => {
            elements.partnerResults.appendChild(partnerResultNode(partner));
        });

        if (matches.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'partner-empty mb-0';
            empty.textContent = 'No directory match. Add the partner using the button above.';
            elements.partnerResults.appendChild(empty);
        }

        elements.partnerSearchCount.textContent = matches.length > 30
            ? `Showing 30 of ${matches.length}`
            : `${matches.length} found`;
    }

    function partnerCardNode(partner) {
        const card = document.createElement('article');
        card.className = 'selected-partner-card';
        card.dataset.selectedPartnerId = String(partner.partner_id);

        const header = document.createElement('div');
        header.className = 'selected-partner-card-header';
        const heading = document.createElement('div');
        const name = document.createElement('strong');
        name.textContent = partner.organization_name || 'Unnamed organization';
        const type = document.createElement('span');
        type.textContent = partnerTypeLabel(partner.partner_type);
        heading.append(name, type);
        const actions = document.createElement('div');
        actions.className = 'selected-partner-actions';
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'btn btn-sm btn-outline-primary';
        edit.dataset.editPartner = String(partner.partner_id);
        edit.textContent = 'Edit details';
        edit.setAttribute(
            'aria-label',
            `Edit ${partner.organization_name || 'partner'} details`
        );
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-outline-danger';
        remove.dataset.removePartner = String(partner.partner_id);
        remove.textContent = 'Remove';
        remove.setAttribute(
            'aria-label',
            `Remove ${partner.organization_name || 'partner'}`
        );
        actions.append(edit, remove);
        header.append(heading, actions);

        const facts = document.createElement('dl');
        facts.className = 'partner-facts';
        [
            ['Country', partner.country || 'Not recorded'],
            ['Website', partner.website || 'Not recorded'],
            ['Profile', partner.profile || 'No brief profile recorded']
        ].forEach(([label, value]) => {
            const wrapper = document.createElement('div');
            const term = document.createElement('dt');
            term.textContent = label;
            const description = document.createElement('dd');
            let safeWebsite = null;
            if (label === 'Website' && partner.website) {
                try {
                    const parsed = new URL(partner.website);
                    if (['http:', 'https:'].includes(parsed.protocol)) {
                        safeWebsite = parsed.href;
                    }
                } catch (error) {
                    safeWebsite = null;
                }
            }
            if (safeWebsite) {
                const link = document.createElement('a');
                link.href = safeWebsite;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = partner.website;
                description.appendChild(link);
            } else {
                description.textContent = value;
            }
            wrapper.append(term, description);
            facts.appendChild(wrapper);
        });

        const warnings = [];
        if (!String(partner.country || '').trim()) {
            warnings.push(
                'Add a country so the Agreement scope can be determined.'
            );
        }
        if (!partnerTypeLabels[partner.partner_type]) {
            warnings.push(
                'Choose one of the four current partner organization types.'
            );
        }
        if (warnings.length > 0) {
            const warning = document.createElement('p');
            warning.className = 'partner-data-warning';
            warning.textContent = warnings.join(' ');
            card.append(header, facts, warning);
        } else {
            card.append(header, facts);
        }

        return card;
    }

    function syncPartnerSelect() {
        [...elements.partners.options].forEach((option) => {
            option.selected = state.selectedPartnerIds.has(option.value);
        });
    }

    function renderSelectedPartners() {
        elements.selectedPartners.replaceChildren();
        const selected = [...state.selectedPartnerIds]
            .map((id) => state.partners.get(id))
            .filter(Boolean);

        if (selected.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'partner-empty mb-0';
            empty.textContent = 'No partner selected yet.';
            elements.selectedPartners.appendChild(empty);
        } else {
            selected.forEach((partner) => {
                elements.selectedPartners.appendChild(partnerCardNode(partner));
            });
        }

        elements.selectedPartnerCount.textContent =
            `${selected.length} selected`;
        elements.derivedPartnerScope.textContent = derivedPartnerScopeLabel();
        elements.partnerError.classList.toggle(
            'd-none',
            selected.length !== 0
        );
        syncPartnerSelect();
        renderPartnerResults();
        refreshProgramSuggestions();
        updateAllSectionStatuses();
    }

    function selectPartner(partnerId, selected = true) {
        const id = String(partnerId);
        if (!state.partners.has(id)) {
            return;
        }

        if (selected) {
            state.selectedPartnerIds.add(id);
        } else {
            state.selectedPartnerIds.delete(id);
        }
        renderSelectedPartners();
    }

    function populatePartners(partners, selectedIds = []) {
        state.partners.clear();
        state.selectedPartnerIds = new Set((selectedIds || []).map(String));
        elements.partners.replaceChildren();

        (Array.isArray(partners) ? partners : []).forEach((partner) => {
            const id = String(partner.partner_id);
            state.partners.set(id, partner);
            const option = document.createElement('option');
            option.value = id;
            option.textContent = partner.organization_name || `Partner ${id}`;
            option.selected = state.selectedPartnerIds.has(id);
            elements.partners.appendChild(option);
        });

        renderSelectedPartners();
    }

    function selectedPartnersHaveRequiredCountries() {
        return [...state.selectedPartnerIds].every((id) =>
            Boolean(String(state.partners.get(id)?.country || '').trim())
        );
    }

    function selectedPartnersHaveRequiredTypes() {
        return [...state.selectedPartnerIds].every((id) =>
            Boolean(
                partnerTypeLabels[state.partners.get(id)?.partner_type]
            )
        );
    }

    function isBahrainCountry(country) {
        return /^(?:kingdom\s+of\s+)?bahrain$/iu.test(
            String(country || '').trim()
        );
    }

    function derivedPartnerScope() {
        const selected = [...state.selectedPartnerIds]
            .map((id) => state.partners.get(id))
            .filter(Boolean);
        if (
            selected.length === 0
            || selected.some((partner) => !String(partner.country || '').trim())
        ) {
            return '';
        }

        return selected.every((partner) => isBahrainCountry(partner.country))
            ? 'LOCAL'
            : 'INTERNATIONAL';
    }

    function derivedPartnerScopeLabel() {
        const scope = derivedPartnerScope();
        if (scope === 'LOCAL') return 'Local scope (derived from Bahrain)';
        if (scope === 'INTERNATIONAL') {
            return 'International scope (derived from partner countries)';
        }
        return 'Scope needs complete partner countries';
    }

    function validatePartnerSelection(showMessage = true) {
        let message = '';
        if (state.selectedPartnerIds.size === 0) {
            message = 'Select at least one partner organization.';
        } else if (!selectedPartnersHaveRequiredCountries()) {
            message = 'Every selected partner must have a country so local or international scope can be determined.';
        } else if (!selectedPartnersHaveRequiredTypes()) {
            message = 'Edit each selected partner and choose Public/government, Private, Academic, or Non-profit.';
        }

        if (showMessage) {
            elements.partnerError.textContent = message;
            elements.partnerError.classList.toggle('d-none', message === '');
        }

        return message === '';
    }

    function resetPartnerEditor() {
        state.editingPartnerId = null;
        ['new_partner_name', 'new_partner_type', 'new_partner_country',
            'new_partner_website', 'new_partner_profile'].forEach((name) => {
            control(name).value = '';
            control(name).classList.remove('is-invalid');
        });
        elements.partnerEditorTitle.textContent =
            'Add a partner to the University directory';
        elements.partnerEditorHelp.textContent =
            'The new profile becomes reusable in future Agreements. Selected profiles can also be corrected through their Edit details action.';
        setPartnerCreateBusy(false);
    }

    function openPartnerEditor(partner = null) {
        resetPartnerEditor();
        elements.newPartnerPanel.classList.remove('d-none');

        if (partner) {
            state.editingPartnerId = String(partner.partner_id);
            elements.partnerEditorTitle.textContent =
                'Edit partner directory details';
            elements.partnerEditorHelp.textContent =
                'Saving updates this shared University directory profile and is recorded in the audit log.';
            control('new_partner_name').value =
                partner.organization_name || '';
            control('new_partner_type').value =
                partnerTypeLabels[partner.partner_type]
                    ? partner.partner_type
                    : '';
            control('new_partner_country').value = partner.country || '';
            control('new_partner_website').value = partner.website || '';
            control('new_partner_profile').value = partner.profile || '';
            setPartnerCreateBusy(false);
        } else {
            control('new_partner_name').value =
                elements.partnerSearch.value.trim();
        }

        control('new_partner_name').focus();
    }

    async function savePartner() {
        const fields = {
            organization_name: control('new_partner_name').value.trim(),
            partner_type: control('new_partner_type').value,
            country: control('new_partner_country').value.trim(),
            website: control('new_partner_website').value.trim(),
            profile: control('new_partner_profile').value.trim()
        };
        const required = [
            control('new_partner_name'),
            control('new_partner_type'),
            control('new_partner_country')
        ];
        required.forEach((input) => {
            input.classList.toggle('is-invalid', !input.value.trim());
        });
        if (required.some((input) => !input.value.trim())) {
            required.find((input) => !input.value.trim())?.focus();
            return;
        }
        if (
            fields.website
            && !control('new_partner_website').checkValidity()
        ) {
            control('new_partner_website').classList.add('is-invalid');
            control('new_partner_website').focus();
            return;
        }

        clearMessages();
        setPartnerCreateBusy(true);
        try {
            const partner = state.editingPartnerId
                ? await AgreementApi.updatePartner(
                    state.editingPartnerId,
                    fields
                )
                : await AgreementApi.createPartner(fields);
            const id = String(partner.partner_id);
            state.partners.set(id, partner);
            if (!elements.partners.querySelector(
                `option[value="${escapeSelector(id)}"]`
            )) {
                const option = document.createElement('option');
                option.value = id;
                option.textContent = partner.organization_name;
                elements.partners.appendChild(option);
            }
            const wasEditing = Boolean(state.editingPartnerId);
            selectPartner(id, true);
            resetPartnerEditor();
            elements.newPartnerPanel.classList.add('d-none');
            elements.partnerSearch.value = '';
            showFeedback(wasEditing
                ? 'The shared partner directory profile was updated.'
                : (partner.already_existed
                    ? 'A matching partner already existed and has been selected.'
                    : 'The partner was added to the University directory and selected.'));
        } catch (error) {
            showError(error);
        } finally {
            setPartnerCreateBusy(false);
        }
    }

    function dateString(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function durationText(startValue, endValue) {
        if (!startValue || !endValue) {
            return '';
        }
        const start = new Date(`${startValue}T00:00:00`);
        const end = new Date(`${endValue}T00:00:00`);
        const days = Math.round((end - start) / 86400000) + 1;
        if (!Number.isFinite(days) || days <= 0) {
            return 'The end date must not be earlier than the start date.';
        }
        const months = Math.floor(days / 30.4375);
        const years = Math.floor(months / 12);
        const remainingMonths = months % 12;
        const parts = [];
        if (years) parts.push(`${years} year${years === 1 ? '' : 's'}`);
        if (remainingMonths) {
            parts.push(`${remainingMonths} month${remainingMonths === 1 ? '' : 's'}`);
        }
        return `Approximately ${parts.join(' and ') || `${days} days`}.`;
    }

    function durationMonths(startValue, endValue) {
        if (!startValue || !endValue) return '';
        const start = new Date(`${startValue}T00:00:00`);
        const end = new Date(`${endValue}T00:00:00`);
        const days = Math.round((end - start) / 86400000) + 1;
        if (!Number.isFinite(days) || days <= 0) return '';

        return String(Math.max(1, Math.round(days / 30.4375)));
    }

    function rangeSummaryElement(rangeInput) {
        return rangeInput.id === 'project_duration'
            ? document.querySelector('[data-project-duration-summary]')
            : document.querySelector('[data-program-duration-summary]');
    }

    function syncRangeSummary(rangeInput) {
        const start = control(rangeInput.dataset.startTarget).value;
        const end = control(rangeInput.dataset.endTarget).value;
        const summary = rangeSummaryElement(rangeInput);
        if (summary) {
            summary.textContent = durationText(start, end);
        }
        if (
            rangeInput.id === 'project_duration'
            && !control('auto_renew').checked
            && !control('fixed_term_months').value
        ) {
            control('fixed_term_months').value =
                durationMonths(start, end);
        }
        refreshProgramSuggestions();
        updateAllSectionStatuses();
    }

    function initializeDateRanges() {
        document.querySelectorAll('[data-date-range]').forEach((input) => {
            if (typeof window.flatpickr === 'function') {
                window.flatpickr(input, {
                    mode: 'range',
                    dateFormat: 'd M Y',
                    minDate: null,
                    disableMobile: true,
                    onChange(selectedDates) {
                        const startTarget = control(input.dataset.startTarget);
                        const endTarget = control(input.dataset.endTarget);
                        startTarget.value = selectedDates[0]
                            ? dateString(selectedDates[0])
                            : '';
                        endTarget.value = selectedDates[1]
                            ? dateString(selectedDates[1])
                            : '';
                        input.setCustomValidity(
                            input.required && selectedDates.length !== 2
                                ? 'Select both a start and an end date.'
                                : ''
                        );
                        syncRangeSummary(input);
                        if (selectedDates.length === 2) {
                            input.dispatchEvent(new Event('change', {
                                bubbles: true
                            }));
                        }
                    }
                });
            } else {
                input.readOnly = false;
                input.placeholder = 'YYYY-MM-DD to YYYY-MM-DD';
                input.addEventListener('change', () => {
                    const match = input.value.match(
                        /^(\d{4}-\d{2}-\d{2})\s+to\s+(\d{4}-\d{2}-\d{2})$/
                    );
                    control(input.dataset.startTarget).value =
                        match?.[1] || '';
                    control(input.dataset.endTarget).value =
                        match?.[2] || '';
                    input.setCustomValidity(
                        input.required && !match
                            ? 'Use YYYY-MM-DD to YYYY-MM-DD.'
                            : ''
                    );
                    syncRangeSummary(input);
                });
            }
        });
    }

    function setDateRange(rangeId, startValue, endValue) {
        const input = document.getElementById(rangeId);
        if (!input) return;
        control(input.dataset.startTarget).value = startValue || '';
        control(input.dataset.endTarget).value = endValue || '';
        if (input._flatpickr) {
            input._flatpickr.setDate(
                [startValue, endValue].filter(Boolean),
                false
            );
        } else if (startValue && endValue) {
            input.value = `${startValue} to ${endValue}`;
        }
        input.setCustomValidity(
            input.required && (!startValue || !endValue)
                ? 'Select both a start and an end date.'
                : ''
        );
        syncRangeSummary(input);
    }

    function populateAgreement(agreement) {
        scalarFields.forEach((name) => {
            if (control(name)) control(name).value = agreement[name] ?? '';
        });
        booleanFields.forEach((name) => {
            if (control(name)) {
                control(name).checked = booleanValue(agreement[name]);
            }
        });
        if (!agreement.financial_currency) {
            control('financial_currency').value = 'BHD';
        }
        document.querySelectorAll('input[name="sdgs[]"]').forEach((checkbox) => {
            checkbox.checked = (agreement.sdgs || []).map(Number)
                .includes(Number(checkbox.value));
        });

        (agreement.contacts || []).forEach((contact) => {
            const row = document.querySelector(
                `[data-contact-row][data-party-type="${escapeSelector(contact.party_type)}"][data-contact-role="${escapeSelector(contact.contact_role)}"]`
            );
            if (!row) return;
            const index = [...document.querySelectorAll('[data-contact-row]')]
                .indexOf(row);
            control(`contact_${index}_name`).value = contact.full_name || '';
            control(`contact_${index}_title`).value = contact.job_title || '';
            control(`contact_${index}_email`).value = contact.email || '';
            control(`contact_${index}_phone`).value = contact.phone || '';
        });

        const program = (agreement.executive_programs || [])[0];
        if (program) {
            ['title', 'description', 'objectives', 'expected_outputs',
                'responsible_entity', 'applicant_name'].forEach((name) => {
                const target = control(`program_${name}`);
                if (target) target.value = program[name] || '';
            });
            setDateRange(
                'program_duration',
                program.start_date || '',
                program.end_date || ''
            );
        }

        (agreement.metrics || []).forEach((metric) => {
            const prefix = `metric_${metric.metric_code.toLowerCase()}`;
            ['planned', 'actual', 'notes'].forEach((suffix) => {
                const sourceName = suffix === 'planned'
                    ? 'planned_value'
                    : (suffix === 'actual' ? 'actual_value' : 'notes');
                const target = control(`${prefix}_${suffix}`);
                if (target) target.value = metric[sourceName] ?? '';
            });
        });

        setDateRange(
            'project_duration',
            agreement.start_date || '',
            agreement.end_date || ''
        );
        syncConditionalSections();
    }

    function configureEditMode(agreement) {
        if (!['DRAFT', 'REVISION_REQUIRED'].includes(agreement.status)) {
            throw new AgreementApi.ApiError(
                'Only draft or returned Agreements can be edited from this screen.',
                409,
                null
            );
        }
        state.isRevision = agreement.status === 'REVISION_REQUIRED';
        elements.changeReasonSection.classList.remove('d-none');
        elements.changeSummary.required = true;
        elements.eyebrow.textContent = `Agreement #${agreement.agreement_id}`;
        elements.title.textContent = state.isRevision
            ? 'Revise returned Agreement'
            : 'Edit comprehensive Agreement';
        elements.description.textContent = state.isRevision
            ? 'Apply the requested changes. Saving creates a complete immutable version before resubmission.'
            : 'Update the Agreement record. Saving creates a complete immutable version snapshot.';
        document.querySelectorAll('[data-cancel-link]').forEach((link) => {
            link.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        });
    }

    function selectedValues(name) {
        return [...document.querySelectorAll(
            `input[name="${name}[]"]:checked`
        )].map((input) => input.value);
    }

    function contactsPayload() {
        return [...document.querySelectorAll('[data-contact-row]')]
            .map((row, index) => ({
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
            responsible_entity:
                control('program_responsible_entity').value.trim(),
            description: control('program_description').value.trim(),
            objectives: control('program_objectives').value.trim(),
            expected_outputs:
                control('program_expected_outputs').value.trim(),
            start_date: control('program_start_date').value,
            end_date: control('program_end_date').value,
            applicant_name: control('program_applicant_name').value.trim()
        }];
    }

    function metricsPayload() {
        return [...document.querySelectorAll('[data-metric-row]')]
            .map((row) => {
                const prefix =
                    `metric_${row.dataset.metricCode.toLowerCase()}`;
                return {
                    metric_code: row.dataset.metricCode,
                    planned_value: control(`${prefix}_planned`).value,
                    actual_value: control(`${prefix}_actual`).value,
                    notes: control(`${prefix}_notes`).value.trim()
                };
            }).filter((metric) =>
                metric.planned_value !== ''
                || metric.actual_value !== ''
                || metric.notes !== ''
            );
    }

    function payload() {
        const data = {};
        scalarFields.forEach((name) => {
            data[name] = control(name)?.value.trim() ?? '';
        });
        booleanFields.forEach((name) => {
            data[name] = Boolean(control(name)?.checked);
        });
        if (!data.auto_renew) {
            data.renewal_term_months = '';
            data.non_renewal_notice_months = '';
        } else {
            data.fixed_term_months = '';
        }
        data.partner_ids = [...state.selectedPartnerIds].map(Number);
        data.geographic_scope = derivedPartnerScope();
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
            const section = document.getElementById(
                checkbox.dataset.toggleSection
            );
            if (!section) return;
            section.classList.toggle('d-none', !checkbox.checked);
            section.querySelectorAll('input').forEach((input) => {
                if (
                    ['financial_description', 'human_resources_description',
                        'training_programs_description'].includes(input.id)
                ) {
                    input.required = checkbox.checked;
                }
            });
        });

        const autoRenew = control('auto_renew').checked;
        const renewalFields = document.querySelector('[data-renewal-fields]');
        const fixedTermFields =
            document.querySelector('[data-fixed-term-fields]');
        renewalFields.classList.toggle('d-none', !autoRenew);
        fixedTermFields.classList.toggle('d-none', autoRenew);
        ['renewal_term_months', 'non_renewal_notice_months'].forEach((name) => {
            control(name).required = autoRenew;
        });
        control('fixed_term_months').required = !autoRenew;
        renderSelectedPartners();
    }

    function setSectionOpen(section, isOpen) {
        const toggle = section.querySelector('[data-section-toggle]');
        const body = section.querySelector('[data-section-body]');
        toggle.setAttribute('aria-expanded', String(isOpen));
        body.hidden = !isOpen;
        section.classList.toggle('is-open', isOpen);
    }

    function sectionHasRequiredFields(section) {
        return section.querySelector('[required]') !== null
            || section.dataset.sectionNumber === '1';
    }

    function sectionHasMeaningfulValue(section) {
        if (
            section.dataset.sectionNumber === '1'
            && state.selectedPartnerIds.size > 0
        ) {
            return true;
        }
        return [...section.querySelectorAll('input, select, textarea')]
            .some((input) => {
                if (input.type === 'hidden' || input.type === 'file') {
                    return input.type === 'file'
                        && (input.files?.length || 0) > 0;
                }
                if (['checkbox', 'radio'].includes(input.type)) {
                    return input.checked;
                }
                return input.value.trim() !== '';
            });
    }

    function sectionIsComplete(section) {
        const required = [...section.querySelectorAll('[required]')]
            .filter((input) => !input.closest('.d-none'));
        const controlsValid = required.every((input) => input.checkValidity());
        if (section.dataset.sectionNumber === '1') {
            return controlsValid && validatePartnerSelection(false);
        }
        if (section.dataset.sectionNumber === '2') {
            return controlsValid
                && control('start_date').value !== ''
                && control('end_date').value !== '';
        }
        if (required.length > 0) {
            return controlsValid;
        }
        return sectionHasMeaningfulValue(section);
    }

    function updateSectionStatus(section) {
        const status = section.querySelector('[data-section-status]');
        const complete = sectionIsComplete(section);
        const started = sectionHasMeaningfulValue(section);
        const required = sectionHasRequiredFields(section);

        status.className = 'section-status';
        section.classList.remove('has-error', 'is-complete');
        if (complete) {
            status.textContent = 'Complete';
            status.classList.add('section-status-complete');
            section.classList.add('is-complete');
        } else if (required && started) {
            status.textContent = 'Needs attention';
            status.classList.add('section-status-error');
            if (section.querySelector('[data-section-body]').hidden) {
                section.classList.add('has-error');
            }
        } else if (required) {
            status.textContent = 'Not started';
            status.classList.add('section-status-pending');
        } else if (started) {
            status.textContent = 'In progress';
            status.classList.add('section-status-progress');
        } else {
            status.textContent = 'Optional';
            status.classList.add('section-status-optional');
        }
    }

    function updateAllSectionStatuses() {
        sectionNodes.forEach(updateSectionStatus);
        const completed = sectionNodes.filter(sectionIsComplete).length;
        const percent = Math.round((completed / sectionNodes.length) * 100);
        elements.progressLabel.textContent =
            `${completed} of ${sectionNodes.length} sections complete`;
        elements.progressBar.style.width = `${percent}%`;
        elements.progressBar.closest('[role="progressbar"]')
            .setAttribute('aria-valuenow', String(percent));

        const requiredComplete = sectionNodes
            .filter(sectionHasRequiredFields)
            .every(sectionIsComplete);
        elements.saveReadiness.textContent = requiredComplete
            ? 'Required sections are complete. You can save this draft.'
            : 'Required sections still need attention.';
    }

    function maybeAdvanceSection(section) {
        if (
            !state.initialized
            || !sectionHasRequiredFields(section)
            || !sectionIsComplete(section)
            || state.autoAdvancedSections.has(section.dataset.sectionNumber)
            || section.querySelector('[data-section-body]').hidden
        ) {
            return;
        }

        const next = sectionNodes[sectionNodes.indexOf(section) + 1];
        if (!next) return;
        state.autoAdvancedSections.add(section.dataset.sectionNumber);
        window.setTimeout(() => {
            setSectionOpen(section, false);
            setSectionOpen(next, true);
            next.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 350);
    }

    function firstInvalidSection() {
        return sectionNodes.find((section) =>
            sectionHasRequiredFields(section) && !sectionIsComplete(section)
        ) || null;
    }

    function validateForm() {
        let dateRangesValid = true;
        document.querySelectorAll('[data-date-range][required]').forEach((input) => {
            const start = control(input.dataset.startTarget).value;
            const end = control(input.dataset.endTarget).value;
            dateRangesValid = dateRangesValid && Boolean(start && end);
            input.setCustomValidity(
                start && end ? '' : 'Select both a start and an end date.'
            );
        });
        const partnersValid = validatePartnerSelection(true);
        const nativeValid = form.checkValidity();
        updateAllSectionStatuses();

        if (!nativeValid || !partnersValid || !dateRangesValid) {
            const invalidSection = firstInvalidSection();
            if (invalidSection) {
                setSectionOpen(invalidSection, true);
                invalidSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                window.setTimeout(() => {
                    invalidSection.querySelector(':invalid')?.focus();
                }, 400);
            }
            return false;
        }

        return true;
    }

    function applicantName(user) {
        return AgreementApi.displayName(user);
    }

    const programSuggestionLabels = Object.freeze({
        title: 'Programme title',
        responsible_entity: 'Responsible entity',
        description: 'Description',
        objectives: 'Objectives',
        expected_outputs: 'Expected outputs',
        start_date: 'Start date',
        end_date: 'End date',
        applicant_name: 'Applicant'
    });

    function renderProgramSuggestions() {
        elements.programSuggestionList.replaceChildren();
        Object.entries(programSuggestionLabels).forEach(([field, label]) => {
            const item = document.createElement('div');
            item.className = 'program-suggestion-item';
            const name = document.createElement('strong');
            name.textContent = label;
            const value = document.createElement('span');
            const suggestion = String(
                state.programSuggestions[field] || ''
            ).trim();
            value.textContent = suggestion || 'Complete more Agreement details';
            item.classList.toggle('is-missing', suggestion === '');
            item.append(name, value);
            elements.programSuggestionList.appendChild(item);
        });
    }

    function refreshProgramSuggestions() {
        if (!state.user) return;
        const agreementTitle = control('title').value.trim();
        const objectives = control('objectives').value.trim();
        const expectedValue = control('expected_value').value.trim();
        const focusAreas = control('focus_areas').value.trim();
        const partners = [...state.selectedPartnerIds]
            .map((id) => state.partners.get(id)?.organization_name)
            .filter(Boolean);
        const context = AgreementApi.primaryContext(state.user);

        state.programSuggestions = {
            title: agreementTitle
                ? `${agreementTitle} — Executive Programme`
                : '',
            responsible_entity: [context, partners.join(', ')]
                .filter(Boolean).join(' with '),
            description: agreementTitle
                ? `Implementation programme for ${agreementTitle}${partners.length ? ` with ${partners.join(', ')}` : ''}.${focusAreas ? ` Focus areas: ${focusAreas}.` : ''}`
                : '',
            objectives,
            expected_outputs: expectedValue,
            start_date: control('start_date').value,
            end_date: control('end_date').value,
            applicant_name: applicantName(state.user)
        };

        const readyCount = Object.values(state.programSuggestions)
            .filter((value) => String(value || '').trim() !== '').length;
        elements.programSuggestionText.textContent = readyCount === 8
            ? `Suggestions are ready for all programme fields from the Agreement title, ${partners.length} selected partner${partners.length === 1 ? '' : 's'}, objectives, impact, duration, and signed-in applicant. Existing programme text will not be overwritten.`
            : 'Complete the title, partners, objectives, impact, and duration to generate more useful programme suggestions.';
        renderProgramSuggestions();
    }

    function applyProgramSuggestions() {
        const mapping = {
            title: 'program_title',
            responsible_entity: 'program_responsible_entity',
            description: 'program_description',
            objectives: 'program_objectives',
            expected_outputs: 'program_expected_outputs',
            applicant_name: 'program_applicant_name'
        };
        Object.entries(mapping).forEach(([source, target]) => {
            if (!control(target).value.trim() && state.programSuggestions[source]) {
                control(target).value = state.programSuggestions[source];
            }
        });
        if (
            !control('program_start_date').value
            && state.programSuggestions.start_date
            && state.programSuggestions.end_date
        ) {
            setDateRange(
                'program_duration',
                state.programSuggestions.start_date,
                state.programSuggestions.end_date
            );
        }
        updateAllSectionStatuses();
        elements.programSuggestionFeedback.textContent =
            'Suggestions were applied only to empty executive-programme fields. Review and adjust them before saving.';
        elements.programSuggestionFeedback.classList.remove('d-none');
    }

    function queuedUploads() {
        const uploads = [];
        document.querySelectorAll('[data-queued-upload]').forEach((input) => {
            [...(input.files || [])].forEach((file) => {
                uploads.push({
                    file,
                    documentType: input.dataset.documentType || 'OTHER'
                });
            });
        });
        return uploads;
    }

    function validateQueuedUploads() {
        const allowed = {
            GOVERNANCE_CLAUSES: ['docx'],
            MEDIA: ['jpg', 'jpeg', 'png', 'webp', 'mp4']
        };
        for (const upload of queuedUploads()) {
            const extension = upload.file.name.split('.').pop()
                .toLocaleLowerCase();
            if (!allowed[upload.documentType]?.includes(extension)) {
                throw new AgreementApi.ApiError(
                    `${upload.file.name} is not a supported ${upload.documentType === 'MEDIA' ? 'media' : 'document'} file.`,
                    422,
                    null
                );
            }
            if (upload.file.size <= 0 || upload.file.size > 10485760) {
                throw new AgreementApi.ApiError(
                    `${upload.file.name} must be larger than 0 bytes and no more than 10 MB.`,
                    422,
                    null
                );
            }
        }
    }

    async function uploadQueuedFiles(agreementId) {
        const uploads = queuedUploads();
        for (let index = 0; index < uploads.length; index += 1) {
            const upload = uploads[index];
            elements.saveLabel.textContent =
                `Uploading ${index + 1} of ${uploads.length}…`;
            await AgreementApi.uploadDocument(
                agreementId,
                upload.file,
                upload.documentType
            );
        }
    }

    function renderMediaFileList() {
        const media = document.getElementById('agreement_media');
        const files = [...(media.files || [])];
        elements.mediaFileList.replaceChildren();
        files.forEach((file) => {
            const item = document.createElement('span');
            item.className = 'queued-file';
            item.textContent =
                `${file.name} · ${(file.size / 1048576).toFixed(1)} MB`;
            elements.mediaFileList.appendChild(item);
        });
        updateAllSectionStatuses();
    }

    async function extractClauses() {
        const file = document.getElementById('governance_document').files?.[0];
        if (!file) {
            showError(new AgreementApi.ApiError(
                'Choose a governance or MOU document first.',
                422,
                null
            ));
            return;
        }

        clearMessages();
        elements.extractClauses.disabled = true;
        elements.extractLabel.textContent = 'Extracting…';
        elements.extractSpinner.classList.remove('d-none');
        try {
            const result = await AgreementApi.extractAgreementClauses(file);
            let applied = 0;
            Object.entries(result.fields || {}).forEach(([name, value]) => {
                const target = control(name);
                if (target && !target.value.trim() && String(value).trim()) {
                    target.value = value;
                    target.lang = result.language || '';
                    target.dir = result.language === 'ar' ? 'rtl' : 'ltr';
                    applied += 1;
                }
            });
            let contactsApplied = 0;
            (result.contacts || []).forEach((contact) => {
                const row = document.querySelector(
                    `[data-contact-row][data-party-type="${escapeSelector(contact.party_type)}"][data-contact-role="${escapeSelector(contact.contact_role)}"]`
                );
                if (!row) return;
                const index = [...document.querySelectorAll('[data-contact-row]')]
                    .indexOf(row);
                const mapping = {
                    full_name: `contact_${index}_name`,
                    job_title: `contact_${index}_title`,
                    email: `contact_${index}_email`,
                    phone: `contact_${index}_phone`
                };
                Object.entries(mapping).forEach(([source, targetName]) => {
                    const target = control(targetName);
                    const value = String(contact[source] || '').trim();
                    if (target && !target.value.trim() && value) {
                        target.value = value;
                        if (['full_name', 'job_title'].includes(source)) {
                            target.lang = result.language || '';
                            target.dir =
                                result.language === 'ar' ? 'rtl' : 'ltr';
                        }
                        contactsApplied += 1;
                    }
                });
            });
            elements.clauseFeedback.textContent = result.extracted
                ? `${result.message} ${applied} empty clause field${applied === 1 ? '' : 's'} and ${contactsApplied} coordinator/signatory field${contactsApplied === 1 ? '' : 's'} populated.`
                : result.message;
            elements.clauseFeedback.classList.remove('d-none');
            updateAllSectionStatuses();
        } catch (error) {
            showError(error);
        } finally {
            elements.extractClauses.disabled = false;
            elements.extractLabel.textContent = 'Extract again';
            elements.extractSpinner.classList.add('d-none');
        }
    }

    async function initialize() {
        try {
            initializeDateRanges();
            state.agreementId = readAgreementId();
            state.isEdit = state.agreementId !== null;
            state.user = await AgreementApi.requireSession(
                state.isEdit ? 'EDIT_AGREEMENT' : 'CREATE_AGREEMENT'
            );
            const requests = [AgreementApi.partners()];
            if (state.isEdit) {
                requests.push(AgreementApi.agreement(state.agreementId));
                requests.push(AgreementApi.documents(state.agreementId));
            }
            const [
                partners,
                agreement = null,
                documents = []
            ] = await Promise.all(requests);

            if (
                agreement
                && Number(agreement.created_by) !== Number(state.user.user_id)
            ) {
                throw new AgreementApi.ApiError(
                    'Only the original Agreement creator may edit this Agreement.',
                    403,
                    null
                );
            }
            populatePartners(partners, agreement?.partner_ids || []);
            if (agreement) {
                configureEditMode(agreement);
                populateAgreement(agreement);
            }
            state.hasGovernanceDocument = (documents || []).some(
                (document) =>
                    document.document_type === 'GOVERNANCE_CLAUSES'
            );
            const governanceInput =
                document.getElementById('governance_document');
            governanceInput.required = !state.hasGovernanceDocument;
            if (state.hasGovernanceDocument) {
                elements.clauseFeedback.textContent =
                    'A governance / MOU clauses file is already attached. Choose a replacement only when the document has changed.';
                elements.clauseFeedback.classList.remove('d-none');
            }
            if (!control('program_applicant_name').value.trim()) {
                control('program_applicant_name').value =
                    applicantName(state.user);
            }
            syncConditionalSections();
            refreshProgramSuggestions();
            updateAllSectionStatuses();
            state.initialized = true;
            elements.loading.classList.add('d-none');
            form.classList.remove('d-none');
        } catch (error) {
            elements.loading.classList.add('d-none');
            showError(error);
        }
    }

    document.querySelectorAll('[data-section-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const section = button.closest('[data-form-section]');
            const isOpen = button.getAttribute('aria-expanded') === 'true';
            setSectionOpen(section, !isOpen);
            updateSectionStatus(section);
        });
    });

    document.querySelector('[data-expand-all]').addEventListener('click', () => {
        sectionNodes.forEach((section) => setSectionOpen(section, true));
    });
    document.querySelector('[data-collapse-all]').addEventListener('click', () => {
        sectionNodes.forEach((section) => setSectionOpen(section, false));
        updateAllSectionStatuses();
    });

    elements.partnerSearch.addEventListener('input', renderPartnerResults);
    elements.partnerResults.addEventListener('click', (event) => {
        const button = event.target.closest('[data-partner-id]');
        if (!button) return;
        const id = button.dataset.partnerId;
        selectPartner(id, !state.selectedPartnerIds.has(id));
    });
    elements.selectedPartners.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-edit-partner]');
        if (editButton) {
            openPartnerEditor(
                state.partners.get(editButton.dataset.editPartner) || null
            );
            return;
        }
        const button = event.target.closest('[data-remove-partner]');
        if (button) {
            selectPartner(button.dataset.removePartner, false);
        }
    });
    document.querySelector('[data-show-new-partner]').addEventListener('click', () => {
        openPartnerEditor();
    });
    document.querySelector('[data-close-new-partner]').addEventListener('click', () => {
        resetPartnerEditor();
        elements.newPartnerPanel.classList.add('d-none');
    });
    elements.createPartner.addEventListener('click', savePartner);

    document.querySelectorAll('[data-toggle-section], [data-auto-renew]')
        .forEach((checkbox) => {
            checkbox.addEventListener('change', syncConditionalSections);
        });

    document.querySelector('[data-apply-program-suggestions]')
        .addEventListener('click', applyProgramSuggestions);
    elements.extractClauses.addEventListener('click', extractClauses);
    document.getElementById('governance_document')
        .addEventListener('change', () => {
            state.hasGovernanceDocument = false;
            elements.clauseFeedback.classList.add('d-none');
            if (document.getElementById('governance_document').files?.length) {
                extractClauses();
            }
            updateAllSectionStatuses();
        });
    document.getElementById('agreement_media')
        .addEventListener('change', renderMediaFileList);

    form.addEventListener('input', (event) => {
        const section = event.target.closest('[data-form-section]');
        refreshProgramSuggestions();
        if (section) {
            updateSectionStatus(section);
        }
        updateAllSectionStatuses();
    });

    form.addEventListener('change', (event) => {
        const section = event.target.closest('[data-form-section]');
        if (section) {
            updateSectionStatus(section);
            maybeAdvanceSection(section);
        }
        updateAllSectionStatuses();
    });

    form.addEventListener('focusout', (event) => {
        const section = event.target.closest('[data-form-section]');
        if (section) {
            window.setTimeout(() => maybeAdvanceSection(section), 0);
        }
    });

    if (window.bootstrap?.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((node) => {
            new window.bootstrap.Tooltip(node);
        });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        clearMessages();
        form.classList.add('was-validated');
        if (!validateForm()) return;

        try {
            validateQueuedUploads();
        } catch (error) {
            showError(error);
            return;
        }

        setBusy(true);
        let savedAgreementId = state.agreementId;
        try {
            if (state.isEdit) {
                await AgreementApi.updateAgreement(
                    state.agreementId,
                    payload()
                );
            } else {
                const result = await AgreementApi.createAgreement(payload());
                savedAgreementId = result.agreement_id;
                state.agreementId = savedAgreementId;
            }

            await uploadQueuedFiles(savedAgreementId);
            const query = state.isRevision
                ? 'revised=1'
                : (state.isEdit ? 'updated=1' : 'created=1');
            window.location.assign(
                `agreement.php?id=${encodeURIComponent(savedAgreementId)}&${query}`
            );
        } catch (error) {
            if (!state.isEdit && savedAgreementId) {
                showError(new AgreementApi.ApiError(
                    `Agreement draft #${savedAgreementId} was saved, but a queued file could not be uploaded: ${error.message} Open the saved Agreement to retry the upload.`,
                    error.status || 500,
                    error.payload || null
                ));
                document.querySelectorAll('[data-cancel-link]').forEach((link) => {
                    link.href = `agreement.php?id=${encodeURIComponent(savedAgreementId)}`;
                    link.textContent = 'Open saved Agreement';
                });
                elements.save.disabled = true;
                elements.saveLabel.textContent = 'Draft saved';
            } else {
                showError(error);
                setBusy(false);
            }
        }
    });

    initialize();
})();
