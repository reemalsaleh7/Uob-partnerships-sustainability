<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../controllers/ConfigurableWorkflowController.php';
require_once __DIR__ . '/../helpers/response.php';

ApiSession::start();
AuthMiddleware::handle();

$controller = new ConfigurableWorkflowController();
$path = '/' . ltrim((string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($path === '/configurable-workflows/inbox' && $method === 'GET') {
    $controller->inbox();
}

if (preg_match(
    '#^/configurable-workflows/([0-9]+)$#',
    $path,
    $matches
) === 1 && $method === 'GET') {
    $controller->show((int) $matches[1]);
}

if (preg_match(
    '#^/configurable-workflows/([0-9]+)/decision$#',
    $path,
    $matches
) === 1 && $method === 'POST') {
    $controller->decide((int) $matches[1]);
}

Response::error('Configurable Workflow route not found.', 404);
