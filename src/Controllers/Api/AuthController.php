<?php
declare(strict_types=1);

namespace Devithor\Controllers\Api;

use Devithor\Auth;
use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\Validator;

/**
 * Mobile auth — passwordless email + name flow that matches the Android
 * AuthScreen exactly. First call creates the user; subsequent calls reuse
 * the existing row by email.
 *
 * POST /api/v1/auth/login   { email, full_name, role? } → { token, user }
 * GET  /api/v1/auth/me                                   → { user }
 * POST /api/v1/auth/logout                               → 204
 */
final class AuthController
{
    public function login(Request $req): never
    {
        $errors = Validator::check($req->body, [
            'email'     => ['required', 'email', 'max:190'],
            'full_name' => ['required', 'min:2', 'max:190'],
            'role'      => ['in:STUDENT,INSTRUCTOR,PARENT'],
        ]);
        if ($errors) Response::json(['errors' => $errors], 422);

        $email = strtolower(trim((string) $req->input('email')));
        $name  = trim((string) $req->input('full_name'));
        $role  = (string) ($req->input('role') ?? 'STUDENT');

        $user = Database::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user === null) {
            $id = 'u_' . bin2hex(random_bytes(8));
            Database::exec(
                'INSERT INTO users (id, email, full_name, role) VALUES (?, ?, ?, ?)',
                [$id, $email, $name, $role],
            );
            $user = Database::one('SELECT * FROM users WHERE id = ?', [$id]);
        } else {
            // Refresh name on each sign-in — cheap, avoids a separate "edit name" endpoint.
            Database::exec(
                'UPDATE users SET full_name = ?, last_sign_in_at = NOW() WHERE id = ?',
                [$name, $user['id']],
            );
            $user['full_name'] = $name;
        }

        $token = Auth::issueApiToken($user['id']);
        Response::json([
            'token' => $token,
            'user'  => $this->shape($user),
        ]);
    }

    public function me(Request $req): never
    {
        Response::json(['user' => $this->shape($req->params['user'])]);
    }

    public function logout(Request $req): never
    {
        $token = $req->bearerToken();
        if ($token) Auth::revokeApiToken($token);
        Response::noContent();
    }

    /**
     * Trim the user row to the fields the Android domain expects. Keeps the
     * API contract intentionally narrow — adding new fields here is
     * deliberate, not accidental.
     */
    private function shape(array $u): array
    {
        return [
            'id'           => $u['id'],
            'email'        => $u['email'],
            'full_name'    => $u['full_name'],
            'role'         => $u['role'],
            'avatar_url'   => $u['avatar_url'],
            'tenant_id'    => $u['tenant_id'],
            'xp'           => (int) $u['xp'],
            'streak_days'  => (int) $u['streak_days'],
        ];
    }
}
