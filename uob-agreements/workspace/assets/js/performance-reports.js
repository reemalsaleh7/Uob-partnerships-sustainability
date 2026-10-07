(function () {
    'use strict';

    const elements = {
        alert: document.querySelector('[data-report-list-alert]'),
        loading: document.querySelector('[data-report-list-loading]'),
        empty: document.querySelector('[data-report-list-empty]'),
        table: document.querySelector('[data-report-list-table]'),
        body: document.querySelector('[data-report-list-body]'),
        search: document.querySelector('[data-report-search]'),
        status: document.querySelector('[data-report-status-filter]'),
        year: document.querySelector('[data-report-year-filter]'),
        dueFrom: document.querySelector('[data-report-due-from]'),
        dueTo: document.querySelector('[data-report-due-to]'),
        clear: document.querySelector('[data-clear-report-filters]'),
        count: document.querySelector('[data-report-result-count]'),
        dashboard: document.querySelector('[data-dashboard-link]')
    };
    let reports = [];

    const searchSchema = {
        any: (report) => [
            report.performance_report_id,
            report.agreement_id,
            report.agreement_title,
            report.agreement_code,
            report.status,
            report.period_start,
            report.period_end,
            report.reporting_year,
            report.due_date,
            report.creator_name,
            report.reviewer_name,
            report.executive_summary,
            report.challenges,
            report.next_period_plan,
            report.updated_at,
            isOverdue(report) ? 'overdue' : ''
        ],
        id: 'performance_report_id',
        report: 'performance_report_id',
        agreement: (report) => [report.agreement_id, report.agreement_title],
        code: 'agreement_code',
        status: 'status',
        creator: (report) => [report.created_by, report.creator_name],
        reviewer: (report) => [report.reviewed_by, report.reviewer_name],
        year: (report) => [report.reporting_year, String(report.period_end || '').slice(0, 4)],
        start: 'period_start',
        end: 'period_end',
        due: 'due_date',
        updated: 'updated_at',
        overdue: (report) => isOverdue(report) ? 'yes true overdue' : 'no false'
    };

    const advancedFilters = UobAdvancedSearch.createFilterController({
        root: '[data-report-search-filters]',
        schema: searchSchema,
        fields: [
            { key: 'agreement', label: 'Agreement', placeholder: 'Enter an Agreement title or ID' },
            { key: 'code', label: 'Agreement code', placeholder: 'Enter an Agreement code' },
            { key: 'status', label: 'Status', placeholder: 'For example, Submitted' },
            { key: 'creator', label: 'Created by', placeholder: 'Enter a person’s name' },
            { key: 'reviewer', label: 'Reviewed by', placeholder: 'Enter a person’s name' },
            { key: 'year', label: 'Reporting year', type: 'number', placeholder: 'For example, 2026' },
            { key: 'start', label: 'Period start', type: 'date' },
            { key: 'end', label: 'Period end', type: 'date' },
            { key: 'due', label: 'Due date', type: 'date' },
            { key: 'updated', label: 'Last updated', type: 'date' },
            { key: 'overdue', label: 'Overdue', placeholder: 'Enter yes or no' }
        ],
        onChange: render
    });

    function isOverdue(report) {
        return report.is_overdue === true
            || report.is_overdue === 1
            || report.is_overdue === '1'
            || report.is_overdue === 't'
            || report.is_overdue === 'true';
    }

    function period(report) {
        return `${report.period_start} – ${report.period_end}`;
    }

    function render() {
        const selected = elements.status.value;
        const filtered = reports.filter((report) => {
            const statusMatches = !selected
                || (selected === 'OVERDUE'
                    ? isOverdue(report)
                    : report.status === selected);
            const reportYear = String(
                report.reporting_year || report.period_end || ''
            ).slice(0, 4);

            return statusMatches
                && (!elements.year.value || reportYear === elements.year.value)
                && UobAdvancedSearch.inDateRange(
                    report.due_date,
                    elements.dueFrom.value,
                    elements.dueTo.value
                )
                && UobAdvancedSearch.matches(
                    report,
                    elements.search.value.trim(),
                    searchSchema
                )
                && advancedFilters.matchesRules(report);
        });
        elements.count.textContent = `${filtered.length} of ${reports.length} ${reports.length === 1 ? 'report' : 'reports'}`;
        advancedFilters.refresh();
        elements.body.replaceChildren();
        filtered.forEach((report) => {
            const row = document.createElement('tr');
            const agreement = document.createElement('td');
            const title = document.createElement('a');
            title.href = `agreement.php?id=${encodeURIComponent(report.agreement_id)}`;
            title.className = 'fw-semibold text-decoration-none';
            title.textContent = report.agreement_title;
            agreement.append(title);
            if (report.agreement_code) {
                const code = document.createElement('span');
                code.className = 'd-block small text-secondary mt-1';
                code.textContent = report.agreement_code;
                agreement.append(code);
            }
            const periodCell = document.createElement('td');
            periodCell.textContent = period(report);
            const deadline = document.createElement('td');
            deadline.textContent = report.due_date;
            if (isOverdue(report)) {
                const overdue = document.createElement('span');
                overdue.className = 'd-block small text-danger fw-semibold mt-1';
                overdue.textContent = 'Overdue';
                deadline.append(overdue);
            }
            const status = document.createElement('td');
            status.append(AgreementApi.createStatusBadge(report.status));
            const action = document.createElement('td');
            action.className = 'text-end';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = `performance-report.php?id=${encodeURIComponent(report.performance_report_id)}`;
            link.textContent = report.status === 'SUBMITTED' ? 'Review' : 'Open';
            action.append(link);
            row.append(agreement, periodCell, deadline, status, action);
            elements.body.append(row);
        });
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', filtered.length !== 0);
        elements.table.classList.toggle('d-none', filtered.length === 0);
    }

    function loadYears() {
        const years = [...new Set(reports.map((report) =>
            String(report.reporting_year || report.period_end || '').slice(0, 4)
        ).filter((year) => /^\d{4}$/.test(year)))].sort().reverse();
        years.forEach((year) => {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            elements.year.append(option);
        });
    }

    function setFiltersEnabled(enabled) {
        [
            elements.search,
            elements.status,
            elements.year,
            elements.dueFrom,
            elements.dueTo,
            elements.clear
        ].forEach((element) => {
            element.disabled = !enabled;
        });
    }

    function clearFilters() {
        elements.search.value = '';
        elements.status.value = '';
        elements.year.value = '';
        elements.dueFrom.value = '';
        elements.dueTo.value = '';
        advancedFilters.clearRules();
        render();
    }

    elements.search.addEventListener('input', render);
    elements.status.addEventListener('change', render);
    elements.year.addEventListener('change', render);
    elements.dueFrom.addEventListener('change', render);
    elements.dueTo.addEventListener('change', render);
    elements.clear.addEventListener('click', clearFilters);

    (async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
            if (!AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
                && !AgreementApi.hasPermission(user, 'REVIEW_AGREEMENT_REPORTS')) {
                throw new AgreementApi.ApiError('You do not have permission to view performance reports.', 403, null);
            }
            const payload = await AgreementApi.performanceReports();
            reports = payload.reports || [];
            elements.dashboard.classList.toggle('d-none', payload.can_view_dashboard !== true);
            loadYears();
            setFiltersEnabled(true);
            render();
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'Performance reports could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    })();
})();
