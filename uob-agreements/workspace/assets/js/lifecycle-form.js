(function () {
    'use strict';
    const fields = [
        'request_type', 'justification', 'activities_summary', 'achieved_value',
        'proposed_start_date', 'proposed_end_date', 'financial_amount',
        'financial_currency', 'financial_description', 'amendment_type',
        'amendment_reason', 'terms_to_amend', 'termination_reason',
        'proposed_termination_date', 'previous_initiatives', 'change_summary'
    ];
    const alert = document.getElementById('lifecycle-form-alert');
    const loading = document.getElementById('lifecycle-form-loading');
    const content = document.getElementById('lifecycle-form-content');
    const form = document.getElementById('lifecycle-request-form');
    const saveLabel = document.querySelector('[data-save-label]');
    const spinner = document.querySelector('[data-save-spinner]');
    const query = new URLSearchParams(window.location.search);
    const requestId = query.get('id');
    const requestedType = String(query.get('type') || '').toUpperCase();
    let agreementId = query.get('agreement_id');
    let request = null;
    let validationAttempted = false;
    const visited = new Set();

    function control(name) { return document.getElementById(name); }
    function activeSections() {
        return [...document.querySelectorAll('[data-lifecycle-section]')]
            .filter((section) => !section.classList.contains('d-none'));
    }
    function updateProgress() {
        const sections = activeSections();
        let completeCount = 0;
        sections.forEach((section) => {
            const number = section.dataset.lifecycleSection;
            const required = [...section.querySelectorAll('[required]')]
                .filter((input) => !input.disabled);
            const complete = required.length
                ? required.every((input) => input.checkValidity())
                : visited.has(number);
            const attention = !complete
                && required.length > 0
                && (validationAttempted || visited.has(number));
            if (complete) completeCount += 1;
            section.classList.toggle('is-complete', complete);
            section.classList.toggle('needs-attention', attention);
            const status = section.querySelector('[data-lifecycle-status]');
            if (status) {
                status.textContent = complete
                    ? 'Complete'
                    : (attention ? 'Needs attention' : (required.length ? 'Not started' : 'Open to review'));
            }
        });
        document.querySelector('[data-lifecycle-progress]').textContent =
            `${completeCount} of ${sections.length} sections complete`;
        document.querySelectorAll('[data-lifecycle-step]').forEach((step) => {
            const matching = sections.filter(
                (section) => section.dataset.lifecycleSection
                    === step.dataset.lifecycleStep
            );
            const complete = matching.length > 0
                && matching.every((section) => section.classList.contains('is-complete'));
            const attention = matching.some(
                (section) => section.classList.contains('needs-attention')
            );
            step.classList.toggle('is-complete', complete);
            step.classList.toggle('needs-attention', attention);
            step.querySelector('.agreement-step-marker').textContent =
                complete ? '✓' : step.dataset.lifecycleStep;
        });
    }
    function showType(type) {
        document.querySelectorAll('[data-type-section]').forEach((section) => {
            const visible = section.dataset.typeSection === type;
            section.classList.toggle('d-none', !visible);
            section.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !visible;
            });
        });
        control('request_type').disabled = Boolean(requestId);
        updateProgress();
    }
    function payload() {
        const data = {};
        fields.forEach((name) => {
            const element = control(name);
            if (!element) return;
            if (name === 'previous_initiatives') {
                data[name] = element.value === '' ? null : element.value === 'true';
            } else {
                data[name] = element.value.trim();
            }
        });
        return data;
    }
    function fill(value) {
        fields.forEach((name) => {
            const element = control(name);
            if (!element || value[name] == null) return;
            if (name === 'previous_initiatives') {
                element.value = String(value[name] === true || value[name] === 't' || value[name] === 'true');
            } else {
                element.value = value[name];
            }
        });
        showType(value.request_type);
        if (value.proposed_start_date && value.proposed_end_date) {
            setRenewalRange(
                value.proposed_start_date,
                value.proposed_end_date
            );
        }
    }
    function busy(value) {
        form.querySelector('button[type="submit"]').disabled = value;
        saveLabel.textContent = value ? 'Saving…' : (requestId ? 'Save revised draft' : 'Save draft');
        spinner.classList.toggle('d-none', !value);
    }

    function dateValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function setRenewalRange(start, end) {
        control('proposed_start_date').value = start || '';
        control('proposed_end_date').value = end || '';
        const range = control('renewal_duration');
        if (range?._flatpickr) {
            range._flatpickr.setDate([start, end].filter(Boolean), false);
        }
        range?.setCustomValidity(
            start && end ? '' : 'Select both proposed dates.'
        );
        updateProgress();
    }

    function initializeRenewalRange() {
        const range = control('renewal_duration');
        if (!range || typeof window.flatpickr !== 'function') return;
        window.flatpickr(range, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            disableMobile: true,
            onChange(dates) {
                setRenewalRange(
                    dates[0] ? dateValue(dates[0]) : '',
                    dates[1] ? dateValue(dates[1]) : ''
                );
            }
        });
        ['proposed_start_date', 'proposed_end_date'].forEach((id) => {
            control(id).addEventListener('click', () => range._flatpickr.open());
            control(id).addEventListener('keydown', (event) => {
                if (['Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    range._flatpickr.open();
                }
            });
        });
    }

    async function initialize() {
        try {
            initializeRenewalRange();
            await AgreementApi.requireSession(requestId ? 'EDIT_AGREEMENT' : 'CREATE_AGREEMENT');
            let agreement;
            if (requestId) {
                request = await AgreementApi.lifecycleRequest(requestId);
                agreementId = request.agreement_id;
                agreement = await AgreementApi.agreement(agreementId);
                if (!['DRAFT', 'REVISION_REQUIRED'].includes(request.status)) {
                    throw new AgreementApi.ApiError('This lifecycle request is not editable.', 403, null);
                }
                fill(request);
                document.querySelector('[data-form-title]').textContent = `Edit ${String(request.request_type).toLowerCase()} request`;
            } else {
                if (!agreementId || !/^\d+$/.test(agreementId)) {
                    throw new AgreementApi.ApiError('Open an approved Agreement before starting a lifecycle request.', 422, null);
                }
                agreement = await AgreementApi.agreement(agreementId);
                if (!['APPROVED', 'ACTIVE'].includes(agreement.status)) {
                    throw new AgreementApi.ApiError('Lifecycle requests require an approved or active Agreement.', 422, null);
                }
                if (
                    ['RENEWAL', 'AMENDMENT', 'TERMINATION']
                        .includes(requestedType)
                ) {
                    control('request_type').value = requestedType;
                    showType(requestedType);
                }
            }
            document.querySelector('[data-agreement-title]').textContent = agreement.title;
            loading.classList.add('d-none');
            content.classList.remove('d-none');
            updateProgress();
        } catch (error) {
            loading.classList.add('d-none');
            alert.textContent = error.message || 'The lifecycle form could not be loaded.';
            alert.classList.remove('d-none');
            alert.focus();
        }
    }

    control('request_type').addEventListener('change', (event) => showType(event.target.value));
    document.querySelectorAll('[data-lifecycle-section]').forEach((section) => {
        section.addEventListener('toggle', () => {
            if (section.open) visited.add(section.dataset.lifecycleSection);
            updateProgress();
        });
    });
    document.querySelector('[data-lifecycle-step-timeline], .lifecycle-step-timeline')
        ?.addEventListener('click', (event) => {
            const step = event.target.closest('[data-lifecycle-step]');
            if (!step) return;
            const section = activeSections().find(
                (candidate) => candidate.dataset.lifecycleSection
                    === step.dataset.lifecycleStep
            );
            if (!section) return;
            section.open = true;
            visited.add(section.dataset.lifecycleSection);
            updateProgress();
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    form.addEventListener('input', (event) => {
        const section = event.target.closest('[data-lifecycle-section]');
        if (section) visited.add(section.dataset.lifecycleSection);
        event.target.classList.remove('is-invalid');
        updateProgress();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        alert.classList.add('d-none');
        validationAttempted = true;
        updateProgress();
        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');
            const section = invalid?.closest('[data-lifecycle-section]');
            if (section) section.open = true;
            invalid?.classList.add('is-invalid');
            invalid?.focus();
            form.reportValidity();
            return;
        }
        busy(true);
        try {
            const result = requestId
                ? await AgreementApi.updateLifecycleRequest(requestId, payload())
                : await AgreementApi.createLifecycleRequest(agreementId, payload());
            const id = result.lifecycle_request_id || requestId;
            window.location.replace(`lifecycle-request.php?id=${encodeURIComponent(id)}&saved=1`);
        } catch (error) {
            alert.textContent = error.message || 'The lifecycle request could not be saved.';
            alert.classList.remove('d-none');
            alert.focus();
            busy(false);
        }
    });
    initialize();
})();
