(function () {
    'use strict';

    const state = {
        agreements: [],
        query: '',
        status: ''
    };

    const elements = {
        alert: document.getElementById('agreement-alert'),
        loading: document.getElementById('agreement-loading'),
        empty: document.getElementById('agreement-empty'),
        tableWrap: document.getElementById('agreement-table-wrap'),
        tableBody: document.getElementById('agreement-table-body'),
        search: document.getElementById('agreement-search'),
        status: document.getElementById('agreement-status'),
        summary: document.querySelector('[data-result-summary]'),
        create: document.querySelector('[data-create-agreement]')
    };

    function normalizeRows(rows) {
        const byId = new Map();

        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const id = String(row.agreement_id);

            if (!byId.has(id)) {
                byId.set(id, {
                    ...row,
                    partner_ids: []
                });
            }

            if (row.partner_id !== null && row.partner_id !== undefined) {
                const target = byId.get(id);
                const partnerId = String(row.partner_id);

                if (!target.partner_ids.includes(partnerId)) {
                    target.partner_ids.push(partnerId);
                }
            }
        });

        return Array.from(byId.values());
    }

    function filteredRows() {
        const query = state.query.toLowerCase();

        return state.agreements.filter((agreement) => {
            const statusMatches = !state.status
                || agreement.status === state.status;

            const searchValue = [
                agreement.agreement_id,
                agreement.title,
                agreement.agreement_type,
                agreement.status,
                ...agreement.partner_ids
            ].join(' ').toLowerCase();

            return statusMatches && (!query || searchValue.includes(query));
        });
    }

    function cell(text) {
        const td = document.createElement('td');
        td.textContent = text ?? '—';
        return td;
    }

    function render() {
        const rows = filteredRows();
        elements.tableBody.replaceChildren();
        elements.summary.textContent = `${rows.length} of ${state.agreements.length} Agreements`;

        elements.empty.classList.toggle('d-none', rows.length !== 0);
        elements.tableWrap.classList.toggle('d-none', rows.length === 0);

        rows.forEach((agreement) => {
            const tr = document.createElement('tr');
            tr.appendChild(cell(agreement.agreement_id));

            const titleCell = cell(agreement.title);
            titleCell.classList.add('agreement-title-cell');
            tr.appendChild(titleCell);

            tr.appendChild(cell(agreement.agreement_type));

            const statusCell = document.createElement('td');
            statusCell.appendChild(AgreementApi.createStatusBadge(agreement.status));
            tr.appendChild(statusCell);

            tr.appendChild(cell(
                agreement.partner_ids.length > 0
                    ? agreement.partner_ids.join(', ')
                    : '—'
            ));
            tr.appendChild(cell(AgreementApi.formatDate(agreement.updated_at)));

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
            link.textContent = 'View';
            actionCell.appendChild(link);
            tr.appendChild(actionCell);

            elements.tableBody.appendChild(tr);
        });
    }

    function loadStatusOptions() {
        const statuses = Array.from(new Set(
            state.agreements
                .map((agreement) => agreement.status)
                .filter(Boolean)
        )).sort();

        statuses.forEach((status) => {
            const option = document.createElement('option');
            option.value = status;
            option.textContent = status.replaceAll('_', ' ');
            elements.status.appendChild(option);
        });
    }

    function showError(error) {
        elements.loading.classList.add('d-none');
        elements.alert.textContent = error.message || 'Agreements could not be loaded.';
        elements.alert.classList.remove('d-none');
        elements.summary.textContent = 'Unable to load Agreements';
    }

    async function initialize() {
        try {
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');
            elements.create.classList.toggle(
                'd-none',
                !AgreementApi.hasPermission(user, 'CREATE_AGREEMENT')
            );

            const rows = await AgreementApi.agreements();
            state.agreements = normalizeRows(rows);

            loadStatusOptions();
            elements.search.disabled = false;
            elements.status.disabled = false;
            elements.loading.classList.add('d-none');
            render();
        } catch (error) {
            showError(error);
        }
    }

    elements.search.addEventListener('input', () => {
        state.query = elements.search.value.trim();
        render();
    });

    elements.status.addEventListener('change', () => {
        state.status = elements.status.value;
        render();
    });

    initialize();
})();

