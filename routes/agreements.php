<?php
require_once __DIR__ . '/../helpers/ApiSession.php';

ApiSession::start();

require_once __DIR__ . '/../controllers/AgreementController.php';
require_once __DIR__ . '/../controllers/AgreementOperationController.php';

$controller = new AgreementController();
$operationController = new AgreementOperationController();
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$basePaths = [];
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $basePaths[] = dirname($_SERVER['SCRIPT_NAME']);
}
$basePaths[] = '/Uob-partnerships-sustainability';

foreach ($basePaths as $basePath) {
    if ($basePath && $basePath !== '/' && strpos($uri, $basePath) === 0) {
        $uri = substr($uri, strlen($basePath));
        break;
    }
}

$uri = '/' . ltrim($uri, '/');

if ($method === 'GET' && $uri === '/agreements') {
    $controller->index();
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/annotations$#', $uri, $matches)) {
    $controller->annotations((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/annotations$#', $uri, $matches)) {
    $controller->createAnnotation((int) $matches[1]);
} elseif ($method === 'PATCH' && preg_match('#^/agreements/([0-9]+)/annotations/([0-9]+)/resolve$#', $uri, $matches)) {
    $controller->resolveAnnotation((int) $matches[1], (int) $matches[2]);
} elseif ($method === 'DELETE' && preg_match('#^/agreements/([0-9]+)/annotations/([0-9]+)$#', $uri, $matches)) {
    $controller->deleteAnnotation((int) $matches[1], (int) $matches[2]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/review-context$#', $uri, $matches)) {
    $controller->reviewContext((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/viewed$#', $uri, $matches)) {
    $controller->markViewed((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/workflow-timeline$#', $uri, $matches)) {
    $controller->workflowTimeline((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/operations$#', $uri, $matches)) {
    $operationController->summary((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/signing-record$#', $uri, $matches)) {
    $operationController->finalize((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)$#', $uri, $matches)) {
    $controller->show((int) $matches[1]);
} elseif ($method === 'POST' && $uri === '/agreements') {
    $controller->create();
} elseif ($method === 'PUT' && preg_match('#^/agreements/([0-9]+)$#', $uri, $matches)) {
    $controller->update((int) $matches[1]);
} elseif ($method === 'DELETE' && preg_match('#^/agreements/([0-9]+)$#', $uri, $matches)) {
    $controller->delete((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/submit$#', $uri, $matches)) {
    $controller->submit((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/resubmit$#', $uri, $matches)) {
    $controller->resubmit((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/versions/([0-9]+)$#', $uri, $matches)) {
    $controller->version((int) $matches[1], (int) $matches[2]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/versions$#', $uri, $matches)) {
    $controller->versions((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/agreements/([0-9]+)/documents$#', $uri, $matches)) {
    $controller->documents((int) $matches[1]);
} elseif ($method === 'POST' && preg_match('#^/agreements/([0-9]+)/documents$#', $uri, $matches)) {
    $controller->uploadDocument((int) $matches[1]);
} elseif ($method === 'GET' && preg_match('#^/documents/([0-9]+)/download$#', $uri, $matches)) {
    $controller->downloadDocument((int) $matches[1]);
} elseif ($method === 'DELETE' && preg_match('#^/documents/([0-9]+)$#', $uri, $matches)) {
    $controller->deleteDocument((int) $matches[1]);
} else {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Route not found']);
}
