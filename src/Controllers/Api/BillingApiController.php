<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Database;
use Devithor\Request;
use Devithor\Response;

/**
 * Mobile billing API — plans, subscriptions, coupons, invoices, payment gateways.
 * Supports: Razorpay, Stripe, PayU, PhonePe (plus free/trial activation).
 * Gateway keys are read from the `settings` table so they can be updated without
 * a code deploy. Missing keys = test mode (safe for development).
 */
final class BillingApiController
{
    // ── Plans ─────────────────────────────────────────────────────────────────

    public function plans(Request $req): never
    {
        $plans = Database::all(
            'SELECT id, name, description,
                    price_monthly_cents, price_yearly_cents,
                    price_monthly_offer_cents, price_yearly_offer_cents,
                    offer_label, offer_ends_at, currency, trial_days,
                    features_json, sort_order, is_default,
                    plan_type, bundle_description
             FROM subscription_plans
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC',
        );

        $now = time();
        foreach ($plans as &$p) {
            $p['features'] = json_decode((string) ($p['features_json'] ?? '[]'), true) ?: [];
            unset($p['features_json']);
            // Expire offers automatically
            if ($p['offer_ends_at'] && strtotime((string) $p['offer_ends_at']) < $now) {
                $p['price_monthly_offer_cents'] = null;
                $p['price_yearly_offer_cents']  = null;
                $p['offer_label']               = null;
            }
            $p['price_monthly_cents'] = (int) $p['price_monthly_cents'];
            $p['price_yearly_cents']  = (int) $p['price_yearly_cents'];
        }
        unset($p);

        Response::json(['plans' => $plans]);
    }

    // ── Current subscription ──────────────────────────────────────────────────

    public function subscription(Request $req): never
    {
        $user = $req->params['user'];
        $sub  = Database::one(
            'SELECT s.*, p.name AS plan_name, p.features_json, p.currency AS plan_currency
             FROM subscriptions s
             LEFT JOIN subscription_plans p ON p.id = s.plan_id
             WHERE s.user_id = ?',
            [$user['id']],
        );

        if ($sub) {
            $sub['features'] = json_decode((string) ($sub['features_json'] ?? '[]'), true) ?: [];
            unset($sub['features_json']);
        }

        $pm = null;
        if ($sub && $sub['payment_method_id']) {
            $pm = Database::one(
                'SELECT id, type, brand, last4, expiry_month, expiry_year, holder_name, is_default
                 FROM payment_methods WHERE id = ?',
                [$sub['payment_method_id']],
            );
        }

        Response::json(['subscription' => $sub, 'payment_method' => $pm]);
    }

    // ── Subscribe (free / trial — no payment needed) ──────────────────────────

    public function subscribe(Request $req): never
    {
        $user   = $req->params['user'];
        $planId = (string) $req->input('plan_id', '');
        $cycle  = $this->validCycle((string) $req->input('billing_cycle', 'MONTHLY'));

        $plan = Database::one('SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1', [$planId]);
        if (!$plan) Response::json(['error' => 'Plan not found'], 404);

        $price = $cycle === 'YEARLY' ? (int) $plan['price_yearly_cents'] : (int) $plan['price_monthly_cents'];
        if ($price > 0) Response::json(['error' => 'Paid plan — use init-payment endpoint'], 400);

        $invoiceId = $this->activateSubscription($user['id'], $planId, $cycle, null, 0, 0);
        $sub       = Database::one('SELECT * FROM subscriptions WHERE user_id = ?', [$user['id']]);
        Response::json(['subscription' => $sub, 'invoice_id' => $invoiceId]);
    }

    // ── Init payment (creates gateway order) ──────────────────────────────────

