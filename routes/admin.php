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
use Devithor\Controllers\Admin\UserController as AdminUser;
use Devithor\Controllers\Admin\SubscriptionController as AdminBilling;
use Devithor\Controllers\Admin\QAController as AdminQA;
use Devithor\Controllers\Admin\SettingsController as AdminSettings;

// ---- Login (public) -----------------------------------------------------
$router->get('/admin',                 fn () => \Devithor\Response::redirect('/admin/login'));
$router->get('/admin/login',           [AdminAuth::class, 'showLogin']);
$router->post('/admin/login',          [AdminAuth::class, 'login']);
$router->post('/admin/logout',         [AdminAuth::class, 'logout']);

// ---- Authenticated admin area ------------------------------------------
$router->group('/admin', [Auth::requireAdmin()], function ($r) {
    $r->get('/dashboard',              [DashboardController::class, 'index']);

    // ---- Courses + lessons -------------------------------------------
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

    // ---- Users -------------------------------------------------------
    $r->get('/users',                            [AdminUser::class, 'index']);
    $r->get('/users/export.csv',                 [AdminUser::class, 'exportCsv']);
    $r->get('/users/{id}',                       [AdminUser::class, 'show']);
    $r->post('/users/{id}/ban',                  [AdminUser::class, 'ban']);
    $r->post('/users/{id}/unban',                [AdminUser::class, 'unban']);
    $r->post('/users/{id}/role',                 [AdminUser::class, 'changeRole']);
    $r->post('/users/{id}/delete',               [AdminUser::class, 'delete']);

    // ---- Billing -----------------------------------------------------
    $r->get('/billing',                          [AdminBilling::class, 'overview']);
    $r->get('/billing/subscriptions',            [AdminBilling::class, 'subscriptionsIndex']);
    $r->post('/billing/subscriptions/{userId}/cancel', [AdminBilling::class, 'cancelSubscription']);
    $r->post('/billing/invoices/{id}/refund',    [AdminBilling::class, 'refundInvoice']);

    $r->get('/billing/plans',                    [AdminBilling::class, 'plansIndex']);
    $r->get('/billing/plans/new',                [AdminBilling::class, 'planNew']);
    $r->post('/billing/plans',                   [AdminBilling::class, 'planCreate']);
    $r->get('/billing/plans/{id}',               [AdminBilling::class, 'planEdit']);
    $r->post('/billing/plans/{id}',              [AdminBilling::class, 'planUpdate']);
    $r->post('/billing/plans/{id}/delete',       [AdminBilling::class, 'planDelete']);

    $r->get('/billing/coupons',                  [AdminBilling::class, 'couponsIndex']);
    $r->get('/billing/coupons/new',              [AdminBilling::class, 'couponNew']);
    $r->post('/billing/coupons',                 [AdminBilling::class, 'couponCreate']);
    $r->get('/billing/coupons/{id}',             [AdminBilling::class, 'couponEdit']);
    $r->post('/billing/coupons/{id}',            [AdminBilling::class, 'couponUpdate']);
    $r->post('/billing/coupons/{id}/delete',     [AdminBilling::class, 'couponDelete']);

    // ---- Q&A moderation ---------------------------------------------
    $r->get('/qa',                               [AdminQA::class, 'index']);
    $r->post('/qa/bulk',                         [AdminQA::class, 'bulkSetStatus']);
    $r->get('/qa/{id}',                          [AdminQA::class, 'show']);
    $r->post('/qa/{id}/status',                  [AdminQA::class, 'setStatus']);
    $r->post('/qa/{id}/pin',                     [AdminQA::class, 'togglePinned']);
    $r->post('/qa/{id}/resolve',                 [AdminQA::class, 'toggleResolved']);
    $r->post('/qa/{id}/delete',                  [AdminQA::class, 'delete']);
    $r->post('/qa/answers/{id}/delete',          [AdminQA::class, 'deleteAnswer']);

    // ---- Settings ----------------------------------------------------
    $r->get('/settings',                         [AdminSettings::class, 'index']);
    $r->post('/settings',                        [AdminSettings::class, 'update']);
});
