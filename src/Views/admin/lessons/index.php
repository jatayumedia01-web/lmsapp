<?php
/** @var array $course */
/** @var array $lessons */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Lessons · <?= View::e($course['title']) ?></h2>
        <p><a href="/admin/courses">← All courses</a> · <a href="/admin/courses/<?= View::e($course['id']) ?>">Edit course</a></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/courses/<?= View::e($course['id']) ?>/lessons/new" class="btn btn-primary">+ New lesson</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($lessons)): ?>
    <div class="card">
        <p>No lessons yet. <a href="/admin/courses/<?= View::e($course['id']) ?>/lessons/new">Add the first one</a>.</p>
    </div>
<?php else: ?>
    <table class="table">
        <thead><tr>
            <th>#</th><th>Title</th><th class="text-right">Duration</th><th>Free preview</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($lessons as $l): ?>
            <tr>
                <td><?= (int) $l['order_index'] + 1 ?></td>
                <td>
                    <strong><?= View::e($l['title']) ?></strong>
                    <div class="text-muted" style="font-size:12px"><?= View::e($l['video_url']) ?></div>
                </td>
                <td class="text-right"><?= (int) $l['duration_seconds'] / 60 ?> min</td>
                <td>
                    <?php if ((int) $l['is_free_preview']): ?>
                        <span class="badge badge-success">Free</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Locked</span>
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <a href="/admin/lessons/<?= View::e($l['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Lessons';
include __DIR__ . '/../../layouts/admin.php';
