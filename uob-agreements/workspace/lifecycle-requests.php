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

<?php workspaceFooter(['assets/js/lifecycle-requests.js']); ?>
