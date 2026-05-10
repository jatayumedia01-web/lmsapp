<?php
/** @var array $stats */
/** @var array $trend */
/** @var array $topEvents */
/** @var array $platformBreakdown */
/** @var array $countryBreakdown */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$humanDuration = function (int $seconds): string {
    if ($seconds < 60)   return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
};

// Build SVG sparkline path for the 14-day trend.
$maxDau = max(array_column($trend, 'dau') ?: [1]);
$points = [];
foreach (array_values($trend) as $i => $row) {
    $x = count($trend) > 1 ? (int) (($i / (count($trend) - 1)) * 600) : 300;
    $y = (int) (100 - (((int) $row['dau'] / max(1, $maxDau)) * 90));
    $points[] = "$x,$y";
}
$polyline = implode(' ', $points);

ob_start();
?>
<header>
    <div>
        <h2>Analytics overview</h2>
        <p>Live behavior + activity. Trend chart needs the daily aggregator (cron) for older data.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics/geography" class="btn btn-secondary btn-sm">Geography</a>
    <a href="/admin/analytics/devices"   class="btn btn-secondary btn-sm">Devices</a>
    <a href="/admin/analytics/events"    class="btn btn-secondary btn-sm">Event log</a>
    <a href="/admin/analytics/logins"    class="btn btn-secondary btn-sm">Login audit</a>
</header>

<div class="grid-stats">
    <div class="stat">
        <div class="stat-label">DAU</div>
        <div class="stat-value"><?= number_format($stats['dau']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">WAU (7d)</div>
        <div class="stat-value"><?= number_format($stats['wau']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">MAU (30d)</div>
        <div class="stat-value"><?= number_format($stats['mau']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Sessions today</div>
        <div class="stat-value"><?= number_format($stats['sessions_today']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Events today</div>
        <div class="stat-value"><?= number_format($stats['events_today']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Avg session</div>
        <div class="stat-value" style="font-size:18px">
            <?= View::e($humanDuration($stats['avg_session_today_seconds'])) ?>
        </div>
    </div>
    <div class="stat">
        <div class="stat-label">Devices total</div>
        <div class="stat-value"><?= number_format($stats['devices_count']) ?></div>
    </div>
    <a class="stat" href="/admin/analytics/logins?failed=1">
        <div class="stat-label">Failed logins (24h)</div>
        <div class="stat-value" style="color:<?= $stats['failed_logins_24h'] > 5 ? 'var(--danger)' : 'var(--text)' ?>">
            <?= number_format($stats['failed_logins_24h']) ?>
        </div>
    </a>
</div>

<div class="card">
    <h3>14-day trend (DAU)</h3>
    <?php if (empty($trend)): ?>
        <p class="text-muted">No tracked behavior in the last 14 days.</p>
    <?php else: ?>
        <svg viewBox="0 0 600 110" preserveAspectRatio="none" style="width:100%;height:140px">
            <polyline points="<?= View::e($polyline) ?>"
                      fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linejoin="round" />
            <?php foreach ($trend as $i => $row): ?>
                <?php
                $x = count($trend) > 1 ? (int) (($i / (count($trend) - 1)) * 600) : 300;
                $y = (int) (100 - (((int) $row['dau'] / max(1, $maxDau)) * 90));
                ?>
                <circle cx="<?= $x ?>" cy="<?= $y ?>" r="3" fill="var(--primary)" />
            <?php endforeach; ?>
        </svg>
        <div class="text-muted text-right" style="font-size:11px">Peak: <?= number_format($maxDau) ?> DAU</div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Top events (last 7 days)</h3>
        <?php if (empty($topEvents)): ?>
            <p class="text-muted">No events recorded yet — wire up the Android app's TrackingClient to start populating this.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Event</th><th class="text-right">Count</th></tr></thead>
                <tbody>
                <?php foreach ($topEvents as $e): ?>
                    <tr>
                        <td><code><?= View::e($e['event_name']) ?></code></td>
                        <td class="text-right"><?= number_format((int) $e['c']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Top countries (30d)</h3>
        <?php if (empty($countryBreakdown)): ?>
            <p class="text-muted">No geo data yet. Check that the API can reach <code>ip-api.com</code>.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Country</th><th class="text-right">Users</th></tr></thead>
                <tbody>
                <?php foreach ($countryBreakdown as $c): ?>
                    <tr>
                        <td><?= View::e($c['country']) ?> <code style="margin-left:6px"><?= View::e((string) $c['country_code']) ?></code></td>
                        <td class="text-right"><?= number_format((int) $c['users_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Platforms</h3>
    <?php if (empty($platformBreakdown)): ?>
        <p class="text-muted">No devices registered yet.</p>
    <?php else: ?>
        <div class="flex-row" style="flex-wrap:wrap;gap:12px">
            <?php foreach ($platformBreakdown as $p): ?>
                <div class="badge badge-<?= $p['platform'] === 'ANDROID' ? 'success' : ($p['platform'] === 'IOS' ? 'info' : 'muted') ?>">
                    <?= View::e($p['platform']) ?> · <?= number_format((int) $p['c']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Analytics';
include __DIR__ . '/../../layouts/admin.php';
