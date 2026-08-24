<?php
/**
 * Admin session auth helpers. Requires config/db.php to already be loaded.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('/admin/login.php');
    }
}

function attempt_admin_login(string $identifier, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        return true;
    }

    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function current_admin(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, email, full_name FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function set_admin_password(int $adminId, string $newPassword): void
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = db()->prepare('UPDATE admins SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
    $stmt->execute([$hash, $adminId]);
}

/**
 * Starts a password-reset flow for the admin with this email (if one
 * exists) and returns the raw token to email them — or null if no admin
 * uses that email. Only a SHA-256 hash of the token is stored, so a
 * database leak alone can't be used to reset a password.
 */
function create_password_reset(string $email): ?string
{
    $stmt = db()->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $stmt = db()->prepare('UPDATE admins SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
    $stmt->execute([hash('sha256', $token), $expires, $admin['id']]);

    return $token;
}

function find_admin_by_reset_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}
