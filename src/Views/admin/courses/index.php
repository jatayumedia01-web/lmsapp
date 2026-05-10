<?php
/** @var array $courses */
/** @var array $classes */
/** @var string $classId */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <h2>Subjects</h2>
        <p>Each subject is a learning track inside a class. Lessons live inside subjects.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/courses/new<?= $classId !== '' ? '?class_id=' . urlencode($classId) : '' ?>" class="btn btn-primary">+ New subject</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:0 0 240px">
        <label>Filter by class</label>
        <select name="class_id" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($classes as $cl): ?>
                <option value="<?= View::e($cl['id']) ?>" <?= $classId === $cl['id'] ? 'selected' : '' ?>><?= View::e($cl['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <a href="/admin/courses" class="btn btn-ghost btn-sm">Reset</a>
    </div>
</form>

<?php if (empty($courses)): ?>
    <div class="card">
        <p>No subjects <?= $classId !== '' ? 'in this class' : 'yet' ?>. <a href="/admin/courses/new<?= $classId !== '' ? '?class_id=' . urlencode($classId) : '' ?>">Create one</a>.</p>
    </div>
<?php else: ?>
    <table class="table">
        <thead><tr>
            <th>Subject</th><th>Class</th><th>Category</th>
            <th class="text-right">Lessons</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td>
                    <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= View::e((string) $c['cover_color_hex']) ?>;margin-right:8px;vertical-align:middle"></span>
                    <strong><?= View::e($c['title']) ?></strong>
                    <div class="text-muted" style="font-size:12px"><?= View::e($c['subtitle']) ?></div>
                </td>
                <td>
                    <?php if (!empty($c['class_name'])): ?>
                        <span class="badge badge-muted" style="background:<?= View::e((string) ($c['class_color'] ?? '#7C5CFF')) ?>22;color:<?= View::e((string) ($c['class_color'] ?? '#7C5CFF')) ?>">
                            <?= View::e($c['class_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-dim">—</span>
                    <?php endif; ?>
                </td>
                <td><?= View::e($c['category']) ?></td>
                <td class="text-right"><?= (int) $c['total_lessons'] ?></td>
                <td>
                    <?php if ((int) $c['is_published']): ?>
                        <span class="badge badge-success">Published</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Draft</span>
                    <?php endif; ?>
                    <?php if ((int) $c['is_premium']): ?>
                        <span class="badge badge-primary">Premium</span>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <a href="/admin/courses/<?= View::e($c['id']) ?>/lessons" class="btn btn-secondary btn-sm">Lessons</a>
                    <a href="/admin/courses/<?= View::e($c['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Subjects';
include __DIR__ . '/../../layouts/admin.php';
