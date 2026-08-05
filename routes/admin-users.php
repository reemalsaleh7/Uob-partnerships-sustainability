<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../controllers/AdminUserController.php';
require_once __DIR__ . '/../helpers/response.php';

ApiSession::start();
AuthMiddleware::handle();
PermissionMiddleware::require('MANAGE_USERS');

$controller = new AdminUserController();
$path = '/' . ltrim((string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/admin/users/options' && $method === 'GET') {
    $controller->options();
}

if ($path === '/admin/users' && $method === 'GET') {
    $controller->index();
}

if (preg_match('#^/admin/users/([0-9]+)$#', $path, $matches) === 1) {
    $userId = (int) $matches[1];

    if ($method === 'GET') {
        $controller->show($userId);
    }

    if ($method === 'PATCH') {
        $controller->update($userId);
    }
}

Response::error('Admin user-management route not found.', 404);
