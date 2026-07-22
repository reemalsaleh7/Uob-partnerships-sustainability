<?php

declare(strict_types=1);

final class ApiRequestException extends InvalidArgumentException
{
    public function __construct(string $message, private int $status)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}

final class ApiRequest
{
    public const MAX_JSON_BYTES = 1048576;

    public static function json(): array
    {
        $declaredLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            self::fail('Request body could not be read', 400);
        }

        try {
            return self::decode(
                $raw,
                (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                $declaredLength
            );
        } catch (ApiRequestException $exception) {
            self::fail($exception->getMessage(), $exception->status());
        }
    }

    public static function decode(
        string $raw,
        string $contentType,
        ?int $declaredLength = null
    ): array {
        if (
            ($declaredLength ?? strlen($raw)) > self::MAX_JSON_BYTES
            || strlen($raw) > self::MAX_JSON_BYTES
        ) {
            throw new ApiRequestException(
                'JSON request body exceeds 1 MB',
                413
            );
        }
        if (trim($raw) === '') {
            return [];
        }

        $mediaType = trim(explode(
            ';',
            strtolower(trim($contentType)),
            2
        )[0]);
        if (
            $mediaType !== 'application/json'
            && !str_ends_with($mediaType, '+json')
        ) {
            throw new ApiRequestException(
                'Content-Type must be application/json',
                415
            );
        }
        if (ltrim($raw)[0] !== '{') {
            throw new ApiRequestException(
                'JSON request body must be an object',
                422
            );
        }

        try {
            $decoded = json_decode(
                $raw,
                true,
                64,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new ApiRequestException(
                'Malformed JSON request body',
                422
            );
        }

        if (!is_array($decoded)) {
            throw new ApiRequestException(
                'JSON request body must be an object',
                422
            );
        }

        return $decoded;
    }

    private static function fail(string $message, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
        ]);
        exit;
    }
}
