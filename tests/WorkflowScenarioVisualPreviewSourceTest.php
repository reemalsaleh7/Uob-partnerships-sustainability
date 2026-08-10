<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$checks = [
    [
        'path' => $root . '/uob-agreements/workspace/admin-workflows.php',
        'needles' => [
            'WORKFLOW_SCENARIO_VISUAL_PREVIEW_V1',
            'data-scenario-explorer',
            'data-scenario-mode="all"',
            'assets/css/admin-workflow-scenarios.css',
            'assets/js/admin-workflow-scenarios.js',
        ],
    ],
    [
        'path' => $root . '/uob-agreements/workspace/assets/js/admin-workflows.js',
        'needles' => [
            'WORKFLOW_SCENARIO_VISUAL_PREVIEW_V1',
            'uob:workflow-stages-changed',
            'renderRouteSummary();',
        ],
    ],
    [
        'path' => $root . '/uob-agreements/workspace/assets/js/admin-workflow-scenarios.js',
        'needles' => [
            'function scenarioAt',
            'Parallel phase',
            'Show ${formatCount(next)} more scenarios',
        ],
    ],
    [
        'path' => $root . '/uob-agreements/workspace/assets/css/admin-workflow-scenarios.css',
        'needles' => [
            '.workflow-scenario-explorer',
            '.workflow-scenario-parallel-group',
            '.workflow-scenario-route',
        ],
    ],
];

$failures = [];
foreach ($checks as $check) {
    if (!is_file($check['path'])) {
        $failures[] = 'Missing file: ' . $check['path'];
        continue;
    }

    $contents = file_get_contents($check['path']);
    if ($contents === false) {
        $failures[] = 'Could not read: ' . $check['path'];
        continue;
    }

    foreach ($check['needles'] as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = sprintf(
                'Missing marker "%s" in %s',
                $needle,
                $check['path']
            );
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Workflow scenario visual preview source checks passed." . PHP_EOL;
