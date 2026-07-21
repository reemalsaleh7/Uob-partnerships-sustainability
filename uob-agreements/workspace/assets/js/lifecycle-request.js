(function () {
    'use strict';
    const elements = {
        alert: document.getElementById('lifecycle-detail-alert'),
        feedback: document.getElementById('lifecycle-detail-feedback'),
        loading: document.getElementById('lifecycle-detail-loading'),
        content: document.getElementById('lifecycle-detail-content'),
        fields: document.querySelector('[data-request-fields]'),
        versions: document.querySelector('[data-version-rows]'),
        edit: document.querySelector('[data-edit-request]'),
        submit: document.querySelector('[data-submit-request]'),
        submitLabel: document.querySelector('[data-submit-request-label]'),
        spinner: document.querySelector('[data-submit-request-spinner]')
    };
    const id = new URLSearchParams(window.location.search).get('id');
    let request = null;

    function item(label, value) {
        const wrap = document.createElement('div');
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = value === null || value === '' || value === undefined ? '—' : value;
        wrap.append(dt, dd);
        return wrap;
    }
    function render(value, versions) {
        document.querySelector('[data-request-type]').textContent = String(value.request_type).replaceAll('_', ' ');
        document.querySelector('[data-request-id]').textContent = `#${value.lifecycle_request_id}`;
        document.querySelector('[data-agreement-title]').textContent = value.agreement_title;
        document.querySelector('[data-request-status]').replaceChildren(AgreementApi.createStatusBadge(value.status));
        const labels = {
            justification: 'Justification', activities_summary: 'Activities summary', achieved_value: 'Achieved value',
            proposed_start_date: 'Proposed start date', proposed_end_date: 'Proposed end date',
            financial_amount: 'Financial amount', financial_currency: 'Currency', financial_description: 'Financial description',
            amendment_type: 'Amendment type', amendment_reason: 'Amendment reason', terms_to_amend: 'Terms to amend',
            termination_reason: 'Termination reason', proposed_termination_date: 'Proposed termination date',
            previous_initiatives: 'Previous initiatives', requester_name: 'Requested by', submitted_at: 'Submitted', decided_at: 'Decided', decision_comments: 'Final comments'
        };
        elements.fields.replaceChildren();
        Object.entries(labels).forEach(([field, label]) => {
            let display = value[field];
            if (field === 'previous_initiatives' && display != null) {
                display = display === true || display === 't' || display === 'true' ? 'Yes' : 'No';
            }
            if (['created_at', 'submitted_at', 'decided_at'].includes(field)) display = AgreementApi.formatDate(display);
            elements.fields.append(item(label, display));
        });
        elements.versions.replaceChildren();
        versions.forEach((version) => {
            const tr = document.createElement('tr');
            [version.version_number, version.change_summary || 'Saved version', version.created_by, AgreementApi.formatDate(version.created_at)].forEach((entry) => {
                const td = document.createElement('td'); td.textContent = entry ?? '—'; tr.append(td);
            });
            elements.versions.append(tr);
        });
    }

    async function initialize() {
        try {
            if (!id || !/^\d+$/.test(id)) throw new AgreementApi.ApiError('A valid request ID is required.', 422, null);
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');
            const results = await Promise.all([AgreementApi.lifecycleRequest(id), AgreementApi.lifecycleVersions(id)]);
            request = results[0];
            render(request, results[1]);
            const owner = Number(request.requested_by) === Number(user.user_id);
            const editable = ['DRAFT', 'REVISION_REQUIRED'].includes(request.status);
            elements.edit.classList.toggle('d-none', !owner || !editable || !AgreementApi.hasPermission(user, 'EDIT_AGREEMENT'));
            elements.edit.href = `lifecycle-form.php?id=${encodeURIComponent(id)}`;
            elements.submit.classList.toggle('d-none', !owner || !editable || !AgreementApi.hasPermission(user, 'SUBMIT_AGREEMENT'));
            elements.submitLabel.textContent = request.status === 'REVISION_REQUIRED' ? 'Resubmit revised request' : 'Submit for review';
            if (new URLSearchParams(window.location.search).get('saved') === '1') {
                elements.feedback.textContent = 'Lifecycle request draft saved successfully.';
                elements.feedback.classList.remove('d-none');
            } else if (new URLSearchParams(window.location.search).get('submitted') === '1') {
                elements.feedback.textContent = 'Lifecycle request submitted for Initial VP review.';
                elements.feedback.classList.remove('d-none');
            }
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'The lifecycle request could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    }
    elements.submit.addEventListener('click', async () => {
        if (!request || !window.confirm('Submit this lifecycle request for formal review?')) return;
        elements.submit.disabled = true;
        elements.spinner.classList.remove('d-none');
        try {
            await AgreementApi.submitLifecycleRequest(id);
            window.location.replace(`lifecycle-request.php?id=${encodeURIComponent(id)}&submitted=1`);
        } catch (error) {
            elements.alert.textContent = error.message || 'The request could not be submitted.';
            elements.alert.classList.remove('d-none');
            elements.submit.disabled = false;
            elements.spinner.classList.add('d-none');
        }
    });
    initialize();
})();
