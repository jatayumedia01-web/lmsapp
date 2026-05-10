<?php
/**
 * Mobile API routes — consumed by the Android app.
 * All endpoints are versioned under /api/v1 so we can ship breaking changes
 * later behind /api/v2 without leaving older app installs broken.
 *
 * @var \Devithor\Router $router  injected by index.php
 */

use Devithor\Auth;
use Devithor\Controllers\Api\AuthController;
use Devithor\Controllers\Api\CourseController;
use Devithor\Controllers\Api\LessonController;
use Devithor\Controllers\Api\TrackingController;

// ---- Public (no auth) ---------------------------------------------------
$router->get('/api/v1/health', fn () => \Devithor\Response::json(['status' => 'ok', 'time' => date('c')]));
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);

// ---- Authenticated ------------------------------------------------------
$router->group('/api/v1', [Auth::requireUser()], function ($r) {
    $r->get('/auth/me',                    [AuthController::class, 'me']);
    $r->post('/auth/logout',               [AuthController::class, 'logout']);

    $r->get('/courses',                    [CourseController::class, 'index']);
    $r->get('/courses/{id}',               [CourseController::class, 'show']);
    $r->get('/courses/{id}/lessons',       [LessonController::class, 'forCourse']);
    $r->get('/lessons/{id}',               [LessonController::class, 'show']);

    // Behavior, device, session tracking. Apps batch events for offline support.
    $r->post('/track/event',               [TrackingController::class, 'singleEvent']);
    $r->post('/track/batch',               [TrackingController::class, 'batchEvents']);
    $r->post('/track/session/start',       [TrackingController::class, 'sessionStart']);
    $r->post('/track/session/end',         [TrackingController::class, 'sessionEnd']);
});
