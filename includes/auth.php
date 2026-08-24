<?php
/**
 * Admin session auth helpers. Requires config/db.php to already be loaded.
 */

ensure_session_started();

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

// An admin session left idle this long is logged out on its next request —
// standard practice for a panel that can see customer PII and hold SMTP
// credentials. Resets on every admin page load, so it's 60 idle minutes,
// not 60 minutes total.
const ADMIN_IDLE_TIMEOUT_SECONDS = 3600;

/**
 * Login + active-account check, without the forced-password-change
 * redirect below. Used by require_admin() itself and by
 * force_password_change.php, which can't call require_admin() directly —
 * that would redirect the page back to itself in a loop.
 */
function require_admin_base(): void
{
    if (!admin_logged_in()) {
        redirect('/admin/login.php');
    }

    $lastActivity = $_SESSION['admin_last_activity'] ?? null;
    if ($lastActivity !== null && (time() - $lastActivity) > ADMIN_IDLE_TIMEOUT_SECONDS) {
        // Clear just the admin identity, not the whole session (see the
        // suspended-account branch below for why) — flash_set() right
        // after a full session_destroy() wouldn't survive to the login page.
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_last_activity']);
        flash_set('error', 'You were logged out after a period of inactivity. Please log in again.');
        redirect('/admin/login.php');
    }
    $_SESSION['admin_last_activity'] = time();

    // Every state-changing admin request must carry a valid CSRF token, so
    // a malicious page an admin happens to have open elsewhere can't
    // silently submit actions (delete a shipment, change SMTP credentials,
    // demote another admin) using their logged-in session. Checked here,
    // centrally, so every admin page that calls require_admin() or
    // require_super_admin() is covered automatically — no per-page code.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Your form session expired and this save did not go through — please try again.');
        // Back to the page the form actually lives on (e.g.
        // admin_edit.php?id=5, smtp_settings.php), not always the
        // dashboard — otherwise a failed save on some other page looks
        // like it silently did nothing instead of clearly failing.
        // REQUEST_URI (not SCRIPT_NAME) so query params like ?id=5 survive.
        $current = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php';
        redirect($current);
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

function require_admin(): void
{
    require_admin_base();

    $stmt = db()->prepare('SELECT must_change_password FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    if ((int) $stmt->fetchColumn() === 1) {
        $current = $_SERVER['SCRIPT_NAME'] ?? '';
        $exempt = str_ends_with($current, '/admin/force_password_change.php') || str_ends_with($current, '/admin/logout.php');
        if (!$exempt) {
            redirect('/admin/force_password_change.php');
        }
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
        log_admin_activity('Logged in', '', $admin['id'], $admin['full_name']);
        return true;
    }

    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * Records a sensitive admin action to the audit trail viewable at
 * /admin/activity_log.php (super admins only). Fails open — a missing
 * admin_activity_log table (not migrated yet) or any DB error here never
 * blocks the action itself, only skips logging it.
 *
 * $adminId/$adminName let attempt_admin_login() log a successful login
 * before $_SESSION is fully usable elsewhere; every other call site omits
 * them and gets the currently logged-in admin.
 */
function log_admin_activity(string $action, string $details = '', ?int $adminId = null, ?string $adminName = null): void
{
    if ($adminId === null) {
        $admin = current_admin();
        $adminId = $admin['id'] ?? null;
        $adminName = $admin['full_name'] ?? 'Unknown';
    }

    try {
        $stmt = db()->prepare('
            INSERT INTO admin_activity_log (admin_id, admin_name, action, details, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$adminId, $adminName ?? 'Unknown', $action, $details, client_ip()]);
    } catch (PDOException $e) {
        // Same rationale as login_attempts — a missing table should never
        // break the admin action itself, only skip the audit trail entry.
    }
}

function current_admin(): ?array
{
    if (!admin_logged_in()) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, email, full_name, is_super_admin, is_active, must_change_password FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function set_admin_password(int $adminId, string $newPassword): void
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    // Setting a password (self-service, a mailed reset link, or a super
    // admin setting one directly) always satisfies any pending forced
    // change — admin_edit.php re-sets the flag afterward if it wants the
    // new password itself to be temporary.
    $stmt = db()->prepare('UPDATE admins SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, must_change_password = 0 WHERE id = ?');
    $stmt->execute([$hash, $adminId]);
}

function set_must_change_password(int $adminId, bool $mustChange): void
{
    $stmt = db()->prepare('UPDATE admins SET must_change_password = ? WHERE id = ?');
    $stmt->execute([$mustChange ? 1 : 0, $adminId]);
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
