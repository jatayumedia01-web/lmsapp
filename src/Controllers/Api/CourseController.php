<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * Course catalog endpoints. Only `is_published = 1` rows are returned —
 * admins can stage drafts in the dashboard before exposing them.
 */
final class CourseController
{
    public function index(Request $req): never
    {
        $rows = Database::all(
            'SELECT * FROM courses WHERE is_published = 1 ORDER BY rating DESC, rating_count DESC',
        );
        Response::json([
            'courses' => array_map([$this, 'shape'], $rows),
        ]);
    }

    public function show(Request $req): never
    {
        $row = Database::one('SELECT * FROM courses WHERE id = ? AND is_published = 1', [$req->params['id']]);
        if (!$row) Response::json(['error' => 'not_found'], 404);
        Response::json(['course' => $this->shape($row)]);
    }

    /**
     * Maps the DB column names to the Android-side `Course` data class fields.
     * Keep camelCase / snake_case mapping in one place so the Android JSON
     * adapter can stay simple.
     */
    private function shape(array $r): array
    {
        return [
            'id'                => $r['id'],
            'title'             => $r['title'],
            'subtitle'          => $r['subtitle'],
            'description'       => $r['description'],
            'instructor_name'   => $r['instructor_name'],
            'cover_color_hex'   => $r['cover_color_hex'],
            'cover_image_url'   => $r['cover_image_url'],
            'category'          => $r['category'],
            'difficulty'        => $r['difficulty'],
            'total_lessons'     => (int) $r['total_lessons'],
            'duration_minutes'  => (int) $r['duration_minutes'],
            'rating'            => (float) $r['rating'],
            'rating_count'      => (int) $r['rating_count'],
            'is_premium'        => (bool) $r['is_premium'],
        ];
    }
}
