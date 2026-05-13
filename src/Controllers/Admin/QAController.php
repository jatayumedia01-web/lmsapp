<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Q&A moderation — list, filter, approve/reject/spam, pin, resolve, delete.
 *
 * "Approved" is the default for legacy rows (set in migration 007). When the
 * `qa_premoderation` setting is enabled, the API write path should insert
 * new questions with status PENDING — that wiring lives in the API
 * controller, not here.
 */
final class QAController
{
    private const PAGE_SIZE = 25;

    public function index(Request $req): never
    {
        $status   = (string) $req->input('status', '');
        $courseId = (string) $req->input('course_id', '');
        $q        = trim((string) $req->input('q', ''));
        $pageNo   = max(1, (int) $req->input('page', 1));

        $where = ['1=1']; $params = [];
        if (in_array($status, ['PENDING', 'APPROVED', 'REJECTED', 'SPAM'], true)) {
            $where[] = 'q.moderation_status = ?'; $params[] = $status;
        }
        if ($courseId !== '') {
            $where[] = 'q.course_id = ?'; $params[] = $courseId;
        }
        if ($q !== '') {
            $where[] = '(q.body LIKE ? OR q.author_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM lesson_questions q WHERE $whereSql",
            $params,
        );
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $pageNo = min($pageNo, $pages);
        $offset = ($pageNo - 1) * self::PAGE_SIZE;

        $rows = Database::all(
            "SELECT q.*, c.title AS course_title, l.title AS lesson_title
             FROM lesson_questions q
             LEFT JOIN courses c ON c.id = q.course_id
             LEFT JOIN lessons l ON l.id = q.lesson_id
             WHERE $whereSql
             ORDER BY q.is_pinned DESC, q.created_at DESC
             LIMIT " . self::PAGE_SIZE . " OFFSET $offset",
            $params,
        );

        $courses = Database::all('SELECT id, title FROM courses ORDER BY title ASC');

        $counts = [
            'PENDING'  => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = ?', ['PENDING']),
            'APPROVED' => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = ?', ['APPROVED']),
            'REJECTED' => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = ?', ['REJECTED']),
            'SPAM'     => (int) Database::scalar('SELECT COUNT(*) FROM lesson_questions WHERE moderation_status = ?', ['SPAM']),
        ];

        Response::html(View::render('admin/qa/index', [
            'rows'     => $rows,
            'courses'  => $courses,
            'counts'   => $counts,
            'status'   => $status,
            'courseId' => $courseId,
            'q'        => $q,
            'pageNo'   => $pageNo,
            'pages'    => $pages,
            'total'    => $total,
            'page'     => 'qa',
            'me'       => $req->params['user'],
            'flash'    => $this->popFlash(),
        ]));
    }

    public function show(Request $req): never
    {
        $question = Database::one(
            'SELECT q.*, c.title AS course_title, l.title AS lesson_title
             FROM lesson_questions q
             LEFT JOIN courses c ON c.id = q.course_id
             LEFT JOIN lessons l ON l.id = q.lesson_id
             WHERE q.id = ?',
            [$req->params['id']],
        );
        if (!$question) Response::notFound();

        $answers = Database::all(
            'SELECT * FROM lesson_answers WHERE question_id = ? ORDER BY is_instructor DESC, like_count DESC, created_at ASC',
            [$req->params['id']],
        );

        Response::html(View::render('admin/qa/show', [
            'question' => $question,
            'answers'  => $answers,
            'page'     => 'qa',
            'me'       => $req->params['user'],
            'flash'    => $this->popFlash(),
        ]));
    }

    public function setStatus(Request $req): never
    {
        $newStatus = strtoupper((string) $req->input('status', ''));
        if (!in_array($newStatus, ['PENDING', 'APPROVED', 'REJECTED', 'SPAM'], true)) {
            $this->setFlash('Invalid moderation status.', 'error');
            Response::redirect('/admin/qa');
        }
        Database::exec(
            'UPDATE lesson_questions SET moderation_status = ?, moderated_at = NOW(), moderated_by = ? WHERE id = ?',
            [$newStatus, $req->params['user']['id'], $req->params['id']],
        );
        $this->setFlash("Question marked $newStatus.", 'success');
        Response::redirect($req->input('back') === 'show'
            ? '/admin/qa/' . rawurlencode($req->params['id'])
            : '/admin/qa');
    }

    public function bulkSetStatus(Request $req): never
    {
        $ids = (array) ($req->input('ids') ?? []);
        $newStatus = strtoupper((string) $req->input('status', ''));
        if (!$ids || !in_array($newStatus, ['PENDING', 'APPROVED', 'REJECTED', 'SPAM'], true)) {
            $this->setFlash('Pick at least one question and a status.', 'error');
            Response::redirect('/admin/qa');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge(
            [$newStatus, $req->params['user']['id']],
            array_values($ids),
        );
        Database::exec(
            "UPDATE lesson_questions
             SET moderation_status = ?, moderated_at = NOW(), moderated_by = ?
             WHERE id IN ($placeholders)",
            $params,
        );
        $this->setFlash(count($ids) . " question(s) marked $newStatus.", 'success');
        Response::redirect('/admin/qa');
    }

    public function togglePinned(Request $req): never
    {
        Database::exec(
            'UPDATE lesson_questions SET is_pinned = 1 - is_pinned WHERE id = ?',
            [$req->params['id']],
        );
        $this->setFlash('Pin toggled.', 'success');
        Response::redirect('/admin/qa/' . rawurlencode($req->params['id']));
    }

    public function toggleResolved(Request $req): never
    {
        Database::exec(
            'UPDATE lesson_questions SET is_resolved = 1 - is_resolved WHERE id = ?',
            [$req->params['id']],
        );
        $this->setFlash('Resolved flag toggled.', 'success');
        Response::redirect('/admin/qa/' . rawurlencode($req->params['id']));
    }

    public function delete(Request $req): never
    {
        Database::exec('DELETE FROM lesson_questions WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Question deleted (cascades removed answers and votes).', 'success');
        Response::redirect('/admin/qa');
    }

    public function deleteAnswer(Request $req): never
    {
        $answer = Database::one('SELECT question_id FROM lesson_answers WHERE id = ?', [$req->params['id']]);
        Database::exec('DELETE FROM lesson_answers WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Answer deleted.', 'success');
        Response::redirect($answer
            ? '/admin/qa/' . rawurlencode($answer['question_id'])
            : '/admin/qa');
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
