<?php use Devithor\View; ob_start(); ?>
<header>
    <div>
        <h2>Results — <?= View::e($exam['title']) ?></h2>
        <p><?= (int)$exam['duration_minutes'] ?> min · Pass: <?= (int)$exam['pass_marks'] ?>/<?= (int)$exam['total_marks'] ?></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/exams/<?= View::e($exam['id']) ?>/questions" class="btn btn-ghost">← Back</a>
</header>

<div class="grid-stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="stat"><div class="stat-label">Total Attempts</div><div class="stat-value"><?= $stats['total'] ?></div></div>
    <div class="stat"><div class="stat-label">Passed</div><div class="stat-value" style="color:#86efac"><?= $stats['passed'] ?></div></div>
    <div class="stat"><div class="stat-label">Avg Score</div><div class="stat-value"><?= $stats['avg_pct'] ?>%</div></div>
</div>

<?php if (empty($attempts)): ?>
<div class="card" style="text-align:center;padding:48px;color:#6b7280">No attempts yet.</div>
<?php else: ?>
<div class="card" style="padding:0">
<table class="table" style="margin:0">
<thead><tr><th>Student</th><th>Score</th><th>%</th><th>Status</th><th>Time Taken</th><th>Certificate</th><th>Submitted</th></tr></thead>
<tbody>
<?php foreach ($attempts as $a): ?>
<tr>
    <td>
        <a href="/admin/users/<?= View::e(urlencode($a['user_id'])) ?>"><?= View::e($a['full_name']) ?></a>
        <div style="font-size:12px;color:#6b7280"><?= View::e($a['email']) ?></div>
    </td>
    <td><strong><?= (int)$a['score'] ?></strong><span class="text-muted">/<?= (int)$a['total_marks'] ?></span></td>
    <td>
        <span class="badge <?= $a['pct'] >= ($exam['pass_marks'] / $exam['total_marks'] * 100) ? 'badge-success' : 'badge-danger' ?>">
            <?= $a['pct'] ?>%
        </span>
    </td>
    <td>
        <?php if ($a['passed']): ?>
            <span class="badge badge-success">PASSED</span>
        <?php elseif ($a['status'] === 'TIMED_OUT'): ?>
            <span class="badge badge-danger">TIMED OUT</span>
        <?php else: ?>
            <span class="badge badge-danger">FAILED</span>
        <?php endif; ?>
    </td>
    <td class="text-muted"><?= gmdate('i:s', (int)$a['time_taken_seconds']) ?></td>
    <td class="text-muted" style="font-size:12px;font-family:monospace"><?= $a['certificate_number'] ? View::e($a['certificate_number']) : '—' ?></td>
    <td class="text-muted"><?= $a['submitted_at'] ? date('M j, H:i', strtotime($a['submitted_at'])) : '—' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); $title = 'Results'; include __DIR__ . '/../../layouts/admin.php'; ?>
