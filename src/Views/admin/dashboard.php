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
        <p>Snapshot of your LMS · <?= date('F j, Y') ?> &nbsp;·&nbsp;
            <span id="live-clock" style="color:#a78bfa;font-weight:600"></span>
            <span id="online-badge" style="display:none;margin-left:10px;background:#22c55e;color:#fff;border-radius:99px;padding:1px 10px;font-size:11px;font-weight:700;vertical-align:middle"></span>
        </p>
    </div>
    <div class="spacer"></div>
    <?php if ((int)$stats['courses'] > 0): ?>
    <form method="post" action="/admin/wipe-demo" style="display:inline"
          onsubmit="return confirm('Delete ALL <?= (int)$stats['courses'] ?> courses and their lessons permanently?')">
        <button class="btn" style="background:#ef4444;color:#fff;border:none;cursor:pointer;margin-right:8px">
            🗑 Delete All Demo Courses
        </button>
    </form>
    <?php endif; ?>
    <a href="/admin/courses/new" class="btn btn-primary">+ New course</a>
</header>

<div class="grid-stats">
    <a class="stat" href="/admin/users">
        <div class="stat-label">Learners</div>
        <div class="stat-value" id="stat-users"><?= number_format($stats['users']) ?></div>
    </a>
    <a class="stat" href="/admin/courses">
        <div class="stat-label">Courses</div>
        <div class="stat-value" id="stat-courses"><?= number_format($stats['courses']) ?></div>
    </a>
    <div class="stat">
        <div class="stat-label">Lessons</div>
        <div class="stat-value" id="stat-lessons"><?= number_format($stats['lessons']) ?></div>
    </div>
    <div class="stat">
        <div class="stat-label">Enrollments</div>
        <div class="stat-value" id="stat-enrollments"><?= number_format($stats['enrollments']) ?></div>
    </div>
    <a class="stat" href="/admin/billing/subscriptions">
        <div class="stat-label">Active subs</div>
        <div class="stat-value" id="stat-subscriptions"><?= number_format($stats['subscriptions']) ?></div>
    </a>
    <a class="stat" href="/admin/qa">
        <div class="stat-label">Doubts posted</div>
        <div class="stat-value" id="stat-questions"><?= number_format($stats['questions']) ?></div>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <h3 style="display:flex;align-items:center;gap:8px">
            Recent learners
            <span id="sync-dot" style="width:7px;height:7px;border-radius:50%;background:#22c55e;display:inline-block;opacity:0;transition:opacity 0.4s"></span>
        </h3>
        <div id="recent-learners-wrap">
        <?php if (empty($recentLearners)): ?>
            <p class="text-muted">No learners yet — share the app to get started.</p>
        <?php else: ?>
            <table class="table" style="margin-bottom:0">
                <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
                <tbody id="learners-tbody">
                <?php foreach ($recentLearners as $l): ?>
                    <tr>
                        <td><a href="/admin/users/<?= View::e(urlencode($l['id'])) ?>"><?= View::e($l['full_name']) ?></a></td>
                        <td class="text-muted"><?= View::e($l['email']) ?></td>
                        <td class="text-muted"><?= date('M j', strtotime((string) $l['joined_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
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

<script>
(function () {
    // Live clock
    function tick() {
        const el = document.getElementById('live-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    tick(); setInterval(tick, 1000);

    // Stat counter animation
    function animateStat(el, toVal) {
        const from = parseInt(el.textContent.replace(/,/g, '')) || 0;
        if (from === toVal) return;
        const steps = 20, dur = 400, step = (toVal - from) / steps;
        let cur = from, i = 0;
        const t = setInterval(() => {
            i++; cur += step;
            el.textContent = Math.round(i < steps ? cur : toVal).toLocaleString();
            if (i >= steps) clearInterval(t);
        }, dur / steps);
    }

    // Poll live.json every 30 seconds
    function poll() {
        fetch('/admin/dashboard/live.json')
            .then(r => r.json())
            .then(data => {
                // Update stats
                const map = { users: 'stat-users', courses: 'stat-courses', lessons: 'stat-lessons',
                              enrollments: 'stat-enrollments', subscriptions: 'stat-subscriptions', questions: 'stat-questions' };
                Object.entries(map).forEach(([key, id]) => {
                    const el = document.getElementById(id);
                    if (el) animateStat(el, data.stats[key]);
                });

                // Online badge
                const badge = document.getElementById('online-badge');
                if (badge) {
                    if (data.onlineNow > 0) {
                        badge.textContent = data.onlineNow + ' online now';
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                }

                // Recent learners table
                const tbody = document.getElementById('learners-tbody');
                if (tbody && data.recentLearners && data.recentLearners.length > 0) {
                    tbody.innerHTML = data.recentLearners.map(l => {
                        const joined = new Date(l.joined_at).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });
                        const encoded = encodeURIComponent(l.id);
                        return `<tr>
                            <td><a href="/admin/users/${encoded}">${escHtml(l.full_name)}</a></td>
                            <td class="text-muted">${escHtml(l.email)}</td>
                            <td class="text-muted">${joined}</td>
                        </tr>`;
                    }).join('');
                }

                // Sync flash dot
                const dot = document.getElementById('sync-dot');
                if (dot) {
                    dot.style.opacity = '1';
                    setTimeout(() => dot.style.opacity = '0', 800);
                }
            })
            .catch(() => {}); // silent fail — don't disrupt the admin UI
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // First poll after 5s, then every 30s
    setTimeout(() => { poll(); setInterval(poll, 30000); }, 5000);
})();
</script>

<?php
$content = ob_get_clean();
$title   = 'Dashboard';
include __DIR__ . '/../layouts/admin.php';
