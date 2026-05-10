<?php
/** @var array $hourGrid  7×24 grid of event counts */
/** @var int $hourMax */
/** @var array $funnel */
/** @var array $journey */
/** @var array $leaderboard */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$dur = function (int $s): string {
    if ($s < 60)   return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm';
    return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
};
$score = function (array $u): int {
    return (int) round(((int) $u['sessions_count']) * 2 + ((int) $u['active_days']) * 5 + ((int) $u['events_count']) / 10);
};
$bucketHour = function (int $c) use ($hourMax): int {
    if ($c === 0 || $hourMax === 0) return 0;
    $r = $c / $hourMax;
    if ($r < 0.20) return 1;
    if ($r < 0.40) return 2;
    if ($r < 0.60) return 3;
    if ($r < 0.85) return 4;
    return 5;
};
$dayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

ob_start();
?>
<header>
    <div>
        <h2>Engagement</h2>
        <p>When users are active, where they drop off, and who's most engaged.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<nav class="tabs" style="margin-bottom:20px">
    <a href="/admin/analytics" class="tab">Overview</a>
    <a href="/admin/analytics/engagement" class="tab active">Engagement</a>
    <a href="/admin/analytics/cohorts" class="tab">Cohorts</a>
    <a href="/admin/analytics/geography" class="tab">Geography</a>
    <a href="/admin/analytics/devices" class="tab">Devices</a>
    <a href="/admin/analytics/events" class="tab">Event log</a>
    <a href="/admin/analytics/logins" class="tab">Login audit</a>
</nav>

<!-- Hour-of-day x weekday heatmap -->
<div class="card">
    <div class="card-header flex-row">
        <div>
            <h3 style="margin-bottom:2px">Hour-of-day activity</h3>
            <p class="text-muted" style="margin:0;font-size:12px">Events by weekday × hour · last 30 days · use this to time push notifications</p>
        </div>
    </div>

    <?php if ($hourMax === 0): ?>
        <div class="empty-state"><div class="empty-icon">⏰</div><p>No events recorded yet.</p></div>
    <?php else: ?>
    <div class="hourmap-wrap">
        <div class="hourmap-cols-header">
            <span></span>
            <?php for ($h = 0; $h < 24; $h++): ?>
                <span class="hour-label"><?= $h % 3 === 0 ? str_pad((string) $h, 2, '0', STR_PAD_LEFT) : '' ?></span>
            <?php endfor; ?>
        </div>
        <?php for ($d = 0; $d < 7; $d++): ?>
            <div class="hourmap-row">
                <span class="hour-row-label"><?= $dayLabels[$d] ?></span>
                <?php for ($h = 0; $h < 24; $h++):
                    $c = (int) $hourGrid[$d][$h];
                    $b = $bucketHour($c);
                ?>
                    <span class="hour-cell hour-<?= $b ?>"
                          title="<?= $dayLabels[$d] ?> <?= $h ?>:00 — <?= number_format($c) ?> events"></span>
                <?php endfor; ?>
            </div>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Funnel + Journey -->
