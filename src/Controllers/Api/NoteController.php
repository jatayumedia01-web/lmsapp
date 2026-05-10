<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/** Per-user notes anchored to a lesson and (optionally) a video timestamp. */
final class NoteController
{
    public function forLesson(Request $req): never
    {
        $rows = Database::all(
            'SELECT * FROM user_notes WHERE user_id = ? AND lesson_id = ?
             ORDER BY COALESCE(timestamp_seconds, 0) ASC, id ASC',
            [$req->params['user']['id'], $req->params['id']],
        );
        Response::json(['notes' => $rows]);
    }

    public function create(Request $req): never
    {
        $lesson = Database::one('SELECT id, course_id FROM lessons WHERE id = ?', [(string) $req->input('lesson_id')]);
        if (!$lesson) Response::json(['error' => 'lesson_not_found'], 404);

        $text = trim((string) $req->input('note_text', ''));
        if ($text === '') Response::json(['error' => 'note_text required'], 422);

        Database::exec(
            'INSERT INTO user_notes (user_id, lesson_id, course_id, note_text, timestamp_seconds, color)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $req->params['user']['id'], $lesson['id'], $lesson['course_id'],
                $text,
                $req->input('timestamp_seconds') !== null ? (int) $req->input('timestamp_seconds') : null,
                $req->input('color') !== null ? substr((string) $req->input('color'), 0, 20) : null,
            ],
        );
        Response::json(['ok' => true, 'id' => (int) Database::pdo()->lastInsertId()]);
    }

    public function update(Request $req): never
    {
        $note = Database::one('SELECT * FROM user_notes WHERE id = ?', [(int) $req->params['id']]);
        if (!$note || $note['user_id'] !== $req->params['user']['id']) {
            Response::json(['error' => 'not_found'], 404);
        }
        $text = trim((string) $req->input('note_text', $note['note_text']));
        Database::exec(
            'UPDATE user_notes SET note_text = ?, timestamp_seconds = ?, color = ? WHERE id = ?',
            [
                $text,
                $req->input('timestamp_seconds') !== null ? (int) $req->input('timestamp_seconds') : null,
                $req->input('color') !== null ? substr((string) $req->input('color'), 0, 20) : null,
                (int) $req->params['id'],
            ],
        );
        Response::json(['ok' => true]);
    }

    public function delete(Request $req): never
    {
        $note = Database::one('SELECT user_id FROM user_notes WHERE id = ?', [(int) $req->params['id']]);
        if (!$note || $note['user_id'] !== $req->params['user']['id']) {
            Response::json(['error' => 'not_found'], 404);
        }
        Database::exec('DELETE FROM user_notes WHERE id = ?', [(int) $req->params['id']]);
        Response::json(['ok' => true]);
    }
}
