<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;
use Devithor\View;

/**
 * Subscription / billing admin: plans CRUD, coupons CRUD, live subscription
 * roster, refund / cancel actions, and a small revenue summary.
 *
 * Plans are stored in `subscription_plans` (introduced in migration 007) so
 * pricing can change without a deploy. Coupons predate this and live in the
 * billing migration.
 */
final class SubscriptionController
{
    private const SUBS_PAGE_SIZE = 25;

    // ---- Overview / revenue dashboard -----------------------------------

    public function overview(Request $req): never
    {
        $now = (int) (microtime(true) * 1000);
        $thirtyDaysAgo = $now - (30 * 86400 * 1000);

        $stats = [
            'active'     => (int) Database::scalar(
                'SELECT COUNT(*) FROM subscriptions WHERE status = ?', ['ACTIVE'],
            ),
            'trialing'   => (int) Database::scalar(
                'SELECT COUNT(*) FROM subscriptions WHERE status = ?', ['TRIALING'],
            ),
            'past_due'   => (int) Database::scalar(
                'SELECT COUNT(*) FROM subscriptions WHERE status = ?', ['PAST_DUE'],
            ),
            'cancelled'  => (int) Database::scalar(
                'SELECT COUNT(*) FROM subscriptions WHERE status = ?', ['CANCELLED'],
            ),
            'mrr_cents'  => (int) (Database::scalar(
                'SELECT COALESCE(SUM(amount_cents), 0) FROM invoices
                 WHERE status = ? AND date_millis >= ?',
                ['PAID', $thirtyDaysAgo],
            ) ?? 0),
            'lifetime_cents' => (int) (Database::scalar(
                'SELECT COALESCE(SUM(amount_cents), 0) FROM invoices WHERE status = ?',
                ['PAID'],
            ) ?? 0),
            'plans_count'   => (int) Database::scalar('SELECT COUNT(*) FROM subscription_plans'),
            'coupons_count' => (int) Database::scalar('SELECT COUNT(*) FROM coupons WHERE is_active = 1'),
        ];

        $recentInvoices = Database::all(
            'SELECT i.*, u.full_name, u.email
             FROM invoices i
             LEFT JOIN users u ON u.id = i.user_id
             ORDER BY i.date_millis DESC
             LIMIT 10',
        );

        $topPlans = Database::all(
            'SELECT plan_id, COUNT(*) AS subs FROM subscriptions
             WHERE status IN (?, ?) GROUP BY plan_id ORDER BY subs DESC LIMIT 5',
            ['ACTIVE', 'TRIALING'],
        );

        Response::html(View::render('admin/subscriptions/overview', [
            'stats'          => $stats,
            'recentInvoices' => $recentInvoices,
            'topPlans'       => $topPlans,
            'me'             => $req->params['user'],
            'page'           => 'billing',
            'flash'          => $this->popFlash(),
        ]));
    }

    // ---- Subscriptions list ---------------------------------------------

