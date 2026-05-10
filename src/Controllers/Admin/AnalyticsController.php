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
        $today    = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekAgo  = date('Y-m-d', strtotime('-7 days'));
        $monthAgo = date('Y-m-d', strtotime('-30 days'));

        $stats = [
            'dau'             => (int) Database::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ?',
                [$today . ' 00:00:00'],
            ),
            'wau'             => (int) Database::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ?',
                [$weekAgo . ' 00:00:00'],
            ),
            'mau'             => (int) Database::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM user_events WHERE occurred_at >= ?',
                [$monthAgo . ' 00:00:00'],
            ),
            'sessions_today'  => (int) Database::scalar(
                'SELECT COUNT(*) FROM user_sessions WHERE started_at >= ?',
                [$today . ' 00:00:00'],
            ),
            'events_today'    => (int) Database::scalar(
                'SELECT COUNT(*) FROM user_events WHERE occurred_at >= ?',
                [$today . ' 00:00:00'],
            ),
            'avg_session_today_seconds' => (int) (Database::scalar(
                'SELECT COALESCE(AVG(duration_seconds), 0) FROM user_sessions
                 WHERE started_at >= ? AND duration_seconds > 0',
                [$today . ' 00:00:00'],
            ) ?? 0),
            'devices_count'   => (int) Database::scalar('SELECT COUNT(*) FROM user_devices'),
            'failed_logins_24h' => (int) Database::scalar(
                'SELECT COUNT(*) FROM user_login_history
                 WHERE success = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            ),
        ];

        // 14-day trend for the line chart. Prefer aggregates; fall back to raw.
        $trend = Database::all(
            'SELECT `date`, dau, sessions_count, events_count
             FROM analytics_daily
             WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             ORDER BY `date` ASC',
        );
        if (empty($trend)) {
            $trend = Database::all(
                'SELECT DATE(occurred_at) AS `date`,
                        COUNT(DISTINCT user_id) AS dau,
                        0 AS sessions_count,
                        COUNT(*) AS events_count
                 FROM user_events
                 WHERE occurred_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                 GROUP BY DATE(occurred_at)
                 ORDER BY `date` ASC',
            );
        }

        $topEvents = Database::all(
            'SELECT event_name, COUNT(*) AS c
             FROM user_events
             WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY event_name ORDER BY c DESC LIMIT 10',
        );

        $platformBreakdown = Database::all(
            'SELECT platform, COUNT(*) AS c FROM user_devices GROUP BY platform ORDER BY c DESC',
        );

        $countryBreakdown = Database::all(
            'SELECT country_code, country, COUNT(DISTINCT user_id) AS users_count
             FROM user_sessions
             WHERE country_code IS NOT NULL AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY country_code, country
             ORDER BY users_count DESC LIMIT 10',
        );

        Response::html(View::render('admin/analytics/overview', [
            'stats'             => $stats,
            'trend'             => $trend,
            'topEvents'         => $topEvents,
            'platformBreakdown' => $platformBreakdown,
            'countryBreakdown'  => $countryBreakdown,
            'me'                => $req->params['user'],
            'page'              => 'analytics',
        ]));
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
}
