<?php
/** @var array $class */
/** @var string $mode */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action = $mode === 'create' ? '/admin/classes' : '/admin/classes/' . $class['id'];
$heading = $mode === 'create' ? 'New class' : 'Edit class';

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/classes">← Back to classes</a></p>
        <h2><?= View::e($heading) ?></h2>
    </div>
</header>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        Please fix:
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
            <label for="id">Class id (optional)</label>
            <input id="id" name="id" type="text" value="<?= View::e((string) ($class['id'] ?? '')) ?>" placeholder="auto: cls_xxxx">
        </div>
    <?php endif; ?>

    <div class="field-row">
        <div class="field">
            <label for="name">Name *</label>
            <input id="name" name="name" type="text" value="<?= View::e((string) $class['name']) ?>" required placeholder="e.g. Class 10">
        </div>
        <div class="field">
            <label for="level">Level (optional)</label>
            <input id="level" name="level" type="text" value="<?= View::e((string) $class['level']) ?>" placeholder="e.g. CBSE / NEET / JEE">
        </div>
    </div>

    <div class="field">
        <label for="slug">URL slug</label>
        <input id="slug" name="slug" type="text" value="<?= View::e((string) ($class['slug'] ?? '')) ?>" placeholder="auto from name">
        <small class="text-muted">Lowercase, no spaces. Used in app URLs.</small>
    </div>

    <div class="field">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="3" required><?= View::e((string) $class['description']) ?></textarea>
    </div>

    <div class="field-row">
        <div class="field">
            <label for="cover_color_hex">Cover color</label>
            <input id="cover_color_hex" name="cover_color_hex" type="color" value="<?= View::e((string) $class['cover_color_hex']) ?>" style="height:42px;padding:4px">
        </div>
        <div class="field">
            <label for="cover_image_url">Cover image URL (optional)</label>
            <input id="cover_image_url" name="cover_image_url" type="url" value="<?= View::e((string) ($class['cover_image_url'] ?? '')) ?>" placeholder="https://.../banner.jpg">
        </div>
        <div class="field">
            <label for="sort_order">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" value="<?= (int) $class['sort_order'] ?>">
        </div>
    </div>

    <div class="field">
        <label><input type="checkbox" name="is_published" value="1" <?= ((int) $class['is_published']) ? 'checked' : '' ?>> Published (visible to learners)</label>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Create class' : 'Save changes' ?></button>
        <a href="/admin/classes" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this class? Subjects inside it become uncategorised."
                    onclick="document.getElementById('class-delete').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="class-delete" method="post" action="/admin/classes/<?= View::e($class['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
