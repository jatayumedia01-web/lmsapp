<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

final class ExamApiController
{
    public function list(Request $req): never
    {
        $user = $req->params['user'];

        // Filter by class if user has a class assigned.
        $exams = Database::all(
            'SELECT e.id, e.title, e.description, e.duration_minutes, e.total_marks,
                    e.pass_marks, e.subject_tag, e.plan_required, e.rules_text,
                    e.scheduled_at, e.expires_at, e.shuffle_questions, e.max_attempts,
                    cl.name AS class_name,
                    (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) AS question_count,
                    (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.user_id = ? AND a.status != "IN_PROGRESS") AS my_attempts
             FROM mock_exams e
             LEFT JOIN classes cl ON cl.id = e.class_id
             WHERE e.is_published = 1
               AND (e.expires_at IS NULL OR e.expires_at > NOW())
             ORDER BY e.scheduled_at DESC, e.created_at DESC',
            [$user['id']],
        );

        Response::json(['exams' => $exams]);
    }

    public function show(Request $req): never
    {
        $user = $req->params['user'];
        $exam = Database::one(
            'SELECT e.*, cl.name AS class_name FROM mock_exams e
             LEFT JOIN classes cl ON cl.id = e.class_id
             WHERE e.id = ? AND e.is_published = 1',
            [$req->params['id']],
        );
        if (!$exam) Response::json(['error' => 'Exam not found'], 404);

        $myAttempts = (int) Database::scalar(
            'SELECT COUNT(*) FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND status != "IN_PROGRESS"',
            [$exam['id'], $user['id']],
        );
        $canAttempt = $myAttempts < (int) $exam['max_attempts'];

        Response::json(['exam' => $exam, 'my_attempts' => $myAttempts, 'can_attempt' => $canAttempt]);
    }

    public function start(Request $req): never
    {
        $user = $req->params['user'];
        $examId = $req->params['id'];

        $exam = Database::one('SELECT * FROM mock_exams WHERE id = ? AND is_published = 1', [$examId]);
        if (!$exam) Response::json(['error' => 'Exam not found'], 404);

        // Check attempt limit.
        $done = (int) Database::scalar(
            'SELECT COUNT(*) FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND status != "IN_PROGRESS"',
            [$examId, $user['id']],
        );
        if ($done >= (int) $exam['max_attempts']) {
            Response::json(['error' => 'Max attempts reached'], 403);
        }

        // Check for existing in-progress attempt.
        $existing = Database::one(
            'SELECT * FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND status = "IN_PROGRESS"',
            [$examId, $user['id']],
        );
        if ($existing) {
            $elapsed = (int) (time() - strtotime((string) $existing['started_at']));
            $remaining = ($exam['duration_minutes'] * 60) - $elapsed;
            if ($remaining <= 0) {
                $this->autoSubmit($existing['id'], $exam);
                Response::json(['error' => 'Time expired'], 403);
            }
            $questions = $this->getQuestions($examId, (bool) $exam['shuffle_questions'], $existing['id']);
            Response::json(['attempt_id' => $existing['id'], 'questions' => $questions, 'remaining_seconds' => $remaining]);
        }

        // Create new attempt.
        $attemptId = 'att_' . bin2hex(random_bytes(8));
        Database::exec(
            'INSERT INTO exam_attempts (id, exam_id, user_id, started_at, total_marks, pass_marks, status)
             VALUES (?, ?, ?, NOW(), ?, ?, "IN_PROGRESS")',
            [$attemptId, $examId, $user['id'], $exam['total_marks'], $exam['pass_marks']],
        );

        $questions = $this->getQuestions($examId, (bool) $exam['shuffle_questions'], $attemptId);
        Response::json([
            'attempt_id'       => $attemptId,
            'duration_seconds' => (int) $exam['duration_minutes'] * 60,
            'questions'        => $questions,
        ]);
    }

