(function () {
    'use strict';
    const alert = document.getElementById('lifecycle-alert');
    const loading = document.getElementById('lifecycle-loading');
    const list = document.getElementById('lifecycle-list');
    const empty = document.getElementById('lifecycle-empty');
    const rows = document.querySelector('[data-request-rows]');
    const count = document.querySelector('[data-request-count]');

    function cell(value) {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        return td;
    }

    async function initialize() {
        try {
            await AgreementApi.requireSession('VIEW_AGREEMENT');
            const requests = await AgreementApi.lifecycleRequests();
            loading.classList.add('d-none');
            empty.classList.toggle('d-none', requests.length !== 0);
            list.classList.toggle('d-none', requests.length === 0);
            count.textContent = requests.length === 1 ? '1 request' : `${requests.length} requests`;
            rows.replaceChildren();
            requests.forEach((request) => {
                const tr = document.createElement('tr');
                tr.append(
                    cell(`${String(request.request_type).replaceAll('_', ' ')} #${request.lifecycle_request_id}`),
                    cell(request.agreement_title || `Agreement #${request.agreement_id}`)
                );
                const status = document.createElement('td');
                status.append(AgreementApi.createStatusBadge(request.status));
                tr.append(status, cell(AgreementApi.formatDate(request.updated_at)));
                const action = document.createElement('td');
                action.className = 'text-end';
                const link = document.createElement('a');
                link.className = 'btn btn-sm btn-outline-primary';
                link.href = `lifecycle-request.php?id=${encodeURIComponent(request.lifecycle_request_id)}`;
                link.textContent = 'Open';
                action.append(link);
                tr.append(action);
                rows.append(tr);
            });
        } catch (error) {
            loading.classList.add('d-none');
            alert.textContent = error.message || 'Lifecycle requests could not be loaded.';
            alert.classList.remove('d-none');
            alert.focus();
        }
    }
    initialize();
})();
