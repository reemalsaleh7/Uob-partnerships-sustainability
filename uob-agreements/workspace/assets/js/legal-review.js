(function () {
    'use strict';

    const STEP_KEY = 'LEGAL_REVIEW';
    const elements = {
        alert: document.getElementById('legal-review-alert'),
        feedback: document.getElementById('legal-review-feedback'),
        loading: document.getElementById('legal-review-loading'),
        content: document.getElementById('legal-review-content'),
        comments: document.getElementById('legal-comments'),
        changeReason: document.getElementById('legal-change-reason'),
        approve: document.querySelector('[data-approve-legal]'),
        approveLabel: document.querySelector('[data-approve-legal-label]'),
        requestChanges: document.querySelector('[data-request-legal-changes]'),
        spinner: document.querySelector('[data-legal-spinner]'),
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
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT');
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

        const latestVersion = (Array.isArray(versions) ? versions : []).reduce(
            (latest, version) => Math.max(latest, Number(version.version_number) || 0),
            0
        );
        setText('[data-latest-version]', latestVersion > 0 ? `Version ${latestVersion}` : '—');

        document.querySelector('[data-agreement-status]').replaceChildren(
            AgreementApi.createStatusBadge(agreement.status)
        );
        elements.openAgreement.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
    }

    function setBusy(isBusy, action = '') {
        state.busy = isBusy;
        elements.approve.disabled = isBusy;
        elements.requestChanges.disabled = isBusy;
        elements.comments.disabled = isBusy;
        elements.changeReason.disabled = isBusy;
        elements.approveLabel.textContent = isBusy && action === 'approve'
            ? 'Approving…'
            : 'Approve Legal review';
        elements.spinner.classList.toggle('d-none', !(isBusy && action === 'approve'));
    }

    function showError(error, hideContent = false) {
        elements.loading.classList.add('d-none');

        if (hideContent) {
            elements.content.classList.add('d-none');
        }

        elements.alert.textContent = error.message || 'The Legal review could not be completed.';
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
                    'You do not have permission to perform Legal Agreement reviews.',
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
                    'This Legal review is not currently assigned to you.',
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

    elements.approve.addEventListener('click', async () => {
        if (state.busy || !state.assignment) {
            return;
        }

        if (!window.confirm('Approve the Legal review for this Agreement?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'approve');

        try {
            await AgreementApi.approveSpecialist(state.instanceId, {
                step_key: STEP_KEY,
                comments: elements.comments.value.trim() || null
            });
            complete('Legal review approved successfully. The workflow moved to its next required stage.');
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

        if (!window.confirm('Send this Legal change request to the VP for routing?')) {
            return;
        }

        clearMessages();
        setBusy(true, 'changes');

        try {
            await AgreementApi.requestChanges(state.instanceId, {
                step_key: STEP_KEY,
                reason
            });
            complete('Legal changes requested. The Agreement was routed to the VP for a decision.');
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    elements.changeReason.addEventListener('input', () => {
        elements.changeReason.classList.remove('is-invalid');
    });

    initialize();
})();
