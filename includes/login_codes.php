<?php
/**
 * Sign-in codes for new browsers.
 *
 * With this switched on (super admin, /admin/security.php), a correct
 * password is no longer enough on its own the first time an admin signs in
 * from a browser. A six-digit code is emailed to the address on their
 * account, and they have to type it in before the panel opens. Once that
 * is done the browser can be remembered, so the code is only asked for
 * again on a different browser, after the browser's cookies are cleared,
 * after the remembered period runs out, or after a password change.
 *
 * Nothing about a code is stored in readable form. The code itself only
 * ever exists in the email: the session keeps a salted hash of it. A
 * remembered browser holds a random token in a cookie, and the database
 * keeps only a SHA-256 hash of that token, so a copy of the database alone
 * can not be used to pass as a remembered browser.
 *
 * Loaded by includes/auth.php. The mail library is only pulled in at the
 * moment a code is actually sent, so the many admin pages that never send
 * one do not pay for loading it.
 */

require_once __DIR__ . '/settings.php';

/** Digits in a code. */
const LOGIN_CODE_LENGTH = 6;
/** How long a code can be used for after it is sent. */
const LOGIN_CODE_TTL_SECONDS = 600;
/** Wrong guesses allowed before the code is thrown away. */
const LOGIN_CODE_MAX_TRIES = 5;
/** The shortest gap between two emails, so "send again" can not be hammered. */
const LOGIN_CODE_RESEND_GAP_SECONDS = 60;
/** Emails per account in a quarter of an hour. */
const LOGIN_CODE_MAX_SENDS = 5;
/** The whole code step has to be finished within this time of the password. */
const LOGIN_CODE_STEP_SECONDS = 900;
/** Cookie that holds this browser's remembered-browser tokens. */
const TRUSTED_BROWSER_COOKIE = 'atb';
/** Tokens one browser keeps at once, for a computer two admins share. */
const TRUSTED_BROWSER_MAX_PER_COOKIE = 5;
/** The choices offered for how long a browser is remembered. 0 = never. */
const TRUSTED_BROWSER_DAY_CHOICES = [0, 1, 7, 14, 30, 60, 90, 180, 365];

/**
 * True when sign-in codes are switched on.
 *
 * LOGIN_CODES_FORCE_OFF in config/config.php overrides the panel. It is
 * the way back in if email ever stops working while codes are on: add
 * define('LOGIN_CODES_FORCE_OFF', true); to config.php, sign in, fix the
 * email settings, then take the line out again.
 */
function login_codes_enabled(): bool
{
    if (defined('LOGIN_CODES_FORCE_OFF') && LOGIN_CODES_FORCE_OFF) {
        return false;
    }
    return get_setting('login_otp_enabled', '0') === '1';
}

/** Days a browser is remembered after a code is entered. 0 means never. */
function trusted_browser_days(): int
{
    $days = (int) get_setting('login_otp_remember_days', '30');
    return in_array($days, TRUSTED_BROWSER_DAY_CHOICES, true) ? $days : 30;
}

/** A readable label for a remembered-browser period, e.g. "30 days". */
function trusted_browser_days_label(int $days): string
{
    if ($days === 0) {
        return 'never (a code at every sign-in)';
    }
    return $days === 1 ? '1 day' : $days . ' days';
}

/**
 * The address shown on the code screen, partly hidden: enough for the
 * person to recognise which of their inboxes to check, not enough to give
 * a stranger standing behind them the whole address.
 */
function masked_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return '***';
    }
    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    $dot = strrpos($domain, '.');
    $domainName = $dot === false ? $domain : substr($domain, 0, $dot);
    $tld = $dot === false ? '' : substr($domain, $dot);

    $maskLocal = mb_substr($local, 0, 1) . str_repeat('*', max(2, min(6, mb_strlen($local) - 1)));
    $maskDomain = mb_substr($domainName, 0, 1) . str_repeat('*', max(2, min(6, mb_strlen($domainName) - 1)));

    return $maskLocal . '@' . $maskDomain . $tld;
}

/**
 * "Chrome on Windows", "Safari on iPhone" and so on, from the browser's
 * own description of itself. Only used as a label in the list of
 * remembered browsers, never for any decision.
 */
