(function () {
    'use strict';
    const alert = document.getElementById('lifecycle-review-alert');
    const loading = document.getElementById('lifecycle-review-loading');
    const content = document.getElementById('lifecycle-review-content');
    const fields = document.querySelector('[data-review-fields]');
    const comments = document.getElementById('review_comments');
    const finance = document.getElementById('include_finance');
    const query = new URLSearchParams(window.location.search);
    const instanceId = query.get('instance_id');
    const requestId = query.get('request_id');
    let assignment = null;

    const labels = { VP_INITIAL: 'Initial VP review', LEGAL_REVIEW: 'Legal review', FINANCE_REVIEW: 'Finance review', VP_FINAL: 'Final VP review', PRESIDENT_APPROVAL: 'President approval' };
    function item(label, value) {
        const wrap = document.createElement('div'); const dt = document.createElement('dt'); const dd = document.createElement('dd');
        dt.textContent = label; dd.textContent = value || '—'; wrap.append(dt, dd); return wrap;
    }
    function render(request) {
        const mediation = assignment.task_mode === 'VP_MEDIATION';
        document.querySelector('[data-review-stage]').textContent = mediation ? 'VP mediation' : (labels[assignment.step_key] || 'Lifecycle review');
        document.querySelector('[data-request-type]').textContent = String(request.request_type).replaceAll('_', ' ');
        document.querySelector('[data-agreement-title]').textContent = request.agreement_title;
        document.querySelector('[data-request-id]').textContent = `#${request.lifecycle_request_id}`;
        document.querySelector('[data-instance-id]').textContent = `#${instanceId}`;
        document.querySelector('[data-open-request]').href = `lifecycle-request.php?id=${encodeURIComponent(requestId)}`;
        fields.replaceChildren(
            item('Justification', request.justification),
            item('Activities summary', request.activities_summary),
            item('Achieved value', request.achieved_value),
            item('Proposed dates', [request.proposed_start_date, request.proposed_end_date].filter(Boolean).join(' to ')),
            item('Amendment type', request.amendment_type),
            item('Amendment reason', request.amendment_reason),
            item('Terms to amend', request.terms_to_amend),
            item('Termination reason', request.termination_reason),
            item('Proposed termination', request.proposed_termination_date),
            item('Financial implications', [request.financial_amount, request.financial_currency, request.financial_description].filter(Boolean).join(' · ')),
            item('Change-request reason', assignment.change_request_reason)
        );
        document.querySelector('[data-finance-choice]').classList.toggle('d-none', assignment.step_key !== 'VP_INITIAL');
        document.querySelector('[data-mediation-note]').classList.toggle('d-none', !mediation);
        document.querySelector('[data-review-action="APPROVE"]').classList.toggle('d-none', mediation);
    }

    async function initialize() {
        try {
            if (!instanceId || !requestId || !/^\d+$/.test(instanceId) || !/^\d+$/.test(requestId)) throw new AgreementApi.ApiError('Valid workflow and request IDs are required.', 422, null);
            await AgreementApi.requireSession();
            const inbox = await AgreementApi.workflowInbox();
            assignment = inbox.find((row) => Number(row.workflow_instance_id) === Number(instanceId) && Number(row.lifecycle_request_id) === Number(requestId));
            if (!assignment || assignment.entity_type !== 'AGREEMENT_LIFECYCLE') throw new AgreementApi.ApiError('This lifecycle review is not assigned to you.', 403, null);
            const request = await AgreementApi.lifecycleRequest(requestId);
            render(request);
            loading.classList.add('d-none');
            content.classList.remove('d-none');
        } catch (error) {
            loading.classList.add('d-none'); alert.textContent = error.message || 'The lifecycle review could not be loaded.'; alert.classList.remove('d-none'); alert.focus();
        }
    }
    document.querySelectorAll('[data-review-action]').forEach((button) => {
        button.addEventListener('click', async () => {
            const action = button.dataset.reviewAction;
            if (action !== 'APPROVE' && comments.value.trim() === '') {
                alert.textContent = 'Enter a reason before returning or rejecting the request.'; alert.classList.remove('d-none'); comments.focus(); return;
            }
            document.querySelectorAll('[data-review-action]').forEach((item) => { item.disabled = true; });
            try {
                await AgreementApi.decideLifecycle(instanceId, { action, comments: comments.value.trim(), include_finance: finance.value === 'true' });
                window.location.replace('workflow-inbox.php?completed=1');
            } catch (error) {
                alert.textContent = error.message || 'The decision could not be saved.'; alert.classList.remove('d-none'); alert.focus();
                document.querySelectorAll('[data-review-action]').forEach((item) => { item.disabled = false; });
            }
        });
    });
    initialize();
})();
