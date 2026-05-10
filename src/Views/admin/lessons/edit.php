<?php
/** @var array $course */
/** @var array $lesson */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create'
    ? '/admin/courses/' . $course['id'] . '/lessons'
    : '/admin/lessons/' . $lesson['id'];
$heading = $mode === 'create' ? 'New lesson' : 'Edit lesson';

ob_start();
?>
<header>
    <h2><?= View::e($heading) ?> · <?= View::e($course['title']) ?></h2>
    <p><a href="/admin/courses/<?= View::e($course['id']) ?>/lessons">← Back to lessons</a></p>
</header>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        Please fix the highlighted fields:
        <ul style="margin:6px 0 0 16px">
        <?php foreach ($errors as $field => $msg): ?>
            <li><strong><?= View::e($field) ?></strong>: <?= View::e($msg) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="card">
    <?php if ($mode === 'create'): ?>
        <div class="field">
            <label for="id">Lesson ID (optional)</label>
            <input id="id" name="id" type="text" value="<?= View::e($lesson['id'] ?? '') ?>" placeholder="auto: <?= View::e($course['id']) ?>_l<?= ((int) $lesson['order_index']) + 1 ?>">
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="title">Title *</label>
        <input id="title" name="title" type="text" value="<?= View::e($lesson['title']) ?>" required>
    </div>

    <div class="field">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="3" required><?= View::e($lesson['description']) ?></textarea>
    </div>

    <div class="field">
        <label for="video_url">Video URL * (HLS .m3u8 or .mp4)</label>
        <input id="video_url" name="video_url" type="url" value="<?= View::e($lesson['video_url']) ?>" required>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="order_index">Order index (0-based)</label>
            <input id="order_index" name="order_index" type="number" min="0" value="<?= (int) $lesson['order_index'] ?>">
        </div>
        <div class="field">
            <label for="duration_seconds">Duration (seconds)</label>
            <input id="duration_seconds" name="duration_seconds" type="number" min="0" value="<?= (int) $lesson['duration_seconds'] ?>">
        </div>
    </div>

    <div class="field">
        <label><input type="checkbox" name="is_free_preview" value="1" <?= !empty($lesson['is_free_preview']) ? 'checked' : '' ?>> Free preview (visible to non-paying users)</label>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Create lesson' : 'Save changes' ?>
        </button>
        <a href="/admin/courses/<?= View::e($course['id']) ?>/lessons" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button"
                    class="btn btn-danger btn-sm"
                    data-confirm="Delete this lesson?"
                    onclick="document.getElementById('delete-form').submit()">
                Delete
            </button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="delete-form" method="post" action="/admin/lessons/<?= View::e($lesson['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
