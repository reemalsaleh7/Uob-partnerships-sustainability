(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('detail-alert'),
        loading: document.getElementById('detail-loading'),
        content: document.getElementById('detail-content'),
        versionLoading: document.getElementById('version-loading'),
        versionEmpty: document.getElementById('version-empty'),
        versionWrap: document.getElementById('version-table-wrap'),
        versionBody: document.getElementById('version-table-body'),
        edit: document.querySelector('[data-edit-agreement]')
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
        setText('[data-partner-id]', agreement.partner_id);
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
    }

    async function initialize() {
        try {
            const id = agreementId();
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');
            elements.edit.classList.toggle(
                'd-none',
                !AgreementApi.hasPermission(user, 'EDIT_AGREEMENT')
            );

            const [agreement, versions] = await Promise.all([
                AgreementApi.agreement(id),
                AgreementApi.versions(id)
            ]);

            if (!agreement) {
                throw new AgreementApi.ApiError('Agreement not found.', 404, null);
            }

            renderAgreement(agreement);
            renderVersions(versions);
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            showError(error);
        }
    }

    initialize();
})();

