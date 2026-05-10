<?php
/** @var array $stats */
/** @var array $recentLearners */
/** @var array $topCourses */
/** @var array $me */
/** @var string $page */
use Devithor\View;

ob_start();
?>
<header>
    <h2>Dashboard</h2>
    <p>Snapshot of your LMS as of <?= date('F j, Y · H:i') ?>.</p>
</header>

<div class="grid grid-3">
    <div class="stat"><div class="label">Learners</div><div class="value"><?= number_format($stats['users']) ?></div></div>
    <div class="stat"><div class="label">Courses</div><div class="value"><?= number_format($stats['courses']) ?></div></div>
    <div class="stat"><div class="label">Lessons</div><div class="value"><?= number_format($stats['lessons']) ?></div></div>
    <div class="stat"><div class="label">Enrollments</div><div class="value"><?= number_format($stats['enrollments']) ?></div></div>
    <div class="stat"><div class="label">Active subscriptions</div><div class="value"><?= number_format($stats['subscriptions']) ?></div></div>
    <div class="stat"><div class="label">Doubts posted</div><div class="value"><?= number_format($stats['questions']) ?></div></div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <h3>Recent learners</h3>
        <?php if (empty($recentLearners)): ?>
            <p class="text-muted">No learners yet — share the app to get started.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($recentLearners as $l): ?>
                    <tr>
                        <td><?= View::e($l['full_name']) ?></td>
                        <td class="text-muted"><?= View::e($l['email']) ?></td>
                        <td class="text-muted"><?= date('M j', strtotime((string) $l['joined_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Top-rated courses</h3>
        <?php if (empty($topCourses)): ?>
            <p class="text-muted">No courses yet. <a href="/admin/courses/new">Create one</a>.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Title</th><th>Rating</th><th class="text-right">Enrolled</th></tr></thead>
                <tbody>
                <?php foreach ($topCourses as $c): ?>
                    <tr>
                        <td><a href="/admin/courses/<?= View::e($c['id']) ?>"><?= View::e($c['title']) ?></a></td>
                        <td><span class="badge badge-success">★ <?= number_format((float) $c['rating'], 2) ?></span></td>
                        <td class="text-right"><?= number_format((int) $c['enrollments']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title   = 'Dashboard';
include __DIR__ . '/../layouts/admin.php';
