<?php
/** @var array $current */ // hero numbers for current window
/** @var array $deltas */
/** @var array $trend */
/** @var array $platforms */
/** @var array $countries */
/** @var array $recentActivity */
/** @var array $topEvents */
/** @var array $recentEnrollments */
/** @var array $secondary */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$money = fn (int $cents, string $cur = 'INR'): string => $cur . ' ' . number_format($cents / 100, 2);
$humanDur = function (int $s): string {
    if ($s < 60)   return $s . 's';
    if ($s < 3600) return floor($s / 60) . 'm ' . ($s % 60) . 's';
    return floor($s / 3600) . 'h ' . floor(($s % 3600) / 60) . 'm';
};
$relTime = function (string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)    return $diff . 's ago';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
};

// ---------- Build the area chart SVG (30-day DAU) ----------------------
$chartW = 800; $chartH = 240; $padL = 36; $padB = 28; $padT = 12; $padR = 16;
$plotW = $chartW - $padL - $padR;
$plotH = $chartH - $padT - $padB;
$daus = array_map(fn ($r) => (int) $r['dau'], $trend);
$maxY = max(max($daus), 1);
$nicestep = max(1, (int) ceil($maxY / 4));
$gridMax = $nicestep * 4;

$pointsXY = [];
$n = count($trend);
foreach (array_values($trend) as $i => $row) {
    $x = $padL + ($n > 1 ? ($i / ($n - 1)) * $plotW : $plotW / 2);
    $y = $padT + $plotH - (((int) $row['dau'] / $gridMax) * $plotH);
    $pointsXY[] = ['x' => $x, 'y' => $y, 'date' => $row['date'], 'dau' => (int) $row['dau']];
}
$linePath = '';
$areaPath = '';
if (!empty($pointsXY)) {
    foreach ($pointsXY as $i => $pt) {
        $cmd = $i === 0 ? 'M' : 'L';
        $linePath .= "$cmd{$pt['x']},{$pt['y']} ";
    }
    $first = $pointsXY[0]; $last = end($pointsXY);
    $areaPath = "M{$first['x']},". ($padT + $plotH) ." L"
              . substr($linePath, 1)
              . "L{$last['x']},". ($padT + $plotH) ." Z";
}

// ---------- Donut chart: platforms ------------------------------------
$donutTotal = array_sum(array_column($platforms, 'c')) ?: 1;
$donutColors = [
    'ANDROID' => '#10B981',
    'IOS'     => '#22D3EE',
    'WEB'     => '#7C5CFF',
    'OTHER'   => '#F59E0B',
];
$donutSegments = [];
$cumulative = 0;
$cir = 2 * M_PI * 64; // r = 64
foreach ($platforms as $p) {
    $share = ((int) $p['c']) / $donutTotal;
    $len = $share * $cir;
    $donutSegments[] = [
        'platform' => $p['platform'],
        'count'    => (int) $p['c'],
        'pct'      => round($share * 100, 1),
        'color'    => $donutColors[$p['platform']] ?? '#8893B8',
        'dasharray'=> "$len " . ($cir - $len),
        'offset'   => -$cumulative,
    ];
    $cumulative += $len;
}

// ---------- Event icon glyph -----------------------------------------
$eventIcon = function (string $name): array {
    $map = [
        'app_open'           => ['◐', 'var(--info)'],
        'screen_view'        => ['◇', 'var(--text-muted)'],
        'lesson_started'     => ['▶', 'var(--success)'],
        'lesson_completed'   => ['✓', 'var(--success)'],
        'lesson_progress'    => ['•', 'var(--primary)'],
        'video_played'       => ['▶', 'var(--info)'],
        'video_paused'       => ['⏸', 'var(--text-muted)'],
        'course_enrolled'    => ['＋', 'var(--primary)'],
        'course_completed'   => ['★', 'var(--warning)'],
        'question_posted'    => ['?', 'var(--warning)'],
        'answer_posted'      => ['↳', 'var(--success)'],
        'subscription_started' => ['$', 'var(--success)'],
        'payment_succeeded'  => ['$', 'var(--success)'],
        'payment_failed'     => ['$', 'var(--danger)'],
        'login'              => ['→', 'var(--info)'],
        'logout'             => ['←', 'var(--text-muted)'],
    ];
    return $map[$name] ?? ['•', 'var(--text-muted)'];
};

