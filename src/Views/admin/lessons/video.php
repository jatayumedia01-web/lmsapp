<?php
/** @var array $lesson */
/** @var array $course */
/** @var array $stats */
/** @var array $segments  20 buckets, value = views */
/** @var array $recentViews */
/** @var string $embed   embed URL */
/** @var array $me */
/** @var string $page */
/** @var ?array $flash */
use Devithor\View;

$dur = function (int $s): string {
    if ($s < 60)   return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm ' . ($s % 60) . 's';
    return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
};

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/courses/<?= View::e($course['id']) ?>/lessons">← Back to lessons</a></p>
        <h2><?= View::e($lesson['title']) ?>
            <span class="badge badge-<?= $lesson['video_provider'] === 'YOUTUBE' ? 'danger' : ($lesson['video_provider'] === 'CLOUDFLARE' ? 'info' : 'muted') ?>" style="margin-left:8px;font-size:11px"><?= View::e($lesson['video_provider']) ?></span>
        </h2>
        <p class="text-muted"><?= View::e($course['title']) ?> · video preview &amp; analytics</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/lessons/<?= View::e(rawurlencode($lesson['id'])) ?>" class="btn btn-secondary btn-sm">Edit lesson →</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-2-7-3">
    <!-- Video preview -->
    <div class="card" style="padding:0;overflow:hidden">
        <?php if (!empty($lesson['video_id']) && $lesson['video_provider'] === 'YOUTUBE'): ?>
            <div style="position:relative;padding-bottom:56.25%;background:#000">
                <iframe src="<?= View::e($embed) ?>"
                        style="position:absolute;inset:0;width:100%;height:100%;border:0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        <?php elseif ($lesson['video_provider'] === 'VIMEO' || $lesson['video_provider'] === 'CLOUDFLARE'): ?>
            <div style="position:relative;padding-bottom:56.25%;background:#000">
                <iframe src="<?= View::e($embed) ?>" style="position:absolute;inset:0;width:100%;height:100%;border:0"
                        allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php elseif ($lesson['video_provider'] === 'HLS'): ?>
            <div style="background:#000;padding:24px;text-align:center;color:#888">
                <p>HLS stream — preview in a player that supports m3u8.</p>
                <code style="display:block;margin-top:12px;word-break:break-all"><?= View::e((string) $lesson['video_url']) ?></code>
            </div>
        <?php elseif ($lesson['video_provider'] === 'MP4'): ?>
            <video controls preload="metadata" style="width:100%;background:#000;display:block"
                <?= ((int) $lesson['is_downloadable']) === 0 ? 'controlsList="nodownload" disablePictureInPicture' : '' ?>>
                <source src="<?= View::e((string) $lesson['video_url']) ?>" type="video/mp4">
                <?php if (!empty($lesson['subtitles_url'])): ?>
                    <track kind="captions" src="<?= View::e((string) $lesson['subtitles_url']) ?>" srclang="en" default>
                <?php endif; ?>
            </video>
        <?php else: ?>
            <div style="padding:36px;text-align:center;color:var(--text-muted)">
                <p>No previewable video set yet.</p>
                <p><a href="/admin/lessons/<?= View::e(rawurlencode($lesson['id'])) ?>" class="btn btn-primary btn-sm">Add a video URL →</a></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div>
        <div class="grid-stats" style="grid-template-columns: 1fr 1fr;margin-bottom:14px">
            <div class="stat"><div class="stat-label">Plays</div><div class="stat-value"><?= number_format($stats['plays']) ?></div></div>
            <div class="stat"><div class="stat-label">Unique users</div><div class="stat-value"><?= number_format($stats['unique_users']) ?></div></div>
            <div class="stat"><div class="stat-label">Completed</div><div class="stat-value"><?= number_format($stats['completed']) ?></div></div>
            <div class="stat"><div class="stat-label">Avg progress</div><div class="stat-value" style="font-size:18px"><?= (int) $stats['avg_pct'] ?>%</div></div>
        </div>
        <div class="card" style="margin-bottom:0">
            <h3 style="margin-bottom:6px">Total watch time</h3>
            <div style="font-size:22px;font-weight:800"><?= View::e($dur($stats['total_seconds'])) ?></div>
            <p class="text-muted" style="margin:6px 0 0;font-size:11px">Across all <?= number_format($stats['plays']) ?> playback sessions.</p>
        </div>
    </div>
</div>

<!-- Drop-off chart -->
<div class="card">
    <div class="card-header flex-row">
        <div>
            <h3 style="margin-bottom:2px">Drop-off chart</h3>
            <p class="text-muted" style="margin:0;font-size:12px">% of viewers who watched each 5% slice. Sharp dips = where to re-edit.</p>
        </div>
    </div>
    <?php
    $maxSeg = max($segments) ?: 1;
    ?>
    <div class="dropoff-bars">
        <?php for ($i = 0; $i < 20; $i++):
            $h = (int) round(($segments[$i] / $maxSeg) * 100);
        ?>
            <div class="dropoff-col" title="<?= ($i * 5) . '–' . (($i + 1) * 5) ?>%: <?= number_format($segments[$i]) ?> views">
                <div class="dropoff-bar" style="height:<?= $h ?>%;background:linear-gradient(180deg, var(--primary), <?= $i > 14 ? 'var(--success)' : ($i > 9 ? '#22D3EE' : '#7C5CFF') ?>)"></div>
                <span class="dropoff-label"><?= $i * 5 ?>%</span>
            </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Recent views -->
<div class="card">
    <h3 style="margin-bottom:8px">Recent playbacks (last 20)</h3>
    <?php if (empty($recentViews)): ?>
        <p class="text-muted">No playbacks tracked yet for this lesson.</p>
    <?php else: ?>
    <table class="table" style="margin-bottom:0">
        <thead><tr>
            <th>Learner</th><th>Started</th><th>Watched</th><th>Progress</th><th>Completed</th><th>Speed</th>
        </tr></thead>
        <tbody>
        <?php foreach ($recentViews as $v): ?>
            <tr>
                <td>
                    <a href="/admin/users/<?= View::e(rawurlencode($v['user_id'])) ?>"><?= View::e($v['full_name'] ?? $v['user_id']) ?></a>
                    <div class="text-muted" style="font-size:11px"><?= View::e((string) ($v['email'] ?? '')) ?></div>
                </td>
                <td class="text-muted" style="font-size:12px"><?= View::e(substr((string) $v['started_at'], 0, 16)) ?></td>
                <td><?= View::e($dur((int) $v['watch_seconds'])) ?></td>
                <td>
                    <div class="progress-mini">
                        <div class="progress-mini-fill" style="width:<?= (int) $v['progress_pct'] ?>%"></div>
                    </div>
                    <span style="font-size:11px;color:var(--text-muted)"><?= (int) $v['progress_pct'] ?>%</span>
                </td>
                <td>
                    <?php if ((int) $v['completed']): ?>
                        <span class="badge badge-success">Yes</span>
                    <?php else: ?>
                        <span class="badge badge-muted">No</span>
                    <?php endif; ?>
                </td>
                <td><?= number_format((float) $v['speed'], 1) ?>×</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$title   = $lesson['title'] . ' · video';
include __DIR__ . '/../../layouts/admin.php';
