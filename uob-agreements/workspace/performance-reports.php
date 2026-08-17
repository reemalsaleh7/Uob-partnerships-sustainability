<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader(
    'Annual reports',
    'performance',
    ['assets/css/performance-reports.css']
);
?>

<section class="page-heading performance-reports-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Agreement monitoring</p>
        <h1 class="display-6 mb-2">Annual reports</h1>
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
            <p class="small text-secondary mb-0" data-report-result-count>Draft, due, returned, submitted, and accepted reporting periods.</p>
        </div>
    </div>
    <div class="filter-bar progressive-search" data-report-search-filters aria-label="Advanced search">
        <div class="progressive-search-primary">
            <div class="progressive-search-field">
                <label class="form-label" for="report-search">Search reports</label>
                <div class="progressive-search-input-wrap">
                    <span class="progressive-search-icon" aria-hidden="true"></span>
                    <input id="report-search" class="form-control" type="search"
                        placeholder="Search by Agreement, code, or person"
                        autocomplete="off" disabled data-search-input data-report-search>
                </div>
            </div>
            <div class="progressive-search-status">
                <label class="form-label" for="report-status">Status</label>
                <select id="report-status" class="form-select" disabled data-report-status-filter>
                    <option value="">All statuses</option>
                    <option value="DRAFT">Draft</option>
                    <option value="RETURNED">Returned</option>
                    <option value="SUBMITTED">Submitted</option>
                    <option value="ACCEPTED">Accepted</option>
                    <option value="OVERDUE">Overdue</option>
                </select>
            </div>
            <div class="progressive-search-action">
                <button class="btn btn-outline-primary progressive-search-toggle" type="button"
                    aria-expanded="false" aria-controls="report-more-filters" data-advanced-search-toggle>
                    <span>More filters</span>
                    <span class="progressive-search-count d-none" data-advanced-filter-count></span>
                    <span class="progressive-search-chevron" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <p class="progressive-search-summary d-none" data-advanced-filter-summary aria-live="polite"></p>
        <div id="report-more-filters" class="progressive-search-panel" data-advanced-search-panel hidden>
            <div class="progressive-search-panel-heading">
                <div>
                    <h3 class="h6 mb-1">Refine your results</h3>
                    <p class="small text-secondary mb-0">Choose only the filters you need, or add precise conditions.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="report-year">Reporting year</label>
                    <select id="report-year" class="form-select" disabled data-advanced-filter data-filter-label="year" data-report-year-filter>
                        <option value="">All years</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="report-due-from">Due from</label>
                    <input id="report-due-from" class="form-control" type="date" disabled data-advanced-filter data-filter-label="due date" data-report-due-from>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="report-due-to">Due to</label>
                    <input id="report-due-to" class="form-control" type="date" disabled data-advanced-filter data-filter-label="due date" data-report-due-to>
                </div>
            </div>
            <div class="search-rule-builder" data-search-rule-builder>
                <div class="search-rule-builder-header">
                    <div>
                        <h4 class="h6 mb-1">Build a precise search</h4>
                        <p class="small text-secondary mb-0">Add conditions in plain language—no search commands required.</p>
                    </div>
                    <label class="search-match-mode d-none" data-search-match-mode-wrap><span>Match</span>
                        <select class="form-select form-select-sm" data-search-match-mode><option value="all">all conditions</option><option value="any">any condition</option></select>
                    </label>
                </div>
                <div class="search-rule-list" data-search-rule-list></div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-search-rule><span aria-hidden="true">+</span> Add condition</button>
            </div>
            <div class="progressive-search-footer">
                <button class="btn btn-link text-secondary p-0" type="button"
                    disabled data-clear-report-filters>Clear filters</button>
            </div>
        </div>
    </div>
    <div class="loading-state" data-report-list-loading>
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading annual reports…</span>
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

<?php workspaceFooter([
    'assets/js/advanced-search.js',
    'assets/js/performance-reports.js',
]); ?>
