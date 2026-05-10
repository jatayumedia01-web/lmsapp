<?php
/** @var array $question */
/** @var array $answers */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header>
    <p><a href="/admin/qa">← Back to Q&amp;A</a></p>
    <h2>Question</h2>
    <p class="text-muted">
        <?= View::e($question['course_title'] ?? $question['course_id']) ?>
        · <?= View::e($question['lesson_title'] ?? $question['lesson_id']) ?>
        · asked by <?= View::e($question['author_name']) ?>
        · <?= View::e(substr((string) $question['created_at'], 0, 16)) ?>
    </p>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="flex-row" style="margin-bottom:12px">
        <?php
        $badgeKind = match ($question['moderation_status']) {
            'APPROVED' => 'success',
            'REJECTED' => 'warning',
            'SPAM'     => 'danger',
            default    => 'muted',
        };
        ?>
        <span class="badge badge-<?= $badgeKind ?>"><?= View::e($question['moderation_status']) ?></span>
        <?php if ((int) $question['is_pinned']): ?><span class="badge badge-primary">📌 Pinned</span><?php endif; ?>
        <?php if ((int) $question['is_resolved']): ?><span class="badge badge-success">✅ Resolved</span><?php endif; ?>
        <div class="spacer"></div>
        <span class="text-muted">👍 <?= (int) $question['like_count'] ?> · 👎 <?= (int) $question['dislike_count'] ?></span>
    </div>
    <p style="white-space:pre-wrap"><?= View::e($question['body']) ?></p>
</div>

<div class="card">
    <h3>Moderation</h3>
    <div class="flex-row" style="gap:8px;flex-wrap:wrap">
        <?php foreach (['APPROVED','REJECTED','SPAM','PENDING'] as $s): ?>
            <form method="post" action="/admin/qa/<?= View::e(urlencode($question['id'])) ?>/status" style="display:inline">
                <input type="hidden" name="status" value="<?= $s ?>">
                <input type="hidden" name="back" value="show">
                <button type="submit" class="btn btn-secondary btn-sm" <?= $question['moderation_status'] === $s ? 'disabled' : '' ?>>
                    Set <?= $s ?>
                </button>
            </form>
        <?php endforeach; ?>

        <form method="post" action="/admin/qa/<?= View::e(urlencode($question['id'])) ?>/pin" style="display:inline">
            <button type="submit" class="btn btn-ghost btn-sm">
                <?= ((int) $question['is_pinned']) ? 'Unpin' : 'Pin' ?>
            </button>
        </form>
        <form method="post" action="/admin/qa/<?= View::e(urlencode($question['id'])) ?>/resolve" style="display:inline">
            <button type="submit" class="btn btn-ghost btn-sm">
                <?= ((int) $question['is_resolved']) ? 'Mark unresolved' : 'Mark resolved' ?>
            </button>
        </form>

        <div class="spacer"></div>

        <form method="post" action="/admin/qa/<?= View::e(urlencode($question['id'])) ?>/delete" style="display:inline">
            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Permanently delete this question and all its answers?">Delete</button>
        </form>
    </div>
    <?php if ($question['moderated_at']): ?>
        <p class="text-muted" style="margin-top:12px;font-size:12px">
            Last moderated: <?= View::e(substr((string) $question['moderated_at'], 0, 16)) ?>
            by <code><?= View::e((string) ($question['moderated_by'] ?? '—')) ?></code>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Answers (<?= count($answers) ?>)</h3>
    <?php if (empty($answers)): ?>
        <p class="text-muted">No answers yet.</p>
    <?php else: ?>
        <?php foreach ($answers as $a): ?>
            <div style="padding:12px 0;border-top:1px solid var(--border)">
                <div class="flex-row" style="margin-bottom:6px">
                    <strong><?= View::e($a['author_name']) ?></strong>
                    <?php if ((int) $a['is_instructor']): ?><span class="badge badge-primary">Instructor</span><?php endif; ?>
                    <span class="text-muted" style="font-size:11px">· <?= View::e(substr((string) $a['created_at'], 0, 16)) ?></span>
                    <div class="spacer"></div>
                    <span class="text-muted">👍 <?= (int) $a['like_count'] ?> · 👎 <?= (int) $a['dislike_count'] ?></span>
                    <form method="post" action="/admin/qa/answers/<?= View::e(urlencode($a['id'])) ?>/delete" style="display:inline;margin-left:8px">
                        <button type="submit" class="btn btn-danger btn-sm" data-confirm="Delete this answer?">Delete</button>
                    </form>
                </div>
                <p style="white-space:pre-wrap;margin:0"><?= View::e($a['body']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = 'Question';
include __DIR__ . '/../../layouts/admin.php';
