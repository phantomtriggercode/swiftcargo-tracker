<?php
/**
 * Site security: request screening, rate limiting, login protection,
 * CSRF tokens and the password policy.
 *
 * Everything here is self-hosted. There is no external service to sign up
 * for, no API key to keep secret and no third party that gets to see your
 * visitors. The trade-off is that the counters live in the database, so
 * every function in this file fails OPEN on a database error: a site with
 * a broken database should show an error page, not lock every customer and
 * every member of staff out of a site that would otherwise work.
 *
 * Loaded by includes/functions.php, which every page loads in turn, so the
 * request screen at the bottom of this file runs on every request.
 *
 * Requires config/db.php to already be loaded (for db_optional()).
 */

// ---------------------------------------------------------------
// Limits. All of these are deliberately tight. Real people never come
// close to them; scripted attacks hit them within seconds.
// ---------------------------------------------------------------

/** Failed logins are counted over this many minutes. */
const LOGIN_RATE_WINDOW_MINUTES = 15;
/** Failed logins allowed from one network before it is refused. */
const LOGIN_MAX_ATTEMPTS_PER_IP = 5;
/** Failed logins allowed against one username/email before it is refused. */
const LOGIN_MAX_ATTEMPTS_PER_IDENTIFIER = 4;
/** Sustained guessing: this many failures in an hour earns a hard block. */
const LOGIN_HARD_BLOCK_THRESHOLD = 12;
/** How long that hard block lasts, in seconds. */
const LOGIN_HARD_BLOCK_SECONDS = 3600;

/**
 * The session cookie's name. Deliberately not PHP's default, PHPSESSID,
 * which advertises what the site is written in before anyone has looked
 * at a single page.
 */
const SESSION_COOKIE_NAME = 'sid';

/** Minimum length for any admin password set anywhere in the panel. */
const PASSWORD_MIN_LENGTH = 12;

/**
 * An admin session left idle this long is signed out on its next request.
 * Fifteen minutes is short on purpose: this panel can see every customer's
 * name, address, phone number and email, and holds the mailbox password
 * used to send alerts. It resets on every admin page load, so it is
 * fifteen *idle* minutes, not fifteen minutes of work.
 */
const ADMIN_IDLE_TIMEOUT_SECONDS = 900;

/**
 * However busy the session, it ends after this long and has to be signed
 * in to again. An idle timeout alone never expires a session that someone
 * (or something) keeps poking, so a stolen cookie could be kept alive
 * indefinitely. This is the ceiling on that.
 */
const ADMIN_ABSOLUTE_TIMEOUT_SECONDS = 28800;

/**
 * How often a signed-in session is given a fresh id. Rotating it limits
 * how long any one captured id is worth anything.
 */
const ADMIN_SESSION_ROTATE_SECONDS = 900;

/**
 * Site-wide ceiling: page views allowed from one address per window.
 * Someone reading the site generates a few dozen; a scraper or a
 * vulnerability scanner generates thousands.
 */
const REQUEST_RATE_MAX_HITS = 240;
const REQUEST_RATE_WINDOW_SECONDS = 300;
const REQUEST_RATE_BLOCK_SECONDS = 900;

/**
 * The visitor's address. Deliberately REMOTE_ADDR only.
 *
 * X-Forwarded-For is attacker-controlled unless you know for certain that
 * a trusted proxy rewrote it, and trusting it blindly would let anyone
 * defeat every limit in this file by sending a different fake address on
 * each request. On shared hosting REMOTE_ADDR is the real client address.
 */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * The address reduced to its network, used when binding a session to the
 * network it was created on. A mobile connection changes its exact address
 * far more often than it changes network, so comparing whole addresses
 * would sign people out constantly; comparing networks catches a stolen
 * cookie replayed from somewhere else without that false alarm.
 */
function client_network(): string
{
    $ip = client_ip();
    if (str_contains($ip, ':')) {
        // IPv6: first four groups, the /64 a device is normally given.
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 4));
    }
    $parts = explode('.', $ip);
    return implode('.', array_slice($parts, 0, 3));
}

// ---------------------------------------------------------------
// Rate limiting
//
// One row per (bucket, actor, window) with a counter, rather than one row
// per request. That keeps the table small and the write cheap even while
// an attack is in progress, which is exactly when the table is written to
// most.
// ---------------------------------------------------------------

