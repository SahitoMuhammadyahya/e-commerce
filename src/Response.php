<?php

namespace EssenceStore;

class Response
{
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success(mixed $data, int $statusCode = 200, ?array $meta = null): void
    {
        $payload = ['status' => 'success'];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }
        $payload['data'] = $data;
        self::json($payload, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, string $code = 'BAD_REQUEST', array $errors = []): void
    {
        $payload = [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $statusCode);
    }
}
