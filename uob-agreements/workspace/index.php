<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader(
    'Overview',
    'dashboard',
    ['assets/css/dashboard-overview.css']
);
?>

<div class="alert alert-danger d-none" role="alert" tabindex="-1" data-dashboard-alert></div>

<div class="loading-state" data-dashboard-loading aria-live="polite">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Preparing your workspace…</span>
</div>

<div class="d-none" data-dashboard-content>
    <section class="dashboard-welcome dashboard-overview-hero">
        <p class="eyebrow mb-2" data-dashboard-greeting>Welcome back</p>
        <h1 data-dashboard-title>Agreements and Initiatives, together in one workspace.</h1>
        <p data-dashboard-description>
            See what needs your attention, follow both approval routes, and move University partnerships into measurable action.
        </p>
        <span class="dashboard-role-chip" data-dashboard-role></span>
    </section>

    <div class="dashboard-section-title dashboard-priority-heading">
        <div>
            <h2>Your priorities</h2>
            <p>Agreement and Initiative work that needs attention or is moving through review.</p>
        </div>
    </div>
    <section class="dashboard-priority-grid" data-dashboard-priorities aria-label="Priority briefing"></section>

    <div class="dashboard-section-title">
        <div>
            <h2>Agreements and Initiatives</h2>
            <p>Two connected portfolios, shown separately so their counts and responsibilities stay clear.</p>
        </div>
    </div>
    <section class="dashboard-portfolio-grid" aria-label="Agreements and Initiatives overview">
        <article class="dashboard-portfolio-card is-agreement" data-agreement-portfolio>
            <header>
                <div>
                    <span class="dashboard-module-label">Agreements</span>
                    <h3>Partnership portfolio</h3>
                    <p>Creation, institutional review, activation, and annual reporting.</p>
                </div>
                <a href="agreements.php">Open Agreements</a>
            </header>
            <div class="dashboard-module-metrics" data-agreement-metrics aria-live="polite"></div>
        </article>

        <article class="dashboard-portfolio-card is-initiative" data-initiative-portfolio>
            <header>
                <div>
                    <span class="dashboard-module-label">Initiatives</span>
                    <h3>Impact portfolio</h3>
                    <p>Requests, approvals, notifications, and final Initiative records.</p>
                </div>
                <a href="initiative-workflow.php">Open Initiatives</a>
            </header>
            <div class="dashboard-module-metrics" data-initiative-metrics aria-live="polite">
                <div class="dashboard-module-loading">Loading Initiative work…</div>
            </div>
        </article>
    </section>

    <div class="dashboard-section-title">
        <div>
            <h2>Quick actions</h2>
            <p>Agreement and Initiative shortcuts based on your role and permissions.</p>
        </div>
    </div>
    <section class="dashboard-action-grid" data-dashboard-actions aria-label="Available actions"></section>

    <div class="dashboard-work-grid">
        <div data-primary-work-column>
            <div class="dashboard-section-title">
                <div>
                    <h2 data-primary-work-title>Agreement work</h2>
                    <p data-primary-work-description>Drafts, reviews, and active partnership records.</p>
                </div>
                <a href="#" data-primary-work-link>View all</a>
            </div>
            <section class="workspace-card dashboard-work-card">
                <ul class="dashboard-list" data-primary-work-list></ul>
            </section>
        </div>

        <div data-initiative-work-column>
            <div class="dashboard-section-title">
                <div>
                    <h2>Initiative work</h2>
                    <p>Requests you create, contribute to, review, or follow.</p>
                </div>
                <a href="initiative-workflow.php">View all</a>
            </div>
            <section class="workspace-card dashboard-work-card">
                <ul class="dashboard-list" data-initiative-work-list>
                    <li class="dashboard-empty">Loading Initiative work…</li>
                </ul>
            </section>
        </div>
    </div>

    <div class="dashboard-section-title" data-role-guidance-column>
        <div>
            <h2>Your role in the system</h2>
            <p>Authority, responsibility, and next steps across both modules.</p>
        </div>
    </div>
    <section class="workspace-card dashboard-guidance-card">
        <div class="form-section" data-role-guidance></div>
    </section>
</div>

<?php
workspaceFooter([
    'assets/js/dashboard.js?v=20260803-unified-overview',
    'assets/js/dashboard-initiative-integration.js?v=20260803-unified-overview',
]);
