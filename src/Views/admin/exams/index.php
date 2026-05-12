<?php use Devithor\View; ob_start(); ?>
<header>
    <div><h2>Mock Exams</h2><p>Create and manage timed exams for students</p></div>
    <div class="spacer"></div>
    <a href="/admin/exams/new" class="btn btn-primary">+ New exam</a>
</header>

<?php if (empty($exams)): ?>
<div class="card" style="text-align:center;padding:48px">
    <p class="text-muted">No exams yet. Create your first mock test.</p>
    <a href="/admin/exams/new" class="btn btn-primary" style="margin-top:12px">+ New exam</a>
</div>
<?php else: ?>
<div class="card" style="padding:0">
<table class="table" style="margin:0">
<thead><tr><th>Title</th><th>Class</th><th>Subject</th><th>Duration</th><th>Marks</th><th>Questions</th><th>Attempts</th><th>Pass rate</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($exams as $e): ?>
<?php $passRate = $e['attempt_count'] > 0 ? round(($e['pass_count'] / $e['attempt_count']) * 100) : null; ?>
<tr>
    <td><a href="/admin/exams/<?= View::e($e['id']) ?>/questions"><strong><?= View::e($e['title']) ?></strong></a></td>
    <td class="text-muted"><?= View::e($e['class_name'] ?? '—') ?></td>
    <td class="text-muted"><?= View::e($e['subject_tag'] ?? '—') ?></td>
    <td class="text-muted"><?= (int)$e['duration_minutes'] ?> min</td>
    <td class="text-muted"><?= (int)$e['pass_marks'] ?>/<?= (int)$e['total_marks'] ?></td>
    <td><span class="badge badge-muted"><?= (int)$e['question_count'] ?> Qs</span></td>
    <td class="text-muted"><?= (int)$e['attempt_count'] ?></td>
    <td>
        <?php if ($passRate !== null): ?>
            <span class="badge <?= $passRate >= 50 ? 'badge-success' : 'badge-danger' ?>"><?= $passRate ?>%</span>
        <?php else: ?>
            <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($e['is_published']): ?>
            <span class="badge badge-success">Live</span>
        <?php else: ?>
            <span class="badge badge-muted">Draft</span>
        <?php endif; ?>
    </td>
    <td style="white-space:nowrap">
        <a href="/admin/exams/<?= View::e($e['id']) ?>" class="btn btn-ghost btn-sm">Edit</a>
        <a href="/admin/exams/<?= View::e($e['id']) ?>/results" class="btn btn-ghost btn-sm">Results</a>
        <form method="post" action="/admin/exams/<?= View::e($e['id']) ?>/publish" style="display:inline">
            <button class="btn btn-ghost btn-sm"><?= $e['is_published'] ? 'Unpublish' : 'Publish' ?></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Mock Exams'; include __DIR__ . '/../../layouts/admin.php'; ?>
