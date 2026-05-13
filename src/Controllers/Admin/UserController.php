<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Admin user management — list, search, ban/unban, role change, delete.
 *
 * Filtering happens in SQL so the dataset can grow without forcing the
 * admin to scroll through 50k rows. All inputs are bound via prepared
 * statements; the only string interpolation is whitelist-driven (sort/dir).
 */
final class UserController
{
    private const PAGE_SIZE = 25;

    private const SORT_COLUMNS = [
        'joined_at'        => 'joined_at',
        'last_sign_in_at'  => 'last_sign_in_at',
        'full_name'        => 'full_name',
        'email'            => 'email',
        'xp'               => 'xp',
    ];

    public function index(Request $req): never
    {
        $q       = trim((string) $req->input('q', ''));
        $role    = (string) $req->input('role', '');
        $banned  = (string) $req->input('banned', '');
        $sort    = (string) $req->input('sort', 'joined_at');
        $dir     = strtolower((string) $req->input('dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $page    = max(1, (int) $req->input('page', 1));

        $sortCol = self::SORT_COLUMNS[$sort] ?? 'joined_at';

        $where  = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[]  = '(email LIKE ? OR full_name LIKE ? OR id LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($role !== '' && in_array($role, ['STUDENT', 'INSTRUCTOR', 'ADMIN', 'PARENT'], true)) {
            $where[]  = 'role = ?';
            $params[] = $role;
        }
        if ($banned === '1') { $where[] = 'is_banned = 1'; }
        if ($banned === '0') { $where[] = 'is_banned = 0'; }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page  = min($page, $pages);
        $offset = ($page - 1) * self::PAGE_SIZE;

        $users = Database::all(
            "SELECT id, email, full_name, role, is_banned, banned_at, xp, streak_days,
                    joined_at, last_sign_in_at
             FROM users
             WHERE $whereSql
             ORDER BY $sortCol $dir, id ASC
             LIMIT " . self::PAGE_SIZE . " OFFSET $offset",
            $params,
        );

        Response::html(View::render('admin/users/index', [
            'users'  => $users,
            'q'      => $q,
            'role'   => $role,
            'banned' => $banned,
            'sort'   => $sort,
            'dir'    => strtolower($dir),
            'page'   => 'users',
            'pageNo' => $page,
            'pages'  => $pages,
            'total'  => $total,
            'me'     => $req->params['user'],
            'flash'  => $this->popFlash(),
        ]));
    }

    public function show(Request $req): never
    {
        // SELECT * includes all profile columns added by migration 012.
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$req->params['id']]);
        if (!$user) Response::notFound();

        $sub = Database::one(
            'SELECT * FROM subscriptions WHERE user_id = ?',
            [$user['id']],
        );

        $stats = [
            'enrollments'    => (int) Database::scalar('SELECT COUNT(*) FROM enrollments WHERE user_id = ?', [$user['id']]),
            'questions'      => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions WHERE author_id = ?', [$user['id']]),
            'answers'        => (int) Database::scalar('SELECT COUNT(*) FROM lesson_answers WHERE author_id = ?', [$user['id']]),
            'invoices_paid'  => (int) Database::scalar(
                'SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = ?',
                [$user['id'], 'PAID'],
            ),
            'lifetime_cents' => (int) (Database::scalar(
                'SELECT COALESCE(SUM(amount_cents), 0) FROM invoices WHERE user_id = ? AND status = ?',
                [$user['id'], 'PAID'],
            ) ?? 0),
            // Extra stats from migration-011 tables
            'quiz_attempts'  => (int) (Database::scalar(
                'SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?',
                [$user['id']],
            ) ?? 0),
            'certificates'   => (int) (Database::scalar(
                'SELECT COUNT(*) FROM certificates WHERE user_id = ?',
                [$user['id']],
            ) ?? 0),
            'notes'          => (int) (Database::scalar(
                'SELECT COUNT(*) FROM user_notes WHERE user_id = ?',
                [$user['id']],
            ) ?? 0),
        ];

