<?php
/** @var array $plan */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create' ? '/admin/billing/plans' : '/admin/billing/plans/' . $plan['id'];
$heading = $mode === 'create' ? 'New plan' : 'Edit plan';

// Convert features_json (array or string) to a textarea-friendly newline list.
$features = $plan['features_json'] ?? '';
if (is_string($features) && $features !== '' && $features[0] === '[') {
    $decoded = json_decode($features, true);
    if (is_array($decoded)) $features = implode("\n", $decoded);
}

ob_start();
?>
<header>
    <p><a href="/admin/billing/plans">← Back to plans</a></p>
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

<form method="post" action="<?= View::e($action) ?>" class="card">
    <?php if ($mode === 'create'): ?>
        <div class="field">
            <label for="id">Plan id (optional)</label>
            <input id="id" name="id" type="text" value="<?= View::e($plan['id'] ?? '') ?>" placeholder="auto: plan_xxxx">
            <small class="text-muted">Used in invoices and the API. Avoid spaces.</small>
        </div>
    <?php endif; ?>

    <div class="field">
        <label for="name">Name *</label>
        <input id="name" name="name" type="text" value="<?= View::e($plan['name']) ?>" required>
    </div>

    <div class="field">
        <label for="description">Description *</label>
        <textarea id="description" name="description" rows="2" required><?= View::e($plan['description']) ?></textarea>
    </div>

    <div class="field-row">
        <div class="field"><label>Currency</label>
            <input name="currency" type="text" value="<?= View::e($plan['currency']) ?>" maxlength="8">
        </div>
        <div class="field"><label>Monthly price (in paise/cents)</label>
            <input name="price_monthly_cents" type="number" min="0" value="<?= (int) $plan['price_monthly_cents'] ?>">
            <small class="text-muted">e.g. 49900 = ₹499.00</small>
        </div>
        <div class="field"><label>Yearly price (paise/cents)</label>
            <input name="price_yearly_cents" type="number" min="0" value="<?= (int) $plan['price_yearly_cents'] ?>">
        </div>
        <div class="field"><label>Trial days</label>
            <input name="trial_days" type="number" min="0" value="<?= (int) $plan['trial_days'] ?>">
        </div>
        <div class="field"><label>Sort order</label>
            <input name="sort_order" type="number" value="<?= (int) $plan['sort_order'] ?>">
        </div>
    </div>

    <div class="field">
        <label for="features_json">Features (one per line)</label>
        <textarea id="features_json" name="features_json" rows="6"><?= View::e($features) ?></textarea>
        <small class="text-muted">Stored as a JSON array — line breaks become entries.</small>
    </div>

    <div class="field">
        <label><input type="checkbox" name="is_active" value="1" <?= ((int) $plan['is_active']) ? 'checked' : '' ?>> Active (selectable in app)</label>
    </div>
    <div class="field">
        <label><input type="checkbox" name="is_default" value="1" <?= ((int) $plan['is_default']) ? 'checked' : '' ?>> Default plan (only one allowed)</label>
    </div>

    <div class="flex-row">
        <button type="submit" class="btn btn-primary">
            <?= $mode === 'create' ? 'Create plan' : 'Save changes' ?>
        </button>
        <a href="/admin/billing/plans" class="btn btn-ghost">Cancel</a>
        <div class="spacer"></div>
        <?php if ($mode === 'edit'): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-confirm="Delete this plan? Existing subscriptions on it will keep working but new sign-ups will fail."
                    onclick="document.getElementById('plan-delete-form').submit()">Delete</button>
        <?php endif; ?>
    </div>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="plan-delete-form" method="post" action="/admin/billing/plans/<?= View::e($plan['id']) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
