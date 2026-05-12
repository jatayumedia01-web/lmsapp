<?php
use Devithor\Auth;
use Devithor\Controllers\Admin\ExamController as AdminExam;
use Devithor\Controllers\Api\ExamApiController as ApiExam;

$adminMiddleware = [Auth::requireAdmin()];

// ── Admin exam routes ──────────────────────────────────────────────────────
$router->get('/admin/exams',                        [AdminExam::class, 'index'],       $adminMiddleware);
$router->get('/admin/exams/new',                    [AdminExam::class, 'showCreate'],  $adminMiddleware);
$router->post('/admin/exams',                       [AdminExam::class, 'create'],      $adminMiddleware);
$router->get('/admin/exams/{id}/questions',         [AdminExam::class, 'questions'],   $adminMiddleware);
$router->post('/admin/exams/{id}/questions',        [AdminExam::class, 'questionCreate'], $adminMiddleware);
$router->post('/admin/exams/questions/{id}/delete', [AdminExam::class, 'questionDelete'], $adminMiddleware);
$router->get('/admin/exams/{id}/results',           [AdminExam::class, 'results'],     $adminMiddleware);
$router->post('/admin/exams/{id}/publish',          [AdminExam::class, 'publish'],     $adminMiddleware);
$router->get('/admin/exams/{id}',                   [AdminExam::class, 'showEdit'],    $adminMiddleware);
$router->post('/admin/exams/{id}',                  [AdminExam::class, 'update'],      $adminMiddleware);
$router->post('/admin/exams/{id}/delete',           [AdminExam::class, 'delete'],      $adminMiddleware);

// ── API exam routes ────────────────────────────────────────────────────────
$apiMiddleware = [Auth::requireUser()];

$router->get('/api/v1/exams',                              [ApiExam::class, 'list'],       $apiMiddleware);
$router->get('/api/v1/exams/{id}',                         [ApiExam::class, 'show'],       $apiMiddleware);
$router->post('/api/v1/exams/{id}/start',                  [ApiExam::class, 'start'],      $apiMiddleware);
$router->post('/api/v1/exams/attempts/{id}/answer',        [ApiExam::class, 'saveAnswer'], $apiMiddleware);
$router->post('/api/v1/exams/attempts/{id}/submit',        [ApiExam::class, 'submit'],     $apiMiddleware);
$router->get('/api/v1/exams/attempts/{id}/result',         [ApiExam::class, 'result'],     $apiMiddleware);
