(function () {
    'use strict';

    const elements = {
        alert: document.querySelector('[data-report-list-alert]'),
        loading: document.querySelector('[data-report-list-loading]'),
        empty: document.querySelector('[data-report-list-empty]'),
        table: document.querySelector('[data-report-list-table]'),
        body: document.querySelector('[data-report-list-body]'),
        filter: document.querySelector('[data-report-status-filter]'),
        dashboard: document.querySelector('[data-dashboard-link]')
    };
    let reports = [];

    function period(report) {
        return `${report.period_start} – ${report.period_end}`;
    }

    function render() {
        const selected = elements.filter.value;
        const filtered = reports.filter((report) => {
            if (!selected) return true;
            if (selected === 'OVERDUE') return report.is_overdue === true;
            return report.status === selected;
        });
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
            if (report.is_overdue === true) {
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

    elements.filter.addEventListener('change', render);

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
            render();
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'Performance reports could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    })();
})();
