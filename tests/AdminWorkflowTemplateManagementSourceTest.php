<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'repositories/AdminWorkflowTemplateRepository.php',
    'services/AdminWorkflowTemplateService.php',
    'services/WorkflowActorResolver.php',
    'services/ConfigurableAgreementWorkflowService.php',
    'services/ConfigurableInitiativeWorkflowService.php',
    'controllers/AdminWorkflowTemplateController.php',
    'controllers/ConfigurableWorkflowController.php',
    'routes/admin-workflows.php',
    'routes/configurable-workflows.php',
    'uob-agreements/workspace/admin-workflows.php',
    'uob-agreements/workspace/workflow-generic-review.php',
    'uob-agreements/workspace/assets/js/admin-workflows.js',
    'uob-agreements/workspace/assets/js/workflow-generic-review.js',
    'uob-agreements/workspace/assets/js/workflow-inbox.js',
    'uob-agreements/data/sql/migrations/20260805_093900_admin_workflow_template_management.sql',
    'api/index.php',
    'services/ApprovalService.php',
    'routes/initiative-workflow/InitiativeWorkflowRepository.php',
    'uob-agreements/workspace/admin-users.php',
    'uob-agreements/workspace/assets/js/workspace-sidebar-polish.js',
];

$failures = [];
foreach ($requiredFiles as $relative) {
    if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
        $failures[] = 'Missing file: ' . $relative;
    }
}

$assertions = [
    'services/AdminWorkflowTemplateService.php' => [
        'SEQUENTIAL',
        'PARALLEL',
        'REQUESTER_DEPARTMENT',
        'REQUESTER_COLLEGE',
        'existing_workflow_snapshots_are_preserved',
    ],
    'services/ConfigurableAgreementWorkflowService.php' => [
        "engine_version = 'CONFIGURABLE'",
        'includeOptionalStepKeys',
        'phaseHasOpenSteps',
        'REQUEST_CHANGES',
    ],
    'services/ConfigurableInitiativeWorkflowService.php' => [
        'current_phase_order',
        'phaseHasOpenStages',
        'workflow_template_version',
        'REQUEST_REVISION',
    ],
    'uob-agreements/workspace/assets/js/admin-workflows.js' => [
        'moveStage',
        'execution_mode',
        'responsibility_scope',
        'Publish new version',
    ],
    'uob-agreements/workspace/assets/js/workflow-inbox.js' => [
        '/configurable-workflows/inbox',
        'workflow-generic-review.php',
    ],
    'routes/admin-workflows.php' => [
        'MANAGE_WORKFLOW_TEMPLATES',
    ],
    'uob-agreements/workspace/admin-workflows.php' => [
        'Add stage',
        'Publish new version',
    ],
    'api/index.php' => [
        '/admin/workflows',
        '/configurable-workflows',
    ],
    'services/ApprovalService.php' => [
        'ConfigurableAgreementWorkflowService',
        'ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1',
    ],
    'routes/initiative-workflow/InitiativeWorkflowRepository.php' => [
        'ConfigurableInitiativeWorkflowService',
        'ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1_DECISION',
        'current_phase_order = NULL',
    ],
    'uob-agreements/workspace/admin-users.php' => [
        'admin-workflows.php',
    ],
    'uob-agreements/workspace/assets/js/workspace-sidebar-polish.js' => [
        'adminWorkflowsNav',
        'MANAGE_WORKFLOW_TEMPLATES',
    ],
];

foreach ($assertions as $relative => $needles) {
    $content = file_get_contents(
        $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)
    );
    if ($content === false) {
        $failures[] = 'Could not read: ' . $relative;
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = "Missing source assertion '{$needle}' in {$relative}";
        }
    }
}

$migrationPath = $root
    . '/uob-agreements/data/sql/migrations/'
    . '20260805_093900_admin_workflow_template_management.sql';
$migration = file_get_contents($migrationPath);
if ($migration === false) {
    $failures[] = 'Could not read migration.';
} else {
    if (preg_match('/^\s*(BEGIN|COMMIT|ROLLBACK)\s*;\s*$/mi', $migration) === 1) {
        $failures[] = 'Migration contains top-level transaction control.';
    }
    foreach ([
        'workflow_template_publications',
        'template_key',
        'version_number',
        'phase_order',
        'execution_mode',
        'responsibility_type',
        'responsibility_scope',
    ] as $needle) {
        if (!str_contains($migration, $needle)) {
            $failures[] = "Migration is missing {$needle}.";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Admin Workflow template management source checks passed." . PHP_EOL;
