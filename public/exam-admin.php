<?php
require __DIR__ . '/../src/bootstrap.php';

use Devithor\Router;
use Devithor\Request;
use Devithor\Response;
use Devithor\Auth;
use Devithor\Controllers\Admin\ExamController as AdminExam;

try {
    $router = new Router();
    $m = [Auth::requireAdmin()];
    $router->get('/admin/exams',                        [AdminExam::class, 'index'],          $m);
    $router->get('/admin/exams/new',                    [AdminExam::class, 'showCreate'],     $m);
    $router->post('/admin/exams',                       [AdminExam::class, 'create'],         $m);
    $router->get('/admin/exams/{id}/questions',         [AdminExam::class, 'questions'],      $m);
    $router->post('/admin/exams/{id}/questions',        [AdminExam::class, 'questionCreate'], $m);
    $router->post('/admin/exams/questions/{id}/delete', [AdminExam::class, 'questionDelete'], $m);
    $router->get('/admin/exams/{id}/results',           [AdminExam::class, 'results'],        $m);
    $router->post('/admin/exams/{id}/publish',          [AdminExam::class, 'publish'],        $m);
    $router->get('/admin/exams/{id}',                   [AdminExam::class, 'showEdit'],       $m);
    $router->post('/admin/exams/{id}',                  [AdminExam::class, 'update'],         $m);
    $router->post('/admin/exams/{id}/delete',           [AdminExam::class, 'delete'],         $m);
    $router->dispatch(Request::fromGlobals());
} catch (Throwable $e) {
    error_log('[exam-admin] ' . $e->getMessage());
    Response::html('<h1>Error</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>', 500);
}
