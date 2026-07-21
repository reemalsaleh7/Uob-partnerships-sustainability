<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Workflow inbox', 'workflow');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Assigned work</p>
        <h1 class="display-6 mb-2">Workflow inbox</h1>
        <p class="text-secondary mb-0">
            Review Agreement tasks currently assigned to you.
        </p>
    </div>

    <div class="align-self-lg-end">
        <button class="btn btn-outline-primary" type="button" data-refresh-inbox>
            Refresh inbox
        </button>
    </div>
</section>

<div
    id="workflow-feedback"
    class="alert alert-success mt-4 d-none"
    role="status"
    aria-live="polite"
></div>

<section class="workspace-card mt-4" aria-labelledby="workflow-list-title">
    <div class="workspace-card-header">
        <div>
            <h2 id="workflow-list-title" class="h5 mb-1">Active assignments</h2>
            <p class="text-secondary small mb-0" data-workflow-summary>
                Loading assigned tasks…
            </p>
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
                    <th scope="col">Agreement</th>
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
