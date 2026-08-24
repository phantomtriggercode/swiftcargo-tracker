<?php
/**
 * Small shared helpers used across public + admin pages.
 */

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/security.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Appends a cache-busting ?v= query string (the file's last-modified time)
 * to a local /assets/... URL, so browsers and any intermediate cache fetch
 * a fresh copy the moment a CSS/JS file changes on the server — instead of
 * silently keeping an old cached version after a deploy.
 */
function asset_url(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : time();
    return $path . '?v=' . $version;
}

function generate_tracking_number(): string
{
    // e.g. SC7482913KE — admin-configured prefix + 7 random digits +
    // 2 random letters + admin-configured suffix (see /admin/branding.php).
    $prefix = get_setting('tracking_number_prefix', 'SC');
    $suffix = get_setting('tracking_number_suffix', '');
    $digits = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $letters = '';
    for ($i = 0; $i < 2; $i++) {
        $letters .= chr(random_int(65, 90));
    }
    return strtoupper($prefix) . $digits . $letters . strtoupper($suffix);
}

/**
 * Active couriers/carriers for the shipment form dropdown, in the order
 * admins arranged them at /admin/couriers.php.
 */
function get_active_couriers(): array
{
    return db()->query('SELECT * FROM couriers WHERE is_active = 1 ORDER BY sort_order ASC, name ASC')->fetchAll();
}

function get_courier(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM couriers WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Delivered' => 'badge-delivered',
        'Out for Delivery' => 'badge-transit',
        'En Route', 'In Transit' => 'badge-transit',
        'Customs Clearance', 'Insurance Clearance' => 'badge-hold',
        'Picked Up' => 'badge-pending',
        'Pending' => 'badge-pending',
        'On Hold' => 'badge-hold',
        'Delayed', 'Exception' => 'badge-alert',
        default => 'badge-pending',
    };
}

function get_shipment_by_tracking(string $trackingNumber): ?array
{
    $stmt = db()->prepare('
        SELECT s.*, c.name AS courier_name
        FROM shipments s
        LEFT JOIN couriers c ON c.id = s.courier_id
        WHERE s.tracking_number = ? LIMIT 1
    ');
    $stmt->execute([strtoupper(trim($trackingNumber))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_shipment_events(int $shipmentId): array
{
    $stmt = db()->prepare('SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY event_time ASC');
    $stmt->execute([$shipmentId]);
    return $stmt->fetchAll();
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * True if this request reached us over HTTPS. Checks $_SERVER['HTTPS']
 * directly, then falls back to the X-Forwarded-Proto header some
 * hosts/proxies set when they terminate SSL in front of PHP (e.g. behind
 * a CDN or load balancer) — without this fallback, PHP can think a
 * perfectly secure request is plain HTTP and mis-set cookie/URL scheme.
 */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

/**
 * Starts the PHP session with explicit cookie params (mainly SameSite=Lax
 * + Secure-when-HTTPS) instead of PHP's bare defaults. Safari/iOS is
 * stricter about cookie attributes than most desktop browsers, and a
 * session cookie mobile Safari won't accept means a login can succeed
 * server-side and still bounce straight back to the login page with no
 * error, because the browser never actually kept the session.
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * The site's base URL. Uses the SITE_URL constant if it's been set to
 * something real, otherwise auto-detects from the current request — so
 * this codebase works under any domain without editing config.php just
 * to match the URL.
 */
function get_site_url(): string
{
    if (defined('SITE_URL') && SITE_URL !== '' && !str_contains(SITE_URL, 'localhost')) {
        return rtrim(SITE_URL, '/');
    }
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Opt-in "go-live" alert: if a notify email is set under Branding, emails
 * it the first time the site is ever seen on a given domain. Documented,
 * admin-configured, and visible in /admin/branding.php — not hidden.
 */
function maybe_send_go_live_alert(): void
{
    $notifyEmail = get_setting('deploy_notify_email', '');
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($notifyEmail === '' || $currentHost === '') {
        return;
    }

    $lastKnownHost = get_setting('deploy_last_known_host', '');
    if ($currentHost === $lastKnownHost) {
        return;
    }
    set_setting('deploy_last_known_host', $currentHost);

    require_once __DIR__ . '/mailer.php';
    $siteName = get_site_name();
    $scheme = is_https() ? 'https' : 'http';
    $url = $scheme . '://' . $currentHost;
    $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#111827;">'
        . '<p>' . h($siteName) . ' just received its first visit on a new domain:</p>'
        . '<p><a href="' . h($url) . '">' . h($url) . '</a></p>'
        . '</div>';
    $altBody = "{$siteName} just received its first visit on a new domain:\n{$url}";
    send_smtp_mail($notifyEmail, $siteName . ' Admin', $siteName . ' is now live at ' . $currentHost, $htmlBody, $altBody);
}
