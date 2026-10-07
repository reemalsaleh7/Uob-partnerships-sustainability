<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Initiative hub', 'initiatives');
?>
<style>
/* ===== Initiative approval path ===== */

.approvalPathCard{
    background:#ffffff;
    border:1px solid #e3eaf1;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 10px 28px rgba(15, 23, 42, 0.05);
}

.approvalPathHeader{
    padding:26px 28px 18px;
    border-bottom:1px solid #eef3f7;
    background:linear-gradient(180deg, #fcfefd 0%, #f8fbf9 100%);
}

.approvalPathHeader h3{
    margin:0;
    font-size:18px;
    font-weight:800;
    color:#0f2f46;
}

.approvalPathHeader p{
    margin:6px 0 0;
    font-size:13px;
    color:#6b7c8f;
}

.approvalPathBody{
    padding:34px 28px 30px;
}

.approvalStepper{
    position:relative;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
}

.approvalStepper::before{
    content:"";
    position:absolute;
    top:21px;
    left:6%;
    right:6%;
    height:4px;
    border-radius:999px;
    background:#dde6ee;
    z-index:0;
}

.approvalStep{
    position:relative;
    z-index:1;
    flex:1;
    text-align:center;
}

.approvalDot{
    width:46px;
    height:46px;
    margin:0 auto 14px;
    border-radius:50%;
    border:2px solid #c9d6e2;
    background:#ffffff;
    color:#7d90a4;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:13px;
    font-weight:800;
    box-shadow:0 4px 12px rgba(15, 23, 42, 0.05);
}

.approvalStep.is-active .approvalDot{
    background:linear-gradient(135deg, #53b97c 0%, #2f855a 100%);
    border-color:#2f855a;
    color:#ffffff;
    box-shadow:0 10px 22px rgba(47, 133, 90, 0.24);
}

.approvalStep.is-done .approvalDot{
    background:#eaf7ef;
    border-color:#9fd3b1;
    color:#2f855a;
}

.approvalStepTitle{
    font-size:14px;
    font-weight:800;
    color:#15364f;
    line-height:1.2;
    margin-bottom:4px;
}

.approvalStepMeta{
    font-size:12px;
    color:#7b8b9b;
    line-height:1.35;
    max-width:120px;
    margin:0 auto;
}

@media (max-width: 991px){
    .approvalPathBody{
        padding:22px 18px 20px;
    }

    .approvalStepper{
        flex-direction:column;
        gap:18px;
    }

    .approvalStepper::before{
        top:0;
        bottom:0;
        left:22px;
        right:auto;
        width:4px;
        height:auto;
    }

    .approvalStep{
        display:flex;
        align-items:flex-start;
        gap:14px;
        text-align:left;
    }

    .approvalDot{
        margin:0;
        flex-shrink:0;
    }

    .approvalStepMeta{
        max-width:none;
        margin:0;
    }
}
</style>

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

    <div class="approvalPathCard">

        <div class="approvalPathHeader">
            <h3>Initiative approval path</h3>
            <p>Who acts after you submit.</p>
        </div>

        <div class="approvalPathBody">

            <div class="approvalStepper">

                <div class="approvalStep is-active">
                    <div class="approvalDot">1</div>
                    <div class="approvalStepTitle">Creator</div>
                    <div class="approvalStepMeta">Faculty or Department Head</div>
                </div>

                <div class="approvalStep">
                    <div class="approvalDot">2</div>
                    <div class="approvalStepTitle">Department</div>
                    <div class="approvalStepMeta">Department Head review</div>
                </div>

                <div class="approvalStep">
                    <div class="approvalDot">3</div>
                    <div class="approvalStepTitle">College</div>
                    <div class="approvalStepMeta">Dean approval</div>
                </div>

                <div class="approvalStep">
                    <div class="approvalDot">4</div>
                    <div class="approvalStepTitle">VP Office</div>
                    <div class="approvalStepMeta">University review</div>
                </div>

                <div class="approvalStep">
                    <div class="approvalDot">5</div>
                    <div class="approvalStepTitle">President</div>
                    <div class="approvalStepMeta">Final approval</div>
                </div>

            </div>

        </div>

    </div>

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
