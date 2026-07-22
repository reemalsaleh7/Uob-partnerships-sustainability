<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Performance dashboard', 'performance-dashboard');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Delivery and accountability</p>
        <h1 class="display-6 mb-2">Performance dashboard</h1>
        <p class="text-secondary mb-3">See whether Agreements are active, reports are on time, and promised outcomes are being achieved.</p>
        <span class="performance-scope-note" data-dashboard-scope>Loading reporting scope…</span>
    </div>
    <div class="align-self-lg-end">
        <label class="form-label small fw-semibold" for="dashboard-year">Reporting year</label>
        <select id="dashboard-year" class="form-select" data-dashboard-year></select>
    </div>
</section>

<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-dashboard-alert></div>
<div class="loading-state" data-dashboard-loading><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading dashboard…</span></div>

<div class="d-none" data-dashboard-content>
    <div class="dashboard-section-title"><div><h2>Portfolio status</h2><p>Operational state and reporting compliance.</p></div></div>
    <section class="row g-3" aria-label="Agreement status summary" data-agreement-kpis></section>
    <section class="row g-3 mt-1" aria-label="Report compliance summary" data-report-kpis></section>

    <div class="row g-4 mt-1">
        <div class="col-xl-8"><section class="workspace-card h-100" aria-labelledby="deadline-title">
            <div class="workspace-card-header"><div><h2 id="deadline-title" class="h5 mb-1">Due and overdue reports</h2><p class="small text-secondary mb-0">Open deadlines through the next 30 days.</p></div></div>
            <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Agreement</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody data-dashboard-deadlines></tbody></table></div>
        </section></div>
        <div class="col-xl-4"><section class="workspace-card h-100" aria-labelledby="program-health-title">
            <div class="workspace-card-header"><h2 id="program-health-title" class="h5 mb-0">Executive-program health</h2></div>
            <div class="form-section" data-program-health></div>
        </section></div>
    </div>

    <section class="workspace-card mt-4" aria-labelledby="metric-outcomes-title">
        <div class="workspace-card-header"><div><h2 id="metric-outcomes-title" class="h5 mb-1">Actual outcomes against targets</h2><p class="small text-secondary mb-0">Only accepted reports count toward achieved results.</p></div></div>
        <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Outcome</th><th>Target</th><th>Actual</th><th class="metric-achievement-column">Achievement</th></tr></thead><tbody data-dashboard-metrics></tbody></table></div>
    </section>
</div>

<?php workspaceFooter(['assets/js/performance-dashboard.js']); ?>