<div class="grid-2">
    <div class="card">
        <div class="card-header flex-row">
            <h3 style="margin:0">Lesson completion funnel</h3>
            <span class="text-muted" style="font-size:11px">Last 30 days</span>
        </div>
        <?php if (empty($funnel) || $funnel[0]['users'] === 0): ?>
            <div class="empty-state"><div class="empty-icon">▶</div><p>No lesson_started events yet.<br><small>App needs to fire 'lesson_started' / 'lesson_progress_25/50/75' / 'lesson_completed'.</small></p></div>
        <?php else: ?>
            <div class="funnel">
                <?php foreach ($funnel as $i => $stage):
                    $width = $stage['pct'];
                    $dropFromPrev = $i > 0 && $funnel[$i - 1]['users'] > 0
                        ? round((($funnel[$i - 1]['users'] - $stage['users']) / $funnel[$i - 1]['users']) * 100, 1)
                        : 0;
                ?>
                    <div class="funnel-row">
                        <div class="funnel-label"><?= View::e($stage['label']) ?></div>
                        <div class="funnel-bar-wrap">
                            <div class="funnel-bar" style="width:<?= $width ?>%"></div>
                            <span class="funnel-count"><?= number_format($stage['users']) ?> users · <?= $stage['pct'] ?>%</span>
                        </div>
                        <?php if ($i > 0 && $dropFromPrev > 0): ?>
                            <div class="funnel-drop">−<?= $dropFromPrev ?>%</div>
                        <?php else: ?>
                            <div class="funnel-drop"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header flex-row">
            <h3 style="margin:0">Conversion journey</h3>
            <span class="text-muted" style="font-size:11px">Last 30 days</span>
        </div>
        <?php $maxJ = max(array_column($journey, 'users')) ?: 1; ?>
        <div class="funnel">
            <?php foreach ($journey as $i => $stage):
                $width = $maxJ > 0 ? ($stage['users'] / $maxJ) * 100 : 0;
                $dropFromPrev = $i > 0 && $journey[$i - 1]['users'] > 0
                    ? round((($journey[$i - 1]['users'] - $stage['users']) / $journey[$i - 1]['users']) * 100, 1)
                    : 0;
            ?>
                <div class="funnel-row">
                    <div class="funnel-label"><?= View::e($stage['label']) ?></div>
                    <div class="funnel-bar-wrap">
                        <div class="funnel-bar funnel-bar-alt" style="width:<?= $width ?>%"></div>
                        <span class="funnel-count"><?= number_format($stage['users']) ?> users</span>
                    </div>
                    <?php if ($i > 0 && $dropFromPrev > 0): ?>
                        <div class="funnel-drop">−<?= $dropFromPrev ?>%</div>
                    <?php else: ?>
                        <div class="funnel-drop"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Engagement leaderboard -->
<div class="card">
    <div class="card-header flex-row">
        <div>
            <h3 style="margin:0">Top engaged users (30d)</h3>
            <p class="text-muted" style="margin:4px 0 0;font-size:12px">Score = sessions × 2 + active days × 5 + events ÷ 10</p>
        </div>
        <div class="spacer"></div>
        <a href="/admin/users" class="text-muted" style="font-size:12px">All learners →</a>
    </div>
    <?php if (empty($leaderboard)): ?>
        <div class="empty-state"><div class="empty-icon">🏆</div><p>No engaged users yet.</p></div>
    <?php else: ?>
    <table class="table" style="margin-bottom:0">
        <thead><tr>
            <th style="width:40px">#</th>
            <th>Learner</th>
            <th class="text-right">Score</th>
            <th class="text-right">Sessions</th>
            <th class="text-right">Active days</th>
            <th class="text-right">Events</th>
            <th class="text-right">Time</th>
            <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($leaderboard as $i => $u):
            $rank = $i + 1;
            $rankClass = $rank === 1 ? 'rank-gold' : ($rank === 2 ? 'rank-silver' : ($rank === 3 ? 'rank-bronze' : ''));
        ?>
            <tr>
                <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
                <td>
                    <div class="user-cell">
                        <span class="avatar" style="background:linear-gradient(135deg,var(--primary),#22D3EE)">
                            <?= View::e(strtoupper(substr((string) $u['full_name'], 0, 1))) ?>
                        </span>
                        <div>
                            <strong><?= View::e($u['full_name']) ?></strong>
                            <div class="text-muted" style="font-size:11px"><?= View::e($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="text-right"><strong style="color:var(--primary);font-size:15px"><?= number_format($score($u)) ?></strong></td>
                <td class="text-right"><?= number_format((int) $u['sessions_count']) ?></td>
                <td class="text-right"><?= (int) $u['active_days'] ?>/30</td>
                <td class="text-right"><?= number_format((int) $u['events_count']) ?></td>
                <td class="text-right text-muted"><?= View::e($dur((int) $u['total_seconds'])) ?></td>
                <td class="text-right">
                    <a href="/admin/users/<?= View::e(urlencode((string) $u['id'])) ?>/activity" class="btn btn-ghost btn-sm">Activity →</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Engagement';
include __DIR__ . '/../../layouts/admin.php';
