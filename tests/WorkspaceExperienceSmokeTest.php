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
$agreementPage = workspaceSource('uob-agreements/workspace/agreement.php');
$agreementJs = workspaceSource('uob-agreements/workspace/assets/js/agreement-detail.js');
$routes = workspaceSource('routes/agreements.php');
$authRoutes = workspaceSource('routes/auth.php');
$handoff = workspaceSource('uob-agreements/workspace-handoff.php');
$performanceController = workspaceSource(
    'controllers/AgreementPerformanceController.php'
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
        && str_contains($handoff, 'workspace_legacy_handoffs'),
    'Secure Initiative portal handoff is missing'
);

echo "Workspace experience smoke test passed.\n";
