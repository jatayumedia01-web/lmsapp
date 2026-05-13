<?php
/** @var string $group  current group key */
/** @var array $groups  ['key' => 'Label'] */
/** @var array $rows    settings rows for current group */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Settings</h2>
        <p>Runtime config — these are read by the API and the app shell.</p>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<nav class="tabs">
    <?php foreach ($groups as $key => $label): ?>
        <a href="?group=<?= rawurlencode($key) ?>" class="tab <?= $group === $key ? 'active' : '' ?>"><?= View::e($label) ?></a>
    <?php endforeach; ?>
</nav>

<form method="post" action="/admin/settings" class="card">
    <input type="hidden" name="group" value="<?= View::e($group) ?>">

    <?php foreach ($rows as $r): ?>
        <div class="field">
            <label for="set-<?= View::e($r['key']) ?>">
                <?= View::e($r['label']) ?>
                <?php if ((int) $r['is_secret']): ?><span class="badge badge-warning" style="margin-left:6px;font-size:10px">SECRET</span><?php endif; ?>
            </label>
            <?php if ($r['value_type'] === 'BOOL'): ?>
                <input type="checkbox" id="set-<?= View::e($r['key']) ?>" name="<?= View::e($r['key']) ?>" value="1" <?= ((string) $r['value'] === '1') ? 'checked' : '' ?>>
            <?php elseif ($r['value_type'] === 'INT'): ?>
                <input type="number" id="set-<?= View::e($r['key']) ?>" name="<?= View::e($r['key']) ?>" value="<?= View::e((string) $r['value']) ?>">
            <?php elseif ($r['value_type'] === 'JSON' || (strlen((string) $r['value']) > 80)): ?>
                <textarea id="set-<?= View::e($r['key']) ?>" name="<?= View::e($r['key']) ?>" rows="4"><?= View::e((string) $r['value']) ?></textarea>
            <?php else: ?>
                <input type="<?= ((int) $r['is_secret']) ? 'password' : 'text' ?>"
                       id="set-<?= View::e($r['key']) ?>"
                       name="<?= View::e($r['key']) ?>"
                       value="<?= ((int) $r['is_secret']) ? '' : View::e((string) $r['value']) ?>"
                       placeholder="<?= ((int) $r['is_secret'] && (string) $r['value'] !== '') ? '•••••• (set — leave blank to keep)' : '' ?>">
            <?php endif; ?>
            <?php if (!empty($r['description'])): ?>
                <small class="text-muted"><?= View::e($r['description']) ?></small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary">Save <?= View::e($groups[$group]) ?></button>
    </div>
</form>
<?php
$content = ob_get_clean();
$title   = 'Settings · ' . $groups[$group];
include __DIR__ . '/../../layouts/admin.php';