function browser_label_from_user_agent(string $ua): string
{
    $browser = 'A browser';
    $checks = [
        'Edg/'      => 'Edge',
        'OPR/'      => 'Opera',
        'SamsungBrowser' => 'Samsung Internet',
        'Firefox/'  => 'Firefox',
        'FxiOS'     => 'Firefox',
        'CriOS'     => 'Chrome',
        'Chrome/'   => 'Chrome',
        'Safari/'   => 'Safari',
    ];
    foreach ($checks as $needle => $name) {
        if (stripos($ua, $needle) !== false) {
            $browser = $name;
            break;
        }
    }

    $system = '';
    $systems = [
        'iPhone'    => 'iPhone',
        'iPad'      => 'iPad',
        'Android'   => 'Android',
        'Windows'   => 'Windows',
        'Mac OS X'  => 'Mac',
        'CrOS'      => 'ChromeOS',
        'Linux'     => 'Linux',
    ];
    foreach ($systems as $needle => $name) {
        if (stripos($ua, $needle) !== false) {
            $system = $name;
            break;
        }
    }

    return $system !== '' ? $browser . ' on ' . $system : $browser;
}

// ---------------------------------------------------------------
// Remembered browsers
// ---------------------------------------------------------------

/**
 * The tokens this browser is holding, as selector => validator.
 *
 * Anything that does not look exactly like a token is ignored rather than
 * looked up, so a tampered cookie never reaches a query.
 */
function trusted_browser_cookie_tokens(): array
{
    $raw = (string) ($_COOKIE[TRUSTED_BROWSER_COOKIE] ?? '');
    $tokens = [];
    foreach (explode(',', $raw) as $pair) {
        if (preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $pair, $m)) {
            $tokens[$m[1]] = $m[2];
        }
        if (count($tokens) >= TRUSTED_BROWSER_MAX_PER_COOKIE) {
            break;
        }
    }
    return $tokens;
}

