<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/** Per-user notification inbox: list, mark-read, mark-all-read, delete. */
final class NotificationApiController
{
    public function inbox(Request $req): never
    {
        $rows = Database::all(
            'SELECT id, type, title, body, link, icon, is_read, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT 100',
            [$req->params['user']['id']],
        );
        $unread = (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$req->params['user']['id']],
        );
        Response::json(['notifications' => $rows, 'unread_count' => $unread]);
    }

    public function markRead(Request $req): never
    {
        Database::exec(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ?',
            [(int) $req->params['id'], $req->params['user']['id']],
        );
        Response::json(['ok' => true]);
    }

    public function markAllRead(Request $req): never
    {
        Database::exec(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0',
            [$req->params['user']['id']],
        );
        Response::json(['ok' => true]);
    }

    public function delete(Request $req): never
    {
        Database::exec(
            'DELETE FROM notifications WHERE id = ? AND user_id = ?',
            [(int) $req->params['id'], $req->params['user']['id']],
        );
        Response::json(['ok' => true]);
    }
}