    public function subscriptionsIndex(Request $req): never
    {
        $status = (string) $req->input('status', '');
        $q      = trim((string) $req->input('q', ''));
        $pageNo = max(1, (int) $req->input('page', 1));

        $where = ['1=1']; $params = [];
        if (in_array($status, ['ACTIVE','TRIALING','PAST_DUE','CANCELLED','FREE'], true)) {
            $where[] = 's.status = ?'; $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(u.email LIKE ? OR u.full_name LIKE ? OR s.plan_id LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM subscriptions s LEFT JOIN users u ON u.id = s.user_id WHERE $whereSql",
            $params,
        );
        $pages = max(1, (int) ceil($total / self::SUBS_PAGE_SIZE));
        $pageNo = min($pageNo, $pages);
        $offset = ($pageNo - 1) * self::SUBS_PAGE_SIZE;

        $rows = Database::all(
            "SELECT s.*, u.email, u.full_name
             FROM subscriptions s LEFT JOIN users u ON u.id = s.user_id
             WHERE $whereSql
             ORDER BY s.updated_at DESC
             LIMIT " . self::SUBS_PAGE_SIZE . " OFFSET $offset",
            $params,
        );

        Response::html(View::render('admin/subscriptions/index', [
            'rows'   => $rows,
            'status' => $status,
            'q'      => $q,
            'page'   => 'billing',
            'pageNo' => $pageNo,
            'pages'  => $pages,
            'total'  => $total,
            'me'     => $req->params['user'],
            'flash'  => $this->popFlash(),
        ]));
    }

    public function cancelSubscription(Request $req): never
    {
        Database::exec(
            'UPDATE subscriptions SET status = ?, auto_renew = 0 WHERE user_id = ?',
            ['CANCELLED', $req->params['userId']],
        );
        $this->setFlash('Subscription cancelled.', 'success');
        Response::redirect('/admin/billing/subscriptions');
    }

    public function refundInvoice(Request $req): never
    {
        Database::exec('UPDATE invoices SET status = ? WHERE id = ?', ['REFUNDED', $req->params['id']]);
        $this->setFlash('Invoice marked as refunded. Trigger the gateway refund out-of-band.', 'success');
        Response::redirect('/admin/billing');
    }

    // ---- Plans CRUD ------------------------------------------------------

    public function plansIndex(Request $req): never
    {
        $plans = Database::all('SELECT * FROM subscription_plans ORDER BY sort_order ASC, name ASC');
        Response::html(View::render('admin/subscriptions/plans_index', [
            'plans' => $plans,
            'me'    => $req->params['user'],
            'page'  => 'billing',
            'flash' => $this->popFlash(),
        ]));
    }

    public function planNew(Request $req): never
    {
        Response::html(View::render('admin/subscriptions/plan_edit', [
            'plan'   => $this->blankPlan(),
            'mode'   => 'create',
            'errors' => [],
            'me'     => $req->params['user'],
            'page'   => 'billing',
        ]));
    }

    public function planCreate(Request $req): never
    {
        $data = $this->assemblePlan($req);
        $errors = Validator::check($data, $this->planRules());
        if ($errors) {
            Response::html(View::render('admin/subscriptions/plan_edit', [
                'plan' => $data, 'mode' => 'create', 'errors' => $errors,
                'me'   => $req->params['user'], 'page' => 'billing',
            ]));
        }
        $id = $data['id'] !== '' ? $data['id'] : ('plan_' . bin2hex(random_bytes(4)));
        if ($data['is_default']) {
            Database::exec('UPDATE subscription_plans SET is_default = 0');
        }
        Database::exec(
            'INSERT INTO subscription_plans
             (id, name, description, price_monthly_cents, price_yearly_cents, currency,
              trial_days, features_json, sort_order, is_active, is_default,
              price_monthly_offer_cents, price_yearly_offer_cents, offer_label, offer_ends_at,
              plan_type, bundle_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $data['name'], $data['description'],
                (int) $data['price_monthly_cents'], (int) $data['price_yearly_cents'],
                $data['currency'], (int) $data['trial_days'],
                $this->normaliseFeatures($data['features_json']),
                (int) $data['sort_order'], (int) $data['is_active'], (int) $data['is_default'],
                $data['price_monthly_offer_cents'], $data['price_yearly_offer_cents'],
                $data['offer_label'], $data['offer_ends_at'],
                $data['plan_type'], $data['bundle_description'],
            ],
        );
        $this->setFlash('Plan created.', 'success');
        Response::redirect('/admin/billing/plans');
    }

    public function planEdit(Request $req): never
    {
        $plan = Database::one('SELECT * FROM subscription_plans WHERE id = ?', [$req->params['id']]);
        if (!$plan) Response::notFound();
        Response::html(View::render('admin/subscriptions/plan_edit', [
            'plan'   => $plan,
            'mode'   => 'edit',
            'errors' => [],
            'me'     => $req->params['user'],
            'page'   => 'billing',
        ]));
    }

    public function planUpdate(Request $req): never
    {
        $existing = Database::one('SELECT * FROM subscription_plans WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();

        $data = $this->assemblePlan($req);
        $data['id'] = $req->params['id'];
        $errors = Validator::check($data, $this->planRules());
        if ($errors) {
            Response::html(View::render('admin/subscriptions/plan_edit', [
                'plan' => $data, 'mode' => 'edit', 'errors' => $errors,
                'me'   => $req->params['user'], 'page' => 'billing',
            ]));
        }
        if ($data['is_default']) {
            Database::exec('UPDATE subscription_plans SET is_default = 0 WHERE id <> ?', [$req->params['id']]);
        }
        Database::exec(
            'UPDATE subscription_plans SET
                name = ?, description = ?, price_monthly_cents = ?, price_yearly_cents = ?,
                currency = ?, trial_days = ?, features_json = ?,
                sort_order = ?, is_active = ?, is_default = ?,
                price_monthly_offer_cents = ?, price_yearly_offer_cents = ?,
                offer_label = ?, offer_ends_at = ?,
                plan_type = ?, bundle_description = ?
             WHERE id = ?',
            [
                $data['name'], $data['description'],
                (int) $data['price_monthly_cents'], (int) $data['price_yearly_cents'],
                $data['currency'], (int) $data['trial_days'],
                $this->normaliseFeatures($data['features_json']),
                (int) $data['sort_order'], (int) $data['is_active'], (int) $data['is_default'],
                $data['price_monthly_offer_cents'], $data['price_yearly_offer_cents'],
                $data['offer_label'], $data['offer_ends_at'],
                $data['plan_type'], $data['bundle_description'],
                $req->params['id'],
            ],
        );
        $this->setFlash('Plan updated.', 'success');
        Response::redirect('/admin/billing/plans');
    }

    public function planDelete(Request $req): never
    {
        Database::exec('DELETE FROM subscription_plans WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Plan deleted.', 'success');
        Response::redirect('/admin/billing/plans');
    }

    // ---- Coupons CRUD ----------------------------------------------------

    public function couponsIndex(Request $req): never
    {
        $coupons = Database::all('SELECT * FROM coupons ORDER BY created_at DESC');
        Response::html(View::render('admin/subscriptions/coupons_index', [
            'coupons' => $coupons,
            'me'      => $req->params['user'],
            'page'    => 'billing',
            'flash'   => $this->popFlash(),
        ]));
    }

    public function couponNew(Request $req): never
    {
        Response::html(View::render('admin/subscriptions/coupon_edit', [
            'coupon' => $this->blankCoupon(),
            'mode'   => 'create',
            'errors' => [],
            'me'     => $req->params['user'],
            'page'   => 'billing',
        ]));
    }

    public function couponCreate(Request $req): never
    {
        $data = $this->assembleCoupon($req);
        $errors = $this->validateCoupon($data);
        if ($errors) {
            Response::html(View::render('admin/subscriptions/coupon_edit', [
                'coupon' => $data, 'mode' => 'create', 'errors' => $errors,
                'me'     => $req->params['user'], 'page' => 'billing',
            ]));
        }
        Database::exec(
            'INSERT INTO coupons (code, description, discount_percent, discount_cents, expires_at_millis, is_active)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['code'], $data['description'],
                $data['discount_percent'] !== '' ? (int) $data['discount_percent'] : null,
                $data['discount_cents'] !== '' ? (int) $data['discount_cents'] : null,
                $data['expires_at_millis'] !== '' ? (int) $data['expires_at_millis'] : null,
                (int) $data['is_active'],
            ],
        );
        $this->setFlash('Coupon created.', 'success');
        Response::redirect('/admin/billing/coupons');
    }

    public function couponEdit(Request $req): never
    {
        $coupon = Database::one('SELECT * FROM coupons WHERE id = ?', [$req->params['id']]);
        if (!$coupon) Response::notFound();
        Response::html(View::render('admin/subscriptions/coupon_edit', [
            'coupon' => $coupon,
            'mode'   => 'edit',
            'errors' => [],
            'me'     => $req->params['user'],
            'page'   => 'billing',
        ]));
    }

    public function couponUpdate(Request $req): never
    {
        $existing = Database::one('SELECT * FROM coupons WHERE id = ?', [$req->params['id']]);
        if (!$existing) Response::notFound();
        $data = $this->assembleCoupon($req);
        $errors = $this->validateCoupon($data, (int) $req->params['id']);
        if ($errors) {
            $data['id'] = $req->params['id'];
            Response::html(View::render('admin/subscriptions/coupon_edit', [
                'coupon' => $data, 'mode' => 'edit', 'errors' => $errors,
                'me'     => $req->params['user'], 'page' => 'billing',
            ]));
        }
        Database::exec(
            'UPDATE coupons SET code = ?, description = ?, discount_percent = ?, discount_cents = ?,
                                expires_at_millis = ?, is_active = ?
             WHERE id = ?',
            [
                $data['code'], $data['description'],
                $data['discount_percent'] !== '' ? (int) $data['discount_percent'] : null,
                $data['discount_cents'] !== '' ? (int) $data['discount_cents'] : null,
                $data['expires_at_millis'] !== '' ? (int) $data['expires_at_millis'] : null,
                (int) $data['is_active'],
                $req->params['id'],
            ],
        );
        $this->setFlash('Coupon updated.', 'success');
        Response::redirect('/admin/billing/coupons');
    }

    public function couponDelete(Request $req): never
    {
        Database::exec('DELETE FROM coupons WHERE id = ?', [$req->params['id']]);
        $this->setFlash('Coupon deleted.', 'success');
        Response::redirect('/admin/billing/coupons');
    }

    // ---- helpers --------------------------------------------------------

    private function blankPlan(): array
    {
        return [
            'id' => '', 'name' => '', 'description' => '',
            'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'price_monthly_offer_cents' => null, 'price_yearly_offer_cents' => null,
            'offer_label' => null, 'offer_ends_at' => null,
            'plan_type' => 'INDIVIDUAL', 'bundle_description' => null,
            'currency' => 'INR', 'trial_days' => 0,
            'features_json' => "Unlimited courses\nDownloads\nCertificates",
            'sort_order' => 0, 'is_active' => 1, 'is_default' => 0,
        ];
    }

    private function planRules(): array
    {
        return [
            'name'        => ['required', 'min:2', 'max:100'],
            'description' => ['required', 'min:5'],
            'currency'    => ['required', 'min:3', 'max:8'],
        ];
    }

    private function assemblePlan(Request $req): array
    {
        $offerMonthly = (string) $req->input('price_monthly_offer_cents', '');
        $offerYearly  = (string) $req->input('price_yearly_offer_cents', '');
        $offerEnds    = trim((string) $req->input('offer_ends_at', ''));
        return [
            'id'                        => trim((string) $req->input('id', '')),
            'name'                      => trim((string) $req->input('name', '')),
            'description'               => trim((string) $req->input('description', '')),
            'price_monthly_cents'       => (int) $req->input('price_monthly_cents', 0),
            'price_yearly_cents'        => (int) $req->input('price_yearly_cents', 0),
            'price_monthly_offer_cents' => ($offerMonthly !== '' && (int) $offerMonthly > 0) ? (int) $offerMonthly : null,
            'price_yearly_offer_cents'  => ($offerYearly !== '' && (int) $offerYearly > 0) ? (int) $offerYearly : null,
            'offer_label'               => trim((string) ($req->input('offer_label') ?? '')) ?: null,
            'offer_ends_at'             => ($offerEnds !== '') ? date('Y-m-d H:i:s', strtotime($offerEnds)) : null,
            'plan_type'                 => (string) ($req->input('plan_type') ?? 'INDIVIDUAL'),
            'bundle_description'        => trim((string) ($req->input('bundle_description') ?? '')) ?: null,
            'currency'                  => strtoupper(trim((string) $req->input('currency', 'INR'))),
            'trial_days'                => (int) $req->input('trial_days', 0),
            'features_json'             => (string) $req->input('features_json', ''),
            'sort_order'                => (int) $req->input('sort_order', 0),
            'is_active'                 => $req->input('is_active') ? 1 : 0,
            'is_default'                => $req->input('is_default') ? 1 : 0,
        ];
    }

    /** Accept either a JSON array or a newline list, normalise to JSON for storage. */
    private function normaliseFeatures(string $input): string
    {
        $trimmed = trim($input);
        if ($trimmed === '') return '[]';
        if ($trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) return json_encode(array_values(array_map('strval', $decoded)));
        }
        $lines = array_filter(array_map('trim', explode("\n", $trimmed)), fn ($l) => $l !== '');
        return json_encode(array_values($lines));
    }

