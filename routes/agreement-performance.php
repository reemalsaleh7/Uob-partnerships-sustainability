<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';

ApiSession::start();

require_once __DIR__ . '/../controllers/AgreementPerformanceController.php';

$controller = new AgreementPerformanceController();
$uri = '/' . ltrim((string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && $uri === '/agreement-performance-reports') {
    $controller->queue();
} elseif ($method === 'GET' && $uri === '/agreement-performance-dashboard') {
    $controller->dashboard();
} elseif (
    $method === 'GET'
    && preg_match('#^/agreements/([0-9]+)/performance-reports$#', $uri, $matches)
) {
    $controller->agreementReports((int) $matches[1]);
} elseif (
    $method === 'GET'
    && preg_match('#^/agreement-performance-reports/([0-9]+)$#', $uri, $matches)
) {
    $controller->show((int) $matches[1]);
} elseif (
    $method === 'PUT'
    && preg_match('#^/agreement-performance-reports/([0-9]+)$#', $uri, $matches)
) {
    $controller->update((int) $matches[1]);
} elseif (
    $method === 'POST'
    && preg_match('#^/agreement-performance-reports/([0-9]+)/submit$#', $uri, $matches)
) {
    $controller->submit((int) $matches[1]);
} elseif (
    $method === 'POST'
    && preg_match('#^/agreement-performance-reports/([0-9]+)/review$#', $uri, $matches)
) {
    $controller->review((int) $matches[1]);
} else {
    Response::error('Route not found', 404);
}
