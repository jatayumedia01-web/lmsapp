<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

final class DashboardController
{
    public function index(Request $req): never
    {
        $stats = [
            'users'         => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE role = ?', ['STUDENT']),
            'courses'       => (int) Database::scalar('SELECT COUNT(*) FROM courses'),
            'lessons'       => (int) Database::scalar('SELECT COUNT(*) FROM lessons'),
            'enrollments'   => (int) Database::scalar('SELECT COUNT(*) FROM enrollments'),
            'subscriptions' => (int) Database::scalar(
                'SELECT COUNT(*) FROM subscriptions WHERE status IN (?, ?)',
                ['ACTIVE', 'TRIALING'],
            ),
            'questions'     => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions'),
        ];

        $recentLearners = Database::all(
            'SELECT id, full_name, email, joined_at FROM users
             WHERE role = ? ORDER BY joined_at DESC LIMIT 8',
            ['STUDENT'],
        );

        $topCourses = Database::all(
            'SELECT c.id, c.title, c.rating, c.rating_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollments
             FROM courses c WHERE c.is_published = 1
             ORDER BY c.rating DESC, c.rating_count DESC LIMIT 5',
        );

        $feedbackStats = self::getFeedbackStats();
        $recentFeedback = self::getRecentFeedback();
        $pendingQuestions = (int) Database::scalar(
            "SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = 'PENDING'"
        );

        Response::html(View::render('admin/dashboard', [
            'stats'            => $stats,
            'recentLearners'   => $recentLearners,
            'topCourses'       => $topCourses,
            'feedbackStats'    => $feedbackStats,
            'recentFeedback'   => $recentFeedback,
            'pendingQuestions' => $pendingQuestions,
            'me'               => $req->params['user'],
            'page'             => 'dashboard',
        ]));
    }

    private static function getFeedbackStats(): array
    {
        try {
            $total   = (int) Database::scalar('SELECT COUNT(*) FROM lesson_feedback WHERE helpful IS NOT NULL');
            $helpful = (int) Database::scalar('SELECT COUNT(*) FROM lesson_feedback WHERE helpful = 1');
            return [
                'total'        => $total,
                'helpful'      => $helpful,
                'not_helpful'  => $total - $helpful,
                'helpful_pct'  => $total > 0 ? round($helpful / $total * 100) : 0,
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'helpful' => 0, 'not_helpful' => 0, 'helpful_pct' => 0];
        }
    }

    private static function getRecentFeedback(): array
    {
        try {
            return Database::all(
                'SELECT f.helpful, f.comment, f.updated_at,
                        u.full_name, l.title AS lesson_title
                 FROM lesson_feedback f
                 JOIN users u ON u.id = f.user_id
                 JOIN lessons l ON l.id = f.lesson_id
                 WHERE f.comment IS NOT NULL AND f.comment != ""
                 ORDER BY f.updated_at DESC LIMIT 6',
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function liveJson(Request $req): never
    {
        try {
            $stats = [
                'users'         => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE role = ?', ['STUDENT']),
                'courses'       => (int) Database::scalar('SELECT COUNT(*) FROM courses'),
                'lessons'       => (int) Database::scalar('SELECT COUNT(*) FROM lessons'),
                'enrollments'   => (int) Database::scalar('SELECT COUNT(*) FROM enrollments'),
                'subscriptions' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM subscriptions WHERE status IN (?, ?)',
                    ['ACTIVE', 'TRIALING'],
                ),
                'questions'     => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions'),
            ];

            $recentLearners = Database::all(
                'SELECT id, full_name, email, joined_at FROM users
                 WHERE role = ? ORDER BY joined_at DESC LIMIT 8',
                ['STUDENT'],
            );

            try {
                $onlineCount = (int) Database::scalar(
                    'SELECT COUNT(DISTINCT user_id) FROM user_sessions
                     WHERE ended_at IS NULL AND started_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
                );
            } catch (\Throwable $e) {
                $onlineCount = 0;
            }

            $feedbackStats = self::getFeedbackStats();
            $pendingQuestions = (int) Database::scalar(
                "SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = 'PENDING'"
            );

            Response::json([
                'ok'              => true,
                'stats'           => $stats,
                'recentLearners'  => $recentLearners,
                'onlineNow'       => $onlineCount,
                'feedbackStats'   => $feedbackStats,
                'pendingQuestions'=> $pendingQuestions,
                'updatedAt'       => date('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function wipeDemo(Request $req): never
    {
        Database::exec('SET FOREIGN_KEY_CHECKS = 0');
        Database::exec('DELETE FROM courses');
        Database::exec('SET FOREIGN_KEY_CHECKS = 1');
        Response::redirect('/admin/dashboard');
    }
}
