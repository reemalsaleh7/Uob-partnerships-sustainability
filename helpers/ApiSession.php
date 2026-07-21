<?php

declare(strict_types=1);

final class ApiSession
{
    private const REQUEST_HEADER = 'HTTP_X_UOB_TAB_SESSION';
    private const RESPONSE_HEADER = 'X-UOB-Tab-Session';
    private const IDLE_TIMEOUT_SECONDS = 1800;
    private const ABSOLUTE_TIMEOUT_SECONDS = 43200;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::enforceLifetime();
            self::exposeCurrentId();
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (self::isHttps()) {
            ini_set('session.cookie_secure', '1');
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
        self::enforceLifetime();
        self::exposeCurrentId();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        self::exposeCurrentId();
    }

    public static function markAuthenticated(): void
    {
        $now = time();
        $_SESSION['authenticated_at'] = $now;
        $_SESSION['last_activity_at'] = $now;
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

    private static function enforceLifetime(): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $now = time();
        $authenticatedAt = (int) (
            $_SESSION['authenticated_at'] ?? $now
        );
        $lastActivityAt = (int) (
            $_SESSION['last_activity_at'] ?? $now
        );

        if (
            $now - $lastActivityAt > self::IDLE_TIMEOUT_SECONDS
            || $now - $authenticatedAt > self::ABSOLUTE_TIMEOUT_SECONDS
        ) {
            $_SESSION = [];
            session_regenerate_id(true);
            return;
        }

        $_SESSION['authenticated_at'] = $authenticatedAt;
        $_SESSION['last_activity_at'] = $now;
    }

    private static function isHttps(): bool
    {
        return !empty($_SERVER['HTTPS'])
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }
}
