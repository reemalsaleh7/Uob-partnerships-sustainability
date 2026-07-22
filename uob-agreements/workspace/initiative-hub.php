<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Initiative hub', 'initiatives');
?>

<section class="dashboard-welcome">
    <p class="eyebrow mb-2">Initiatives</p>
    <h1>Move an idea from your department to University approval.</h1>
    <p>
        Faculty and Department Heads can propose initiatives. The request then moves through Department,
        College, Vice President, and President approval.
    </p>
    <span class="dashboard-role-chip" data-initiative-access>Checking your initiative access…</span>
</section>

<div class="dashboard-section-title">
    <div>
        <h2>Initiative actions</h2>
        <p>The Initiative module remains connected while its teammate completes the new workflow implementation.</p>
    </div>
</div>

<section class="dashboard-action-grid">
    <a
        class="dashboard-action d-none"
        href="#"
        data-create-initiative
        data-legacy-initiative="request-initiative.php?lang=en"
    >
        <strong>Start an initiative request</strong>
        <small>Propose an initiative from your college or department.</small>
        <span>Start request →</span>
    </a>
    <a class="dashboard-action" href="../initiatives.php?lang=en">
        <strong>Browse initiatives</strong>
        <small>Explore submitted and published University initiatives.</small>
        <span>Open catalogue →</span>
    </a>
    <a class="dashboard-action" href="agreements.php">
        <strong>Find an active Agreement</strong>
        <small>Review live partnership objectives and start an Initiative from the selected Agreement.</small>
        <span>Choose a partnership →</span>
    </a>
    <a class="dashboard-action" href="../sdg.php?lang=en">
        <strong>Choose SDG outcomes</strong>
        <small>Understand the 17 Sustainable Development Goals before submitting.</small>
        <span>Explore SDGs →</span>
    </a>
</section>

<div class="row g-4 mt-3">
    <div class="col-lg-7">
        <section class="workspace-card h-100">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">Initiative approval path</h2>
                    <p class="small text-secondary mb-0">Who acts after you submit.</p>
                </div>
            </div>
            <div class="workflow-timeline">
                <div class="timeline-step is-current"><span class="timeline-marker">1</span><strong>Creator</strong><small>Faculty or Department Head</small></div>
                <div class="timeline-step"><span class="timeline-marker">2</span><strong>Department</strong><small>Department Head review</small></div>
                <div class="timeline-step"><span class="timeline-marker">3</span><strong>College</strong><small>Dean approval</small></div>
                <div class="timeline-step"><span class="timeline-marker">4</span><strong>VP Office</strong><small>University review</small></div>
                <div class="timeline-step"><span class="timeline-marker">5</span><strong>President</strong><small>Final approval</small></div>
            </div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="workspace-card h-100">
            <div class="workspace-card-header"><h2 class="h5 mb-0">Before you start</h2></div>
            <div class="form-section small text-secondary">
                <p class="mb-2">Prepare:</p>
                <ul class="ps-3 mb-0">
                    <li class="mb-2">A clear objective and expected impact.</li>
                    <li class="mb-2">Your executing department or college.</li>
                    <li class="mb-2">Target beneficiaries and measurable outcomes.</li>
                    <li class="mb-2">Related Agreement, if the activity uses a partnership.</li>
                    <li>Relevant SDGs and supporting evidence.</li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php workspaceFooter(['assets/js/initiative-hub.js']); ?>
