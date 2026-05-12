<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

final class ExamController
{
    public function index(Request $req): never
    {
        $exams = Database::all(
            'SELECT e.*, cl.name AS class_name,
                    (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) AS question_count,
                    (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id) AS attempt_count,
                    (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.passed = 1) AS pass_count
             FROM mock_exams e
             LEFT JOIN classes cl ON cl.id = e.class_id
             ORDER BY e.created_at DESC',
        );
        Response::html(View::render('admin/exams/index', [
            'exams' => $exams, 'me' => $req->params['user'], 'page' => 'exams',
        ]));
    }

    public function showCreate(Request $req): never
    {
        $classes = Database::all('SELECT id, name FROM classes ORDER BY name');
        Response::html(View::render('admin/exams/edit', [
            'exam' => null, 'classes' => $classes, 'me' => $req->params['user'], 'page' => 'exams',
        ]));
    }

    public function create(Request $req): never
    {
        $id = 'ex_' . bin2hex(random_bytes(8));
        Database::exec(
            'INSERT INTO mock_exams (id, title, description, class_id, subject_tag, duration_minutes,
             total_marks, pass_marks, rules_text, plan_required, shuffle_questions, show_answers_after,
             max_attempts, scheduled_at, expires_at, is_published)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $id,
                trim((string) $req->input('title', '')),
                trim((string) $req->input('description', '')),
                $req->input('class_id') ?: null,
                trim((string) $req->input('subject_tag', '')),
                max(1, (int) $req->input('duration_minutes', 60)),
                max(1, (int) $req->input('total_marks', 100)),
                max(0, (int) $req->input('pass_marks', 40)),
                trim((string) $req->input('rules_text', '')),
                $req->input('plan_required') ?: null,
                $req->input('shuffle_questions') ? 1 : 0,
                $req->input('show_answers_after') ? 1 : 0,
                max(1, (int) $req->input('max_attempts', 1)),
                $req->input('scheduled_at') ?: null,
                $req->input('expires_at') ?: null,
                0,
            ],
        );
        Response::redirect("/admin/exams/$id/questions");
    }

    public function showEdit(Request $req): never
    {
        $exam = Database::one('SELECT * FROM mock_exams WHERE id = ?', [$req->params['id']]);
        if (!$exam) Response::notFound();
        $classes = Database::all('SELECT id, name FROM classes ORDER BY name');
        Response::html(View::render('admin/exams/edit', [
            'exam' => $exam, 'classes' => $classes, 'me' => $req->params['user'], 'page' => 'exams',
        ]));
    }

    public function update(Request $req): never
    {
        $id = $req->params['id'];
        Database::exec(
            'UPDATE mock_exams SET title=?, description=?, class_id=?, subject_tag=?,
             duration_minutes=?, total_marks=?, pass_marks=?, rules_text=?,
             plan_required=?, shuffle_questions=?, show_answers_after=?,
             max_attempts=?, scheduled_at=?, expires_at=?, is_published=?
             WHERE id=?',
            [
                trim((string) $req->input('title', '')),
                trim((string) $req->input('description', '')),
                $req->input('class_id') ?: null,
                trim((string) $req->input('subject_tag', '')),
                max(1, (int) $req->input('duration_minutes', 60)),
                max(1, (int) $req->input('total_marks', 100)),
                max(0, (int) $req->input('pass_marks', 40)),
                trim((string) $req->input('rules_text', '')),
                $req->input('plan_required') ?: null,
                $req->input('shuffle_questions') ? 1 : 0,
                $req->input('show_answers_after') ? 1 : 0,
                max(1, (int) $req->input('max_attempts', 1)),
                $req->input('scheduled_at') ?: null,
                $req->input('expires_at') ?: null,
                $req->input('is_published') ? 1 : 0,
                $id,
            ],
        );
        Response::redirect("/admin/exams/$id/questions");
    }

    public function delete(Request $req): never
    {
        $id = $req->params['id'];
        Database::exec('DELETE FROM exam_answers WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE exam_id = ?)', [$id]);
        Database::exec('DELETE FROM exam_attempts WHERE exam_id = ?', [$id]);
        Database::exec('DELETE FROM exam_questions WHERE exam_id = ?', [$id]);
        Database::exec('DELETE FROM mock_exams WHERE id = ?', [$id]);
        Response::redirect('/admin/exams');
    }

    public function questions(Request $req): never
    {
        $exam = Database::one('SELECT * FROM mock_exams WHERE id = ?', [$req->params['id']]);
        if (!$exam) Response::notFound();
        $questions = Database::all(
            'SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY order_index ASC',
            [$exam['id']],
        );
        Response::html(View::render('admin/exams/questions', [
            'exam' => $exam, 'questions' => $questions, 'me' => $req->params['user'], 'page' => 'exams',
        ]));
    }

    public function questionCreate(Request $req): never
    {
        $examId = $req->params['id'];
        $max = (int) (Database::scalar('SELECT MAX(order_index) FROM exam_questions WHERE exam_id = ?', [$examId]) ?? -1);
        Database::exec(
            'INSERT INTO exam_questions (id, exam_id, question_text, option_a, option_b, option_c, option_d,
             correct_option, marks, explanation, order_index)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                'eq_' . bin2hex(random_bytes(8)),
                $examId,
                trim((string) $req->input('question_text', '')),
                trim((string) $req->input('option_a', '')),
                trim((string) $req->input('option_b', '')),
                trim((string) $req->input('option_c', '')) ?: null,
                trim((string) $req->input('option_d', '')) ?: null,
                strtoupper(trim((string) $req->input('correct_option', 'A'))),
                max(1, (int) $req->input('marks', 1)),
                trim((string) $req->input('explanation', '')) ?: null,
                $max + 1,
            ],
        );
        Response::redirect("/admin/exams/$examId/questions");
    }

    public function questionDelete(Request $req): never
    {
        $q = Database::one('SELECT * FROM exam_questions WHERE id = ?', [$req->params['id']]);
        if ($q) {
            Database::exec('DELETE FROM exam_questions WHERE id = ?', [$q['id']]);
        }
        Response::redirect('/admin/exams/' . ($q['exam_id'] ?? '') . '/questions');
    }

    public function results(Request $req): never
    {
        $exam = Database::one('SELECT * FROM mock_exams WHERE id = ?', [$req->params['id']]);
        if (!$exam) Response::notFound();

        $attempts = Database::all(
            'SELECT a.*, u.full_name, u.email,
                    ROUND((a.score / a.total_marks) * 100, 1) AS pct
             FROM exam_attempts a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.exam_id = ? AND a.status != "IN_PROGRESS"
             ORDER BY a.submitted_at DESC',
            [$exam['id']],
        );

        $stats = [
            'total'   => count($attempts),
            'passed'  => count(array_filter($attempts, fn($a) => (int)$a['passed'] === 1)),
            'avg_pct' => count($attempts) > 0
                ? round(array_sum(array_column($attempts, 'pct')) / count($attempts), 1)
                : 0,
        ];

        Response::html(View::render('admin/exams/results', [
            'exam' => $exam, 'attempts' => $attempts, 'stats' => $stats,
            'me' => $req->params['user'], 'page' => 'exams',
        ]));
    }

    public function publish(Request $req): never
    {
        $id = $req->params['id'];
        $current = (int) Database::scalar('SELECT is_published FROM mock_exams WHERE id = ?', [$id]);
        Database::exec('UPDATE mock_exams SET is_published = ? WHERE id = ?', [$current ? 0 : 1, $id]);
        Response::redirect("/admin/exams/$id/questions");
    }
}
