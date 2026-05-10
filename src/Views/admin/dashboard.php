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
    <div>
        <h2>Welcome back, <?= View::e(explode(' ', (string) $me['full_name'])[0]) ?> 👋</h2>
        <p>Snapshot of your LMS · <?= date('F j, Y · H:i') ?></p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/courses/new" class="btn btn-primary">+ New course</a>
</header>

<div class="grid-stats">
    <a class="stat" href="/admin/users">
        <div class="stat-label">Learners</div>
        <div class="stat-value"><?= number_format($stats['users']) ?></div>
    </a>
    <a class="stat" href="/admin/courses">
        <div class="stat-label">Courses</div>
        <div class="stat-value"><?= number_format($stats['courses']) ?></div>
    </a>
    <div class="stat">
        <div class="stat-label">Lessons</div>
        <div class="stat-value"><?= number_format($stats['lessons']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Enrollments</div>
        <div class="stat-value"><?= number_format($stats['enrollments']) ?></div>
    </div>
    <a class="stat" href="/admin/billing/subscriptions">
        <div class="stat-label">Active subs</div>
        <div class="stat-value"><?= number_format($stats['subscriptions']) ?></div>
    </a>
    <a class="stat" href="/admin/qa">
        <div class="stat-label">Doubts posted</div>
        <div class="stat-value"><?= number_format($stats['questions']) ?></div>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Recent learners</h3>
        <?php if (empty($recentLearners)): ?>
            <p class="text-muted">No learners yet — share the app to get started.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($recentLearners as $l): ?>
                    <tr>
                        <td>
                            <a href="/admin/users/<?= View::e(urlencode($l['id'])) ?>"><?= View::e($l['full_name']) ?></a>
                        </td>
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
            <table class="table" style="margin-bottom:0">
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
