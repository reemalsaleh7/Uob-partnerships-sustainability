<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Overview', 'dashboard');
?>

<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-dashboard-alert></div>

<div class="loading-state" data-dashboard-loading aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Preparing your workspace…</span>
</div>

<div class="d-none" data-dashboard-content>
    <section class="dashboard-welcome">
        <p class="eyebrow mb-2" data-dashboard-greeting>Welcome back</p>
        <h1 data-dashboard-title>Your work, decisions, and results in one place.</h1>
        <p data-dashboard-description>
            See what needs attention, where each Agreement is in review, and what your role allows you to do.
        </p>
        <span class="dashboard-role-chip" data-dashboard-role></span>
    </section>

    <div class="dashboard-section-title">
        <div>
            <h2>What you can do</h2>
            <p>Shortcuts based on your role and current permissions.</p>
        </div>
    </div>
    <section class="dashboard-action-grid" data-dashboard-actions aria-label="Available actions"></section>

    <div class="dashboard-section-title d-none" data-kpi-heading>
        <div>
            <h2 data-kpi-title>Your operating picture</h2>
            <p data-kpi-description>Live information from the Agreements workflow.</p>
        </div>
    </div>
    <section class="dashboard-kpi-grid d-none" data-dashboard-kpis aria-label="Workspace summary"></section>

    <div class="row g-4 mt-2">
        <div class="col-xl-7 d-none" data-primary-work-column>
            <div class="dashboard-section-title">
                <div>
                    <h2 data-primary-work-title>Work requiring attention</h2>
                    <p data-primary-work-description>Open items relevant to your role.</p>
                </div>
                <a href="#" data-primary-work-link>View all</a>
            </div>
            <section class="workspace-card">
                <ul class="dashboard-list" data-primary-work-list></ul>
            </section>
        </div>

        <div class="col-xl-5" data-role-guidance-column>
            <div class="dashboard-section-title">
                <div>
                    <h2>Your role in the system</h2>
                    <p>Authority, responsibility, and next steps.</p>
                </div>
            </div>
            <section class="workspace-card">
                <div class="form-section" data-role-guidance></div>
            </section>
        </div>
    </div>
</div>

<?php workspaceFooter(['assets/js/dashboard.js?v=20260722-showcase-data']); ?>
