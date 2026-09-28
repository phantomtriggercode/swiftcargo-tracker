<?php
/**
 * Small shared helpers used across public + admin pages.
 */

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/design.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/seo.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * The statuses this codebase ships with, and the fallback used if the
 * shipment_statuses table cannot be read (for example the migration has
 * not been imported yet). Same fail-open habit as get_setting(): a
 * database problem must never leave the status dropdown empty.
 */
const DEFAULT_SHIPMENT_STATUSES = [
    'Pending', 'Picked Up', 'In Transit', 'En Route', 'Customs Clearance',
    'Insurance Clearance', 'Out for Delivery', 'Delivered', 'On Hold',
    'Delayed', 'Exception',
];

/**
 * Every status staff can choose from, in display order, as managed at
 * /admin/statuses.php.
 *
 * @return array<int, array{id:int, name:string, badge_class:string, sort_order:int, is_protected:int}>
 */
function get_shipment_statuses(): array
{
    static $cache = null;

    if ($cache === null) {
        try {
            $cache = db()->query(
                'SELECT id, name, badge_class, sort_order, is_protected
                 FROM shipment_statuses ORDER BY sort_order, name'
            )->fetchAll();
        } catch (PDOException $e) {
            $cache = [];
        }

        if (!$cache) {
            $order = 0;
            foreach (DEFAULT_SHIPMENT_STATUSES as $name) {
                $order += 10;
                $cache[] = [
                    'id' => 0,
                    'name' => $name,
                    'badge_class' => 'badge-pending',
                    'sort_order' => $order,
                    'is_protected' => $name === 'Pending' ? 1 : 0,
                ];
            }
        }
    }

    return $cache;
}

/** Just the status names, which is all most callers need. */
function get_shipment_status_names(): array
{
    return array_column(get_shipment_statuses(), 'name');
}

/** Settings key holding the admin-editable default message for a status (see /admin/status_messages.php). */
/**
 * The estimated delivery, as one readable line for the tracking page and
 * the waybill. Shared so the two can never word it differently.
 *
 * The time is optional: a shipment usually has a delivery day well before
 * anyone can promise an hour, so it is left out until one is set.
 */
function estimated_delivery_label(array $shipment): string
{
    $date = $shipment['estimated_delivery'] ?? null;
    if (!$date) {
        return 'TBD';
    }

    $label = date('M j, Y', strtotime($date));
    $time = $shipment['estimated_delivery_time'] ?? null;

    if ($time) {
        $ts = strtotime($date . ' ' . $time);
        if ($ts !== false) {
            $label .= ' at ' . date('g:i A', $ts);
        }
    }

    return $label;
}

function status_message_key(string $status): string
{
    // Spaces become underscores and anything else is dropped, so a status
    // named with punctuation still produces a clean settings key. Every
    // status shipped so far is plain words, so existing keys are unchanged.
    $slug = strtolower(str_replace(' ', '_', trim($status)));
    $slug = preg_replace('/[^a-z0-9_]/', '', $slug);

    return 'status_message_' . substr($slug, 0, 60);
}

function get_status_message(string $status): string
{
    return get_setting(status_message_key($status), '');
}

/**
 * Human-readable payment status for a shipment row (needs payment_type,
 * payment_price, payment_initial_amount, payment_amount_paid). Shared by
 * the public tracking page, the admin dashboard, and the waybill PDF so
 * the wording never drifts between them.
 */
function payment_status_label(array $shipment): string
{
    $price = $shipment['payment_price'] ?? null;
    switch ($shipment['payment_type'] ?? 'Full Payment') {
        case 'Partial Payment':
            $initial = (float) ($shipment['payment_initial_amount'] ?? 0);
            $paid = (float) ($shipment['payment_amount_paid'] ?? 0);
            $balance = $initial - $paid;
            return 'Partial payment: $' . number_format($paid, 2) . ' paid of $' . number_format($initial, 2)
                . ' ($' . number_format($balance, 2) . ' remaining)';
        case 'Payment on Arrival':
            return 'Payment due on arrival' . ($price !== null ? ' ($' . number_format((float) $price, 2) . ')' : '');
        default:
            return 'Paid in full' . ($price !== null ? ' ($' . number_format((float) $price, 2) . ')' : '');
    }
}