/**
 * Counts one hit against a bucket and reports whether it is allowed.
 *
 * Returns ['allowed' => bool, 'retry_after' => seconds]. Fails open
 * (allowed) whenever the database cannot be reached or the table has not
 * been imported yet, so a site that has not run the update keeps working.
 *
 * @param string $bucket        Short name for what is being limited, e.g. 'login'.
 * @param int    $maxHits       Hits allowed inside the window.
 * @param int    $windowSeconds Length of the window.
 * @param string $actor         Who is being limited. Defaults to their address.
 * @param int    $blockSeconds  How long to refuse them for once they go over.
 */
function rate_limit_hit(string $bucket, int $maxHits, int $windowSeconds, string $actor = '', int $blockSeconds = 0): array
{
    $allowed = ['allowed' => true, 'retry_after' => 0];

    $pdo = db_optional();
    if ($pdo === null) {
        return $allowed;
    }

    $actor = $actor === '' ? client_ip() : $actor;
    // Long actors (an email address plus an address, say) are hashed so the
    // key never overflows its column.
    if (strlen($actor) > 180) {
        $actor = hash('sha256', $actor);
    }

    $now = time();
    $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
    $blockSeconds = $blockSeconds > 0 ? $blockSeconds : $windowSeconds;

    try {
        // Still serving an earlier block for this bucket?
        $stmt = $pdo->prepare('SELECT MAX(blocked_until) FROM rate_limits WHERE bucket = ? AND actor = ?');
        $stmt->execute([$bucket, $actor]);
        $blockedUntil = (int) $stmt->fetchColumn();
        if ($blockedUntil > $now) {
            return ['allowed' => false, 'retry_after' => $blockedUntil - $now];
        }

        $stmt = $pdo->prepare('
            INSERT INTO rate_limits (bucket, actor, window_start, hits)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE hits = hits + 1
        ');
        $stmt->execute([$bucket, $actor, $windowStart]);

        $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND actor = ? AND window_start = ?');
        $stmt->execute([$bucket, $actor, $windowStart]);
        $hits = (int) $stmt->fetchColumn();

        // Housekeeping. No cron on shared hosting, so it happens inline on
        // a small slice of requests instead of on a schedule.
        if (random_int(1, 200) === 1) {
            $pdo->exec('DELETE FROM rate_limits WHERE updated_at < (NOW() - INTERVAL 2 DAY)');
        }

        if ($hits > $maxHits) {
            $until = $now + $blockSeconds;
            $stmt = $pdo->prepare('UPDATE rate_limits SET blocked_until = ? WHERE bucket = ? AND actor = ? AND window_start = ?');
            $stmt->execute([$until, $bucket, $actor, $windowStart]);
            return ['allowed' => false, 'retry_after' => $blockSeconds];
        }
    } catch (PDOException $e) {
        return $allowed;
    }

    return $allowed;
}

/**
 * Counts a hit and, if the caller has gone over, stops the request there
 * with a 429 and a Retry-After header. Used on endpoints where there is
 * no form to send the visitor back to.
 */
function rate_limit_enforce(string $bucket, int $maxHits, int $windowSeconds, string $actor = '', int $blockSeconds = 0): void
{
    $result = rate_limit_hit($bucket, $maxHits, $windowSeconds, $actor, $blockSeconds);
    if ($result['allowed']) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(429);
        header('Retry-After: ' . $result['retry_after']);
        header('Cache-Control: no-store');
    }

    $wantsJson = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/')
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    if ($wantsJson) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['ok' => false, 'error' => 'Too many requests. Please slow down and try again shortly.']);
    } else {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Too many requests</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;margin:0;'
            . 'min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f5f7;color:#111827}'
            . 'div{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.4rem;margin:0 0 .75rem}'
            . 'p{margin:0;color:#4b5563;line-height:1.6}</style></head><body><div>'
            . '<h1>Too many requests</h1>'
            . '<p>You have made a lot of requests in a short space of time, so this one was not processed. '
            . 'Please wait a few minutes and try again.</p>'
            . '</div></body></html>';
    }
    exit;
}

