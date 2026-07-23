<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/HierarchyResolver.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

function hierarchyAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$hierarchyResolver = new HierarchyResolver();
$userRepository = new UserRepository();

$expectedOfficeUsers = [
    'VP' => 'dev.vp@uob.test',
    'LEGAL' => 'dev.legal@uob.test',
    'FIN' => 'dev.finance@uob.test',
    'PRES' => 'dev.president@uob.test',
];

$resolvedOffices = [];

foreach ($expectedOfficeUsers as $unitCode => $expectedEmail) {
    $users = $hierarchyResolver
        ->resolveEligibleApprovers($unitCode);

    $emails = array_column($users, 'email');

    hierarchyAssert(
        in_array($expectedEmail, $emails, true),
        "{$expectedEmail} was not resolved for {$unitCode}"
    );

    $resolvedOffices[$unitCode] = $emails;
}

$creatorExpectations = [
    'dev.dean@uob.test' => true,
    'dev.vp@uob.test' => true,
    'dev.president@uob.test' => true,
    'dev.faculty@uob.test' => false,
    'dev.legal@uob.test' => false,
    'dev.finance@uob.test' => false,
];

$creatorResults = [];

foreach ($creatorExpectations as $email => $expected) {
    $user = $userRepository->findByEmail($email);

    hierarchyAssert(
        $user !== null,
        "Development user {$email} was not found"
    );

    $actual = $hierarchyResolver->canStartAgreement(
        (int) $user['user_id']
    );

    hierarchyAssert(
        $actual === $expected,
        "Unexpected Agreement creator eligibility for {$email}"
    );

    $creatorResults[$email] = $actual;
}

echo json_encode(
    [
        'success' => true,
        'resolved_offices' => $resolvedOffices,
        'creator_eligibility' => $creatorResults,
        'message' => 'Hierarchy resolver smoke test passed',
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;