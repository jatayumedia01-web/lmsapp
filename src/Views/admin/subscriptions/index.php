<?php
/** @var array $rows */
/** @var string $status */
/** @var string $q */
/** @var int $pageNo */
/** @var int $pages */
/** @var int $total */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Subscriptions</h2>
        <p><?= (int) $total ?> total · cancel, view billing history.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/billing" class="btn btn-ghost">Back to billing</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:2">
        <label>Search</label>
        <input name="q" type="text" value="<?= View::e($q) ?>" placeholder="email, name, plan id">
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">Any</option>
            <?php foreach (['ACTIVE','TRIALING','PAST_DUE','CANCELLED','FREE'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="/admin/billing/subscriptions" class="btn btn-ghost">Reset</a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><p>No subscriptions match.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>User</th><th>Plan</th><th>Status</th><th>Cycle</th><th>Renews</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <a href="/admin/users/<?= View::e(urlencode($r['user_id'])) ?>"><?= View::e($r['full_name'] ?? $r['user_id']) ?></a>
                <div class="text-muted" style="font-size:11px"><?= View::e($r['email'] ?? '') ?></div>
            </td>
            <td><code><?= View::e($r['plan_id']) ?></code></td>
            <td><span class="badge badge-muted"><?= View::e($r['status']) ?></span></td>
            <td><?= View::e($r['billing_cycle']) ?></td>
            <td class="text-muted">
                <?= !empty($r['renews_at_millis']) ? View::e(date('Y-m-d', (int) ($r['renews_at_millis'] / 1000))) : '—' ?>
            </td>
            <td class="text-right">
                <?php if ($r['status'] !== 'CANCELLED'): ?>
                    <form method="post" action="/admin/billing/subscriptions/<?= View::e(urlencode($r['user_id'])) ?>/cancel" style="display:inline">
                        <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Cancel this subscription?">Cancel</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<nav class="pager flex-row" style="margin-top:16px">
    <?php if ($pageNo > 1): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= http_build_query(['q' => $q, 'status' => $status, 'page' => $pageNo - 1]) ?>">← Prev</a>
    <?php endif; ?>
    <span class="text-muted">Page <?= (int) $pageNo ?> / <?= (int) $pages ?></span>
    <div class="spacer"></div>
    <?php if ($pageNo < $pages): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= http_build_query(['q' => $q, 'status' => $status, 'page' => $pageNo + 1]) ?>">Next →</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Subscriptions';
include __DIR__ . '/../../layouts/admin.php';
