<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;
use Devithor\View;

/**
 * Admin CRUD for "Classes" — the top-level grouping above subjects/courses.
 * A class typically maps to an academic year or exam track ("Class 10",
 * "NEET 2026"), and contains many subjects.
 */
final class ClassController
{
    public function index(Request $req): never
    {
        $rows = Database::all(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM courses s WHERE s.class_id = c.id) AS subjects_count,
                    (SELECT COUNT(*) FROM lessons l
                       INNER JOIN courses s ON s.id = l.course_id
                       WHERE s.class_id = c.id) AS lessons_count
             FROM classes c
             ORDER BY c.sort_order ASC, c.name ASC',
        );
        Response::html(View::render('admin/classes/index', [
            'rows'  => $rows,
            'me'    => $req->params['user'],
            'page'  => 'classes',
            'flash' => $this->popFlash(),
        ]));
    }

    public function showCreate(Request $req): never
    {
        Response::html(View::render('admin/classes/edit', [
            'class'  => $this->blank(),
            'mode'   => 'create',
            'errors' => [],
            'me'     => $req->params['user'],
            'page'   => 'classes',
        ]));
    }

    public function create(Request $req): never
    {
        $data = $this->assemble($req);
        $errors = $this->validate($data);
        if ($errors) {
            Response::html(View::render('admin/classes/edit', [
                'class' => $data, 'mode' => 'create', 'errors' => $errors,
                'me' => $req->params['user'], 'page' => 'classes',
            ]));
        }
        $id = $data['id'] !== '' ? $data['id'] : ('cls_' . bin2hex(random_bytes(4)));
        $slug = $this->ensureUniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['name']);
        Database::exec(
            'INSERT INTO classes
                (id, name, slug, description, level, cover_image_url, cover_color_hex,
                 sort_order, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $data['name'], $slug, $data['description'], $data['level'],
                $data['cover_image_url'] ?: null, $data['cover_color_hex'],
                (int) $data['sort_order'], (int) $data['is_published'],
            ],
        );
        $this->setFlash('Class created.', 'success');
        Response::redirect('/admin/classes');
    }

    public function showEdit(Request $req): never
    {
        $row = Database::one('SELECT * FROM classes WHERE id = ?', [$req->params['id']]);
        if (!$row) Response::notFound();
        Response::html(View::render('admin/classes/edit', [
            'class' => $row, 'mode' => 'edit', 'errors' => [],
            'me' => $req->params['user'], 'page' => 'classes',
        ]));
    }

    public function update(Request $req): never
    {
        $existing = Database::one('SELECT * FROM classes WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();
        $data = $this->assemble($req);
        $data['id'] = $req->params['id'];
        $errors = $this->validate($data, $existing['slug']);
        if ($errors) {
            Response::html(View::render('admin/classes/edit', [
                'class' => $data, 'mode' => 'edit', 'errors' => $errors,
                'me' => $req->params['user'], 'page' => 'classes',
            ]));
        }
        $slug = $existing['slug'] === $data['slug']
            ? $existing['slug']
            : $this->ensureUniqueSlug($data['slug'] !== '' ? $data['slug'] : $data['name'], (string) $existing['id']);
        Database::exec(
            'UPDATE classes SET
                name = ?, slug = ?, description = ?, level = ?,
                cover_image_url = ?, cover_color_hex = ?,
                sort_order = ?, is_published = ?
             WHERE id = ?',
            [
                $data['name'], $slug, $data['description'], $data['level'],
                $data['cover_image_url'] ?: null, $data['cover_color_hex'],
                (int) $data['sort_order'], (int) $data['is_published'],
                $existing['id'],
            ],
        );
        $this->setFlash('Class updated.', 'success');
        Response::redirect('/admin/classes');
    }

    public function delete(Request $req): never
    {
        Database::exec('DELETE FROM classes WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Class deleted (subjects detached).', 'success');
        Response::redirect('/admin/classes');
    }

    /** Show the subjects/courses inside this class. */
    public function subjects(Request $req): never
    {
        $class = Database::one('SELECT * FROM classes WHERE id = ?', [$req->params['id']]);
        if (!$class) Response::notFound();
        $subjects = Database::all(
            'SELECT * FROM courses WHERE class_id = ? ORDER BY title ASC',
            [$class['id']],
        );
        Response::html(View::render('admin/classes/subjects', [
            'class'    => $class,
            'subjects' => $subjects,
            'me'       => $req->params['user'],
            'page'     => 'classes',
            'flash'    => $this->popFlash(),
        ]));
    }

    // ---- helpers --------------------------------------------------------

    private function blank(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '',
            'description' => '', 'level' => '',
            'cover_image_url' => '', 'cover_color_hex' => '#7C5CFF',
            'sort_order' => 0, 'is_published' => 1,
        ];
    }

    private function assemble(Request $req): array
    {
        return [
            'id'              => trim((string) $req->input('id', '')),
            'name'            => trim((string) $req->input('name', '')),
            'slug'            => $this->slugify(trim((string) $req->input('slug', $req->input('name', '')))),
            'description'     => trim((string) $req->input('description', '')),
            'level'           => trim((string) $req->input('level', '')),
            'cover_image_url' => trim((string) $req->input('cover_image_url', '')),
            'cover_color_hex' => trim((string) $req->input('cover_color_hex', '#7C5CFF')),
            'sort_order'      => (int) $req->input('sort_order', 0),
            'is_published'    => $req->input('is_published') ? 1 : 0,
        ];
    }

    private function validate(array $data, ?string $existingSlug = null): array
    {
        return Validator::check($data, [
            'name'        => ['required', 'min:2', 'max:190'],
            'description' => ['required', 'min:5'],
        ]);
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('~[^a-z0-9]+~', '-', $text);
        return trim((string) $text, '-');
    }

    private function ensureUniqueSlug(string $base, ?string $excludeId = null): string
    {
        $base = $this->slugify($base) ?: 'class';
        $slug = $base;
        $i = 2;
        while (true) {
            $clash = $excludeId === null
                ? Database::scalar('SELECT id FROM classes WHERE slug = ?', [$slug])
                : Database::scalar('SELECT id FROM classes WHERE slug = ? AND id <> ?', [$slug, $excludeId]);
            if (!$clash) return $slug;
            $slug = $base . '-' . $i;
            $i++;
        }
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
