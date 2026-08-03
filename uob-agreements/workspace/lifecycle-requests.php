<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader('Lifecycle requests', 'lifecycle');
?>

<section class="page-heading">
    <p class="eyebrow mb-2">Agreement governance</p>
    <h1 class="display-6 mb-2">Lifecycle requests</h1>
    <p class="text-secondary mb-0">Renewal, amendment, and termination requests remain separate from approved Agreement records.</p>
</section>

<div id="lifecycle-alert" class="alert alert-danger mt-4 d-none" role="alert" tabindex="-1"></div>
<div id="lifecycle-loading" class="loading-state mt-4"><div class="spinner-border text-primary"></div><span>Loading requests…</span></div>

<section id="lifecycle-list" class="workspace-card mt-4 d-none" aria-labelledby="lifecycle-list-title">
    <div class="workspace-card-header"><div>
        <h2 id="lifecycle-list-title" class="h5 mb-1">Visible requests</h2>
        <p class="small text-secondary mb-0" data-request-count></p>
    </div></div>
    <div class="filter-bar">
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label" for="lifecycle-search">Advanced search</label>
                <input id="lifecycle-search" class="form-control" type="search"
                    placeholder='Try type:renewal status:"under review"'
                    aria-describedby="lifecycle-search-help" disabled>
                <div id="lifecycle-search-help" class="form-text">
                    Use words, quoted phrases, <code>field:value</code>, <code>a|b</code>,
                    <code>-term</code>, or <code>updated&gt;=2026-01-01</code>.
                    Fields: id, agreement, code, type, status,
                    requester, start, end, termination, updated.
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="lifecycle-status">Status</label>
                <select id="lifecycle-status" class="form-select" disabled>
                    <option value="">All statuses</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="lifecycle-type">Request type</label>
                <select id="lifecycle-type" class="form-select" disabled>
                    <option value="">All request types</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="lifecycle-updated-from">Updated from</label>
                <input id="lifecycle-updated-from" class="form-control" type="date" disabled>
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="form-label" for="lifecycle-updated-to">Updated to</label>
                <input id="lifecycle-updated-to" class="form-control" type="date" disabled>
            </div>
            <div class="col-lg-4 d-flex align-items-end">
                <button class="btn btn-outline-secondary w-100" type="button"
                    data-clear-lifecycle-filters disabled>Clear filters</button>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table workspace-table align-middle mb-0">
            <thead><tr><th>Request</th><th>Agreement</th><th>Status</th><th>Updated</th><th><span class="visually-hidden">Action</span></th></tr></thead>
            <tbody data-request-rows></tbody>
        </table>
    </div>
</section>

<div id="lifecycle-empty" class="empty-state mt-4 d-none">
    <h2 class="h5">No lifecycle requests</h2>
    <p class="text-secondary mb-0">Open an approved or active Agreement to start a renewal, amendment, or termination request.</p>
</div>

<?php workspaceFooter([
    'assets/js/advanced-search.js?v=20260802-advanced-search',
    'assets/js/lifecycle-requests.js?v=20260802-advanced-search',
]); ?>