        $invoices = Database::all(
            'SELECT * FROM invoices WHERE user_id = ? ORDER BY date_millis DESC LIMIT 10',
            [$user['id']],
        );

        $recentEnrollments = Database::all(
            'SELECT e.*, c.title AS course_title
             FROM enrollments e
             LEFT JOIN courses c ON c.id = e.course_id
             WHERE e.user_id = ? ORDER BY e.enrolled_at DESC LIMIT 10',
            [$user['id']],
        );

        $captureAttempts = Database::all(
            'SELECT occurred_at, props_json FROM user_events
             WHERE user_id = ? AND event_name = "screen_capture_attempt"
             ORDER BY occurred_at DESC LIMIT 20',
            [$user['id']],
        );

        Response::html(View::render('admin/users/show', [
            'user'              => $user,
            'sub'               => $sub,
            'stats'             => $stats,
            'invoices'          => $invoices,
            'recentEnrollments' => $recentEnrollments,
            'captureAttempts'   => $captureAttempts,
            'me'                => $req->params['user'],
            'page'              => 'users',
            'flash'             => $this->popFlash(),
        ]));
    }

    public function ban(Request $req): never
    {
        $reason = trim((string) $req->input('reason', ''));
        Database::exec(
            'UPDATE users SET is_banned = 1, banned_at = NOW(), banned_reason = ? WHERE id = ?',
            [$reason !== '' ? $reason : 'Violation of terms', $req->params['id']],
        );
        // Kill any live API tokens so the ban is immediate.
        Database::exec('DELETE FROM auth_tokens WHERE user_id = ?', [$req->params['id']]);
        $this->setFlash('User banned and signed out everywhere.', 'success');
        Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
    }

    public function unban(Request $req): never
    {
        Database::exec(
            'UPDATE users SET is_banned = 0, banned_at = NULL, banned_reason = NULL WHERE id = ?',
            [$req->params['id']],
        );
        $this->setFlash('User unbanned.', 'success');
        Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
    }

    public function changeRole(Request $req): never
    {
        $newRole = (string) $req->input('role', 'STUDENT');
        if (!in_array($newRole, ['STUDENT', 'INSTRUCTOR', 'ADMIN', 'PARENT'], true)) {
            $this->setFlash('Invalid role.', 'error');
            Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
        }
        // Don't let an admin demote themselves and lock everyone out.
        $me = $req->params['user'];
        if ($me['id'] === $req->params['id'] && $newRole !== 'ADMIN') {
            $this->setFlash("You can't demote yourself — ask another admin.", 'error');
            Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
        }
        Database::exec('UPDATE users SET role = ? WHERE id = ?', [$newRole, $req->params['id']]);
        $this->setFlash("Role updated to $newRole.", 'success');
        Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
    }

    public function delete(Request $req): never
    {
        $me = $req->params['user'];
        if ($me['id'] === $req->params['id']) {
            $this->setFlash("You can't delete your own account from here.", 'error');
            Response::redirect('/admin/users/' . rawurlencode($req->params['id']));
        }
        Database::exec('DELETE FROM users WHERE id = ?', [$req->params['id']]);
        $this->setFlash('User deleted (cascades removed enrollments, Q&A, etc.).', 'success');
        Response::redirect('/admin/users');
    }

    public function exportCsv(Request $req): never
    {
        $rows = Database::all(
            'SELECT id, email, full_name, role, is_banned, xp, streak_days, joined_at, last_sign_in_at
             FROM users ORDER BY joined_at DESC',
        );
        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, ['id','email','name','role','banned','xp','streak','joined_at','last_sign_in_at']);
        foreach ($rows as $r) {
            fputcsv($fp, [
                $r['id'], $r['email'], $r['full_name'], $r['role'], $r['is_banned'],
                $r['xp'], $r['streak_days'], $r['joined_at'], $r['last_sign_in_at'] ?? '',
            ]);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users-' . date('Ymd-His') . '.csv"');
        echo $csv;
        exit;
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
