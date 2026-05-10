<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Tiny static response helpers — just sugar around `header()` + `echo`. The
 * methods all `exit` after writing so a controller never accidentally renders
 * twice.
 */
final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        self::corsHeadersIfNeeded();
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function noContent(int $status = 204): never
    {
        http_response_code($status);
        self::corsHeadersIfNeeded();
        exit;
    }

    public static function html(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function redirect(string $location, int $status = 302): never
    {
        http_response_code($status);
        header("Location: $location");
        exit;
    }

    public static function notFound(string $message = 'Not found'): never
    {
        // Distinguish API vs admin by URL prefix so a missing API route gets
        // JSON (mobile parses easily) while a missing admin route gets HTML.
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        if (str_starts_with($path, '/api/')) {
            self::json(['error' => 'not_found', 'message' => $message], 404);
        }
        self::html("<h1>404</h1><p>$message</p>", 404);
    }

    /** Emit CORS headers if the request looks cross-origin and the env allows it. */
    private static function corsHeadersIfNeeded(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') return;

        $allowed = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));
        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        }
    }
}
