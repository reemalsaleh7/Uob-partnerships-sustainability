<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/ApiRequest.php';

function apiBoundaryAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function apiBoundaryFailure(
    string $body,
    string $contentType,
    int $status,
    ?int $declaredLength = null
): void {
    try {
        ApiRequest::decode($body, $contentType, $declaredLength);
    } catch (ApiRequestException $exception) {
        apiBoundaryAssert(
            $exception->status() === $status,
            "Expected HTTP {$status}; received {$exception->status()}"
        );
        return;
    }

    throw new RuntimeException('Expected API request validation to fail');
}

$decoded = ApiRequest::decode(
    '{"title":"Valid object","nested":{"value":1}}',
    'application/json; charset=utf-8'
);
apiBoundaryAssert(
    $decoded['nested']['value'] === 1,
    'Valid JSON object was not decoded'
);

apiBoundaryAssert(
    ApiRequest::decode('', '') === [],
    'An empty optional request body must decode as an empty object'
);

apiBoundaryFailure('{broken', 'application/json', 422);
apiBoundaryFailure('[]', 'application/json', 422);
apiBoundaryFailure('{"ok":true}', 'text/plain', 415);
apiBoundaryFailure(
    '{"ok":true}',
    'application/json',
    413,
    ApiRequest::MAX_JSON_BYTES + 1
);

foreach ([
    'AgreementController.php',
    'AgreementLifecycleController.php',
    'AgreementOperationController.php',
    'AgreementPerformanceController.php',
    'ApprovalController.php',
    'AuthController.php',
] as $controllerFile) {
    $source = file_get_contents(
        __DIR__ . '/../controllers/' . $controllerFile
    );
    apiBoundaryAssert(
        is_string($source),
        "Could not read {$controllerFile}"
    );
    apiBoundaryAssert(
        !str_contains($source, 'php://input'),
        "{$controllerFile} bypasses the centralized JSON boundary"
    );
}

echo "API boundary smoke test passed.\n";
