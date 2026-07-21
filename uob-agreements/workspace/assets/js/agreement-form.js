(function () {
    'use strict';

    const form = document.getElementById('agreement-form');
    const elements = {
        alert: document.getElementById('form-alert'),
        loading: document.getElementById('form-loading'),
        title: document.querySelector('[data-form-title]'),
        eyebrow: document.querySelector('[data-form-eyebrow]'),
        description: document.querySelector('[data-form-description]'),
        partner: document.getElementById('agreement-partner'),
        partnerHelp: document.querySelector('[data-partner-help]'),
        save: document.getElementById('save-agreement'),
        saveLabel: document.querySelector('[data-save-label]'),
        saveSpinner: document.querySelector('[data-save-spinner]')
    };

    const state = {
        agreementId: null,
        isEdit: false,
        isRevision: false
    };

    function readAgreementId() {
        const value = new URLSearchParams(window.location.search).get('id');

        if (!value) {
            return null;
        }

        if (!/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }

        return value;
    }

    function setBusy(isBusy) {
        elements.save.disabled = isBusy;
        elements.saveLabel.textContent = isBusy
            ? 'Saving…'
            : (
                state.isRevision
                    ? 'Save revised version'
                    : (state.isEdit ? 'Save changes' : 'Save draft')
            );
        elements.saveSpinner.classList.toggle('d-none', !isBusy);
    }

    function showError(error) {
        elements.alert.textContent = error.message || 'The Agreement could not be saved.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function populatePartners(partners, selectedPartnerId = null) {
        const rows = Array.isArray(partners) ? partners : [];

        rows.forEach((partner) => {
            const option = document.createElement('option');
            option.value = String(partner.partner_id);
            option.textContent = [
                partner.organization_name,
                partner.country
            ].filter(Boolean).join(' — ');
            elements.partner.appendChild(option);
        });

        if (selectedPartnerId !== null && selectedPartnerId !== undefined) {
            elements.partner.value = String(selectedPartnerId);
        }

        if (rows.length === 0) {
            elements.partner.disabled = true;
            elements.partnerHelp.textContent = 'No active partners are available. Add a partner before creating an Agreement.';
            elements.save.disabled = true;
        }
    }

    function populateAgreement(agreement) {
        form.elements.title.value = agreement.title || '';
        form.elements.agreement_type.value = agreement.agreement_type || '';
        form.elements.description.value = agreement.description || '';
        elements.partner.value = agreement.partner_id == null
            ? ''
            : String(agreement.partner_id);
    }

    function configureEditMode(agreement) {
        const editableStatuses = ['DRAFT', 'REVISION_REQUIRED'];

        if (!editableStatuses.includes(agreement.status)) {
            throw new AgreementApi.ApiError(
                'Only draft or returned Agreements can be edited from this screen.',
                409,
                null
            );
        }

        state.isRevision = agreement.status === 'REVISION_REQUIRED';

        elements.eyebrow.textContent = `Agreement #${agreement.agreement_id}`;
        elements.title.textContent = state.isRevision
            ? 'Revise returned Agreement'
            : 'Edit Agreement';
        elements.description.textContent = state.isRevision
            ? 'Apply the requested changes. Saving creates a new immutable version before resubmission.'
            : 'Update the draft information. Saving creates a new immutable version snapshot.';
        document.title = state.isRevision
            ? 'Revise Agreement | UOB Agreement Workspace'
            : 'Edit Agreement | UOB Agreement Workspace';
        document.querySelectorAll('[data-cancel-link]').forEach((link) => {
            link.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        });
        elements.saveLabel.textContent = state.isRevision
            ? 'Save revised version'
            : 'Save changes';
    }

    function payload() {
        return {
            title: form.elements.title.value.trim(),
            agreement_type: form.elements.agreement_type.value.trim(),
            partner_id: Number(form.elements.partner_id.value),
            description: form.elements.description.value.trim(),
            change_summary: state.isEdit
                ? (
                    state.isRevision
                        ? 'Agreement revised after reviewer feedback'
                        : 'Agreement draft updated through the workspace'
                )
                : undefined
        };
    }

    async function initialize() {
        try {
            state.agreementId = readAgreementId();
            state.isEdit = state.agreementId !== null;

            const requiredPermission = state.isEdit
                ? 'EDIT_AGREEMENT'
                : 'CREATE_AGREEMENT';

            const user = await AgreementApi.requireSession(requiredPermission);

            const requests = [AgreementApi.partners()];

            if (state.isEdit) {
                requests.push(AgreementApi.agreement(state.agreementId));
            }

            const [partners, agreement = null] = await Promise.all(requests);

            if (
                agreement
                && Number(agreement.created_by) !== Number(user.user_id)
            ) {
                throw new AgreementApi.ApiError(
                    'Only the original Agreement creator may edit this Agreement.',
                    403,
                    null
                );
            }

            populatePartners(partners, agreement?.partner_id);

            if (agreement) {
                configureEditMode(agreement);
                populateAgreement(agreement);
            }

            elements.loading.classList.add('d-none');
            form.classList.remove('d-none');
        } catch (error) {
            elements.loading.classList.add('d-none');
            showError(error);
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        elements.alert.classList.add('d-none');
        form.classList.add('was-validated');

        if (!form.checkValidity()) {
            return;
        }

        setBusy(true);

        try {
            if (state.isEdit) {
                await AgreementApi.updateAgreement(state.agreementId, payload());
                const resultFlag = state.isRevision ? 'revised=1' : 'updated=1';
                window.location.assign(`agreement.php?id=${encodeURIComponent(state.agreementId)}&${resultFlag}`);
                return;
            }

            const result = await AgreementApi.createAgreement(payload());
            window.location.assign(`agreement.php?id=${encodeURIComponent(result.agreement_id)}&created=1`);
        } catch (error) {
            showError(error);
            setBusy(false);
        }
    });

    initialize();
})();