function write_trusted_browser_cookie(array $tokens, int $days): void
{
    $pairs = [];
    foreach ($tokens as $selector => $validator) {
        $pairs[] = $selector . '.' . $validator;
    }
    $value = implode(',', $pairs);

    setcookie(TRUSTED_BROWSER_COOKIE, $value, [
        'expires'  => $value === '' ? time() - 3600 : time() + max(1, $days) * 86400,
        // Only ever sent to the admin area, never with public page requests.
        'path'     => '/admin/',
        'secure'   => is_https_for_cookie(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[TRUSTED_BROWSER_COOKIE] = $value;
}

/**
 * True if this browser was remembered for this admin and the remembering
 * has not run out.
 *
 * Fails closed: if the table is missing or the database errors, the
 * browser is treated as new and a code is asked for. Being asked for a
 * code once too often is an annoyance; skipping it when it should not be
 * is not acceptable.
 */
function browser_is_trusted(int $adminId): bool
{
    $tokens = trusted_browser_cookie_tokens();
    if (!$tokens) {
        return false;
    }

    try {
        $marks = implode(',', array_fill(0, count($tokens), '?'));
        $stmt = db()->prepare("
            SELECT id, selector, validator_hash FROM admin_trusted_browsers
            WHERE admin_id = ? AND expires_at > NOW() AND selector IN ($marks)
        ");
        $stmt->execute(array_merge([$adminId], array_keys($tokens)));

        foreach ($stmt->fetchAll() as $row) {
            $validator = $tokens[$row['selector']] ?? '';
            if ($validator !== '' && hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
                $touch = db()->prepare('UPDATE admin_trusted_browsers SET last_used_at = NOW(), ip_address = ? WHERE id = ?');
                $touch->execute([client_ip(), $row['id']]);
                return true;
            }
        }
    } catch (PDOException $e) {
        return false;
    }

    return false;
}

/**
 * Remembers this browser for this admin, for the configured number of
 * days. Does nothing when the site is set to ask for a code every time.
 */
function remember_this_browser(int $adminId): void
{
    $days = trusted_browser_days();
    if ($days <= 0) {
        return;
    }

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));

    try {
        $stmt = db()->prepare('
            INSERT INTO admin_trusted_browsers (admin_id, selector, validator_hash, browser_label, ip_address, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
        ');
        $stmt->execute([
            $adminId,
            $selector,
            hash('sha256', $validator),
            mb_substr(browser_label_from_user_agent((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 120),
            client_ip(),
            $days,
        ]);

        // Housekeeping on a small share of sign-ins: there is no cron on
        // shared hosting.
        if (random_int(1, 20) === 1) {
            db()->exec('DELETE FROM admin_trusted_browsers WHERE expires_at < NOW()');
        }
    } catch (PDOException $e) {
        return;
    }

    $tokens = trusted_browser_cookie_tokens();
    $tokens[$selector] = $validator;
    // Newest last; the oldest is dropped once the cookie is full.
    while (count($tokens) > TRUSTED_BROWSER_MAX_PER_COOKIE) {
        array_shift($tokens);
    }
    write_trusted_browser_cookie($tokens, $days);
}

/**
 * Forgets every remembered browser for an admin, so each one has to enter
 * a code again. With $keepThisBrowser, the browser making the request is
 * spared: used when someone changes their own password, where signing
 * themselves out of the computer they are sitting at would only annoy.
 *
 * Returns how many were forgotten.
 */
function forget_trusted_browsers(int $adminId, bool $keepThisBrowser = false): int
{
    try {
        $keep = $keepThisBrowser ? array_keys(trusted_browser_cookie_tokens()) : [];
        if ($keep) {
            $marks = implode(',', array_fill(0, count($keep), '?'));
            $stmt = db()->prepare("DELETE FROM admin_trusted_browsers WHERE admin_id = ? AND selector NOT IN ($marks)");
            $stmt->execute(array_merge([$adminId], $keep));
        } else {
            $stmt = db()->prepare('DELETE FROM admin_trusted_browsers WHERE admin_id = ?');
            $stmt->execute([$adminId]);
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}

/** Forgets one remembered browser, only if it belongs to this admin. */
function forget_trusted_browser(int $adminId, int $rowId): bool
{
    try {
        $stmt = db()->prepare('DELETE FROM admin_trusted_browsers WHERE id = ? AND admin_id = ?');
        $stmt->execute([$rowId, $adminId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/** Every remembered browser for an admin, newest first, with "this one" marked. */
function list_trusted_browsers(int $adminId): array
{
    try {
        $stmt = db()->prepare('
            SELECT id, selector, browser_label, ip_address, created_at, last_used_at, expires_at
            FROM admin_trusted_browsers
            WHERE admin_id = ? AND expires_at > NOW()
            ORDER BY COALESCE(last_used_at, created_at) DESC
        ');
        $stmt->execute([$adminId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }

    $mine = trusted_browser_cookie_tokens();
    foreach ($rows as &$row) {
        $row['is_this_browser'] = array_key_exists($row['selector'], $mine);
        unset($row['selector']);
    }
    unset($row);

    return $rows;
}

// ---------------------------------------------------------------
// The code step itself
// ---------------------------------------------------------------

/**
 * Starts the code step for an admin whose password has just been checked,
 * and sends the first code.
 *
 * The session gets a brand new id first, so nothing about this half-done
 * sign-in can be attached to an id an attacker planted beforehand.
 *
 * @return array{ok: bool, error?: string}
 */
function login_code_begin(array $admin, string $identifier): array
{
    $email = trim((string) ($admin['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'no_email'];
    }

    session_regenerate_id(true);
    $_SESSION['login_code'] = [
        'admin_id'    => (int) $admin['id'],
        'identifier'  => $identifier,
        'started'     => time(),
        'fingerprint' => admin_session_fingerprint(),
        'hash'        => '',
        'salt'        => '',
        'expires'     => 0,
        'tries'       => 0,
        'sent_at'     => 0,
    ];

    return login_code_send();
}

/**
 * The code step in progress for this browser, or null if there is none or
 * it has run out of time.
 */
function login_code_pending(): ?array
{
    $pending = $_SESSION['login_code'] ?? null;
    if (!is_array($pending) || empty($pending['admin_id'])) {
        return null;
    }
    if (time() - (int) $pending['started'] > LOGIN_CODE_STEP_SECONDS) {
        login_code_clear();
        return null;
    }
    // Same browser, same network as the one that typed the password. A
    // session copied elsewhere half way through does not carry on.
    if (!hash_equals((string) $pending['fingerprint'], admin_session_fingerprint())) {
        login_code_clear();
        return null;
    }
    return $pending;
}

function login_code_clear(): void
{
    unset($_SESSION['login_code']);
}

/** The admin row for the code step in progress, if the account is still active. */
function login_code_admin(): ?array
{
    $pending = login_code_pending();
    if ($pending === null) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int) $pending['admin_id']]);
    return $stmt->fetch() ?: null;
}

/**
 * Seconds until "send a new code" is allowed again, 0 if it is allowed now.
 */
function login_code_resend_wait(): int
{
    $pending = login_code_pending();
    if ($pending === null || empty($pending['sent_at'])) {
        return 0;
    }
    return max(0, LOGIN_CODE_RESEND_GAP_SECONDS - (time() - (int) $pending['sent_at']));
}

/**
 * Makes a fresh code, keeps its hash in the session and emails it. Any
 * earlier code stops working the moment a new one is made.
 *
 * @return array{ok: bool, error?: string, wait?: int}
 */
function login_code_send(): array
{
    $pending = login_code_pending();
    $admin = login_code_admin();
    if ($pending === null || $admin === null) {
        return ['ok' => false, 'error' => 'expired'];
    }

    $wait = login_code_resend_wait();
    if ($wait > 0) {
        return ['ok' => false, 'error' => 'wait', 'wait' => $wait];
    }

    $limit = rate_limit_hit('login_code_send', LOGIN_CODE_MAX_SENDS, 900, 'admin:' . (int) $admin['id'], 900);
    if (!$limit['allowed']) {
        return ['ok' => false, 'error' => 'too_many', 'wait' => (int) $limit['retry_after']];
    }

    $code = str_pad((string) random_int(0, 10 ** LOGIN_CODE_LENGTH - 1), LOGIN_CODE_LENGTH, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(16));

    $pending['hash'] = hash_hmac('sha256', $code, $salt);
    $pending['salt'] = $salt;
    $pending['expires'] = time() + LOGIN_CODE_TTL_SECONDS;
    $pending['tries'] = 0;
    $pending['sent_at'] = time();
    $_SESSION['login_code'] = $pending;

    require_once __DIR__ . '/mailer.php';
    $intro = 'Someone signed in to the ' . get_site_name() . ' admin panel with your password from a browser we have not seen before. To finish signing in, enter this code:';
    $result = send_smtp_mail(
        (string) $admin['email'],
        (string) $admin['full_name'],
        'Your ' . get_site_name() . ' sign-in code',
        render_login_code_email_html($admin, $code, $intro),
        render_login_code_email_text($admin, $code, $intro)
    );

    if (!$result['ok']) {
        // A code nobody received must not stay usable.
        $pending['hash'] = '';
        $pending['sent_at'] = 0;
        $_SESSION['login_code'] = $pending;
        log_admin_activity('Sign-in code not sent', 'The email could not be sent: ' . mb_substr((string) ($result['error'] ?? ''), 0, 200), (int) $admin['id'], (string) $admin['full_name']);
        return ['ok' => false, 'error' => 'send_failed'];
    }

    log_admin_activity('Sign-in code sent', 'To ' . masked_email((string) $admin['email']), (int) $admin['id'], (string) $admin['full_name']);
    return ['ok' => true];
}

/**
 * Checks a code typed on the code screen.
 *
 * @return array{ok: bool, error?: string, tries_left?: int}
 */
function login_code_verify(string $submitted): array
{
    $pending = login_code_pending();
    if ($pending === null) {
        return ['ok' => false, 'error' => 'expired'];
    }

    // Across every account and every code, one address only gets so many
    // guesses: a six-digit code is short, and this is what keeps it out of
    // reach of anything automated.
    $limit = rate_limit_hit('login_code_verify', 20, 900, '', 900);
    if (!$limit['allowed']) {
        return ['ok' => false, 'error' => 'too_many', 'wait' => (int) $limit['retry_after']];
    }

    if ($pending['hash'] === '' || time() > (int) $pending['expires']) {
        return ['ok' => false, 'error' => 'code_expired'];
    }

    $submitted = preg_replace('/\D/', '', $submitted);
    $pending['tries'] = (int) $pending['tries'] + 1;
    $_SESSION['login_code'] = $pending;

    if (strlen($submitted) === LOGIN_CODE_LENGTH
        && hash_equals((string) $pending['hash'], hash_hmac('sha256', $submitted, (string) $pending['salt']))) {
        return ['ok' => true];
    }

    $left = LOGIN_CODE_MAX_TRIES - (int) $pending['tries'];
    if ($left <= 0) {
        // Out of guesses: the whole sign-in starts again from the
        // password, which has its own lockout.
        login_code_clear();
        return ['ok' => false, 'error' => 'locked', 'tries_left' => 0];
    }

    return ['ok' => false, 'error' => 'wrong', 'tries_left' => $left];
}

// ---------------------------------------------------------------
// Confirmation codes, for proving the email really arrives before
// something starts depending on it (turning sign-in codes on).
// ---------------------------------------------------------------

/**
 * Emails a one-off code to a signed-in admin for $purpose.
 *
 * @return array{ok: bool, error?: string, wait?: int}
 */
function confirmation_code_send(array $admin, string $purpose, string $intro): array
{
    $email = trim((string) ($admin['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'no_email'];
    }

    $existing = $_SESSION['confirm_code'][$purpose] ?? null;
    if (is_array($existing) && time() - (int) $existing['sent_at'] < LOGIN_CODE_RESEND_GAP_SECONDS) {
        return ['ok' => false, 'error' => 'wait', 'wait' => LOGIN_CODE_RESEND_GAP_SECONDS - (time() - (int) $existing['sent_at'])];
    }

    $limit = rate_limit_hit('confirm_code_send', LOGIN_CODE_MAX_SENDS, 900, 'admin:' . (int) $admin['id'], 900);
    if (!$limit['allowed']) {
        return ['ok' => false, 'error' => 'too_many', 'wait' => (int) $limit['retry_after']];
    }

    $code = str_pad((string) random_int(0, 10 ** LOGIN_CODE_LENGTH - 1), LOGIN_CODE_LENGTH, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(16));
    $_SESSION['confirm_code'][$purpose] = [
        'admin_id' => (int) $admin['id'],
        'hash'     => hash_hmac('sha256', $code, $salt),
        'salt'     => $salt,
        'expires'  => time() + LOGIN_CODE_TTL_SECONDS,
        'tries'    => 0,
        'sent_at'  => time(),
    ];

    require_once __DIR__ . '/mailer.php';
    $result = send_smtp_mail(
        $email,
        (string) $admin['full_name'],
        'Your ' . get_site_name() . ' confirmation code',
        render_login_code_email_html($admin, $code, $intro),
        render_login_code_email_text($admin, $code, $intro)
    );

    if (!$result['ok']) {
        unset($_SESSION['confirm_code'][$purpose]);
        return ['ok' => false, 'error' => 'send_failed', 'detail' => (string) ($result['error'] ?? '')];
    }
    return ['ok' => true];
}

/** True while a confirmation code for $purpose is out and still usable. */
function confirmation_code_outstanding(int $adminId, string $purpose): bool
{
    $pending = $_SESSION['confirm_code'][$purpose] ?? null;
    return is_array($pending)
        && (int) $pending['admin_id'] === $adminId
        && time() <= (int) $pending['expires'];
}

/**
 * Checks a confirmation code. A right code is used up; five wrong ones
 * throw the code away.
 *
 * @return array{ok: bool, error?: string, tries_left?: int}
 */
function confirmation_code_check(int $adminId, string $purpose, string $submitted): array
{
    $pending = $_SESSION['confirm_code'][$purpose] ?? null;
    if (!is_array($pending) || (int) $pending['admin_id'] !== $adminId) {
        return ['ok' => false, 'error' => 'none'];
    }
    if (time() > (int) $pending['expires']) {
        unset($_SESSION['confirm_code'][$purpose]);
        return ['ok' => false, 'error' => 'code_expired'];
    }

    $limit = rate_limit_hit('login_code_verify', 20, 900, '', 900);
    if (!$limit['allowed']) {
        return ['ok' => false, 'error' => 'too_many'];
    }

    $submitted = preg_replace('/\D/', '', $submitted);
    $pending['tries'] = (int) $pending['tries'] + 1;
    $_SESSION['confirm_code'][$purpose] = $pending;

    if (strlen($submitted) === LOGIN_CODE_LENGTH
        && hash_equals((string) $pending['hash'], hash_hmac('sha256', $submitted, (string) $pending['salt']))) {
        unset($_SESSION['confirm_code'][$purpose]);
        return ['ok' => true];
    }

    $left = LOGIN_CODE_MAX_TRIES - (int) $pending['tries'];
    if ($left <= 0) {
        unset($_SESSION['confirm_code'][$purpose]);
        return ['ok' => false, 'error' => 'locked', 'tries_left' => 0];
    }
    return ['ok' => false, 'error' => 'wrong', 'tries_left' => $left];
}

function render_login_code_email_html(array $admin, string $code, string $intro): string
{
    $theme = get_active_palette();
    $primary = h($theme['color_primary']);
    $ink = h($theme['color_ink']);
    $muted = h($theme['color_muted']);
    $bgSoft = h($theme['color_bg_soft']);
    $white = h($theme['color_white']);
    $border = h($theme['color_border']);
    $onPrimary = h(readable_text_on($theme['color_primary']));

    $site = h(get_site_name());
    $name = h((string) $admin['full_name']);
    $minutes = (int) (LOGIN_CODE_TTL_SECONDS / 60);
    $where = h(browser_label_from_user_agent((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $ip = h(client_ip());
    $when = h(date('M j, Y g:i A'));
    $spaced = h(implode(' ', str_split($code, 3)));
    $introHtml = h($intro);

    return <<<HTML
    <div style="font-family:Arial,Helvetica,sans-serif;background:{$bgSoft};padding:24px;">
      <div style="max-width:520px;margin:0 auto;background:{$white};border-radius:8px;overflow:hidden;border:1px solid {$border};">
        <div style="background:{$primary};padding:18px 24px;">
          <span style="color:{$onPrimary};font-size:20px;font-weight:bold;">{$site}</span>
        </div>
        <div style="padding:24px;color:{$ink};font-size:15px;line-height:1.5;">
          <p style="margin:0 0 12px;">Hi {$name},</p>
          <p style="margin:0 0 18px;">{$introHtml}</p>
          <div style="text-align:center;margin:0 0 18px;">
            <span style="display:inline-block;font-size:32px;font-weight:bold;letter-spacing:6px;color:{$ink};background:{$bgSoft};border:1px solid {$border};border-radius:8px;padding:12px 22px;">{$spaced}</span>
          </div>
          <p style="margin:0 0 6px;color:{$muted};font-size:13px;">The code works once and expires in {$minutes} minutes.</p>
          <p style="margin:0 0 18px;color:{$muted};font-size:13px;">Requested from {$where}, address {$ip}, on {$when}.</p>
          <p style="margin:0;font-size:13px;color:{$ink};"><strong>Was this not you?</strong> Do not share the code. Someone knows your password: sign in and change it straight away.</p>
        </div>
      </div>
    </div>
    HTML;
}

function render_login_code_email_text(array $admin, string $code, string $intro): string
{
    $minutes = (int) (LOGIN_CODE_TTL_SECONDS / 60);
    return 'Hi ' . $admin['full_name'] . ",\n\n"
        . $intro . "\n\n"
        . "Your code: {$code}\n\n"
        . "The code works once and expires in {$minutes} minutes.\n"
        . 'Requested from ' . browser_label_from_user_agent((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) . ', address ' . client_ip() . ', on ' . date('M j, Y g:i A') . ".\n\n"
        . "Was this not you? Do not share the code. Someone knows your password: sign in and change it straight away.\n";
}
