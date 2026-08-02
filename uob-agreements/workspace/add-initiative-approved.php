<?php

declare(strict_types=1);

/*
 * Workspace entry point for adding the final approved Initiative.
 * The URL remains inside the Workspace while the shared workflow renderer
 * supplies the PostgreSQL-backed form.
 */

$requestId = filter_input(
    INPUT_GET,
    'request_id',
    FILTER_VALIDATE_INT
);

if (!$requestId) {
    $requestId = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );
}

$_GET['view'] = 'convert';

if ($requestId && $requestId > 0) {
    $_GET['id'] = (string) $requestId;
}

require __DIR__ . '/initiative-workflow.php';
