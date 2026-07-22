(function () {
    'use strict';

    const root = document.querySelector('[data-agreement-operations]');
    if (!root) return;

    const elements = {
        alert: root.querySelector('[data-operation-alert]'),
        feedback: root.querySelector('[data-operation-feedback]'),
        loading: root.querySelector('[data-operation-loading]'),
        state: root.querySelector('[data-operational-state]'),
        summary: root.querySelector('[data-signing-summary]'),
        empty: root.querySelector('[data-signing-empty]'),
        form: root.querySelector('[data-signing-form]'),
        signingDate: root.querySelector('[data-final-signing-date]'),
        effectiveDate: root.querySelector('[data-final-effective-date]'),
        expiryDate: root.querySelector('[data-final-expiry-date]'),
        document: root.querySelector('[data-final-signed-document]'),
        venue: root.querySelector('[data-final-signing-venue]'),
        announcement: root.querySelector('[data-final-announcement-url]'),
        notes: root.querySelector('[data-final-ceremony-notes]'),
        editor: root.querySelector('[data-signatory-editor]'),
        add: root.querySelector('[data-add-final-signatory]'),
        finalize: root.querySelector('[data-finalize-signing]'),
        finalizeLabel: root.querySelector('[data-finalize-signing-label]'),
        finalizeSpinner: root.querySelector('[data-finalize-signing-spinner]'),
        signatoryRows: root.querySelector('[data-final-signatory-rows]'),
        eventList: root.querySelector('[data-status-event-list]')
    };

    const state = { agreementId: null, agreement: null, busy: false };

    function id() {
        const value = new URLSearchParams(window.location.search).get('id');
        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }
        return value;
    }

    function setMessage(element, message) {
        element.textContent = message;
        element.classList.remove('d-none');
        element.focus();
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }

    function operationBadge(value) {
        const label = value === 'ACTIVE_LEGACY'
            ? 'ACTIVE · LEGACY RECORD'
            : String(value || 'NOT_FINALIZED').replaceAll('_', ' ');
        elements.state.textContent = label;
        const status = value === 'SCHEDULED'
            ? 'APPROVED'
            : (value === 'ACTIVE_LEGACY' ? 'ACTIVE' : value);
        elements.state.className = `status-badge ${status === 'NOT_FINALIZED' ? 'status-default' : `status-${String(status).toLowerCase()}`}`;
    }

    function textField(name, value) {
        const target = root.querySelector(`[data-signing-field="${name}"]`);
        if (target) target.textContent = value || '—';
    }

    function renderRecord(payload) {
        const record = payload.signing_record;
        operationBadge(payload.operational_state);
        const agreementStatus = document.querySelector('[data-agreement-status]');
        if (agreementStatus && payload.agreement_status) {
            agreementStatus.replaceChildren(
                AgreementApi.createStatusBadge(payload.agreement_status)
            );
        }
        if (state.agreement && payload.agreement_status) {
            state.agreement.status = payload.agreement_status;
        }
        elements.loading.classList.add('d-none');
        elements.summary.classList.toggle('d-none', !record);
        elements.empty.classList.toggle('d-none', Boolean(record));
        elements.form.classList.toggle('d-none', payload.can_finalize !== true);

        renderDocuments(payload.eligible_documents || []);
        if (!record) {
            prefillDates();
            ensureInitialSignatories();
            return;
        }

        textField('signing_date', record.signing_date);
        textField('effective_date', record.effective_date);
        textField('expiry_date', record.expiry_date);
        textField('venue', record.venue);
        textField('finalized_by', record.finalized_by_name || record.finalized_by_email);
        textField('finalized_at', AgreementApi.formatDate(record.finalized_at));
        textField('document', record.signed_document_name);
        textField('public_announcement_url', record.public_announcement_url);
        textField('ceremony_notes', record.ceremony_notes);

        elements.signatoryRows.replaceChildren();
        (record.signatory_snapshot || []).forEach((signatory) => {
            const row = document.createElement('tr');
            [signatory.party_type, signatory.full_name, signatory.job_title, signatory.organization_name]
                .forEach((value) => {
                    const cell = document.createElement('td');
                    cell.textContent = value || '—';
                    row.append(cell);
                });
            elements.signatoryRows.append(row);
        });
        renderEvents(payload.status_events || []);
    }

    function renderEvents(events) {
        elements.eventList.replaceChildren();
        if (!events.length) {
            elements.eventList.textContent = 'No operational status transition has occurred yet.';
            elements.eventList.className = 'small text-secondary';
            return;
        }
        const list = document.createElement('ul');
        list.className = 'list-group list-group-flush';
        events.forEach((event) => {
            const item = document.createElement('li');
            item.className = 'list-group-item px-0';
            item.textContent = `${event.from_status} → ${event.to_status} as of ${event.effective_as_of} · ${event.reason}`;
            list.append(item);
        });
        elements.eventList.className = '';
        elements.eventList.append(list);
    }

    function renderDocuments(documents) {
        const current = elements.document.value;
        elements.document.replaceChildren();
        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = 'Select the final signed Agreement';
        elements.document.append(prompt);
        documents
            .filter((item) => item.document_type === 'SIGNED_AGREEMENT')
            .forEach((item) => {
                const option = document.createElement('option');
                option.value = item.document_id;
                option.textContent = item.file_name;
                elements.document.append(option);
            });
        if ([...elements.document.options].some((option) => option.value === current)) {
            elements.document.value = current;
        }
    }

    function prefillDates() {
        elements.signingDate.value ||= state.agreement.signing_date || '';
        elements.effectiveDate.value ||= state.agreement.effective_date || state.agreement.start_date || '';
        elements.expiryDate.value ||= state.agreement.end_date || '';
        elements.announcement.value ||= state.agreement.signing_link || '';
    }

    function partnerOptions(select, selected = '') {
        select.replaceChildren();
        (state.agreement.partners || []).forEach((partner) => {
            const option = document.createElement('option');
            option.value = partner.partner_id;
            option.textContent = partner.organization_name;
            select.append(option);
        });
        select.value = selected || select.options[0]?.value || '';
    }

    function addSignatory(party = 'PARTNER') {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-3 signing-editor-row';
        row.innerHTML = `
            <div class="col-md-2"><label class="form-label small">Party</label><select class="form-select" data-signer-party><option value="UOB">UOB</option><option value="PARTNER">Partner</option></select></div>
            <div class="col-md-2"><label class="form-label small">Partner</label><select class="form-select" data-signer-partner></select></div>
            <div class="col-md-2"><label class="form-label small">Full name</label><input class="form-control" required data-signer-name></div>
            <div class="col-md-2"><label class="form-label small">Job title</label><input class="form-control" required data-signer-title></div>
            <div class="col-md-3"><label class="form-label small">Organization</label><input class="form-control" required data-signer-organization></div>
            <div class="col-md-1 d-grid"><button class="btn btn-outline-danger" type="button" aria-label="Remove signatory" data-remove-signer>×</button></div>`;
        const partySelect = row.querySelector('[data-signer-party]');
        const partnerSelect = row.querySelector('[data-signer-partner]');
        const organization = row.querySelector('[data-signer-organization]');
        partySelect.value = party;
        partnerOptions(partnerSelect);

        function syncParty() {
            const isUob = partySelect.value === 'UOB';
            partnerSelect.disabled = isUob;
            if (isUob) {
                organization.value = 'University of Bahrain';
            } else {
                const partner = (state.agreement.partners || []).find(
                    (item) => String(item.partner_id) === partnerSelect.value
                );
                organization.value = partner?.organization_name || '';
            }
        }
        partySelect.addEventListener('change', syncParty);
        partnerSelect.addEventListener('change', syncParty);
        row.querySelector('[data-remove-signer]').addEventListener('click', () => row.remove());
        syncParty();
        elements.editor.append(row);
    }

    function ensureInitialSignatories() {
        if (elements.editor.children.length) return;
        addSignatory('UOB');
        addSignatory('PARTNER');
    }

    function signatories() {
        return [...elements.editor.querySelectorAll('.signing-editor-row')].map((row) => ({
            party_type: row.querySelector('[data-signer-party]').value,
            partner_id: row.querySelector('[data-signer-party]').value === 'PARTNER'
                ? row.querySelector('[data-signer-partner]').value
                : null,
            full_name: row.querySelector('[data-signer-name]').value.trim(),
            job_title: row.querySelector('[data-signer-title]').value.trim(),
            organization_name: row.querySelector('[data-signer-organization]').value.trim()
        }));
    }

    function setBusy(busy) {
        state.busy = busy;
        elements.finalize.disabled = busy;
        elements.finalizeLabel.textContent = busy ? 'Finalizing…' : 'Finalize signing';
        elements.finalizeSpinner.classList.toggle('d-none', !busy);
    }

    async function reload() {
        const payload = await AgreementApi.agreementOperations(state.agreementId);
        renderRecord(payload);
    }

    async function initialize() {
        try {
            state.agreementId = id();
            await AgreementApi.requireSession('VIEW_AGREEMENT');
            state.agreement = await AgreementApi.agreement(state.agreementId);
            await reload();
        } catch (error) {
            elements.loading.classList.add('d-none');
            setMessage(elements.alert, error.message || 'Signing information could not be loaded.');
        }
    }

    elements.add.addEventListener('click', () => addSignatory('PARTNER'));
    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (state.busy || !elements.form.reportValidity()) return;
        if (!window.confirm('Finalize this signing record? It cannot be edited or removed afterward.')) return;
        clearMessages();
        setBusy(true);
        try {
            const result = await AgreementApi.finalizeAgreementSigning(state.agreementId, {
                signing_date: elements.signingDate.value,
                effective_date: elements.effectiveDate.value,
                expiry_date: elements.expiryDate.value,
                signed_document_id: elements.document.value,
                venue: elements.venue.value.trim(),
                public_announcement_url: elements.announcement.value.trim(),
                ceremony_notes: elements.notes.value.trim(),
                signatories: signatories()
            });
            await reload();
            setMessage(
                elements.feedback,
                result.status === 'ACTIVE'
                    ? 'Signing finalized and the Agreement is now active.'
                    : 'Signing finalized. Activation is scheduled for the effective date.'
            );
        } catch (error) {
            setMessage(elements.alert, error.message || 'Signing could not be finalized.');
        } finally {
            setBusy(false);
        }
    });

    window.addEventListener('agreement-documents-changed', (event) => {
        if (String(event.detail?.agreementId) === String(state.agreementId) && state.agreement) {
            reload().catch(() => {});
        }
    });

    initialize();
})();
