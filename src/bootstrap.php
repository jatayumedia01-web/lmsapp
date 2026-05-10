<?php
/**
 * Boot sequence — runs once per request before any route logic.
 *  1. Registers a PSR-4-style autoloader for `Devithor\` namespace.
 *  2. Loads .env into getenv() / $_ENV.
 *  3. Initialises the PDO database singleton lazily.
 *  4. Starts the PHP session (used by the admin web UI).
 */

declare(strict_types=1);

// ---- Autoloader ---------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'Devithor\\';
    $base   = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return; // not ours
    }
    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---- .env loader (no Composer, no library) ------------------------------
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes if any.
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// ---- Sane defaults ------------------------------------------------------
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

// ---- Sessions for the admin UI ------------------------------------------
// Sessions are only used by /admin — the API path explicitly skips this so
// mobile clients never get a Set-Cookie header back.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        ($_SERVER['HTTPS'] ?? '') === 'on' ||
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('devithor_admin');
    session_start();
}
