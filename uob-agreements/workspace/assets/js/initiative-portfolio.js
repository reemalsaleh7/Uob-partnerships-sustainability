(function () {
    'use strict';

    const loading = document.querySelector('[data-portfolio-loading]');
    const content = document.querySelector('[data-portfolio-content]');
    const alert = document.querySelector('[data-portfolio-alert]');
    const body = document.querySelector('[data-portfolio-body]');
    const empty = document.querySelector('[data-portfolio-empty]');
    const search = document.querySelector('[data-portfolio-search]');
    const approvedOptions = document.querySelector(
        '[data-approved-portfolio-options]'
    );
    const approvedButton = document.querySelector(
        '[data-approved-portfolio-button]'
    );
    const existingLink = document.querySelector(
        '[data-register-existing-initiative]'
    );
    let initiatives = [];

    function showError(error) {
        alert.textContent = error?.message
            || 'Final Initiatives could not be loaded.';
        alert.classList.remove('d-none');
    }

    function text(value, fallback = '—') {
        const normalized = String(value ?? '').trim();
        return normalized === '' ? fallback : normalized;
    }

    function render() {
        const term = search.value.trim().toLowerCase();
        const filtered = initiatives.filter((initiative) => {
            if (term === '') {
                return true;
            }
            return [
                initiative.title,
                initiative.initiative_code,
                initiative.creator_name,
                initiative.initiative_type,
                initiative.source_request_code,
                initiative.legacy_reference,
                initiative.record_origin
            ].some((value) =>
                String(value || '').toLowerCase().includes(term)
            );
        });

        body.replaceChildren();
        empty.classList.toggle('d-none', filtered.length !== 0);

        filtered.forEach((initiative) => {
            const row = document.createElement('tr');
            row.style.cursor = 'pointer';
            const href = `initiative-view.php?id=${
                encodeURIComponent(initiative.initiative_id)
            }`;

            const initiativeCell = document.createElement('td');
            const title = document.createElement('strong');
            title.textContent = text(initiative.title);
            const code = document.createElement('div');
            code.className = 'small text-secondary';
            code.textContent = text(
                initiative.initiative_code,
                `Initiative #${initiative.initiative_id}`
            );
            initiativeCell.append(title, code);

            if (initiative.record_origin === 'LEGACY') {
                const origin = document.createElement('span');
                origin.className = 'initiative-origin-badge';
                origin.textContent = initiative.legacy_reference
                    ? `Existing · ${initiative.legacy_reference}`
                    : 'Existing record';
                initiativeCell.append(origin);
            }

            const owner = document.createElement('td');
            owner.textContent = text(initiative.creator_name);

            const type = document.createElement('td');
            type.textContent = text(initiative.initiative_type)
                .replaceAll('_', ' ');

            const status = document.createElement('td');
            status.append(
                AgreementApi.createStatusBadge(initiative.status)
            );

            const updated = document.createElement('td');
            updated.textContent = AgreementApi.formatDate(
                initiative.updated_at
            );

            const action = document.createElement('td');
            action.className = 'text-end';
            const open = document.createElement('a');
            open.className = 'btn btn-sm btn-outline-primary';
            open.href = href;
            open.textContent = 'Open';
            action.append(open);

            row.append(
                initiativeCell,
                owner,
                type,
                status,
                updated,
                action
            );

            row.addEventListener('click', (event) => {
                if (event.target.closest('a, button')) {
                    return;
                }
                window.location.assign(href);
            });

            body.append(row);
        });
    }

    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true';
    }

    function renderApprovedRequests(requests) {
        if (!approvedOptions || !approvedButton) {
            return;
        }

        const eligible = requests.filter((request) => (
            booleanValue(request.can_convert)
            && ['APPROVED', 'CONVERTING'].includes(
                String(request.status || '').toUpperCase()
            )
        ));

        approvedOptions.replaceChildren();

        if (eligible.length === 0) {
            const item = document.createElement('li');
            const empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-secondary';
            empty.textContent = 'No approved requests are available for conversion.';
            item.append(empty);
            approvedOptions.append(item);
            approvedButton.classList.add('disabled');
            approvedButton.setAttribute('aria-disabled', 'true');
            return;
        }

        approvedButton.classList.remove('disabled');
        approvedButton.removeAttribute('aria-disabled');

        eligible.forEach((request) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.className = 'dropdown-item initiative-approved-option';
            link.href = `add-initiative-approved.php?request_id=${
                encodeURIComponent(request.request_id)
            }`;

            const title = document.createElement('strong');
            title.textContent = text(request.title);
            const detail = document.createElement('small');
            detail.textContent = `${text(request.request_code)} · ${
                request.status === 'CONVERTING'
                    ? 'Continue draft'
                    : 'Create Final Initiative'
            }`;
            link.append(title, detail);
            item.append(link);
            approvedOptions.append(item);
        });
    }

    async function initialize() {
        try {
            await AgreementApi.requireSession();
            const [payload, requestPayload, access] =
                await Promise.all([
                    AgreementApi.request(
                        '/initiative-requests/converted-initiatives'
                    ),
                    AgreementApi.request('/initiative-requests'),
                    AgreementApi.request('/initiative-access')
                ]);
            initiatives = Array.isArray(payload)
                ? payload
                : (payload?.items || []);
            const requests = Array.isArray(requestPayload)
                ? requestPayload
                : (requestPayload?.items || []);
            renderApprovedRequests(requests);
            existingLink?.classList.toggle(
                'd-none',
                !booleanValue(access?.can_create_initiative)
            );
            content.classList.remove('d-none');
            render();
            search.addEventListener('input', render);
        } catch (error) {
            showError(error);
        } finally {
            loading.classList.add('d-none');
        }
    }

    initialize();
}());
