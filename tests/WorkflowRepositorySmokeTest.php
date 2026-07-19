<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/WorkflowRepository.php';

function assertSameValue(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . PHP_EOL
            . 'Expected: '
            . var_export($expected, true)
            . PHP_EOL
            . 'Actual: '
            . var_export($actual, true)
        );
    }
}

$repository = new WorkflowRepository();

$template = $repository->findActiveTemplate('AGREEMENT');

if ($template === null) {
    throw new RuntimeException(
        'Active Agreement workflow template was not found'
    );
}

$steps = $repository->findTemplateSteps(
    (int) $template['workflow_template_id']
);

$expectedKeys = [
    'CREATOR',
    'VP_INITIAL',
    'LEGAL_REVIEW',
    'FINANCE_REVIEW',
    'VP_FINAL',
    'PRESIDENT_APPROVAL',
];

assertSameValue(
    $expectedKeys,
    array_column($steps, 'step_key'),
    'Agreement workflow stage order is incorrect'
);

assertSameValue(
    false,
    (bool) $steps[2]['is_optional'],
    'Legal review must be mandatory'
);

assertSameValue(
    true,
    (bool) $steps[3]['is_optional'],
    'Finance review must be optional'
);

assertSameValue(
    'LEGAL',
    $steps[2]['required_unit_code'],
    'Legal review must resolve to the LEGAL unit'
);

assertSameValue(
    'FIN',
    $steps[3]['required_unit_code'],
    'Finance review must resolve to the FIN unit'
);

echo json_encode(
    [
        'success' => true,
        'template_id' =>
            (int) $template['workflow_template_id'],
        'stage_count' => count($steps),
        'stage_keys' => array_column($steps, 'step_key'),
        'message' =>
            'Workflow repository smoke test passed',
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;