/**
 * Appends a cache-busting ?v= query string (the file's last-modified time)
 * to a local /assets/... URL, so browsers and any intermediate cache fetch
 * a fresh copy the moment a CSS/JS file changes on the server, instead of
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
    // e.g. SC7482913KE, admin-configured prefix + 7 random digits +
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
 * True if this string is shaped like a tracking number at all.
 *
 * The database column holds up to 32 characters and every number this site
 * issues, or that a carrier issues, is letters, digits and at most a
 * separator. Checking that before running a lookup means a public page
 * never passes a paragraph of someone else's making to the database, and
 * an obviously bogus lookup costs nothing to answer.
 *
 * This is belt and braces, not the actual defence: every lookup is a
 * prepared statement, so the value is sent as data and can never be read
 * as SQL whatever it contains.
 */
function is_valid_tracking_number(string $value): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9 _\-]{2,31}$/', $value);
}

/**
 * True if this is acceptable as a tracking number an admin typed in by
 * hand, overriding the generated one.
 *
 * Stricter than is_valid_tracking_number() on purpose: no spaces, so the
 * number always sits cleanly in a tracking URL and an email. Three to
 * thirty-two characters of letters, digits, dash and underscore, which is
 * a subset of what the public lookup accepts, so any number this allows is
 * guaranteed to be trackable.
 */
function is_valid_manual_tracking_number(string $value): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,31}$/', $value);
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

/**
 * True if $value is a usable latitude (-90..90) / longitude (-180..180).
 *
 * Coordinates are the one piece of admin-entered data the live map cannot
 * survive being wrong: a typo like a longitude pasted without its decimal
 * point ("-1182437" instead of "-118.2437") would otherwise be stored
 * happily by MySQL and then break the map for that shipment. Validated on
 * the way in, on every form that accepts coordinates, so bad values never
 * reach the database. assets/js/map.js independently guards against bad
 * values too, so older rows saved before this check still can't break it.
 */
function is_valid_latitude(string $value): bool
{
    return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
}

function is_valid_longitude(string $value): bool
{
    return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
}

