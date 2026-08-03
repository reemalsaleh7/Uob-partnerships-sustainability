<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Final Initiatives', 'initiatives');
?>
<link rel="stylesheet" href="assets/css/initiative-portfolio.css?v=20260802-phase16">

<section class="workspace-page-header">
    <div>
        <p class="eyebrow mb-2">Initiatives</p>
        <h1 class="h2 mb-2">Final Initiatives</h1>
        <p class="text-secondary mb-0">
            Approved workflow records and existing Initiatives registered from earlier University records.
        </p>
    </div>

    <div class="initiative-portfolio-actions">
        <div class="dropdown" data-approved-portfolio-menu>
            <button
                class="btn btn-primary dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                data-approved-portfolio-button
            >
                Create Final Initiative
            </button>
            <ul
                class="dropdown-menu dropdown-menu-end initiative-portfolio-approved-menu"
                data-approved-portfolio-options
            >
                <li><span class="dropdown-item-text text-secondary">Loading approved requests…</span></li>
            </ul>
        </div>
        <a
            class="btn btn-outline-secondary d-none initiative-existing-action"
            href="register-existing-initiative.php"
            data-register-existing-initiative
            title="Register an Initiative approved before this workflow"
        >
            <span class="initiative-action-plus" aria-hidden="true">+</span>
            <span>Register Existing Initiative</span>
        </a>
        <a
            class="btn btn-outline-secondary"
            href="initiative-workflow.php"
        >
            Initiative requests
        </a>
    </div>
</section>

<div
    class="alert alert-danger d-none"
    role="alert"
    data-portfolio-alert
></div>

<div class="loading-state" data-portfolio-loading>
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading final Initiatives…</span>
</div>

<section class="workspace-card d-none" data-portfolio-content>
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">My visible Initiatives</h2>
            <p class="small text-secondary mb-0">
                Initiatives you own, participate in, approve, or administer.
            </p>
        </div>
        <input
            type="search"
            class="form-control"
            style="max-width: 340px"
            placeholder="Search title or Initiative code"
            data-portfolio-search
        >
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Initiative</th>
                    <th>Owner</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-portfolio-body></tbody>
        </table>
    </div>

    <div class="form-section text-center py-5 d-none" data-portfolio-empty>
        <h2 class="h5">No final Initiatives found</h2>
        <p class="text-secondary mb-3">
            Add an approved request or register an Initiative that existed before the workflow system.
        </p>
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <a class="btn btn-primary" href="initiative-workflow.php">
                Open Initiative requests
            </a>
            <a
                class="btn btn-outline-secondary initiative-existing-action"
                href="register-existing-initiative.php"
            >
                <span class="initiative-action-plus" aria-hidden="true">+</span>
                <span>Register Existing Initiative</span>
            </a>
        </div>
    </div>
</section>

<?php
workspaceFooter([
    'assets/js/initiative-portfolio.js?v=20260802-phase16',
]);
