<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__
    . '/../controllers/ApprovalController.php';

$controller = new ApprovalController();

$uri = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$method =
    $_SERVER['REQUEST_METHOD'] ?? 'GET';

$uri = '/' . ltrim((string) $uri, '/');

if (
    $method === 'GET'
    && $uri === '/workflow-inbox'
) {
    $controller->inbox();
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/initial-vp/approve$#',
        $uri,
        $matches
    )
) {
    $controller->approveInitialVp(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/specialist/approve$#',
        $uri,
        $matches
    )
) {
    $controller->approveSpecialist(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/final-vp/approve$#',
        $uri,
        $matches
    )
) {
    $controller->approveFinalVp(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/president/approve$#',
        $uri,
        $matches
    )
) {
    $controller->approvePresident(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/changes/request$#',
        $uri,
        $matches
    )
) {
    $controller->requestChanges(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/vp/route$#',
        $uri,
        $matches
    )
) {
    $controller->routeByVp(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/redraft/resubmit$#',
        $uri,
        $matches
    )
) {
    $controller->resubmitRedraft(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/vp/decide$#',
        $uri,
        $matches
    )
) {
    $controller->decideVpOutcome(
        (int) $matches[1]
    );
} elseif (
    $method === 'POST'
    && preg_match(
        '#^/workflow-instances/([0-9]+)/president/reject$#',
        $uri,
        $matches
    )
) {
    $controller->rejectPresident(
        (int) $matches[1]
    );
} else {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'error' =>
            'Approval route not found',
    ]);
}