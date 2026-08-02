<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Initiative', 'initiatives');
?>

<link
    rel="stylesheet"
    href="assets/css/initiative-view.css?v=20260801-phase10d"
>

<div class="mb-4">
    <a
        href="initiative-workflow.php"
        class="back-link"
        data-initiative-back
    >
        ← Back to Initiative requests
    </a>
</div>

<div
    class="alert alert-danger d-none"
    role="alert"
    data-initiative-alert
></div>

<div class="loading-state" data-initiative-loading>
    <div
        class="spinner-border text-primary"
        aria-hidden="true"
    ></div>
    <span>Loading Initiative…</span>
</div>

<div class="d-none" data-initiative-content>
    <section class="initiative-record-heading">
        <div>
            <p
                class="initiative-record-code mb-2"
                data-initiative-code
            ></p>
            <h1
                class="display-6 mb-2"
                data-initiative-title
            ></h1>
            <p class="text-secondary mb-0">
                Owned by
                <span data-initiative-owner></span>
            </p>
        </div>
        <div data-initiative-status></div>
    </section>

    <section class="workspace-card">
        <div class="workspace-card-header">
            <div>
                <h2 class="h5 mb-1">
                    Initiative information
                </h2>
                <p class="small text-secondary mb-0">
                    Final record created from the approved
                    Initiative request.
                </p>
            </div>
        </div>

        <div class="form-section">
            <dl class="initiative-record-grid mb-0">
                <div class="initiative-record-field">
                    <dt>Type</dt>
                    <dd data-initiative-type></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Expected budget</dt>
                    <dd data-initiative-budget></dd>
                </div>

                <div class="initiative-record-field wide">
                    <dt>Description</dt>
                    <dd data-initiative-description></dd>
                </div>

                <div class="initiative-record-field wide">
                    <dt>Objectives</dt>
                    <dd data-initiative-objectives></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Expected impact</dt>
                    <dd data-initiative-impact></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Beneficiaries</dt>
                    <dd data-initiative-beneficiaries></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Planned start</dt>
                    <dd data-initiative-start></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Planned end</dt>
                    <dd data-initiative-end></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Approved</dt>
                    <dd data-initiative-approved></dd>
                </div>

                <div class="initiative-record-field">
                    <dt>Last updated</dt>
                    <dd data-initiative-updated></dd>
                </div>
            </dl>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <section class="workspace-card h-100">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">
                            Participants
                        </h2>
                        <p class="small text-secondary mb-0">
                            Owner and collaborators copied
                            from the request.
                        </p>
                    </div>
                </div>

                <div
                    class="form-section"
                    data-initiative-participants
                ></div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="workspace-card h-100">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">
                            Source and versions
                        </h2>
                        <p class="small text-secondary mb-0">
                            Traceability back to the approved
                            request.
                        </p>
                    </div>
                </div>

                <div class="form-section">
                    <div data-initiative-source></div>
                    <div data-initiative-agreements></div>
                    <hr>
                    <div data-initiative-versions></div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php
workspaceFooter([
    'assets/js/initiative-view.js?v=20260801-phase10d',
]);