/**
 * Seconds remaining on an existing block, or 0 if there is none.
 *
 * Read-only on purpose: checking whether someone is blocked must not
 * count as another hit against them, or a blocked address would extend
 * its own block simply by being refused.
 */
function rate_limit_blocked_for(string $bucket, string $actor = ''): int
{
    $pdo = db_optional();
    if ($pdo === null) {
        return 0;
    }
    $actor = $actor === '' ? client_ip() : $actor;
    if (strlen($actor) > 180) {
        $actor = hash('sha256', $actor);
    }
    try {
        $stmt = $pdo->prepare('SELECT MAX(blocked_until) FROM rate_limits WHERE bucket = ? AND actor = ?');
        $stmt->execute([$bucket, $actor]);
        $until = (int) $stmt->fetchColumn();
        return $until > time() ? $until - time() : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Clears a bucket for one actor, e.g. after a successful sign-in, so a
 * couple of mistyped passwords do not linger against someone who then got
 * it right.
 */
function rate_limit_clear(string $bucket, string $actor = ''): void
{
    $pdo = db_optional();
    if ($pdo === null) {
        return;
    }
    $actor = $actor === '' ? client_ip() : $actor;
    if (strlen($actor) > 180) {
        $actor = hash('sha256', $actor);
    }
    try {
        $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE bucket = ? AND actor = ?');
        $stmt->execute([$bucket, $actor]);
    } catch (PDOException $e) {
        // A failed cleanup only means the limit expires on its own a few
        // minutes later.
    }
}

// ---------------------------------------------------------------
// Login protection
// ---------------------------------------------------------------

/**
 * Returns a message if this address or this account has had too many
 * recent failures, or null if the sign-in should be allowed to proceed.
 *
 * Fails open if the login_attempts table does not exist yet: the
 * alternative (throwing) would take the login page down for everyone,
 * which is worse than briefly running without the counter.
 */
function login_rate_limit_check(string $ip, string $identifier): ?string
{
    // The hard block comes first: once an address has been hammering the
    // form it is refused outright, regardless of which account it is
    // currently guessing at.
    $blocked = rate_limit_hit('login_block', LOGIN_HARD_BLOCK_THRESHOLD, LOGIN_HARD_BLOCK_SECONDS, 'ip:' . $ip, LOGIN_HARD_BLOCK_SECONDS);
    if (!$blocked['allowed']) {
        return 'Too many failed sign-in attempts from your network. Access to this page is paused for '
            . max(1, (int) ceil($blocked['retry_after'] / 60)) . ' minutes.';
    }

    try {
        $db = db();
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = ? AND succeeded = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)
        ');
        $stmt->execute([$ip, LOGIN_RATE_WINDOW_MINUTES]);
        if ((int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS_PER_IP) {
            return 'Too many login attempts from your network. Please wait ' . LOGIN_RATE_WINDOW_MINUTES . ' minutes and try again.';
        }

        if ($identifier !== '') {
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM login_attempts
                WHERE identifier = ? AND succeeded = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)
            ');
            $stmt->execute([$identifier, LOGIN_RATE_WINDOW_MINUTES]);
            if ((int) $stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS_PER_IDENTIFIER) {
                return 'Too many failed attempts for this account. Please wait ' . LOGIN_RATE_WINDOW_MINUTES . ' minutes and try again.';
            }
        }
    } catch (PDOException $e) {
        return null;
    }

    return null;
}

function record_login_attempt(string $ip, string $identifier, bool $succeeded): void
{
    try {
        $stmt = db()->prepare('INSERT INTO login_attempts (ip_address, identifier, succeeded) VALUES (?, ?, ?)');
        $stmt->execute([$ip, $identifier, $succeeded ? 1 : 0]);

        if ($succeeded) {
            // A correct sign-in clears this account's and this address's
            // recent failure count.
            $clear = db()->prepare('DELETE FROM login_attempts WHERE succeeded = 0 AND (ip_address = ? OR identifier = ?)');
            $clear->execute([$ip, $identifier]);
            rate_limit_clear('login_block', 'ip:' . $ip);
        }

        // Opportunistic cleanup of old rows, same reasoning as above.
        if (random_int(1, 50) === 1) {
            db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        }
    } catch (PDOException $e) {
        // A missing table should never break the login page itself, only
        // skip the tracking.
    }
}

/**
 * Pauses briefly after a failed sign-in.
 *
 * Password guessing is a numbers game: the attacker needs thousands of
 * tries. A fraction of a second per attempt costs someone who mistyped
 * their own password nothing, and costs an automated attack more than the
 * rate limit alone does. Randomised so the delay itself cannot be timed to
 * tell a wrong password apart from an unknown username.
 */
function login_failure_delay(): void
{
    usleep(random_int(250000, 600000));
}

/**
 * Checks a new password against the site's policy. Returns an error
 * message, or null if the password is acceptable.
 *
 * Length does most of the work here. The character classes are a floor,
 * not the point: a long passphrase beats a short scramble every time,
 * which is why the minimum is 12 rather than 8.
 */
function password_policy_error(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen($password) > 200) {
        return 'Password must be 200 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and at least one number.';
    }

    // The handful of passwords every automated attack tries first. Length
    // alone would let some of these through.
    $obvious = [
        'password1234', 'passw0rd1234', '123456789012', 'qwertyuiop12',
        'administrator', 'letmein12345', 'welcome12345', 'changeme123!',
        'p@ssw0rd1234', 'iloveyou1234', 'trustno112345',
    ];
    if (in_array(strtolower($password), $obvious, true)) {
        return 'That password is far too common. Please choose something else.';
    }

    return null;
}

