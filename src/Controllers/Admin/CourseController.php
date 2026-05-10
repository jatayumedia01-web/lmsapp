<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;
use Devithor\View;

/**
 * CRUD for courses inside the admin dashboard. Every save flows through
 * the same `assemble + validate + persist` shape so behaviour stays
 * predictable for create + update.
 */
final class CourseController
{
    public function index(Request $req): never
    {
        $classId = (string) $req->input('class_id', '');
        $where = ''; $params = [];
        if ($classId !== '') { $where = 'WHERE class_id = ?'; $params[] = $classId; }
        $courses = Database::all(
            "SELECT c.*, cl.name AS class_name, cl.cover_color_hex AS class_color
             FROM courses c
             LEFT JOIN classes cl ON cl.id = c.class_id
             $where
             ORDER BY c.created_at DESC",
            $params,
        );
        $classes = Database::all('SELECT id, name FROM classes ORDER BY sort_order, name');
        Response::html(View::render('admin/courses/index', [
            'courses' => $courses,
            'classes' => $classes,
            'classId' => $classId,
            'me'      => $req->params['user'],
            'page'    => 'courses',
            'flash'   => $this->popFlash(),
        ]));
    }

    public function showCreate(Request $req): never
    {
        $course = $this->blankCourse();
        $course['class_id'] = (string) ($req->input('class_id') ?? '');
        Response::html(View::render('admin/courses/edit', [
            'course'  => $course,
            'mode'    => 'create',
            'me'      => $req->params['user'],
            'classes' => Database::all('SELECT id, name FROM classes ORDER BY sort_order, name'),
            'page'    => 'courses',
            'errors'  => [],
        ]));
    }

    public function create(Request $req): never
    {
        $data = $this->assembleFromRequest($req);
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            Response::html(View::render('admin/courses/edit', [
                'course'  => $data,
                'mode'    => 'create',
                'me'      => $req->params['user'],
                'classes' => Database::all('SELECT id, name FROM classes ORDER BY sort_order, name'),
                'page'    => 'courses',
                'errors'  => $errors,
            ]));
        }

        $id = $data['id'] !== '' ? $data['id'] : ('c_' . bin2hex(random_bytes(6)));
        Database::exec(
            'INSERT INTO courses
             (id, class_id, title, subtitle, description, instructor_name, cover_color_hex, cover_image_url,
              category, difficulty, total_lessons, duration_minutes, rating, rating_count,
              is_premium, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $data['class_id'] ?: null,
                $data['title'], $data['subtitle'], $data['description'], $data['instructor_name'],
                $data['cover_color_hex'], $data['cover_image_url'] ?: null,
                $data['category'], $data['difficulty'],
                (int) $data['total_lessons'], (int) $data['duration_minutes'],
                (float) $data['rating'], (int) $data['rating_count'],
                (int) $data['is_premium'], (int) $data['is_published'],
            ],
        );
        $this->setFlash('Subject created.', 'success');
        Response::redirect($data['class_id'] ? "/admin/classes/{$data['class_id']}/subjects" : '/admin/courses');
    }

    public function showEdit(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['id']]);
        if (!$course) Response::notFound();
        Response::html(View::render('admin/courses/edit', [
            'course'  => $course,
            'mode'    => 'edit',
            'me'      => $req->params['user'],
            'classes' => Database::all('SELECT id, name FROM classes ORDER BY sort_order, name'),
            'page'    => 'courses',
            'errors'  => [],
        ]));
    }

    public function update(Request $req): never
    {
        $existing = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();

        $data = $this->assembleFromRequest($req);
        $data['id'] = $req->params['id']; // ID is immutable after creation
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            Response::html(View::render('admin/courses/edit', [
                'course'  => $data,
                'mode'    => 'edit',
                'me'      => $req->params['user'],
                'classes' => Database::all('SELECT id, name FROM classes ORDER BY sort_order, name'),
                'page'    => 'courses',
                'errors'  => $errors,
            ]));
        }

        Database::exec(
            'UPDATE courses SET
                class_id = ?,
                title = ?, subtitle = ?, description = ?, instructor_name = ?,
                cover_color_hex = ?, cover_image_url = ?,
                category = ?, difficulty = ?,
                total_lessons = ?, duration_minutes = ?,
                rating = ?, rating_count = ?,
                is_premium = ?, is_published = ?
             WHERE id = ?',
            [
                $data['class_id'] ?: null,
                $data['title'], $data['subtitle'], $data['description'], $data['instructor_name'],
                $data['cover_color_hex'], $data['cover_image_url'] ?: null,
                $data['category'], $data['difficulty'],
                (int) $data['total_lessons'], (int) $data['duration_minutes'],
                (float) $data['rating'], (int) $data['rating_count'],
                (int) $data['is_premium'], (int) $data['is_published'],
                $req->params['id'],
            ],
        );
        $this->setFlash('Subject updated.', 'success');
        Response::redirect('/admin/courses/' . $req->params['id']);
    }

    public function delete(Request $req): never
    {
        Database::exec('DELETE FROM courses WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Course deleted.', 'success');
        Response::redirect('/admin/courses');
    }

    // ---- helpers --------------------------------------------------------

    private function rules(): array
    {
        return [
            'title'           => ['required', 'min:3', 'max:190'],
            'subtitle'        => ['max:255'],
            'description'     => ['required', 'min:10'],
            'instructor_name' => ['required', 'min:2', 'max:190'],
            'cover_color_hex' => ['required', 'min:4', 'max:9'],
            'category'        => ['required', 'min:2', 'max:60'],
            'difficulty'      => ['required', 'in:BEGINNER,INTERMEDIATE,ADVANCED'],
            'duration_minutes'=> ['int'],
            'rating'          => ['max:5'],
        ];
    }

    private function blankCourse(): array
    {
        return [
            'id' => '',
            'class_id' => '',
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'instructor_name' => '',
            'cover_color_hex' => '#7C5CFF',
            'cover_image_url' => '',
            'category' => '',
            'difficulty' => 'BEGINNER',
            'total_lessons' => 0,
            'duration_minutes' => 0,
            'rating' => '0.00',
            'rating_count' => 0,
            'is_premium' => 0,
            'is_published' => 1,
        ];
    }

    private function assembleFromRequest(Request $req): array
    {
        return [
            'id'              => trim((string) $req->input('id', '')),
            'class_id'        => trim((string) $req->input('class_id', '')),
            'title'           => trim((string) $req->input('title', '')),
            'subtitle'        => trim((string) $req->input('subtitle', '')),
            'description'     => trim((string) $req->input('description', '')),
            'instructor_name' => trim((string) $req->input('instructor_name', '')),
            'cover_color_hex' => trim((string) $req->input('cover_color_hex', '#7C5CFF')),
            'cover_image_url' => trim((string) $req->input('cover_image_url', '')),
            'category'        => trim((string) $req->input('category', '')),
            'difficulty'      => (string) $req->input('difficulty', 'BEGINNER'),
            'total_lessons'   => (int) $req->input('total_lessons', 0),
            'duration_minutes'=> (int) $req->input('duration_minutes', 0),
            'rating'          => (string) $req->input('rating', '0.00'),
            'rating_count'    => (int) $req->input('rating_count', 0),
            'is_premium'      => $req->input('is_premium') ? 1 : 0,
            'is_published'    => $req->input('is_published') ? 1 : 0,
        ];
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
