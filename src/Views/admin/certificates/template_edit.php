<?php
/** @var array $template */
/** @var string $mode */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action = $mode === 'create' ? '/admin/certificates/templates' : '/admin/certificates/templates/' . $template['id'];
$heading = $mode === 'create' ? 'New certificate template' : 'Edit certificate template';

ob_start();
?>
<header>
    <div>
        <p><a href="/admin/certificates">← Back to certificates</a></p>
        <h2><?= View::e($heading) ?></h2>
    </div>
    <?php if ($mode === 'edit'): ?>
        <div class="spacer"></div>
        <a href="/admin/certificates/templates/<?= View::e($template['id']) ?>/preview" target="_blank" class="btn btn-secondary">Preview ↗</a>
    <?php endif; ?>
</header>

<form method="post" action="<?= View::e($action) ?>" class="card">
    <?php if ($mode === 'create'): ?>
        <div class="field"><label>Template ID</label><input name="id" type="text" placeholder="auto: tpl_xxxx"></div>
    <?php endif; ?>
    <div class="field"><label>Name *</label><input name="name" type="text" value="<?= View::e($template['name']) ?>" required></div>
    <div class="field"><label>Description</label><input name="description" type="text" value="<?= View::e((string) ($template['description'] ?? '')) ?>"></div>

    <div class="field">
        <label>HTML *</label>
        <textarea name="html_template" rows="12" style="font-family:monospace;font-size:12px" required><?= View::e((string) $template['html_template']) ?></textarea>
        <small class="text-muted">Placeholders: <code>{{user_name}}</code>, <code>{{course_title}}</code>, <code>{{certificate_number}}</code>, <code>{{issued_date}}</code>, <code>{{score}}</code></small>
    </div>

    <div class="field">
        <label>CSS</label>
        <textarea name="css" rows="10" style="font-family:monospace;font-size:12px"><?= View::e((string) ($template['css'] ?? '')) ?></textarea>
    </div>

    <div class="field"><label><input type="checkbox" name="is_default" value="1" <?= ((int) ($template['is_default'] ?? 0)) ? 'checked' : '' ?>> Default template (auto-used when no other is picked)</label></div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Create' : 'Save changes' ?></button>
        <a href="/admin/certificates" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this template?"
                    onclick="document.getElementById('tpl-del').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>
<?php if ($mode === 'edit'): ?>
    <form id="tpl-del" method="post" action="/admin/certificates/templates/<?= View::e($template['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
