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

$initials = function (string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
};

$avatarUrl = $user['profile_picture_url'] ?? $user['avatar_url'] ?? null;

ob_start();
?>
<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<header style="margin-bottom:24px">
    <p style="margin:0 0 12px"><a href="/admin/users" style="color:var(--text-muted);text-decoration:none">← Back to users</a></p>

    <div class="flex-row" style="align-items:center;gap:20px;flex-wrap:wrap">
        <!-- Avatar -->
        <?php if ($avatarUrl): ?>
            <img src="<?= View::e($avatarUrl) ?>"
                 alt="<?= View::e($user['full_name']) ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid var(--border);flex-shrink:0">
        <?php else: ?>
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;font-weight:700;flex-shrink:0;letter-spacing:1px">
                <?= View::e($initials($user['full_name'])) ?>
            </div>
        <?php endif; ?>

        <!-- Name + meta -->
        <div style="flex:1;min-width:0">
            <h2 style="margin:0 0 4px;font-size:22px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <?= View::e($user['full_name']) ?>
                <span class="badge badge-<?= $user['role'] === 'ADMIN' ? 'primary' : 'muted' ?>" style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                    <?= View::e($user['role']) ?>
                </span>
                <?php if ((int) ($user['is_banned'] ?? 0)): ?>
                    <span class="badge badge-danger" style="font-size:11px">BANNED</span>
                <?php endif; ?>
                <?php if ((int) ($user['onboarding_completed'] ?? 0) === 0): ?>
                    <span class="badge badge-warning" style="font-size:11px">ONBOARDING PENDING</span>
                <?php endif; ?>
            </h2>
            <p class="text-muted" style="margin:0;font-size:13px">
                <?= View::e($user['email']) ?>
                &nbsp;·&nbsp; joined <?= View::e(substr((string) $user['joined_at'], 0, 10)) ?>
                &nbsp;·&nbsp; ID <code style="font-size:11px"><?= View::e($user['id']) ?></code>
                <?php if (!empty($user['last_sign_in_at'])): ?>
                    &nbsp;·&nbsp; last seen <?= View::e(substr((string) $user['last_sign_in_at'], 0, 16)) ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
</header>

<!-- ── Quick actions ────────────────────────────────────────────────────────── -->
<div class="flex-row" style="margin-bottom:20px;flex-wrap:wrap;gap:8px">
    <a href="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/activity" class="btn btn-secondary btn-sm">Activity timeline</a>
    <a href="/admin/analytics/events?user_id=<?= View::e(rawurlencode($user['id'])) ?>" class="btn btn-ghost btn-sm">Event log</a>
    <a href="/admin/analytics/logins?user_id=<?= View::e(rawurlencode($user['id'])) ?>" class="btn btn-ghost btn-sm">Login history</a>

    <?php if ((int) ($user['is_banned'] ?? 0)): ?>
        <form method="post" action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/unban" style="margin:0">
            <button type="submit" class="btn btn-secondary btn-sm">Unban user</button>
        </form>
    <?php else: ?>
        <button type="button" class="btn btn-warning btn-sm"
                onclick="document.getElementById('ban-form').style.display='block';this.style.display='none'">
            Ban user
        </button>
    <?php endif; ?>

    <?php if ($me['id'] !== $user['id']): ?>
        <form method="post" action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/delete" style="margin:0">
            <button type="submit" class="btn btn-danger btn-sm"
                    data-confirm="Permanently delete <?= View::e(addslashes($user['full_name'])) ?>? This removes all their enrollments, Q&A and payment history.">
                Delete user
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- ── Flash / alerts ───────────────────────────────────────────────────────── -->
<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide" style="margin-bottom:16px">
        <?= View::e($flash['message']) ?>
    </div>
<?php endif; ?>

<?php if ((int) ($user['is_banned'] ?? 0) && !empty($user['banned_reason'])): ?>
    <div class="alert alert-warning" style="margin-bottom:16px">
        <strong>Banned reason:</strong> <?= View::e($user['banned_reason']) ?>
        <?php if (!empty($user['banned_at'])): ?>
            <span class="text-muted">(since <?= View::e(substr((string) $user['banned_at'], 0, 16)) ?>)</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ── Inline ban form (hidden by default) ──────────────────────────────────── -->
