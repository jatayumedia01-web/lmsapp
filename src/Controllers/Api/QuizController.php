<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * Mobile-side quiz lifecycle:
 *  1. GET  /quizzes/{id}                  — fetch quiz + questions (no answers leaked)
 *  2. POST /quizzes/{id}/attempts         — start an attempt, returns attempt_id
 *  3. POST /quizzes/attempts/{id}/answer  — record one answer
 *  4. POST /quizzes/attempts/{id}/submit  — finalise + auto-grade + return result
 */
final class QuizController
{
    public function show(Request $req): never
    {
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ? AND is_published = 1', [$req->params['id']]);
        if (!$quiz) Response::json(['error' => 'not_found'], 404);

        $questions = Database::all(
            'SELECT id, question_type, question_text, image_url, points, options_json, order_index
             FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC',
            [$quiz['id']],
        );
        // Strip is_correct flags before sending to the client.
        foreach ($questions as &$q) {
            if (!empty($q['options_json'])) {
                $opts = json_decode((string) $q['options_json'], true) ?: [];
                $q['options'] = array_map(fn ($o) => ['text' => $o['text']], $opts);
                unset($q['options_json']);
            }
        }
        unset($q);

        if ((int) $quiz['shuffle_questions']) shuffle($questions);

        Response::json([
            'quiz' => [
                'id'                 => $quiz['id'],
                'title'              => $quiz['title'],
                'description'        => $quiz['description'],
                'instructions'       => $quiz['instructions'],
                'pass_score_pct'     => (int) $quiz['pass_score_pct'],
                'time_limit_minutes' => (int) $quiz['time_limit_minutes'],
                'max_attempts'       => (int) $quiz['max_attempts'],
            ],
            'questions' => $questions,
        ]);
    }

    public function startAttempt(Request $req): never
    {
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ? AND is_published = 1', [$req->params['id']]);
        if (!$quiz) Response::json(['error' => 'not_found'], 404);

        $user = $req->params['user'];
        if ((int) $quiz['max_attempts'] > 0) {
            $existing = (int) Database::scalar(
                'SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ?',
                [$quiz['id'], $user['id']],
            );
            if ($existing >= (int) $quiz['max_attempts']) {
                Response::json(['error' => 'max_attempts_reached'], 400);
            }
        }

        $totalPoints = (int) (Database::scalar(
            'SELECT COALESCE(SUM(points), 0) FROM quiz_questions WHERE quiz_id = ?',
            [$quiz['id']],
        ) ?? 0);

        Database::exec(
            'INSERT INTO quiz_attempts (quiz_id, user_id, points_total, status) VALUES (?, ?, ?, ?)',
            [$quiz['id'], $user['id'], $totalPoints, 'IN_PROGRESS'],
        );
        $attemptId = (int) Database::pdo()->lastInsertId();
        Response::json(['attempt_id' => $attemptId]);
    }

