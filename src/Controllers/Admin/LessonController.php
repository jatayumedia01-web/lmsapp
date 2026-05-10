<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;
use Devithor\Video;
use Devithor\View;

final class LessonController
{
    public function index(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();
        $lessons = Database::all(
            'SELECT * FROM lessons WHERE course_id = ? ORDER BY order_index ASC',
            [$course['id']],
        );
        Response::html(View::render('admin/lessons/index', [
            'course'  => $course,
            'lessons' => $lessons,
            'me'      => $req->params['user'],
            'page'    => 'courses',
            'flash'   => $this->popFlash(),
        ]));
    }

    public function showCreate(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();
        $nextOrder = (int) Database::scalar(
            'SELECT COALESCE(MAX(order_index), -1) + 1 FROM lessons WHERE course_id = ?',
            [$course['id']],
        );
        Response::html(View::render('admin/lessons/edit', [
            'course' => $course,
            'lesson' => $this->blankLesson($nextOrder),
            'mode'   => 'create',
            'me'     => $req->params['user'],
            'page'   => 'courses',
            'errors' => [],
        ]));
    }

    public function create(Request $req): never
    {
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$req->params['courseId']]);
        if (!$course) Response::notFound();

        $data = $this->assembleFromRequest($req, $course['id']);
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            Response::html(View::render('admin/lessons/edit', [
                'course' => $course,
                'lesson' => $data,
                'mode'   => 'create',
                'me'     => $req->params['user'],
                'page'   => 'courses',
                'errors' => $errors,
            ]));
        }

        $id = $data['id'] !== '' ? $data['id'] : ($course['id'] . '_l' . ($data['order_index'] + 1));
        $video = Video::detect($data['video_url']);
        Database::exec(
            'INSERT INTO lessons
             (id, course_id, title, order_index, duration_seconds, video_url,
              video_provider, video_id, thumbnail_url, subtitles_url, chapters_json,
              is_downloadable, allow_speed, watermark_enabled,
              description, is_free_preview)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $course['id'], $data['title'],
                (int) $data['order_index'], (int) $data['duration_seconds'],
                $video['normalized_url'] ?: $data['video_url'],
                $video['provider'], $video['id'],
                $data['thumbnail_url'] ?: $video['thumbnail_url'],
                $data['subtitles_url'] ?: null,
                $this->normaliseChapters((string) ($data['chapters_json'] ?? '')),
                (int) $data['is_downloadable'],
                (int) $data['allow_speed'],
                (int) $data['watermark_enabled'],
                $data['description'],
                (int) $data['is_free_preview'],
            ],
        );
        // Recompute the course's `total_lessons` so the catalog stays accurate.
        $this->recountCourseLessons($course['id']);
        $this->setFlash('Lesson added.', 'success');
        Response::redirect('/admin/courses/' . $course['id'] . '/lessons');
    }

    public function showEdit(Request $req): never
    {
        $lesson = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$lesson) Response::notFound();
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$lesson['course_id']]);
        $faqs = Database::all(
            'SELECT * FROM lesson_faqs WHERE lesson_id = ? ORDER BY order_index ASC, id ASC',
            [$lesson['id']],
        );
        Response::html(View::render('admin/lessons/edit', [
            'course' => $course,
            'lesson' => $lesson,
            'faqs'   => $faqs,
            'mode'   => 'edit',
            'me'     => $req->params['user'],
            'page'   => 'courses',
            'errors' => [],
            'flash'  => $this->popFlash(),
        ]));
    }

    public function update(Request $req): never
    {
        $existing = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();

        $data = $this->assembleFromRequest($req, $existing['course_id']);
        $errors = Validator::check($data, $this->rules());
        if ($errors) {
            $course = Database::one('SELECT * FROM courses WHERE id = ?', [$existing['course_id']]);
            Response::html(View::render('admin/lessons/edit', [
                'course' => $course,
                'lesson' => array_merge($existing, $data),
                'mode'   => 'edit',
                'me'     => $req->params['user'],
                'page'   => 'courses',
                'errors' => $errors,
            ]));
        }

        $video = Video::detect($data['video_url']);
        Database::exec(
            'UPDATE lessons SET
                title = ?, order_index = ?, duration_seconds = ?, video_url = ?,
                video_provider = ?, video_id = ?, thumbnail_url = ?, subtitles_url = ?,
                chapters_json = ?, is_downloadable = ?, allow_speed = ?, watermark_enabled = ?,
                description = ?, is_free_preview = ?
             WHERE id = ?',
            [
                $data['title'], (int) $data['order_index'], (int) $data['duration_seconds'],
                $video['normalized_url'] ?: $data['video_url'],
                $video['provider'], $video['id'],
                $data['thumbnail_url'] ?: $video['thumbnail_url'],
                $data['subtitles_url'] ?: null,
                $this->normaliseChapters((string) ($data['chapters_json'] ?? '')),
                (int) $data['is_downloadable'],
                (int) $data['allow_speed'],
                (int) $data['watermark_enabled'],
                $data['description'],
                (int) $data['is_free_preview'],
                $existing['id'],
            ],
        );
        $this->setFlash('Lesson updated.', 'success');
        Response::redirect('/admin/courses/' . $existing['course_id'] . '/lessons');
    }

    public function delete(Request $req): never
    {
        $existing = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();
        Database::exec('DELETE FROM lessons WHERE id = ?', [$existing['id']]);
        $this->recountCourseLessons($existing['course_id']);
        $this->setFlash('Lesson deleted.', 'success');
        Response::redirect('/admin/courses/' . $existing['course_id'] . '/lessons');
    }

    /** Per-lesson video page — preview embed + drop-off chart + recent views. */
    public function videoPage(Request $req): never
    {
        $lesson = Database::one('SELECT * FROM lessons WHERE id = ?', [$req->params['id']]);
        if (!$lesson) Response::notFound();
        $course = Database::one('SELECT * FROM courses WHERE id = ?', [$lesson['course_id']]);

        $stats = [
            'plays'        => (int) Database::scalar('SELECT COUNT(*) FROM video_views WHERE lesson_id = ?', [$lesson['id']]),
            'unique_users' => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM video_views WHERE lesson_id = ?', [$lesson['id']]),
            'completed'    => (int) Database::scalar('SELECT COUNT(*) FROM video_views WHERE lesson_id = ? AND completed = 1', [$lesson['id']]),
            'avg_pct'      => (int) (Database::scalar('SELECT COALESCE(AVG(progress_pct), 0) FROM video_views WHERE lesson_id = ?', [$lesson['id']]) ?? 0),
            'total_seconds'=> (int) (Database::scalar('SELECT COALESCE(SUM(watch_seconds), 0) FROM video_views WHERE lesson_id = ?', [$lesson['id']]) ?? 0),
        ];

        $segments = Database::all(
            'SELECT bucket, views FROM video_segments WHERE lesson_id = ? ORDER BY bucket ASC',
            [$lesson['id']],
        );
        $segmentArray = array_fill(0, 20, 0);
        foreach ($segments as $s) {
            $idx = (int) $s['bucket'];
            if ($idx >= 0 && $idx < 20) $segmentArray[$idx] = (int) $s['views'];
        }

        $recentViews = Database::all(
            'SELECT v.*, u.full_name, u.email
             FROM video_views v LEFT JOIN users u ON u.id = v.user_id
             WHERE v.lesson_id = ?
             ORDER BY v.started_at DESC LIMIT 20',
            [$lesson['id']],
        );

        Response::html(View::render('admin/lessons/video', [
            'lesson'      => $lesson,
            'course'      => $course,
            'stats'       => $stats,
            'segments'    => $segmentArray,
            'recentViews' => $recentViews,
            'embed'       => Video::buildEmbed((string) $lesson['video_provider'], (string) $lesson['video_id']),
            'me'          => $req->params['user'],
            'page'        => 'courses',
            'flash'       => $this->popFlash(),
        ]));
    }

    /**
     * JSON endpoint for the live "paste a URL" preview in the lesson editor.
     * Returns provider + id + thumbnail (and YouTube oEmbed title where free).
     */
    public function videoDetect(Request $req): never
    {
        $url = trim((string) $req->input('url', ''));
        if ($url === '') {
            Response::json(['provider' => 'OTHER', 'id' => '', 'thumbnail_url' => null]);
        }
        $det = Video::detect($url);
        if ($det['provider'] === Video::PROVIDER_YOUTUBE) {
            $oembed = Video::youtubeOembed($det['id']);
            if (!empty($oembed['title']))         $det['title']         = $oembed['title'];
            if (!empty($oembed['thumbnail_url'])) $det['thumbnail_url'] = $oembed['thumbnail_url'];
            if (!empty($oembed['author_name']))   $det['author_name']   = $oembed['author_name'];
        }
        Response::json($det);
    }

    // ---- helpers --------------------------------------------------------

    private function rules(): array
    {
        return [
            'title'            => ['required', 'min:2', 'max:255'],
            'description'      => ['required', 'min:5'],
            'video_url'        => ['required', 'url', 'max:500'],
            'order_index'      => ['int'],
            'duration_seconds' => ['int'],
        ];
    }

    private function blankLesson(int $order): array
    {
        return [
            'id' => '',
            'title' => '',
            'order_index' => $order,
            'duration_seconds' => 600,
            'video_url' => '',
            'video_provider' => 'OTHER',
            'video_id' => '',
            'thumbnail_url' => '',
            'subtitles_url' => '',
            'chapters_json' => '',
            'is_downloadable' => 0,
            'allow_speed' => 1,
            'watermark_enabled' => 0,
            'description' => '',
            'is_free_preview' => $order === 0 ? 1 : 0,
        ];
    }

    private function assembleFromRequest(Request $req, string $courseId): array
    {
        return [
            'id'                => trim((string) $req->input('id', '')),
            'course_id'         => $courseId,
            'title'             => trim((string) $req->input('title', '')),
            'order_index'       => (int) $req->input('order_index', 0),
            'duration_seconds'  => (int) $req->input('duration_seconds', 600),
            'video_url'         => trim((string) $req->input('video_url', '')),
            'thumbnail_url'     => trim((string) $req->input('thumbnail_url', '')),
            'subtitles_url'     => trim((string) $req->input('subtitles_url', '')),
            'chapters_json'     => trim((string) $req->input('chapters_json', '')),
            'is_downloadable'   => $req->input('is_downloadable')   ? 1 : 0,
            'allow_speed'       => $req->input('allow_speed')       ? 1 : 0,
            'watermark_enabled' => $req->input('watermark_enabled') ? 1 : 0,
            'description'       => trim((string) $req->input('description', '')),
            'is_free_preview'   => $req->input('is_free_preview')   ? 1 : 0,
        ];
    }

    /**
     * Convert "120 Intro\n360 Demo" textarea input to a normalised JSON array
     * of { time:int, title:string }. Returns null if input is empty.
     */
    private function normaliseChapters(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        // If it's already JSON, validate and re-encode.
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return json_encode($decoded);
        $out = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $m)) {
                $out[] = ['time' => (int) $m[1], 'title' => $m[2]];
            }
        }
        return $out ? json_encode($out) : null;
    }

    private function recountCourseLessons(string $courseId): void
    {
        Database::exec(
            'UPDATE courses SET
                total_lessons   = (SELECT COUNT(*) FROM lessons WHERE course_id = ?),
                duration_minutes = (SELECT COALESCE(SUM(duration_seconds), 0) DIV 60 FROM lessons WHERE course_id = ?)
             WHERE id = ?',
            [$courseId, $courseId, $courseId],
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
