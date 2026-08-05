<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../controllers/AdminWorkflowTemplateController.php';
require_once __DIR__ . '/../helpers/response.php';

ApiSession::start();
AuthMiddleware::handle();
PermissionMiddleware::require('MANAGE_WORKFLOW_TEMPLATES');

$controller = new AdminWorkflowTemplateController();
$path = '/' . ltrim((string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/admin/workflows' && $method === 'GET') {
    $controller->index();
}

if ($path === '/admin/workflows/options' && $method === 'GET') {
    $controller->options();
}

if (preg_match(
    '#^/admin/workflows/([A-Za-z0-9_]+)$#',
    $path,
    $matches
) === 1) {
    if ($method === 'GET') {
        $controller->show($matches[1]);
    }
    if ($method === 'POST') {
        $controller->publish($matches[1]);
    }
}

if (preg_match(
    '#^/admin/workflows/([A-Za-z0-9_]+)/versions/([0-9]+)$#',
    $path,
    $matches
) === 1 && $method === 'GET') {
    $controller->version($matches[1], (int) $matches[2]);
}

Response::error('Admin Workflow-template route not found.', 404);
