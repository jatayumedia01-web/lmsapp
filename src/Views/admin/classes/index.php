<?php
/** @var array $rows */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <div>
        <h2>Classes</h2>
        <p>Top-level groupings — each class holds many subjects, each subject many lessons.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/classes/new" class="btn btn-primary">+ New class</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <div class="card">
        <p>No classes yet. <a href="/admin/classes/new">Create your first class</a> — e.g. "Class 10", "NEET 2026", "JEE Mains".</p>
    </div>
<?php else: ?>
    <div class="grid-3" style="margin-bottom:20px">
        <?php foreach ($rows as $r): ?>
            <a href="/admin/classes/<?= View::e($r['id']) ?>/subjects" class="class-card" style="background:linear-gradient(135deg, <?= View::e((string) $r['cover_color_hex']) ?>22, var(--surface))">
                <div class="class-card-head">
                    <span class="class-color" style="background:<?= View::e((string) $r['cover_color_hex']) ?>"></span>
                    <?php if ((int) $r['is_published'] === 0): ?>
                        <span class="badge badge-warning" style="margin-left:auto">Draft</span>
                    <?php endif; ?>
                </div>
                <h3 class="class-name"><?= View::e($r['name']) ?></h3>
                <?php if (!empty($r['level'])): ?>
                    <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700"><?= View::e($r['level']) ?></div>
                <?php endif; ?>
                <p class="class-desc"><?= View::e(mb_substr((string) $r['description'], 0, 100)) ?><?= mb_strlen((string) $r['description']) > 100 ? '…' : '' ?></p>
                <div class="class-meta">
                    <span><?= number_format((int) $r['subjects_count']) ?> subjects</span>
                    <span>·</span>
                    <span><?= number_format((int) $r['lessons_count']) ?> lessons</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom:8px">All classes</h3>
        <table class="table" style="margin-bottom:0">
            <thead><tr>
                <th>Name</th><th>Level</th>
                <th class="text-right">Subjects</th><th class="text-right">Lessons</th>
                <th>Status</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <span class="class-color" style="background:<?= View::e((string) $r['cover_color_hex']) ?>;display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:8px;vertical-align:middle"></span>
                        <strong><?= View::e($r['name']) ?></strong>
                        <code style="font-size:11px;margin-left:6px"><?= View::e($r['slug']) ?></code>
                    </td>
                    <td><?= !empty($r['level']) ? View::e($r['level']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-right"><?= number_format((int) $r['subjects_count']) ?></td>
                    <td class="text-right"><?= number_format((int) $r['lessons_count']) ?></td>
                    <td>
                        <?php if ((int) $r['is_published']): ?>
                            <span class="badge badge-success">Published</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right" style="white-space:nowrap">
                        <a href="/admin/classes/<?= View::e($r['id']) ?>/subjects" class="btn btn-secondary btn-sm">Subjects</a>
                        <a href="/admin/classes/<?= View::e($r['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
                        <form method="post" action="/admin/classes/<?= View::e($r['id']) ?>/delete" style="display:inline"
                              onsubmit="return confirm('Delete class &quot;<?= addslashes(View::e($r['name'])) ?>&quot;?\n\nThis will permanently delete the class AND all its <?= (int)$r['subjects_count'] ?> subject(s) and <?= (int)$r['lessons_count'] ?> lesson(s).')">
                            <button class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;cursor:pointer">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Classes';
include __DIR__ . '/../../layouts/admin.php';
