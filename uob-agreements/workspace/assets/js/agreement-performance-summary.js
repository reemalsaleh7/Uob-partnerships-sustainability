(function () {
    'use strict';

    const root = document.querySelector('[data-performance-summary]');
    if (!root) return;
    const loading = root.querySelector('[data-performance-summary-loading]');
    const empty = root.querySelector('[data-performance-summary-empty]');
    const table = root.querySelector('[data-performance-summary-table]');
    const body = root.querySelector('[data-performance-summary-body]');

    function agreementId() {
        const value = new URLSearchParams(window.location.search).get('id');
        return value && /^\d+$/.test(value) ? value : null;
    }

    (async function initialize() {
        try {
            const user = await AgreementApi.me();
            const permitted = AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
                || AgreementApi.hasPermission(user, 'REVIEW_AGREEMENT_REPORTS');
            if (!permitted || !agreementId()) return;
            root.classList.remove('d-none');
            const payload = await AgreementApi.agreementPerformanceReports(agreementId());
            const reports = payload.reports || [];
            body.replaceChildren();
            reports.forEach((report) => {
                const row = document.createElement('tr');
                const period = document.createElement('td');
                period.textContent = `${report.period_start} – ${report.period_end}`;
                const due = document.createElement('td');
                due.textContent = report.due_date;
                if (report.is_overdue === true) due.className = 'text-danger fw-semibold';
                const status = document.createElement('td');
                status.append(AgreementApi.createStatusBadge(report.status));
                const action = document.createElement('td');
                action.className = 'text-end';
                const link = document.createElement('a');
                link.href = `performance-report.php?id=${encodeURIComponent(report.performance_report_id)}`;
                link.className = 'btn btn-sm btn-outline-primary';
                link.textContent = 'Open';
                action.append(link);
                row.append(period, due, status, action);
                body.append(row);
            });
            loading.classList.add('d-none');
            empty.classList.toggle('d-none', reports.length !== 0);
            table.classList.toggle('d-none', reports.length === 0);
        } catch (error) {
            root.classList.add('d-none');
        }
    })();
})();
