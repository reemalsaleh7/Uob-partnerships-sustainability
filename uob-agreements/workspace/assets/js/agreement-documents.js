(function () {
    'use strict';

    const root = document.querySelector('[data-agreement-documents]');

    if (!root) {
        return;
    }

    const elements = {
        alert: root.querySelector('[data-document-alert]'),
        feedback: root.querySelector('[data-document-feedback]'),
        form: root.querySelector('[data-document-upload-form]'),
        file: root.querySelector('[data-document-file]'),
        type: root.querySelector('[data-document-type]'),
        upload: root.querySelector('[data-upload-document]'),
        uploadLabel: root.querySelector('[data-upload-document-label]'),
        uploadSpinner: root.querySelector('[data-upload-document-spinner]'),
        loading: root.querySelector('[data-documents-loading]'),
        empty: root.querySelector('[data-documents-empty]'),
        tableWrap: root.querySelector('[data-documents-table-wrap]'),
        body: root.querySelector('[data-documents-body]')
    };

    const state = {
        agreementId: null,
        constraints: null,
        busy: false
    };

    const typeLabels = {
        AGREEMENT_DRAFT: 'Agreement draft',
        SUPPORTING: 'Supporting document',
        LEGAL_REVIEW: 'Legal review document',
        FINANCE_REVIEW: 'Finance review document',
        OTHER: 'Other'
    };

    function agreementId() {
        const parameter = root.dataset.idParameter || 'id';
        const value = new URLSearchParams(window.location.search).get(parameter);

        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError(
                'A valid Agreement ID is required to load documents.',
                422,
                null
            );
        }

        return value;
    }

    function setBusy(isBusy) {
        state.busy = isBusy;
        elements.file.disabled = isBusy;
        elements.type.disabled = isBusy;
        elements.upload.disabled = isBusy;
        elements.uploadLabel.textContent = isBusy
            ? 'Uploading…'
            : 'Upload securely';
        elements.uploadSpinner.classList.toggle('d-none', !isBusy);
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }

    function showError(error) {
        elements.alert.textContent = error.message
            || 'The document operation could not be completed.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function showFeedback(message) {
        elements.feedback.textContent = message;
        elements.feedback.classList.remove('d-none');
        elements.feedback.focus();
    }

    function formatSize(value) {
        const bytes = Number(value);

        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '—';
        }

        if (bytes < 1024) {
            return `${bytes} B`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }

        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function actionButton(label, className, action, documentRecord) {
        const button = window.document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.dataset.documentAction = action;
        button.dataset.documentId = String(documentRecord.document_id);
        button.dataset.fileName = documentRecord.file_name || 'document';
        return button;
    }

    function renderDocuments(payload) {
        const documents = Array.isArray(payload?.documents)
            ? payload.documents
            : [];
        state.constraints = payload?.constraints || null;
        elements.form.classList.toggle('d-none', payload?.can_upload !== true);
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', documents.length !== 0);
        elements.tableWrap.classList.toggle('d-none', documents.length === 0);
        elements.body.replaceChildren();

        documents.forEach((document) => {
            const row = documentNode(document);
            elements.body.appendChild(row);
        });
    }

    function documentNode(document) {
        const row = window.document.createElement('tr');
        const nameCell = window.document.createElement('td');
        nameCell.className = 'document-name-cell';
        const name = window.document.createElement('span');
        name.className = 'd-block fw-semibold text-break';
        name.textContent = document.file_name || 'Unnamed document';
        nameCell.appendChild(name);

        if (document.available !== true) {
            const legacy = window.document.createElement('span');
            legacy.className = 'd-block small text-secondary mt-1';
            legacy.textContent = 'Metadata only — stored file unavailable';
            nameCell.appendChild(legacy);
        }

        const typeCell = window.document.createElement('td');
        const type = window.document.createElement('span');
        type.className = 'd-block';
        type.textContent = typeLabels[document.document_type]
            || String(document.document_type || 'Other').replaceAll('_', ' ');
        const version = window.document.createElement('span');
        version.className = 'd-block small text-secondary mt-1';
        version.textContent = document.version_number
            ? `Version ${document.version_number}`
            : 'Legacy entry';
        typeCell.append(type, version);

        const uploadedCell = window.document.createElement('td');
        const uploader = window.document.createElement('span');
        uploader.className = 'd-block';
        uploader.textContent = document.uploader_name
            || document.uploader_email
            || 'Unknown user';
        const uploadedAt = window.document.createElement('span');
        uploadedAt.className = 'd-block small text-secondary mt-1';
        uploadedAt.textContent = AgreementApi.formatDate(document.uploaded_at);
        uploadedCell.append(uploader, uploadedAt);

        const sizeCell = window.document.createElement('td');
        sizeCell.textContent = formatSize(document.file_size_bytes);

        const actionsCell = window.document.createElement('td');
        actionsCell.className = 'text-end';
        const actions = window.document.createElement('div');
        actions.className = 'document-actions';

        if (document.available === true) {
            actions.appendChild(actionButton(
                'Download',
                'btn btn-sm btn-outline-primary',
                'download',
                document
            ));
        }

        if (document.can_delete === true) {
            actions.appendChild(actionButton(
                'Delete',
                'btn btn-sm btn-outline-danger',
                'delete',
                document
            ));
        }

        if (!actions.hasChildNodes()) {
            const unavailable = window.document.createElement('span');
            unavailable.className = 'small text-secondary';
            unavailable.textContent = 'No actions';
            actions.appendChild(unavailable);
        }

        actionsCell.appendChild(actions);
        row.append(nameCell, typeCell, uploadedCell, sizeCell, actionsCell);
        return row;
    }

    async function reloadDocuments() {
        const payload = await AgreementApi.documents(state.agreementId);
        renderDocuments(payload);
    }

    async function initialize() {
        try {
            state.agreementId = agreementId();
            await AgreementApi.requireSession('VIEW_AGREEMENT');

            const defaultType = root.dataset.defaultType || 'SUPPORTING';

            if (typeLabels[defaultType]) {
                elements.type.value = defaultType;
            }

            await reloadDocuments();
        } catch (error) {
            elements.loading.classList.add('d-none');
            showError(error);
        }
    }

    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (state.busy) {
            return;
        }

        const file = elements.file.files?.[0];
        elements.file.classList.toggle('is-invalid', !file);

        if (!file) {
            elements.file.focus();
            return;
        }

        const maxBytes = Number(
            state.constraints?.max_file_size_bytes || 10485760
        );

        if (file.size <= 0 || file.size > maxBytes) {
            showError(new AgreementApi.ApiError(
                'The document must be larger than 0 bytes and no more than 10 MB.',
                422,
                null
            ));
            return;
        }

        clearMessages();
        setBusy(true);

        try {
            await AgreementApi.uploadDocument(
                state.agreementId,
                file,
                elements.type.value
            );
            elements.file.value = '';
            await reloadDocuments();
            showFeedback('Document uploaded securely.');
        } catch (error) {
            showError(error);
        } finally {
            setBusy(false);
        }
    });

    elements.file.addEventListener('change', () => {
        elements.file.classList.remove('is-invalid');
    });

    elements.body.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-document-action]');

        if (!button || state.busy) {
            return;
        }

        const documentId = button.dataset.documentId;
        const action = button.dataset.documentAction;
        const fileName = button.dataset.fileName || 'document';
        clearMessages();
        button.disabled = true;

        try {
            if (action === 'download') {
                const blob = await AgreementApi.downloadDocument(documentId);
                const url = URL.createObjectURL(blob);
                const link = window.document.createElement('a');
                link.href = url;
                link.download = fileName;
                window.document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            } else if (action === 'delete') {
                if (!window.confirm(`Delete ${fileName}? This cannot be undone.`)) {
                    return;
                }

                await AgreementApi.deleteDocument(documentId);
                await reloadDocuments();
                showFeedback('Document deleted.');
            }
        } catch (error) {
            showError(error);
        } finally {
            button.disabled = false;
        }
    });

    initialize();
})();
