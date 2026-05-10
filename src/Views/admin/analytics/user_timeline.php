<?php
/** @var array $user */
/** @var array $events */
/** @var array $sessions */
/** @var array $devices */
/** @var array $logins */
/** @var array $eventStats */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$dur = function (int $s): string {
    if ($s < 60)   return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm';
    return floor($s / 3600) . 'h';
};

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/users/<?= View::e(urlencode($user['id'])) ?>">← Back to user</a></p>
        <h2>Activity · <?= View::e($user['full_name']) ?></h2>
        <p class="text-muted"><?= View::e($user['email']) ?></p>
    </div>
</header>

<div class="card">
    <h3>Most-fired events (30d)</h3>
    <?php if (empty($eventStats)): ?>
        <p class="text-muted">No events for this user yet.</p>
    <?php else: ?>
        <div class="flex-row" style="flex-wrap:wrap">
            <?php foreach ($eventStats as $e): ?>
                <div class="badge badge-muted"><code><?= View::e($e['event_name']) ?></code> · <?= number_format((int) $e['c']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Devices</h3>
        <?php if (empty($devices)): ?>
            <p class="text-muted">No registered devices.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Platform</th><th>Model</th><th>App</th><th>Last seen</th></tr></thead>
                <tbody>
                <?php foreach ($devices as $d): ?>
                    <tr>
                        <td><span class="badge badge-<?= $d['platform'] === 'ANDROID' ? 'success' : ($d['platform'] === 'IOS' ? 'info' : 'muted') ?>"><?= View::e($d['platform']) ?></span></td>
                        <td>
                            <?= View::e($d['manufacturer']) ?> <?= View::e($d['model']) ?>
                            <div class="text-muted" style="font-size:11px"><?= View::e($d['os_name']) ?> <?= View::e($d['os_version']) ?></div>
                        </td>
                        <td><code><?= View::e($d['app_version']) ?></code></td>
                        <td class="text-muted" style="font-size:11px"><?= View::e(substr((string) $d['last_seen_at'], 0, 16)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent logins</h3>
        <?php if (empty($logins)): ?>
            <p class="text-muted">No login attempts logged.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>When</th><th>From</th><th>Result</th></tr></thead>
                <tbody>
                <?php foreach ($logins as $l): ?>
                    <tr>
                        <td class="text-muted" style="font-size:11px;white-space:nowrap"><?= View::e(substr((string) $l['attempted_at'], 0, 16)) ?></td>
                        <td>
                            <code><?= View::e($l['ip_address']) ?></code>
                            <div class="text-muted" style="font-size:11px"><?= View::e((string) ($l['city'] ?? '—')) ?>, <?= View::e((string) ($l['country'] ?? '—')) ?></div>
                        </td>
                        <td>
                            <?php if ((int) $l['success'] === 1): ?>
                                <span class="badge badge-success">OK</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Fail</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Sessions (last 30)</h3>
    <?php if (empty($sessions)): ?>
        <p class="text-muted">No tracked sessions.</p>
    <?php else: ?>
        <table class="table" style="margin-bottom:0">
            <thead><tr><th>Started</th><th>Duration</th><th>Events</th><th>Device</th><th>From</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
                <tr>
                    <td class="text-muted" style="font-size:11px;white-space:nowrap"><?= View::e(substr((string) $s['started_at'], 0, 16)) ?></td>
                    <td><?= View::e($dur((int) $s['duration_seconds'])) ?></td>
                    <td class="text-right"><?= number_format((int) $s['events_count']) ?></td>
                    <td>
                        <?= View::e((string) ($s['platform'] ?? '—')) ?>
                        <span class="text-muted" style="font-size:11px"><?= View::e((string) ($s['model'] ?? '')) ?></span>
                    </td>
                    <td class="text-muted" style="font-size:11px">
                        <?= View::e((string) ($s['city'] ?? '—')) ?>, <?= View::e((string) ($s['country'] ?? '—')) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Recent events</h3>
    <?php if (empty($events)): ?>
        <p class="text-muted">No events recorded yet.</p>
    <?php else: ?>
        <table class="table" style="margin-bottom:0">
            <thead><tr><th>Time</th><th>Event</th><th>Context</th></tr></thead>
            <tbody>
            <?php foreach ($events as $e): ?>
                <tr>
                    <td class="text-muted" style="font-size:11px;white-space:nowrap"><?= View::e(substr((string) $e['occurred_at'], 0, 19)) ?></td>
                    <td><code><?= View::e($e['event_name']) ?></code></td>
                    <td>
                        <?php if (!empty($e['screen'])): ?>
                            <span class="badge badge-muted"><?= View::e($e['screen']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($e['lesson_title'])): ?>
                            <span class="text-muted" style="font-size:12px"><?= View::e($e['lesson_title']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Activity';
include __DIR__ . '/../../layouts/admin.php';
