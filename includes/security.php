<?php
/**
 * Self-hosted login protection: no external service, no API key, nothing
 * for the site owner to sign up for. Combines a honeypot field, a
 * server-generated math challenge, and an IP+username rate limit with a
 * temporary lockout — this is what stands in for "reCAPTCHA" on the admin
 * login form. See admin/login.php for how these are wired together.
 *
 * Requires config/db.php and includes/functions.php (for ensure_session_started)
 * to already be loaded.
 */

const LOGIN_RATE_WINDOW_MINUTES = 15;
const LOGIN_MAX_ATTEMPTS_PER_IP = 10;
const LOGIN_MAX_ATTEMPTS_PER_IDENTIFIER = 6;

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Returns a lockout message if this IP or identifier has too many recent
 * failed attempts, or null if login should be allowed to proceed.
 */
function login_rate_limit_check(string $ip, string $identifier): ?string
{
    // Fails open (no rate limit applied, login proceeds) if the
    // login_attempts table doesn't exist yet — e.g. this code deployed
    // before migration 009 was imported. The alternative (throwing) would
    // take the entire login page down for every admin, which is worse
    // than briefly running without rate limiting.
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
            // A correct login clears this account/IP's recent failure count,
            // so a mistyped password or two doesn't linger against a
            // legitimate user after they get it right.
            $clear = db()->prepare('DELETE FROM login_attempts WHERE succeeded = 0 AND (ip_address = ? OR identifier = ?)');
            $clear->execute([$ip, $identifier]);
        }

        // Opportunistic cleanup of old rows — no cron available on shared
        // hosting, so do it inline on a small fraction of requests instead.
        if (random_int(1, 50) === 1) {
            db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
        }
    } catch (PDOException $e) {
        // Same rationale as above — a missing table here should never
        // break the login page itself, only skip the tracking.
    }
}

/**
 * Generates a fresh, trivially-easy-for-a-human math challenge and
 * stores the answer in the session. Call on every GET to the login page
 * and again after every POST (success or fail) so a challenge is never
 * reused.
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
    unset($_SESSION['login_captcha_answer']); // single use — always re-issued after this call
    $submitted = trim($submitted);
    if ($expected === null || $submitted === '' || !ctype_digit($submitted)) {
        return false;
    }
    return (int) $submitted === (int) $expected;
}

/** True if the honeypot field was filled in — real users never see or fill it. */
function honeypot_tripped(string $value): bool
{
    return trim($value) !== '';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(string $submitted): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';
    return $expected !== '' && hash_equals($expected, $submitted);
}