    public function initPayment(Request $req): never
    {
        $user       = $req->params['user'];
        $planId     = (string) $req->input('plan_id', '');
        $cycle      = $this->validCycle((string) $req->input('billing_cycle', 'MONTHLY'));
        $gateway    = $this->validGateway(strtoupper((string) $req->input('gateway', 'RAZORPAY')));
        $couponCode = strtoupper(trim((string) ($req->input('coupon_code') ?? '')));

        $plan = Database::one('SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1', [$planId]);
        if (!$plan) Response::json(['error' => 'Plan not found'], 404);

        // Resolve effective price (offer or regular)
        $offerActive = empty($plan['offer_ends_at']) || strtotime((string) $plan['offer_ends_at']) >= time();
        $baseAmount  = $cycle === 'YEARLY' ? (int) $plan['price_yearly_cents'] : (int) $plan['price_monthly_cents'];
        $offerCol    = $cycle === 'YEARLY' ? 'price_yearly_offer_cents' : 'price_monthly_offer_cents';
        $amount      = ($offerActive && !empty($plan[$offerCol])) ? (int) $plan[$offerCol] : $baseAmount;

        // Apply coupon
        $coupon = null; $discountCents = 0; $couponId = null;
        if ($couponCode !== '') {
            $coupon = $this->resolveCoupon($couponCode, $user['id']);
            if ($coupon) {
                $couponId = $coupon['id'];
                $discountCents = $coupon['discount_percent'] > 0
                    ? (int) round($amount * $coupon['discount_percent'] / 100)
                    : (int) $coupon['discount_cents'];
                $amount = max(0, $amount - $discountCents);
            }
        }

        // Free after coupon
        if ($amount === 0) {
            $invoiceId = $this->activateSubscription($user['id'], $planId, $cycle, $couponId, $discountCents, $baseAmount);
            if ($couponId) $this->recordCouponUsage($couponId, $user['id'], $invoiceId, $discountCents);
            $sub = Database::one('SELECT * FROM subscriptions WHERE user_id = ?', [$user['id']]);
            Response::json(['subscription' => $sub, 'free_activation' => true]);
        }

        $currency = (string) ($plan['currency'] ?: 'INR');
        $receipt  = 'rcpt_' . bin2hex(random_bytes(6));
        $orderId  = 'po_' . bin2hex(random_bytes(8));

        $settings    = $this->settings();
        $gatewayData = $this->createGatewayOrder($gateway, $amount, $currency, $receipt, $plan, $user, $settings);

        Database::exec(
            'INSERT INTO payment_orders (id, user_id, plan_id, billing_cycle, gateway, gateway_order_id, amount_cents, currency, coupon_id, discount_cents)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$orderId, $user['id'], $planId, $cycle, $gateway, $gatewayData['gateway_order_id'], $amount, $currency, $couponId, $discountCents],
        );

        Response::json([
            'order_id'       => $orderId,
            'gateway'        => $gateway,
            'amount_cents'   => $amount,
            'currency'       => $currency,
            'discount_cents' => $discountCents,
            'base_amount_cents' => $baseAmount,
            'plan_name'      => $plan['name'],
            ...$gatewayData,
        ]);
    }

    // ── Verify payment (activate subscription) ────────────────────────────────

    public function verifyPayment(Request $req): never
    {
        $user            = $req->params['user'];
        $orderId         = (string) $req->input('order_id', '');
        $gatewayPaymentId = (string) $req->input('payment_id', '');
        $signature       = (string) ($req->input('signature') ?? '');

        $order = Database::one(
            'SELECT * FROM payment_orders WHERE id = ? AND user_id = ? AND status = "PENDING"',
            [$orderId, $user['id']],
        );
        if (!$order) Response::json(['error' => 'Order not found or already processed'], 404);

        $settings = $this->settings();
        if (!$this->verifySignature($order['gateway'], $order['gateway_order_id'], $gatewayPaymentId, $signature, $settings)) {
            Response::json(['error' => 'Payment verification failed. Contact support.'], 400);
        }

        Database::exec(
            'UPDATE payment_orders SET status = "PAID", gateway_payment_id = ?, updated_at = NOW() WHERE id = ?',
            [$gatewayPaymentId, $orderId],
        );

        $invoiceId = $this->activateSubscription(
            $user['id'], $order['plan_id'], $order['billing_cycle'],
            $order['coupon_id'], (int) $order['discount_cents'], (int) $order['amount_cents'],
            $gatewayPaymentId,
        );
        if ($order['coupon_id']) $this->recordCouponUsage($order['coupon_id'], $user['id'], $invoiceId, (int) $order['discount_cents']);

        $sub     = Database::one('SELECT * FROM subscriptions WHERE user_id = ?', [$user['id']]);
        $invoice = Database::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        Response::json(['subscription' => $sub, 'invoice' => $invoice]);
    }

