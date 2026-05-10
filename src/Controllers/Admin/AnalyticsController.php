<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Read-only analytics dashboards for ops/admin. Reads are split into two
 * tiers:
 *   - Hot panels (today's DAU, last-hour events) hit the raw events table.
 *   - Trend panels (30-day chart) hit the analytics_daily aggregate filled
 *     by `php seeds/aggregate.php` (run on a daily cron).
 *
 * The aggregate stays empty until the cron runs at least once — every chart
 * here renders gracefully against an empty aggregate by reading raw data
 * for the rendered window.
 */
final class AnalyticsController
{
    public function overview(Request $req): never
    {
        $rangeDays = max(1, min(365, (int) $req->input('days', 30)));
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekAgo   = date('Y-m-d', strtotime('-7 days'));
        $monthAgo  = date('Y-m-d', strtotime("-$rangeDays days"));
        $prevMonth = date('Y-m-d', strtotime('-' . ($rangeDays * 2) . ' days'));

        // ---- Hero stats with prior-window deltas -----------------------
        $thisWindow = [
            'dau'      => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ?', [$today . ' 00:00:00']),
            'mau'      => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ?', [$monthAgo . ' 00:00:00']),
            'sessions' => (int) Database::scalar('SELECT COUNT(*) FROM user_sessions WHERE started_at >= ?', [$weekAgo . ' 00:00:00']),
            'revenue'  => (int) (Database::scalar('SELECT COALESCE(SUM(amount_cents), 0) FROM invoices WHERE status = "PAID" AND date_millis >= UNIX_TIMESTAMP(?) * 1000', [$monthAgo . ' 00:00:00']) ?? 0),
            'subs'     => (int) Database::scalar('SELECT COUNT(*) FROM subscriptions WHERE status IN ("ACTIVE", "TRIALING")'),
            'events'   => (int) Database::scalar('SELECT COUNT(*) FROM user_events WHERE occurred_at >= ?', [$today . ' 00:00:00']),
        ];
        $prevWindow = [
            'dau'      => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ? AND occurred_at < ?', [$yesterday . ' 00:00:00', $today . ' 00:00:00']),
            'mau'      => (int) Database::scalar('SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ? AND occurred_at < ?', [$prevMonth . ' 00:00:00', $monthAgo . ' 00:00:00']),
            'sessions' => (int) Database::scalar('SELECT COUNT(*) FROM user_sessions WHERE started_at >= ? AND started_at < ?', [date('Y-m-d', strtotime('-14 days')) . ' 00:00:00', $weekAgo . ' 00:00:00']),
            'revenue'  => (int) (Database::scalar('SELECT COALESCE(SUM(amount_cents), 0) FROM invoices WHERE status = "PAID" AND date_millis >= UNIX_TIMESTAMP(?) * 1000 AND date_millis < UNIX_TIMESTAMP(?) * 1000', [$prevMonth . ' 00:00:00', $monthAgo . ' 00:00:00']) ?? 0),
        ];

        $deltas = [
            'dau'      => $this->pctDelta($thisWindow['dau'],      $prevWindow['dau']),
            'mau'      => $this->pctDelta($thisWindow['mau'],      $prevWindow['mau']),
            'sessions' => $this->pctDelta($thisWindow['sessions'], $prevWindow['sessions']),
            'revenue'  => $this->pctDelta($thisWindow['revenue'],  $prevWindow['revenue']),
        ];

        // ---- N-day trend (DAU + sessions for stacked area) ------------
        $trend = Database::all(
            "SELECT `date`, dau, sessions_count, events_count
             FROM analytics_daily
             WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             ORDER BY `date` ASC",
            [$rangeDays],
        );
        if (empty($trend)) {
            $trend = Database::all(
                "SELECT DATE(occurred_at) AS `date`,
                        COUNT(DISTINCT user_id) AS dau,
                        0 AS sessions_count,
                        COUNT(*) AS events_count
                 FROM user_events
                 WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY DATE(occurred_at)
                 ORDER BY `date` ASC",
                [$rangeDays],
            );
        }
        $trend = $this->padDailyZeros($trend, $rangeDays);

        // ---- Live online: users active in last 5 minutes ---------------
        $onlineNow = (int) Database::scalar(
            'SELECT COUNT(DISTINCT user_id) FROM user_events
             WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
        );

        // ---- GitHub-style daily activity heatmap (12 weeks = 84 days) --
        $heatmapDays = 84;
        $heatRows = Database::all(
            'SELECT DATE(occurred_at) AS d, COUNT(*) AS c
             FROM user_events
             WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(occurred_at)',
            [$heatmapDays],
        );
        $heatMap = [];
        foreach ($heatRows as $r) $heatMap[$r['d']] = (int) $r['c'];
        $heatmap = [];
        for ($i = $heatmapDays - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $heatmap[] = ['date' => $date, 'count' => $heatMap[$date] ?? 0, 'dow' => (int) date('w', strtotime($date))];
        }

        // ---- Anomaly detection: failed logins or DAU spike/drop --------
        $anomalies = $this->detectAnomalies($trend);

        // ---- Donut data: platform breakdown ----------------------------
        $platforms = Database::all(
            'SELECT platform, COUNT(*) AS c FROM user_devices GROUP BY platform ORDER BY c DESC',
        );

        // ---- Geography: top countries with %share ----------------------
        $countries = Database::all(
            'SELECT country_code, country, COUNT(DISTINCT user_id) AS users_count
             FROM user_sessions
             WHERE country_code IS NOT NULL AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY country_code, country
             ORDER BY users_count DESC LIMIT 6',
        );

        // ---- Recent activity (latest events feed) ----------------------
        $recentActivity = Database::all(
            'SELECT e.event_name, e.screen, e.occurred_at,
                    u.id AS user_id, u.full_name, u.email,
                    c.title AS course_title, l.title AS lesson_title
             FROM user_events e
             LEFT JOIN users   u ON u.id = e.user_id
             LEFT JOIN courses c ON c.id = e.course_id
             LEFT JOIN lessons l ON l.id = e.lesson_id
             ORDER BY e.occurred_at DESC LIMIT 12',
        );

        // ---- Top events ------------------------------------------------
        $topEvents = Database::all(
            'SELECT event_name, COUNT(*) AS c
             FROM user_events
             WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY event_name ORDER BY c DESC LIMIT 6',
        );

        // ---- Latest enrollments (the "recent orders" panel) ------------
        $recentEnrollments = Database::all(
            'SELECT e.*, u.full_name, u.email, c.title AS course_title, c.cover_color_hex
             FROM enrollments e
             LEFT JOIN users   u ON u.id = e.user_id
             LEFT JOIN courses c ON c.id = e.course_id
             ORDER BY e.enrolled_at DESC LIMIT 6',
        );

        $secondaryStats = [
            'avg_session' => (int) (Database::scalar('SELECT COALESCE(AVG(duration_seconds), 0) FROM user_sessions WHERE duration_seconds > 0 AND started_at >= ?', [$monthAgo . ' 00:00:00']) ?? 0),
            'failed_logins_24h' => (int) Database::scalar('SELECT COUNT(*) FROM user_login_history WHERE success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'),
            'devices_total' => (int) Database::scalar('SELECT COUNT(*) FROM user_devices'),
            'lessons_today' => (int) Database::scalar('SELECT COUNT(*) FROM user_events WHERE event_name = "lesson_started" AND occurred_at >= ?', [$today . ' 00:00:00']),
        ];

        Response::html(View::render('admin/analytics/overview', [
            'current'           => $thisWindow,
            'deltas'            => $deltas,
            'trend'             => $trend,
            'platforms'         => $platforms,
            'countries'         => $countries,
            'recentActivity'    => $recentActivity,
            'topEvents'         => $topEvents,
            'recentEnrollments' => $recentEnrollments,
            'secondary'         => $secondaryStats,
            'onlineNow'         => $onlineNow,
            'heatmap'           => $heatmap,
            'anomalies'         => $anomalies,
            'rangeDays'         => $rangeDays,
            'me'                => $req->params['user'],
            'page'              => 'analytics',
        ]));
    }

    /** JSON endpoint hit by the overview page's polling JS every 15s. */
    public function liveJson(Request $req): never
    {
        Response::json([
            'online_now' => (int) Database::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM user_events
                 WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
            ),
            'sessions_active' => (int) Database::scalar(
                'SELECT COUNT(*) FROM user_sessions
                 WHERE last_event_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND ended_at IS NULL',
            ),
            'events_per_minute' => (int) round(((int) Database::scalar(
                'SELECT COUNT(*) FROM user_events WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
            )) / 5),
            'as_of' => date('c'),
        ]);
    }

    /**
     * Engagement: hour-of-day x weekday heatmap, lesson funnel, leaderboard.
     */
    public function engagement(Request $req): never
    {
        // Hour x weekday heatmap (last 30 days). 7 rows (Mon..Sun) x 24 cols.
        $rows = Database::all(
            'SELECT DAYOFWEEK(occurred_at) AS dow,
                    HOUR(occurred_at) AS hour,
                    COUNT(*) AS c
             FROM user_events
             WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DAYOFWEEK(occurred_at), HOUR(occurred_at)',
        );
        // MySQL DAYOFWEEK is 1=Sun..7=Sat; remap to 0=Mon..6=Sun.
        $hourGrid = array_fill(0, 7, array_fill(0, 24, 0));
        $hourMax = 0;
        foreach ($rows as $r) {
            $dow = ((int) $r['dow'] + 5) % 7;
            $hr  = (int) $r['hour'];
            $hourGrid[$dow][$hr] = (int) $r['c'];
            if ((int) $r['c'] > $hourMax) $hourMax = (int) $r['c'];
        }

        // Lesson funnel: started -> 25% -> 50% -> 75% -> completed (last 30d).
        $funnel = $this->lessonFunnel(30);

        // Engagement leaderboard: top 20 users in last 30 days, scored by:
        //   sessions × 2  +  unique_days × 5  +  events / 10
        $leaderboard = Database::all(
            'SELECT u.id, u.full_name, u.email,
                    COUNT(DISTINCT s.id)                AS sessions_count,
                    COUNT(DISTINCT DATE(e.occurred_at)) AS active_days,
                    COUNT(e.id)                         AS events_count,
                    COALESCE(SUM(s.duration_seconds), 0) AS total_seconds
             FROM users u
             LEFT JOIN user_sessions s
                ON s.user_id = u.id AND s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             LEFT JOIN user_events e
                ON e.user_id = u.id AND e.occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE u.role = ?
             GROUP BY u.id, u.full_name, u.email
             HAVING COUNT(e.id) > 0
             ORDER BY (COUNT(DISTINCT s.id) * 2
                      + COUNT(DISTINCT DATE(e.occurred_at)) * 5
                      + COUNT(e.id) / 10) DESC
             LIMIT 20',
            ['STUDENT'],
        );

        // Per-event funnel: course_view -> course_enrolled -> lesson_started -> lesson_completed
        $journey = [];
        $journeySteps = ['course_view' => 'Viewed course', 'course_enrolled' => 'Enrolled', 'lesson_started' => 'Started lesson', 'lesson_completed' => 'Completed lesson'];
        foreach ($journeySteps as $name => $label) {
            $journey[] = [
                'name'  => $name,
                'label' => $label,
                'users' => (int) Database::scalar(
                    'SELECT COUNT(DISTINCT user_id) FROM user_events
                     WHERE event_name = ? AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                    [$name],
                ),
            ];
        }

        Response::html(View::render('admin/analytics/engagement', [
            'hourGrid'    => $hourGrid,
            'hourMax'     => $hourMax,
            'funnel'      => $funnel,
            'journey'     => $journey,
            'leaderboard' => $leaderboard,
            'me'          => $req->params['user'],
            'page'        => 'analytics',
        ]));
    }

    /**
     * Cohort retention table: rows = signup week, cols = D1, D7, D14, D30, D60.
     */
    public function cohorts(Request $req): never
    {
        $weeks = max(4, min(26, (int) $req->input('weeks', 12)));

        // Pull all users joined in the last N weeks.
        $cohortUsers = Database::all(
            'SELECT id, joined_at, DATE(joined_at) AS join_date,
                    YEARWEEK(joined_at, 3) AS week_key
             FROM users
             WHERE joined_at >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
               AND role = ?',
            [$weeks, 'STUDENT'],
        );

        // Group by week.
        $cohorts = [];
        foreach ($cohortUsers as $u) {
            $key = $u['week_key'];
            if (!isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'week_key' => $key,
                    'label'    => date('M j', strtotime((string) $u['join_date'])),
                    'users'    => [],
                ];
            }
            $cohorts[$key]['users'][] = $u;
        }
        krsort($cohorts);

        // For each cohort + each "day after signup" bucket, compute retention.
        $buckets = [1, 3, 7, 14, 30, 60];
        $rows = [];
        foreach ($cohorts as $cohort) {
            $size = count($cohort['users']);
            if ($size === 0) continue;
            $userIds = array_column($cohort['users'], 'id');
            $idsList = "'" . implode("','", array_map(fn ($id) => str_replace("'", "''", (string) $id), $userIds)) . "'";

            $row = [
                'label'    => $cohort['label'],
                'size'     => $size,
                'retention'=> [],
            ];
            foreach ($buckets as $day) {
                // For each user in cohort, did they have an event between joined_at + (day-1) and joined_at + day?
                $retained = (int) Database::scalar(
                    "SELECT COUNT(DISTINCT u.id)
                     FROM users u
                     INNER JOIN user_events e ON e.user_id = u.id
                     WHERE u.id IN ($idsList)
                       AND DATEDIFF(DATE(e.occurred_at), DATE(u.joined_at)) = ?",
                    [$day],
                );
                $row['retention'][$day] = [
                    'count' => $retained,
                    'pct'   => $size > 0 ? round(($retained / $size) * 100, 1) : 0,
                ];
            }
            $rows[] = $row;
        }

        Response::html(View::render('admin/analytics/cohorts', [
            'rows'    => $rows,
            'buckets' => $buckets,
            'weeks'   => $weeks,
            'me'      => $req->params['user'],
            'page'    => 'analytics',
        ]));
    }

    private function lessonFunnel(int $days): array
    {
        $stages = [
            ['key' => 'started',  'event' => 'lesson_started',   'label' => 'Started'],
            ['key' => 'p25',      'event' => 'lesson_progress_25', 'label' => '25% watched'],
            ['key' => 'p50',      'event' => 'lesson_progress_50', 'label' => '50% watched'],
            ['key' => 'p75',      'event' => 'lesson_progress_75', 'label' => '75% watched'],
            ['key' => 'completed','event' => 'lesson_completed', 'label' => 'Completed'],
        ];
        $base = (int) Database::scalar(
            'SELECT COUNT(DISTINCT user_id) FROM user_events
             WHERE event_name = ? AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            ['lesson_started', $days],
        );
        foreach ($stages as &$s) {
            $s['users'] = (int) Database::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM user_events
                 WHERE event_name = ? AND occurred_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
                [$s['event'], $days],
            );
            $s['pct'] = $base > 0 ? round(($s['users'] / $base) * 100, 1) : 0;
        }
        return $stages;
    }

    /**
     * Lightweight statistical anomaly detection: compare today vs avg of
     * prior 7 days, flag > 3x or < 0.3x as worth highlighting.
     */
    private function detectAnomalies(array $trend): array
    {
        $alerts = [];
        if (count($trend) < 8) return $alerts;
        $today = end($trend);
        $prior = array_slice($trend, -8, 7);
        $avg = array_sum(array_column($prior, 'dau')) / max(1, count($prior));
        if ($avg > 0) {
            if ((int) $today['dau'] >= $avg * 3) {
                $alerts[] = ['kind' => 'success', 'msg' => 'DAU spike: ' . (int) $today['dau'] . ' vs avg ' . round($avg, 1) . ' (3×+ normal)'];
            }
            if ((int) $today['dau'] <= $avg * 0.3 && $avg >= 5) {
                $alerts[] = ['kind' => 'warning', 'msg' => 'DAU drop: ' . (int) $today['dau'] . ' vs avg ' . round($avg, 1)];
            }
        }
        // Failed-login spike check
        $failed24h = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_login_history WHERE success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
        );
        $failedPrior = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_login_history WHERE success = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL 8 DAY)
             AND attempted_at <  DATE_SUB(NOW(), INTERVAL 1 DAY)',
        );
        $failedAvg = $failedPrior / 7;
        if ($failed24h >= 10 && $failed24h >= $failedAvg * 3) {
            $alerts[] = ['kind' => 'danger', 'msg' => "Failed-login spike: $failed24h in 24h vs 7d avg " . round($failedAvg, 1) . ' — check the login audit'];
        }
        return $alerts;
    }

    public function geography(Request $req): never
    {
        $days = max(1, min(365, (int) $req->input('days', 30)));

        $rows = Database::all(
            'SELECT country_code, country,
                    COUNT(DISTINCT user_id) AS users_count,
                    COUNT(*)               AS sessions_count
             FROM user_sessions
             WHERE country_code IS NOT NULL AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY country_code, country
             ORDER BY users_count DESC',
            [$days],
        );

        $cityRows = Database::all(
            'SELECT country, city,
                    COUNT(DISTINCT user_id) AS users_count
             FROM user_sessions
             WHERE city IS NOT NULL AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY country, city
             ORDER BY users_count DESC LIMIT 50',
            [$days],
        );

        Response::html(View::render('admin/analytics/geography', [
            'rows'     => $rows,
            'cityRows' => $cityRows,
            'days'     => $days,
            'me'       => $req->params['user'],
            'page'     => 'analytics',
        ]));
    }

    public function devices(Request $req): never
    {
        $platforms = Database::all(
            'SELECT platform, COUNT(*) AS devices_count, COUNT(DISTINCT user_id) AS users_count
             FROM user_devices GROUP BY platform ORDER BY devices_count DESC',
        );
        $os = Database::all(
            'SELECT os_name, os_version, COUNT(*) AS c
             FROM user_devices WHERE os_name <> ""
             GROUP BY os_name, os_version ORDER BY c DESC LIMIT 30',
        );
        $appVersions = Database::all(
            'SELECT app_version, COUNT(*) AS c
             FROM user_devices WHERE app_version <> ""
             GROUP BY app_version ORDER BY c DESC LIMIT 20',
        );
        $models = Database::all(
            'SELECT manufacturer, model, COUNT(*) AS c
             FROM user_devices WHERE model <> ""
             GROUP BY manufacturer, model ORDER BY c DESC LIMIT 30',
        );

        Response::html(View::render('admin/analytics/devices', [
            'platforms'    => $platforms,
            'os'           => $os,
            'appVersions'  => $appVersions,
            'models'       => $models,
            'me'           => $req->params['user'],
            'page'         => 'analytics',
        ]));
    }

    public function events(Request $req): never
    {
        $name   = (string) $req->input('name', '');
        $userId = (string) $req->input('user_id', '');
        $hours  = max(1, min(720, (int) $req->input('hours', 24)));

        $where = ['occurred_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)'];
        $params = [$hours];
        if ($name !== '')   { $where[] = 'event_name = ?'; $params[] = $name; }
        if ($userId !== '') { $where[] = 'user_id = ?';    $params[] = $userId; }
        $whereSql = implode(' AND ', $where);

        $rows = Database::all(
            "SELECT e.*, u.email, u.full_name, c.title AS course_title, l.title AS lesson_title
             FROM user_events e
             LEFT JOIN users   u ON u.id = e.user_id
             LEFT JOIN courses c ON c.id = e.course_id
             LEFT JOIN lessons l ON l.id = e.lesson_id
             WHERE $whereSql
             ORDER BY e.occurred_at DESC LIMIT 200",
            $params,
        );

        $eventNames = Database::all(
            'SELECT event_name, COUNT(*) AS c FROM user_events
             WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY event_name ORDER BY c DESC',
        );

        Response::html(View::render('admin/analytics/events', [
            'rows'       => $rows,
            'eventNames' => $eventNames,
            'name'       => $name,
            'userId'     => $userId,
            'hours'      => $hours,
            'me'         => $req->params['user'],
            'page'       => 'analytics',
        ]));
    }

    public function logins(Request $req): never
    {
        $onlyFailed = $req->input('failed') === '1';
        $userId     = (string) $req->input('user_id', '');

        $where = ['1=1']; $params = [];
        if ($onlyFailed) $where[] = 'success = 0';
        if ($userId !== '') { $where[] = 'user_id = ?'; $params[] = $userId; }
        $whereSql = implode(' AND ', $where);

        $rows = Database::all(
            "SELECT h.*, u.email, u.full_name
             FROM user_login_history h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE $whereSql
             ORDER BY h.attempted_at DESC LIMIT 200",
            $params,
        );

        Response::html(View::render('admin/analytics/logins', [
            'rows'       => $rows,
            'onlyFailed' => $onlyFailed,
            'userId'     => $userId,
            'me'         => $req->params['user'],
            'page'       => 'analytics',
        ]));
    }

    /** Per-user behavior timeline — linked from /admin/users/{id}. */
    public function userTimeline(Request $req): never
    {
        $user = Database::one('SELECT * FROM users WHERE id = ?', [$req->params['id']]);
        if (!$user) Response::notFound();

        $events = Database::all(
            'SELECT e.*, c.title AS course_title, l.title AS lesson_title
             FROM user_events e
             LEFT JOIN courses c ON c.id = e.course_id
             LEFT JOIN lessons l ON l.id = e.lesson_id
             WHERE e.user_id = ?
             ORDER BY e.occurred_at DESC LIMIT 200',
            [$user['id']],
        );

        $sessions = Database::all(
            'SELECT s.*, d.platform, d.model
             FROM user_sessions s
             LEFT JOIN user_devices d ON d.id = s.device_pk
             WHERE s.user_id = ?
             ORDER BY s.started_at DESC LIMIT 30',
            [$user['id']],
        );

        $devices = Database::all(
            'SELECT * FROM user_devices WHERE user_id = ? ORDER BY last_seen_at DESC',
            [$user['id']],
        );

        $logins = Database::all(
            'SELECT * FROM user_login_history WHERE user_id = ? ORDER BY attempted_at DESC LIMIT 30',
            [$user['id']],
        );

        $eventStats = Database::all(
            'SELECT event_name, COUNT(*) AS c FROM user_events
             WHERE user_id = ? AND occurred_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY event_name ORDER BY c DESC LIMIT 10',
            [$user['id']],
        );

        Response::html(View::render('admin/analytics/user_timeline', [
            'user'       => $user,
            'events'     => $events,
            'sessions'   => $sessions,
            'devices'    => $devices,
            'logins'     => $logins,
            'eventStats' => $eventStats,
            'me'         => $req->params['user'],
            'page'       => 'users',
        ]));
    }

    // ---- helpers --------------------------------------------------------

    /** @return array{value:float, dir:'up'|'down'|'flat'} */
    private function pctDelta(int $current, int $previous): array
    {
        if ($previous === 0) {
            return ['value' => $current > 0 ? 100.0 : 0.0, 'dir' => $current > 0 ? 'up' : 'flat'];
        }
        $delta = (($current - $previous) / $previous) * 100;
        return [
            'value' => abs(round($delta, 1)),
            'dir'   => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * Fill any missing day in $rows with a zero row so the area chart x-axis
     * has uniform spacing (otherwise an idle day would visually compress).
     */
    private function padDailyZeros(array $rows, int $days): array
    {
        $byDate = [];
        foreach ($rows as $r) $byDate[(string) $r['date']] = $r;

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $out[] = $byDate[$d] ?? ['date' => $d, 'dau' => 0, 'sessions_count' => 0, 'events_count' => 0];
        }
        return $out;
    }
}
