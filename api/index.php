<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

$scriptName = str_replace(
    '\\',
    '/',
    $_SERVER['SCRIPT_NAME'] ?? '/api/index.php'
);

$apiBasePath = rtrim(dirname($scriptName), '/.');

if (
    $apiBasePath !== ''
    && $apiBasePath !== '/'
    && str_starts_with($requestPath, $apiBasePath)
) {
    $requestPath = substr($requestPath, strlen($apiBasePath));
}

$requestPath = preg_replace(
    '#^/index\.php#',
    '',
    $requestPath
);

$requestPath = '/' . ltrim($requestPath, '/');

// Give route modules a normalized path such as /agreements.
$_SERVER['REQUEST_URI'] = $requestPath;

try {
    if ($requestPath === '/') {
        http_response_code(200);

        echo json_encode([
            'success' => true,
            'message' => 'UOB Partnerships API is running',
        ]);

        exit;
    }

    if (in_array($requestPath, ['/login', '/logout', '/me'], true)) {
        require dirname(__DIR__) . '/routes/auth.php';
        exit;
    }

    if (
    $requestPath === '/workflow-inbox'
    || str_starts_with(
        $requestPath,
        '/workflow-instances/'
    )
) {
    require dirname(__DIR__)
        . '/routes/approvals.php';

    exit;
}

    if (
        str_starts_with($requestPath, '/agreements')
        || str_starts_with($requestPath, '/documents')
    ) {
        require dirname(__DIR__) . '/routes/agreements.php';
        exit;
    }

    if ($requestPath === '/partners') {
        require dirname(__DIR__) . '/routes/partners.php';
        exit;
    }

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'error' => 'API route not found',
    ]);
} catch (Throwable $exception) {
    error_log($exception->__toString());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}
