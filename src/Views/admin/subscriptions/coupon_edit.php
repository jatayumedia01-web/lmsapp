<?php
/** @var array $coupon */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create' ? '/admin/billing/coupons' : '/admin/billing/coupons/' . $coupon['id'];
$heading = $mode === 'create' ? 'New coupon' : 'Edit coupon';

$expiresValue = '';
if (!empty($coupon['expires_at_millis'])) {
    $expiresValue = date('Y-m-d', (int) ($coupon['expires_at_millis'] / 1000));
}

ob_start();
?>
<header>
    <p><a href="/admin/billing/coupons">← Back to coupons</a></p>
    <h2><?= View::e($heading) ?></h2>
</header>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        Please fix:
        <ul style="margin:6px 0 0 16px">
            <?php foreach ($errors as $field => $msg): ?>
                <li><strong><?= View::e($field) ?></strong>: <?= View::e($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="card" id="coupon-form">
    <div class="field">
        <label for="code">Code *</label>
        <input id="code" name="code" type="text" value="<?= View::e($coupon['code']) ?>" maxlength="50" required style="text-transform:uppercase">
    </div>
    <div class="field">
        <label for="description">Description *</label>
        <input id="description" name="description" type="text" value="<?= View::e($coupon['description']) ?>" required>
    </div>
    <div class="field-row">
        <div class="field"><label>Discount %</label>
            <input name="discount_percent" type="number" min="1" max="100" value="<?= View::e((string) $coupon['discount_percent']) ?>">
            <small class="text-muted">Set this OR a flat amount.</small>
        </div>
        <div class="field"><label>Flat discount (paise/cents)</label>
            <input name="discount_cents" type="number" min="0" value="<?= View::e((string) $coupon['discount_cents']) ?>">
            <small class="text-muted">e.g. 10000 = ₹100</small>
        </div>
    </div>

    <div class="field">
        <label>Expires on (leave blank for never)</label>
        <input type="date" id="expires_at_date" value="<?= View::e($expiresValue) ?>">
        <input type="hidden" name="expires_at_millis" id="expires_at_millis" value="<?= View::e((string) $coupon['expires_at_millis']) ?>">
    </div>

    <div class="field">
        <label><input type="checkbox" name="is_active" value="1" <?= ((int) $coupon['is_active']) ? 'checked' : '' ?>> Active</label>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary"><?= $mode === 'create' ? 'Create coupon' : 'Save changes' ?></button>
        <a href="/admin/billing/coupons" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this coupon?"
                    onclick="document.getElementById('coupon-delete-form').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="coupon-delete-form" method="post" action="/admin/billing/coupons/<?= (int) $coupon['id'] ?>/delete" style="display:none"></form>
<?php endif; ?>

<script>
(function () {
    var d = document.getElementById('expires_at_date');
    var m = document.getElementById('expires_at_millis');
    var f = document.getElementById('coupon-form');
    if (!d || !m || !f) return;
    f.addEventListener('submit', function () {
        m.value = d.value ? Date.parse(d.value + 'T00:00:00Z') : '';
    });
})();
</script>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
