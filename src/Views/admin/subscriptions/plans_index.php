<?php
/** @var array $plans */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
$money = fn (int $cents, string $currency = 'INR') => $currency . ' ' . number_format($cents / 100, 2);
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Subscription plans</h2>
        <p>Edit pricing and feature lists. Apps fetch this on next sync.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/billing" class="btn btn-ghost">Back to billing</a>
    <a href="/admin/billing/plans/new" class="btn btn-primary">+ New plan</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($plans)): ?>
    <div class="card"><p>No plans yet. <a href="/admin/billing/plans/new">Create your first plan</a> — Free, Pro Monthly, Pro Yearly is a sensible starter set.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Name</th><th>Currency</th>
        <th class="text-right">Monthly</th><th class="text-right">Yearly</th>
        <th class="text-right">Trial</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($plans as $p): ?>
        <tr>
            <td>
                <strong><?= View::e($p['name']) ?></strong>
                <?php if ((int) $p['is_default']): ?><span class="badge badge-primary" style="margin-left:6px">Default</span><?php endif; ?>
                <div class="text-muted" style="font-size:12px"><?= View::e($p['description']) ?></div>
                <code style="font-size:11px"><?= View::e($p['id']) ?></code>
            </td>
            <td><?= View::e($p['currency']) ?></td>
            <td class="text-right"><?= View::e($money((int) $p['price_monthly_cents'], (string) $p['currency'])) ?></td>
            <td class="text-right"><?= View::e($money((int) $p['price_yearly_cents'], (string) $p['currency'])) ?></td>
            <td class="text-right"><?= (int) $p['trial_days'] ?>d</td>
            <td>
                <?php if ((int) $p['is_active']): ?>
                    <span class="badge badge-success">Active</span>
                <?php else: ?>
                    <span class="badge badge-warning">Inactive</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <a href="/admin/billing/plans/<?= View::e(rawurlencode($p['id'])) ?>" class="btn btn-secondary btn-sm">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Plans';
include __DIR__ . '/../../layouts/admin.php';
