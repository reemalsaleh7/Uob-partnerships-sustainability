<?php
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/ApiSession.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthMiddleware {
    public static function handle(): void {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            Response::error('Unauthorized', 401);
        }

        $users = new UserRepository();
        if (!$users->isActive($userId)) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                ApiSession::regenerate();
            }
            Response::error('Unauthorized', 401);
        }
    }
}
