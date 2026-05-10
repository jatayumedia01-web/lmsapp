<?php
/** @var array $courses */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Courses</h2>
        <p>Edit catalog content — changes appear in the app on its next sync.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/courses/new" class="btn btn-primary">+ New course</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($courses)): ?>
    <div class="card">
        <p>No courses yet. <a href="/admin/courses/new">Create your first course</a> to populate the app.</p>
    </div>
<?php else: ?>
    <table class="table">
        <thead><tr>
            <th>Title</th><th>Category</th><th>Difficulty</th>
            <th class="text-right">Lessons</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr>
                <td>
                    <strong><?= View::e($c['title']) ?></strong>
                    <div class="text-muted" style="font-size:12px"><?= View::e($c['subtitle']) ?></div>
                </td>
                <td><?= View::e($c['category']) ?></td>
                <td><span class="badge badge-muted"><?= View::e($c['difficulty']) ?></span></td>
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
                    <a href="/admin/courses/<?= View::e($c['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                    <a href="/admin/courses/<?= View::e($c['id']) ?>/lessons" class="btn btn-ghost btn-sm">Lessons</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Courses';
include __DIR__ . '/../../layouts/admin.php';
