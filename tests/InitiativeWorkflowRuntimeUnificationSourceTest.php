<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$enumMigration = $root
    . '/uob-agreements/data/sql/migrations/'
    . '20260805_111900_expand_workflow_step_status.sql';
$migration = $root
    . '/uob-agreements/data/sql/migrations/'
    . '20260805_112000_unify_initiative_workflow_runtime.sql';
$service = $root
    . '/services/ConfigurableInitiativeWorkflowService.php';
$reminder = $root
    . '/routes/initiative-workflow/InitiativeReminderService.php';
$documentation = $root
    . '/docs/initiative-workflow-runtime-unification.md';

$failures = [];

$assert = static function (
    bool $condition,
    string $message
) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach ([
    $enumMigration,
    $migration,
    $service,
    $reminder,
    $documentation,
] as $requiredFile) {
    $assert(
        is_file($requiredFile),
        'Required file is missing: ' . $requiredFile
    );
}

$enumMigrationSource = is_file($enumMigration)
    ? (string) file_get_contents($enumMigration)
    : '';
$migrationSource = is_file($migration)
    ? (string) file_get_contents($migration)
    : '';
$serviceSource = is_file($service)
    ? (string) file_get_contents($service)
    : '';
$reminderSource = is_file($reminder)
    ? (string) file_get_contents($reminder)
    : '';


foreach ([
    'CHANGES_REQUESTED',
    'DISCUSSING_REVISION',
    'CANCELLED',
] as $requiredStatus) {
    $assert(
        str_contains($enumMigrationSource, $requiredStatus),
        'Workflow status expansion is missing: ' . $requiredStatus
    );
}

$assert(
    preg_match(
        '/^\s*(BEGIN|COMMIT|ROLLBACK)\s*;/mi',
        $enumMigrationSource
    ) !== 1,
    'Status expansion migration must not own the database transaction.'
);

foreach ([
    'DROP TABLE initiative_request_stages',
    'DROP TABLE initiative_request_events',
    'DROP TABLE initiative_admin_skips',
    'CREATE VIEW initiative_request_stages',
    'CREATE VIEW initiative_request_events',
    'CREATE VIEW initiative_admin_skips',
    'workflow_instances',
    'workflow_instance_steps',
    'workflow_step_assignments',
    'workflow_history',
    'initiative_request_stages_compat_write',
    'initiative_request_events_compat_write',
    'initiative_admin_skips_compat_write',
    'CONFIGURABLE_INITIATIVE_V2',
    'INITIATIVE_REQUEST',
] as $requiredMarker) {
    $assert(
        str_contains($migrationSource, $requiredMarker),
        'Migration marker is missing: ' . $requiredMarker
    );
}

$assert(
    preg_match(
        '/^\s*(BEGIN|COMMIT|ROLLBACK)\s*;/mi',
        $migrationSource
    ) !== 1,
    'Migration must not own the database transaction.'
);

$assert(
    !str_contains(
        $migrationSource,
        'ALTER COLUMN status TYPE VARCHAR'
    ),
    'The shared Workflow step status must remain the existing enum type.'
);

foreach ([
    'stage.status::workflow_step_status',
    'CAST(:status AS workflow_status)',
    'CAST(:status AS workflow_step_status)',
] as $typedStatusMarker) {
    $combinedSource = $migrationSource . "
" . $serviceSource;
    $assert(
        str_contains($combinedSource, $typedStatusMarker),
        'Typed Workflow status marker is missing: '
        . $typedStatusMarker
    );
}

foreach ([
    'workflow_instances',
    'workflow_instance_steps',
    'workflow_step_assignments',
    'workflow_history',
] as $sharedRuntimeMarker) {
    $assert(
        str_contains($serviceSource, $sharedRuntimeMarker),
        'Configurable service does not use: '
        . $sharedRuntimeMarker
    );
}

foreach ([
    'initiative_request_stages',
    'initiative_request_events',
    'initiative_admin_skips',
] as $duplicatedRelation) {
    $assert(
        !str_contains($serviceSource, $duplicatedRelation),
        'Configurable service still depends on duplicated relation: '
        . $duplicatedRelation
    );
    $assert(
        !str_contains($reminderSource, $duplicatedRelation),
        'Reminder service still depends on duplicated relation: '
        . $duplicatedRelation
    );
}

foreach ([
    'workflow_instances',
    'workflow_instance_steps',
    'workflow_step_assignments',
    'workflow_history',
] as $reminderMarker) {
    $assert(
        str_contains($reminderSource, $reminderMarker),
        'Reminder service does not use: ' . $reminderMarker
    );
}

if ($failures !== []) {
    fwrite(
        STDERR,
        "Initiative Workflow runtime unification source test failed:\n"
    );
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Initiative Workflow runtime unification source test passed."
    . PHP_EOL;