    public function answer(Request $req): never
    {
        $attempt = Database::one('SELECT * FROM quiz_attempts WHERE id = ?', [(int) $req->params['id']]);
        if (!$attempt || $attempt['user_id'] !== $req->params['user']['id']) {
            Response::json(['error' => 'not_found'], 404);
        }
        if ($attempt['status'] !== 'IN_PROGRESS') {
            Response::json(['error' => 'attempt_closed'], 400);
        }

        $questionId = (int) $req->input('question_id', 0);
        $question = Database::one('SELECT * FROM quiz_questions WHERE id = ? AND quiz_id = ?', [$questionId, $attempt['quiz_id']]);
        if (!$question) Response::json(['error' => 'question_not_found'], 404);

        $answerText = $req->input('answer_text');
        $selected   = (array) ($req->input('selected_options') ?? []);
        [$isCorrect, $earned] = $this->grade($question, $answerText, $selected);

        // Upsert (one answer per question per attempt).
        $existing = Database::one(
            'SELECT id FROM quiz_answers WHERE attempt_id = ? AND question_id = ?',
            [(int) $attempt['id'], $questionId],
        );
        if ($existing) {
            Database::exec(
                'UPDATE quiz_answers
                 SET answer_text = ?, selected_options_json = ?, is_correct = ?, points_earned = ?, answered_at = NOW()
                 WHERE id = ?',
                [
                    $answerText !== null ? (string) $answerText : null,
                    json_encode($selected),
                    $isCorrect ? 1 : 0, $earned, (int) $existing['id'],
                ],
            );
        } else {
            Database::exec(
                'INSERT INTO quiz_answers
                    (attempt_id, question_id, answer_text, selected_options_json, is_correct, points_earned)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    (int) $attempt['id'], $questionId,
                    $answerText !== null ? (string) $answerText : null,
                    json_encode($selected),
                    $isCorrect ? 1 : 0, $earned,
                ],
            );
        }
        Response::json(['ok' => true]);
    }

    public function submit(Request $req): never
    {
        $attempt = Database::one('SELECT * FROM quiz_attempts WHERE id = ?', [(int) $req->params['id']]);
        if (!$attempt || $attempt['user_id'] !== $req->params['user']['id']) {
            Response::json(['error' => 'not_found'], 404);
        }
        if ($attempt['status'] !== 'IN_PROGRESS') {
            Response::json(['error' => 'attempt_closed'], 400);
        }
        $quiz = Database::one('SELECT * FROM quizzes WHERE id = ?', [$attempt['quiz_id']]);

        $earned = (int) (Database::scalar(
            'SELECT COALESCE(SUM(points_earned), 0) FROM quiz_answers WHERE attempt_id = ?',
            [(int) $attempt['id']],
        ) ?? 0);
        $total  = max(1, (int) $attempt['points_total']);
        $pct    = round(($earned / $total) * 100, 2);
        $passed = $pct >= (float) $quiz['pass_score_pct'] ? 1 : 0;
        $duration = max(0, time() - strtotime((string) $attempt['started_at']));

        Database::exec(
            'UPDATE quiz_attempts
             SET status = ?, submitted_at = NOW(), score_pct = ?, points_earned = ?,
                 passed = ?, duration_seconds = ?
             WHERE id = ?',
            ['SUBMITTED', $pct, $earned, $passed, $duration, (int) $attempt['id']],
        );

        $payload = [
            'attempt_id'    => (int) $attempt['id'],
            'score_pct'     => $pct,
            'points_earned' => $earned,
            'points_total'  => $total,
            'passed'        => (bool) $passed,
            'pass_score_pct'=> (int) $quiz['pass_score_pct'],
        ];
        if ((int) $quiz['show_correct_answers']) {
            $payload['answers'] = Database::all(
                'SELECT a.question_id, a.is_correct, a.points_earned, q.explanation, q.options_json, q.correct_answer_text
                 FROM quiz_answers a INNER JOIN quiz_questions q ON q.id = a.question_id
                 WHERE a.attempt_id = ?',
                [(int) $attempt['id']],
            );
        }
        Response::json($payload);
    }

    /** @return array{0:bool,1:int} [isCorrect, pointsEarned] */
    private function grade(array $question, $answerText, array $selected): array
    {
        $type = (string) $question['question_type'];
        $pts  = (int) $question['points'];

        if ($type === 'SHORT' || $type === 'FILL') {
            $expected = strtolower(trim((string) ($question['correct_answer_text'] ?? '')));
            $given    = strtolower(trim((string) $answerText));
            return $expected !== '' && $expected === $given ? [true, $pts] : [false, 0];
        }
        if ($type === 'TRUE_FALSE' || $type === 'MCQ' || $type === 'MULTI') {
            $opts = json_decode((string) ($question['options_json'] ?? '[]'), true) ?: [];
            $correctIdx = [];
            foreach ($opts as $i => $o) if (!empty($o['is_correct'])) $correctIdx[] = $i;
            sort($correctIdx);
            $sel = array_map('intval', $selected); sort($sel);
            return $sel === $correctIdx ? [true, $pts] : [false, 0];
        }
        return [false, 0];
    }
}
