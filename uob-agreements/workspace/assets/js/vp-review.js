(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('vp-review-alert'),
        feedback: document.getElementById('vp-review-feedback'),
        loading: document.getElementById('vp-review-loading'),
        content: document.getElementById('vp-review-content'),
        taskLabel: document.querySelector('[data-vp-task-label]'),
        mediationContext: document.querySelector('[data-mediation-context]'),
        finalPanel: document.querySelector('[data-final-review-panel]'),
        finalApprovalPanel: document.querySelector('[data-final-approval-panel]'),
        mediationPanel: document.querySelector('[data-mediation-panel]'),
        finalComments: document.getElementById('final-vp-comments'),
        negativeReason: document.getElementById('final-vp-negative-reason'),
        approve: document.querySelector('[data-approve-final-vp]'),
        approveLabel: document.querySelector('[data-approve-final-vp-label]'),
        approveSpinner: document.querySelector('[data-final-vp-spinner]'),
        returnButton: document.querySelector('[data-return-final-vp]'),
        rejectButton: document.querySelector('[data-reject-final-vp]'),
        mediationForm: document.getElementById('vp-mediation-form'),
        mediationReason: document.getElementById('vp-routing-reason'),
        destinationError: document.querySelector('[data-destination-error]'),
        mediationSubmit: document.querySelector('[data-submit-mediation]'),
        mediationLabel: document.querySelector('[data-submit-mediation-label]'),
        mediationSpinner: document.querySelector('[data-mediation-spinner]'),
        openAgreement: document.querySelector('[data-open-agreement]')
    };

    const state = {
        instanceId: null,
        agreementId: null,
        assignment: null,
        mode: 'REVIEW',
        busy: false
    };

    const stepNames = Object.freeze({
        LEGAL_REVIEW: 'Legal review',
        FINANCE_REVIEW: 'Finance review',
        PRESIDENT_APPROVAL: 'President review'
    });

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
        state.mode = assignment.task_mode === 'VP_MEDIATION'
            ? 'VP_MEDIATION'
            : 'REVIEW';

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

        elements.openAgreement.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        elements.taskLabel.textContent = state.mode === 'VP_MEDIATION'
            ? 'VP mediation'
            : 'Final VP review';
        elements.mediationContext.classList.toggle('d-none', state.mode !== 'VP_MEDIATION');
        elements.mediationPanel.classList.toggle('d-none', state.mode !== 'VP_MEDIATION');
        elements.finalPanel.classList.toggle('d-none', state.mode !== 'REVIEW');

        if (state.mode === 'VP_MEDIATION') {
            setText(
                '[data-change-source]',
                stepNames[assignment.change_request_step_key]
                    || assignment.change_request_step_key
                    || 'Review stage'
            );
            setText(
                '[data-change-reason]',
                assignment.change_request_reason || 'No reason was recorded.'
            );
        }

        const canApprove = AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT');
        elements.finalApprovalPanel.classList.toggle('d-none', !canApprove);
    }

    function setBusy(isBusy, action = '') {
        state.busy = isBusy;
        [
            elements.approve,
            elements.returnButton,
            elements.rejectButton,
            elements.mediationSubmit,
            elements.finalComments,
            elements.negativeReason,
            elements.mediationReason
        ].forEach((element) => {
            if (element) {
                element.disabled = isBusy;
            }
        });
        elements.mediationForm.querySelectorAll('input[name="destination"]').forEach((input) => {
            input.disabled = isBusy;
        });

        elements.approveLabel.textContent = isBusy && action === 'approve'
            ? 'Sending to President…'
            : 'Approve and send to President';
        elements.approveSpinner.classList.toggle('d-none', !(isBusy && action === 'approve'));
        elements.mediationLabel.textContent = isBusy && action === 'mediate'
            ? 'Saving decision…'
            : 'Save routing decision';
        elements.mediationSpinner.classList.toggle('d-none', !(isBusy && action === 'mediate'));
    }

    function showError(error, hideContent = false) {
        elements.loading.classList.add('d-none');

        if (hideContent) {
            elements.content.classList.add('d-none');
        }

        elements.alert.textContent = error.message || 'The VP assignment could not be completed.';
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
                    'You do not have permission to perform VP Agreement reviews.',
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
                    && assignment.step_key === 'VP_FINAL'
                )
            );

            if (!state.assignment) {
                throw new AgreementApi.ApiError(
                    'This Final VP review or mediation task is not currently assigned to you.',
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
        if (state.busy || !state.assignment || state.mode !== 'REVIEW') {
            return;
        }

        if (!window.confirm('Approve the Final VP review and send this Agreement to the President?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'approve');

        try {
            await AgreementApi.approveFinalVp(state.instanceId, {
                comments: elements.finalComments.value.trim() || null
            });
            complete('Final VP review approved. The Agreement was sent to the President.');
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    async function makeFinalDecision(decision) {
        if (state.busy || !state.assignment || state.mode !== 'REVIEW') {
            return;
        }

        const reason = elements.negativeReason.value.trim();
        elements.negativeReason.classList.toggle('is-invalid', reason === '');

        if (!reason) {
            elements.negativeReason.focus();
            return;
        }

        const isReject = decision === 'REJECT';
        const prompt = isReject
            ? 'Reject this Agreement and permanently end the workflow?'
            : 'Return this Agreement to its creator for a revised version?';

        if (!window.confirm(prompt)) {
            return;
        }

        clearMessages();
        setBusy(true);

        try {
            await AgreementApi.decideVp(state.instanceId, {
                step_key: 'VP_FINAL',
                decision,
                reason
            });
            complete(
                isReject
                    ? 'The Agreement was rejected and the workflow ended.'
                    : 'The Agreement was returned to its creator for redrafting.'
            );
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    }

    elements.mediationForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (state.busy || !state.assignment || state.mode !== 'VP_MEDIATION') {
            return;
        }

        const destination = new FormData(elements.mediationForm).get('destination');
        const reason = elements.mediationReason.value.trim();
        elements.destinationError.classList.toggle('d-none', Boolean(destination));
        elements.mediationReason.classList.toggle('is-invalid', reason === '');

        if (!destination) {
            elements.mediationForm.querySelector('input[name="destination"]')?.focus();
            return;
        }

        if (!reason) {
            elements.mediationReason.focus();
            return;
        }

        const destinationLabels = {
            CREATOR: 'the creator for redrafting',
            LEGAL: 'Legal for review',
            FINANCE: 'Finance for review',
            REJECT: 'terminal rejection'
        };

        if (!window.confirm(`Route this change request to ${destinationLabels[destination]}?`)) {
            return;
        }

        clearMessages();
        setBusy(true, 'mediate');

        try {
            await AgreementApi.routeByVp(state.instanceId, {
                destination,
                reason
            });
            complete(
                destination === 'REJECT'
                    ? 'The Agreement was rejected and the workflow ended.'
                    : `The Agreement was routed to ${destinationLabels[destination]}.`
            );
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    elements.negativeReason.addEventListener('input', () => {
        elements.negativeReason.classList.remove('is-invalid');
    });
    elements.mediationReason.addEventListener('input', () => {
        elements.mediationReason.classList.remove('is-invalid');
    });
    elements.mediationForm.querySelectorAll('input[name="destination"]').forEach((input) => {
        input.addEventListener('change', () => {
            elements.destinationError.classList.add('d-none');
        });
    });
    elements.returnButton.addEventListener('click', () => {
        makeFinalDecision('RETURN_TO_CREATOR');
    });
    elements.rejectButton.addEventListener('click', () => {
        makeFinalDecision('REJECT');
    });

    initialize();
})();
