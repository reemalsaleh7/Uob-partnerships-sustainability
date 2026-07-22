<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

function authHardeningAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$users = new UserRepository();
$db->beginTransaction();

try {
    $statement = $db->query(
        'SELECT user_id FROM users WHERE is_active = TRUE ORDER BY user_id LIMIT 1'
    );
    $userId = (int) $statement->fetchColumn();
    authHardeningAssert($userId > 0, 'An active test user is required');

    $users->resetFailedAttempts($userId);
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $users->recordFailedLogin($userId);
    }

    $read = $db->prepare(
        'SELECT failed_login_attempts, locked_until
         FROM users WHERE user_id = :user_id'
    );
    $read->execute(['user_id' => $userId]);
    $beforeLock = $read->fetch();
    authHardeningAssert(
        (int) $beforeLock['failed_login_attempts'] === 4,
        'The failed-attempt counter did not reach four'
    );
    authHardeningAssert(
        $beforeLock['locked_until'] === null,
        'The account locked before the fifth failure'
    );

    $users->recordFailedLogin($userId);
    $read->execute(['user_id' => $userId]);
    $locked = $read->fetch();
    authHardeningAssert(
        (int) $locked['failed_login_attempts'] === 5,
        'The fifth failed attempt was not recorded'
    );
    authHardeningAssert(
        strtotime((string) $locked['locked_until']) > time(),
        'The fifth failure did not create a temporary lock'
    );

    $users->resetFailedAttempts($userId);
    $read->execute(['user_id' => $userId]);
    $reset = $read->fetch();
    authHardeningAssert(
        (int) $reset['failed_login_attempts'] === 0
        && $reset['locked_until'] === null,
        'Successful-login reset did not clear protection state'
    );

    echo "Authentication hardening smoke test passed; transaction rolled back.\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

