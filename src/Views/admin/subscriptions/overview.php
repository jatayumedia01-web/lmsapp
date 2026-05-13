<?php
/** @var array $stats */
/** @var array $recentInvoices */
/** @var array $topPlans */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
$money = fn (int $cents, string $currency = 'INR') => $currency . ' ' . number_format($cents / 100, 2);
$statusBadge = fn(string $s) => match($s) {
    'PAID'     => '<span class="badge badge-success">PAID</span>',
    'PENDING'  => '<span class="badge badge-warning">PENDING</span>',
    'REFUNDED' => '<span class="badge badge-muted">REFUNDED</span>',
    'FAILED'   => '<span class="badge" style="background:#fee2e2;color:#991b1b">FAILED</span>',
    default    => '<span class="badge badge-muted">'.htmlspecialchars($s).'</span>',
};
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Billing &amp; subscriptions</h2>
        <p>Plans, coupons, live subscriptions, and revenue snapshot.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/settings?group=company" class="btn btn-ghost">⚙ Company Profile</a>
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
    <div class="card stat"><div class="stat-label">30d revenue</div><div class="stat-value"><?= View::e($money((int) $stats['mrr_cents'])) ?></div></div>
    <div class="card stat"><div class="stat-label">Lifetime revenue</div><div class="stat-value"><?= View::e($money((int) $stats['lifetime_cents'])) ?></div></div>
    <div class="card stat"><div class="stat-label">Plans / coupons</div><div class="stat-value"><?= (int) $stats['plans_count'] ?> / <?= (int) $stats['coupons_count'] ?></div></div>
</div>

<div class="grid-2">
    <!-- Recent invoices with view links -->
    <div class="card">
        <h3>Recent invoices</h3>
        <?php if (empty($recentInvoices)): ?>
            <p class="text-muted">No invoices yet. Subscribers will appear here after their first payment.</p>
        <?php else: ?>
            <table class="table" style="font-size:12px">
                <thead><tr><th>Invoice #</th><th>Customer</th><th>Plan</th><th class="text-right">Amount</th><th>GST</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recentInvoices as $i): ?>
                    <tr>
                        <td><code style="font-size:11px"><?= View::e($i['number'] ?? $i['id']) ?></code><div style="font-size:10px;color:#6b7280"><?= date('d M Y', intdiv((int)$i['date_millis'],1000)) ?></div></td>
                        <td><?= View::e($i['full_name'] ?? '-') ?><div style="font-size:10px;color:#6b7280"><?= View::e($i['email'] ?? '') ?></div></td>
                        <td style="font-size:11px"><?= View::e($i['plan_name'] ?? '-') ?><div style="font-size:10px;color:#6b7280"><?= View::e($i['billing_cycle_label'] ?? '') ?></div></td>
                        <td class="text-right"><strong><?= View::e($money((int) $i['amount_cents'], (string) $i['currency'])) ?></strong>
                        <?php if (!empty($i['subtotal_cents']) && (int)$i['subtotal_cents'] !== (int)$i['amount_cents']): ?>
                        <div style="font-size:10px;color:#6b7280">Tax: +<?= number_format(((int)$i['cgst_cents']+(int)$i['sgst_cents']+(int)$i['igst_cents'])/100,2) ?></div>
                        <?php endif; ?>
                        </td>
                        <td style="font-size:10px;color:#6b7280">
                            <?php if (!empty($i['gst_type']) && $i['gst_type'] !== 'EXEMPT'): ?>
                            <?= View::e((float)($i['gst_percent'] ?? 0)) ?>%<br><?= View::e($i['gst_type']) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?= $statusBadge((string)($i['status'] ?? '')) ?>
                            <?php if ($i['status'] === 'PAID'): ?>
                            <form method="post" action="/admin/billing/invoices/<?= View::e($i['id']) ?>/refund" style="display:inline;margin-top:4px">
                                <button class="btn btn-ghost btn-sm" data-confirm="Mark refunded?">Refund</button>
                            </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/billing/invoices/<?= View::e($i['id']) ?>" target="_blank" class="btn btn-ghost btn-sm" title="View / Print Invoice">🖨</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Top plans -->
    <div class="card">
        <h3>Top plans by active subs</h3>
        <?php if (empty($topPlans)): ?>
            <p class="text-muted">No active subscriptions yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Plan</th><th class="text-right">Active subs</th></tr></thead>
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
</div>

<!-- Sample invoice preview -->
<div class="card" style="margin-top:24px">
    <div class="flex-row" style="margin-bottom:16px;align-items:center">
        <h3 style="margin:0">Sample GST Tax Invoice Preview</h3>
        <div class="spacer"></div>
        <a href="/admin/settings?group=company" class="btn btn-ghost btn-sm">Edit company profile →</a>
    </div>
    <p class="text-muted" style="margin-bottom:16px">This is how invoices sent to customers look. Fill in company details at Settings → Company Profile to customise.</p>
    <?php
    // Render a demo invoice inline
    $inv = [
        'id'                 => 'demo',
        'number'             => 'INV/2026-27/0001',
        'date_millis'        => time() * 1000,
        'amount_cents'       => 59000,  // ₹590 total
        'subtotal_cents'     => 50000,  // ₹500 subtotal
        'gst_percent'        => 18.0,
        'cgst_cents'         => 4500,
        'sgst_cents'         => 4500,
        'igst_cents'         => 0,
        'gst_type'           => 'CGST_SGST',
        'currency'           => 'INR',
        'status'             => 'PAID',
        'plan_name'          => 'Pro Monthly',
        'billing_cycle_label'=> 'Monthly',
        'period_start_millis'=> time() * 1000,
        'period_end_millis'  => (time() + 30*86400) * 1000,
        'customer_name'      => 'Ravi Kumar',
        'customer_email'     => 'ravi@example.com',
        'customer_address'   => 'Flat 101, Tech Park, Hyderabad 500081',
        'place_of_supply'    => 'Telangana',
        'customer_gstin'     => '',
        'sac_code'           => '998314',
        'notes'              => '',
    ];
    try {
        $companyRows = \Devithor\Database::all("SELECT `key`, value FROM app_settings WHERE `group` = 'company'");
        $company = array_column($companyRows, 'value', 'key');
    } catch (\Throwable $e) {
        $company = ['company_name' => 'Devithor LMS Pvt Ltd', 'company_gstin' => '36AAAAA0000A1Z5'];
    }
    $month = (int) date('n'); $yr = (int) date('Y');
    $fiscalYear = $month >= 4 ? $yr.'-'.substr((string)($yr+1),2) : ($yr-1).'-'.substr((string)$yr,2);
    ?>
    <div style="transform:scale(0.85);transform-origin:top left;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;width:117%;max-height:600px;overflow-y:auto">
        <?php include __DIR__ . '/../../billing/invoice.php'; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
$title   = 'Billing';
include __DIR__ . '/../../layouts/admin.php';
