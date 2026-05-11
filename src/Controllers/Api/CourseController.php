<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * Course catalog endpoints + student enrollment.
 */
final class CourseController
{
    public function index(Request $req): never
    {
        $rows = Database::all(
            'SELECT * FROM courses WHERE is_published = 1 ORDER BY rating DESC, rating_count DESC',
        );
        Response::json(['courses' => array_map([$this, 'shape'], $rows)]);
    }

    public function show(Request $req): never
    {
        $row = Database::one('SELECT * FROM courses WHERE id = ? AND is_published = 1', [$req->params['id']]);
        if (!$row) Response::json(['error' => 'not_found'], 404);
        Response::json(['course' => $this->shape($row)]);
    }

    public function enroll(Request $req): never
    {
        $user     = $req->params['user'];
        $courseId = $req->params['id'];

        $course = Database::one(
            'SELECT id FROM courses WHERE id = ? AND is_published = 1',
            [$courseId],
        );
        if (!$course) Response::json(['error' => 'course_not_found'], 404);

        $existing = Database::one(
            'SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?',
            [$user['id'], $courseId],
        );
        if (!$existing) {
            Database::exec(
                'INSERT INTO enrollments (id, user_id, course_id, enrolled_at)
                 VALUES (?, ?, ?, NOW())',
                ['enr_' . bin2hex(random_bytes(8)), $user['id'], $courseId],
            );
            // Track enrollment event
            Database::exec(
                'INSERT INTO user_events (user_id, event_name, course_id, props_json, occurred_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [$user['id'], 'course_enroll', $courseId, json_encode(['course_id' => $courseId])],
            );
        }

        Response::json(['enrolled' => true, 'course_id' => $courseId]);
    }

    public function unenroll(Request $req): never
    {
        $user = $req->params['user'];
        Database::exec(
            'DELETE FROM enrollments WHERE user_id = ? AND course_id = ?',
            [$user['id'], $req->params['id']],
        );
        Response::json(['enrolled' => false]);
    }

    public function myCourses(Request $req): never
    {
        $user = $req->params['user'];
        $rows = Database::all(
            'SELECT c.*,
                    e.enrolled_at,
                    e.last_accessed_lesson_id,
                    (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS total_lessons,
                    (SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = c.id AND completed = 1) AS completed_lessons
             FROM enrollments e
             INNER JOIN courses c ON c.id = e.course_id
             WHERE e.user_id = ?
             ORDER BY e.enrolled_at DESC',
            [$user['id'], $user['id']],
        );

        Response::json([
            'courses' => array_map(function (array $r) {
                $total     = (int) ($r['total_lessons'] ?? 0);
                $completed = (int) ($r['completed_lessons'] ?? 0);
                return array_merge($this->shape($r), [
                    'enrolled_at'              => $r['enrolled_at'],
                    'last_accessed_lesson_id'  => $r['last_accessed_lesson_id'],
                    'completed_lessons'        => $completed,
                    'progress_pct'             => $total > 0 ? (int) round($completed / $total * 100) : 0,
                ]);
            }, $rows),
        ]);
    }

    private function shape(array $r): array
    {
        return [
            'id'               => $r['id'],
            'title'            => $r['title'],
            'subtitle'         => $r['subtitle'],
            'description'      => $r['description'],
            'instructor_name'  => $r['instructor_name'],
            'cover_color_hex'  => $r['cover_color_hex'],
            'cover_image_url'  => $r['cover_image_url'],
            'category'         => $r['category'],
            'difficulty'       => $r['difficulty'],
            'total_lessons'    => (int) $r['total_lessons'],
            'duration_minutes' => (int) $r['duration_minutes'],
            'rating'           => (float) $r['rating'],
            'rating_count'     => (int) $r['rating_count'],
            'is_premium'       => (bool) $r['is_premium'],
        ];
    }
}
