<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;
use Devithor\View;

final class LessonController
{
    public function index(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();
        $lessons = Database::all(
            'SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC',
            [$course['id']],
        );
        Response::html(View::render('admin/lessons/index', [
            'course'  => $course,
            'lessons' => $lessons,
            'me'      => $req->params['user'],
            'page'    => 'courses',
            'flash'   => $this->popFlash(),
        ]));
    }

    public function showCreate(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();
        $nextOrder = (int) Database::scalar(
            'SELECT COALESCE(MAX(order_index), -1) + 1 FROM lessons WHERE course_id = ?',
            [$course['id']],
        );
        Response::html(View::render('admin/lessons/edit', [
            'course' => $course,
            'lesson' => $this->blankLesson($nextOrder),
            'mode'   => 'create',
            'me'     => $req->params['user'],
            'page'   => 'courses',
            'errors' => [],
        ]));
    }

    public function create(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();

        $data = $this->assembleFromRequest($req, $course['id']);
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            Response::html(View::render('admin/lessons/edit', [
                'course' => $course,
                'lesson' => $data,
                'mode'   => 'create',
                'me'     => $req->params['user'],
                'page'   => 'courses',
                'errors' => $errors,
            ]));
        }

        $id = $data['id'] !== '' ? $data['id'] : ($course['id'] . '_l' . ($data['order_index'] + 1));
        Database::exec(
            'INSERT INTO lessons
             (id, course_id, title, order_index, duration_seconds, video_url, description, is_free_preview)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $course['id'], $data['title'],
                (int) $data['order_index'], (int) $data['duration_seconds'],
                $data['video_url'], $data['description'],
                (int) $data['is_free_preview'],
            ],
        );
        // Recompute the course's `total_lessons` so the catalog stays accurate.
        $this->recountCourseLessons($course['id']);
        $this->setFlash('Lesson added.', 'success');
        Response::redirect('/admin/courses/' . $course['id'] . '/lessons');
    }

    public function showEdit(Request $req): never
    {
        $lesson = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$lesson) Response::notFound();
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$lesson['course_id']]);
        Response::html(View::render('admin/lessons/edit', [
            'course' => $course,
            'lesson' => $lesson,
            'mode'   => 'edit',
            'me'     => $req->params['user'],
            'page'   => 'courses',
            'errors' => [],
        ]));
    }

    public function update(Request $req): never
    {
        $existing = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();

        $data = $this->assembleFromRequest($req, $existing['course_id']);
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            $course = Database::one('SELECT * FROM courses WHERE id = ?', [$existing['course_id']]);
            Response::html(View::render('admin/lessons/edit', [
                'course' => $course,
                'lesson' => array_merge($existing, $data),
                'mode'   => 'edit',
                'me'     => $req->params['user'],
                'page'   => 'courses',
                'errors' => $errors,
            ]));
        }

        Database::exec(
            'UPDATE lessons SET
                title = ?, order_index = ?, duration_seconds = ?, video_url = ?,
                description = ?, is_free_preview = ?
             WHERE id = ?',
            [
                $data['title'], (int) $data['order_index'], (int) $data['duration_seconds'],
                $data['video_url'], $data['description'],
                (int) $data['is_free_preview'],
                $existing['id'],
            ],
        );
        $this->setFlash('Lesson updated.', 'success');
        Response::redirect('/admin/courses/' . $existing['course_id'] . '/lessons');
    }

    public function delete(Request $req): never
    {
        $existing = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();
        Database::exec('DELETE FROM lessons WHERE id = ?', [$existing['id']]);
        $this->recountCourseLessons($existing['course_id']);
        $this->setFlash('Lesson deleted.', 'success');
        Response::redirect('/admin/courses/' . $existing['course_id'] . '/lessons');
    }

    // ---- helpers --------------------------------------------------------

    private function rules(): array
    {
        return [
            'title'            => ['required', 'min:2', 'max:255'],
            'description'      => ['required', 'min:5'],
            'video_url'        => ['required', 'url', 'max:500'],
            'order_index'      => ['int'],
            'duration_seconds' => ['int'],
        ];
    }

    private function blankLesson(int $order): array
    {
        return [
            'id' => '',
            'title' => '',
            'order_index' => $order,
            'duration_seconds' => 600,
            'video_url' => '',
            'description' => '',
            'is_free_preview' => $order === 0 ? 1 : 0,
        ];
    }

    private function assembleFromRequest(Request $req, string $courseId): array
    {
        return [
            'id'               => trim((string) $req->input('id', '')),
            'course_id'        => $courseId,
            'title'            => trim((string) $req->input('title', '')),
            'order_index'      => (int) $req->input('order_index', 0),
            'duration_seconds' => (int) $req->input('duration_seconds', 600),
            'video_url'        => trim((string) $req->input('video_url', '')),
            'description'      => trim((string) $req->input('description', '')),
            'is_free_preview'  => $req->input('is_free_preview') ? 1 : 0,
        ];
    }

    private function recountCourseLessons(string $courseId): void
    {
        Database::exec(
            'UPDATE courses SET
                total_lessons   = (SELECT COUNT(*) FROM lessons WHERE course_id = ?),
                duration_minutes = (SELECT COALESCE(SUM(duration_seconds), 0) DIV 60 FROM lessons WHERE course_id = ?)
             WHERE id = ?',
            [$courseId, $courseId, $courseId],
        );
    }

    private function setFlash(string $message, string $kind = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
    }

    private function popFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}
