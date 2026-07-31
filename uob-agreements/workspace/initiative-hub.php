<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Initiative hub', 'initiatives');
?>

<section class="dashboard-welcome">
    <p class="eyebrow mb-2">
        Initiatives
    </p>

    <h1>
        Move an idea from your department to University approval.
    </h1>

    <p>
        Create, follow, and manage University initiatives through
        the approved workflow.
    </p>

    <span
        class="dashboard-role-chip"
        data-initiative-access
    >
        Checking initiative access...
    </span>
</section>

<div class="dashboard-section-title">
    <div>
        <h2>
            Initiative actions
        </h2>

        <p>
            Create a new initiative or review existing initiatives.
        </p>
    </div>
</div>

<section class="dashboard-action-grid">
    <a
        class="dashboard-action d-none"
        href="#"
        data-create-initiative
        data-legacy-initiative="workspace/initiative-module/initiative-create.php"
        aria-hidden="true"
    >
        <strong>
            Start an initiative
        </strong>

        <small>
            Create a new initiative draft and optionally link
            an existing Agreement.
        </small>

        <span>
            Start →
        </span>
    </a>

    <a
        class="dashboard-action"
        href="#"
        data-legacy-initiative="workspace/initiative-module/index.php"
    >
        <strong>
            Open Initiative Workspace
        </strong>

        <small>
            View your initiatives and their approval progress.
        </small>

        <span>
            Open workspace →
        </span>
    </a>

    <a
        class="dashboard-action"
        href="agreements.php"
    >
        <strong>
            Find an active Agreement
        </strong>

        <small>
            Review an existing Agreement before linking it
            to an Initiative.
        </small>

        <span>
            Browse Agreements →
        </span>
    </a>

    <a
        class="dashboard-action"
        href="../sdg.php?lang=en"
    >
        <strong>
            Choose SDG outcomes
        </strong>

        <small>
            Review Sustainable Development Goals before submitting.
        </small>

        <span>
            Explore SDGs →
        </span>
    </a>
</section>

<div class="row g-4 mt-3">
    <div class="col-lg-7">
        <section class="workspace-card h-100">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">
                        Initiative approval path
                    </h2>

                    <p class="small text-secondary mb-0">
                        The workflow used after submission.
                    </p>
                </div>
            </div>

            <div class="workflow-timeline">
                <div class="timeline-step is-current">
                    <span class="timeline-marker">
                        1
                    </span>

                    <strong>
                        Creator
                    </strong>

                    <small>
                        Create and submit
                    </small>
                </div>

                <div class="timeline-step">
                    <span class="timeline-marker">
                        2
                    </span>

                    <strong>
                        Department Head
                    </strong>

                    <small>
                        Department review
                    </small>
                </div>

                <div class="timeline-step">
                    <span class="timeline-marker">
                        3
                    </span>

                    <strong>
                        Dean
                    </strong>

                    <small>
                        College approval
                    </small>
                </div>

                <div class="timeline-step">
                    <span class="timeline-marker">
                        4
                    </span>

                    <strong>
                        Vice President
                    </strong>

                    <small>
                        University review
                    </small>
                </div>

                <div class="timeline-step">
                    <span class="timeline-marker">
                        5
                    </span>

                    <strong>
                        President
                    </strong>

                    <small>
                        Final approval
                    </small>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-5">
        <section class="workspace-card h-100">
            <div class="workspace-card-header">
                <h2 class="h5 mb-0">
                    Before you start
                </h2>
            </div>

            <div class="form-section small text-secondary">
                <p class="mb-2">
                    Prepare:
                </p>

                <ul class="ps-3 mb-0">
                    <li class="mb-2">
                        A clear objective and expected impact.
                    </li>

                    <li class="mb-2">
                        The initiative type and planned dates.
                    </li>

                    <li class="mb-2">
                        Expected budget, when applicable.
                    </li>

                    <li class="mb-2">
                        A related Agreement, when applicable.
                    </li>

                    <li>
                        Relevant supporting information.
                    </li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php workspaceFooter(['assets/js/initiative-hub.js']); ?>