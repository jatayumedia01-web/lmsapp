<?php
/**
 * Admin web UI routes — server-rendered HTML behind a session login.
 *
 * @var \Devithor\Router $router  injected by index.php
 */

use Devithor\Auth;
use Devithor\Controllers\Admin\AuthController as AdminAuth;
use Devithor\Controllers\Admin\DashboardController;
use Devithor\Controllers\Admin\CourseController as AdminCourse;
use Devithor\Controllers\Admin\LessonController as AdminLesson;

// ---- Login (public) -----------------------------------------------------
$router->get('/admin',                 fn () => \Devithor\Response::redirect('/admin/login'));
$router->get('/admin/login',           [AdminAuth::class, 'showLogin']);
$router->post('/admin/login',          [AdminAuth::class, 'login']);
$router->post('/admin/logout',         [AdminAuth::class, 'logout']);

// ---- Authenticated admin area ------------------------------------------
$router->group('/admin', [Auth::requireAdmin()], function ($r) {
    $r->get('/dashboard',              [DashboardController::class, 'index']);

    $r->get('/courses',                [AdminCourse::class, 'index']);
    $r->get('/courses/new',            [AdminCourse::class, 'showCreate']);
    $r->post('/courses',               [AdminCourse::class, 'create']);
    $r->get('/courses/{id}',           [AdminCourse::class, 'showEdit']);
    $r->post('/courses/{id}',          [AdminCourse::class, 'update']);
    $r->post('/courses/{id}/delete',   [AdminCourse::class, 'delete']);

    $r->get('/courses/{courseId}/lessons',       [AdminLesson::class, 'index']);
    $r->get('/courses/{courseId}/lessons/new',   [AdminLesson::class, 'showCreate']);
    $r->post('/courses/{courseId}/lessons',      [AdminLesson::class, 'create']);
    $r->get('/lessons/{id}',                     [AdminLesson::class, 'showEdit']);
    $r->post('/lessons/{id}',                    [AdminLesson::class, 'update']);
    $r->post('/lessons/{id}/delete',             [AdminLesson::class, 'delete']);
});
