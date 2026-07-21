<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';
ApiSession::start();

require_once __DIR__ . '/../controllers/AgreementLifecycleController.php';

$controller = new AgreementLifecycleController();
$uri = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $uri === '/agreement-lifecycle-requests') {
    $controller->index();
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/lifecycle-requests$#', $uri, $matches)) {
    $controller->create((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreement-lifecycle-requests/([0-9]+)$#', $uri, $matches)) {
    $controller->show((int) $matches[1]);
} elseif ($method === 'PUT' && preg_match('#^/agreement-lifecycle-requests/([0-9]+)$#', $uri, $matches)) {
    $controller->update((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreement-lifecycle-requests/([0-9]+)/submit$#', $uri, $matches)) {
    $controller->submit((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreement-lifecycle-requests/([0-9]+)/versions$#', $uri, $matches)) {
    $controller->versions((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/lifecycle-workflow-instances/([0-9]+)/decide$#', $uri, $matches)) {
    $controller->decide((int) $matches[1]);
} else {
    Response::error('Lifecycle route not found', 404);
}
