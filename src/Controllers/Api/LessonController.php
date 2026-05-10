<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Video;

final class LessonController
{
    public function forCourse(Request $req): never
    {
        $rows = Database::all(
            'SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC',
            [$req->params['id']],
        );
        Response::json([
            'lessons' => array_map([$this, 'shape'], $rows),
        ]);
    }

    public function show(Request $req): never
    {
        $row = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$row) Response::json(['error' => 'not_found'], 404);
        Response::json(['lesson' => $this->shape($row)]);
    }

    /**
     * Issue a short-lived signed playback URL. The Android player should call
     * this just before playing — the token expires in 1 hour. Free-preview
     * lessons return immediately; premium lessons (TODO) check subscription.
     */
    public function playback(Request $req): never
    {
        $lesson = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$lesson) Response::json(['error' => 'not_found'], 404);

        $user  = $req->params['user'];
        $token = Video::signPlaybackToken($lesson['id'], $user['id']);

        Response::json([
            'lesson_id'      => $lesson['id'],
            'provider'       => $lesson['video_provider'],
            'video_id'       => $lesson['video_id'],
            'video_url'      => $lesson['video_url'],
            'embed_url'      => Video::buildEmbed((string) $lesson['video_provider'], (string) $lesson['video_id']),
            'thumbnail_url'  => $lesson['thumbnail_url'],
            'subtitles_url'  => $lesson['subtitles_url'],
            'chapters_json'  => $lesson['chapters_json'],
            'is_downloadable'=> (bool) $lesson['is_downloadable'],
            'allow_speed'    => (bool) $lesson['allow_speed'],
            'watermark'      => ((int) $lesson['watermark_enabled']) ? $user['email'] : null,
            'token'          => $token,
            'expires_at'     => date('c', time() + 3600),
        ]);
    }

    /**
     * Track a playback heartbeat from the app — upserts video_views and
     * increments segment counters. Apps should call this every ~10 seconds
     * (or on pause / seek / end) so the drop-off chart stays accurate.
     */
    public function trackPlayback(Request $req): never
    {
        $lesson = Database::one('SELECT id, course_id FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$lesson) Response::json(['error' => 'not_found'], 404);

        $user      = $req->params['user'];
        $sessionId = (string) ($req->input('session_id') ?? ('vp_' . bin2hex(random_bytes(8))));
        $watch     = max(0, (int) ($req->input('watch_seconds')  ?? 0));
        $progress  = max(0, min(100, (int) ($req->input('progress_pct') ?? 0)));
        $completed = $req->input('completed') ? 1 : 0;
        $speed     = max(0.25, min(4.0, (float) ($req->input('speed') ?? 1.0)));
        $quality   = $req->input('quality') !== null ? substr((string) $req->input('quality'), 0, 20) : null;

        $existing = Database::one(
            'SELECT id FROM video_views WHERE session_id = ? AND lesson_id = ? AND user_id = ?',
            [$sessionId, $lesson['id'], $user['id']],
        );

        if ($existing) {
            Database::exec(
                'UPDATE video_views SET
                    watch_seconds = GREATEST(watch_seconds, ?),
                    progress_pct  = GREATEST(progress_pct, ?),
                    completed     = GREATEST(completed, ?),
                    speed         = ?,
                    quality       = COALESCE(?, quality),
                    ended_at      = IF(? = 1, NOW(), ended_at)
                 WHERE id = ?',
                [$watch, $progress, $completed, $speed, $quality, $completed, $existing['id']],
            );
        } else {
            Database::exec(
                'INSERT INTO video_views
                    (user_id, lesson_id, course_id, session_id, watch_seconds,
                     progress_pct, completed, speed, quality, ended_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL))',
                [
                    $user['id'], $lesson['id'], $lesson['course_id'], $sessionId,
                    $watch, $progress, $completed, $speed, $quality, $completed,
                ],
            );
        }

        // Bump the segment bucket the viewer has reached. Counts every
        // heartbeat; over many viewers this gives a relative drop-off shape.
        $bucket = (int) floor($progress / 5);
        if ($bucket >= 0 && $bucket < 20) {
            Database::exec(
                'INSERT INTO video_segments (lesson_id, bucket, views)
                 VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE views = views + 1',
                [$lesson['id'], $bucket],
            );
        }

        Response::json(['ok' => true, 'session_id' => $sessionId]);
    }

    private function shape(array $r): array
    {
        return [
            'id'                 => $r['id'],
            'course_id'          => $r['course_id'],
            'title'              => $r['title'],
            'order_index'        => (int) $r['order_index'],
            'duration_seconds'   => (int) $r['duration_seconds'],
            'video_url'          => $r['video_url'],
            'video_provider'     => $r['video_provider']  ?? 'OTHER',
            'video_id'           => $r['video_id']        ?? '',
            'thumbnail_url'      => $r['thumbnail_url']   ?? null,
            'subtitles_url'      => $r['subtitles_url']   ?? null,
            'chapters_json'      => $r['chapters_json']   ?? null,
            'is_downloadable'    => (bool) ($r['is_downloadable'] ?? 0),
            'allow_speed'        => (bool) ($r['allow_speed']     ?? 1),
            'description'        => $r['description'],
            'is_free_preview'    => (bool) $r['is_free_preview'],
        ];
    }
}