    private function blankCoupon(): array
    {
        return [
            'id' => null, 'code' => '', 'description' => '',
            'discount_percent' => '', 'discount_cents' => '',
            'expires_at_millis' => '', 'is_active' => 1,
        ];
    }

    private function assembleCoupon(Request $req): array
    {
        return [
            'code'              => strtoupper(trim((string) $req->input('code', ''))),
            'description'       => trim((string) $req->input('description', '')),
            'discount_percent'  => trim((string) $req->input('discount_percent', '')),
            'discount_cents'    => trim((string) $req->input('discount_cents', '')),
            'expires_at_millis' => trim((string) $req->input('expires_at_millis', '')),
            'is_active'         => $req->input('is_active') ? 1 : 0,
        ];
    }

    /** @return array<string, string> */
    private function validateCoupon(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if ($data['code'] === '' || strlen($data['code']) < 3) {
            $errors['code'] = 'Code must be at least 3 characters.';
        } else {
            $clash = $excludeId === null
                ? Database::scalar('SELECT id FROM coupons WHERE code = ?', [$data['code']])
                : Database::scalar('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$data['code'], $excludeId]);
            if ($clash) $errors['code'] = 'Another coupon already uses this code.';
        }
        if ($data['description'] === '') $errors['description'] = 'Required.';
        if ($data['discount_percent'] === '' && $data['discount_cents'] === '') {
            $errors['discount_percent'] = 'Set either a percent or a flat amount.';
        }
        if ($data['discount_percent'] !== '' && ((int) $data['discount_percent'] < 1 || (int) $data['discount_percent'] > 100)) {
            $errors['discount_percent'] = 'Percent must be 1–100.';
        }
        return $errors;
    }

    private function setFlash(string $message, string $kind = 'success'): void
    {
        $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
    }

    private function popFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }
}
