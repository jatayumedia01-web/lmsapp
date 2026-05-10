<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Notification campaigns + per-row inbox view. Admin-side composer fans
 * out to the relevant users when "Send now" is hit; respects each user's
 * email/push preference toggle.
 */
final class NotificationController
{
    public function index(Request $req): never
    {
        $campaigns = Database::all(
            'SELECT c.*, u.full_name AS author_name
             FROM notification_campaigns c
             LEFT JOIN users u ON u.id = c.created_by
             ORDER BY c.created_at DESC LIMIT 100',
        );
        $stats = [
            'total_sent'  => (int) (Database::scalar('SELECT COALESCE(SUM(sent_count), 0) FROM notification_campaigns WHERE sent_at IS NOT NULL') ?? 0),
            'campaigns'   => (int) Database::scalar('SELECT COUNT(*) FROM notification_campaigns'),
            'unread'      => (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE is_read = 0'),
            'last_24h'    => (int) Database::scalar('SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'),
        ];
        $classes = Database::all('SELECT id, name FROM classes ORDER BY name');
        $courses = Database::all('SELECT id, title FROM courses ORDER BY title');
        Response::html(View::render('admin/notifications/index', [
            'campaigns' => $campaigns,
            'stats'     => $stats,
            'classes'   => $classes,
            'courses'   => $courses,
            'me'        => $req->params['user'],
            'page'      => 'notifications',
            'flash'     => $this->popFlash(),
        ]));
    }

    public function send(Request $req): never
    {
        $title  = trim((string) $req->input('title', ''));
        $body   = trim((string) $req->input('body', ''));
        $link   = trim((string) $req->input('link', ''));
        $icon   = trim((string) $req->input('icon', ''));
        $target = strtoupper((string) $req->input('target', 'ALL'));
        $targetId = trim((string) $req->input('target_id', ''));
        $sendEmail = $req->input('send_email') ? 1 : 0;
        $sendPush  = $req->input('send_push')  ? 1 : 0;

        if ($title === '' || $body === '') {
            $this->setFlash('Title and body are required.', 'error');
            Response::redirect('/admin/notifications');
        }
        if (!in_array($target, ['ALL','CLASS','SUBJECT','PAYING','BANNED','ROLE'], true)) {
            $target = 'ALL';
        }

        // Resolve recipient user ids based on target.
        $userIds = $this->resolveTargets($target, $targetId);
        if (empty($userIds)) {
            $this->setFlash('No users matched this target.', 'error');
            Response::redirect('/admin/notifications');
        }

        // Insert campaign row.
        Database::exec(
            'INSERT INTO notification_campaigns
                (title, body, link, icon, target, target_id, send_email, send_push,
                 sent_count, sent_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $title, $body, $link ?: null, $icon ?: null,
                $target, $targetId ?: null, $sendEmail, $sendPush,
                count($userIds), $req->params['user']['id'],
            ],
        );
        $campaignId = (int) Database::pdo()->lastInsertId();

        // Fan-out: one notifications row per user. Batched insert keeps it cheap.
        $now = date('Y-m-d H:i:s');
        $values = [];
        $params = [];
        foreach ($userIds as $uid) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $params[] = $uid;
            $params[] = 'CAMPAIGN';
            $params[] = $title;
            $params[] = $body;
            $params[] = $link ?: null;
            $params[] = $icon ?: null;
            $params[] = $sendEmail;
            $params[] = $sendPush;
            $params[] = $campaignId;
            $params[] = $now;
        }
        // Insert in chunks of 200 to stay under bind-param limits on shared MySQL.
        foreach (array_chunk($values, 200, true) as $chunkIdx => $chunk) {
            $sql = 'INSERT INTO notifications
                        (user_id, type, title, body, link, icon, sent_email, sent_push, campaign_id, created_at)
                    VALUES ' . implode(', ', $chunk);
            $offset = $chunkIdx * 200 * 10;
            $slice  = array_slice($params, $offset, count($chunk) * 10);
            Database::exec($sql, $slice);
        }

        $this->setFlash('Sent to ' . count($userIds) . ' user(s).', 'success');
        Response::redirect('/admin/notifications');
    }

    /** @return array<int, string> */
    private function resolveTargets(string $target, string $targetId): array
    {
        switch ($target) {
            case 'ALL':
                return array_column(Database::all('SELECT id FROM users WHERE role = ? AND is_banned = 0', ['STUDENT']), 'id');
            case 'CLASS':
                return array_column(Database::all(
                    'SELECT DISTINCT e.user_id AS id
                     FROM enrollments e
                     INNER JOIN courses c ON c.id = e.course_id
                     WHERE c.class_id = ?',
                    [$targetId],
                ), 'id');
            case 'SUBJECT':
                return array_column(Database::all('SELECT user_id AS id FROM enrollments WHERE course_id = ?', [$targetId]), 'id');
            case 'PAYING':
                return array_column(Database::all('SELECT user_id AS id FROM subscriptions WHERE status IN (?, ?)', ['ACTIVE', 'TRIALING']), 'id');
            case 'BANNED':
                return array_column(Database::all('SELECT id FROM users WHERE is_banned = 1'), 'id');
            case 'ROLE':
                return array_column(Database::all('SELECT id FROM users WHERE role = ?', [$targetId]), 'id');
            default:
                return [];
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
