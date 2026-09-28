<?php
/**
 * Shared PDO/MySQL connection. Included by every entry-point script.
 */

// Security headers, sent on every request site-wide. This runs before
// includes/functions.php is loaded (this file is always required first),
// so HTTPS detection is duplicated here in miniature rather than reusing
// is_https() from there.
// Nothing PHP goes wrong about is ever printed to the browser. A database
// error printed on the page hands over table names, column names and often
// the query itself; a file-path warning gives away the directory layout.
// Both go to the host's error log instead, where the site owner can read
// them and nobody else can.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

$__isAdminArea = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), '/admin/');

if (!headers_sent()) {
    $__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Tells the browser what this site is written in. It is of no use to
    // the browser and of real use to anyone scanning for known bugs in a
    // particular version.
    header_remove('X-Powered-By');

    header('X-Content-Type-Options: nosniff');
    // The admin panel has no business being framed by anything at all; the
    // public site may legitimately be embedded by the site owner.
    header('X-Frame-Options: ' . ($__isAdminArea ? 'DENY' : 'SAMEORIGIN'));
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
    // Stops another site pulling this one into its own browsing context
    // group, and stops it loading this site's pages and files as
    // subresources.
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    // The old Flash/Acrobat cross-domain policy files. There are none here,
    // and this says so explicitly rather than leaving it to be probed.
    header('X-Permitted-Cross-Domain-Policies: none');

    if ($__isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if ($__isAdminArea) {
        // Customer names, addresses and phone numbers pass through these
        // pages. None of it should survive in a browser cache, a shared
        // machine's back button, or a proxy.
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }
}

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    // On the command line (a first-run check over SSH) say exactly what is
    // wrong, because the only person reading it is the one installing the
    // site. In a browser say nothing: a visitor who has just found a site
    // with no configuration should not also be told the file to look for.
    error_log('Site configuration file is missing: ' . $configFile);
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        die(
            'Configuration missing. Copy config/config.sample.php to config/config.php ' .
            'and fill in your database and SMTP details.' . PHP_EOL
        );
    }
    die('This site is not available right now. Please try again shortly.');
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
            // The message can contain the username and the host, so it goes
            // to the log and no further.
            error_log('Database connection failed: ' . $e->getMessage());
            $pdo = null;
        }
    }

    return $pdo;
}

/**
 * Last line of defence for anything that escapes a try/catch.
 *
 * An uncaught PDOException prints the failing SQL by default, and a stack
 * trace prints every argument passed along the way, which on this site
 * includes the database password. Neither ever reaches the browser.
 */
set_exception_handler(static function (Throwable $e): void {
    error_log('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Something went wrong</title></head><body>'
        . '<h1>Something went wrong</h1>'
        . '<p>This page could not be loaded. Please try again in a moment.</p>'
        . '</body></html>';
});

function db(): PDO
{
    $pdo = db_optional();

    if ($pdo === null) {
        // Same reasoning as the missing-config branch above: the details go
        // to the error log, the visitor gets a plain apology. "Database
        // connection failed" on a public page confirms there is a database
        // to attack and often which one.
        error_log('Database connection could not be established.');
        http_response_code(503);
        if (!headers_sent()) {
            header('Retry-After: 120');
        }
        die('This site is not available right now. Please try again shortly.');
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
        "object-src 'none'; base-uri 'self'; form-action 'self'; "
        . 'frame-ancestors ' . ($__isAdminArea ? "'none'" : "'self'")
    );
}
