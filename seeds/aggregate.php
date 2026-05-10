<?php
/**
 * Daily analytics aggregator. Recomputes the last N days of:
 *   - analytics_daily       (site-wide DAU / sessions / events / revenue)
 *   - analytics_geography   (per-country slices)
 *   - analytics_devices     (per-platform slices)
 *
 * Runs in O(events-in-window). Safe to run repeatedly — every row is
 * INSERT … ON DUPLICATE KEY UPDATE so re-runs overwrite, never duplicate.
 *
 * Usage:
 *   php seeds/aggregate.php             # default 30-day rolling window
 *   php seeds/aggregate.php --days=90   # custom window
 *
 * Hostinger cron line (recommended: daily 03:10 UTC):
 *   10 3 * * * /usr/bin/php8.2 /home/u169457691/domains/apptesting.in/public_html/devithor-backend/seeds/aggregate.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Devithor\Database;

$days = 30;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, min(365, (int) $m[1]));
    }
}

$startDate = date('Y-m-d', strtotime("-$days days"));
echo "Rebuilding analytics aggregates from $startDate ...\n";

// ---- analytics_daily -------------------------------------------------
$dailyRows = Database::all(
    'SELECT DATE(occurred_at)               AS d,
            COUNT(DISTINCT user_id)         AS dau,
            COUNT(*)                         AS events_count
     FROM user_events
     WHERE occurred_at >= ?
     GROUP BY DATE(occurred_at)',
    [$startDate . ' 00:00:00'],
);

$sessionsByDay = Database::all(
    'SELECT DATE(started_at)                AS d,
            COUNT(*)                         AS sessions_count,
            COALESCE(AVG(duration_seconds), 0) AS avg_session
     FROM user_sessions
     WHERE started_at >= ?
     GROUP BY DATE(started_at)',
    [$startDate . ' 00:00:00'],
);
$sessionMap = [];
foreach ($sessionsByDay as $r) $sessionMap[$r['d']] = $r;

$revenueByDay = Database::all(
    'SELECT DATE(FROM_UNIXTIME(date_millis / 1000)) AS d,
            SUM(amount_cents)                       AS revenue_cents,
            COUNT(DISTINCT user_id)                 AS paying_users
     FROM invoices
     WHERE status = "PAID" AND date_millis >= UNIX_TIMESTAMP(?) * 1000
     GROUP BY DATE(FROM_UNIXTIME(date_millis / 1000))',
    [$startDate . ' 00:00:00'],
);
$revMap = [];
foreach ($revenueByDay as $r) $revMap[$r['d']] = $r;

$newUsersByDay = Database::all(
    'SELECT DATE(joined_at) AS d, COUNT(*) AS new_users
     FROM users WHERE joined_at >= ? GROUP BY DATE(joined_at)',
    [$startDate . ' 00:00:00'],
);
$newUserMap = [];
foreach ($newUsersByDay as $r) $newUserMap[$r['d']] = (int) $r['new_users'];

$lessonStartedByDay = Database::all(
    'SELECT DATE(occurred_at) AS d, COUNT(*) AS c
     FROM user_events WHERE event_name = "lesson_started" AND occurred_at >= ?
     GROUP BY DATE(occurred_at)',
    [$startDate . ' 00:00:00'],
);
$lsMap = [];
foreach ($lessonStartedByDay as $r) $lsMap[$r['d']] = (int) $r['c'];

$lessonDoneByDay = Database::all(
    'SELECT DATE(occurred_at) AS d, COUNT(*) AS c
     FROM user_events WHERE event_name = "lesson_completed" AND occurred_at >= ?
     GROUP BY DATE(occurred_at)',
    [$startDate . ' 00:00:00'],
);
$ldMap = [];
foreach ($lessonDoneByDay as $r) $ldMap[$r['d']] = (int) $r['c'];

$qaByDay = Database::all(
    'SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM lesson_questions WHERE created_at >= ? GROUP BY DATE(created_at)',
    [$startDate . ' 00:00:00'],
);
$qaMap = [];
foreach ($qaByDay as $r) $qaMap[$r['d']] = (int) $r['c'];

$rowsWritten = 0;
foreach ($dailyRows as $d) {
    $date = $d['d'];
    Database::exec(
        'INSERT INTO analytics_daily
            (`date`, dau, new_users, returning_users, sessions_count, events_count,
             avg_session_seconds, revenue_cents, paying_users,
             lessons_started, lessons_completed, qa_posted)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            dau = VALUES(dau), new_users = VALUES(new_users), returning_users = VALUES(returning_users),
            sessions_count = VALUES(sessions_count), events_count = VALUES(events_count),
            avg_session_seconds = VALUES(avg_session_seconds),
            revenue_cents = VALUES(revenue_cents), paying_users = VALUES(paying_users),
            lessons_started = VALUES(lessons_started), lessons_completed = VALUES(lessons_completed),
            qa_posted = VALUES(qa_posted)',
        [
            $date,
            (int) $d['dau'],
            $newUserMap[$date] ?? 0,
            max(0, ((int) $d['dau']) - ($newUserMap[$date] ?? 0)),
            (int) ($sessionMap[$date]['sessions_count'] ?? 0),
            (int) $d['events_count'],
            (int) ($sessionMap[$date]['avg_session'] ?? 0),
            (int) ($revMap[$date]['revenue_cents'] ?? 0),
            (int) ($revMap[$date]['paying_users'] ?? 0),
            $lsMap[$date] ?? 0,
            $ldMap[$date] ?? 0,
            $qaMap[$date] ?? 0,
        ],
    );
    $rowsWritten++;
}
echo "  analytics_daily: $rowsWritten day(s)\n";

// ---- analytics_geography ---------------------------------------------
$geoRows = Database::all(
    'SELECT country_code, country, DATE(started_at) AS d,
            COUNT(DISTINCT user_id) AS users_count,
            COUNT(*)                AS sessions_count
     FROM user_sessions
     WHERE country_code IS NOT NULL AND started_at >= ?
     GROUP BY country_code, country, DATE(started_at)',
    [$startDate . ' 00:00:00'],
);
$geoWritten = 0;
foreach ($geoRows as $r) {
    Database::exec(
        'INSERT INTO analytics_geography (country_code, country, `date`, users_count, sessions_count)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            country = VALUES(country), users_count = VALUES(users_count), sessions_count = VALUES(sessions_count)',
        [$r['country_code'], $r['country'], $r['d'], (int) $r['users_count'], (int) $r['sessions_count']],
    );
    $geoWritten++;
}
echo "  analytics_geography: $geoWritten row(s)\n";

// ---- analytics_devices -----------------------------------------------
$devRows = Database::all(
    'SELECT d.platform, DATE(s.started_at) AS day,
            COUNT(DISTINCT s.user_id) AS users_count,
            COUNT(*)                  AS sessions_count
     FROM user_sessions s
     LEFT JOIN user_devices d ON d.id = s.device_pk
     WHERE s.started_at >= ?
     GROUP BY d.platform, DATE(s.started_at)',
    [$startDate . ' 00:00:00'],
);
$devWritten = 0;
foreach ($devRows as $r) {
    if (empty($r['platform'])) continue;
    Database::exec(
        'INSERT INTO analytics_devices (platform, `date`, users_count, sessions_count)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE users_count = VALUES(users_count), sessions_count = VALUES(sessions_count)',
        [$r['platform'], $r['day'], (int) $r['users_count'], (int) $r['sessions_count']],
    );
    $devWritten++;
}
echo "  analytics_devices: $devWritten row(s)\n";

echo "\nDone.\n";
