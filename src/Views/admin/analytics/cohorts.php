<?php
/** @var array $rows  */
/** @var array $buckets */
/** @var int $weeks */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$cellClass = function (float $pct): string {
    if ($pct === 0.0)  return 'cohort-c0';
    if ($pct < 10)     return 'cohort-c1';
    if ($pct < 25)     return 'cohort-c2';
    if ($pct < 40)     return 'cohort-c3';
    if ($pct < 60)     return 'cohort-c4';
    return 'cohort-c5';
};

ob_start();
?>
<header>
    <div>
        <h2>Cohort retention</h2>
        <p>For each signup week: % of users who returned N days later. Healthy LMS curves stay flat after Day 7.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<nav class="tabs" style="margin-bottom:20px">
    <a href="/admin/analytics" class="tab">Overview</a>
    <a href="/admin/analytics/engagement" class="tab">Engagement</a>
    <a href="/admin/analytics/cohorts" class="tab active">Cohorts</a>
    <a href="/admin/analytics/geography" class="tab">Geography</a>
    <a href="/admin/analytics/devices" class="tab">Devices</a>
    <a href="/admin/analytics/events" class="tab">Event log</a>
    <a href="/admin/analytics/logins" class="tab">Login audit</a>
</nav>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:0 0 200px">
        <label>Window</label>
        <select name="weeks">
            <?php foreach ([4, 8, 12, 16, 26] as $w): ?>
                <option value="<?= $w ?>" <?= $weeks === $w ? 'selected' : '' ?>>Last <?= $w ?> weeks</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button class="btn btn-primary">Apply</button>
    </div>
</form>

<div class="card">
    <div class="card-header flex-row">
        <h3 style="margin:0">Retention by signup cohort</h3>
        <div class="spacer"></div>
        <div class="cohort-legend">
            <span class="text-muted" style="font-size:11px">Lower</span>
            <span class="cohort-cell cohort-c1"></span>
            <span class="cohort-cell cohort-c2"></span>
            <span class="cohort-cell cohort-c3"></span>
            <span class="cohort-cell cohort-c4"></span>
            <span class="cohort-cell cohort-c5"></span>
            <span class="text-muted" style="font-size:11px">Higher</span>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state"><div class="empty-icon">📊</div><p>No signups in this window yet.</p></div>
    <?php else: ?>
    <div class="cohort-table-wrap">
        <table class="cohort-table">
            <thead>
                <tr>
                    <th>Cohort</th>
                    <th>Size</th>
                    <?php foreach ($buckets as $d): ?>
                        <th>D<?= $d ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="cohort-label"><?= View::e($row['label']) ?></td>
                        <td class="cohort-size"><?= number_format((int) $row['size']) ?></td>
                        <?php foreach ($buckets as $d):
                            $cell = $row['retention'][$d];
                            $cls  = $cellClass((float) $cell['pct']);
                        ?>
                            <td class="cohort-cell-td <?= $cls ?>"
                                title="<?= number_format((int) $cell['count']) ?> of <?= number_format((int) $row['size']) ?> users returned on D<?= $d ?>">
                                <?= $cell['pct'] > 0 ? $cell['pct'] . '%' : '·' ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Cohort retention';
include __DIR__ . '/../../layouts/admin.php';
