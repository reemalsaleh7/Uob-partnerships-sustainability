<?php

declare(strict_types=1);

function workspaceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function workspaceSource(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    $source = file_get_contents($path);
    workspaceAssert($source !== false, "Could not read {$relativePath}");
    return $source;
}

$layout = workspaceSource('uob-agreements/workspace/includes/layout.php');
$dashboard = workspaceSource('uob-agreements/workspace/index.php');
$dashboardJs = workspaceSource('uob-agreements/workspace/assets/js/dashboard.js');
$dashboardInitiatives = workspaceSource(
    'uob-agreements/workspace/assets/js/dashboard-initiative-integration.js'
);
$agreementPage = workspaceSource('uob-agreements/workspace/agreement.php');
$agreementJs = workspaceSource('uob-agreements/workspace/assets/js/agreement-detail.js');
$agreementForm = workspaceSource('uob-agreements/workspace/agreement-form.php');
$routes = workspaceSource('routes/agreements.php');
$authRoutes = workspaceSource('routes/auth.php');
$authService = workspaceSource('services/AuthService.php');
$userRepository = workspaceSource('repositories/UserRepository.php');
$handoff = workspaceSource('uob-agreements/workspace-handoff.php');
$performanceController = workspaceSource(
    'controllers/AgreementPerformanceController.php'
);
$apiClient = workspaceSource(
    'uob-agreements/workspace/assets/js/api-client.js'
);
$workspaceStyles = workspaceSource(
    'uob-agreements/workspace/assets/css/workspace.css'
);
$agreementFormStyles = workspaceSource(
    'uob-agreements/workspace/assets/css/agreement-form.css'
);
$lifecycleForm = workspaceSource(
    'uob-agreements/workspace/lifecycle-form.php'
);
$lifecycleFormJs = workspaceSource(
    'uob-agreements/workspace/assets/js/lifecycle-form.js'
);
$lifecycleRequest = workspaceSource(
    'uob-agreements/workspace/lifecycle-request.php'
);
$exportUtils = workspaceSource(
    'uob-agreements/workspace/assets/js/export-utils.js'
);
$dialog = workspaceSource(
    'uob-agreements/workspace/assets/js/ui-dialog.js'
);
$workflowReview = workspaceSource(
    'uob-agreements/workspace/assets/js/workflow-review.js'
);
$legalReview = workspaceSource(
    'uob-agreements/workspace/assets/js/legal-review.js'
);
$financeReview = workspaceSource(
    'uob-agreements/workspace/assets/js/finance-review.js'
);
$performanceDashboard = workspaceSource(
    'uob-agreements/workspace/assets/js/performance-dashboard.js'
);
$migration = workspaceSource(
    'uob-agreements/data/sql/migrations/20260721_functional_workspace_redesign.sql'
);

foreach (['index.php', 'profile.php', 'initiative-hub.php'] as $destination) {
    workspaceAssert(
        str_contains($layout, 'href="' . $destination . '"'),
        "Workspace navigation is missing {$destination}"
    );
}

