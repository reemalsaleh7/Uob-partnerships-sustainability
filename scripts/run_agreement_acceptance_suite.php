<?php

declare(strict_types=1);

$quick = in_array('--quick', $argv, true);
$tests = [
    'ApiBoundarySmokeTest.php',
    'AuthenticationHardeningSmokeTest.php',
    'AgreementReleaseReadinessSmokeTest.php',
    'WorkflowRepositorySmokeTest.php',
    'HierarchyResolverSmokeTest.php',
    'AgreementSubmissionWorkflowSmokeTest.php',
    'AgreementDocumentAuthorizationSmokeTest.php',
    'PublicAgreementRepositorySmokeTest.php',
    'AgreementLifecycleWorkflowSmokeTest.php',
    'AgreementLifecycleDocumentAuthorizationSmokeTest.php',
    'AgreementLifecycleSuccessorSmokeTest.php',
    'AgreementOperationalStatusSmokeTest.php',
    'AgreementPerformanceMonitoringSmokeTest.php',
    'AgreementAnnotationSmokeTest.php',
];

if (!$quick) {
    $tests = array_merge($tests, [
        'ApprovalServiceStartSmokeTest.php',
        'InitialVpDecisionSmokeTest.php',
        'InitialVpNoFinanceSmokeTest.php',
        'SpecialistReviewSmokeTest.php',
        'FinalVpReviewSmokeTest.php',
        'PresidentApprovalSmokeTest.php',
        'ReturnWorkflowRepositorySmokeTest.php',
        'AgreementChangeRequestSmokeTest.php',
        'VpRoutingDecisionSmokeTest.php',
        'AgreementRedraftResubmissionSmokeTest.php',
        'VpDirectDecisionSmokeTest.php',
        'PresidentRejectionSmokeTest.php',
        'ComprehensiveAgreementSmokeTest.php',
        'LegacyAgreementCsvMapperSmokeTest.php',
        'LegacyAgreementImportVerification.php',
    ]);
}

$tests = array_values(array_unique($tests));
$failures = [];
$startedAt = microtime(true);

foreach ($tests as $index => $test) {
    $path = dirname(__DIR__) . '/tests/' . $test;
    printf(
        "\n[%d/%d] %s\n",
        $index + 1,
        count($tests),
        $test
    );

    if (!is_file($path)) {
        $failures[$test] = 127;
        echo "Test file not found.\n";
        continue;
    }

    $command = escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($path);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        $failures[$test] = $exitCode;
    }
}

$duration = round(microtime(true) - $startedAt, 2);
echo "\nAgreement acceptance suite summary\n";
echo 'Mode: ' . ($quick ? 'quick' : 'full') . "\n";
echo 'Tests: ' . count($tests) . "\n";
echo 'Passed: ' . (count($tests) - count($failures)) . "\n";
echo 'Failed: ' . count($failures) . "\n";
echo "Duration: {$duration} seconds\n";

if ($failures !== []) {
    foreach ($failures as $test => $exitCode) {
        echo "FAIL {$test} (exit {$exitCode})\n";
    }
    exit(1);
}

echo "All Agreement acceptance tests passed.\n";