    // ── Cancel / Resume ───────────────────────────────────────────────────────

    public function cancel(Request $req): never
    {
        $user   = $req->params['user'];
        $reason = (string) ($req->input('reason') ?? '');
        Database::exec(
            'UPDATE subscriptions SET status = "CANCELLED", auto_renew = 0, updated_at = NOW() WHERE user_id = ?',
            [$user['id']],
        );
        Response::json(['ok' => true, 'reason' => $reason]);
    }

    public function resume(Request $req): never
    {
        $user = $req->params['user'];
        Database::exec(
            'UPDATE subscriptions SET status = "ACTIVE", auto_renew = 1, updated_at = NOW()
             WHERE user_id = ? AND status = "CANCELLED"',
            [$user['id']],
        );
        $sub = Database::one('SELECT * FROM subscriptions WHERE user_id = ?', [$user['id']]);
        Response::json(['subscription' => $sub]);
    }

    // ── Invoices ──────────────────────────────────────────────────────────────

    public function invoices(Request $req): never
    {
        $user     = $req->params['user'];
        $invoices = Database::all(
            'SELECT * FROM invoices WHERE user_id = ? ORDER BY date_millis DESC LIMIT 24',
            [$user['id']],
        );
        Response::json(['invoices' => $invoices]);
    }

    // ── Coupon validate ───────────────────────────────────────────────────────

    public function validateCoupon(Request $req): never
    {
        $user   = $req->params['user'];
        $code   = strtoupper(trim((string) ($req->input('code') ?? '')));
        $planId = (string) ($req->input('plan_id') ?? '');
        $cycle  = $this->validCycle((string) ($req->input('billing_cycle') ?? 'MONTHLY'));

        $coupon = $this->resolveCoupon($code, $user['id']);
        if (!$coupon) Response::json(['valid' => false, 'error' => 'Invalid or expired coupon'], 200);

        $originalAmount = 0; $discountCents = 0;
        if ($planId) {
            $plan = Database::one('SELECT * FROM subscription_plans WHERE id = ?', [$planId]);
            if ($plan) {
                $originalAmount = $cycle === 'YEARLY' ? (int) $plan['price_yearly_cents'] : (int) $plan['price_monthly_cents'];
                $discountCents  = $coupon['discount_percent'] > 0
                    ? (int) round($originalAmount * $coupon['discount_percent'] / 100)
                    : (int) $coupon['discount_cents'];
            }
        }

        Response::json([
            'valid'               => true,
            'code'                => $coupon['code'],
            'description'         => $coupon['description'],
            'discount_percent'    => (int) $coupon['discount_percent'],
            'discount_cents'      => $discountCents,
            'original_amount_cents' => $originalAmount,
            'final_amount_cents'  => max(0, $originalAmount - $discountCents),
        ]);
    }

    // ── Payment methods ───────────────────────────────────────────────────────

    public function paymentMethods(Request $req): never
    {
        $user    = $req->params['user'];
        $methods = Database::all(
            'SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, added_at_millis DESC',
            [$user['id']],
        );
        Response::json(['payment_methods' => $methods]);
    }