<?php if (!(int) ($user['is_banned'] ?? 0)): ?>
<div id="ban-form" style="display:none;margin-bottom:16px">
    <div class="card" style="border-left:4px solid var(--danger,#dc2626)">
        <h4 style="margin-top:0">Ban user</h4>
        <form method="post" action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/ban">
            <div class="field">
                <label>Reason (optional)</label>
                <input name="reason" type="text" placeholder="e.g. Spamming Q&amp;A, harassment" style="max-width:400px">
            </div>
            <div class="flex-row" style="gap:8px">
                <button type="submit" class="btn btn-danger" data-confirm="Ban this user? They'll be signed out everywhere immediately.">Confirm ban</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('ban-form').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── Stats grid ───────────────────────────────────────────────────────────── -->
<div class="grid-stats" style="margin-bottom:24px">
    <div class="card stat">
        <div class="stat-label">Enrollments</div>
        <div class="stat-value"><?= (int) $stats['enrollments'] ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Questions</div>
        <div class="stat-value"><?= (int) $stats['questions'] ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Answers</div>
        <div class="stat-value"><?= (int) $stats['answers'] ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Paid invoices</div>
        <div class="stat-value"><?= (int) $stats['invoices_paid'] ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Lifetime spend</div>
        <div class="stat-value" style="font-size:14px"><?= View::e($money((int) $stats['lifetime_cents'])) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">XP / Streak</div>
        <div class="stat-value"><?= (int) ($user['xp'] ?? 0) ?> <span style="font-size:13px;font-weight:400">/ <?= (int) ($user['streak_days'] ?? 0) ?>d</span></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Quiz attempts</div>
        <div class="stat-value"><?= (int) ($stats['quiz_attempts'] ?? 0) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Certificates</div>
        <div class="stat-value"><?= (int) ($stats['certificates'] ?? 0) ?></div>
    </div>
    <div class="card stat">
        <div class="stat-label">Notes</div>
        <div class="stat-value"><?= (int) ($stats['notes'] ?? 0) ?></div>
    </div>
</div>

