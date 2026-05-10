<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Immutable view of the incoming HTTP request. Built once via [fromGlobals]
 * and passed through the router → controller chain so handlers don't poke at
 * the superglobals directly (easier to test, easier to mock).
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        /** @var array<string, string> Captured route parameters like {id}. */
        public array $params = [],
    ) {}

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        // Parse JSON body for API requests; fall back to form-encoded for admin.
        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            method:  strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path:    $path,
            query:   $_GET,
            body:    $body,
            headers: self::collectHeaders(),
        );
    }

    public function bearerToken(): ?string
    {
        $auth = $this->headers['authorization'] ?? '';
        if (stripos($auth, 'bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return null;
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    private static function collectHeaders(): array
    {
        $out = [];
        foreach ($_SERVER as $key => $value) {
            if (strncmp($key, 'HTTP_', 5) === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $out[$name] = $value;
            }
        }
        // Apache CGI sometimes hides Authorization elsewhere.
        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                $out[strtolower($k)] = $v;
            }
        }
        return $out;
    }
}
