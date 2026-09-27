<?php
/**
 * Shared PDO/MySQL connection. Included by every entry-point script.
 */

// Security headers, sent on every request site-wide. This runs before
// includes/functions.php is loaded (this file is always required first),
// so HTTPS detection is duplicated here in miniature rather than reusing
// is_https() from there.
if (!headers_sent()) {
    $__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    if ($__isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    die(
        'Configuration missing. Copy config/config.sample.php to config/config.php ' .
        'and fill in your database + SMTP details.'
    );
}
require_once $configFile;

/**
 * The shared connection, or null if the database cannot be reached.
 *
 * Used by the Content-Security-Policy block below, which must not kill the
 * request just because the database is down, and by db(), which does.
 */
function db_optional(): ?PDO
{
    static $pdo = null;
    static $tried = false;

    if (!$tried) {
        $tried = true;
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            $pdo = null;
        }
    }

    return $pdo;
}

function db(): PDO
{
    $pdo = db_optional();

    if ($pdo === null) {
        http_response_code(500);
        die('Database connection failed. Check config/config.php credentials.');
    }

    return $pdo;
}

// ------------------------------------------------------------------
// Content-Security-Policy.
//
// Sent after config.php is loaded (and not at the top with the headers
// above) because the policy depends on one saved setting: whether the live
// chat widget is switched on. A CSP that forbids the chat host makes the
// browser block the widget *silently*: no error anywhere, the chat bubble
// simply never appears, so the policy and the widget have to agree.
//
// Nothing has been printed by this point (this file is required first and
// outputs nothing), so it is still safe to send headers here.
// ------------------------------------------------------------------
if (!headers_sent()) {
    // The chat widget only ever renders in includes/footer.php, which is
    // the public site's footer, the admin panel uses its own. So admin
    // pages keep the tightest possible policy no matter what. If this
    // path check ever misfires the result is a slightly looser policy on
    // an admin page, never a blocked widget on a public one.
    $__script = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $__isAdminArea = str_contains($__script, '/admin/');

    $__liveChatOn = false;
    if (!$__isAdminArea) {
        // Read directly rather than through get_setting(), which lives in
        // includes/settings.php and is not loaded yet. Fails open to
        // "chat off, tight policy" on any database problem, the same way
        // get_setting() and get_active_palette() do, so a missing settings
        // table can never blank the page.
        $__pdo = db_optional();
        if ($__pdo !== null) {
            try {
                $__stmt = $__pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'live_chat_enabled'");
                $__stmt->execute();
                $__liveChatOn = $__stmt->fetchColumn() === '1';
            } catch (PDOException $e) {
                $__liveChatOn = false;
            }
        }
    }

    // 'unsafe-inline' is required for script-src/style-src because this
    // codebase uses inline <script> blocks (menu toggles, admin widgets)
    // and inline style="" attributes throughout, plus the palette system's
    // injected <style> tag, a stricter policy would break the site. Even
    // with that, this still blocks loading scripts/styles/frames from any
    // origin other than this one.
    //
    // No external script/style host is listed by default: Leaflet (the map
    // library) is served from assets/vendor/leaflet/ rather than a CDN, so
    // the map keeps working on networks that block third-party hosts.
    //
    // img-src always allows the OpenStreetMap tile servers, because map
    // tiles are images fetched from them at runtime, that's the one
    // outside host the map still talks to. If tiles are ever unreachable
    // the map itself still loads, pans and zooms, and shows the route and
    // markers over a plain background (see assets/js/map.js).
    $scriptSrc  = ["'self'", "'unsafe-inline'"];
    $styleSrc   = ["'self'", "'unsafe-inline'"];
    $imgSrc     = ["'self'", 'data:', 'https://*.tile.openstreetmap.org', 'https://tile.openstreetmap.org'];
    $fontSrc    = ["'self'", 'data:'];
    $connectSrc = ["'self'"];
    $frameSrc   = ["'self'"];
    $mediaSrc   = ["'self'"];

    if ($__liveChatOn) {
        // Tawk.to's widget: the loader script and its chat iframe come from
        // embed.tawk.to, live messages ride a websocket on *.tawk.to,
        // agent avatars and shared files come from the same family of
        // hosts, and the "new message" chime is an audio file. Every one of
        // these is scoped to tawk.to subdomains, switching chat off in the
        // admin panel removes all of them again.
        $tawk = 'https://*.tawk.to';
        $scriptSrc[]  = $tawk;
        $styleSrc[]   = $tawk;
        $imgSrc[]     = $tawk;
        $imgSrc[]     = 'https://tawk.link';
        $fontSrc[]    = $tawk;
        $connectSrc[] = $tawk;
        $connectSrc[] = 'wss://*.tawk.to';
        $frameSrc[]   = $tawk;
        $mediaSrc[]   = $tawk;
    }

    header(
        "Content-Security-Policy: default-src 'self'; " .
        'script-src ' . implode(' ', $scriptSrc) . '; ' .
        'style-src ' . implode(' ', $styleSrc) . '; ' .
        'img-src ' . implode(' ', $imgSrc) . '; ' .
        'font-src ' . implode(' ', $fontSrc) . '; ' .
        'connect-src ' . implode(' ', $connectSrc) . '; ' .
        'frame-src ' . implode(' ', $frameSrc) . '; ' .
        'media-src ' . implode(' ', $mediaSrc) . '; ' .
        "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
    );
}
