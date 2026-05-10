<?php
/** @var array $user */
/** @var ?array $sub */
/** @var array $stats */
/** @var array $invoices */
/** @var array $recentEnrollments */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;

$money = function (int $cents, string $currency = 'INR'): string {
    return $currency . ' ' . number_format($cents / 100, 2);
};
ob_start();
?>
<header>
    <p><a href="/admin/users">← Back to users</a></p>
    <h2><?= View::e($user['full_name']) ?>
        <?php if ((int) $user['is_banned']): ?>
            <span class="badge badge-danger" style="font-size:12px;margin-left:8px">BANNED</span>
        <?php endif; ?>
    </h2>
    <p class="text-muted"><?= View::e($user['email']) ?> · joined <?= View::e(substr((string) $user['joined_at'], 0, 10)) ?> · ID <code><?= View::e($user['id']) ?></code></p>
</header>

<div class="flex-row" style="margin-bottom:16px">
    <a href="/admin/users/<?= View::e(urlencode($user['id'])) ?>/activity" class="btn btn-secondary">📊 View activity timeline</a>
    <a href="/admin/analytics/events?user_id=<?= View::e(urlencode($user['id'])) ?>" class="btn btn-ghost btn-sm">Event log</a>
    <a href="/admin/analytics/logins?user_id=<?= View::e(urlencode($user['id'])) ?>" class="btn btn-ghost btn-sm">Login history</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if ((int) $user['is_banned'] && !empty($user['banned_reason'])): ?>
<div class="alert alert-warning">
    <strong>Banned:</strong> <?= View::e($user['banned_reason']) ?>
    (since <?= View::e(substr((string) $user['banned_at'], 0, 16)) ?>)
</div>
<?php endif; ?>

<div class="grid-stats">
    <div class="card stat"><div class="stat-label">Enrollments</div><div class="stat-value"><?= (int) $stats['enrollments'] ?></div></div>
    <div class="card stat"><div class="stat-label">Questions</div><div class="stat-value"><?= (int) $stats['questions'] ?></div></div>
    <div class="card stat"><div class="stat-label">Answers</div><div class="stat-value"><?= (int) $stats['answers'] ?></div></div>
    <div class="card stat"><div class="stat-label">Paid invoices</div><div class="stat-value"><?= (int) $stats['invoices_paid'] ?></div></div>
    <div class="card stat"><div class="stat-label">Lifetime spend</div><div class="stat-value"><?= View::e($money((int) $stats['lifetime_cents'])) ?></div></div>
    <div class="card stat"><div class="stat-label">XP / Streak</div><div class="stat-value"><?= (int) $user['xp'] ?> / <?= (int) $user['streak_days'] ?>d</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Subscription</h3>
        <?php if ($sub): ?>
            <p>
                <strong><?= View::e($sub['plan_id']) ?></strong>
                · <span class="badge badge-muted"><?= View::e($sub['status']) ?></span>
                · <?= View::e($sub['billing_cycle']) ?>
                · auto-renew: <?= ((int) $sub['auto_renew']) ? 'on' : 'off' ?>
            </p>
            <?php if (!empty($sub['renews_at_millis'])): ?>
                <p class="text-muted">Renews at <?= View::e(date('Y-m-d', (int) ($sub['renews_at_millis'] / 1000))) ?></p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted">No subscription on file (free plan).</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Admin actions</h3>
        <form method="post" action="/admin/users/<?= View::e(urlencode($user['id'])) ?>/role" class="flex-row" style="gap:8px;align-items:flex-end">
            <div class="field" style="flex:1">
                <label>Role</label>
                <select name="role">
                    <?php foreach (['STUDENT','INSTRUCTOR','ADMIN','PARENT'] as $r): ?>
                        <option value="<?= $r ?>" <?= $user['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Update role</button>
        </form>

        <hr style="margin:16px 0;border:0;border-top:1px solid var(--border)">

        <?php if ((int) $user['is_banned']): ?>
            <form method="post" action="/admin/users/<?= View::e(urlencode($user['id'])) ?>/unban">
                <button type="submit" class="btn btn-secondary">Unban user</button>
            </form>
        <?php else: ?>
            <form method="post" action="/admin/users/<?= View::e(urlencode($user['id'])) ?>/ban">
                <div class="field">
                    <label>Ban reason (optional)</label>
                    <input name="reason" type="text" placeholder="e.g. spamming Q&amp;A">
                </div>
                <button type="submit" class="btn btn-warning" data-confirm="Ban this user? They'll be signed out everywhere.">Ban user</button>
            </form>
        <?php endif; ?>

        <hr style="margin:16px 0;border:0;border-top:1px solid var(--border)">

        <form method="post" action="/admin/users/<?= View::e(urlencode($user['id'])) ?>/delete">
            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Permanently delete this user? Their enrollments, Q&amp;A and payment history go with them.">Delete user</button>
        </form>
    </div>
</div>

<div class="card">
    <h3>Recent enrollments</h3>
    <?php if (empty($recentEnrollments)): ?>
        <p class="text-muted">None yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Course</th><th>Enrolled at</th></tr></thead>
            <tbody>
            <?php foreach ($recentEnrollments as $e): ?>
                <tr>
                    <td><?= View::e($e['course_title'] ?? $e['course_id']) ?></td>
                    <td class="text-muted"><?= View::e(substr((string) ($e['enrolled_at'] ?? ''), 0, 16)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Invoices</h3>
    <?php if (empty($invoices)): ?>
        <p class="text-muted">No invoices.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr>
                <th>#</th><th>Date</th><th>Plan</th>
                <th class="text-right">Amount</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($invoices as $i): ?>
                <tr>
                    <td><code><?= View::e($i['number']) ?></code></td>
                    <td class="text-muted"><?= View::e(date('Y-m-d', (int) ($i['date_millis'] / 1000))) ?></td>
                    <td><?= View::e($i['plan_name']) ?></td>
                    <td class="text-right"><?= View::e($money((int) $i['amount_cents'], (string) $i['currency'])) ?></td>
                    <td><span class="badge badge-muted"><?= View::e($i['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title   = $user['full_name'];
include __DIR__ . '/../../layouts/admin.php';