workspaceAssert(
    !str_contains($dashboard, "header('Location:"),
    'Workspace overview still redirects instead of rendering a dashboard'
);
workspaceAssert(
    str_contains($dashboardJs, "hasRole(user, 'Initiative Creator')"),
    'Faculty Initiative Creator experience is missing'
);
workspaceAssert(
    str_contains($agreementPage, 'data-workflow-timeline'),
    'Agreement page is missing the review timeline'
);
workspaceAssert(
    str_contains($agreementJs, 'assigned_reviewer_names'),
    'Review timeline does not display the assigned reviewer'
);
workspaceAssert(
    str_contains($agreementPage, 'agreement-detail-grid')
        && str_contains($agreementPage, 'agreement-overview-card')
        && str_contains($agreementPage, 'agreement-program-card')
        && str_contains($agreementPage, 'agreement-record-card'),
    'Agreement detail page is missing the guided card layout'
);
workspaceAssert(
    str_contains($agreementPage, 'data-record-origin')
        && str_contains($apiClient, 'createRecordOriginBadge'),
    'Agreement detail page is missing a supported record-origin badge'
);
workspaceAssert(
    str_contains($agreementJs, 'agreement-form.php?id=')
        && str_contains($agreementForm, 'agreement-request-hero')
        && str_contains($agreementForm, 'agreement-form-shell')
        && str_contains($agreementForm, 'agreement-form-step-panel')
        && str_contains($agreementForm, 'agreement-form-section')
        && str_contains($agreementFormStyles, '.agreement-form-shell'),
    'Agreement editing is not routed through the redesigned comprehensive form'
);
workspaceAssert(
    str_contains($dashboard, 'data-dashboard-priorities')
        && str_contains($workspaceStyles, '.dashboard-priority-card > strong')
        && str_contains($workspaceStyles, '.dashboard-priority-card > small'),
    'Overview priority cards are missing the layout that separates labels, counts, and descriptions'
);
workspaceAssert(
    str_contains($dashboard, 'data-agreement-portfolio')
        && str_contains($dashboard, 'data-initiative-portfolio')
        && str_contains($dashboard, 'data-agreement-metrics')
        && str_contains($dashboard, 'data-initiative-metrics')
        && str_contains($dashboard, 'data-initiative-work-list'),
    'Overview does not present Agreements and Initiatives as equal work areas'
);
workspaceAssert(
    str_contains($dashboardJs, "'Agreements and Initiatives, together in one workspace.'")
        && str_contains($dashboardJs, 'renderAgreementMetrics')
        && str_contains($dashboardInitiatives, 'renderMetrics')
        && str_contains($dashboardInitiatives, "'/initiative-requests'")
        && str_contains(
            $dashboardInitiatives,
            "'/initiative-requests/converted-initiatives'"
        ),
    'Overview does not load separate live Agreement and Initiative summaries'
);
workspaceAssert(
    str_contains($workspaceStyles, '.dashboard-portfolio-grid')
        && str_contains($workspaceStyles, '.dashboard-work-grid')
        && str_contains($workspaceStyles, '.dashboard-action.is-initiative'),
    'Unified Overview responsive module styling is missing'
);
workspaceAssert(
    str_contains($routes, '/workflow-timeline$#'),
    'Workflow timeline API route is missing'
);
workspaceAssert(
    str_contains($performanceController, "'MANAGE_AGREEMENT_REPORTS'"),
    'Agreement owners cannot open their scoped performance dashboard'
);
workspaceAssert(
    str_contains($migration, "WHERE r.role_name = 'Initiative Creator'"),
    'Initiative Creator permission repair is missing'
);
workspaceAssert(
    str_contains($authRoutes, '/legacy-initiative-handoff')
        && str_contains($authRoutes, 'legacyInitiativeHandoff()')
        && str_contains($authService, 'createLegacyInitiativeHandoff()')
        && str_contains($authService, "new DateTimeImmutable('+2 minutes')")
        && str_contains($userRepository, 'createLegacyHandoff(')
        && str_contains($handoff, 'workspace_legacy_handoffs'),
    'Secure Initiative portal handoff is missing'
);
workspaceAssert(
    str_contains($apiClient, 'sidebar-collapsed')
        && str_contains($workspaceStyles, '.workspace-app.sidebar-collapsed'),
    'The workspace side menu cannot slide or collapse responsively'
);
workspaceAssert(
    str_contains($lifecycleForm, 'lifecycle-step-timeline')
        && str_contains($lifecycleForm, 'data-lifecycle-section')
        && str_contains($lifecycleFormJs, 'flatpickr')
        && str_contains($lifecycleRequest, 'record-accordion'),
    'Lifecycle creation and review do not use the guided responsive design'
);
workspaceAssert(
    str_contains($agreementPage, 'data-export-agreement')
        && str_contains($lifecycleRequest, 'data-export-lifecycle')
        && str_contains($exportUtils, 'function csv(')
        && str_contains($exportUtils, 'printRecord'),
    'Agreement and lifecycle records are not exportable in multiple formats'
);
workspaceAssert(
    str_contains($layout, 'workspace-confirm-modal')
        && str_contains($dialog, 'WorkspaceDialog'),
    'Confirmation popups do not use the website dialog design'
);
workspaceAssert(
    str_contains($workflowReview, 'agreementReviewUrl')
        && str_contains($legalReview, 'agreementReviewUrl')
        && str_contains($financeReview, 'agreementReviewUrl')
        && str_contains($agreementJs, "'SKIPPED'"),
    'Review return navigation or conditional Finance-stage display is incomplete'
);
workspaceAssert(
    str_contains($dashboard, 'data-dashboard-priorities')
        && str_contains($dashboardJs, 'renderAgreementPriorities')
        && str_contains($dashboardInitiatives, 'renderPriorities')
        && str_contains($performanceDashboard, "'Reporting coverage'")
        && str_contains($performanceDashboard, 'deadlines'),
    'Combined role priorities and performance insights are missing from the dashboards'
);

echo "Workspace experience smoke test passed.\n";
