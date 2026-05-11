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
use Devithor\Controllers\Api\CertificateController as ApiCert;
use Devithor\Controllers\Api\CourseController;
use Devithor\Controllers\Api\LessonController;
use Devithor\Controllers\Api\ProfileController;
use Devithor\Controllers\Api\TrackingController;
use Devithor\Controllers\Api\QuizController as ApiQuiz;
use Devithor\Controllers\Api\NoteController as ApiNote;
use Devithor\Controllers\Api\NotificationApiController as ApiNotif;

// ── Health ────────────────────────────────────────────────────────────────────
$router->get('/api/v1/health', fn () => \Devithor\Response::json(['status' => 'ok', 'time' => date('c')]));

// ── Public: OTP-based auth ────────────────────────────────────────────────────
$router->post('/api/v1/auth/request-otp', [AuthController::class, 'requestOtp']);
$router->post('/api/v1/auth/verify-otp',  [AuthController::class, 'verifyOtp']);
$router->post('/api/v1/auth/login',       [AuthController::class, 'login']); // backward-compat shim

// ── Authenticated ─────────────────────────────────────────────────────────────
$router->group('/api/v1', [Auth::requireUser()], function ($r) {

    // ── Auth ─────────────────────────────────────────────────────────────────
    $r->get('/auth/me',      [AuthController::class, 'me']);
    $r->post('/auth/logout', [AuthController::class, 'logout']);

    // ── Profile & onboarding ─────────────────────────────────────────────────
    $r->post('/profile',                     [ProfileController::class, 'update']);
    $r->post('/profile/complete-onboarding', [ProfileController::class, 'completeOnboarding']);
    $r->post('/profile/avatar',              [ProfileController::class, 'uploadAvatar']);

    // ── Courses & enrollment ──────────────────────────────────────────────────
    $r->get('/courses',                        [CourseController::class, 'index']);
    $r->get('/my-courses',                     [CourseController::class, 'myCourses']);
    $r->get('/courses/{id}',                   [CourseController::class, 'show']);
    $r->post('/courses/{id}/enroll',           [CourseController::class, 'enroll']);
    $r->post('/courses/{id}/unenroll',         [CourseController::class, 'unenroll']);
    $r->get('/courses/{id}/lessons',           [LessonController::class, 'forCourse']);

    // ── Lessons & playback ────────────────────────────────────────────────────
    $r->get('/lessons/{id}/playback',          [LessonController::class, 'playback']);
    $r->post('/lessons/{id}/track-playback',   [LessonController::class, 'trackPlayback']);
    $r->get('/lessons/{id}',                   [LessonController::class, 'show']);

    // ── Tracking ──────────────────────────────────────────────────────────────
    $r->post('/track/event',         [TrackingController::class, 'singleEvent']);
    $r->post('/track/batch',         [TrackingController::class, 'batchEvents']);
    $r->post('/track/session/start', [TrackingController::class, 'sessionStart']);
    $r->post('/track/session/end',   [TrackingController::class, 'sessionEnd']);

    // ── Quizzes ───────────────────────────────────────────────────────────────
    $r->get('/quizzes/{id}',                   [ApiQuiz::class, 'show']);
    $r->post('/quizzes/{id}/attempts',         [ApiQuiz::class, 'startAttempt']);
    $r->post('/quizzes/attempts/{id}/answer',  [ApiQuiz::class, 'answer']);
    $r->post('/quizzes/attempts/{id}/submit',  [ApiQuiz::class, 'submit']);

    // ── Notes ─────────────────────────────────────────────────────────────────
    $r->get('/lessons/{id}/notes',   [ApiNote::class, 'forLesson']);
    $r->post('/notes',               [ApiNote::class, 'create']);
    $r->post('/notes/{id}',          [ApiNote::class, 'update']);
    $r->post('/notes/{id}/delete',   [ApiNote::class, 'delete']);

    // ── Notifications inbox ───────────────────────────────────────────────────
    $r->get('/notifications',                [ApiNotif::class, 'inbox']);
    $r->post('/notifications/{id}/read',     [ApiNotif::class, 'markRead']);
    $r->post('/notifications/read-all',      [ApiNotif::class, 'markAllRead']);
    $r->post('/notifications/{id}/delete',   [ApiNotif::class, 'delete']);

    // ── Certificates ──────────────────────────────────────────────────────────
    $r->get('/my-certificates',              [ApiCert::class, 'myList']);
});