ob_start();
?>
<header>
    <div>
        <h2>Analytics</h2>
        <p>Live behavior, geography &amp; device insights · <?= date('M j, Y · H:i') ?></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics/events"  class="btn btn-ghost btn-sm">Event log →</a>
    <a href="/admin/analytics/logins"  class="btn btn-ghost btn-sm">Login audit →</a>
</header>

<!-- Hero stat cards row -->
<div class="hero-stats">
    <?php
    $heroes = [
        ['MAU (30d)',   number_format($current['mau']),                    $deltas['mau'],      '👥', 'var(--primary)'],
        ['DAU today',   number_format($current['dau']),                    $deltas['dau'],      '⚡', 'var(--success)'],
        ['Sessions 7d', number_format($current['sessions']),               $deltas['sessions'], '◐', 'var(--info)'],
        ['Revenue 30d', $money($current['revenue']),                       $deltas['revenue'],  '◊', 'var(--warning)'],
    ];
    foreach ($heroes as [$label, $value, $delta, $icon, $color]):
        $arrow = $delta['dir'] === 'up' ? '↑' : ($delta['dir'] === 'down' ? '↓' : '→');
        $deltaCls = $delta['dir'] === 'up' ? 'up' : ($delta['dir'] === 'down' ? 'down' : 'flat');
    ?>
        <div class="hero-card">
            <div class="hero-icon" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $icon ?></div>
            <div class="hero-meta">
                <div class="hero-label"><?= View::e($label) ?></div>
                <div class="hero-value"><?= View::e($value) ?></div>
                <div class="hero-delta <?= $deltaCls ?>">
                    <?= $arrow ?> <?= View::e((string) $delta['value']) ?>% <span class="text-dim">vs prev</span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Main chart + donut row -->
<div class="grid-2-7-3">
    <div class="card chart-card">
        <div class="card-header flex-row">
            <div>
                <h3 style="margin-bottom:2px">User activity</h3>
                <p class="text-muted" style="margin:0;font-size:12px">Daily active users · last 30 days</p>
            </div>
            <div class="spacer"></div>
            <div class="badge badge-success">Peak: <?= number_format(max($daus)) ?></div>
        </div>

        <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" class="area-chart">
            <defs>
                <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="var(--primary)" stop-opacity="0.45"/>
                    <stop offset="100%" stop-color="var(--primary)" stop-opacity="0.0"/>
                </linearGradient>
                <linearGradient id="lineGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%"   stop-color="#7C5CFF"/>
                    <stop offset="100%" stop-color="#22D3EE"/>
                </linearGradient>
            </defs>

            <!-- y-axis grid lines + labels -->
            <?php for ($i = 0; $i <= 4; $i++):
                $gy = $padT + ($plotH * $i / 4);
                $lbl = (int) round($gridMax - ($gridMax * $i / 4));
            ?>
                <line x1="<?= $padL ?>" x2="<?= $chartW - $padR ?>" y1="<?= $gy ?>" y2="<?= $gy ?>"
                      stroke="var(--border)" stroke-dasharray="2,4"/>
                <text x="<?= $padL - 6 ?>" y="<?= $gy + 3 ?>" text-anchor="end"
                      fill="var(--text-dim)" font-size="10"><?= $lbl ?></text>
            <?php endfor; ?>

            <!-- x-axis labels (sparse) -->
            <?php foreach ($pointsXY as $i => $pt):
                if ($n > 12 && $i % (int) ceil($n / 6) !== 0 && $i !== $n - 1) continue;
            ?>
                <text x="<?= $pt['x'] ?>" y="<?= $chartH - 8 ?>" text-anchor="middle"
                      fill="var(--text-dim)" font-size="10">
                    <?= date('M j', strtotime((string) $pt['date'])) ?>
                </text>
            <?php endforeach; ?>

            <?php if ($areaPath !== ''): ?>
                <path d="<?= $areaPath ?>" fill="url(#areaGrad)"/>
                <path d="<?= trim($linePath) ?>" fill="none" stroke="url(#lineGrad)"
                      stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                <?php foreach ($pointsXY as $pt): ?>
                    <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="3"
                            fill="var(--bg)" stroke="var(--primary)" stroke-width="2">
                        <title><?= View::e((string) $pt['date']) ?>: <?= (int) $pt['dau'] ?> users</title>
                    </circle>
                <?php endforeach; ?>
            <?php endif; ?>
        </svg>
    </div>

    <div class="card donut-card">
        <h3>Platforms</h3>
        <p class="text-muted" style="margin-top:-4px;font-size:12px">Devices by platform</p>

        <?php if (empty($donutSegments)): ?>
            <div class="text-muted" style="text-align:center;padding:40px 0">
                No devices registered yet.<br><small>Wire up the Android tracking SDK to populate this.</small>
            </div>
        <?php else: ?>
            <div class="donut-wrap">
                <svg viewBox="0 0 160 160" class="donut-svg">
                    <circle cx="80" cy="80" r="64" fill="none" stroke="var(--surface-2)" stroke-width="20"/>
                    <?php foreach ($donutSegments as $seg): ?>
                        <circle cx="80" cy="80" r="64" fill="none"
                                stroke="<?= $seg['color'] ?>" stroke-width="20"
                                stroke-dasharray="<?= $seg['dasharray'] ?>"
                                stroke-dashoffset="<?= $seg['offset'] ?>"
                                transform="rotate(-90 80 80)"/>
                    <?php endforeach; ?>
                    <text x="80" y="76" text-anchor="middle" fill="var(--text)"
                          font-size="22" font-weight="800"><?= number_format($donutTotal) ?></text>
                    <text x="80" y="92" text-anchor="middle" fill="var(--text-muted)"
                          font-size="10" letter-spacing="1">DEVICES</text>
                </svg>
                <div class="donut-legend">
                    <?php foreach ($donutSegments as $seg): ?>
                        <div class="legend-row">
                            <span class="legend-dot" style="background:<?= $seg['color'] ?>"></span>
                            <span class="legend-label"><?= View::e($seg['platform']) ?></span>
                            <span class="legend-value"><?= number_format($seg['count']) ?>
                                <small class="text-muted">· <?= $seg['pct'] ?>%</small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent activity + countries row -->
