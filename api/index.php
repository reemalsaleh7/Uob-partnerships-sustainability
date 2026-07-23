<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$requestId = bin2hex(random_bytes(12));
header('X-Request-Id: ' . $requestId);

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
    $requestMethod = strtoupper((string) (
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ));
    if (
        in_array($requestMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        && trim((string) ($_SERVER['HTTP_X_UOB_TAB_SESSION'] ?? '')) === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'The workspace tab session header is required.',
        ]);
        exit;
    }

    if ($requestPath === '/') {
        http_response_code(200);

        echo json_encode([
            'success' => true,
            'message' => 'UOB Partnerships API is running',
        ]);

        exit;
    }

    if (in_array(
        $requestPath,
        ['/login', '/logout', '/me', '/legacy-initiative-handoff'],
        true
    )) {
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
        str_starts_with($requestPath, '/agreement-performance-reports')
        || $requestPath === '/agreement-performance-dashboard'
        || preg_match(
            '#^/agreements/[0-9]+/performance-reports$#',
            $requestPath
        )
    ) {
        require dirname(__DIR__) . '/routes/agreement-performance.php';
        exit;
    }

    if (
        str_starts_with($requestPath, '/agreements')
        || str_starts_with($requestPath, '/documents')
    ) {
        if (str_contains($requestPath, '/lifecycle-requests')) {
            require dirname(__DIR__) . '/routes/agreement-lifecycle.php';
            exit;
        }
        require dirname(__DIR__) . '/routes/agreements.php';
        exit;
    }

    if (
        str_starts_with($requestPath, '/agreement-lifecycle-requests')
        || str_starts_with($requestPath, '/lifecycle-request-documents')
        || str_starts_with($requestPath, '/lifecycle-workflow-instances')
    ) {
        require dirname(__DIR__) . '/routes/agreement-lifecycle.php';
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
    error_log(sprintf(
        '[UOB API %s] %s',
        $requestId,
        $exception->__toString()
    ));

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'request_id' => $requestId,
    ]);
}
