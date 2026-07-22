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
    let agreementId = query.get('agreement_id');
    let request = null;

    function control(name) { return document.getElementById(name); }
    function showType(type) {
        document.querySelectorAll('[data-type-section]').forEach((section) => {
            section.classList.toggle('d-none', section.dataset.typeSection !== type);
        });
        control('request_type').disabled = Boolean(requestId);
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
    }
    function busy(value) {
        form.querySelector('button[type="submit"]').disabled = value;
        saveLabel.textContent = value ? 'Saving…' : (requestId ? 'Save revised draft' : 'Save draft');
        spinner.classList.toggle('d-none', !value);
    }

    async function initialize() {
        try {
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
            }
            document.querySelector('[data-agreement-title]').textContent = agreement.title;
            loading.classList.add('d-none');
            content.classList.remove('d-none');
        } catch (error) {
            loading.classList.add('d-none');
            alert.textContent = error.message || 'The lifecycle form could not be loaded.';
            alert.classList.remove('d-none');
            alert.focus();
        }
    }

    control('request_type').addEventListener('change', (event) => showType(event.target.value));
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        alert.classList.add('d-none');
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
