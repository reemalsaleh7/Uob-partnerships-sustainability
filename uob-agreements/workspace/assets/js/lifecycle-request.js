(function () {
    'use strict';
    const elements = {
        alert: document.getElementById('lifecycle-detail-alert'),
        feedback: document.getElementById('lifecycle-detail-feedback'),
        loading: document.getElementById('lifecycle-detail-loading'),
        content: document.getElementById('lifecycle-detail-content'),
        fieldGroups: {
            request: document.querySelector('[data-request-fields="request"]'),
            change: document.querySelector('[data-request-fields="change"]'),
            financial: document.querySelector('[data-request-fields="financial"]')
        },
        versions: document.querySelector('[data-version-rows]'),
        edit: document.querySelector('[data-edit-request]'),
        submit: document.querySelector('[data-submit-request]'),
        submitLabel: document.querySelector('[data-submit-request-label]'),
        spinner: document.querySelector('[data-submit-request-spinner]'),
        successorSection: document.querySelector('[data-successor-section]'),
        successorLink: document.querySelector('[data-successor-link]')
    };
    const id = new URLSearchParams(window.location.search).get('id');
    let request = null;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-export-lifecycle]');
        if (!button || !request) return;
        WorkspaceExport.download(
            `lifecycle-request-${request.lifecycle_request_id}`,
            request,
            button.dataset.exportLifecycle,
            `${String(request.request_type || 'Lifecycle').replaceAll('_', ' ')} request`
        );
    });

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
            justification: ['Justification', 'request'],
            requester_name: ['Requested by', 'request'],
            submitted_at: ['Submitted', 'request'],
            decided_at: ['Decided', 'request'],
            decision_comments: ['Final comments', 'request'],
            activities_summary: ['Activities summary', 'change'],
            achieved_value: ['Achieved value', 'change'],
            proposed_start_date: ['Proposed start date', 'change'],
            proposed_end_date: ['Proposed end date', 'change'],
            amendment_type: ['Amendment type', 'change'],
            amendment_reason: ['Amendment reason', 'change'],
            terms_to_amend: ['Terms to amend', 'change'],
            termination_reason: ['Termination reason', 'change'],
            proposed_termination_date: ['Proposed termination date', 'change'],
            previous_initiatives: ['Previous initiatives', 'change'],
            financial_amount: ['Financial amount', 'financial'],
            financial_currency: ['Currency', 'financial'],
            financial_description: ['Financial description', 'financial']
        };
        Object.values(elements.fieldGroups).forEach((group) => group.replaceChildren());
        Object.entries(labels).forEach(([field, [label, group]]) => {
            let display = value[field];
            if (field === 'previous_initiatives' && display != null) {
                display = display === true || display === 't' || display === 'true' ? 'Yes' : 'No';
            }
            if (['created_at', 'submitted_at', 'decided_at'].includes(field)) display = AgreementApi.formatDate(display);
            if (display === null || display === '' || display === undefined) return;
            elements.fieldGroups[group].append(item(label, display));
        });
        Object.values(elements.fieldGroups).forEach((group) => {
            if (group.children.length) return;
            const empty = document.createElement('p');
            empty.className = 'text-secondary mb-0';
            empty.textContent = 'No information recorded for this group.';
            group.append(empty);
        });
        elements.versions.replaceChildren();
        versions.forEach((version) => {
            const tr = document.createElement('tr');
            [version.version_number, version.change_summary || 'Saved version', version.created_by, AgreementApi.formatDate(version.created_at)].forEach((entry) => {
                const td = document.createElement('td'); td.textContent = entry ?? '—'; tr.append(td);
            });
            elements.versions.append(tr);
        });
        const successorId = Number(value.successor_agreement_id || 0);
        elements.successorSection.classList.toggle('d-none', successorId <= 0);
        if (successorId > 0) {
            elements.successorLink.href = `agreement.php?id=${encodeURIComponent(successorId)}`;
        }
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
        if (!request || !await WorkspaceDialog.confirm('Submit this lifecycle request for formal review?', { confirmLabel: 'Submit request' })) return;
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
