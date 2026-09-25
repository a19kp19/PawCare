<?php
/**
 * Authentication & role-based access control.
 * Roles: admin (clinic owner/manager), staff (vets, receptionists), owner (pet owner / client).
 */

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (!empty($_SESSION['uid'])) {
        $user = row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $_SESSION['uid']]);
        if (!$user) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

function full_name(array $u): string
{
    return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
}

function is_staff(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u && in_array($u['role'], ['admin', 'staff'], true);
}

function is_admin(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u && $u['role'] === 'admin';
}

function role_label(string $role): string
{
    return ['admin' => 'Administrator', 'staff' => 'Clinic Staff', 'owner' => 'Pet Owner'][$role] ?? ucfirst($role);
}

function home_for(array $u): string
{
    return is_staff($u) ? 'admin/' : 'owner/';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        if (is_api_request()) {
            json_response(['ok' => false, 'error' => 'Please log in.'], 401);
        }
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $u;
}

function require_staff(): array
{
    $u = require_login();
    if (!is_staff($u)) {
        abort(403, 'This area is for clinic staff only');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if (!is_admin($u)) {
        abort(403, 'Only administrators can open this page');
    }
    return $u;
}

function require_owner(): array
{
    $u = require_login();
    if (is_staff($u)) {
        redirect('admin/');
    }
    return $u;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until']);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    // Clear everything and rotate the session id (keeps the session alive for the goodbye message).
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Returns seconds remaining if the login form is temporarily locked (brute-force protection). */
function login_lock_remaining(): int
{
    $until = (int) ($_SESSION['login_locked_until'] ?? 0);
    return max(0, $until - time());
}

function register_login_failure(): void
{
    $_SESSION['login_failures'] = (int) ($_SESSION['login_failures'] ?? 0) + 1;
    if ($_SESSION['login_failures'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 60;
        $_SESSION['login_failures'] = 0;
    }
}

function password_problems(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain letters and at least one number.';
    }
    return null;
}

function random_password(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out . random_int(1, 9);
}
