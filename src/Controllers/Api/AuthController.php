<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Auth;
use Devithor\Database;
use Devithor\Geolocation;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;

/**
 * Mobile auth — OTP-based passwordless email flow.
 *
 * Step 1:  POST /api/v1/auth/request-otp  { email }
 *          → Generates a 6-digit OTP, stores in auth_otps, sends email.
 *          → In APP_DEBUG=true mode also returns { otp } for easy testing.
 *
 * Step 2:  POST /api/v1/auth/verify-otp   { email, otp }
 *          → Validates OTP (max 5 attempts, 10-min expiry).
 *          → Creates the user row on first sign-in, or refreshes last_sign_in_at.
 *          → Returns { token, user, onboarding_required }.
 *
 * Authenticated:
 *          GET  /api/v1/auth/me            → full user profile
 *          POST /api/v1/auth/logout        → revoke token (204)
 *
 * Backward-compat:
 *          POST /api/v1/auth/login         → 410 Gone with migration message
 */
final class AuthController
{
    private const OTP_TTL_MINUTES  = 10;
    private const OTP_MAX_ATTEMPTS = 5;

    // ── Public: request an OTP ────────────────────────────────────────────────

    public function requestOtp(Request $req): never
    {
        $errors = Validator::check($req->body, [
            'email' => ['required', 'email', 'max:190'],
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
        }

        $email = strtolower(trim((string) $req->input('email')));

        // Expire any stale unused OTPs for this email before creating a fresh one.
        Database::exec(
            'UPDATE auth_otps SET used = 1 WHERE email = ? AND used = 0',
            [$email],
        );

        $otp       = $this->generateOtp();
        $expiresAt = date('Y-m-d H:i:s', time() + self::OTP_TTL_MINUTES * 60);
        $ip        = Geolocation::clientIp();

        Database::exec(
            'INSERT INTO auth_otps (email, otp_code, expires_at, ip) VALUES (?, ?, ?, ?)',
            [$email, $otp, $expiresAt, $ip],
        );

        $sent = $this->sendOtpEmail($email, $otp);

        $payload = ['sent' => $sent];

        // In debug mode expose the OTP so Postman / Android emulator can verify
        // without a live SMTP server.
        if (getenv('APP_DEBUG') === 'true') {
            $payload['otp'] = $otp;
        }

        Response::json($payload);
    }

    // ── Public: verify OTP and sign in ────────────────────────────────────────

    public function verifyOtp(Request $req): never
    {
        $errors = Validator::check($req->body, [
            'email' => ['required', 'email', 'max:190'],
            'otp'   => ['required', 'min:4', 'max:10'],
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
        }

        $email = strtolower(trim((string) $req->input('email')));
        $otp   = trim((string) $req->input('otp'));

        // Fetch the most recent unused, non-expired OTP for this email.
        $record = Database::one(
            'SELECT * FROM auth_otps
              WHERE email = ? AND used = 0 AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1',
            [$email],
        );

        if ($record === null) {
            Response::json(['error' => 'otp_expired', 'message' => 'OTP expired or not found. Please request a new one.'], 401);
        }

        // Increment attempt counter first to prevent brute-force even if we abort.
        $newAttempts = (int) $record['attempts'] + 1;
        Database::exec(
            'UPDATE auth_otps SET attempts = ? WHERE id = ?',
            [$newAttempts, $record['id']],
        );

        if ($newAttempts > self::OTP_MAX_ATTEMPTS) {
            // Mark it used so the user is forced to re-request.
            Database::exec('UPDATE auth_otps SET used = 1 WHERE id = ?', [$record['id']]);
            Auth::logLogin(null, $email, 'OTP', 'API', false, 'Too many attempts');
            Response::json(['error' => 'too_many_attempts', 'message' => 'Too many incorrect attempts. Please request a new OTP.'], 429);
        }

        if (!hash_equals($record['otp_code'], $otp)) {
            $remaining = self::OTP_MAX_ATTEMPTS - $newAttempts;
            Auth::logLogin(null, $email, 'OTP', 'API', false, 'Wrong OTP');
            Response::json([
                'error'   => 'invalid_otp',
                'message' => 'Incorrect OTP.',
                'attempts_remaining' => max(0, $remaining),
            ], 401);
        }

        // Valid OTP — mark as used.
        Database::exec('UPDATE auth_otps SET used = 1 WHERE id = ?', [$record['id']]);

        // Upsert the user.
        $user = Database::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user === null) {
            $id = 'u_' . bin2hex(random_bytes(8));
            Database::exec(
                'INSERT INTO users (id, email, full_name, role, onboarding_completed)
                 VALUES (?, ?, ?, ?, 0)',
                [$id, $email, $this->nameFromEmail($email), 'STUDENT'],
            );
            $user = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        } else {
            Database::exec(
                'UPDATE users SET last_sign_in_at = NOW() WHERE id = ?',
                [$user['id']],
            );
            $user['last_sign_in_at'] = date('Y-m-d H:i:s');
        }

        if ($user['is_banned'] ?? false) {
            Auth::logLogin($user['id'], $email, 'OTP', 'API', false, 'Banned');
            Response::json([
                'error'   => 'banned',
                'message' => 'Your account has been suspended. Contact support.',
            ], 403);
        }

        $token = Auth::issueApiToken($user['id']);
        Auth::logLogin($user['id'], $email, 'OTP', 'API', true);

        Response::json([
            'token'               => $token,
            'user'                => $this->shape($user),
            'onboarding_required' => ((int) ($user['onboarding_completed'] ?? 0)) === 0,
        ]);
    }

    // ── Authenticated: me ─────────────────────────────────────────────────────

    public function me(Request $req): never
    {
        Response::json(['user' => $this->shape($req->params['user'])]);
    }

    // ── Authenticated: logout ─────────────────────────────────────────────────

    public function logout(Request $req): never
    {
        $token = $req->bearerToken();
        if ($token) {
            Auth::revokeApiToken($token);
        }
        Response::noContent();
    }

    // ── Backward-compat: old /auth/login endpoint ─────────────────────────────

    /**
     * The old single-step login (email + name → token) is retired in favour of
     * the two-step OTP flow. Return 410 Gone with a clear migration message so
     * outdated app builds display a useful error rather than a generic failure.
     */
    public function login(Request $req): never
    {
        Response::json([
            'error'   => 'endpoint_retired',
            'message' => 'The /auth/login endpoint has been replaced by the OTP flow. '
                       . 'Please call POST /api/v1/auth/request-otp then POST /api/v1/auth/verify-otp.',
            'docs'    => [
                'step1' => 'POST /api/v1/auth/request-otp  { "email": "..." }',
                'step2' => 'POST /api/v1/auth/verify-otp   { "email": "...", "otp": "..." }',
            ],
        ], 410);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Trim the user row to the fields the Android domain model expects.
     * Adding a new field here is a deliberate API contract change.
     */
    private function shape(array $u): array
    {
        return [
            'id'                   => $u['id'],
            'email'                => $u['email'],
            'full_name'            => $u['full_name'],
            'role'                 => $u['role'],
            'avatar_url'           => $u['profile_picture_url'] ?? $u['avatar_url'] ?? null,
            'tenant_id'            => $u['tenant_id'] ?? 'default',
            'xp'                   => (int) ($u['xp'] ?? 0),
            'streak_days'          => (int) ($u['streak_days'] ?? 0),
            'dob'                  => $u['dob'] ?? null,
            'gender'               => $u['gender'] ?? null,
            'mobile'               => $u['mobile'] ?? null,
            'whatsapp'             => $u['whatsapp'] ?? null,
            'school_name'          => $u['school_name'] ?? null,
            'class_id'             => $u['class_id'] ?? null,
            'city'                 => $u['city'] ?? null,
            'state'                => $u['state'] ?? null,
            'onboarding_completed' => (bool) ($u['onboarding_completed'] ?? false),
        ];
    }

    /** Generate a cryptographically random 6-digit OTP string. */
    private function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send the OTP email via PHP's built-in mail().
     * Returns true if mail() accepted the message (not a delivery guarantee).
     */
    private function sendOtpEmail(string $email, string $otp): bool
    {
        $appName = getenv('APP_NAME') ?: 'Devithor';
        $subject = "Your {$appName} OTP";
        $body    = "Your OTP for {$appName} is: {$otp}. "
                 . "Valid for " . self::OTP_TTL_MINUTES . " minutes. "
                 . "Do not share this with anyone.";

        $headers  = "From: noreply@" . (getenv('MAIL_DOMAIN') ?: 'devithor.com') . "\r\n";
        $headers .= "Reply-To: noreply@" . (getenv('MAIL_DOMAIN') ?: 'devithor.com') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        // mail() returns false when it cannot hand off to the MTA.
        // We log the failure but do NOT abort — the caller checks the returned bool.
        $sent = @mail($email, $subject, $body, $headers);

        if (!$sent && getenv('APP_DEBUG') === 'true') {
            error_log("[AuthController] mail() returned false for $email");
        }

        return $sent !== false;
    }

    /**
     * Build a reasonable display name from an email address when a new account
     * is auto-created during OTP verify (user can update it in onboarding).
     */
    private function nameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];
        // Replace dots/underscores/hyphens with spaces and title-case.
        $name = ucwords(str_replace(['.', '_', '-'], ' ', $local));
        return substr($name, 0, 190);
    }
}
