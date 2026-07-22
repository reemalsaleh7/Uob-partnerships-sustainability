<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = trim((string) ($_GET['token'] ?? ''));
$target = trim((string) ($_GET['to'] ?? 'request-initiative.php?lang=en'));

if (preg_match('/\A[a-f0-9]{64}\z/D', $token) !== 1) {
    http_response_code(400);
    exit('The workspace handoff link is invalid.');
}

$allowedTarget = preg_match(
    '/\A(?:request-initiative|initiatives|agreements|sdg)\.php(?:\?[A-Za-z0-9_=&%.-]*)?\z/D',
    $target
) === 1;

if (!$allowedTarget) {
    $target = 'request-initiative.php?lang=en';
}

$db = Database::connect();
$db->beginTransaction();

try {
    $statement = $db->prepare(
        'WITH consumed AS (
            UPDATE workspace_legacy_handoffs
            SET used_at = NOW()
            WHERE token_hash = :token_hash
              AND used_at IS NULL
              AND expires_at >= NOW()
            RETURNING user_id
         )
         SELECT u.user_id, u.email,
                EXISTS (
                    SELECT 1
                    FROM user_roles ur
                    JOIN roles r ON r.role_id = ur.role_id
                    WHERE ur.user_id = u.user_id
                      AND r.role_name = \'System Administrator\'
                ) AS is_administrator
         FROM consumed c
         JOIN users u ON u.user_id = c.user_id
         WHERE u.is_active = TRUE'
    );
    $statement->execute(['token_hash' => hash('sha256', $token)]);
    $user = $statement->fetch();

    if (!$user) {
        $db->rollBack();
        http_response_code(410);
        exit('This workspace handoff link has expired or was already used.');
    }

    $db->commit();
    session_regenerate_id(true);
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = in_array(
        $user['is_administrator'],
        [true, 1, '1', 't', 'true'],
        true
    ) ? 'admin' : 'user';
    $_SESSION['workspace_user_id'] = (int) $user['user_id'];

    header('Location: ' . $target, true, 303);
    exit;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}