    public function saveAnswer(Request $req): never
    {
        $user      = $req->params['user'];
        $attemptId = $req->params['id'];
        $attempt   = Database::one(
            'SELECT a.*, e.duration_minutes FROM exam_attempts a
             JOIN mock_exams e ON e.id = a.exam_id
             WHERE a.id = ? AND a.user_id = ? AND a.status = "IN_PROGRESS"',
            [$attemptId, $user['id']],
        );
        if (!$attempt) Response::json(['error' => 'Attempt not found or already submitted'], 404);

        $elapsed = (int) (time() - strtotime((string) $attempt['started_at']));
        if ($elapsed > $attempt['duration_minutes'] * 60 + 30) {
            $this->autoSubmit($attemptId, $attempt);
            Response::json(['error' => 'Time expired', 'auto_submitted' => true], 200);
        }

        $questionId = (string) $req->input('question_id', '');
        $selected   = strtoupper(trim((string) $req->input('selected_option', '')));
        if (!in_array($selected, ['A', 'B', 'C', 'D', ''], true)) {
            Response::json(['error' => 'Invalid option'], 400);
        }

        $existing = Database::one(
            'SELECT id FROM exam_answers WHERE attempt_id = ? AND question_id = ?',
            [$attemptId, $questionId],
        );
        if ($existing) {
            Database::exec(
                'UPDATE exam_answers SET selected_option = ? WHERE attempt_id = ? AND question_id = ?',
                [$selected ?: null, $attemptId, $questionId],
            );
        } else {
            Database::exec(
                'INSERT INTO exam_answers (attempt_id, question_id, selected_option) VALUES (?,?,?)',
                [$attemptId, $questionId, $selected ?: null],
            );
        }
        Response::json(['ok' => true]);
    }

    public function submit(Request $req): never
    {
        $user      = $req->params['user'];
        $attemptId = $req->params['id'];
        $attempt   = Database::one(
            'SELECT a.*, e.* FROM exam_attempts a
             JOIN mock_exams e ON e.id = a.exam_id
             WHERE a.id = ? AND a.user_id = ?',
            [$attemptId, $user['id']],
        );
        if (!$attempt) Response::json(['error' => 'Attempt not found'], 404);
        if ($attempt['status'] !== 'IN_PROGRESS') {
            Response::json($this->buildResult($attemptId, $attempt, $user));
        }

        $elapsed = (int) (time() - strtotime((string) $attempt['started_at']));
        $timedOut = $elapsed > $attempt['duration_minutes'] * 60 + 30;
        $this->autoSubmit($attemptId, $attempt, $timedOut ? 'TIMED_OUT' : 'SUBMITTED');

        $updated = Database::one('SELECT * FROM exam_attempts WHERE id = ?', [$attemptId]);
        Response::json($this->buildResult($attemptId, $updated, $user));
    }

    public function result(Request $req): never
    {
        $user      = $req->params['user'];
        $attemptId = $req->params['id'];
        $attempt   = Database::one(
            'SELECT a.* FROM exam_attempts a WHERE a.id = ? AND a.user_id = ?',
            [$attemptId, $user['id']],
        );
        if (!$attempt) Response::json(['error' => 'Not found'], 404);
        Response::json($this->buildResult($attemptId, $attempt, $user));
    }

    // ---- helpers --------------------------------------------------------

    private function getQuestions(string $examId, bool $shuffle, string $attemptId): array
    {
        $qs = Database::all(
            'SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                    q.marks, q.order_index,
                    a.selected_option AS my_answer
             FROM exam_questions q
             LEFT JOIN exam_answers a ON a.question_id = q.id AND a.attempt_id = ?
             WHERE q.exam_id = ?
             ORDER BY q.order_index ASC',
            [$attemptId, $examId],
        );
        if ($shuffle) shuffle($qs);
        return $qs;
    }