<div class="grid-2-6-4">
    <div class="card">
        <div class="card-header flex-row">
            <div>
                <h3 style="margin-bottom:2px">Recent activity</h3>
                <p class="text-muted" style="margin:0;font-size:12px">Live event stream</p>
            </div>
            <div class="spacer"></div>
            <a href="/admin/analytics/events" class="text-muted" style="font-size:12px">View all →</a>
        </div>

        <?php if (empty($recentActivity)): ?>
            <div class="empty-state">
                <div class="empty-icon">⚡</div>
                <p>No tracked events yet.<br><small>Once the app starts firing events, they'll stream in here in real time.</small></p>
            </div>
        <?php else: ?>
            <div class="activity-feed">
                <?php foreach ($recentActivity as $a):
                    [$ico, $col] = $eventIcon((string) $a['event_name']);
                ?>
                    <div class="activity-row">
                        <span class="activity-dot" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $ico ?></span>
                        <div class="activity-body">
                            <div class="activity-title">
                                <strong><?= View::e($a['full_name'] ?? 'Unknown') ?></strong>
                                <span class="text-muted"><?= View::e($a['event_name']) ?></span>
                                <?php if (!empty($a['lesson_title'])): ?>
                                    in <em><?= View::e($a['lesson_title']) ?></em>
                                <?php elseif (!empty($a['screen'])): ?>
                                    on <code><?= View::e($a['screen']) ?></code>
                                <?php endif; ?>
                            </div>
                            <div class="activity-meta">
                                <?= View::e($relTime((string) $a['occurred_at'])) ?>
                                <?php if (!empty($a['email'])): ?>
                                    · <span class="text-dim"><?= View::e($a['email']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header flex-row">
            <div>
                <h3 style="margin-bottom:2px">Top countries</h3>
                <p class="text-muted" style="margin:0;font-size:12px">Last 30 days</p>
            </div>
            <div class="spacer"></div>
            <a href="/admin/analytics/geography" class="text-muted" style="font-size:12px">All →</a>
        </div>

        <?php if (empty($countries)): ?>
            <div class="empty-state"><div class="empty-icon">🌍</div><p>No geo data yet.</p></div>
        <?php else: ?>
            <?php $maxC = max(array_column($countries, 'users_count')); ?>
            <div class="country-list">
                <?php foreach ($countries as $c):
                    $share = $maxC > 0 ? ((int) $c['users_count'] / $maxC) * 100 : 0;
                ?>
                    <div class="country-row">
                        <div class="country-meta">
                            <span class="country-name"><?= View::e($c['country']) ?></span>
                            <span class="country-code"><?= View::e((string) $c['country_code']) ?></span>
                        </div>
                        <div class="country-bar"><div class="country-bar-fill" style="width:<?= $share ?>%"></div></div>
                        <div class="country-value"><?= number_format((int) $c['users_count']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.6px;font-weight:700;margin-bottom:8px">Quick stats</div>
            <div class="quick-stats">
                <div><span class="text-muted">Avg session</span><strong><?= View::e($humanDur($secondary['avg_session'])) ?></strong></div>
                <div><span class="text-muted">Devices total</span><strong><?= number_format($secondary['devices_total']) ?></strong></div>
                <div><span class="text-muted">Lessons today</span><strong><?= number_format($secondary['lessons_today']) ?></strong></div>
                <div><span class="text-muted">Failed logins 24h</span><strong style="color:<?= $secondary['failed_logins_24h'] > 5 ? 'var(--danger)' : 'inherit' ?>"><?= number_format($secondary['failed_logins_24h']) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<!-- Top events strip -->
<?php if (!empty($topEvents)): ?>
<div class="card">
    <div class="card-header flex-row">
        <h3 style="margin:0">Top events (7d)</h3>
        <div class="spacer"></div>
        <a href="/admin/analytics/events" class="text-muted" style="font-size:12px">Filter log →</a>
    </div>
    <div class="event-pills">
        <?php $maxE = max(array_column($topEvents, 'c')); ?>
        <?php foreach ($topEvents as $e):
            [$ico, $col] = $eventIcon((string) $e['event_name']);
            $w = $maxE > 0 ? round(((int) $e['c'] / $maxE) * 100) : 0;
        ?>
            <div class="event-pill">
                <div class="event-pill-head">
                    <span class="event-dot" style="background:<?= $col ?>22;color:<?= $col ?>"><?= $ico ?></span>
                    <span style="flex:1;font-weight:600;font-size:13px"><?= View::e($e['event_name']) ?></span>
                    <strong><?= number_format((int) $e['c']) ?></strong>
                </div>
                <div class="event-bar"><div class="event-bar-fill" style="width:<?= $w ?>%;background:<?= $col ?>"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent enrollments table -->
<div class="card">
    <div class="card-header flex-row">
        <h3 style="margin:0">Recent enrollments</h3>
        <div class="spacer"></div>
        <a href="/admin/users" class="text-muted" style="font-size:12px">All learners →</a>
    </div>
    <?php if (empty($recentEnrollments)): ?>
        <div class="empty-state"><div class="empty-icon">＋</div><p>No enrollments yet.</p></div>
    <?php else: ?>
    <table class="table" style="margin-bottom:0">
        <thead><tr>
            <th>Learner</th><th>Course</th><th>Enrolled</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recentEnrollments as $e): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <span class="avatar" style="background:linear-gradient(135deg,var(--primary),#22D3EE)">
                            <?= View::e(strtoupper(substr((string) ($e['full_name'] ?? '?'), 0, 1))) ?>
                        </span>
                        <div>
                            <strong><?= View::e($e['full_name'] ?? '—') ?></strong>
                            <div class="text-muted" style="font-size:11px"><?= View::e((string) ($e['email'] ?? '')) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="course-cell">
                        <span class="course-swatch" style="background:<?= View::e((string) ($e['cover_color_hex'] ?? '#7C5CFF')) ?>"></span>
                        <?= View::e($e['course_title'] ?? $e['course_id']) ?>
                    </div>
                </td>
                <td class="text-muted" style="font-size:12px"><?= View::e(substr((string) ($e['enrolled_at'] ?? ''), 0, 16)) ?></td>
                <td>
                    <?php if (!empty($e['completed_at'])): ?>
                        <span class="badge badge-success">Completed</span>
                    <?php elseif (!empty($e['progress_pct']) && (int) $e['progress_pct'] > 0): ?>
                        <span class="badge badge-info">In progress · <?= (int) $e['progress_pct'] ?>%</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Not started</span>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <a href="/admin/users/<?= View::e(urlencode((string) $e['user_id'])) ?>" class="btn btn-ghost btn-sm">Open</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Analytics';
include __DIR__ . '/../../layouts/admin.php';
