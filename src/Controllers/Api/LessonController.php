<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

final class LessonController
{
    public function forCourse(Request $req): never
    {
        $rows = Database::all(
            'SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC',
            [$req->params['id']],
        );
        Response::json([
            'lessons' => array_map([$this, 'shape'], $rows),
        ]);
    }

    public function show(Request $req): never
    {
        $row = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$row) Response::json(['error' => 'not_found'], 404);
        Response::json(['lesson' => $this->shape($row)]);
    }

    private function shape(array $r): array
    {
        return [
            'id'                 => $r['id'],
            'course_id'          => $r['course_id'],
            'title'              => $r['title'],
            'order_index'        => (int) $r['order_index'],
            'duration_seconds'   => (int) $r['duration_seconds'],
            'video_url'          => $r['video_url'],
            'description'        => $r['description'],
            'is_free_preview'    => (bool) $r['is_free_preview'],
        ];
    }
}
