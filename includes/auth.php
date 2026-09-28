<?php
/**
 * Admin session auth helpers. Requires config/db.php to already be loaded.
 */

ensure_session_started();

// ---------------------------------------------------------------
// Hiding the admin entrance.
//
// None of this replaces the real defences (rate limiting, lockout, strong
// passwords, CSRF): it removes the sign-in page from view so automated
// scanners that hammer /admin/login.php never find it in the first place.
// Two independent, optional switches, both set at /admin/branding.php:
//
//   - the header sign-in link is off by default and a super admin can
//     turn it back on;
//   - an access key, when set, makes the sign-in and password-recovery
//     pages answer 404 to anyone who does not present it, exactly as if
//     the page did not exist.
//
// Both default to "open and hidden link", so a fresh install is never
// locked out: with no key set, the pages behave normally.
// ---------------------------------------------------------------

/**
 * The value stored in the gate cookie: an HMAC of the key rather than the
 * key itself, so the raw secret never sits in a cookie, and so the cookie
 * from one key is worthless once the key is changed. Keyed on the app's own
 * salt (the default admin password hash constant is not available here, so
 * the key is its own HMAC key, which is enough: the cookie only has to be
 * unguessable and to change when the key changes).
 */
function admin_gate_cookie_value(string $key): string
{
    return hash_hmac('sha256', 'admin-gate', $key);
}

/**
 * Enforces the access-key gate. Call at the very top of every page that is
 * a way in: the sign-in page and the two password-recovery pages.
 *
 * With no key set, returns immediately. With a key set, the visitor must
 * present it once as ?k=KEY (which is then remembered in a cookie so it is
 * not needed on every click); anyone who has not is shown the ordinary 404
 * page and the request ends there, so a scanner cannot tell the page from
 * one that was never there. An already-signed-in admin is never gated.
 */
function enforce_admin_gate(): void
{
    $key = admin_access_key();
    if ($key === '' || admin_logged_in()) {
        return;
    }

    $cookieName = 'agk';
    $expected = admin_gate_cookie_value($key);

    // Presenting the key in the URL: check it in constant time, remember
    // it, then send the visitor to the clean URL so the key does not sit
    // in the address bar, browser history or the Referer of the next click.
    $supplied = (string) ($_GET['k'] ?? '');
    if ($supplied !== '' && hash_equals($key, $supplied)) {
        setcookie($cookieName, $expected, [
            'expires'  => time() + 86400 * 30,
            'path'     => '/admin/',
            'secure'   => is_https_for_cookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Drop only the key from the address, keeping any other
        // parameter (the reset-password token, for one), so presenting the
        // key never loses the rest of the request.
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/login.php');
        $path = strtok($uri, '?');
        $rest = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $rest);
        unset($rest['k']);
        $clean = ($path ?: '/admin/login.php') . ($rest ? '?' . http_build_query($rest) : '');
        redirect($clean);
    }

    // Returning with the remembered cookie.
    if (hash_equals($expected, (string) ($_COOKIE[$cookieName] ?? ''))) {
        return;
    }

    // No key, no cookie: this page does not exist, as far as anyone can tell.
    http_response_code(404);
    if (is_file(__DIR__ . '/../404.php')) {
        // The 404.php page renders the site's own not-found screen. It reads
        // no request input that matters here, so including it is safe.
        include __DIR__ . '/../404.php';
    } else {
        echo 'Not Found';
    }
    exit;
}

function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * What a session is tied to: the browser that signed in and the network it
 * signed in from. Hashed, so nothing identifying is kept in the session
 * itself, and deliberately coarse on the address (see client_network) so a
 * phone moving between masts does not sign itself out.
 */
function admin_session_fingerprint(): string
{
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . client_network());
}

/**
 * Ends the signed-in session and sends the person back to the login page
 * with an explanation.
 *
 * Clears only the admin identity rather than destroying the whole session,
 * because flash_set() right after a session_destroy() would have nothing
 * left to write the message into and the login page would explain nothing.
 */
function end_admin_session(string $message): void
{
    foreach (['admin_id', 'admin_name', 'admin_last_activity', 'admin_login_time', 'admin_fingerprint', 'admin_session_rotated'] as $key) {
        unset($_SESSION[$key]);
    }
    session_regenerate_id(true);
    flash_set('error', $message);
    redirect('/admin/login.php');
}

/**
 * Login + active-account check, without the forced-password-change
 * redirect below. Used by require_admin() itself and by
 * force_password_change.php, which can't call require_admin() directly, * that would redirect the page back to itself in a loop.
 */
