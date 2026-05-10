<?php
/** @var array $issued */
/** @var array $templates */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header>
    <div>
        <h2>Certificates</h2>
        <p>Templates and the audit log of every issued certificate.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/certificates/templates/new" class="btn btn-primary">+ New template</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h3>Templates</h3>
        <?php if (empty($templates)): ?>
            <p class="text-muted">No templates yet.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Name</th><th>Default</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td>
                            <strong><?= View::e($t['name']) ?></strong>
                            <div class="text-muted" style="font-size:11px"><?= View::e((string) ($t['description'] ?? '')) ?></div>
                        </td>
                        <td><?= ((int) $t['is_default']) ? '<span class="badge badge-success">Default</span>' : '—' ?></td>
                        <td class="text-right">
                            <a href="/admin/certificates/templates/<?= View::e($t['id']) ?>/preview" target="_blank" class="btn btn-ghost btn-sm">Preview</a>
                            <a href="/admin/certificates/templates/<?= View::e($t['id']) ?>" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recently issued</h3>
        <?php if (empty($issued)): ?>
            <p class="text-muted">No certificates issued yet. They auto-issue when learners complete a course.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>#</th><th>Learner</th><th>Course</th><th>Issued</th><th></th></tr></thead>
                <tbody>
                <?php foreach (array_slice($issued, 0, 12) as $c): ?>
                    <tr>
                        <td><code style="font-size:11px"><?= View::e($c['certificate_number']) ?></code></td>
                        <td>
                            <a href="/admin/users/<?= View::e(urlencode((string) $c['user_id'])) ?>"><?= View::e($c['user_name_snapshot']) ?></a>
                        </td>
                        <td><?= View::e((string) ($c['course_title_snapshot'] ?? '—')) ?></td>
                        <td class="text-muted" style="font-size:11px"><?= View::e(substr((string) $c['issued_at'], 0, 10)) ?></td>
                        <td class="text-right">
                            <a href="/verify/<?= View::e($c['certificate_number']) ?>" target="_blank" class="btn btn-ghost btn-sm">Open</a>
                            <?php if (empty($c['revoked_at'])): ?>
                                <form method="post" action="/admin/certificates/<?= View::e($c['id']) ?>/revoke" style="display:inline">
                                    <button type="submit" class="btn btn-danger btn-sm" data-confirm="Revoke this certificate?">Revoke</button>
                                </form>
                            <?php else: ?>
                                <span class="badge badge-danger">Revoked</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title   = 'Certificates';
include __DIR__ . '/../../layouts/admin.php';