// ---------------------------------------------------------------
// Login form defences: a honeypot field and a math challenge, both of
// which cost a person nothing and stop the overwhelming majority of
// automated form submissions.
// ---------------------------------------------------------------

/**
 * Generates a fresh, trivially-easy-for-a-human math challenge and stores
 * the answer in the session. Call on every GET to the login page and
 * again after every POST (success or fail) so a challenge is never reused.
 */
function new_captcha_challenge(): array
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['login_captcha_answer'] = $a + $b;
    return ['a' => $a, 'b' => $b];
}

function verify_captcha(string $submitted): bool
{
    $expected = $_SESSION['login_captcha_answer'] ?? null;
    unset($_SESSION['login_captcha_answer']); // single use, always re-issued after this call
    $submitted = trim($submitted);
    if ($expected === null || $submitted === '' || !ctype_digit($submitted)) {
        return false;
    }
    return (int) $submitted === (int) $expected;
}

/** True if the honeypot field was filled in, real users never see or fill it. */
function honeypot_tripped(string $value): bool
{
    return trim($value) !== '';
}

// ---------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify(string $submitted): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && hash_equals($expected, $submitted);
}

// ---------------------------------------------------------------
// Sessions
//
// These live here rather than in functions.php because the request screen
// below needs a started session before it can tell a signed-in member of
// staff from a stranger, and this file is loaded first.
// ---------------------------------------------------------------

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

/**
 * Stricter cousin of is_https(), used only to decide whether the session
 * cookie gets the `Secure` flag. Getting this wrong doesn't degrade the
 * site. It locks people out of it, so it errs on the side of working.
 *
 * The `Secure` flag tells the browser "only ever send this cookie over
 * HTTPS". If we set it on a response the browser received over plain
 * HTTP, the browser doesn't just ignore the flag. It refuses to store
 * the cookie at all. No cookie means no session, no session means the
 * CSRF token from the login form has nothing to match against, and the
 * user is told "your session expired" no matter how correct their
 * password is.
 *
 * That is not hypothetical: several hosts (Hostinger and Cloudflare among
 * them) send `X-Forwarded-Proto: https` on *every* request once SSL is
 * enabled, including ones the visitor genuinely made over http://. Trust
 * that header alone and the login page becomes unusable for anyone who
 * reaches the site by typing the bare domain: which is most people on a
 * phone, while desktop users click an https:// bookmark and never notice.
 *
 * So the forwarded header is only believed when the port agrees with it.
 * When the two disagree, the cookie simply goes out without `Secure`: it
 * still works, and repeat visitors are protected anyway by the
 * Strict-Transport-Security header config/db.php sends over HTTPS.
 */
function is_https_for_cookie(): bool
{
    // TLS terminated by the web server itself: unambiguous, always trust.
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded !== 'https') {
        return false;
    }

    // Behind a TLS-terminating proxy. Believe it only if the port backs it up.
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    return $port === 443 || $port === 0;
}

