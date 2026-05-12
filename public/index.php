<?php
/**
 * Front controller. Every HTTP request hits this file (via .htaccess rewrite),
 * the router matches it to a controller, and the controller writes its
 * response. Nothing else is reachable from the web — keep all PHP logic in
 * `../src/` and `../routes/`.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Devithor\Router;
use Devithor\Request;
use Devithor\Response;

try {
    $router = new Router();

    // Wire in route definitions. Adding a new feature = a new entry in one of
    // these files; never edit index.php for a new endpoint.
    require __DIR__ . '/../routes/api.php';
    require __DIR__ . '/../routes/admin.php';
    require __DIR__ . '/../routes/exams.php';

    $router->dispatch(Request::fromGlobals());
} catch (Throwable $e) {
    // Last-resort error wall — never leak a stack trace to the client.
    error_log('[devithor] uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ((getenv('APP_DEBUG') ?: 'false') === 'true') {
        Response::json([
            'error' => 'internal_server_error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ], 500);
    } else {
        Response::json(['error' => 'internal_server_error'], 500);
    }
}
