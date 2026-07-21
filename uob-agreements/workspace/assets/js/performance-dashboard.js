(function () {
    'use strict';

    const elements = {
        year: document.querySelector('[data-dashboard-year]'),
        alert: document.querySelector('[data-dashboard-alert]'),
        loading: document.querySelector('[data-dashboard-loading]'),
        content: document.querySelector('[data-dashboard-content]'),
        agreementKpis: document.querySelector('[data-agreement-kpis]'),
        reportKpis: document.querySelector('[data-report-kpis]'),
        deadlines: document.querySelector('[data-dashboard-deadlines]'),
        metrics: document.querySelector('[data-dashboard-metrics]'),
        programs: document.querySelector('[data-program-health]')
    };

    function number(value) {
        return new Intl.NumberFormat('en-BH', { maximumFractionDigits: 2 }).format(Number(value || 0));
    }

    function card(label, value, tone = '') {
        const column = document.createElement('div');
        column.className = 'col-6 col-lg-3';
        const panel = document.createElement('div');
        panel.className = `workspace-card dashboard-kpi h-100 ${tone}`.trim();
        const strong = document.createElement('strong');
        strong.className = 'dashboard-kpi-value';
        strong.textContent = number(value);
        const text = document.createElement('span');
        text.className = 'small text-secondary';
        text.textContent = label;
        panel.append(strong, text);
        column.append(panel);
        return column;
    }

    function render(payload) {
        const agreements = payload.agreements || {};
        elements.agreementKpis.replaceChildren(
            card('Active Agreements', agreements.active_agreements, 'kpi-active'),
            card('Scheduled Agreements', agreements.scheduled_agreements),
            card('Expired Agreements', agreements.expired_agreements),
            card('Reportable Agreements', agreements.reportable_agreements)
        );
        const reports = payload.reports || {};
        elements.reportKpis.replaceChildren(
            card('Accepted reports', reports.accepted, 'kpi-active'),
            card('Awaiting review', reports.submitted),
            card('Returned reports', reports.returned),
            card('Overdue reports', reports.overdue, Number(reports.overdue) > 0 ? 'kpi-danger' : '')
        );

        elements.deadlines.replaceChildren();
        (payload.deadlines || []).forEach((report) => {
            const row = document.createElement('tr');
            const title = document.createElement('td');
            title.textContent = report.agreement_title;
            const due = document.createElement('td');
            due.textContent = report.due_date;
            if (report.is_overdue === true) due.className = 'text-danger fw-semibold';
            const status = document.createElement('td');
            status.append(AgreementApi.createStatusBadge(report.status));
            const action = document.createElement('td');
            action.className = 'text-end';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = `performance-report.php?id=${encodeURIComponent(report.performance_report_id)}`;
            link.textContent = 'Open';
            action.append(link);
            row.append(title, due, status, action);
            elements.deadlines.append(row);
        });
        if (!elements.deadlines.children.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'text-secondary text-center py-4';
            cell.textContent = 'No due or overdue reports in this view.';
            row.append(cell);
            elements.deadlines.append(row);
        }

        elements.metrics.replaceChildren();
        (payload.metrics || []).forEach((metric) => {
            const planned = Number(metric.planned_value || 0);
            const actual = Number(metric.actual_value || 0);
            const row = document.createElement('tr');
            [metric.metric_label, `${number(planned)} ${metric.unit || ''}`, `${number(actual)} ${metric.unit || ''}`, planned > 0 ? `${number(actual / planned * 100)}%` : '—']
                .forEach((value) => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.append(cell);
                });
            elements.metrics.append(row);
        });
        if (!elements.metrics.children.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 4;
            cell.className = 'text-secondary text-center py-4';
            cell.textContent = 'No accepted metric results are available for this year.';
            row.append(cell);
            elements.metrics.append(row);
        }

        elements.programs.replaceChildren();
        (payload.programs || []).forEach((item) => {
            const line = document.createElement('div');
            line.className = 'd-flex justify-content-between align-items-center border-bottom py-2';
            const label = document.createElement('span');
            label.textContent = String(item.progress_status).replaceAll('_', ' ');
            const count = document.createElement('strong');
            count.textContent = number(item.program_count);
            line.append(label, count);
            elements.programs.append(line);
        });
        if (!elements.programs.children.length) {
            elements.programs.textContent = 'No accepted program updates are available.';
            elements.programs.className = 'form-section text-secondary';
        }
        elements.loading.classList.add('d-none');
        elements.content.classList.remove('d-none');
    }

    async function load() {
        elements.alert.classList.add('d-none');
        elements.loading.classList.remove('d-none');
        elements.content.classList.add('d-none');
        try {
            render(await AgreementApi.performanceDashboard(elements.year.value));
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'The dashboard could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    }

    (async function initialize() {
        try {
            await AgreementApi.requireSession('VIEW_AGREEMENT_DASHBOARD');
            const current = new Date().getFullYear();
            for (let year = current + 1; year >= current - 5; year -= 1) {
                const option = document.createElement('option');
                option.value = year;
                option.textContent = year;
                if (year === current) option.selected = true;
                elements.year.append(option);
            }
            elements.year.addEventListener('change', load);
            await load();
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'The dashboard could not be opened.';
            elements.alert.classList.remove('d-none');
        }
    })();
})();
