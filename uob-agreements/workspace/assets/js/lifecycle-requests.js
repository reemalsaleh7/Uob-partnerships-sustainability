(function () {
    'use strict';
    const alert = document.getElementById('lifecycle-alert');
    const loading = document.getElementById('lifecycle-loading');
    const list = document.getElementById('lifecycle-list');
    const empty = document.getElementById('lifecycle-empty');
    const rows = document.querySelector('[data-request-rows]');
    const count = document.querySelector('[data-request-count]');
    const filters = {
        search: document.getElementById('lifecycle-search'),
        status: document.getElementById('lifecycle-status'),
        type: document.getElementById('lifecycle-type'),
        updatedFrom: document.getElementById('lifecycle-updated-from'),
        updatedTo: document.getElementById('lifecycle-updated-to'),
        clear: document.querySelector('[data-clear-lifecycle-filters]')
    };
    let requests = [];

    const searchSchema = {
        any: (request) => [
            request.lifecycle_request_id,
            request.request_type,
            request.status,
            request.agreement_id,
            request.agreement_code,
            request.agreement_title,
            request.requester_name,
            request.justification,
            request.activities_summary,
            request.achieved_value,
            request.amendment_type,
            request.amendment_reason,
            request.terms_to_amend,
            request.termination_reason,
            request.proposed_start_date,
            request.proposed_end_date,
            request.proposed_termination_date,
            request.updated_at
        ],
        id: 'lifecycle_request_id',
        request: 'lifecycle_request_id',
        agreement: (request) => [request.agreement_id, request.agreement_title],
        code: 'agreement_code',
        type: 'request_type',
        status: 'status',
        requester: (request) => [request.requested_by, request.requester_name],
        start: 'proposed_start_date',
        end: 'proposed_end_date',
        termination: (request) => [
            request.termination_reason,
            request.proposed_termination_date
        ],
        updated: 'updated_at'
    };

    const advancedFilters = UobAdvancedSearch.createFilterController({
        root: '[data-lifecycle-search-filters]',
        schema: searchSchema,
        fields: [
            { key: 'agreement', label: 'Agreement', placeholder: 'Enter an Agreement title or ID' },
            { key: 'code', label: 'Agreement code', placeholder: 'Enter an Agreement code' },
            { key: 'type', label: 'Request type', placeholder: 'For example, Renewal' },
            { key: 'status', label: 'Status', placeholder: 'For example, Under review' },
            { key: 'requester', label: 'Requested by', placeholder: 'Enter a person’s name' },
            { key: 'start', label: 'Proposed start date', type: 'date' },
            { key: 'end', label: 'Proposed end date', type: 'date' },
            { key: 'termination', label: 'Termination details', placeholder: 'Enter a reason or date' },
            { key: 'updated', label: 'Last updated', type: 'date' }
        ],
        onChange: render
    });

    function cell(value) {
        const td = document.createElement('td');
        td.textContent = value ?? '—';
        return td;
    }

    function option(select, value) {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = String(value).replaceAll('_', ' ');
        select.append(item);
    }

    function loadFilterOptions() {
        [...new Set(requests.map((request) => request.status).filter(Boolean))]
            .sort().forEach((value) => option(filters.status, value));
        [...new Set(requests.map((request) => request.request_type).filter(Boolean))]
            .sort().forEach((value) => option(filters.type, value));
    }

    function filteredRequests() {
        return requests.filter((request) =>
            (!filters.status.value || request.status === filters.status.value)
            && (!filters.type.value || request.request_type === filters.type.value)
            && UobAdvancedSearch.inDateRange(
                request.updated_at,
                filters.updatedFrom.value,
                filters.updatedTo.value
            )
            && UobAdvancedSearch.matches(
                request,
                filters.search.value.trim(),
                searchSchema
            )
            && advancedFilters.matchesRules(request)
        );
    }

    function render() {
        const visible = filteredRequests();
        loading.classList.add('d-none');
        empty.classList.toggle('d-none', visible.length !== 0);
        list.classList.toggle('d-none', requests.length === 0);
        count.textContent = `${visible.length} of ${requests.length} ${requests.length === 1 ? 'request' : 'requests'}`;
        advancedFilters.refresh();
        rows.replaceChildren();
        visible.forEach((request) => {
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
    }

    function setFiltersEnabled(enabled) {
        Object.values(filters).forEach((element) => {
            element.disabled = !enabled;
        });
    }

    function clearFilters() {
        filters.search.value = '';
        filters.status.value = '';
        filters.type.value = '';
        filters.updatedFrom.value = '';
        filters.updatedTo.value = '';
        advancedFilters.clearRules();
        render();
    }

    async function initialize() {
        try {
            await AgreementApi.requireSession('VIEW_AGREEMENT');
            requests = await AgreementApi.lifecycleRequests();
            loadFilterOptions();
            setFiltersEnabled(true);
            render();
        } catch (error) {
            loading.classList.add('d-none');
            alert.textContent = error.message || 'Lifecycle requests could not be loaded.';
            alert.classList.remove('d-none');
            alert.focus();
        }
    }

    filters.search.addEventListener('input', render);
    filters.status.addEventListener('change', render);
    filters.type.addEventListener('change', render);
    filters.updatedFrom.addEventListener('change', render);
    filters.updatedTo.addEventListener('change', render);
    filters.clear.addEventListener('click', clearFilters);

    initialize();
})();
