<?php
/** @var array $quiz */
/** @var array $rows */
/** @var array $stats */
/** @var array $me */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header>
    <div>
        <p><a href="/admin/quizzes/<?= View::e(rawurlencode($quiz['id'])) ?>">← Back to quiz</a></p>
        <h2><?= View::e($quiz['title']) ?> · Attempts</h2>
    </div>
</header>

<div class="grid-stats">
    <div class="stat"><div class="stat-label">Total</div><div class="stat-value"><?= number_format((int) $stats['total']) ?></div></div>
    <div class="stat"><div class="stat-label">Submitted</div><div class="stat-value"><?= number_format((int) $stats['submitted']) ?></div></div>
    <div class="stat"><div class="stat-label">Passed</div><div class="stat-value"><?= number_format((int) $stats['passed']) ?></div></div>
    <div class="stat"><div class="stat-label">Avg score</div><div class="stat-value"><?= $stats['avg_score'] ?>%</div></div>
</div>

<?php if (empty($rows)): ?>
    <div class="card"><p>No attempts yet.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Learner</th><th>Started</th><th>Status</th>
        <th class="text-right">Score</th><th>Result</th><th class="text-right">Duration</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <a href="/admin/users/<?= View::e(rawurlencode($r['user_id'])) ?>"><?= View::e($r['full_name'] ?? $r['user_id']) ?></a>
                <div class="text-muted" style="font-size:11px"><?= View::e((string) ($r['email'] ?? '')) ?></div>
            </td>
            <td class="text-muted" style="font-size:12px"><?= View::e(substr((string) $r['started_at'], 0, 16)) ?></td>
            <td><span class="badge badge-muted"><?= View::e($r['status']) ?></span></td>
            <td class="text-right"><?= $r['score_pct'] !== null ? number_format((float) $r['score_pct'], 1) . '%' : '—' ?></td>
            <td>
                <?php if ($r['status'] === 'IN_PROGRESS'): ?>
                    <span class="text-muted">in progress</span>
                <?php elseif ((int) $r['passed']): ?>
                    <span class="badge badge-success">Passed</span>
                <?php else: ?>
                    <span class="badge badge-danger">Failed</span>
                <?php endif; ?>
            </td>
            <td class="text-right text-muted"><?= (int) $r['duration_seconds'] ?>s</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $quiz['title'] . ' · attempts';
include __DIR__ . '/../../layouts/admin.php';