function status_badge_class(string $status): string
{
    // A status staff created themselves carries its own colour choice.
    foreach (get_shipment_statuses() as $row) {
        if ($row['name'] === $status) {
            return $row['badge_class'] !== '' ? $row['badge_class'] : 'badge-pending';
        }
    }

    // A status no longer in the list (an older shipment still carrying a
    // status that has since been deleted) still needs a sensible colour.
    return match ($status) {
        'Delivered' => 'badge-delivered',
        'Out for Delivery' => 'badge-transit',
        'En Route', 'In Transit' => 'badge-transit',
        'Customs Clearance', 'Insurance Clearance' => 'badge-hold',
        'Picked Up', 'Pending' => 'badge-pending',
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
 * The site's base URL, used to build absolute links in emails (tracking
 * links, password-reset links), the waybill PDF footer, and sitemap.xml.
 *
 * SITE_URL in config/config.php is the single place to set this. When it's
 * set, it always wins. That is the recommended production setup, and the
 * ONLY configuration that's safe against Host-header spoofing (see below).
 *
 * When SITE_URL is blank (or still pointing at localhost), the URL is
 * auto-detected from the incoming request so the site works out of the box
 * on any domain. That convenience has a real caveat: the host comes from
 * the request's own Host header, which the client controls. An attacker
 * can send `Host: evil.example` to /admin/forgot_password.php with a real
 * admin's email address, and the reset email that admin receives would
 * carry a link pointing at the attacker's domain with a valid token
 * attached, classic password-reset poisoning. Setting SITE_URL closes
 * that off completely, which is why the deploy docs insist on it.
 *
 * Either way the host is sanitized to characters actually legal in a
 * hostname, so a malformed header can never inject extra URL structure
 * (a path, a second host, CR/LF) into a link that gets emailed out.
 */
function get_site_url(): string
{
    if (defined('SITE_URL') && SITE_URL !== '' && !str_contains(SITE_URL, 'localhost')) {
        return rtrim(SITE_URL, '/');
    }

    $scheme = is_https() ? 'https' : 'http';
    $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Letters, digits, dot, hyphen and an optional :port, nothing else.
    $host = preg_match('/^[A-Za-z0-9.\-]+(:[0-9]{1,5})?$/', $rawHost) ? $rawHost : 'localhost';

    return $scheme . '://' . $host;
}

/**
 * Opt-in "go-live" alert: if a notify email is set under Branding, emails
 * it the first time the site is ever seen on a given domain. Documented,
 * admin-configured, and visible in /admin/branding.php, not hidden.
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

/**
 * Turns a date and time string into a value MySQL will store, or null if
 * it is not a usable date. Callers normally reach this through
 * parse_admin_date_and_time(), which joins a date field and a time field.
 *
 * Every tracking update carries the time staff chose rather than the moment
 * the form was submitted, so the customer's timeline reflects when things
 * actually happened, including checkpoints recorded after the fact.
 */
function parse_admin_datetime(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    // Browsers send "2026-09-27T14:30", sometimes with seconds.
    $formats = ['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $input);
        if (!$date instanceof DateTime) {
            continue;
        }

        // createFromFormat silently rolls impossible values over: month 13
        // becomes January of the next year, hour 99 becomes four days on,
        // and 29 February in a non-leap year becomes 1 March. Requiring the
        // parsed date to format back to exactly what was typed rejects those
        // rather than storing a date nobody entered.
        if ($date->format($format) !== $input) {
            continue;
        }

        return $date->format('Y-m-d H:i:s');
    }

    return null;
}

/**
 * The date and the time halves of a stored timestamp, for a pair of
 * <input type="date"> and <input type="time"> fields. Split in two because
 * each one then gets its own native picker: a calendar for the date, an
 * hour and minute list for the time.
 */
function date_input_value(?string $sqlDateTime): string
{
    $ts = $sqlDateTime ? strtotime($sqlDateTime) : false;
    return date('Y-m-d', $ts === false ? time() : $ts);
}

function time_input_value(?string $sqlDateTime): string
{
    $ts = $sqlDateTime ? strtotime($sqlDateTime) : false;
    return date('H:i', $ts === false ? time() : $ts);
}

/**
 * Combines a date field and a time field into one value MySQL will store,
 * or null if either half is missing or impossible. Validation stays in
 * parse_admin_datetime(), which rejects dates that only look valid because
 * PHP would roll them over.
 */
function parse_admin_date_and_time(string $date, string $time): ?string
{
    $date = trim($date);
    $time = trim($time);

    if ($date === '' || $time === '') {
        return null;
    }

    // A time input sends "HH:MM", or "HH:MM:SS" when it is set to seconds.
    return parse_admin_datetime($date . 'T' . $time);
}

/**
 * Re-derives a shipment's current status and position from its tracking
 * updates, newest first by the time staff entered.
 *
 * Needed because updates can be edited, retimed and deleted. Without this,
 * correcting the date on an update, or deleting the latest one, would leave
 * the shipment showing a status that no longer matches its own history.
 */
function resync_shipment_from_events(int $shipmentId): void
{
    $stmt = db()->prepare(
        'SELECT status, lat, lng FROM tracking_events
         WHERE shipment_id = ?
         ORDER BY event_time DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([$shipmentId]);
    $latest = $stmt->fetch();

    if (!$latest) {
        return; // No updates left; leave the shipment exactly as it is.
    }

    $update = db()->prepare(
        'UPDATE shipments SET status = ?, current_lat = ?, current_lng = ? WHERE id = ?'
    );
    $update->execute([$latest['status'], $latest['lat'], $latest['lng'], $shipmentId]);
}
