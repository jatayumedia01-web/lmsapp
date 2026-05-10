<?php
/** @var array $rows */
/** @var array $eventNames */
/** @var string $name */
/** @var string $userId */
/** @var int $hours */
/** @var array $me */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <h2>Event log</h2>
        <p>Most recent 200 events matching the filter. Use this to debug per-user issues or eyeball traffic shape.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/analytics" class="btn btn-ghost btn-sm">← Overview</a>
</header>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:1.5">
        <label>Event name</label>
        <select name="name">
            <option value="">Any</option>
            <?php foreach ($eventNames as $e): ?>
                <option value="<?= View::e($e['event_name']) ?>" <?= $name === $e['event_name'] ? 'selected' : '' ?>>
                    <?= View::e($e['event_name']) ?> · <?= number_format((int) $e['c']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="flex:1.5">
        <label>User id</label>
        <input name="user_id" type="text" value="<?= View::e($userId) ?>" placeholder="u_xxx">
    </div>
    <div class="field">
        <label>Window</label>
        <select name="hours">
            <?php foreach ([1, 6, 24, 72, 168, 720] as $h): ?>
                <option value="<?= $h ?>" <?= $hours === $h ? 'selected' : '' ?>>Last <?= $h ?>h</option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button class="btn btn-primary">Apply</button>
        <a href="/admin/analytics/events" class="btn btn-ghost">Reset</a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><p>No events match.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Time</th><th>User</th><th>Event</th><th>Context</th><th>Props</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td class="text-muted" style="white-space:nowrap;font-size:11px">
                <?= View::e(substr((string) $r['occurred_at'], 0, 19)) ?>
            </td>
            <td>
                <a href="/admin/users/<?= View::e(urlencode($r['user_id'])) ?>"><?= View::e($r['full_name'] ?? $r['user_id']) ?></a>
                <div class="text-muted" style="font-size:11px"><?= View::e((string) ($r['email'] ?? '')) ?></div>
            </td>
            <td><code><?= View::e($r['event_name']) ?></code></td>
            <td>
                <?php if (!empty($r['screen'])): ?>
                    <span class="badge badge-muted"><?= View::e($r['screen']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['course_title'])): ?>
                    <div style="font-size:12px;margin-top:4px"><?= View::e($r['course_title']) ?></div>
                <?php endif; ?>
                <?php if (!empty($r['lesson_title'])): ?>
                    <div class="text-muted" style="font-size:11px"><?= View::e($r['lesson_title']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($r['props_json'])): ?>
                    <code style="font-size:11px;display:block;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= View::e($r['props_json']) ?></code>
                <?php elseif (isset($r['value_numeric'])): ?>
                    <span class="text-muted">val: <?= View::e((string) $r['value_numeric']) ?></span>
                <?php else: ?>
                    <span class="text-dim">—</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Event log';
include __DIR__ . '/../../layouts/admin.php';
