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

function attempt_admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
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
