<?php
/** @var array $coupons */
/** @var array $me */
/** @var ?array $flash */
/** @var string $page */
use Devithor\View;
ob_start();
?>
<header class="flex-row">
    <div>
        <h2>Coupons</h2>
        <p>One-shot or evergreen discount codes.</p>
    </div>
    <div class="spacer"></div>
    <a href="/admin/billing" class="btn btn-ghost">Back to billing</a>
    <a href="/admin/billing/coupons/new" class="btn btn-primary">+ New coupon</a>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= View::e($flash['kind']) ?> auto-hide"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (empty($coupons)): ?>
    <div class="card"><p>No coupons yet. <a href="/admin/billing/coupons/new">Create your first coupon</a> for a launch promo.</p></div>
<?php else: ?>
<table class="table">
    <thead><tr>
        <th>Code</th><th>Description</th><th>Discount</th><th>Expires</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($coupons as $c): ?>
        <tr>
            <td><code><?= View::e($c['code']) ?></code></td>
            <td><?= View::e($c['description']) ?></td>
            <td>
                <?php if (!empty($c['discount_percent'])): ?>
                    <?= (int) $c['discount_percent'] ?>%
                <?php elseif (!empty($c['discount_cents'])): ?>
                    Flat <?= number_format(((int) $c['discount_cents']) / 100, 2) ?>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td class="text-muted">
                <?= !empty($c['expires_at_millis']) ? View::e(date('Y-m-d', (int) ($c['expires_at_millis'] / 1000))) : 'Never' ?>
            </td>
            <td>
                <?php if ((int) $c['is_active']): ?>
                    <span class="badge badge-success">Active</span>
                <?php else: ?>
                    <span class="badge badge-warning">Disabled</span>
                <?php endif; ?>
            </td>
            <td class="text-right">
                <a href="/admin/billing/coupons/<?= (int) $c['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = 'Coupons';
include __DIR__ . '/../../layouts/admin.php';