    public function addPaymentMethod(Request $req): never
    {
        $user        = $req->params['user'];
        $type        = strtoupper((string) ($req->input('type') ?? 'CARD'));
        $brand       = (string) ($req->input('brand') ?? '');
        $last4       = (string) ($req->input('last4') ?? '');
        $expiryMonth = (int)   ($req->input('expiry_month') ?? 0);
        $expiryYear  = (int)   ($req->input('expiry_year') ?? 0);
        $holderName  = (string) ($req->input('holder_name') ?? '');
        $makeDefault = (bool)  $req->input('make_default', true);

        if ($makeDefault) {
            Database::exec('UPDATE payment_methods SET is_default = 0 WHERE user_id = ?', [$user['id']]);
        }
        $id  = 'pm_' . bin2hex(random_bytes(8));
        $now = (int) (microtime(true) * 1000);
        Database::exec(
            'INSERT INTO payment_methods (id, user_id, type, brand, last4, expiry_month, expiry_year, holder_name, is_default, added_at_millis)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$id, $user['id'], $type, $brand, $last4, $expiryMonth, $expiryYear, $holderName, $makeDefault ? 1 : 0, $now],
        );
        $pm = Database::one('SELECT * FROM payment_methods WHERE id = ?', [$id]);
        Response::json(['payment_method' => $pm], 201);
    }

    public function removePaymentMethod(Request $req): never
    {
        $user = $req->params['user'];
        Database::exec('DELETE FROM payment_methods WHERE id = ? AND user_id = ?', [$req->params['id'], $user['id']]);
        Response::json(['ok' => true]);
    }

