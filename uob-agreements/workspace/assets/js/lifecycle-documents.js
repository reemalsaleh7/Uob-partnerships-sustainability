(function () {
    'use strict';

    const root = document.querySelector('[data-lifecycle-documents]');
    if (!root) return;

    const elements = {
        alert: root.querySelector('[data-lifecycle-document-alert]'),
        feedback: root.querySelector('[data-lifecycle-document-feedback]'),
        form: root.querySelector('[data-lifecycle-document-form]'),
        file: root.querySelector('[data-lifecycle-document-file]'),
        type: root.querySelector('[data-lifecycle-document-type]'),
        upload: root.querySelector('[data-lifecycle-document-upload]'),
        uploadLabel: root.querySelector('[data-lifecycle-document-upload-label]'),
        spinner: root.querySelector('[data-lifecycle-document-spinner]'),
        loading: root.querySelector('[data-lifecycle-documents-loading]'),
        empty: root.querySelector('[data-lifecycle-documents-empty]'),
        table: root.querySelector('[data-lifecycle-documents-table]'),
        body: root.querySelector('[data-lifecycle-documents-body]')
    };
    const labels = {
        REQUEST_FORM: 'Request form', SUPPORTING: 'Supporting evidence',
        PROPOSED_AMENDMENT: 'Proposed amendment', RENEWAL_EVIDENCE: 'Renewal evidence',
        TERMINATION_EVIDENCE: 'Termination evidence', LEGAL_REVIEW: 'Legal review document',
        FINANCE_REVIEW: 'Finance review document', PRESIDENT_DECISION: 'President decision',
        OTHER: 'Other'
    };
    const state = { requestId: null, constraints: null, busy: false };

    function requestId() {
        const parameter = root.dataset.idParameter || 'id';
        const value = new URLSearchParams(window.location.search).get(parameter);
        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid lifecycle request ID is required.', 422, null);
        }
        return value;
    }
    function message(target, value) {
        target.textContent = value;
        target.classList.remove('d-none');
        target.focus();
    }
    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }
    function size(value) {
        const bytes = Number(value);
        if (!Number.isFinite(bytes) || bytes <= 0) return '—';
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }
    function setBusy(busy) {
        state.busy = busy;
        elements.file.disabled = busy;
        elements.type.disabled = busy;
        elements.upload.disabled = busy;
        elements.uploadLabel.textContent = busy ? 'Uploading…' : 'Upload securely';
        elements.spinner.classList.toggle('d-none', !busy);
    }
    function button(label, style, action, documentRecord) {
        const result = document.createElement('button');
        result.type = 'button';
        result.className = style;
        result.textContent = label;
        result.dataset.lifecycleDocumentAction = action;
        result.dataset.documentId = String(documentRecord.lifecycle_request_document_id);
        result.dataset.fileName = documentRecord.file_name || 'document';
        return result;
    }
    function row(documentRecord) {
        const tr = document.createElement('tr');
        const name = document.createElement('td');
        name.className = 'fw-semibold text-break';
        name.textContent = documentRecord.file_name || 'Unnamed document';
        const type = document.createElement('td');
        type.textContent = `${labels[documentRecord.document_type] || 'Other'} · Version ${documentRecord.version_number || '—'}`;
        const uploaded = document.createElement('td');
        uploaded.textContent = `${documentRecord.uploader_name || documentRecord.uploader_email || 'Unknown user'} · ${AgreementApi.formatDate(documentRecord.uploaded_at)}`;
        const fileSize = document.createElement('td');
        fileSize.textContent = size(documentRecord.file_size_bytes);
        const actions = document.createElement('td');
        actions.className = 'text-end';
        const wrap = document.createElement('div');
        wrap.className = 'document-actions';
        if (documentRecord.available === true) {
            wrap.append(button('Download', 'btn btn-sm btn-outline-primary', 'download', documentRecord));
        }
        if (documentRecord.can_delete === true) {
            wrap.append(button('Delete', 'btn btn-sm btn-outline-danger', 'delete', documentRecord));
        }
        if (!wrap.hasChildNodes()) {
            const none = document.createElement('span');
            none.className = 'small text-secondary'; none.textContent = 'No actions'; wrap.append(none);
        }
        actions.append(wrap); tr.append(name, type, uploaded, fileSize, actions); return tr;
    }
    function render(payload) {
        const documents = Array.isArray(payload?.documents) ? payload.documents : [];
        state.constraints = payload?.constraints || null;
        elements.form.classList.toggle('d-none', payload?.can_upload !== true);
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', documents.length > 0);
        elements.table.classList.toggle('d-none', documents.length === 0);
        elements.body.replaceChildren(...documents.map(row));
    }
    async function reload() {
        render(await AgreementApi.lifecycleDocuments(state.requestId));
    }
    async function initialize() {
        try {
            state.requestId = requestId();
            await AgreementApi.requireSession('VIEW_AGREEMENT');
            const defaultType = root.dataset.defaultType || 'SUPPORTING';
            if (labels[defaultType]) elements.type.value = defaultType;
            await reload();
        } catch (error) {
            elements.loading.classList.add('d-none');
            message(elements.alert, error.message || 'Request documents could not be loaded.');
        }
    }
    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.busy) return;
        const file = elements.file.files?.[0];
        elements.file.classList.toggle('is-invalid', !file);
        if (!file) return elements.file.focus();
        const maximum = Number(state.constraints?.max_file_size_bytes || 10485760);
        if (file.size <= 0 || file.size > maximum) {
            return message(elements.alert, 'The document must be larger than 0 bytes and no more than 10 MB.');
        }
        clearMessages(); setBusy(true);
        try {
            await AgreementApi.uploadLifecycleDocument(state.requestId, file, elements.type.value);
            elements.file.value = ''; await reload();
            message(elements.feedback, 'Request document uploaded securely.');
        } catch (error) {
            message(elements.alert, error.message || 'The document could not be uploaded.');
        } finally { setBusy(false); }
    });
    elements.body.addEventListener('click', async (event) => {
        const control = event.target.closest('[data-lifecycle-document-action]');
        if (!control || state.busy) return;
        clearMessages(); control.disabled = true;
        try {
            if (control.dataset.lifecycleDocumentAction === 'download') {
                const blob = await AgreementApi.downloadLifecycleDocument(control.dataset.documentId);
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a'); link.href = url;
                link.download = control.dataset.fileName || 'document';
                document.body.append(link); link.click(); link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            } else if (window.confirm(`Delete ${control.dataset.fileName}? This cannot be undone.`)) {
                await AgreementApi.deleteLifecycleDocument(control.dataset.documentId);
                await reload(); message(elements.feedback, 'Request document deleted.');
            }
        } catch (error) {
            message(elements.alert, error.message || 'The document operation failed.');
        } finally { control.disabled = false; }
    });
    initialize();
})();
