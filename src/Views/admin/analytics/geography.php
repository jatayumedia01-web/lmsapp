<?php
/** @var array $rows */
/** @var array $cityRows */
/** @var int $days */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$totalUsers = array_sum(array_column($rows, 'users_count')) ?: 1;

ob_start();
?>
<header>
    <div>
        <h2>Geography</h2>
        <p>Where your learners open the app. Resolved from session IPs via ip-api.com (cached 7d per IP).</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:0 0 200px">
        <label>Window</label>
        <select name="days">
            <?php foreach ([1, 7, 30, 90, 365] as $d): ?>
                <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Last <?= $d ?> day<?= $d > 1 ? 's' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button class="btn btn-primary">Apply</button>
    </div>
</form>

<div class="grid-2">
    <div class="card">
        <h3>Countries</h3>
        <?php if (empty($rows)): ?>
            <p class="text-muted">No geo data in this window.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Country</th><th class="text-right">Users</th><th class="text-right">Sessions</th><th>Share</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $share = (int) round(((int) $r['users_count'] / $totalUsers) * 100); ?>
                    <tr>
                        <td>
                            <?= View::e($r['country']) ?>
                            <code style="margin-left:6px"><?= View::e((string) $r['country_code']) ?></code>
                        </td>
                        <td class="text-right"><?= number_format((int) $r['users_count']) ?></td>
                        <td class="text-right text-muted"><?= number_format((int) $r['sessions_count']) ?></td>
                        <td>
                            <div style="background:var(--surface-2);height:8px;width:100px;border-radius:4px;overflow:hidden">
                                <div style="background:var(--primary);height:100%;width:<?= $share ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $share ?>%</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Top cities</h3>
        <?php if (empty($cityRows)): ?>
            <p class="text-muted">No city resolution yet.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>City</th><th>Country</th><th class="text-right">Users</th></tr></thead>
                <tbody>
                <?php foreach ($cityRows as $c): ?>
                    <tr>
                        <td><?= View::e($c['city']) ?></td>
                        <td class="text-muted"><?= View::e((string) $c['country']) ?></td>
                        <td class="text-right"><?= number_format((int) $c['users_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title   = 'Geography';
include __DIR__ . '/../../layouts/admin.php';
