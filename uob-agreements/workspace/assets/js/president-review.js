(function () {
    'use strict';

    const STEP_KEY = 'PRESIDENT_APPROVAL';

    const elements = {
        alert: document.getElementById('president-review-alert'),
        feedback: document.getElementById('president-review-feedback'),
        loading: document.getElementById('president-review-loading'),
        content: document.getElementById('president-review-content'),
        approvalPanel: document.querySelector('[data-president-approval-panel]'),
        changePanel: document.querySelector('[data-president-change-panel]'),
        rejectionPanel: document.querySelector('[data-president-rejection-panel]'),
        comments: document.getElementById('president-comments'),
        changeReason: document.getElementById('president-change-reason'),
        rejectionReason: document.getElementById('president-rejection-reason'),
        approve: document.querySelector('[data-approve-president]'),
        approveLabel: document.querySelector('[data-approve-president-label]'),
        approveSpinner: document.querySelector('[data-approve-president-spinner]'),
        requestChanges: document.querySelector('[data-request-president-changes]'),
        requestChangesLabel: document.querySelector('[data-request-president-changes-label]'),
        requestChangesSpinner: document.querySelector('[data-president-changes-spinner]'),
        reject: document.querySelector('[data-reject-president]'),
        rejectLabel: document.querySelector('[data-reject-president-label]'),
        rejectSpinner: document.querySelector('[data-reject-president-spinner]'),
        openAgreement: document.querySelector('[data-open-agreement]')
    };

    const state = {
        instanceId: null,
        agreementId: null,
        assignment: null,
        busy: false
    };

    function setText(selector, value) {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = value ?? '—';
        }
    }

    function numericQuery(name, label) {
        const value = new URLSearchParams(window.location.search).get(name);

        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError(`A valid ${label} is required.`, 422, null);
        }

        return value;
    }

    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true';
    }

    function canReview(user) {
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT')
            || AgreementApi.hasPermission(user, 'REJECT_AGREEMENT');
    }

    function renderStepStatus(selector, status, fallback = '—') {
        const container = document.querySelector(selector);

        if (!container) {
            return;
        }

        if (!status) {
            container.textContent = fallback;
            return;
        }

        container.replaceChildren(AgreementApi.createStatusBadge(status));
    }

    function render(agreement, versions, assignment, user) {
        setText('[data-agreement-id]', `#${agreement.agreement_id}`);
        setText('[data-agreement-title]', agreement.title);
        setText('[data-agreement-type]', agreement.agreement_type);
        setText('[data-partner-name]', agreement.partner_name || '—');
        setText(
            '[data-agreement-description]',
            agreement.description || 'No description provided.'
        );
        setText('[data-instance-id]', `#${assignment.workflow_instance_id}`);
        setText(
            '[data-assigned-unit]',
            assignment.assigned_unit_name || assignment.assigned_unit_code || '—'
        );
        setText('[data-review-started]', AgreementApi.formatDate(assignment.started_at));

        const latestVersion = (Array.isArray(versions) ? versions : []).reduce(
            (latest, version) => Math.max(latest, Number(version.version_number) || 0),
            0
        );
        setText('[data-latest-version]', latestVersion > 0 ? `Version ${latestVersion}` : '—');

        document.querySelector('[data-agreement-status]').replaceChildren(
            AgreementApi.createStatusBadge(agreement.status)
        );

        renderStepStatus('[data-legal-status]', assignment.legal_review_status);
        setText(
            '[data-legal-comments]',
            assignment.legal_review_comments || 'No Legal comments recorded.'
        );

        const financeRequired = booleanValue(assignment.finance_review_required);
        renderStepStatus(
            '[data-finance-status]',
            financeRequired ? assignment.finance_review_status : null,
            'Not required'
        );
        setText(
            '[data-finance-comments]',
            financeRequired
                ? (assignment.finance_review_comments || 'No Finance comments recorded.')
                : 'Finance review was not required for this cycle.'
        );

        renderStepStatus('[data-final-vp-status]', assignment.final_vp_review_status);
        setText(
            '[data-final-vp-comments]',
            assignment.final_vp_review_comments || 'No Final VP comments recorded.'
        );

        elements.openAgreement.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;

        const canApprove = AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT');
        const canReject = AgreementApi.hasPermission(user, 'REJECT_AGREEMENT');
        elements.approvalPanel.classList.toggle('d-none', !canApprove);
        elements.changePanel.classList.toggle('d-none', !canApprove);
        elements.rejectionPanel.classList.toggle('d-none', !canReject);
    }

    function setBusy(isBusy, action = '') {
        state.busy = isBusy;

        [
            elements.approve,
            elements.requestChanges,
            elements.reject,
            elements.comments,
            elements.changeReason,
            elements.rejectionReason
        ].forEach((element) => {
            element.disabled = isBusy;
        });

        elements.approveLabel.textContent = isBusy && action === 'approve'
            ? 'Completing workflow…'
            : 'Approve and complete workflow';
        elements.approveSpinner.classList.toggle('d-none', !(isBusy && action === 'approve'));

        elements.requestChangesLabel.textContent = isBusy && action === 'changes'
            ? 'Sending to VP…'
            : 'Request changes through VP';
        elements.requestChangesSpinner.classList.toggle(
            'd-none',
            !(isBusy && action === 'changes')
        );

        elements.rejectLabel.textContent = isBusy && action === 'reject'
            ? 'Rejecting…'
            : 'Reject Agreement';
        elements.rejectSpinner.classList.toggle('d-none', !(isBusy && action === 'reject'));
    }

    function showError(error, hideContent = false) {
        elements.loading.classList.add('d-none');

        if (hideContent) {
            elements.content.classList.add('d-none');
        }

        elements.alert.textContent = error.message || 'The President assignment could not be completed.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }

    function complete(message) {
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
        elements.feedback.focus();
        setTimeout(() => {
            window.location.replace('workflow-inbox.php?completed=1');
        }, 700);
    }

    async function initialize() {
        try {
            state.instanceId = numericQuery('instance_id', 'workflow instance ID');
            state.agreementId = numericQuery('agreement_id', 'Agreement ID');

            const user = await AgreementApi.requireSession();

            if (!canReview(user)) {
                throw new AgreementApi.ApiError(
                    'You do not have permission to perform President Agreement reviews.',
                    403,
                    null
                );
            }

            const assignments = await AgreementApi.workflowInbox();
            state.assignment = (Array.isArray(assignments) ? assignments : []).find(
                (assignment) => (
                    String(assignment.workflow_instance_id) === state.instanceId
                    && String(assignment.entity_id) === state.agreementId
                    && assignment.entity_type === 'AGREEMENT'
                    && assignment.step_key === STEP_KEY
                )
            );

            if (!state.assignment) {
                throw new AgreementApi.ApiError(
                    'This President approval is not currently assigned to you.',
                    403,
                    null
                );
            }

            const [agreement, versions] = await Promise.all([
                AgreementApi.agreement(state.agreementId),
                AgreementApi.versions(state.agreementId)
            ]);

            render(agreement, versions, state.assignment, user);
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            showError(error, true);
        }
    }

    elements.approve.addEventListener('click', async () => {
        if (state.busy || !state.assignment) {
            return;
        }

        if (!window.confirm('Approve this Agreement and complete the approval workflow?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'approve');

        try {
            await AgreementApi.approvePresident(state.instanceId, {
                comments: elements.comments.value.trim() || null
            });
            complete('Agreement approved. The workflow is complete.');
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    elements.requestChanges.addEventListener('click', async () => {
        if (state.busy || !state.assignment) {
            return;
        }

        const reason = elements.changeReason.value.trim();
        elements.changeReason.classList.toggle('is-invalid', reason === '');

        if (!reason) {
            elements.changeReason.focus();
            return;
        }

        if (!window.confirm('Send this President change request to the VP for mediation?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'changes');

        try {
            await AgreementApi.requestChanges(state.instanceId, {
                step_key: STEP_KEY,
                reason
            });
            complete('President changes requested. The Agreement was sent to the VP for mediation.');
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    elements.reject.addEventListener('click', async () => {
        if (state.busy || !state.assignment) {
            return;
        }

        const reason = elements.rejectionReason.value.trim();
        elements.rejectionReason.classList.toggle('is-invalid', reason === '');

        if (!reason) {
            elements.rejectionReason.focus();
            return;
        }

        if (!window.confirm('Reject this Agreement and permanently end the workflow?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'reject');

        try {
            await AgreementApi.rejectPresident(state.instanceId, { reason });
            complete('The Agreement was rejected and the workflow ended.');
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    elements.changeReason.addEventListener('input', () => {
        elements.changeReason.classList.remove('is-invalid');
    });
    elements.rejectionReason.addEventListener('input', () => {
        elements.rejectionReason.classList.remove('is-invalid');
    });

    initialize();
})();
