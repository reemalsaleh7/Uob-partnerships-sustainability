<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreements', 'agreements');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Agreement management</p>
        <h1 class="display-6 mb-2">Agreements</h1>
        <p class="text-secondary mb-0">
            View drafts, Agreements under review, and completed decisions.
        </p>
    </div>

    <div class="align-self-lg-end">
        <button
            type="button"
            class="btn btn-primary d-none"
            data-create-agreement
            disabled
            title="Agreement creation is included in the next frontend slice"
        >
            Create Agreement
        </button>
    </div>
</section>

<section class="workspace-card mt-4" aria-labelledby="agreement-list-title">
    <div class="workspace-card-header">
        <div>
            <h2 id="agreement-list-title" class="h5 mb-1">Agreement register</h2>
            <p class="text-secondary small mb-0" data-result-summary>
                Loading Agreements…
            </p>
        </div>
    </div>

    <div class="filter-bar">
        <div class="row g-3">
            <div class="col-lg-8">
                <label for="agreement-search" class="form-label">Search</label>
                <input
                    id="agreement-search"
                    type="search"
                    class="form-control"
                    placeholder="Search by title, type, status, or ID"
                    disabled
                >
            </div>
            <div class="col-lg-4">
                <label for="agreement-status" class="form-label">Status</label>
                <select id="agreement-status" class="form-select" disabled>
                    <option value="">All statuses</option>
                </select>
            </div>
        </div>
    </div>

    <div
        id="agreement-alert"
        class="alert alert-danger m-3 d-none"
        role="alert"
        aria-live="polite"
    ></div>

    <div id="agreement-loading" class="loading-state" aria-live="polite">
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading Agreements…</span>
    </div>

    <div id="agreement-empty" class="empty-state d-none">
        <h3 class="h5">No Agreements found</h3>
        <p class="text-secondary mb-0">Try changing the search or status filter.</p>
    </div>

    <div id="agreement-table-wrap" class="table-responsive d-none">
        <table class="table workspace-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Title</th>
                    <th scope="col">Type</th>
                    <th scope="col">Status</th>
                    <th scope="col">Partner</th>
                    <th scope="col">Updated</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="agreement-table-body"></tbody>
        </table>
    </div>
</section>

<?php workspaceFooter(['assets/js/agreements.js']); ?>

