<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Performance reports', 'performance');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Agreement monitoring</p>
        <h1 class="display-6 mb-3">Performance reports</h1>
        <p class="lead text-secondary mb-0">
            Prepare annual reports, respond to returns, and review submitted evidence.
        </p>
    </div>
    <a class="btn btn-outline-primary align-self-lg-end d-none" href="performance-dashboard.php" data-dashboard-link>
        Open management dashboard
    </a>
</section>

<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-report-list-alert></div>

<section class="workspace-card mt-4" aria-labelledby="report-register-title">
    <div class="workspace-card-header">
        <div>
            <h2 id="report-register-title" class="h5 mb-1">Reporting queue</h2>
            <p class="small text-secondary mb-0">Draft, due, returned, submitted, and accepted reporting periods.</p>
        </div>
        <select class="form-select report-status-filter" aria-label="Filter by status" data-report-status-filter>
            <option value="">All statuses</option>
            <option value="DRAFT">Draft</option>
            <option value="RETURNED">Returned</option>
            <option value="SUBMITTED">Submitted</option>
            <option value="ACCEPTED">Accepted</option>
            <option value="OVERDUE">Overdue</option>
        </select>
    </div>
    <div class="loading-state" data-report-list-loading>
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading performance reports…</span>
    </div>
    <div class="empty-state d-none" data-report-list-empty>
        <p class="text-secondary mb-0">No reporting periods match this view.</p>
    </div>
    <div class="table-responsive d-none" data-report-list-table>
        <table class="table workspace-table align-middle mb-0">
            <thead><tr><th>Agreement</th><th>Period</th><th>Deadline</th><th>Status</th><th></th></tr></thead>
            <tbody data-report-list-body></tbody>
        </table>
    </div>
</section>

<?php workspaceFooter(['assets/js/performance-reports.js']); ?>