    public function setDefaultPaymentMethod(Request $req): never
    {
        $user = $req->params['user'];
        Database::exec('UPDATE payment_methods SET is_default = 0 WHERE user_id = ?', [$user['id']]);
        Database::exec('UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?', [$req->params['id'], $user['id']]);
        Response::json(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function activateSubscription(
        string $userId, string $planId, string $cycle,
        ?string $couponId, int $discountCents, int $amountCents,
        string $gatewayPaymentId = '',
    ): string {
        $plan     = Database::one('SELECT * FROM subscription_plans WHERE id = ?', [$planId]);
        $now      = (int) (microtime(true) * 1000);
        $trialDays = (int) ($plan['trial_days'] ?? 0);
        $status   = $trialDays > 0 ? 'TRIALING' : 'ACTIVE';
        $periodMs = $cycle === 'YEARLY' ? 365 * 86400 * 1000 : 30 * 86400 * 1000;
        $renewsAt = $now + $periodMs;
        $trialEnd = $trialDays > 0 ? $now + ($trialDays * 86400 * 1000) : null;

        $existing = Database::one('SELECT user_id FROM subscriptions WHERE user_id = ?', [$userId]);
        if ($existing) {
            Database::exec(
                'UPDATE subscriptions SET plan_id=?, status=?, billing_cycle=?, started_at_millis=?,
                 renews_at_millis=?, trial_ends_at_millis=?, auto_renew=1, updated_at=NOW() WHERE user_id=?',
                [$planId, $status, $cycle, $now, $renewsAt, $trialEnd, $userId],
            );
        } else {
            Database::exec(
                'INSERT INTO subscriptions (user_id, plan_id, status, billing_cycle, started_at_millis,
                 renews_at_millis, trial_ends_at_millis, auto_renew, updated_at)
                 VALUES (?,?,?,?,?,?,?,1,NOW())',
                [$userId, $planId, $status, $cycle, $now, $renewsAt, $trialEnd],
            );
        }

        $invoiceId = 'inv_' . bin2hex(random_bytes(8));
        $invNo     = 'INV-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        Database::exec(
            'INSERT INTO invoices (id, user_id, number, date_millis, amount_cents, currency, status,
             plan_name, billing_cycle_label, period_start_millis, period_end_millis, payment_method_last4)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $invoiceId, $userId, $invNo, $now, $amountCents,
                (string) ($plan['currency'] ?: 'INR'),
                'PAID',
                (string) $plan['name'],
                $cycle === 'YEARLY' ? 'Annual' : 'Monthly',
                $now, $renewsAt,
                $gatewayPaymentId ? substr($gatewayPaymentId, -4) : null,
            ],
        );
        return $invoiceId;
    }

    private function resolveCoupon(string $code, string $userId): ?array
    {
        $now    = (int) (microtime(true) * 1000);
        $coupon = Database::one(
            'SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at_millis IS NULL OR expires_at_millis > ?)',
            [$code, $now],
        );
        if (!$coupon) return null;
        $used = Database::one('SELECT id FROM coupon_usages WHERE coupon_id = ? AND user_id = ?', [$coupon['id'], $userId]);
        return $used ? null : $coupon;
    }

    private function recordCouponUsage(string $couponId, string $userId, string $invoiceId, int $discountCents): void
    {
        Database::exec(
            'INSERT IGNORE INTO coupon_usages (coupon_id, user_id, invoice_id, discount_cents) VALUES (?,?,?,?)',
            [$couponId, $userId, $invoiceId, $discountCents],
        );
    }

    // ── Gateway: Razorpay ─────────────────────────────────────────────────────

    private function createGatewayOrder(string $gw, int $amt, string $cur, string $receipt, array $plan, array $user, array $cfg): array
    {
        return match ($gw) {
            'RAZORPAY' => $this->razorpayOrder($amt, $cur, $receipt, $plan, $user, $cfg),
            'STRIPE'   => $this->stripeIntent($amt, $cur, $plan, $user, $cfg),
            'PAYU'     => $this->payuOrder($amt, $cur, $receipt, $plan, $user, $cfg),
            'PHONEPE'  => $this->phonepeOrder($amt, $cur, $receipt, $user, $cfg),
            'PAYPAL'   => $this->paypalOrder($amt, $cur, $plan, $user, $cfg),
            default    => Response::json(['error' => 'Unsupported gateway'], 400),
        };
    }

    private function razorpayOrder(int $amt, string $cur, string $receipt, array $plan, array $user, array $cfg): array
    {
        $keyId = $cfg['razorpay_key_id'] ?? '';
        $secret = $cfg['razorpay_key_secret'] ?? '';
        if ($keyId && $secret) {
            $res = $this->curlPost(
                'https://api.razorpay.com/v1/orders',
                ['amount' => $amt, 'currency' => $cur, 'receipt' => $receipt],
                ['Authorization: Basic ' . base64_encode("$keyId:$secret"), 'Content-Type: application/json'],
            );
            $gatewayOrderId = $res['id'] ?? ('order_test_' . bin2hex(random_bytes(6)));
        } else {
            $gatewayOrderId = 'order_test_' . bin2hex(random_bytes(6));
        }
        return [
            'gateway_order_id' => $gatewayOrderId,
            'razorpay_key_id'  => $keyId ?: 'rzp_test_placeholder',
            'name'             => $cfg['app_name'] ?? 'Devithor LMS',
            'description'      => $plan['name'],
            'prefill_name'     => $user['full_name'],
            'prefill_email'    => $user['email'],
            'prefill_contact'  => $user['mobile'] ?? '',
        ];
    }

    private function stripeIntent(int $amt, string $cur, array $plan, array $user, array $cfg): array
    {
        $sk = $cfg['stripe_secret_key'] ?? '';
        if ($sk) {
            $res = $this->curlPost(
                'https://api.stripe.com/v1/payment_intents',
                ['amount' => $amt, 'currency' => strtolower($cur), 'description' => $plan['name'], 'receipt_email' => $user['email']],
                ['Authorization: Bearer ' . $sk, 'Content-Type: application/x-www-form-urlencoded'],
                false,
            );
            $clientSecret = $res['client_secret'] ?? '';
            $piId         = $res['id'] ?? ('pi_test_' . bin2hex(random_bytes(6)));
        } else {
            $clientSecret = 'pi_test_client_secret_placeholder';
            $piId         = 'pi_test_' . bin2hex(random_bytes(6));
        }
        return [
            'gateway_order_id'      => $piId,
            'client_secret'         => $clientSecret,
            'stripe_publishable_key' => $cfg['stripe_publishable_key'] ?? 'pk_test_placeholder',
        ];
    }

    private function payuOrder(int $amt, string $cur, string $receipt, array $plan, array $user, array $cfg): array
    {
        $key    = $cfg['payu_merchant_key'] ?? 'payu_test_key';
        $salt   = $cfg['payu_merchant_salt'] ?? '';
        $amount = number_format($amt / 100, 2, '.', '');
        $hash   = hash('sha512', "$key|$receipt|$amount|{$plan['name']}|{$user['full_name']}|{$user['email']}|||||||||||$salt");
        return [
            'gateway_order_id' => 'payu_' . bin2hex(random_bytes(6)),
            'payu_key'    => $key,
            'txnid'       => $receipt,
            'amount'      => $amount,
            'productinfo' => $plan['name'],
            'firstname'   => $user['full_name'],
            'email'       => $user['email'],
            'hash'        => $hash,
            'surl'        => ($cfg['app_url'] ?? 'https://apptesting.in') . '/payment/success',
            'furl'        => ($cfg['app_url'] ?? 'https://apptesting.in') . '/payment/failure',
        ];
    }

    private function phonepeOrder(int $amt, string $cur, string $receipt, array $user, array $cfg): array
    {
        $merchantId  = $cfg['phonepe_merchant_id'] ?? 'DEVITHORTEST';
        $saltKey     = $cfg['phonepe_salt_key'] ?? '';
        $saltIndex   = (int) ($cfg['phonepe_salt_index'] ?? 1);
        $callbackUrl = ($cfg['app_url'] ?? 'https://apptesting.in') . '/payment/phonepe-callback';
        $payload     = json_encode([
            'merchantId'            => $merchantId,
            'merchantTransactionId' => $receipt,
            'merchantUserId'        => $user['id'],
            'amount'                => $amt,
            'redirectUrl'           => $callbackUrl,
            'redirectMode'          => 'REDIRECT',
            'callbackUrl'           => $callbackUrl,
            'paymentInstrument'     => ['type' => 'PAY_PAGE'],
        ]);
        $base64  = base64_encode($payload);
        $checksum = hash('sha256', $base64 . '/pg/v1/pay' . $saltKey) . '###' . $saltIndex;
        $gwOrderId = 'phonepe_' . bin2hex(random_bytes(6));
        return [
            'gateway_order_id'    => $gwOrderId,
            'merchant_transaction_id' => $receipt,
            'phonepe_payload'     => $base64,
            'phonepe_checksum'    => $checksum,
            'redirect_url'        => $callbackUrl,
        ];
    }

    private function paypalOrder(int $amt, string $cur, array $plan, array $user, array $cfg): array
    {
        $amount = number_format($amt / 100, 2, '.', '');
        return [
            'gateway_order_id' => 'paypal_' . bin2hex(random_bytes(6)),
            'amount'           => $amount,
            'currency'         => $cur,
            'description'      => $plan['name'],
        ];
    }

    private function verifySignature(string $gw, string $gwOrderId, string $paymentId, string $sig, array $cfg): bool
    {
        return match ($gw) {
            'RAZORPAY' => (function () use ($gwOrderId, $paymentId, $sig, $cfg): bool {
                $secret = $cfg['razorpay_key_secret'] ?? '';
                if (!$secret) return true; // test mode
                return hash_equals(hash_hmac('sha256', "$gwOrderId|$paymentId", $secret), $sig);
            })(),
            'STRIPE'  => !empty($paymentId),
            'PAYU'    => !empty($paymentId),
            'PHONEPE' => !empty($paymentId),
            'PAYPAL'  => !empty($paymentId),
            default   => false,
        };
    }

    private function curlPost(string $url, array $data, array $headers, bool $json = true): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POSTFIELDS     => $json ? json_encode($data) : http_build_query($data),
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode((string) $res, true) ?? [];
    }

    private function settings(): array
    {
        try {
            return array_column(Database::all('SELECT key_name, value FROM settings'), 'value', 'key_name');
        } catch (\Throwable) {
            return [];
        }
    }

    private function validCycle(string $c): string
    {
        return in_array($c, ['MONTHLY', 'YEARLY'], true) ? $c : 'MONTHLY';
    }

    private function validGateway(string $g): string
    {
        $allowed = ['RAZORPAY', 'STRIPE', 'PAYU', 'PHONEPE', 'PAYPAL'];
        return in_array($g, $allowed, true) ? $g : 'RAZORPAY';
    }
}
