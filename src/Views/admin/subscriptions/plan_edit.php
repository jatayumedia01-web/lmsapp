<?php
/** @var array $plan */
/** @var string $mode  'create' | 'edit' */
/** @var array $errors */
/** @var array $me */
/** @var string $page */
use Devithor\View;

$action  = $mode === 'create' ? '/admin/billing/plans' : '/admin/billing/plans/' . rawurlencode((string)($plan['id'] ?? ''));
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
        <div class="field"><label>Plan type</label>
            <select name="plan_type">
                <?php foreach (['INDIVIDUAL', 'BUNDLE', 'COURSE_PACK'] as $t): ?>
                    <option value="<?= $t ?>" <?= ($plan['plan_type'] ?? 'INDIVIDUAL') === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Currency</label>
            <input name="currency" type="text" value="<?= View::e($plan['currency']) ?>" maxlength="8">
        </div>
        <div class="field"><label>Trial days</label>
            <input name="trial_days" type="number" min="0" value="<?= (int) $plan['trial_days'] ?>">
        </div>
        <div class="field"><label>Sort order</label>
            <input name="sort_order" type="number" value="<?= (int) $plan['sort_order'] ?>">
        </div>
    </div>

    <div style="background:#1e293b;border-radius:8px;padding:16px 20px;margin-bottom:16px">
        <p style="color:#94a3b8;font-size:12px;margin:0 0 12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Pricing</p>
        <div class="field-row">
            <div class="field"><label>Monthly price (paise)</label>
                <input name="price_monthly_cents" type="number" min="0" value="<?= (int) $plan['price_monthly_cents'] ?>">
                <small class="text-muted">e.g. 49900 = ₹499</small>
            </div>
            <div class="field"><label>Yearly price (paise)</label>
                <input name="price_yearly_cents" type="number" min="0" value="<?= (int) $plan['price_yearly_cents'] ?>">
            </div>
        </div>
        <div style="border-top:1px solid #334155;margin:12px 0;padding-top:12px">
            <p style="color:#f59e0b;font-size:12px;margin:0 0 10px;font-weight:600">🏷 Offer / Sale Price (optional)</p>
            <div class="field-row">
                <div class="field"><label>Offer monthly price (paise)</label>
                    <input name="price_monthly_offer_cents" type="number" min="0" value="<?= (int) ($plan['price_monthly_offer_cents'] ?? 0) ?: '' ?>">
                    <small class="text-muted">0 = no offer</small>
                </div>
                <div class="field"><label>Offer yearly price (paise)</label>
                    <input name="price_yearly_offer_cents" type="number" min="0" value="<?= (int) ($plan['price_yearly_offer_cents'] ?? 0) ?: '' ?>">
                </div>
                <div class="field"><label>Offer label</label>
                    <input name="offer_label" type="text" value="<?= View::e($plan['offer_label'] ?? '') ?>" placeholder="e.g. 50% OFF Launch">
                </div>
                <div class="field"><label>Offer expires at</label>
                    <input name="offer_ends_at" type="datetime-local" value="<?= $plan['offer_ends_at'] ? date('Y-m-d\TH:i', strtotime((string)$plan['offer_ends_at'])) : '' ?>">
                </div>
            </div>
        </div>
    </div>

    <?php if (($plan['plan_type'] ?? '') === 'BUNDLE' || ($plan['plan_type'] ?? '') === 'COURSE_PACK'): ?>
    <div class="field">
        <label for="bundle_description">Bundle description (what's included)</label>
        <textarea id="bundle_description" name="bundle_description" rows="3"><?= View::e($plan['bundle_description'] ?? '') ?></textarea>
    </div>
    <?php else: ?>
    <div class="field" style="display:none" id="bundle_desc_wrap">
        <label for="bundle_description">Bundle description</label>
        <textarea id="bundle_description" name="bundle_description" rows="3"><?= View::e($plan['bundle_description'] ?? '') ?></textarea>
    </div>
    <?php endif; ?>
    <script>
    document.querySelector('[name=plan_type]').addEventListener('change', function() {
        document.getElementById('bundle_desc_wrap').style.display = ['BUNDLE','COURSE_PACK'].includes(this.value) ? '' : 'none';
    });
    </script>

    <div class="field">
        <label for="features_json">Features (one per line)</label>
        <textarea id="features_json" name="features_json" rows="6"><?= View::e($features) ?></textarea>
        <small class="text-muted">Stored as a JSON array — line breaks become entries.</small>
    </div>

    <div style="background:#1e293b;border-radius:8px;padding:16px 20px;margin-bottom:16px">
        <p style="color:#94a3b8;font-size:12px;margin:0 0 12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em">GST / Tax Settings</p>
        <div class="field-row">
            <div class="field">
                <label><input type="checkbox" name="is_gst_applicable" value="1" <?= ((int)($plan['is_gst_applicable'] ?? 1)) ? 'checked' : '' ?>> GST applicable on this plan</label>
            </div>
            <div class="field">
                <label><input type="checkbox" name="is_gst_inclusive" value="1" <?= ((int)($plan['is_gst_inclusive'] ?? 1)) ? 'checked' : '' ?>> Price is GST-inclusive</label>
                <small class="text-muted">If checked, GST is extracted from the plan price (e.g. ₹590 includes 18% GST → ₹500 + ₹90 GST). If unchecked, GST is added on top.</small>
            </div>
            <div class="field">
                <label>GST % (default 18)</label>
                <input name="gst_percent" type="number" min="0" max="28" step="0.01" value="<?= number_format((float)($plan['gst_percent'] ?? 18), 2) ?>">
                <small class="text-muted">Common rates: 0%, 5%, 12%, 18%, 28%</small>
            </div>
            <div class="field">
                <label>SAC Code</label>
                <input name="sac_code" type="text" value="<?= View::e($plan['sac_code'] ?? '998314') ?>" maxlength="10">
                <small class="text-muted">998314 = IT/SaaS services</small>
            </div>
        </div>
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
    <form id="plan-delete-form" method="post" action="/admin/billing/plans/<?= View::e(rawurlencode((string)($plan['id'] ?? ''))) ?>/delete" style="display:none"></form>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title   = $heading;
include __DIR__ . '/../../layouts/admin.php';