/**
 * Starts the PHP session with explicit, hardened settings instead of
 * PHP's bare defaults.
 *
 * SameSite=Lax + HttpOnly + Secure-when-HTTPS is the part that matters for
 * cookie theft. Safari/iOS is stricter about cookie attributes than most
 * desktop browsers, and a session cookie mobile Safari will not accept
 * means a sign-in can succeed server-side and still bounce straight back
 * to the login page with no error, because the browser never kept the
 * session.
 *
 * The three ini settings on top of that close session fixation: strict
 * mode makes PHP refuse a session id it did not issue itself, so an
 * attacker cannot plant a known id in someone's browser and then walk in
 * on it once they sign in. Cookies-only means a session id in a URL is
 * ignored, which also keeps ids out of Referer headers and server logs.
 */
function ensure_session_started(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (!headers_sent()) {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');
        // The server-side session file is discarded on roughly the same
        // schedule the application signs an idle admin out, so an
        // abandoned session does not sit on disk for PHP's default 24
        // minutes past its usefulness.
        @ini_set('session.gc_maxlifetime', (string) (ADMIN_IDLE_TIMEOUT_SECONDS + 300));

        // A neutral cookie name. The default, PHPSESSID, advertises what
        // the site is written in before anyone has even looked at a page.
        session_name(SESSION_COOKIE_NAME);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            // Deliberately is_https_for_cookie(), not is_https(), see the
            // long note on that function. Marking this Secure on a request
            // the browser made over plain HTTP makes the browser discard
            // the cookie entirely, which presents as "your session expired"
            // on a perfectly correct sign-in.
            'secure' => is_https_for_cookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

// ---------------------------------------------------------------
// Request screening
//
// This runs before any page does its own work. It is a coarse filter, not
// a substitute for the prepared statements and output escaping used
// everywhere else: those are what actually make injection impossible. What
// this adds is that an automated scanner gets a 403 on its first probe
// instead of a slow tour of every form on the site.
// ---------------------------------------------------------------

/** True when the current request is for a page under /admin/. */
function is_admin_area(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    return str_contains($script, '/admin/');
}

/**
 * Ends the request with a 403 and no explanation.
 *
 * Deliberately says nothing about what was wrong. Telling a scanner which
 * of its probes was recognised is free tuning advice.
 */
function security_reject(string $reason): void
{
    $pdo = db_optional();
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare('
                INSERT INTO security_events (ip_address, reason, request_path, user_agent)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                client_ip(),
                substr($reason, 0, 60),
                substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (PDOException $e) {
            // Logging is a nicety; refusing the request is the job.
        }
    }

    // Repeat offenders stop getting even a 403 quickly: a handful of
    // rejected probes buys a block on everything for an hour.
    rate_limit_hit('probe', 5, 600, '', 3600);

    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head>'
        . '<body><h1>Forbidden</h1><p>This request was not accepted.</p></body></html>';
    exit;
}

/**
 * Patterns that have no innocent meaning in a URL or a public form field
 * on this site. Kept short on purpose: a long list of clever patterns
 * catches legitimate text and blocks real customers, which is a worse
 * outcome than letting a probe through to a prepared statement that was
 * always going to reject it anyway.
 */
function security_suspicious_patterns(): array
{
    return [
        '/\bunion\b[\s\/*]+\bselect\b/i',
        '/\binformation_schema\b/i',
        '/\bbenchmark\s*\(/i',
        '/\bsleep\s*\(\s*\d/i',
        '/\bload_file\s*\(/i',
        '/\binto\s+(out|dump)file\b/i',
        '/\bxp_cmdshell\b/i',
        '/\bselect\b.{0,40}\bfrom\b.{0,40}\b(mysql|pg_|sys)\./i',
        '/\bor\b\s+["\']?\d+["\']?\s*=\s*["\']?\d+/i',
        '/(%27|\')\s*(or|and)\s*(%27|\')?\d/i',
        '/<\s*script\b/i',
        '/\bon(error|load|click)\s*=\s*["\']/i',
        '/javascript\s*:/i',
        '/(\.\.[\/\\\\]){2,}/',
        '/\/etc\/(passwd|shadow)\b/i',
        '/\b(php|data|expect|file|zip|phar):\/\//i',
        '/\{\{.*\}\}/',
    ];
}

/**
 * Walks a submitted array (which may be nested) looking for anything that
 * matches the patterns above.
 */
function security_scan_input($value, int $depth = 0): bool
{
    if ($depth > 6) {
        return true; // absurdly nested input is itself the attack
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            if (is_string($key) && security_scan_input($key, $depth + 1)) {
                return true;
            }
            if (security_scan_input($item, $depth + 1)) {
                return true;
            }
        }
        return false;
    }
    if (!is_string($value) || $value === '') {
        return false;
    }

    // Decoded as well as raw, so a payload hidden behind percent-encoding
    // is compared in the form the application would actually see.
    foreach ([$value, rawurldecode($value)] as $candidate) {
        foreach (security_suspicious_patterns() as $pattern) {
            if (preg_match($pattern, $candidate)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * The screen itself, run once per request from the bottom of this file.
 */
function security_screen_request(): void
{
    // Never screen the command line: this same code is loaded by any
    // maintenance script run over SSH, where none of this applies.
    if (PHP_SAPI === 'cli') {
        return;
    }

    // A session is only started when there is already one to resume, or
    // when this is the admin panel. Starting one unconditionally would set
    // a cookie on every anonymous visitor who is only reading a page, for
    // no benefit: what the ceiling below needs is to recognise signed-in
    // staff, and staff always arrive with the cookie already.
    //
    // Sending a made-up cookie to force this branch gains nothing. PHP is
    // in strict mode (see ensure_session_started), so an id it did not
    // issue is discarded rather than adopted, and the empty session that
    // results is treated exactly like a stranger's.
    if (isset($_COOKIE[SESSION_COOKIE_NAME]) || is_admin_area()) {
        ensure_session_started();
    }

    // An address that has already had a handful of probes refused is shut
    // out of the whole site for an hour, not just off the page it was
    // probing. This is checked first and answers without logging, so a
    // blocked scanner cannot fill the security log by continuing to knock.
    if (empty($_SESSION['admin_id'])) {
        $probeBlock = rate_limit_blocked_for('probe');
        if ($probeBlock > 0) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Retry-After: ' . $probeBlock);
                header('Cache-Control: no-store');
            }
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head>'
                . '<body><h1>Forbidden</h1><p>This request was not accepted.</p></body></html>';
            exit;
        }
    }

    if (!headers_sent()) {
        // PHP announces its own version in this header by default, which
        // tells anyone scanning exactly which published vulnerabilities to
        // try. It is of no use to a browser.
        header_remove('X-Powered-By');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
        if (!headers_sent()) {
            http_response_code(405);
            header('Allow: GET, POST, HEAD');
        }
        echo 'Method not allowed.';
        exit;
    }

    // Tools that announce themselves. Anything that genuinely is one of
    // these is not a customer.
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($agent !== '' && preg_match('/\b(sqlmap|nikto|havij|acunetix|nessus|openvas|wpscan|dirbuster|gobuster|masscan|zgrab|arachni|w3af)\b/i', $agent)) {
        security_reject('scanner user agent');
    }

    // A request with no Host header is not a browser.
    if (($_SERVER['HTTP_HOST'] ?? '') === '') {
        security_reject('missing host header');
    }

    // The site-wide ceiling. Signed-in staff are exempt: bulk data entry
    // legitimately looks like a lot of requests, and they have already
    // proved who they are.
    $staffSignedIn = !empty($_SESSION['admin_id']);
    if (!$staffSignedIn) {
        rate_limit_enforce('page', REQUEST_RATE_MAX_HITS, REQUEST_RATE_WINDOW_SECONDS, '', REQUEST_RATE_BLOCK_SECONDS);
    }

    // The query string is screened everywhere, including the admin panel:
    // nothing on this site puts prose in a URL, so a match there is always
    // hostile.
    if (security_scan_input($_GET)) {
        security_reject('suspicious query string');
    }

    // Submitted form bodies are screened on the public side only. Staff
    // writing site copy, a status message or a shipment note are entitled
    // to type whatever they like, including the words that look like an
    // attack when a stranger sends them.
    if ($method === 'POST' && !is_admin_area() && security_scan_input($_POST)) {
        security_reject('suspicious form input');
    }
}

security_screen_request();
