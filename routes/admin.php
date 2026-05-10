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
use Devithor\Controllers\Admin\AnalyticsController as AdminAnalytics;
use Devithor\Controllers\Admin\ClassController as AdminClass;
use Devithor\Controllers\Admin\FaqController    as AdminFaq;
use Devithor\Controllers\Admin\VideoUploadController as AdminUpload;
use Devithor\Controllers\Admin\QuizController        as AdminQuiz;
use Devithor\Controllers\Admin\CertificateController as AdminCert;
use Devithor\Controllers\Admin\NotificationController as AdminNotif;
use Devithor\Controllers\PublicController            as PublicCtl;

// ---- Public certificate verify ---------------------------------------
$router->get('/verify/{number}',       [PublicCtl::class, 'verifyCertificate']);

// ---- Login (public) -----------------------------------------------------
$router->get('/admin',                 fn () => \Devithor\Response::redirect('/admin/login'));
$router->get('/admin/login',           [AdminAuth::class, 'showLogin']);
$router->post('/admin/login',          [AdminAuth::class, 'login']);
$router->post('/admin/logout',         [AdminAuth::class, 'logout']);

// ---- Authenticated admin area ------------------------------------------
$router->group('/admin', [Auth::requireAdmin()], function ($r) {
    $r->get('/dashboard',              [DashboardController::class, 'index']);

    // ---- Classes -----------------------------------------------------
    $r->get('/classes',                          [AdminClass::class, 'index']);
    $r->get('/classes/new',                      [AdminClass::class, 'showCreate']);
    $r->post('/classes',                         [AdminClass::class, 'create']);
    $r->get('/classes/{id}/subjects',            [AdminClass::class, 'subjects']);
    $r->get('/classes/{id}',                     [AdminClass::class, 'showEdit']);
    $r->post('/classes/{id}',                    [AdminClass::class, 'update']);
    $r->post('/classes/{id}/delete',             [AdminClass::class, 'delete']);

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
    // /api/* MUST be registered before /lessons/{id} or {id} captures it.
    $r->get('/api/video-detect',                 [AdminLesson::class, 'videoDetect']);
    $r->post('/api/upload-video',                [AdminUpload::class, 'upload']);
    $r->get('/lessons/{id}/video',               [AdminLesson::class, 'videoPage']);
    $r->post('/lessons/{lessonId}/faqs',                  [AdminFaq::class, 'create']);
    $r->post('/lessons/{lessonId}/faqs/{id}',             [AdminFaq::class, 'update']);
    $r->post('/lessons/{lessonId}/faqs/{id}/delete',      [AdminFaq::class, 'delete']);
    $r->post('/lessons/{lessonId}/faqs/{id}/reorder',     [AdminFaq::class, 'reorder']);
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

    // ---- Quizzes -----------------------------------------------------
    $r->get('/quizzes',                            [AdminQuiz::class, 'index']);
    $r->get('/quizzes/new',                        [AdminQuiz::class, 'showCreate']);
    $r->post('/quizzes',                           [AdminQuiz::class, 'create']);
    $r->post('/quizzes/questions/{id}/delete',     [AdminQuiz::class, 'questionDelete']);
    $r->post('/quizzes/questions/{id}/reorder',    [AdminQuiz::class, 'questionReorder']);
    $r->get('/quizzes/{id}/questions',             [AdminQuiz::class, 'questions']);
    $r->post('/quizzes/{id}/questions',            [AdminQuiz::class, 'questionCreate']);
    $r->get('/quizzes/{id}/attempts',              [AdminQuiz::class, 'attempts']);
    $r->get('/quizzes/{id}',                       [AdminQuiz::class, 'showEdit']);
    $r->post('/quizzes/{id}',                      [AdminQuiz::class, 'update']);
    $r->post('/quizzes/{id}/delete',               [AdminQuiz::class, 'delete']);

    // ---- Certificates ------------------------------------------------
    $r->get('/certificates',                       [AdminCert::class, 'index']);
    $r->get('/certificates/templates/new',         [AdminCert::class, 'templateNew']);
    $r->post('/certificates/templates',            [AdminCert::class, 'templateCreate']);
    $r->get('/certificates/templates/{id}/preview',[AdminCert::class, 'templatePreview']);
    $r->get('/certificates/templates/{id}',        [AdminCert::class, 'templateEdit']);
    $r->post('/certificates/templates/{id}',       [AdminCert::class, 'templateUpdate']);
    $r->post('/certificates/templates/{id}/delete',[AdminCert::class, 'templateDelete']);
    $r->post('/certificates/{id}/revoke',          [AdminCert::class, 'revoke']);

    // ---- Notifications -----------------------------------------------
    $r->get('/notifications',                      [AdminNotif::class, 'index']);
    $r->post('/notifications',                     [AdminNotif::class, 'send']);

    // ---- Analytics ---------------------------------------------------
    $r->get('/analytics',                        [AdminAnalytics::class, 'overview']);
    $r->get('/analytics/live.json',              [AdminAnalytics::class, 'liveJson']);
    $r->get('/analytics/engagement',             [AdminAnalytics::class, 'engagement']);
    $r->get('/analytics/cohorts',                [AdminAnalytics::class, 'cohorts']);
    $r->get('/analytics/videos',                 [AdminAnalytics::class, 'videos']);
    $r->get('/analytics/geography',              [AdminAnalytics::class, 'geography']);
    $r->get('/analytics/devices',                [AdminAnalytics::class, 'devices']);
    $r->get('/analytics/events',                 [AdminAnalytics::class, 'events']);
    $r->get('/analytics/logins',                 [AdminAnalytics::class, 'logins']);
    $r->get('/users/{id}/activity',              [AdminAnalytics::class, 'userTimeline']);
});
