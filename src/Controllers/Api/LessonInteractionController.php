<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

final class LessonInteractionController
{
    // ── Feedback ──────────────────────────────────────────────────────────────

    public function submitFeedback(Request $req): never
    {
        $user     = $req->params['user'];
        $lessonId = $req->params['id'];
        $helpful  = $req->input('helpful');   // true | false | null
        $comment  = trim((string) ($req->input('comment') ?? '')) ?: null;

        if ($helpful !== null) $helpful = filter_var($helpful, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $existing = Database::one(
            'SELECT helpful, comment FROM lesson_feedback WHERE user_id = ? AND lesson_id = ?',
            [$user['id'], $lessonId],
        );

        if ($existing) {
            $newHelpful = $helpful !== null ? $helpful : $existing['helpful'];
            $newComment = $comment !== null ? $comment : $existing['comment'];
            Database::exec(
                'UPDATE lesson_feedback SET helpful = ?, comment = ?, updated_at = NOW() WHERE user_id = ? AND lesson_id = ?',
                [$newHelpful, $newComment, $user['id'], $lessonId],
            );
        } else {
            Database::exec(
                'INSERT INTO lesson_feedback (user_id, lesson_id, helpful, comment, updated_at) VALUES (?,?,?,?,NOW())',
                [$user['id'], $lessonId, $helpful, $comment],
            );
        }

        Response::json(['ok' => true]);
    }

    // ── Q&A — Questions ───────────────────────────────────────────────────────

    public function listQuestions(Request $req): never
    {
        $user     = $req->params['user'];
        $lessonId = $req->params['id'];

        $questions = Database::all(
            'SELECT q.id, q.author_id, q.author_name, q.body,
                    q.like_count, q.dislike_count, q.answer_count,
                    q.is_resolved, q.is_pinned, q.created_at,
                    v.value AS my_vote
             FROM lesson_questions q
             LEFT JOIN votes v ON v.target_id = q.id AND v.target_type = "QUESTION" AND v.user_id = ?
             WHERE q.lesson_id = ?
               AND q.moderation_status IN ("APPROVED", "PENDING")
             ORDER BY q.is_pinned DESC, q.like_count DESC, q.created_at DESC
             LIMIT 50',
            [$user['id'], $lessonId],
        );

        foreach ($questions as &$q) {
            $q['answers'] = Database::all(
                'SELECT a.id, a.author_id, a.author_name, a.body,
                        a.like_count, a.dislike_count, a.is_instructor, a.created_at,
                        v.value AS my_vote
                 FROM lesson_answers a
                 LEFT JOIN votes v ON v.target_id = a.id AND v.target_type = "ANSWER" AND v.user_id = ?
                 WHERE a.question_id = ?
                 ORDER BY a.is_instructor DESC, a.like_count DESC, a.created_at ASC
                 LIMIT 20',
                [$user['id'], $q['id']],
            );
            $q['is_mine'] = $q['author_id'] === $user['id'];
        }
        unset($q);

        Response::json(['questions' => $questions]);
    }

    public function postQuestion(Request $req): never
    {
        $user     = $req->params['user'];
        $lessonId = $req->params['id'];
        $body     = trim((string) ($req->input('body') ?? ''));

        if (strlen($body) < 5) Response::json(['error' => 'Question too short'], 422);

        $lesson = Database::one('SELECT course_id FROM lessons WHERE id = ?', [$lessonId]);
        if (!$lesson) Response::json(['error' => 'Lesson not found'], 404);

        $id = 'q_' . bin2hex(random_bytes(8));
        Database::exec(
            'INSERT INTO lesson_questions (id, lesson_id, course_id, author_id, author_name, body, moderation_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, "PENDING", NOW())',
            [$id, $lessonId, $lesson['course_id'], $user['id'], $user['full_name'], $body],
        );

        Response::json(['question_id' => $id], 201);
    }

    // ── Q&A — Answers ─────────────────────────────────────────────────────────

    public function postAnswer(Request $req): never
    {
        $user       = $req->params['user'];
        $questionId = $req->params['id'];
        $body       = trim((string) ($req->input('body') ?? ''));

        if (strlen($body) < 2) Response::json(['error' => 'Answer too short'], 422);

        $question = Database::one('SELECT id FROM lesson_questions WHERE id = ?', [$questionId]);
        if (!$question) Response::json(['error' => 'Question not found'], 404);

        $id = 'a_' . bin2hex(random_bytes(8));
        Database::exec(
            'INSERT INTO lesson_answers (id, question_id, author_id, author_name, body, is_instructor, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$id, $questionId, $user['id'], $user['full_name'], $body, $user['role'] === 'ADMIN' ? 1 : 0],
        );
        Database::exec('UPDATE lesson_questions SET answer_count = answer_count + 1 WHERE id = ?', [$questionId]);

        Response::json(['answer_id' => $id], 201);
    }

    // ── Votes ─────────────────────────────────────────────────────────────────

    public function voteQuestion(Request $req): never
    {
        $this->handleVote($req, $req->params['id'], 'QUESTION', 'lesson_questions');
    }

    public function voteAnswer(Request $req): never
    {
        $this->handleVote($req, $req->params['id'], 'ANSWER', 'lesson_answers');
    }

    private function handleVote(Request $req, string $targetId, string $targetType, string $table): never
    {
        $user  = $req->params['user'];
        $value = strtoupper(trim((string) ($req->input('value') ?? 'NONE')));

        if (!in_array($value, ['UP', 'DOWN', 'NONE'], true)) {
            Response::json(['error' => 'Invalid vote value'], 422);
        }

        $existing = Database::one(
            'SELECT value FROM votes WHERE user_id = ? AND target_id = ? AND target_type = ?',
            [$user['id'], $targetId, $targetType],
        );
        $prev = $existing['value'] ?? 'NONE';

        if ($prev === $value) Response::json(['ok' => true]);

        // Adjust counters
        if ($prev === 'UP')   Database::exec("UPDATE $table SET like_count    = GREATEST(like_count - 1, 0)    WHERE id = ?", [$targetId]);
        if ($prev === 'DOWN') Database::exec("UPDATE $table SET dislike_count = GREATEST(dislike_count - 1, 0) WHERE id = ?", [$targetId]);
        if ($value === 'UP')   Database::exec("UPDATE $table SET like_count    = like_count + 1    WHERE id = ?", [$targetId]);
        if ($value === 'DOWN') Database::exec("UPDATE $table SET dislike_count = dislike_count + 1 WHERE id = ?", [$targetId]);

        if ($value === 'NONE') {
            Database::exec(
                'DELETE FROM votes WHERE user_id = ? AND target_id = ? AND target_type = ?',
                [$user['id'], $targetId, $targetType],
            );
        } else {
            Database::exec(
                'INSERT INTO votes (user_id, target_id, target_type, value, created_at)
                 VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE value = ?, created_at = NOW()',
                [$user['id'], $targetId, $targetType, $value, $value],
            );
        }

        Response::json(['ok' => true]);
    }
}
