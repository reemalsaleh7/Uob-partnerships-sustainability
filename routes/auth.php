<?php
require_once __DIR__ . '/../helpers/ApiSession.php';

ApiSession::start();

require_once __DIR__ . '/../controllers/AuthController.php';

$controller = new AuthController();

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

if ($uri === '/login' && $method === 'POST') {
    $controller->login();
} elseif ($uri === '/logout' && $method === 'POST') {
    $controller->logout();
} elseif ($uri === '/me' && $method === 'GET') {
    $controller->me();
} else {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Route not found']);
}