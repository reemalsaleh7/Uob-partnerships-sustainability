<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Performance dashboard', 'performance-dashboard');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Management view</p>
        <h1 class="display-6 mb-3">Agreement performance dashboard</h1>
        <p class="lead text-secondary mb-0">Accepted outcomes, reporting compliance, deadlines, and executive-program health.</p>
    </div>
    <div class="align-self-lg-end">
        <label class="form-label small fw-semibold" for="dashboard-year">Reporting year</label>
        <select id="dashboard-year" class="form-select" data-dashboard-year></select>
    </div>
</section>

<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-dashboard-alert></div>
<div class="loading-state" data-dashboard-loading><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading dashboard…</span></div>

<div class="d-none" data-dashboard-content>
    <section class="row g-3 mt-1" aria-label="Agreement status summary" data-agreement-kpis></section>
    <section class="row g-3 mt-1" aria-label="Report compliance summary" data-report-kpis></section>

    <div class="row g-4 mt-1">
        <div class="col-lg-7"><section class="workspace-card h-100" aria-labelledby="deadline-title">
            <div class="workspace-card-header"><div><h2 id="deadline-title" class="h5 mb-1">Due and overdue reports</h2><p class="small text-secondary mb-0">Open deadlines through the next 30 days.</p></div></div>
            <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Agreement</th><th>Due</th><th>Status</th><th></th></tr></thead><tbody data-dashboard-deadlines></tbody></table></div>
        </section></div>
        <div class="col-lg-5"><section class="workspace-card h-100" aria-labelledby="program-health-title">
            <div class="workspace-card-header"><h2 id="program-health-title" class="h5 mb-0">Executive-program health</h2></div>
            <div class="form-section" data-program-health></div>
        </section></div>
    </div>

    <section class="workspace-card mt-4" aria-labelledby="metric-outcomes-title">
        <div class="workspace-card-header"><div><h2 id="metric-outcomes-title" class="h5 mb-1">Accepted outcome metrics</h2><p class="small text-secondary mb-0">Totals include accepted reports only.</p></div></div>
        <div class="table-responsive"><table class="table workspace-table align-middle mb-0"><thead><tr><th>Metric</th><th>Planned</th><th>Actual</th><th>Achievement</th></tr></thead><tbody data-dashboard-metrics></tbody></table></div>
    </section>
</div>

<?php workspaceFooter(['assets/js/performance-dashboard.js']); ?>
