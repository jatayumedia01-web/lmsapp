<?php
/** @var array $stats */
/** @var array $recentInvoices */
/** @var array $topPlans */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
$money = fn (int $cents, string $currency = 'INR') => $currency . ' ' . number_format($cents / 100, 2);
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Billing &amp; subscriptions</h2>
        <p>Plans, coupons, live subscriptions, and a snapshot of revenue.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/billing/plans" class="btn btn-secondary">Plans</a>
    <a href="/admin/billing/coupons" class="btn btn-secondary">Coupons</a>
    <a href="/admin/billing/subscriptions" class="btn btn-primary">Subscriptions</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<div class="grid-stats">
    <div class="card stat"><div class="stat-label">Active</div><div class="stat-value"><?= (int) $stats['active'] ?></div></div>
    <div class="card stat"><div class="stat-label">Trialing</div><div class="stat-value"><?= (int) $stats['trialing'] ?></div></div>
    <div class="card stat"><div class="stat-label">Past due</div><div class="stat-value"><?= (int) $stats['past_due'] ?></div></div>
    <div class="card stat"><div class="stat-label">Cancelled</div><div class="stat-value"><?= (int) $stats['cancelled'] ?></div></div>
    <div class="card stat"><div class="stat-label">Last 30d revenue</div><div class="stat-value"><?= View::e($money((int) $stats['mrr_cents'])) ?></div></div>
    <div class="card stat"><div class="stat-label">Lifetime revenue</div><div class="stat-value"><?= View::e($money((int) $stats['lifetime_cents'])) ?></div></div>
    <div class="card stat"><div class="stat-label">Plans / coupons</div><div class="stat-value"><?= (int) $stats['plans_count'] ?> / <?= (int) $stats['coupons_count'] ?></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Top plans by active subs</h3>
        <?php if (empty($topPlans)): ?>
            <p class="text-muted">No active subscriptions yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Plan id</th><th class="text-right">Active subs</th></tr></thead>
                <tbody>
                <?php foreach ($topPlans as $p): ?>
                    <tr>
                        <td><code><?= View::e($p['plan_id']) ?></code></td>
                        <td class="text-right"><?= (int) $p['subs'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent invoices</h3>
        <?php if (empty($recentInvoices)): ?>
            <p class="text-muted">No invoices yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>#</th><th>User</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentInvoices as $i): ?>
                    <tr>
                        <td><code><?= View::e($i['number']) ?></code></td>
                        <td><?= View::e($i['full_name'] ?? $i['user_id']) ?><div class="text-muted" style="font-size:11px"><?= View::e($i['email'] ?? '') ?></div></td>
                        <td class="text-right"><?= View::e($money((int) $i['amount_cents'], (string) $i['currency'])) ?></td>
                        <td>
                            <span class="badge badge-muted"><?= View::e($i['status']) ?></span>
                            <?php if ($i['status'] === 'PAID'): ?>
                                <form method="post" action="/admin/billing/invoices/<?= View::e($i['id']) ?>/refund" style="display:inline">
                                    <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Mark this invoice as refunded?">Refund</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$title   = 'Billing';
include __DIR__ . '/../../layouts/admin.php';
