<?php
/** @var array $rows */
/** @var bool $onlyFailed */
/** @var string $userId */
/** @var array $me */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <h2>Login audit</h2>
        <p>Most recent 200 login attempts. Use this to chase suspicious activity (failures from one IP, impossible-travel logins, etc.).</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<form method="get" class="card filter-bar">
    <div class="field">
        <label>Filter</label>
        <select name="failed">
            <option value="">All attempts</option>
            <option value="1" <?= $onlyFailed ? 'selected' : '' ?>>Failed only</option>
        </select>
    </div>
    <div class="field" style="flex:2">
        <label>User id</label>
        <input name="user_id" type="text" value="<?= View::e($userId) ?>" placeholder="u_xxx">
    </div>
    <div class="field" style="align-self:flex-end">
        <button class="btn btn-primary">Apply</button>
        <a href="/admin/analytics/logins" class="btn btn-ghost">Reset</a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><p>No login attempts logged in this view.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>When</th><th>User</th><th>Surface</th><th>From</th><th>UA</th><th>Result</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="text-muted" style="white-space:nowrap;font-size:11px">
                <?= View::e(substr((string) $r['attempted_at'], 0, 19)) ?>
            </td>
            <td>
                <?php if (!empty($r['user_id'])): ?>
                    <a href="/admin/users/<?= View::e(rawurlencode((string) $r['user_id'])) ?>">
                        <?= View::e((string) ($r['full_name'] ?? $r['user_id'])) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">unknown</span>
                <?php endif; ?>
                <div class="text-muted" style="font-size:11px"><?= View::e((string) ($r['email'] ?? $r['email_attempted'] ?? '')) ?></div>
            </td>
            <td>
                <span class="badge badge-muted"><?= View::e($r['surface']) ?></span>
                <span class="badge badge-muted"><?= View::e($r['method']) ?></span>
            </td>
            <td>
                <code><?= View::e($r['ip_address']) ?></code>
                <?php if (!empty($r['country'])): ?>
                    <div class="text-muted" style="font-size:11px"><?= View::e((string) $r['city']) ?>, <?= View::e((string) $r['country']) ?></div>
                <?php endif; ?>
            </td>
            <td class="text-muted" style="font-size:11px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?= View::e((string) $r['user_agent']) ?>
            </td>
            <td>
                <?php if ((int) $r['success'] === 1): ?>
                    <span class="badge badge-success">Success</span>
                <?php else: ?>
                    <span class="badge badge-danger" title="<?= View::e((string) ($r['failure_reason'] ?? '')) ?>">Failed</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Login audit';
include __DIR__ . '/../../layouts/admin.php';