    private function autoSubmit(string $attemptId, array $attempt, string $status = 'SUBMITTED'): void
    {
        $questions = Database::all(
            'SELECT q.id, q.correct_option, q.marks FROM exam_questions q WHERE q.exam_id = ?',
            [$attempt['exam_id']],
        );

        $score = 0;
        foreach ($questions as $q) {
            $ans = Database::one(
                'SELECT selected_option FROM exam_answers WHERE attempt_id = ? AND question_id = ?',
                [$attemptId, $q['id']],
            );
            $correct = $ans && $ans['selected_option'] === $q['correct_option'];
            $awarded = $correct ? (int) $q['marks'] : 0;
            if ($ans) {
                Database::exec(
                    'UPDATE exam_answers SET is_correct = ?, marks_awarded = ? WHERE attempt_id = ? AND question_id = ?',
                    [$correct ? 1 : 0, $awarded, $attemptId, $q['id']],
                );
            } else {
                Database::exec(
                    'INSERT INTO exam_answers (attempt_id, question_id, selected_option, is_correct, marks_awarded)
                     VALUES (?,?,NULL,0,0)',
                    [$attemptId, $q['id']],
                );
            }
            $score += $awarded;
        }

        $passed = $score >= (int) $attempt['pass_marks'];
        $elapsed = (int) (time() - strtotime((string) $attempt['started_at']));

        $certNumber = null;
        if ($passed) {
            $certNumber = strtoupper(substr(md5($attemptId . $score), 0, 12));
        }

        Database::exec(
            'UPDATE exam_attempts SET status = ?, submitted_at = NOW(), time_taken_seconds = ?,
             score = ?, passed = ?, certificate_number = ?,
             certificate_issued_at = IF(? IS NOT NULL, NOW(), NULL)
             WHERE id = ?',
            [$status, $elapsed, $score, $passed ? 1 : 0, $certNumber, $certNumber, $attemptId],
        );
    }

    private function buildResult(string $attemptId, array $attempt, array $user): array
    {
        $exam = Database::one('SELECT * FROM mock_exams WHERE id = ?', [$attempt['exam_id']]);

        $answers = [];
        if ((int) ($exam['show_answers_after'] ?? 1)) {
            $answers = Database::all(
                'SELECT a.question_id, a.selected_option, a.is_correct, a.marks_awarded,
                        q.question_text, q.correct_option, q.explanation,
                        q.option_a, q.option_b, q.option_c, q.option_d
                 FROM exam_answers a
                 JOIN exam_questions q ON q.id = a.question_id
                 WHERE a.attempt_id = ?
                 ORDER BY q.order_index ASC',
                [$attemptId],
            );
        }

        $totalQ = (int) Database::scalar('SELECT COUNT(*) FROM exam_questions WHERE exam_id = ?', [$attempt['exam_id']]);
        $pct    = $attempt['total_marks'] > 0
            ? round(((int) $attempt['score'] / (int) $attempt['total_marks']) * 100, 1)
            : 0;

        return [
            'attempt_id'         => $attemptId,
            'status'             => $attempt['status'],
            'score'              => (int) $attempt['score'],
            'total_marks'        => (int) $attempt['total_marks'],
            'pass_marks'         => (int) $attempt['pass_marks'],
            'passed'             => (bool) $attempt['passed'],
            'percentage'         => $pct,
            'time_taken_seconds' => (int) $attempt['time_taken_seconds'],
            'total_questions'    => $totalQ,
            'correct_count'      => (int) Database::scalar(
                'SELECT COUNT(*) FROM exam_answers WHERE attempt_id = ? AND is_correct = 1', [$attemptId]
            ),
            'certificate' => $attempt['certificate_number'] ? [
                'number'      => $attempt['certificate_number'],
                'issued_at'   => $attempt['certificate_issued_at'],
                'student_name'=> $user['full_name'],
                'exam_title'  => $exam['title'],
                'score'       => $attempt['score'],
                'total'       => $attempt['total_marks'],
                'percentage'  => $pct,
            ] : null,
            'answers' => $answers,
        ];
    }
}
