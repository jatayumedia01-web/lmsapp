<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * FAQ CRUD scoped to a lesson. Listed inline on the lesson edit page;
 * each create/update/delete redirects back to that page so the editor
 * never loses context.
 */
final class FaqController
{
    public function create(Request $req): never
    {
        $lessonId = (string) $req->params['lessonId'];
        $lesson = Database::one('SELECT id FROM lessons WHERE id = ?', [$lessonId]);
        if (!$lesson) Response::notFound();

        $question = trim((string) $req->input('question', ''));
        $answer   = trim((string) $req->input('answer', ''));
        if ($question === '' || $answer === '') {
            $this->setFlash('Question and answer are both required.', 'error');
            Response::redirect('/admin/lessons/' . rawurlencode($lessonId));
        }
        $next = (int) Database::scalar(
            'SELECT COALESCE(MAX(order_index), -1) + 1 FROM lesson_faqs WHERE lesson_id = ?',
            [$lessonId],
        );
        Database::exec(
            'INSERT INTO lesson_faqs (lesson_id, question, answer, order_index, is_published)
             VALUES (?, ?, ?, ?, 1)',
            [$lessonId, $question, $answer, $next],
        );
        $this->setFlash('FAQ added.', 'success');
        Response::redirect('/admin/lessons/' . rawurlencode($lessonId) . '#faqs');
    }

    public function update(Request $req): never
    {
        $faq = Database::one('SELECT * FROM lesson_faqs WHERE id = ?', [(int) $req->params['id']]);
        if (!$faq) Response::notFound();

        $question = trim((string) $req->input('question', ''));
        $answer   = trim((string) $req->input('answer', ''));
        if ($question === '' || $answer === '') {
            $this->setFlash('Question and answer cannot be blank.', 'error');
            Response::redirect('/admin/lessons/' . rawurlencode((string) $faq['lesson_id']) . '#faqs');
        }
        Database::exec(
            'UPDATE lesson_faqs SET question = ?, answer = ?, is_published = ? WHERE id = ?',
            [$question, $answer, $req->input('is_published') ? 1 : 0, (int) $req->params['id']],
        );
        $this->setFlash('FAQ updated.', 'success');
        Response::redirect('/admin/lessons/' . rawurlencode((string) $faq['lesson_id']) . '#faqs');
    }

    public function delete(Request $req): never
    {
        $faq = Database::one('SELECT lesson_id FROM lesson_faqs WHERE id = ?', [(int) $req->params['id']]);
        if (!$faq) Response::notFound();
        Database::exec('DELETE FROM lesson_faqs WHERE id = ?', [(int) $req->params['id']]);
        $this->setFlash('FAQ deleted.', 'success');
        Response::redirect('/admin/lessons/' . rawurlencode((string) $faq['lesson_id']) . '#faqs');
    }

    public function reorder(Request $req): never
    {
        $faq = Database::one('SELECT * FROM lesson_faqs WHERE id = ?', [(int) $req->params['id']]);
        if (!$faq) Response::notFound();
        $direction = (string) $req->input('dir', 'up');
        $current = (int) $faq['order_index'];

        $neighbour = Database::one(
            $direction === 'up'
                ? 'SELECT * FROM lesson_faqs WHERE lesson_id = ? AND order_index < ? ORDER BY order_index DESC LIMIT 1'
                : 'SELECT * FROM lesson_faqs WHERE lesson_id = ? AND order_index > ? ORDER BY order_index ASC  LIMIT 1',
            [$faq['lesson_id'], $current],
        );
        if ($neighbour) {
            Database::exec('UPDATE lesson_faqs SET order_index = ? WHERE id = ?', [(int) $neighbour['order_index'], (int) $faq['id']]);
            Database::exec('UPDATE lesson_faqs SET order_index = ? WHERE id = ?', [$current, (int) $neighbour['id']]);
        }
        Response::redirect('/admin/lessons/' . rawurlencode((string) $faq['lesson_id']) . '#faqs');
    }

    private function setFlash(string $message, string $kind = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
    }
}
