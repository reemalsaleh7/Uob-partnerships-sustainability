(function () {
    'use strict';

    const state = {
        agreements: [],
        query: '',
        status: '',
        scope: 'ACTIVE',
        user: null,
        canCreateInitiative: false
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
        create: document.querySelector('[data-create-agreement]'),
        description: document.querySelector('[data-agreement-page-description]'),
        facultyNote: document.querySelector('[data-faculty-agreement-note]'),
        scopeButtons: [...document.querySelectorAll('[data-agreement-scope]')]
    };

    function normalizeRows(rows) {
        const byId = new Map();

        (Array.isArray(rows) ? rows : []).forEach((row) => {
            const id = String(row.agreement_id);

            if (!byId.has(id)) {
                byId.set(id, {
                    ...row,
                    partner_ids: [],
                    partner_names: []
                });
            }

            if (row.partner_id !== null && row.partner_id !== undefined) {
                const target = byId.get(id);
                const partnerId = String(row.partner_id);

                if (!target.partner_ids.includes(partnerId)) {
                    target.partner_ids.push(partnerId);
                }

                if (row.partner_name && !target.partner_names.includes(row.partner_name)) {
                    target.partner_names.push(row.partner_name);
                }
            }
        });

        return Array.from(byId.values());
    }

    function filteredRows() {
        const query = state.query.toLowerCase();

        return state.agreements.filter((agreement) => {
            const isMine = Number(agreement.created_by) === Number(state.user?.user_id);
            const scopeMatches = state.scope === 'ALL'
                || (state.scope === 'ACTIVE' && agreement.status === 'ACTIVE')
                || (state.scope === 'MY_ACTIVE' && isMine && agreement.status === 'ACTIVE')
                || (state.scope === 'MINE' && isMine);
            const statusMatches = !state.status
                || agreement.status === state.status;

            const searchValue = [
                agreement.agreement_id,
                agreement.title,
                agreement.agreement_type,
                agreement.status,
                ...agreement.partner_ids,
                ...agreement.partner_names
            ].join(' ').toLowerCase();

            return scopeMatches
                && statusMatches
                && (!query || searchValue.includes(query));
        });
    }

    function scopeLabel() {
        return {
            ACTIVE: 'active University Agreements',
            MY_ACTIVE: 'active Agreements created by you',
            MINE: 'Agreements created by you',
            ALL: 'visible Agreements'
        }[state.scope] || 'Agreements';
    }

    function updateScopeCounts() {
        const mine = state.agreements.filter(
            (agreement) => Number(agreement.created_by) === Number(state.user?.user_id)
        );
        const counts = {
            ACTIVE: state.agreements.filter((agreement) => agreement.status === 'ACTIVE').length,
            MY_ACTIVE: mine.filter((agreement) => agreement.status === 'ACTIVE').length,
            MINE: mine.length,
            ALL: state.agreements.length
        };

        Object.entries(counts).forEach(([scope, value]) => {
            const target = document.querySelector(`[data-scope-count="${scope}"]`);
            if (target) target.textContent = String(value);
        });
    }

    function selectScope(scope) {
        state.scope = scope;
        elements.scopeButtons.forEach((button) => {
            const selected = button.dataset.agreementScope === scope;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        render();
    }

    function cell(text) {
        const td = document.createElement('td');
        td.textContent = text ?? '—';
        return td;
    }

    function render() {
        const rows = filteredRows();
        elements.tableBody.replaceChildren();
        elements.summary.textContent = `${rows.length} ${scopeLabel()}`;

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

            const ownerCell = document.createElement('td');
            const isMine = Number(agreement.created_by) === Number(state.user?.user_id);
            const ownerBadge = document.createElement('span');
            ownerBadge.className = `agreement-owner-badge${isMine ? '' : ' is-institutional'}`;
            ownerBadge.textContent = isMine ? 'Created by you' : 'University Agreement';
            ownerCell.append(ownerBadge);
            tr.appendChild(ownerCell);

            tr.appendChild(cell(
                agreement.partner_names.length > 0
                    ? agreement.partner_names.join(', ')
                    : '—'
            ));
            tr.appendChild(cell(AgreementApi.formatDate(agreement.updated_at)));

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const actions = document.createElement('div');
            actions.className = 'agreement-row-actions';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
            link.textContent = 'View';
            actions.appendChild(link);

            if (state.canCreateInitiative && agreement.status === 'ACTIVE') {
                const initiative = document.createElement('a');
                initiative.className = 'btn btn-sm btn-primary';
                initiative.href = '#';
                initiative.dataset.legacyInitiative = `request-initiative.php?lang=en&agreement_id=${encodeURIComponent(agreement.agreement_id)}&agreement_code=${encodeURIComponent(agreement.agreement_code || '')}`;
                initiative.textContent = 'Use for Initiative';
                actions.appendChild(initiative);
            }

            actionCell.appendChild(actions);
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
            state.user = user;
            state.canCreateInitiative = AgreementApi.hasPermission(user, 'CREATE_INITIATIVE')
                || (Array.isArray(user.roles) && user.roles.includes('Initiative Creator'));
            const canCreateAgreement = AgreementApi.hasPermission(user, 'CREATE_AGREEMENT');
            elements.create.classList.toggle(
                'd-none',
                !canCreateAgreement
            );

            elements.scopeButtons.forEach((button) => {
                if (['MY_ACTIVE', 'MINE'].includes(button.dataset.agreementScope)) {
                    button.classList.toggle('d-none', !canCreateAgreement);
                }
            });
            elements.facultyNote.classList.toggle('d-none', !state.canCreateInitiative);
            if (state.canCreateInitiative && !canCreateAgreement) {
                elements.description.textContent = 'Explore active University partnerships, review their objectives, and choose one as the context for a new Initiative.';
            }

            const rows = await AgreementApi.agreements();
            state.agreements = normalizeRows(rows);

            updateScopeCounts();
            loadStatusOptions();
            elements.search.disabled = false;
            elements.status.disabled = false;
            elements.loading.classList.add('d-none');
            selectScope('ACTIVE');
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

    elements.scopeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            selectScope(button.dataset.agreementScope || 'ACTIVE');
        });
    });

    initialize();
})();
