<?php
/** @var array $class */
/** @var array $subjects */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/classes">← Back to classes</a></p>
        <h2>
            <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:<?= View::e((string) $class['cover_color_hex']) ?>;vertical-align:middle;margin-right:6px"></span>
            <?= View::e($class['name']) ?>
            <?php if (!empty($class['level'])): ?>
                <span class="badge badge-muted" style="font-size:11px;margin-left:8px"><?= View::e($class['level']) ?></span>
            <?php endif; ?>
        </h2>
        <p class="text-muted"><?= View::e($class['description']) ?></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/classes/<?= View::e($class['id']) ?>" class="btn btn-ghost btn-sm">Edit class</a>
    <a href="/admin/courses/new?class_id=<?= View::e(rawurlencode($class['id'])) ?>" class="btn btn-primary">+ New subject</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($subjects)): ?>
    <div class="card">
        <p>No subjects in this class yet. <a href="/admin/courses/new?class_id=<?= View::e(rawurlencode($class['id'])) ?>">Add the first subject</a> — Mathematics, Physics, etc.</p>
    </div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Subject</th><th>Category</th><th>Difficulty</th>
        <th class="text-right">Lessons</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($subjects as $s): ?>
        <tr>
            <td>
                <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= View::e((string) $s['cover_color_hex']) ?>;margin-right:8px;vertical-align:middle"></span>
                <strong><?= View::e($s['title']) ?></strong>
                <div class="text-muted" style="font-size:12px"><?= View::e($s['subtitle']) ?></div>
            </td>
            <td><?= View::e($s['category']) ?></td>
            <td><span class="badge badge-muted"><?= View::e($s['difficulty']) ?></span></td>
            <td class="text-right"><?= (int) $s['total_lessons'] ?></td>
            <td>
                <?php if ((int) $s['is_published']): ?>
                    <span class="badge badge-success">Published</span>
                <?php else: ?>
                    <span class="badge badge-warning">Draft</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <a href="/admin/courses/<?= View::e($s['id']) ?>/lessons" class="btn btn-secondary btn-sm">Lessons</a>
                <a href="/admin/courses/<?= View::e($s['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $class['name'] . ' · subjects';
include __DIR__ . '/../../layouts/admin.php';
