<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader(
    'Review inbox',
    'workflow',
    ['assets/css/workflow-inbox.css']
);
?>

<section class="page-heading review-inbox-heading">
    <div>
        <p class="eyebrow mb-2">Assigned work</p>
        <h1 class="display-6 mb-2">Review inbox</h1>
        <p class="text-secondary mb-0">
            Review Agreement, lifecycle-request, and Initiative tasks currently assigned to you.
        </p>
    </div>


</section>

<div
    id="workflow-feedback"
    class="alert alert-success mt-4 d-none"
    role="status"
    aria-live="polite"
></div>

<section class="workspace-card review-inbox-card mt-4" aria-labelledby="workflow-list-title">
    <div class="workspace-card-header review-inbox-card-header">
        <div>
            <h2 id="workflow-list-title" class="h5 mb-1">Active assignments</h2>
            <p class="text-secondary small mb-0" data-workflow-summary>
                Loading assigned tasks…
            </p>
        </div>

        <div class="review-inbox-controls">
            <label class="form-check form-switch mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    data-workflow-unread-only
                >
                <span class="form-check-label">Unread only</span>
            </label>

            <button
                class="btn btn-sm btn-outline-primary"
                type="button"
                data-mark-all-workflow-read
             disabled>
                Mark all as read
            </button>

            <!-- REVIEW INBOX REFRESH ICON V2 -->
<button
    class="btn btn-sm btn-outline-primary review-inbox-refresh-button"
    type="button"
    data-refresh-inbox
    aria-label="Refresh inbox"
    title="Refresh inbox"
>
    <!-- REVIEW INBOX REFRESH ICON V3 -->
<svg
    viewBox="0 0 24 24"
    width="18"
    height="18"
    aria-hidden="true"
    focusable="false"
>
    <path
        d="M21 12a9 9 0 1 1-2.64-6.36L21 8"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
    />
    <path
        d="M21 3v5h-5"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
    />
</svg>
</button>
        </div>
    </div>

    <div
        id="workflow-alert"
        class="alert alert-danger m-3 d-none"
        role="alert"
        aria-live="polite"
        tabindex="-1"
    ></div>

    <div id="workflow-loading" class="loading-state" aria-live="polite">
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading assigned tasks…</span>
    </div>

    <div id="workflow-empty" class="empty-state d-none">
        <h3 class="h5">Your inbox is clear</h3>
        <p class="text-secondary mb-0">
            New review assignments will appear here when they become active.
        </p>
    </div>

    <div id="workflow-table-wrap" class="table-responsive d-none">
        <table class="table workspace-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Task</th>
                    <th scope="col">Item</th>
                    <th scope="col">Assigned office</th>
                    <th scope="col">Started</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="workflow-table-body"></tbody>
        </table>
    </div>
</section>

<?php workspaceFooter(['assets/js/workflow-inbox.js']); ?>
