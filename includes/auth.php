<?php
/**
 * Admin session auth helpers. Requires config/db.php to already be loaded.
 */

ensure_session_started();

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('/admin/login.php');
    }
    // Re-check on every request (not just at login) so a suspended admin's
    // active session is cut off immediately, not just their next login.
    $stmt = db()->prepare('SELECT is_active FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) {
        // Clear just the admin identity, not the whole session — admin_logout()
        // wipes $_SESSION entirely, which would also erase the flash message
        // below before it ever reaches the login page.
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        flash_set('error', 'This account has been suspended.');
        redirect('/admin/login.php');
    }
}

function require_super_admin(): void
{
    require_admin();
    $admin = current_admin();
    if (!$admin || !$admin['is_super_admin']) {
        flash_set('error', 'You don\'t have permission to do that.');
        redirect('/admin/dashboard.php');
    }
}

function attempt_admin_login(string $identifier, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$identifier, $identifier]);
    $admin = $stmt->fetch();

    if ($admin && $admin['is_active'] && password_verify($password, $admin['password_hash'])) {
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
    $stmt = db()->prepare('SELECT id, username, email, full_name, is_super_admin, is_active FROM admins WHERE id = ? LIMIT 1');
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

function count_active_super_admins(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM admins WHERE is_super_admin = 1 AND is_active = 1')->fetchColumn();
}
