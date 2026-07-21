(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('detail-alert'),
        feedback: document.getElementById('detail-feedback'),
        loading: document.getElementById('detail-loading'),
        content: document.getElementById('detail-content'),
        versionLoading: document.getElementById('version-loading'),
        versionEmpty: document.getElementById('version-empty'),
        versionWrap: document.getElementById('version-table-wrap'),
        versionBody: document.getElementById('version-table-body'),
        edit: document.querySelector('[data-edit-agreement]'),
        submit: document.querySelector('[data-submit-agreement]'),
        submitLabel: document.querySelector('[data-submit-label]'),
        submitSpinner: document.querySelector('[data-submit-spinner]')
    };

    const state = {
        agreementId: null,
        agreement: null
    };

    function setText(selector, value) {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = value ?? '—';
        }
    }

    function agreementId() {
        const value = new URLSearchParams(window.location.search).get('id');

        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }

        return value;
    }

    function renderAgreement(agreement) {
        setText('[data-agreement-id]', `#${agreement.agreement_id}`);
        setText('[data-agreement-title]', agreement.title);
        setText('[data-agreement-type]', agreement.agreement_type);
        setText(
            '[data-partner-name]',
            agreement.partner_name || (
                agreement.partner_id
                    ? `Partner #${agreement.partner_id}`
                    : '—'
            )
        );
        setText('[data-agreement-description]', agreement.description || 'No description provided.');
        setText('[data-created-by]', agreement.created_by);
        setText('[data-created-at]', AgreementApi.formatDate(agreement.created_at));
        setText('[data-updated-at]', AgreementApi.formatDate(agreement.updated_at));

        const status = document.querySelector('[data-agreement-status]');
        status.replaceChildren(AgreementApi.createStatusBadge(agreement.status));
    }

    function renderVersions(versions) {
        const rows = Array.isArray(versions) ? versions : [];
        elements.versionLoading.classList.add('d-none');
        elements.versionEmpty.classList.toggle('d-none', rows.length !== 0);
        elements.versionWrap.classList.toggle('d-none', rows.length === 0);
        elements.versionBody.replaceChildren();

        rows.forEach((version) => {
            const tr = document.createElement('tr');

            [
                version.version_number,
                version.change_summary || 'Initial version',
                version.created_by,
                AgreementApi.formatDate(version.created_at)
            ].forEach((value) => {
                const td = document.createElement('td');
                td.textContent = value ?? '—';
                tr.appendChild(td);
            });

            elements.versionBody.appendChild(tr);
        });
    }

    function showError(error) {
        elements.loading.classList.add('d-none');
        elements.content.classList.add('d-none');
        elements.alert.textContent = error.message || 'Agreement details could not be loaded.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function showFeedbackFromQuery() {
        const query = new URLSearchParams(window.location.search);
        let message = '';

        if (query.get('created') === '1') {
            message = 'Agreement draft created successfully.';
        } else if (query.get('updated') === '1') {
            message = 'Agreement draft updated successfully.';
        } else if (query.get('submitted') === '1') {
            message = 'Agreement submitted for review successfully.';
        } else if (query.get('revised') === '1') {
            message = 'Revised Agreement version saved. Review it, then resubmit it.';
        } else if (query.get('resubmitted') === '1') {
            message = 'Revised Agreement resubmitted to Initial VP review successfully.';
        }

        if (message) {
            elements.feedback.textContent = message;
            elements.feedback.classList.remove('d-none');
        }
    }

    function configureActions(user, agreement) {
        const isDraft = agreement.status === 'DRAFT';
        const isRevision = agreement.status === 'REVISION_REQUIRED';
        const isCreator = Number(agreement.created_by) === Number(user.user_id);
        const canEdit = AgreementApi.hasPermission(user, 'EDIT_AGREEMENT');
        const canSubmit = AgreementApi.hasPermission(user, 'SUBMIT_AGREEMENT');

        elements.edit.classList.toggle(
            'd-none',
            (!isDraft && !isRevision) || !isCreator || !canEdit
        );
        elements.edit.href = `agreement-form.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        elements.edit.textContent = isRevision ? 'Revise Agreement' : 'Edit Agreement';
        elements.submit.classList.toggle(
            'd-none',
            (!isDraft && !isRevision) || !isCreator || !canSubmit
        );
        elements.submitLabel.textContent = isRevision
            ? 'Resubmit revised Agreement'
            : 'Submit for review';
    }

    function setSubmitBusy(isBusy) {
        elements.submit.disabled = isBusy;
        elements.submitLabel.textContent = isBusy
            ? 'Submitting…'
            : (
                state.agreement?.status === 'REVISION_REQUIRED'
                    ? 'Resubmit revised Agreement'
                    : 'Submit for review'
            );
        elements.submitSpinner.classList.toggle('d-none', !isBusy);
    }

    async function initialize() {
        try {
            const id = agreementId();
            state.agreementId = id;
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');

            const [agreement, versions] = await Promise.all([
                AgreementApi.agreement(id),
                AgreementApi.versions(id)
            ]);

            if (!agreement) {
                throw new AgreementApi.ApiError('Agreement not found.', 404, null);
            }

            state.agreement = agreement;
            renderAgreement(agreement);
            renderVersions(versions);
            configureActions(user, agreement);
            showFeedbackFromQuery();
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            showError(error);
        }
    }

    elements.submit.addEventListener('click', async () => {
        if (
            !state.agreement
            || !['DRAFT', 'REVISION_REQUIRED'].includes(state.agreement.status)
        ) {
            return;
        }

        const isRevision = state.agreement.status === 'REVISION_REQUIRED';

        const confirmed = window.confirm(
            isRevision
                ? 'Resubmit this revised Agreement? It will return to Initial VP review.'
                : 'Submit this Agreement for formal review? You will not be able to edit it as a draft after submission.'
        );

        if (!confirmed) {
            return;
        }

        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
        setSubmitBusy(true);

        try {
            if (isRevision) {
                await AgreementApi.resubmitAgreement(state.agreementId, {
                    comments: 'Revised Agreement resubmitted through the workspace'
                });
            } else {
                await AgreementApi.submitAgreement(state.agreementId);
            }
            window.location.replace(
                `agreement.php?id=${encodeURIComponent(state.agreementId)}&${isRevision ? 'resubmitted=1' : 'submitted=1'}`
            );
        } catch (error) {
            elements.alert.textContent = error.message || 'The Agreement could not be submitted.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
            setSubmitBusy(false);
        }
    });

    initialize();
})();
