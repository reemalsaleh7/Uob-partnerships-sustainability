<?php

declare(strict_types=1);

function officeDelegatePositionAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$repository = file_get_contents(
    __DIR__ . '/../repositories/WorkflowRepository.php'
);
$service = file_get_contents(
    __DIR__ . '/../services/ConfigurableInitiativeWorkflowService.php'
);
$initiativeRepository = file_get_contents(
    __DIR__
    . '/../routes/initiative-workflow/InitiativeWorkflowRepository.php'
);
$reminderService = file_get_contents(
    __DIR__
    . '/../routes/initiative-workflow/InitiativeReminderService.php'
);
$migration = file_get_contents(
    __DIR__ . '/../uob-agreements/data/sql/migrations/'
    . '20260813_170700_initiative_office_delegate_position_inbox.sql'
);

officeDelegatePositionAssert(
    $repository !== false
    && str_contains($repository, 'workflow_inbox_read_states'),
    'WorkflowRepository does not use per-user inbox read states.'
);

foreach ([
    'Vice President Office Delegate',
    'President Office Delegate',
] as $positionName) {
    officeDelegatePositionAssert(
        str_contains($repository, $positionName),
        "WorkflowRepository is missing delegate Position: {$positionName}"
    );
    officeDelegatePositionAssert(
        $service !== false && str_contains($service, $positionName),
        "Initiative notification logic is missing delegate Position: {$positionName}"
    );
    officeDelegatePositionAssert(
        $migration !== false && str_contains($migration, $positionName),
        "Migration is missing delegate Position: {$positionName}"
    );
}

/*
 * VPAA delegation was added after the historical Office Delegate migration.
 * Verify the current runtime sources instead of changing that applied migration.
 */
$vpaaStageKey = 'VICE_PRESIDENT_ACADEMIC_AFFAIRS';
$vpaaDelegatePosition =
    'Vice President for Academic Affairs Office Delegate';

foreach ([
    'WorkflowRepository' => $repository,
    'ConfigurableInitiativeWorkflowService' => $service,
    'InitiativeWorkflowRepository' => $initiativeRepository,
    'InitiativeReminderService' => $reminderService,
] as $sourceName => $source) {
    officeDelegatePositionAssert(
        $source !== false
        && str_contains($source, $vpaaStageKey),
        "{$sourceName} is missing VPAA stage-key delegation support."
    );

    officeDelegatePositionAssert(
        $source !== false
        && str_contains($source, $vpaaDelegatePosition),
        "{$sourceName} is missing the VPAA Office Delegate Position."
    );
}

officeDelegatePositionAssert(
    str_contains($repository, 'wis.is_office_delegable = TRUE')
    && str_contains($repository, 'VICE_PRESIDENT')
    && str_contains($repository, 'PRESIDENT')
    && str_contains($repository, 'delegate_role_position.position_id'),
    'WorkflowRepository delegate access is not restricted by Position.'
);

officeDelegatePositionAssert(
    str_contains($service, 'JOIN positions delegate_role_position')
    && str_contains($service, "'APPROVAL_ASSIGNED:'")
    && str_contains($service, 'dedupe_key'),
    'Initiative active-phase delegate notification logic is incomplete.'
);

officeDelegatePositionAssert(
    str_contains(
        $migration,
        'CREATE TABLE IF NOT EXISTS workflow_inbox_read_states'
    )
    && str_contains($migration, 'DELETE FROM workflow_inbox_read_states')
    && str_contains($migration, 'APPROVAL_ASSIGNED'),
    'Position-based Office Delegate migration is incomplete.'
);

echo "Initiative Office Delegate Position source test passed." . PHP_EOL;