<?php
class Response {
    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function error(string $message, int $status = 400): void {
        self::json(['success' => false, 'error' => $message], $status);
    }

    public static function success($data = [], int $status = 200): void {
        self::json(['success' => true, 'data' => $data], $status);
    }
}