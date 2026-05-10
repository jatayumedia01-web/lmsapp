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

        // Most recent learners — useful "did our last campaign work" sanity check.
        $recentLearners = Database::all(
            'SELECT id, full_name, email, joined_at FROM users
             WHERE role = ? ORDER BY joined_at DESC LIMIT 8',
            ['STUDENT'],
        );

        // Top-rated courses with their enrollment counts.
        $topCourses = Database::all(
            'SELECT c.id, c.title, c.rating, c.rating_count,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrollments
             FROM courses c
             WHERE c.is_published = 1
             ORDER BY c.rating DESC, c.rating_count DESC
             LIMIT 5',
        );

        Response::html(View::render('admin/dashboard', [
            'stats'          => $stats,
            'recentLearners' => $recentLearners,
            'topCourses'     => $topCourses,
            'me'             => $req->params['user'],
            'page'           => 'dashboard',
        ]));
    }
}
