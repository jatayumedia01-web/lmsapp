<?php
/** @var array $stats */
/** @var array $top */
/** @var array $worst */
/** @var array $providers */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$dur = function (int $s): string {
    if ($s < 60)   return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm';
    if ($s < 86400) return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
    return floor($s / 86400) . 'd ' . floor(($s % 86400) / 3600) . 'h';
};

ob_start();
?>
<header>
    <div>
        <h2>Video analytics</h2>
        <p>Plays, completions, drop-offs &amp; provider breakdown across all lessons.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<nav class="tabs" style="margin-bottom:20px">
    <a href="/admin/analytics" class="tab">Overview</a>
    <a href="/admin/analytics/engagement" class="tab">Engagement</a>
    <a href="/admin/analytics/cohorts" class="tab">Cohorts</a>
    <a href="/admin/analytics/videos" class="tab active">Videos</a>
    <a href="/admin/analytics/geography" class="tab">Geography</a>
    <a href="/admin/analytics/devices" class="tab">Devices</a>
    <a href="/admin/analytics/events" class="tab">Event log</a>
</nav>

<div class="grid-stats">
    <div class="stat"><div class="stat-label">Plays (30d)</div><div class="stat-value"><?= number_format($stats['plays_30d']) ?></div></div>
    <div class="stat"><div class="stat-label">Unique viewers</div><div class="stat-value"><?= number_format($stats['unique_users_30d']) ?></div></div>
    <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= number_format($stats['completed_30d']) ?></div></div>
    <div class="stat"><div class="stat-label">Watch hours</div><div class="stat-value"><?= number_format($stats['watch_hours_30d'], 1) ?>h</div></div>
    <div class="stat"><div class="stat-label">Avg progress</div><div class="stat-value"><?= (int) $stats['avg_progress_30d'] ?>%</div></div>
</div>

<div class="card">
    <h3 style="margin-bottom:8px">Provider mix</h3>
    <?php if (empty($providers)): ?>
        <p class="text-muted">No lessons with videos yet.</p>
    <?php else: ?>
        <div class="flex-row" style="flex-wrap:wrap;gap:8px">
            <?php $totalP = array_sum(array_column($providers, 'c')) ?: 1; ?>
            <?php foreach ($providers as $p):
                $cls = $p['video_provider'] === 'YOUTUBE' ? 'badge-danger' :
                       ($p['video_provider'] === 'CLOUDFLARE' ? 'badge-info' :
                       ($p['video_provider'] === 'VIMEO' ? 'badge-success' : 'badge-muted'));
                $pct = round(((int) $p['c'] / $totalP) * 100);
            ?>
                <span class="badge <?= $cls ?>"><?= View::e((string) $p['video_provider']) ?> · <?= number_format((int) $p['c']) ?> · <?= $pct ?>%</span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header flex-row">
        <h3 style="margin:0">Top videos by plays (30d)</h3>
        <span class="text-muted" style="font-size:11px">Last 30 days</span>
    </div>
    <?php if (empty($top)): ?>
        <div class="empty-state"><div class="empty-icon">▶</div><p>No video plays in the last 30 days.<br><small>Once the Android player fires <code>video_play_started</code> / <code>video_play_ended</code>, this fills up.</small></p></div>
    <?php else: ?>
    <table class="table" style="margin-bottom:0">
        <thead><tr>
            <th>Lesson</th>
            <th>Provider</th>
            <th class="text-right">Plays</th>
            <th class="text-right">Viewers</th>
            <th class="text-right">Completed</th>
            <th class="text-right">Avg %</th>
            <th class="text-right">Watch time</th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($top as $r): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <?php if (!empty($r['thumbnail_url'])): ?>
                            <img src="<?= View::e((string) $r['thumbnail_url']) ?>" alt="" style="width:48px;height:28px;object-fit:cover;border-radius:4px;flex-shrink:0">
                        <?php endif; ?>
                        <div>
                            <strong><?= View::e($r['title']) ?></strong>
                            <div class="text-muted" style="font-size:11px"><?= View::e((string) $r['course_title']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-muted"><?= View::e($r['video_provider']) ?></span></td>
                <td class="text-right"><?= number_format((int) $r['plays']) ?></td>
                <td class="text-right"><?= number_format((int) $r['unique_users']) ?></td>
                <td class="text-right"><?= number_format((int) $r['completions']) ?></td>
                <td class="text-right"><?= (int) round((float) $r['avg_pct']) ?>%</td>
                <td class="text-right text-muted"><?= View::e($dur((int) $r['total_seconds'])) ?></td>
                <td class="text-right">
                    <a href="/admin/lessons/<?= View::e(urlencode($r['id'])) ?>/video" class="btn btn-ghost btn-sm">Open →</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header flex-row">
        <h3 style="margin:0">Worst engagement (lowest avg progress)</h3>
        <span class="text-muted" style="font-size:11px">≥3 plays · last 30 days</span>
    </div>
    <?php if (empty($worst)): ?>
        <p class="text-muted">Not enough data yet.</p>
    <?php else: ?>
    <table class="table" style="margin-bottom:0">
        <thead><tr><th>Lesson</th><th class="text-right">Plays</th><th>Avg progress</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($worst as $r): ?>
            <tr>
                <td>
                    <strong><?= View::e($r['title']) ?></strong>
                    <div class="text-muted" style="font-size:11px"><?= View::e((string) $r['course_title']) ?></div>
                </td>
                <td class="text-right"><?= number_format((int) $r['plays']) ?></td>
                <td>
                    <div class="country-bar"><div class="country-bar-fill" style="width:<?= (int) round((float) $r['avg_pct']) ?>%;background:linear-gradient(90deg, var(--danger), var(--warning))"></div></div>
                    <small class="text-muted"><?= (int) round((float) $r['avg_pct']) ?>%</small>
                </td>
                <td class="text-right">
                    <a href="/admin/lessons/<?= View::e(urlencode($r['id'])) ?>/video" class="btn btn-ghost btn-sm">Diagnose →</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Video analytics';
include __DIR__ . '/../../layouts/admin.php';
