<?php
declare(strict_types=1);

namespace Devithor;

use RuntimeException;

/**
 * Two auth surfaces share this helper:
 *
 *  1. **Mobile API** — bearer tokens stored in the `auth_tokens` table.
 *     Format: <random>.<hmac> where <hmac> = HMAC-SHA256(<random>, APP_KEY).
 *     We verify HMAC first (cheap, no DB hit), then look up the row.
 *
 *  2. **Admin web UI** — PHP session set by AdminAuthController on login.
 *     Session ids are stored server-side (PHP default), only an opaque cookie
 *     ever reaches the browser.
 *
 * Both flows funnel into [requireUser] / [requireAdmin] middleware which
 * either populate the request with the user, or short-circuit with 401/403.
 */
final class Auth
{
    /** Generate + persist a fresh API token for the given user. Returns the token to send to the client. */
    public static function issueApiToken(string $userId, int $ttlSeconds = 60 * 60 * 24 * 90): string
    {
        $random = bin2hex(random_bytes(24));
        $hmac   = self::hmac($random);
        $token  = "$random.$hmac";

        $tokenHash = hash('sha256', $token); // store the hash, not the plaintext
        Database::exec(
            'INSERT INTO auth_tokens (token_hash, user_id, created_at, expires_at)
             VALUES (?, ?, ?, ?)',
            [
                $tokenHash,
                $userId,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s', time() + $ttlSeconds),
            ],
        );
        return $token;
    }

    /** Resolve a bearer token to a user row, or null if invalid/expired. */
    public static function userFromToken(string $token): ?array
    {
        if (!str_contains($token, '.')) return null;
        [$random, $providedHmac] = explode('.', $token, 2);
        $expectedHmac = self::hmac($random);
        if (!hash_equals($expectedHmac, $providedHmac)) {
            // Bad signature — don't even hit the DB.
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $row = Database::one(
            'SELECT u.* FROM auth_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > NOW()
             LIMIT 1',
            [$tokenHash],
        );
        return $row;
    }

    /** Revoke a token (logout). */
    public static function revokeApiToken(string $token): void
    {
        Database::exec('DELETE FROM auth_tokens WHERE token_hash = ?', [hash('sha256', $token)]);
    }

    /** Hash a password for the users table. Uses PHP's preferred algorithm. */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // ---- Middleware factories -------------------------------------------

    /** Require a valid Bearer token on the request; populate $request->params['user']. */
    public static function requireUser(): callable
    {
        return function (Request $request, callable $next) {
            $token = $request->bearerToken();
            if ($token === null) {
                Response::json(['error' => 'unauthenticated'], 401);
            }
            $user = self::userFromToken($token);
            if ($user === null) {
                Response::json(['error' => 'unauthenticated'], 401);
            }
            $request->params['user'] = $user;
            return $next($request);
        };
    }

    /** Require an admin session cookie. Used only by /admin/* routes. */
    public static function requireAdmin(): callable
    {
        return function (Request $request, callable $next) {
            $userId = $_SESSION['admin_user_id'] ?? null;
            if (!$userId) {
                Response::redirect('/admin/login');
            }
            $user = Database::one('SELECT * FROM users WHERE id = ? AND role = ? LIMIT 1', [$userId, 'ADMIN']);
            if (!$user) {
                // Session pointed at a non-admin (or deleted) user — purge.
                unset($_SESSION['admin_user_id']);
                Response::redirect('/admin/login');
            }
            $request->params['user'] = $user;
            return $next($request);
        };
    }

    private static function hmac(string $value): string
    {
        $key = getenv('APP_KEY');
        if (!$key) throw new RuntimeException('APP_KEY not set in .env');
        return hash_hmac('sha256', $value, $key);
    }
}
