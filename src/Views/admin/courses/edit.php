<?php
/** @var array $course */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var array $classes */
/** @var string $page */
use Devithor\View;

$action = $mode === 'create' ? '/admin/courses' : '/admin/courses/' . $course['id'];
$heading = $mode === 'create' ? 'New subject' : 'Edit subject';

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/courses">← All subjects</a></p>
        <h2><?= View::e($heading) ?></h2>
    </div>
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
            <label for="id">Subject ID (optional — auto-generated if blank)</label>
            <input id="id" name="id" type="text" value="<?= View::e((string) ($course['id'] ?? '')) ?>" placeholder="e.g. c_maths_algebra">
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id">
            <option value="">— Uncategorised —</option>
            <?php foreach ($classes as $cl): ?>
                <option value="<?= View::e($cl['id']) ?>" <?= ($course['class_id'] ?? '') === $cl['id'] ? 'selected' : '' ?>><?= View::e($cl['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <small class="text-muted">Pick the parent class. <a href="/admin/classes/new">Create a class</a> if needed.</small>
    </div>

    <div class="field">
        <label for="title">Title *</label>
        <input id="title" name="title" type="text" value="<?= View::e((string) $course['title']) ?>" required placeholder="e.g. Mathematics">
    </div>

    <div class="field">
        <label for="subtitle">Subtitle</label>
        <input id="subtitle" name="subtitle" type="text" value="<?= View::e($course['subtitle']) ?>">
    </div>

    <div class="field">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="4" required><?= View::e($course['description']) ?></textarea>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="instructor_name">Instructor name *</label>
            <input id="instructor_name" name="instructor_name" type="text" value="<?= View::e($course['instructor_name']) ?>" required>
        </div>
        <div class="field">
            <label for="category">Category *</label>
            <input id="category" name="category" type="text" value="<?= View::e($course['category']) ?>" placeholder="Mobile, Backend, AI…" required>
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="difficulty">Difficulty *</label>
            <select id="difficulty" name="difficulty">
                <?php foreach (['BEGINNER','INTERMEDIATE','ADVANCED'] as $d): ?>
                    <option value="<?= $d ?>" <?= $course['difficulty'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="cover_color_hex">Cover color hex</label>
            <input id="cover_color_hex" name="cover_color_hex" type="text" value="<?= View::e($course['cover_color_hex']) ?>" placeholder="#7C5CFF">
        </div>
    </div>

    <div class="field">
        <label for="cover_image_url">Cover image URL (optional)</label>
        <input id="cover_image_url" name="cover_image_url" type="url" value="<?= View::e($course['cover_image_url'] ?? '') ?>" placeholder="https://...">
    </div>

    <div class="field-row">
        <div class="field">
            <label for="duration_minutes">Total duration (minutes)</label>
            <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="<?= (int) $course['duration_minutes'] ?>">
        </div>
        <div class="field">
            <label for="rating">Rating (0.0 – 5.0)</label>
            <input id="rating" name="rating" type="text" value="<?= View::e((string) $course['rating']) ?>">
        </div>
    </div>

    <div class="field-row">
        <div class="field">
            <label><input type="checkbox" name="is_premium" value="1" <?= !empty($course['is_premium']) ? 'checked' : '' ?>> Premium plan only</label>
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_published" value="1" <?= !empty($course['is_published']) ? 'checked' : '' ?>> Published (visible in app)</label>
        </div>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Create course' : 'Save changes' ?>
        </button>
        <a href="/admin/courses" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button"
                    class="btn btn-danger btn-sm"
                    data-confirm="Delete this course and all its lessons?"
                    onclick="document.getElementById('delete-form').submit()">
                Delete
            </button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="delete-form" method="post" action="/admin/courses/<?= View::e($course['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
