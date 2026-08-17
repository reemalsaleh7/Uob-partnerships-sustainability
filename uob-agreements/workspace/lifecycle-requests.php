<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
workspaceHeader(
    'Lifecycle requests',
    'lifecycle',
    ['assets/css/lifecycle-requests.css']
);
?>

<section class="page-heading lifecycle-requests-heading">
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
    <div class="filter-bar progressive-search" data-lifecycle-search-filters aria-label="Advanced search">
        <div class="progressive-search-primary">
            <div class="progressive-search-field">
                <label class="form-label" for="lifecycle-search">Search requests</label>
                <div class="progressive-search-input-wrap">
                    <span class="progressive-search-icon" aria-hidden="true"></span>
                    <input id="lifecycle-search" class="form-control" type="search"
                        placeholder="Search by Agreement, request type, or person"
                        autocomplete="off" data-search-input disabled>
                </div>
            </div>
            <div class="progressive-search-status">
                <label class="form-label" for="lifecycle-status">Status</label>
                <select id="lifecycle-status" class="form-select" disabled>
                    <option value="">All statuses</option>
                </select>
            </div>
            <div class="progressive-search-action">
                <button class="btn btn-outline-primary progressive-search-toggle" type="button"
                    aria-expanded="false" aria-controls="lifecycle-more-filters" data-advanced-search-toggle>
                    <span>More filters</span>
                    <span class="progressive-search-count d-none" data-advanced-filter-count></span>
                    <span class="progressive-search-chevron" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <p class="progressive-search-summary d-none" data-advanced-filter-summary aria-live="polite"></p>
        <div id="lifecycle-more-filters" class="progressive-search-panel" data-advanced-search-panel hidden>
            <div class="progressive-search-panel-heading">
                <div>
                    <h3 class="h6 mb-1">Refine your results</h3>
                    <p class="small text-secondary mb-0">Choose only the filters you need, or add precise conditions.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="lifecycle-type">Request type</label>
                    <select id="lifecycle-type" class="form-select" data-advanced-filter data-filter-label="request type" disabled>
                        <option value="">All request types</option>
                    </select>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="lifecycle-updated-from">Updated from</label>
                    <input id="lifecycle-updated-from" class="form-control" type="date" data-advanced-filter data-filter-label="updated date" disabled>
                </div>
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="lifecycle-updated-to">Updated to</label>
                    <input id="lifecycle-updated-to" class="form-control" type="date" data-advanced-filter data-filter-label="updated date" disabled>
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

<!-- LIFECYCLE REQUESTS EMPTY STATE V1 -->
<section
    id="lifecycle-empty"
    class="workspace-card lifecycle-empty-card mt-4 d-none"
    aria-labelledby="lifecycle-empty-title"
>
    <div class="lifecycle-empty-content">
        <div class="lifecycle-empty-icon" aria-hidden="true">
            <svg
                viewBox="0 0 24 24"
                width="24"
                height="24"
                focusable="false"
            >
                <path
                    d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M14 3v5h5M9 13h6M9 17h4"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </div>

        <div class="lifecycle-empty-copy">
            <h2 id="lifecycle-empty-title" class="h5 mb-2">
                No lifecycle requests
            </h2>

            <p class="text-secondary mb-0">
                Open an approved or active Agreement to start a renewal,
                amendment, or termination request.
            </p>
        </div>

        <a
            href="agreements.php"
            class="btn btn-primary lifecycle-empty-action"
        >
            Browse Agreements
        </a>
    </div>
</section>

<?php workspaceFooter([
    'assets/js/advanced-search.js',
    'assets/js/lifecycle-requests.js',
]); ?>
