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
        <h2>Quizzes</h2>
        <p>Auto-graded assessments. Attach to a lesson, subject, or class.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/quizzes/new" class="btn btn-primary">+ New quiz</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <div class="card"><p>No quizzes yet. <a href="/admin/quizzes/new">Create one</a> — pick a lesson and start adding questions.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Title</th><th>Scope</th>
        <th class="text-right">Questions</th><th class="text-right">Attempts</th>
        <th class="text-right">Pass score</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <strong><?= View::e($r['title']) ?></strong>
                <div class="text-muted" style="font-size:11px"><?= View::e(mb_substr((string) ($r['description'] ?? ''), 0, 80)) ?></div>
            </td>
            <td>
                <span class="badge badge-muted"><?= View::e($r['scope']) ?></span>
                <code style="font-size:11px"><?= View::e($r['parent_id']) ?></code>
            </td>
            <td class="text-right"><?= number_format((int) $r['questions_count']) ?></td>
            <td class="text-right"><?= number_format((int) $r['attempts_count']) ?></td>
            <td class="text-right"><?= (int) $r['pass_score_pct'] ?>%</td>
            <td>
                <?php if ((int) $r['is_published']): ?>
                    <span class="badge badge-success">Published</span>
                <?php else: ?>
                    <span class="badge badge-warning">Draft</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <a href="/admin/quizzes/<?= View::e(rawurlencode($r['id'])) ?>/questions" class="btn btn-secondary btn-sm">Questions</a>
                <a href="/admin/quizzes/<?= View::e(rawurlencode($r['id'])) ?>/attempts" class="btn btn-ghost btn-sm">Attempts</a>
                <a href="/admin/quizzes/<?= View::e(rawurlencode($r['id'])) ?>" class="btn btn-ghost btn-sm">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Quizzes';
include __DIR__ . '/../../layouts/admin.php';
