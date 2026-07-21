(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('review-alert'),
        feedback: document.getElementById('review-feedback'),
        loading: document.getElementById('review-loading'),
        content: document.getElementById('review-content'),
        approvalForm: document.getElementById('vp-approval-form'),
        approvalComments: document.getElementById('approval-comments'),
        negativeReason: document.getElementById('negative-reason'),
        approve: document.querySelector('[data-approve-review]'),
        approveLabel: document.querySelector('[data-approve-label]'),
        spinner: document.querySelector('[data-review-spinner]'),
        returnButton: document.querySelector('[data-return-review]'),
        rejectButton: document.querySelector('[data-reject-review]'),
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

    function canReview(user) {
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT')
            || AgreementApi.hasPermission(user, 'REJECT_AGREEMENT');
    }

    function render(agreement, versions, assignment) {
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

        const versionRows = Array.isArray(versions) ? versions : [];
        const latestVersion = versionRows.reduce(
            (latest, version) => Math.max(latest, Number(version.version_number) || 0),
            0
        );
        setText('[data-latest-version]', latestVersion > 0 ? `Version ${latestVersion}` : '—');

        const status = document.querySelector('[data-agreement-status]');
        status.replaceChildren(AgreementApi.createStatusBadge(agreement.status));
        elements.openAgreement.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
    }

    function setBusy(isBusy, label = '') {
        state.busy = isBusy;
        elements.approve.disabled = isBusy;
        elements.returnButton.disabled = isBusy;
        elements.rejectButton.disabled = isBusy;
        elements.approveLabel.textContent = isBusy && label
            ? label
            : 'Approve and route';
        elements.spinner.classList.toggle('d-none', !isBusy);
    }

    function showError(error, hideContent = false) {
        elements.loading.classList.add('d-none');

        if (hideContent) {
            elements.content.classList.add('d-none');
        }

        elements.alert.textContent = error.message || 'The assigned review could not be loaded.';
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
                    'You do not have permission to perform Agreement reviews.',
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
                    && assignment.step_key === 'VP_INITIAL'
                )
            );

            if (!state.assignment) {
                throw new AgreementApi.ApiError(
                    'This Initial VP review is not currently assigned to you.',
                    403,
                    null
                );
            }

            const [agreement, versions] = await Promise.all([
                AgreementApi.agreement(state.agreementId),
                AgreementApi.versions(state.agreementId)
            ]);

            render(agreement, versions, state.assignment);
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            showError(error, true);
        }
    }

    elements.approvalForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (state.busy || !state.assignment) {
            return;
        }

        const financeChoice = new FormData(elements.approvalForm).get('include_finance');
        const includeFinance = financeChoice === 'true';
        const destination = includeFinance ? 'Legal and Finance' : 'Legal';

        if (!window.confirm(`Approve this review and send the Agreement to ${destination}?`)) {
            return;
        }

        clearMessages();
        setBusy(true, 'Routing…');

        try {
            await AgreementApi.approveInitialVp(state.instanceId, {
                include_finance: includeFinance,
                comments: elements.approvalComments.value.trim() || null
            });
            complete(`Initial VP review approved. The Agreement was sent to ${destination}.`);
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    async function makeNegativeDecision(decision) {
        if (state.busy || !state.assignment) {
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
            : 'Return this Agreement to its creator for redrafting?';

        if (!window.confirm(prompt)) {
            return;
        }

        clearMessages();
        setBusy(true, isReject ? 'Rejecting…' : 'Returning…');

        try {
            await AgreementApi.decideVp(state.instanceId, {
                step_key: 'VP_INITIAL',
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

    elements.negativeReason.addEventListener('input', () => {
        elements.negativeReason.classList.remove('is-invalid');
    });
    elements.returnButton.addEventListener('click', () => {
        makeNegativeDecision('RETURN_TO_CREATOR');
    });
    elements.rejectButton.addEventListener('click', () => {
        makeNegativeDecision('REJECT');
    });

    initialize();
})();
