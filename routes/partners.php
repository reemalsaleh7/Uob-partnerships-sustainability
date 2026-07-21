<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiSession.php';

ApiSession::start();

require_once __DIR__ . '/../controllers/PartnerController.php';

$controller = new PartnerController();
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$uri = '/' . ltrim((string) $uri, '/');

if ($method === 'GET' && $uri === '/partners') {
    $controller->index();
}

header('HTTP/1.1 404 Not Found');
echo json_encode([
    'success' => false,
    'error' => 'Route not found',
]);