function require_admin_base(): void
{
    if (!admin_logged_in()) {
        redirect('/admin/login.php');
    }

    $now = time();

    // A session that has been sitting untouched is over.
    $lastActivity = $_SESSION['admin_last_activity'] ?? null;
    if ($lastActivity !== null && ($now - $lastActivity) > ADMIN_IDLE_TIMEOUT_SECONDS) {
        end_admin_session('You were signed out after '
            . (int) (ADMIN_IDLE_TIMEOUT_SECONDS / 60) . ' minutes of inactivity. Please sign in again.');
    }

    // ...and so is one that has been going all day, however busy it was.
    $startedAt = $_SESSION['admin_login_time'] ?? null;
    if ($startedAt !== null && ($now - $startedAt) > ADMIN_ABSOLUTE_TIMEOUT_SECONDS) {
        end_admin_session('For security, sessions end after '
            . (int) (ADMIN_ABSOLUTE_TIMEOUT_SECONDS / 3600) . ' hours. Please sign in again.');
    }

    // The session is tied to the browser and the network it was created
    // on. A cookie copied off this machine and replayed from somewhere
    // else no longer matches, and is thrown out rather than honoured.
    $fingerprint = $_SESSION['admin_fingerprint'] ?? null;
    if ($fingerprint !== null && !hash_equals($fingerprint, admin_session_fingerprint())) {
        log_admin_activity('Session rejected', 'Fingerprint did not match the session it was issued for');
        end_admin_session('Your session could not be verified, so it was ended. Please sign in again.');
    }

    // Rotate the session id periodically, so a captured id is only worth
    // something for a short window rather than for the whole session.
    $rotatedAt = $_SESSION['admin_session_rotated'] ?? $now;
    if (($now - $rotatedAt) > ADMIN_SESSION_ROTATE_SECONDS) {
        session_regenerate_id(true);
        $_SESSION['admin_session_rotated'] = $now;
    }

    $_SESSION['admin_last_activity'] = $now;

    // Every state-changing admin request must carry a valid CSRF token, so
    // a malicious page an admin happens to have open elsewhere can't
    // silently submit actions (delete a shipment, change SMTP credentials,
    // demote another admin) using their logged-in session. Checked here,
    // centrally, so every admin page that calls require_admin() or
    // require_super_admin() is covered automatically, no per-page code.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify($_POST['csrf_token'] ?? '')) {
        flash_set('error', 'Your form session expired and this save did not go through, please try again.');
        // Back to the page the form actually lives on (e.g.
        // admin_edit.php?id=5, smtp_settings.php), not always the
        // dashboard, otherwise a failed save on some other page looks
        // like it silently did nothing instead of clearly failing.
        // REQUEST_URI (not SCRIPT_NAME) so query params like ?id=5 survive.
        // Kept to a single leading slash and stripped of any control
        // character, so the value the browser sent can never steer the
        // redirect off this site or split the Location header.
        $current = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/dashboard.php');
        $current = preg_replace('/[\x00-\x1F\x7F]/', '', $current);
        if ($current === '' || $current[0] !== '/' || str_starts_with($current, '//')) {
            $current = '/admin/dashboard.php';
        }
        redirect($current);
    }

    // Re-check on every request (not just at login) so a suspended admin's
    // active session is cut off immediately, not just their next login.
    $stmt = db()->prepare('SELECT is_active FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) {
        // Clear just the admin identity, not the whole session: admin_logout()
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

    if (!$admin) {
        // Verify against a throwaway hash anyway. Without this, an unknown
        // username returns noticeably faster than a known one with the
        // wrong password, and that difference is enough to work out which
        // usernames exist before guessing a single password.
        password_verify($password, '$2y$12$usesomesillystringforsalttoavoidtimingleaksxxxxxxxxxxxxxxxxxxxxx');
        return false;
    }

    if ($admin['is_active'] && password_verify($password, $admin['password_hash'])) {
        // A brand new id for the signed-in session, so an id an attacker
        // may already know cannot become an authenticated one.
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_last_activity'] = time();
        $_SESSION['admin_session_rotated'] = time();
        $_SESSION['admin_fingerprint'] = admin_session_fingerprint();
        // A fresh CSRF token per session, never one carried over from
        // before sign-in.
        unset($_SESSION['csrf_token']);

        // If the stored hash was made with older settings than this PHP
        // build now uses, quietly upgrade it while the password is in hand.
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $rehash->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }

        log_admin_activity('Signed in', '', $admin['id'], $admin['full_name']);
        return true;
    }

    return false;
}

function admin_logout(): void
{
    $_SESSION = [];
    // Expire the cookie itself as well as the server-side session, so a
    // shared or public computer is not left holding a usable id.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * Records a sensitive admin action to the audit trail viewable at
 * /admin/activity_log.php (super admins only). Fails open: a missing
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
        // Same rationale as login_attempts: a missing table should never
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
    // Cost 12 rather than PHP's default 10: roughly four times the work
    // per guess for an attacker who gets hold of the table, and still
    // well under a tenth of a second for the one person signing in.
    $hash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
    // Setting a password (self-service, a mailed reset link, or a super
    // admin setting one directly) always satisfies any pending forced
    // change, admin_edit.php re-sets the flag afterward if it wants the
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
 * exists) and returns the raw token to email them, or null if no admin
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
