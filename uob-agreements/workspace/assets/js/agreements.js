(function () {
    'use strict';

    const state = {
        agreements: [],
        query: '',
        title: '',
        titleAr: '',
        type: '',
        partner: '',
        partnerType: '',
        partnerCountry: '',
        description: '',
        need: '',
        objectives: '',
        impact: '',
        focus: '',
        startFrom: '',
        startTo: '',
        endFrom: '',
        endTo: '',
        fixedTermMin: '',
        fixedTermMax: '',
        renewalTermMin: '',
        renewalTermMax: '',
        noticeMin: '',
        noticeMax: '',
        financial: '',
        humanResources: '',
        training: '',
        annualReport: '',
        autoRenew: '',
        financialMin: '',
        financialMax: '',
        currency: '',
        sdg: '',
        scope: 'ACTIVE',
        user: null,
        canCreateInitiative: false
    };

    const elements = {
        alert: document.getElementById('agreement-alert'),
        loading: document.getElementById('agreement-loading'),
        empty: document.getElementById('agreement-empty'),
        tableWrap: document.getElementById('agreement-table-wrap'),
        tableBody: document.getElementById('agreement-table-body'),
        search: document.getElementById('agreement-search'),
        title: document.getElementById('agreement-title'),
        titleAr: document.getElementById('agreement-title-ar'),
        type: document.getElementById('agreement-type'),
        partner: document.getElementById('agreement-partner'),
        partnerType: document.getElementById('agreement-partner-type'),
        partnerCountry: document.getElementById('agreement-partner-country'),
        descriptionFilter: document.getElementById('agreement-description'),
        need: document.getElementById('agreement-need'),
        objectives: document.getElementById('agreement-objectives'),
        impact: document.getElementById('agreement-impact'),
        focus: document.getElementById('agreement-focus'),
        startFrom: document.getElementById('agreement-start-from'),
        startTo: document.getElementById('agreement-start-to'),
        endFrom: document.getElementById('agreement-end-from'),
        endTo: document.getElementById('agreement-end-to'),
        fixedTermMin: document.getElementById('agreement-fixed-term-min'),
        fixedTermMax: document.getElementById('agreement-fixed-term-max'),
        renewalTermMin: document.getElementById('agreement-renewal-term-min'),
        renewalTermMax: document.getElementById('agreement-renewal-term-max'),
        noticeMin: document.getElementById('agreement-notice-min'),
        noticeMax: document.getElementById('agreement-notice-max'),
        financial: document.getElementById('agreement-financial'),
        humanResources: document.getElementById('agreement-human-resources'),
        training: document.getElementById('agreement-training'),
        annualReport: document.getElementById('agreement-annual-report'),
        autoRenew: document.getElementById('agreement-auto-renew'),
        financialMin: document.getElementById('agreement-financial-min'),
        financialMax: document.getElementById('agreement-financial-max'),
        currency: document.getElementById('agreement-currency'),
        sdg: document.getElementById('agreement-sdg'),
        clearFilters: document.querySelector('[data-clear-agreement-filters]'),
        summary: document.querySelector('[data-result-summary]'),
        create: document.querySelector('[data-create-agreement]'),
        description: document.querySelector('[data-agreement-page-description]'),
        facultyNote: document.querySelector('[data-faculty-agreement-note]'),
        scopeButtons: [...document.querySelectorAll('[data-agreement-scope]')]
    };

    const arrayValues = (records, field) => (Array.isArray(records) ? records : [])
        .map((record) => record?.[field])
        .filter((value) => value !== null && value !== undefined && value !== '');
    const booleanOptions = [['true', 'Yes'], ['false', 'No']];
    const projectTypeOptions = [
        'Cooperation Framework',
        'Memorandum of Understanding',
        'Cooperation Agreement',
        'Research Agreement',
        'Other'
    ];
    const partnerTypeOptions = [
        ['PUBLIC_GOVERNMENT', 'Public / government'],
        ['PRIVATE', 'Private'],
        ['ACADEMIC', 'Academic'],
        ['NON_PROFIT', 'Non-profit']
    ];
    const metricOptions = [
        ['STUDENTS_EXCHANGED', 'Students exchanged'],
        ['TRAINED_STUDENTS', 'Trained students'],
        ['FACULTY_EXCHANGED', 'Faculty exchanged'],
        ['JOINT_PROGRAMS', 'Joint programmes']
    ];
    const searchFieldGroups = {
        'Agreement and partner': [
            'title_en', 'title_ar', 'project_type', 'partner', 'partner_type',
            'partner_country', 'partner_website', 'partner_profile', 'description'
        ],
        'Dates and renewal': [
            'start_date', 'end_date', 'auto_renew', 'fixed_term',
            'renewal_term', 'renewal_notice'
        ],
        'Purpose and impact': ['need', 'objectives', 'impact', 'focus'],
        'Resources and SDGs': [
            'financial_commitment', 'financial_amount', 'currency',
            'financial_description', 'hr_commitment', 'hr_description',
            'training_included', 'training_description', 'sdg'
        ],
        'MOU clauses': [
            'cooperation', 'implementation', 'annual_report', 'monitoring',
            'confidentiality', 'intellectual_property', 'compliance',
            'relationship_disclaimer', 'amendment', 'dispute', 'other_terms'
        ],
        'People': ['contact_name', 'contact_title', 'contact_email', 'contact_phone'],
        'Executive programmes': [
            'programme_title', 'programme_entity', 'programme_description',
            'programme_objectives', 'programme_outputs', 'programme_start',
            'programme_end'
        ],
        'Planned outcomes': ['metric', 'metric_planned', 'metric_actual', 'metric_notes']
    };

    function searchFieldGroup(key) {
        return Object.entries(searchFieldGroups).find(([, keys]) => keys.includes(key))?.[0] || '';
    }

    const searchSchema = {
        any: (agreement) => [
            agreement.title, agreement.title_ar, agreement.agreement_type,
            agreement.description, agreement.start_date, agreement.end_date,
            agreement.fixed_term_months, agreement.renewal_term_months,
            agreement.non_renewal_notice_months, agreement.need_justification,
            agreement.objectives, agreement.expected_value, agreement.focus_areas,
            agreement.collaboration_areas, agreement.implementation_methods,
            agreement.financial_amount, agreement.financial_currency,
            agreement.financial_description, agreement.human_resources_description,
            agreement.training_programs_description, agreement.monitoring_plan,
            agreement.confidentiality_terms, agreement.intellectual_property_terms,
            agreement.compliance_terms, agreement.relationship_disclaimer,
            agreement.amendment_terms, agreement.dispute_resolution_terms,
            agreement.other_terms, ...agreement.partner_names,
            ...agreement.partner_types, ...agreement.partner_countries,
            ...agreement.partner_websites, ...agreement.partner_profiles,
            ...agreement.sdgs, ...arrayValues(agreement.contacts, 'full_name'),
            ...arrayValues(agreement.contacts, 'job_title'),
            ...arrayValues(agreement.contacts, 'email'),
            ...arrayValues(agreement.contacts, 'phone'),
            ...arrayValues(agreement.executive_programs, 'title'),
            ...arrayValues(agreement.executive_programs, 'responsible_entity'),
            ...arrayValues(agreement.executive_programs, 'description'),
            ...arrayValues(agreement.executive_programs, 'objectives'),
            ...arrayValues(agreement.executive_programs, 'expected_outputs'),
            ...arrayValues(agreement.metrics, 'metric_code'),
            ...arrayValues(agreement.metrics, 'planned_value'),
            ...arrayValues(agreement.metrics, 'actual_value'),
            ...arrayValues(agreement.metrics, 'notes')
        ],
        title: (agreement) => [agreement.title, agreement.title_ar],
        title_en: 'title',
        title_ar: 'title_ar',
        project_type: 'agreement_type',
        partner: (agreement) => agreement.partner_names,
        partner_type: (agreement) => agreement.partner_types,
        partner_country: (agreement) => agreement.partner_countries,
        partner_website: (agreement) => agreement.partner_websites,
        partner_profile: (agreement) => agreement.partner_profiles,
        description: 'description',
        start_date: 'start_date',
        end_date: 'end_date',
        auto_renew: 'auto_renew',
        fixed_term: 'fixed_term_months',
        renewal_term: 'renewal_term_months',
        renewal_notice: 'non_renewal_notice_months',
        need: 'need_justification',
        objectives: 'objectives',
        impact: 'expected_value',
        focus: 'focus_areas',
        financial_commitment: 'financial_commitments',
        financial_amount: 'financial_amount',
        currency: 'financial_currency',
        financial_description: 'financial_description',
        hr_commitment: 'human_resources_commitments',
        hr_description: 'human_resources_description',
        training_included: 'training_programs',
        training_description: 'training_programs_description',
        sdg: (agreement) => agreement.sdgs,
        cooperation: 'collaboration_areas',
        implementation: 'implementation_methods',
        annual_report: 'annual_report_required',
        monitoring: 'monitoring_plan',
        confidentiality: 'confidentiality_terms',
        intellectual_property: 'intellectual_property_terms',
        compliance: 'compliance_terms',
        relationship_disclaimer: 'relationship_disclaimer',
        amendment: 'amendment_terms',
        dispute: 'dispute_resolution_terms',
        other_terms: 'other_terms',
        contact_name: (agreement) => arrayValues(agreement.contacts, 'full_name'),
        contact_title: (agreement) => arrayValues(agreement.contacts, 'job_title'),
        contact_email: (agreement) => arrayValues(agreement.contacts, 'email'),
        contact_phone: (agreement) => arrayValues(agreement.contacts, 'phone'),
        programme_title: (agreement) => arrayValues(agreement.executive_programs, 'title'),
        programme_entity: (agreement) => arrayValues(agreement.executive_programs, 'responsible_entity'),
        programme_description: (agreement) => arrayValues(agreement.executive_programs, 'description'),
        programme_objectives: (agreement) => arrayValues(agreement.executive_programs, 'objectives'),
        programme_outputs: (agreement) => arrayValues(agreement.executive_programs, 'expected_outputs'),
        programme_start: (agreement) => arrayValues(agreement.executive_programs, 'start_date'),
        programme_end: (agreement) => arrayValues(agreement.executive_programs, 'end_date'),
        metric: (agreement) => arrayValues(agreement.metrics, 'metric_code'),
        metric_planned: (agreement) => arrayValues(agreement.metrics, 'planned_value'),
        metric_actual: (agreement) => arrayValues(agreement.metrics, 'actual_value'),
        metric_notes: (agreement) => arrayValues(agreement.metrics, 'notes')
    };

    const advancedFilters = UobAdvancedSearch.createFilterController({
        root: '[data-agreement-search-filters]',
        schema: searchSchema,
        fields: [
            { key: 'title_en', label: 'Agreement title (English)' },
            { key: 'title_ar', label: 'Agreement title (Arabic)' },
            { key: 'project_type', label: 'Type of cooperative project', type: 'select', options: projectTypeOptions, placeholder: 'Choose a project type' },
            { key: 'partner', label: 'Partner organization' },
            { key: 'partner_type', label: 'Partner type', type: 'select', options: partnerTypeOptions, placeholder: 'Choose a partner type' },
            { key: 'partner_country', label: 'Partner country' },
            { key: 'partner_website', label: 'Partner website' },
            { key: 'partner_profile', label: 'Partner profile' },
            { key: 'description', label: 'Brief Agreement profile / summary' },
            { key: 'start_date', label: 'Project start date', type: 'date' },
            { key: 'end_date', label: 'Project end date', type: 'date' },
            { key: 'auto_renew', label: 'Automatically renewable', type: 'boolean', options: booleanOptions, placeholder: 'Choose yes or no' },
            { key: 'fixed_term', label: 'Agreement term (months)', type: 'number', min: 1, step: 1 },
            { key: 'renewal_term', label: 'Renewal term (months)', type: 'number', min: 1, step: 1 },
            { key: 'renewal_notice', label: 'Non-renewal notice (months)', type: 'number', min: 0, step: 1 },
            { key: 'need', label: 'Statement of need and justification' },
            { key: 'objectives', label: 'Cooperation objectives' },
            { key: 'impact', label: 'Expected value and impact' },
            { key: 'focus', label: 'Focus areas' },
            { key: 'financial_commitment', label: 'Financial commitments', type: 'boolean', options: booleanOptions, placeholder: 'Choose yes or no' },
            { key: 'financial_amount', label: 'Financial amount', type: 'number', min: 0, step: 0.01 },
            { key: 'currency', label: 'Financial currency' },
            { key: 'financial_description', label: 'Financial arrangement / description' },
            { key: 'hr_commitment', label: 'Human-resources commitments', type: 'boolean', options: booleanOptions, placeholder: 'Choose yes or no' },
            { key: 'hr_description', label: 'Human-resources description' },
            { key: 'training_included', label: 'Training programmes included', type: 'boolean', options: booleanOptions, placeholder: 'Choose yes or no' },
            { key: 'training_description', label: 'Training programme description' },
            { key: 'sdg', label: 'Sustainable Development Goal', type: 'select', options: Array.from({ length: 17 }, (_, index) => [String(index + 1), `SDG ${index + 1}`]), placeholder: 'Choose an SDG' },
            { key: 'cooperation', label: 'Fields of cooperation / MOU Article 1' },
            { key: 'implementation', label: 'Implementation methods / MOU Article 2' },
            { key: 'annual_report', label: 'Annual report required', type: 'boolean', options: booleanOptions, placeholder: 'Choose yes or no' },
            { key: 'monitoring', label: 'Monitoring, evaluation, and reporting plan' },
            { key: 'confidentiality', label: 'Confidentiality and announcement terms' },
            { key: 'intellectual_property', label: 'Intellectual-property terms' },
            { key: 'compliance', label: 'Rights, obligations, laws, and regulations' },
            { key: 'relationship_disclaimer', label: 'No partnership / joint-venture disclaimer' },
            { key: 'amendment', label: 'Amendment terms' },
            { key: 'dispute', label: 'Dispute-resolution terms' },
            { key: 'other_terms', label: 'Other agreed terms' },
            { key: 'contact_name', label: 'Coordinator or signatory name' },
            { key: 'contact_title', label: 'Coordinator or signatory job title' },
            { key: 'contact_email', label: 'Coordinator or signatory email' },
            { key: 'contact_phone', label: 'Coordinator or signatory phone' },
            { key: 'programme_title', label: 'Executive programme title' },
            { key: 'programme_entity', label: 'Programme responsible entity' },
            { key: 'programme_description', label: 'Programme description' },
            { key: 'programme_objectives', label: 'Programme objectives' },
            { key: 'programme_outputs', label: 'Programme expected outputs' },
            { key: 'programme_start', label: 'Programme start date', type: 'date' },
            { key: 'programme_end', label: 'Programme end date', type: 'date' },
            { key: 'metric', label: 'Planned outcome metric', type: 'select', options: metricOptions, placeholder: 'Choose an outcome metric' },
            { key: 'metric_planned', label: 'Planned outcome number', type: 'number', min: 0, step: 1 },
            { key: 'metric_actual', label: 'Actual outcome number', type: 'number', min: 0, step: 1 },
            { key: 'metric_notes', label: 'Outcome notes' }
        ].map((field) => ({ ...field, group: searchFieldGroup(field.key) })),
        onChange: render
    });

    function parseCollection(value) {
        if (Array.isArray(value)) return value;
        if (value && typeof value === 'object') return Object.values(value);
        if (typeof value !== 'string' || value.trim() === '') return [];
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function addUnique(target, value) {
        if (value !== null && value !== undefined && value !== '' && !target.includes(value)) {
            target.push(value);
        }
    }

    function normalizeRows(rows) {
        const byId = new Map();

        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const id = String(row.agreement_id);

            if (!byId.has(id)) {
                byId.set(id, {
                    ...row,
                    partner_ids: [],
                    partner_names: [],
                    partner_types: [],
                    partner_countries: [],
                    partner_websites: [],
                    partner_profiles: [],
                    sdgs: parseCollection(row.sdgs).map(String),
                    contacts: parseCollection(row.contacts),
                    executive_programs: parseCollection(row.executive_programs),
                    metrics: parseCollection(row.metrics)
                });
            }

            if (row.partner_id !== null && row.partner_id !== undefined) {
                const target = byId.get(id);
                const partnerId = String(row.partner_id);

                addUnique(target.partner_ids, partnerId);
                addUnique(target.partner_names, row.partner_name);
                addUnique(target.partner_types, row.partner_type);
                addUnique(target.partner_countries, row.partner_country);
                addUnique(target.partner_websites, row.partner_website);
                addUnique(target.partner_profiles, row.partner_profile);
            }
        });

        return Array.from(byId.values());
    }

    function filteredRows() {
        return state.agreements.filter((agreement) => {
            const isMine = Number(agreement.created_by) === Number(state.user?.user_id);
            const scopeMatches = state.scope === 'ALL'
                || (state.scope === 'ACTIVE' && agreement.status === 'ACTIVE')
                || (state.scope === 'MY_ACTIVE' && isMine && agreement.status === 'ACTIVE')
                || (state.scope === 'MINE' && isMine);
            const titleMatches = !state.title
                || UobAdvancedSearch.normalize(agreement.title).includes(
                    UobAdvancedSearch.normalize(state.title)
                )
                || UobAdvancedSearch.normalize(agreement.title_ar).includes(
                    UobAdvancedSearch.normalize(state.title)
                );
            const titleArMatches = textFilterMatches(agreement.title_ar, state.titleAr);
            const typeMatches = !state.type
                || agreement.agreement_type === state.type;
            const partnerMatches = !state.partner
                || agreement.partner_names.includes(state.partner);
            const partnerTypeMatches = !state.partnerType
                || agreement.partner_types.includes(state.partnerType);
            const partnerCountryMatches = !state.partnerCountry
                || agreement.partner_countries.includes(state.partnerCountry);
            const descriptionMatches = textFilterMatches(agreement.description, state.description);
            const needMatches = textFilterMatches(agreement.need_justification, state.need);
            const objectivesMatch = textFilterMatches(agreement.objectives, state.objectives);
            const impactMatches = textFilterMatches(agreement.expected_value, state.impact);
            const focusMatches = textFilterMatches(agreement.focus_areas, state.focus);
            const startMatches = UobAdvancedSearch.inDateRange(
                agreement.start_date,
                state.startFrom,
                state.startTo
            );
            const endMatches = UobAdvancedSearch.inDateRange(
                agreement.end_date,
                state.endFrom,
                state.endTo
            );
            const fixedTermMatches = numberRangeMatches(
                agreement.fixed_term_months, state.fixedTermMin, state.fixedTermMax
            );
            const renewalTermMatches = numberRangeMatches(
                agreement.renewal_term_months, state.renewalTermMin, state.renewalTermMax
            );
            const noticeMatches = numberRangeMatches(
                agreement.non_renewal_notice_months, state.noticeMin, state.noticeMax
            );
            const financialMatches = booleanFilterMatches(
                agreement.financial_commitments,
                state.financial
            );
            const annualReportMatches = booleanFilterMatches(
                agreement.annual_report_required,
                state.annualReport
            );
            const humanResourcesMatches = booleanFilterMatches(
                agreement.human_resources_commitments,
                state.humanResources
            );
            const trainingMatches = booleanFilterMatches(
                agreement.training_programs,
                state.training
            );
            const autoRenewMatches = booleanFilterMatches(
                agreement.auto_renew,
                state.autoRenew
            );
            const financialAmountMatches = numberRangeMatches(
                agreement.financial_amount, state.financialMin, state.financialMax
            );
            const currencyMatches = !state.currency
                || agreement.financial_currency === state.currency;
            const sdgMatches = !state.sdg || agreement.sdgs.includes(state.sdg);
            return scopeMatches
                && titleMatches
                && titleArMatches
                && typeMatches
                && partnerMatches
                && partnerTypeMatches
                && partnerCountryMatches
                && descriptionMatches
                && needMatches
                && objectivesMatch
                && impactMatches
                && focusMatches
                && startMatches
                && endMatches
                && fixedTermMatches
                && renewalTermMatches
                && noticeMatches
                && financialMatches
                && humanResourcesMatches
                && trainingMatches
                && annualReportMatches
                && autoRenewMatches
                && financialAmountMatches
                && currencyMatches
                && sdgMatches
                && UobAdvancedSearch.matches(
                    agreement,
                    state.query,
                    searchSchema
                )
                && advancedFilters.matchesRules(agreement);
        });
    }

    function textFilterMatches(value, filter) {
        return !filter || UobAdvancedSearch.normalize(value).includes(
            UobAdvancedSearch.normalize(filter)
        );
    }

    function numberRangeMatches(value, minimum, maximum) {
        if (minimum === '' && maximum === '') return true;
        if (value === null || value === undefined || value === '') return false;
        const number = Number(value);
        if (!Number.isFinite(number)) return false;
        if (minimum !== '' && number < Number(minimum)) return false;
        if (maximum !== '' && number > Number(maximum)) return false;
        return true;
    }

    function booleanFilterMatches(value, filter) {
        if (!filter) return true;
        const enabled = value === true
            || value === 1
            || value === '1'
            || String(value).toLowerCase() === 'true'
            || String(value).toLowerCase() === 't';
        return filter === 'YES' ? enabled : !enabled;
    }

    function scopeLabel() {
        return {
            ACTIVE: 'active University Agreements',
            MY_ACTIVE: 'active Agreements created by you',
            MINE: 'Agreements created by you',
            ALL: 'visible Agreements'
        }[state.scope] || 'Agreements';
    }

    function updateScopeCounts() {
        const mine = state.agreements.filter(
            (agreement) => Number(agreement.created_by) === Number(state.user?.user_id)
        );
        const counts = {
            ACTIVE: state.agreements.filter((agreement) => agreement.status === 'ACTIVE').length,
            MY_ACTIVE: mine.filter((agreement) => agreement.status === 'ACTIVE').length,
            MINE: mine.length,
            ALL: state.agreements.length
        };

        Object.entries(counts).forEach(([scope, value]) => {
            const target = document.querySelector(`[data-scope-count="${scope}"]`);
            if (target) target.textContent = String(value);
        });
    }

    function selectScope(scope) {
        state.scope = scope;
        elements.scopeButtons.forEach((button) => {
            const selected = button.dataset.agreementScope === scope;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        render();
    }

    function cell(text, className = '') {
        const td = document.createElement('td');
        td.textContent = text ?? '—';
        if (className) td.className = className;
        return td;
    }

    function secondaryText(text) {
        const small = document.createElement('span');
        small.className = 'agreement-cell-secondary';
        small.textContent = text || '—';
        return small;
    }

    function projectPeriod(agreement) {
        const start = AgreementApi.formatDate(agreement.start_date);
        const end = AgreementApi.formatDate(agreement.end_date);
        if ((!agreement.start_date && !agreement.end_date) || (start === '—' && end === '—')) {
            return 'Dates not set';
        }
        return `${start} – ${end}`;
    }

    function render() {
        const rows = filteredRows();
        elements.tableBody.replaceChildren();
        elements.summary.textContent = `${rows.length} ${scopeLabel()}`;

        elements.empty.classList.toggle('d-none', rows.length !== 0);
        elements.tableWrap.classList.toggle('d-none', rows.length === 0);
        advancedFilters.refresh();

        rows.forEach((agreement) => {
            const tr = document.createElement('tr');
            const detailUrl = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;

            const agreementCell = document.createElement('td');
            agreementCell.className = 'agreement-primary-cell';
            agreementCell.dataset.label = 'Agreement';
            const titleLink = document.createElement('a');
            titleLink.className = 'agreement-register-title';
            titleLink.href = detailUrl;
            titleLink.textContent = agreement.title || `Agreement #${agreement.agreement_id}`;
            agreementCell.append(titleLink);
            if (agreement.title_ar) agreementCell.appendChild(secondaryText(agreement.title_ar));
            tr.appendChild(agreementCell);

            const partnerCell = document.createElement('td');
            partnerCell.dataset.label = 'Partner organization';
            const partnerName = document.createElement('span');
            partnerName.className = 'agreement-cell-primary';
            partnerName.textContent = agreement.partner_names.join(', ') || 'Partner not recorded';
            partnerCell.append(partnerName);
            if (agreement.partner_countries.length > 0) {
                partnerCell.appendChild(secondaryText(agreement.partner_countries.join(', ')));
            }
            tr.appendChild(partnerCell);

            const typeCell = document.createElement('td');
            typeCell.dataset.label = 'Project type';
            const type = document.createElement('span');
            type.className = 'agreement-cell-primary';
            type.textContent = agreement.agreement_type || 'Type not set';
            typeCell.append(type);
            tr.appendChild(typeCell);

            const periodCell = cell(projectPeriod(agreement), 'agreement-period-cell');
            periodCell.dataset.label = 'Project period';
            tr.appendChild(periodCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            actionCell.dataset.label = 'Actions';
            const actions = document.createElement('div');
            actions.className = 'agreement-row-actions';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = detailUrl;
            link.textContent = 'View';
            actions.appendChild(link);

            if (state.canCreateInitiative && agreement.status === 'ACTIVE') {
                const initiative = document.createElement('a');
                initiative.className = 'btn btn-sm btn-primary';
                initiative.href = '#';
                initiative.dataset.legacyInitiative = `request-initiative.php?lang=en&agreement_id=${encodeURIComponent(agreement.agreement_id)}&agreement_code=${encodeURIComponent(agreement.agreement_code || '')}`;
                initiative.textContent = 'Use for Initiative';
                actions.appendChild(initiative);
            }

            actionCell.appendChild(actions);
            tr.appendChild(actionCell);

            elements.tableBody.appendChild(tr);
        });
    }

    function loadSelectOptions(select, values, format = (value) => value) {
        Array.from(new Set(values.filter(Boolean))).sort((left, right) =>
            String(format(left)).localeCompare(String(format(right)))
        ).forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = format(value);
            select.appendChild(option);
        });
    }

    function loadFilterOptions() {
        loadSelectOptions(
            elements.type,
            state.agreements.map((agreement) => agreement.agreement_type)
        );
        loadSelectOptions(
            elements.partner,
            state.agreements.flatMap((agreement) => agreement.partner_names)
        );
        loadSelectOptions(
            elements.partnerCountry,
            state.agreements.flatMap((agreement) => agreement.partner_countries)
        );
        loadSelectOptions(
            elements.currency,
            state.agreements.map((agreement) => agreement.financial_currency)
        );
    }

    function setFiltersEnabled(enabled) {
        [
            elements.search,
            elements.title,
            elements.titleAr,
            elements.type,
            elements.partner,
            elements.partnerType,
            elements.partnerCountry,
            elements.descriptionFilter,
            elements.need,
            elements.objectives,
            elements.impact,
            elements.focus,
            elements.startFrom,
            elements.startTo,
            elements.endFrom,
            elements.endTo,
            elements.fixedTermMin,
            elements.fixedTermMax,
            elements.renewalTermMin,
            elements.renewalTermMax,
            elements.noticeMin,
            elements.noticeMax,
            elements.financial,
            elements.humanResources,
            elements.training,
            elements.annualReport,
            elements.autoRenew,
            elements.financialMin,
            elements.financialMax,
            elements.currency,
            elements.sdg,
            elements.clearFilters
        ].forEach((element) => {
            element.disabled = !enabled;
        });
    }

    function clearFilters() {
        state.query = '';
        state.title = '';
        state.titleAr = '';
        state.type = '';
        state.partner = '';
        state.partnerType = '';
        state.partnerCountry = '';
        state.description = '';
        state.need = '';
        state.objectives = '';
        state.impact = '';
        state.focus = '';
        state.startFrom = '';
        state.startTo = '';
        state.endFrom = '';
        state.endTo = '';
        state.fixedTermMin = '';
        state.fixedTermMax = '';
        state.renewalTermMin = '';
        state.renewalTermMax = '';
        state.noticeMin = '';
        state.noticeMax = '';
        state.financial = '';
        state.humanResources = '';
        state.training = '';
        state.annualReport = '';
        state.autoRenew = '';
        state.financialMin = '';
        state.financialMax = '';
        state.currency = '';
        state.sdg = '';
        elements.search.value = '';
        elements.title.value = '';
        elements.titleAr.value = '';
        elements.type.value = '';
        elements.partner.value = '';
        elements.partnerType.value = '';
        elements.partnerCountry.value = '';
        elements.descriptionFilter.value = '';
        elements.need.value = '';
        elements.objectives.value = '';
        elements.impact.value = '';
        elements.focus.value = '';
        elements.startFrom.value = '';
        elements.startTo.value = '';
        elements.endFrom.value = '';
        elements.endTo.value = '';
        elements.fixedTermMin.value = '';
        elements.fixedTermMax.value = '';
        elements.renewalTermMin.value = '';
        elements.renewalTermMax.value = '';
        elements.noticeMin.value = '';
        elements.noticeMax.value = '';
        elements.financial.value = '';
        elements.humanResources.value = '';
        elements.training.value = '';
        elements.annualReport.value = '';
        elements.autoRenew.value = '';
        elements.financialMin.value = '';
        elements.financialMax.value = '';
        elements.currency.value = '';
        elements.sdg.value = '';
        advancedFilters.clearRules();
        render();
    }

    function showError(error) {
        elements.loading.classList.add('d-none');
        elements.alert.textContent = error.message || 'Agreements could not be loaded.';
        elements.alert.classList.remove('d-none');
        elements.summary.textContent = 'Unable to load Agreements';
    }

    async function initialize() {
        try {
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');
            state.user = user;
            state.canCreateInitiative = AgreementApi.hasPermission(user, 'CREATE_INITIATIVE')
                || (Array.isArray(user.roles) && user.roles.includes('Initiative Creator'));
            const canCreateAgreement = AgreementApi.hasPermission(user, 'CREATE_AGREEMENT');
            elements.create.classList.toggle(
                'd-none',
                !canCreateAgreement
            );

            elements.scopeButtons.forEach((button) => {
                if (['MY_ACTIVE', 'MINE'].includes(button.dataset.agreementScope)) {
                    button.classList.toggle('d-none', !canCreateAgreement);
                }
            });
            elements.facultyNote.classList.toggle('d-none', !state.canCreateInitiative);
            if (state.canCreateInitiative && !canCreateAgreement) {
                elements.description.textContent = 'Explore active University partnerships, review their objectives, and choose one as the context for a new Initiative.';
            }

            const rows = await AgreementApi.agreements();
            state.agreements = normalizeRows(rows);

            updateScopeCounts();
            loadFilterOptions();
            setFiltersEnabled(true);
            elements.loading.classList.add('d-none');
            selectScope('ACTIVE');
        } catch (error) {
            showError(error);
        }
    }

    elements.search.addEventListener('input', () => {
        state.query = elements.search.value.trim();
        render();
    });

    elements.title.addEventListener('input', () => {
        state.title = elements.title.value.trim();
        render();
    });

    elements.titleAr.addEventListener('input', () => {
        state.titleAr = elements.titleAr.value.trim();
        render();
    });

    elements.type.addEventListener('change', () => {
        state.type = elements.type.value;
        render();
    });

    elements.partner.addEventListener('change', () => {
        state.partner = elements.partner.value;
        render();
    });

    elements.partnerType.addEventListener('change', () => {
        state.partnerType = elements.partnerType.value;
        render();
    });

    elements.partnerCountry.addEventListener('change', () => {
        state.partnerCountry = elements.partnerCountry.value;
        render();
    });

    elements.descriptionFilter.addEventListener('input', () => {
        state.description = elements.descriptionFilter.value.trim();
        render();
    });

    [
        [elements.need, 'need'],
        [elements.objectives, 'objectives'],
        [elements.impact, 'impact'],
        [elements.focus, 'focus']
    ].forEach(([element, key]) => {
        element.addEventListener('input', () => {
            state[key] = element.value.trim();
            render();
        });
    });

    elements.startFrom.addEventListener('change', () => {
        state.startFrom = elements.startFrom.value;
        render();
    });

    elements.startTo.addEventListener('change', () => {
        state.startTo = elements.startTo.value;
        render();
    });

    elements.endFrom.addEventListener('change', () => {
        state.endFrom = elements.endFrom.value;
        render();
    });

    elements.endTo.addEventListener('change', () => {
        state.endTo = elements.endTo.value;
        render();
    });

    [
        [elements.fixedTermMin, 'fixedTermMin'],
        [elements.fixedTermMax, 'fixedTermMax'],
        [elements.renewalTermMin, 'renewalTermMin'],
        [elements.renewalTermMax, 'renewalTermMax'],
        [elements.noticeMin, 'noticeMin'],
        [elements.noticeMax, 'noticeMax'],
        [elements.financialMin, 'financialMin'],
        [elements.financialMax, 'financialMax']
    ].forEach(([element, key]) => {
        element.addEventListener('input', () => {
            state[key] = element.value;
            render();
        });
    });

    elements.financial.addEventListener('change', () => {
        state.financial = elements.financial.value;
        render();
    });

    elements.humanResources.addEventListener('change', () => {
        state.humanResources = elements.humanResources.value;
        render();
    });

    elements.training.addEventListener('change', () => {
        state.training = elements.training.value;
        render();
    });

    elements.annualReport.addEventListener('change', () => {
        state.annualReport = elements.annualReport.value;
        render();
    });

    elements.autoRenew.addEventListener('change', () => {
        state.autoRenew = elements.autoRenew.value;
        render();
    });

    elements.currency.addEventListener('change', () => {
        state.currency = elements.currency.value;
        render();
    });

    elements.sdg.addEventListener('change', () => {
        state.sdg = elements.sdg.value;
        render();
    });

    elements.clearFilters.addEventListener('click', clearFilters);

    elements.scopeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectScope(button.dataset.agreementScope || 'ACTIVE');
        });
    });

    initialize();
})();
