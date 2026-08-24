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
    // 'unsafe-inline' is required for script-src/style-src because this
    // codebase uses inline <script> blocks (menu toggles, admin widgets)
    // and inline style="" attributes throughout, plus the theme system's
    // injected <style> tag — a stricter policy would break the site. Even
    // with that, this still blocks loading scripts/styles/frames/images
    // from any origin other than the ones explicitly listed below.
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "img-src 'self' data: https://*.tile.openstreetmap.org; " .
        "font-src 'self' data:; " .
        "connect-src 'self'; " .
        "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'"
    );
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

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Check config/config.php credentials.');
        }
    }

    return $pdo;
}
