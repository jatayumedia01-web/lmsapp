<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Admin CRUD for quizzes and their questions. A quiz is owned by either a
 * lesson, a subject, or a class; the picker is on the quiz form.
 *
 * Question types:
 *  - MCQ        : single correct option from N
 *  - MULTI      : N correct options (partial credit OFF)
 *  - TRUE_FALSE : MCQ with the two options pre-baked
 *  - SHORT      : free-text, exact-match against correct_answer_text
 *  - FILL       : same as SHORT but rendered as inline blank in UI
 */
final class QuizController
{
    public function index(Request $req): never
    {
        $rows = Database::all(
            'SELECT q.*,
                    (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS questions_count,
                    (SELECT COUNT(*) FROM quiz_attempts  WHERE quiz_id = q.id) AS attempts_count
             FROM quizzes q
             ORDER BY q.created_at DESC',
        );
        Response::html(View::render('admin/quizzes/index', [
            'rows'  => $rows,
            'me'    => $req->params['user'],
            'page'  => 'quizzes',
            'flash' => $this->popFlash(),
        ]));
    }

    public function showCreate(Request $req): never
    {
        Response::html(View::render('admin/quizzes/edit', [
            'quiz'    => $this->blank(),
            'mode'    => 'create',
            'errors'  => [],
            'lessons' => $this->lookupLessons(),
            'me'      => $req->params['user'],
            'page'    => 'quizzes',
        ]));
    }

    public function create(Request $req): never
    {
        $data = $this->assemble($req);
        if (trim((string) $data['title']) === '') {
            Response::html(View::render('admin/quizzes/edit', [
                'quiz' => $data, 'mode' => 'create',
                'errors' => ['title' => 'required'],
                'lessons' => $this->lookupLessons(),
                'me' => $req->params['user'], 'page' => 'quizzes',
            ]));
        }
        $id = $data['id'] !== '' ? $data['id'] : ('qz_' . bin2hex(random_bytes(5)));
        Database::exec(
            'INSERT INTO quizzes
                (id, scope, parent_id, title, description, instructions,
                 pass_score_pct, time_limit_minutes, max_attempts,
                 shuffle_questions, shuffle_options, show_correct_answers, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $data['scope'], $data['parent_id'], $data['title'], $data['description'],
                $data['instructions'], (int) $data['pass_score_pct'],
                (int) $data['time_limit_minutes'], (int) $data['max_attempts'],
                (int) $data['shuffle_questions'], (int) $data['shuffle_options'],
                (int) $data['show_correct_answers'], (int) $data['is_published'],
            ],
        );
        $this->setFlash('Quiz created. Now add questions.', 'success');
        Response::redirect('/admin/quizzes/' . rawurlencode($id) . '/questions');
    }

    public function showEdit(Request $req): never
    {
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ?', [$req->params['id']]);
        if (!$quiz) Response::notFound();
        Response::html(View::render('admin/quizzes/edit', [
            'quiz' => $quiz, 'mode' => 'edit',
            'errors' => [],
            'lessons' => $this->lookupLessons(),
            'me' => $req->params['user'], 'page' => 'quizzes',
            'flash' => $this->popFlash(),
        ]));
    }

    public function update(Request $req): never
    {
        $existing = Database::one('SELECT * FROM quizzes WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();
        $data = $this->assemble($req);
        if (trim((string) $data['title']) === '') {
            $data['id'] = $req->params['id'];
            Response::html(View::render('admin/quizzes/edit', [
                'quiz' => $data, 'mode' => 'edit',
                'errors' => ['title' => 'required'],
                'lessons' => $this->lookupLessons(),
                'me' => $req->params['user'], 'page' => 'quizzes',
            ]));
        }
        Database::exec(
            'UPDATE quizzes SET
                scope = ?, parent_id = ?, title = ?, description = ?, instructions = ?,
                pass_score_pct = ?, time_limit_minutes = ?, max_attempts = ?,
                shuffle_questions = ?, shuffle_options = ?, show_correct_answers = ?, is_published = ?
             WHERE id = ?',
            [
                $data['scope'], $data['parent_id'], $data['title'], $data['description'],
                $data['instructions'], (int) $data['pass_score_pct'],
                (int) $data['time_limit_minutes'], (int) $data['max_attempts'],
                (int) $data['shuffle_questions'], (int) $data['shuffle_options'],
                (int) $data['show_correct_answers'], (int) $data['is_published'],
                $req->params['id'],
            ],
        );
        $this->setFlash('Quiz updated.', 'success');
        Response::redirect('/admin/quizzes/' . rawurlencode($req->params['id']));
    }

    public function delete(Request $req): never
    {
        Database::exec('DELETE FROM quizzes WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Quiz deleted (questions and attempts went with it).', 'success');
        Response::redirect('/admin/quizzes');
    }

    // ---- Question management --------------------------------------------

    public function questions(Request $req): never
    {
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ?', [$req->params['id']]);
        if (!$quiz) Response::notFound();
        $questions = Database::all(
            'SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC',
            [$quiz['id']],
        );
        Response::html(View::render('admin/quizzes/questions', [
            'quiz' => $quiz,
            'questions' => $questions,
            'me' => $req->params['user'],
            'page' => 'quizzes',
            'flash' => $this->popFlash(),
        ]));
    }

    public function questionCreate(Request $req): never
    {
        $quiz = Database::one('SELECT id FROM quizzes WHERE id = ?', [$req->params['id']]);
        if (!$quiz) Response::notFound();

        $type = strtoupper((string) $req->input('question_type', 'MCQ'));
        if (!in_array($type, ['MCQ','MULTI','TRUE_FALSE','SHORT','FILL'], true)) $type = 'MCQ';

        $text = trim((string) $req->input('question_text', ''));
        if ($text === '') {
            $this->setFlash('Question text required.', 'error');
            Response::redirect('/admin/quizzes/' . rawurlencode((string) $quiz['id']) . '/questions');
        }

        $optionsJson = null;
        $correctText = null;
        if ($type === 'TRUE_FALSE') {
            $correct = (int) $req->input('tf_correct', 1);
            $optionsJson = json_encode([
                ['text' => 'True',  'is_correct' => $correct === 1],
                ['text' => 'False', 'is_correct' => $correct === 0],
            ]);
        } elseif ($type === 'MCQ' || $type === 'MULTI') {
            $optionTexts = (array) ($req->input('option_text') ?? []);
            $correctIdx = $type === 'MCQ'
                ? [(int) $req->input('mcq_correct', 0)]
                : array_map('intval', (array) ($req->input('multi_correct') ?? []));
            $opts = [];
            foreach ($optionTexts as $i => $t) {
                $t = trim((string) $t);
                if ($t === '') continue;
                $opts[] = ['text' => $t, 'is_correct' => in_array((int) $i, $correctIdx, true)];
            }
            if (count($opts) < 2) {
                $this->setFlash('At least two options needed.', 'error');
                Response::redirect('/admin/quizzes/' . rawurlencode((string) $quiz['id']) . '/questions');
            }
            $optionsJson = json_encode($opts);
        } else { // SHORT, FILL
            $correctText = trim((string) $req->input('correct_answer_text', ''));
        }

        $next = (int) Database::scalar(
            'SELECT COALESCE(MAX(order_index), -1) + 1 FROM quiz_questions WHERE quiz_id = ?',
            [$quiz['id']],
        );

        Database::exec(
            'INSERT INTO quiz_questions
                (quiz_id, question_type, question_text, explanation, image_url,
                 points, order_index, options_json, correct_answer_text, is_required)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $quiz['id'], $type, $text,
                trim((string) $req->input('explanation', '')) ?: null,
                trim((string) $req->input('image_url', '')) ?: null,
                max(1, (int) $req->input('points', 1)),
                $next,
                $optionsJson,
                $correctText,
            ],
        );
        $this->setFlash('Question added.', 'success');
        Response::redirect('/admin/quizzes/' . rawurlencode((string) $quiz['id']) . '/questions');
    }

    public function questionDelete(Request $req): never
    {
        $q = Database::one('SELECT quiz_id FROM quiz_questions WHERE id = ?', [(int) $req->params['id']]);
        if (!$q) Response::notFound();
        Database::exec('DELETE FROM quiz_questions WHERE id = ?', [(int) $req->params['id']]);
        $this->setFlash('Question deleted.', 'success');
        Response::redirect('/admin/quizzes/' . rawurlencode((string) $q['quiz_id']) . '/questions');
    }

    public function questionReorder(Request $req): never
    {
        $q = Database::one('SELECT * FROM quiz_questions WHERE id = ?', [(int) $req->params['id']]);
        if (!$q) Response::notFound();
        $dir = (string) $req->input('dir', 'up');
        $cur = (int) $q['order_index'];
        $neighbour = Database::one(
            $dir === 'up'
                ? 'SELECT * FROM quiz_questions WHERE quiz_id = ? AND order_index < ? ORDER BY order_index DESC LIMIT 1'
                : 'SELECT * FROM quiz_questions WHERE quiz_id = ? AND order_index > ? ORDER BY order_index ASC  LIMIT 1',
            [$q['quiz_id'], $cur],
        );
        if ($neighbour) {
            Database::exec('UPDATE quiz_questions SET order_index = ? WHERE id = ?', [(int) $neighbour['order_index'], (int) $q['id']]);
            Database::exec('UPDATE quiz_questions SET order_index = ? WHERE id = ?', [$cur, (int) $neighbour['id']]);
        }
        Response::redirect('/admin/quizzes/' . rawurlencode((string) $q['quiz_id']) . '/questions');
    }

    /** Per-quiz attempts list (admin diagnostic). */
    public function attempts(Request $req): never
    {
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ?', [$req->params['id']]);
        if (!$quiz) Response::notFound();
        $rows = Database::all(
            'SELECT a.*, u.full_name, u.email
             FROM quiz_attempts a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.quiz_id = ?
             ORDER BY a.started_at DESC LIMIT 200',
            [$quiz['id']],
        );
        $stats = [
            'total'      => count($rows),
            'submitted'  => count(array_filter($rows, fn ($r) => $r['status'] === 'SUBMITTED')),
            'passed'     => count(array_filter($rows, fn ($r) => (int) $r['passed'] === 1)),
            'avg_score'  => count($rows) === 0 ? 0 :
                            round(array_sum(array_map(fn ($r) => (float) ($r['score_pct'] ?? 0), $rows)) / count($rows), 1),
        ];
        Response::html(View::render('admin/quizzes/attempts', [
            'quiz' => $quiz, 'rows' => $rows, 'stats' => $stats,
            'me' => $req->params['user'], 'page' => 'quizzes',
        ]));
    }

    // ---- helpers --------------------------------------------------------

    private function blank(): array
    {
        return [
            'id' => '', 'scope' => 'LESSON', 'parent_id' => '',
            'title' => '', 'description' => '', 'instructions' => '',
            'pass_score_pct' => 70, 'time_limit_minutes' => 0, 'max_attempts' => 0,
            'shuffle_questions' => 1, 'shuffle_options' => 1, 'show_correct_answers' => 1,
            'is_published' => 0,
        ];
    }

    private function assemble(Request $req): array
    {
        return [
            'id'                   => trim((string) $req->input('id', '')),
            'scope'                => strtoupper((string) $req->input('scope', 'LESSON')),
            'parent_id'            => trim((string) $req->input('parent_id', '')),
            'title'                => trim((string) $req->input('title', '')),
            'description'          => trim((string) $req->input('description', '')),
            'instructions'         => trim((string) $req->input('instructions', '')),
            'pass_score_pct'       => max(0, min(100, (int) $req->input('pass_score_pct', 70))),
            'time_limit_minutes'   => max(0, (int) $req->input('time_limit_minutes', 0)),
            'max_attempts'         => max(0, (int) $req->input('max_attempts', 0)),
            'shuffle_questions'    => $req->input('shuffle_questions')    ? 1 : 0,
            'shuffle_options'      => $req->input('shuffle_options')      ? 1 : 0,
            'show_correct_answers' => $req->input('show_correct_answers') ? 1 : 0,
            'is_published'         => $req->input('is_published')         ? 1 : 0,
        ];
    }

    /** @return array<int, array{id:string, label:string}> */
    private function lookupLessons(): array
    {
        return Database::all(
            'SELECT l.id, l.title AS lesson_title, c.title AS course_title, l.id AS value
             FROM lessons l LEFT JOIN courses c ON c.id = l.course_id
             ORDER BY c.title, l.order_index',
        );
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
