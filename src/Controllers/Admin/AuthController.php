<?php
declare(strict_types=1);

namespace Devithor\Controllers\Admin;

use Devithor\Auth;
use Devithor\Database;
use Devithor\Request;
use Devithor\Response;
use Devithor\View;

/**
 * Admin login flow. Posts to /admin/login set $_SESSION['admin_user_id'] —
 * Auth::requireAdmin() middleware checks it on every other admin route.
 */
final class AuthController
{
    public function showLogin(Request $req): never
    {
        if (!empty($_SESSION['admin_user_id'])) {
            Response::redirect('/admin/dashboard');
        }
        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        Response::html(View::render('admin/login', ['error' => $error]));
    }

    public function login(Request $req): never
    {
        $email    = strtolower(trim((string) $req->input('email')));
        $password = (string) $req->input('password');

        $user = Database::one(
            'SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1',
            [$email, 'ADMIN'],
        );

        if (!$user || !$user['password_hash'] || !Auth::verifyPassword($password, $user['password_hash'])) {
            // Constant-ish failure regardless of which field was wrong — don't
            // help an attacker enumerate valid emails.
            $_SESSION['flash_error'] = 'Email or password is incorrect.';
            Response::redirect('/admin/login');
        }

        // Regenerate the session ID on login to defeat session fixation.
        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = $user['id'];

        Database::exec('UPDATE users SET last_sign_in_at = NOW() WHERE id = ?', [$user['id']]);
        Response::redirect('/admin/dashboard');
    }

    public function logout(Request $req): never
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        Response::redirect('/admin/login');
    }
}

