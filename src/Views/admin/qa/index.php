<?php
/** @var array $rows */
/** @var array $courses */
/** @var array $counts */
/** @var string $status */
/** @var string $courseId */
/** @var string $q */
/** @var int $pageNo */
/** @var int $pages */
/** @var int $total */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Q&amp;A moderation</h2>
        <p>Approve, reject, or remove learner questions and answers.</p>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-stats" style="margin-bottom:16px">
    <a class="card stat" href="?status=PENDING"><div class="stat-label">Pending</div><div class="stat-value"><?= (int) $counts['PENDING'] ?></div></a>
    <a class="card stat" href="?status=APPROVED"><div class="stat-label">Approved</div><div class="stat-value"><?= (int) $counts['APPROVED'] ?></div></a>
    <a class="card stat" href="?status=REJECTED"><div class="stat-label">Rejected</div><div class="stat-value"><?= (int) $counts['REJECTED'] ?></div></a>
    <a class="card stat" href="?status=SPAM"><div class="stat-label">Spam</div><div class="stat-value"><?= (int) $counts['SPAM'] ?></div></a>
</div>

<form method="get" class="card filter-bar">
    <div class="field" style="flex:2">
        <label>Search body / author</label>
        <input name="q" type="text" value="<?= View::e($q) ?>">
    </div>
    <div class="field">
        <label>Status</label>
        <select name="status">
            <option value="">Any</option>
            <?php foreach (['PENDING','APPROVED','REJECTED','SPAM'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Course</label>
        <select name="course_id">
            <option value="">Any</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= View::e($c['id']) ?>" <?= $courseId === $c['id'] ? 'selected' : '' ?>><?= View::e($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field" style="align-self:flex-end">
        <button class="btn btn-primary">Apply</button>
        <a href="/admin/qa" class="btn btn-ghost">Reset</a>
    </div>
</form>

<?php if (empty($rows)): ?>
    <div class="card"><p>Nothing to moderate right now. 🎉</p></div>
<?php else: ?>
<form method="post" action="/admin/qa/bulk">
<table class="table">
    <thead><tr>
        <th style="width:32px"><input type="checkbox" id="check-all"></th>
        <th>Question</th>
        <th>Course / lesson</th>
        <th>Author</th>
        <th>Status</th>
        <th class="text-right">Likes / answers</th>
        <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><input type="checkbox" name="ids[]" value="<?= View::e($r['id']) ?>"></td>
            <td>
                <div style="max-width:480px"><?= View::e(mb_substr((string) $r['body'], 0, 200)) ?><?= mb_strlen((string) $r['body']) > 200 ? '…' : '' ?></div>
                <div class="text-muted" style="font-size:11px">
                    <?php if ((int) $r['is_pinned']): ?>📌 pinned · <?php endif; ?>
                    <?php if ((int) $r['is_resolved']): ?>✅ resolved · <?php endif; ?>
                    <?= View::e(substr((string) $r['created_at'], 0, 16)) ?>
                </div>
            </td>
            <td>
                <?= View::e($r['course_title'] ?? $r['course_id']) ?>
                <div class="text-muted" style="font-size:11px"><?= View::e($r['lesson_title'] ?? $r['lesson_id']) ?></div>
            </td>
            <td><?= View::e($r['author_name']) ?></td>
            <td>
                <?php
                $badgeKind = match ($r['moderation_status']) {
                    'APPROVED' => 'success',
                    'REJECTED' => 'warning',
                    'SPAM'     => 'danger',
                    default    => 'muted',
                };
                ?>
                <span class="badge badge-<?= $badgeKind ?>"><?= View::e($r['moderation_status']) ?></span>
            </td>
            <td class="text-right"><?= (int) $r['like_count'] ?> / <?= (int) $r['answer_count'] ?></td>
            <td class="text-right">
                <a href="/admin/qa/<?= View::e(rawurlencode($r['id'])) ?>" class="btn btn-secondary btn-sm">Open</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="flex-row" style="margin-top:16px">
    <div class="field">
        <label>Bulk action</label>
        <select name="status">
            <option value="APPROVED">Approve selected</option>
            <option value="REJECTED">Reject selected</option>
            <option value="SPAM">Mark spam</option>
            <option value="PENDING">Move to pending</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary" data-confirm="Apply this status to the checked questions?">Apply</button>
</div>
</form>

<?php if ($pages > 1): ?>
<nav class="pager flex-row" style="margin-top:16px">
    <?php if ($pageNo > 1): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= http_build_query(['q' => $q, 'status' => $status, 'course_id' => $courseId, 'page' => $pageNo - 1]) ?>">← Prev</a>
    <?php endif; ?>
    <span class="text-muted">Page <?= (int) $pageNo ?> / <?= (int) $pages ?></span>
    <div class="spacer"></div>
    <?php if ($pageNo < $pages): ?>
        <a class="btn btn-ghost btn-sm" href="?<?= http_build_query(['q' => $q, 'status' => $status, 'course_id' => $courseId, 'page' => $pageNo + 1]) ?>">Next →</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var all = document.getElementById('check-all');
    if (!all) return;
    all.addEventListener('change', function () {
        document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
    });
})();
</script>
<?php
$content = ob_get_clean();
$title   = 'Q&A';
include __DIR__ . '/../../layouts/admin.php';
