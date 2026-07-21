<?php

declare(strict_types=1);

final class ApiSession
{
    private const REQUEST_HEADER = 'HTTP_X_UOB_TAB_SESSION';
    private const RESPONSE_HEADER = 'X-UOB-Tab-Session';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::exposeCurrentId();
            return;
        }

        $tabSessionId = trim(
            (string) ($_SERVER[self::REQUEST_HEADER] ?? '')
        );

        if ($tabSessionId !== '') {
            self::validateId($tabSessionId);

            // A workspace tab supplies its own session identifier. Do not
            // read or replace the browser-wide PHPSESSID cookie in this mode.
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
            session_id($tabSessionId);
        }

        session_start();
        self::exposeCurrentId();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        self::exposeCurrentId();
    }

    public static function exposeCurrentId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        header(self::RESPONSE_HEADER . ': ' . session_id());
        header('Vary: X-UOB-Tab-Session', false);
    }

    private static function validateId(string $sessionId): void
    {
        if (
            preg_match(
                '/\A[A-Za-z0-9,-]{16,128}\z/D',
                $sessionId
            ) === 1
        ) {
            return;
        }

        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error' => 'Invalid tab session identifier.',
        ]);

        exit;
    }
}