<!-- ── Two-column detail area ───────────────────────────────────────────────── -->
<div class="grid-2" style="margin-bottom:24px">

    <!-- LEFT: personal info + education + onboarding -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Personal info -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:16px">Personal information</h3>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <tbody>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);width:130px;vertical-align:top">Full name</td>
                        <td style="padding:6px 0;font-weight:500"><?= View::e($user['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">Email</td>
                        <td style="padding:6px 0"><a href="mailto:<?= View::e($user['email']) ?>"><?= View::e($user['email']) ?></a></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">Date of birth</td>
                        <td style="padding:6px 0"><?= !empty($user['dob']) ? View::e($user['dob']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">Gender</td>
                        <td style="padding:6px 0">
                            <?php if (!empty($user['gender'])): ?>
                                <?= View::e(ucwords(strtolower(str_replace('_', ' ', $user['gender'])))) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">Mobile</td>
                        <td style="padding:6px 0">
                            <?php if (!empty($user['mobile'])): ?>
                                <a href="tel:<?= View::e($user['mobile']) ?>"><?= View::e($user['mobile']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">WhatsApp</td>
                        <td style="padding:6px 0">
                            <?php if (!empty($user['whatsapp'])): ?>
                                <a href="https://wa.me/<?= View::e(preg_replace('/\D/', '', $user['whatsapp'])) ?>" target="_blank" rel="noopener">
                                    <?= View::e($user['whatsapp']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">City</td>
                        <td style="padding:6px 0"><?= !empty($user['city']) ? View::e($user['city']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">State</td>
                        <td style="padding:6px 0"><?= !empty($user['state']) ? View::e($user['state']) : '<span class="text-muted">—</span>' ?></td>
                    </tr>
                    <?php if (!empty($user['address'])): ?>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);vertical-align:top">Address</td>
                        <td style="padding:6px 0;white-space:pre-wrap;font-size:12px"><?= View::e($user['address']) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Education -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:16px">Education</h3>
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <tbody>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted);width:130px">School</td>
                        <td style="padding:6px 0;font-weight:500">
                            <?= !empty($user['school_name']) ? View::e($user['school_name']) : '<span class="text-muted">—</span>' ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-muted)">Class ID</td>
                        <td style="padding:6px 0">
                            <?php if (!empty($user['class_id'])): ?>
                                <a href="/admin/classes/<?= View::e(rawurlencode($user['class_id'])) ?>">
                                    <?= View::e($user['class_id']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Onboarding status -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:12px">Onboarding</h3>
            <?php if ((int) ($user['onboarding_completed'] ?? 0)): ?>
                <p style="margin:0;font-size:13px;color:var(--success,#16a34a);font-weight:600">
                    Onboarding completed
                </p>
            <?php else: ?>
                <p style="margin:0 0 12px;font-size:13px;color:var(--warning-text,#92400e)">
                    Student has not completed onboarding yet.
                </p>
                <a href="mailto:<?= View::e($user['email']) ?>?subject=Complete+your+Devithor+profile"
                   class="btn btn-ghost btn-sm">
                    Send reminder email
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: subscription + role + danger zone -->
    <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Subscription -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:16px">Subscription</h3>
            <?php if ($sub): ?>
                <p style="margin:0 0 8px">
                    <strong><?= View::e($sub['plan_id']) ?></strong>
                    &nbsp;
                    <span class="badge badge-muted"><?= View::e($sub['status']) ?></span>
                    &nbsp;
                    <span class="text-muted"><?= View::e($sub['billing_cycle'] ?? '') ?></span>
                </p>
                <p class="text-muted" style="margin:0 0 4px;font-size:12px">
                    Auto-renew: <?= ((int) ($sub['auto_renew'] ?? 0)) ? 'on' : 'off' ?>
                </p>
                <?php if (!empty($sub['renews_at_millis'])): ?>
                    <p class="text-muted" style="margin:0;font-size:12px">
                        Renews <?= View::e(date('Y-m-d', (int) ($sub['renews_at_millis'] / 1000))) ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted" style="margin:0;font-size:13px">No active subscription (free plan).</p>
            <?php endif; ?>
        </div>

        <!-- Role management -->
        <div class="card">
            <h3 style="margin-top:0;margin-bottom:16px">Role management</h3>
            <form method="post"
                  action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/role"
                  class="flex-row" style="gap:8px;align-items:flex-end">
                <div class="field" style="flex:1;margin-bottom:0">
                    <label style="font-size:12px;margin-bottom:4px;display:block">Current role</label>
                    <select name="role">
                        <?php foreach (['STUDENT', 'INSTRUCTOR', 'ADMIN', 'PARENT'] as $r): ?>
                            <option value="<?= $r ?>" <?= ($user['role'] === $r) ? 'selected' : '' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary btn-sm"
                    <?php if ($me['id'] === $user['id']): ?>
                        data-confirm="Changing your own role to something other than ADMIN will lock you out. Continue?"
                    <?php endif; ?>>
                    Update role
                </button>
            </form>
            <?php if ($me['id'] === $user['id']): ?>
                <p class="text-muted" style="font-size:12px;margin:8px 0 0">
                    Caution: you are viewing your own account. Demoting yourself will revoke admin access.
                </p>
            <?php endif; ?>
        </div>

        <!-- Danger zone -->
        <div class="card" style="border-left:4px solid var(--danger,#dc2626)">
            <h3 style="margin-top:0;margin-bottom:16px;color:var(--danger,#dc2626)">Danger zone</h3>

            <?php if (!(int) ($user['is_banned'] ?? 0)): ?>
                <p class="text-muted" style="font-size:13px;margin:0 0 12px">
                    Banning immediately revokes all active sessions. The user cannot log in until unbanned.
                </p>
                <button type="button" class="btn btn-warning btn-sm"
                        onclick="document.getElementById('ban-form').style.display='block';window.scrollTo({top:0,behavior:'smooth'})">
                    Ban this user
                </button>
            <?php else: ?>
                <p class="text-muted" style="font-size:13px;margin:0 0 12px">
                    This user is currently banned. Unbanning restores full access.
                </p>
                <form method="post" action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/unban">
                    <button type="submit" class="btn btn-secondary btn-sm">Unban user</button>
                </form>
            <?php endif; ?>

            <?php if ($me['id'] !== $user['id']): ?>
                <hr style="margin:16px 0;border:0;border-top:1px solid var(--border)">
                <p class="text-muted" style="font-size:13px;margin:0 0 12px">
                    Deleting permanently removes the user and all their enrollments, Q&amp;A, invoices, and certificates.
                </p>
                <form method="post" action="/admin/users/<?= View::e(rawurlencode($user['id'])) ?>/delete">
                    <button type="submit" class="btn btn-danger btn-sm"
                            data-confirm="Permanently delete <?= View::e(addslashes($user['full_name'])) ?>? This cannot be undone.">
                        Delete user
                    </button>
                </form>
            <?php else: ?>
                <hr style="margin:16px 0;border:0;border-top:1px solid var(--border)">
                <p class="text-muted" style="font-size:12px;margin:0">You cannot delete your own account from here.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Payment history ───────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
    <h3 style="margin-top:0">Payment history</h3>
    <?php if (empty($invoices)): ?>
        <p class="text-muted" style="margin:0">No invoices on file.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Plan</th>
                        <th class="text-right">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><code style="font-size:11px"><?= View::e($inv['number'] ?? '—') ?></code></td>
                        <td class="text-muted" style="white-space:nowrap">
                            <?= !empty($inv['date_millis']) ? View::e(date('Y-m-d', (int) ($inv['date_millis'] / 1000))) : '—' ?>
                        </td>
                        <td><?= View::e($inv['plan_name'] ?? '—') ?></td>
                        <td class="text-right" style="font-weight:500">
                            <?= View::e($money((int) ($inv['amount_cents'] ?? 0), (string) ($inv['currency'] ?? 'INR'))) ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match ($inv['status'] ?? '') {
                                'PAID'    => 'badge-success',
                                'PENDING' => 'badge-warning',
                                'FAILED'  => 'badge-danger',
                                default   => 'badge-muted',
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= View::e($inv['status'] ?? '—') ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ── Recent enrollments ────────────────────────────────────────────────────── -->
<div class="card">
    <h3 style="margin-top:0">Recent enrollments</h3>
    <?php if (empty($recentEnrollments)): ?>
        <p class="text-muted" style="margin:0">No enrollments yet.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Enrolled at</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentEnrollments as $e): ?>
                    <tr>
                        <td>
                            <?php if (!empty($e['course_id'])): ?>
                                <a href="/admin/courses/<?= View::e(rawurlencode($e['course_id'])) ?>">
                                    <?= View::e($e['course_title'] ?? $e['course_id']) ?>
                                </a>
                            <?php else: ?>
                                <?= View::e($e['course_title'] ?? '—') ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="white-space:nowrap">
                            <?= View::e(substr((string) ($e['enrolled_at'] ?? ''), 0, 16)) ?>
                        </td>
                        <td>
                            <?php
                            $pct = isset($e['progress_pct']) ? (int) $e['progress_pct'] : null;
                            if ($pct !== null):
                            ?>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div style="flex:1;height:6px;background:var(--border);border-radius:4px;overflow:hidden;min-width:60px">
                                        <div style="height:100%;width:<?= min(100, $pct) ?>%;background:var(--primary,#4f46e5);border-radius:4px"></div>
                                    </div>
                                    <span style="font-size:12px;color:var(--text-muted);min-width:30px"><?= $pct ?>%</span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $completedAt = $e['completed_at'] ?? null;
                            if ($completedAt):
                            ?>
                                <span class="badge badge-success" style="font-size:11px">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-muted" style="font-size:11px">In progress</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($captureAttempts)): ?>
<div class="card" style="border:1px solid #ef4444;margin-top:24px">
    <h3 style="color:#ef4444;display:flex;align-items:center;gap:8px">
        ⚠ Screen Recording Violations
        <span style="background:#ef4444;color:#fff;border-radius:99px;padding:2px 10px;font-size:12px;font-weight:700"><?= count($captureAttempts) ?></span>
        <?php if ((int)($user['is_banned'] ?? 0) === 1): ?>
            <span style="background:#7f1d1d;color:#fca5a5;border-radius:99px;padding:2px 10px;font-size:12px;margin-left:4px">AUTO-SUSPENDED</span>
        <?php endif; ?>
    </h3>
    <table class="table" style="margin-bottom:0">
        <thead><tr><th>Time</th><th>Lesson ID</th></tr></thead>
        <tbody>
        <?php foreach ($captureAttempts as $a): ?>
            <?php $props = json_decode((string)($a['props_json'] ?? '{}'), true) ?? []; ?>
            <tr>
                <td class="text-muted"><?= htmlspecialchars((string)$a['occurred_at']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($props['lesson_id'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title   = $user['full_name'];
include __DIR__ . '/../../layouts/admin.php';
