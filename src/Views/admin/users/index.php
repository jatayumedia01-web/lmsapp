<?php
/** @var array $users */
/** @var string $q */
/** @var string $role */
/** @var string $banned */
/** @var string $sort */
/** @var string $dir */
/** @var int $pageNo */
/** @var int $pages */
/** @var int $total */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

$qs = function (array $overrides) use ($q, $role, $banned, $sort, $dir, $pageNo) {
    $base = compact('q', 'role', 'banned', 'sort', 'dir');
    $base['page'] = $pageNo;
    $merged = array_merge($base, $overrides);
    return http_build_query(array_filter($merged, fn ($v) => $v !== '' && $v !== null));
};
$sortLink = function (string $col, string $label) use ($sort, $dir, $qs) {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $arrow  = $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . $qs(['sort' => $col, 'dir' => $newDir, 'page' => 1]) . '">' . htmlspecialchars($label) . $arrow . '</a>';
};

ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Learners &amp; users</h2>
        <p><?= (int) $total ?> total · view, search, ban / unban, change role.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/users/export.csv" class="btn btn-ghost">Export CSV</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:2">
        <label for="q">Search</label>
        <input id="q" name="q" type="text" value="<?= View::e($q) ?>" placeholder="email, name, or id">
    </div>
    <div class="field">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="">Any</option>
            <?php foreach (['STUDENT','INSTRUCTOR','ADMIN','PARENT'] as $r): ?>
                <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="banned">Banned</label>
        <select id="banned" name="banned">
            <option value="">Any</option>
            <option value="0" <?= $banned === '0' ? 'selected' : '' ?>>Active</option>
            <option value="1" <?= $banned === '1' ? 'selected' : '' ?>>Banned only</option>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a href="/admin/users" class="btn btn-ghost">Reset</a>
    </div>
</form>

<?php if (empty($users)): ?>
    <div class="card"><p>No users match these filters.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th><?= $sortLink('full_name', 'Name') ?></th>
        <th><?= $sortLink('email', 'Email') ?></th>
        <th>Role</th>
        <th class="text-right"><?= $sortLink('xp', 'XP') ?></th>
        <th><?= $sortLink('joined_at', 'Joined') ?></th>
        <th><?= $sortLink('last_sign_in_at', 'Last sign-in') ?></th>
        <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <strong><?= View::e($u['full_name']) ?></strong>
                <?php if ((int) $u['is_banned']): ?>
                    <span class="badge badge-danger" style="margin-left:6px">BANNED</span>
                <?php endif; ?>
                <div class="text-muted" style="font-size:11px"><?= View::e($u['id']) ?></div>
            </td>
            <td><?= View::e($u['email']) ?></td>
            <td><span class="badge badge-muted"><?= View::e($u['role']) ?></span></td>
            <td class="text-right"><?= (int) $u['xp'] ?></td>
            <td class="text-muted"><?= View::e(substr((string) $u['joined_at'], 0, 10)) ?></td>
            <td class="text-muted"><?= View::e($u['last_sign_in_at'] ? substr((string) $u['last_sign_in_at'], 0, 10) : '—') ?></td>
            <td class="text-right">
                <a href="/admin/users/<?= View::e(urlencode($u['id'])) ?>" class="btn btn-secondary btn-sm">Open</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<nav class="pager flex-row" style="margin-top:16px">
    <?php if ($pageNo > 1): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= $qs(['page' => $pageNo - 1]) ?>">← Prev</a>
    <?php endif; ?>
    <span class="text-muted">Page <?= (int) $pageNo ?> / <?= (int) $pages ?></span>
    <div class="spacer"></div>
    <?php if ($pageNo < $pages): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= $qs(['page' => $pageNo + 1]) ?>">Next →</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Users';
include __DIR__ . '/../../layouts/admin.php';
