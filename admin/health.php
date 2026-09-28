<?php
/**
 * Installation health check: a plain-English "is everything set up
 * correctly?" page for the person running the site, who may not write
 * code. Visit it right after uploading to a new server, and any time
 * something looks wrong.
 *
 * Every check says what it means and how to fix it, so a problem here
 * never needs someone to read the codebase. Deliberately read-only: it
 * changes nothing, it only reports.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/live_chat.php';
require_super_admin();

/** @var array<int, array{label:string, state:string, detail:string, fix:string}> */
$checks = [];

function check(string $label, string $state, string $detail, string $fix = ''): void
{
    global $checks;
    $checks[] = ['label' => $label, 'state' => $state, 'detail' => $detail, 'fix' => $fix];
}

// ---------------------------------------------------------------
// The live map: the most important thing on the site, so it's first.
// ---------------------------------------------------------------
$root = dirname(__DIR__);
$mapFiles = [
    'assets/vendor/leaflet/leaflet.js' => 'the map library itself',
    'assets/vendor/leaflet/leaflet.css' => 'the map styling',
    'assets/vendor/leaflet/images/marker-icon.png' => 'map marker images',
];
$missingMap = [];
foreach ($mapFiles as $rel => $what) {
    if (!is_file($root . '/' . $rel) || filesize($root . '/' . $rel) === 0) {
        $missingMap[] = $rel . ' (' . $what . ')';
    }
}
if (!$missingMap) {
    check('Live map files', 'ok', 'All map files are present and served from this site, the map does not depend on any outside service to load.');
} else {
    check(
        'Live map files',
        'fail',
        'Missing or empty: ' . implode(', ', $missingMap),
        'Re-upload the whole assets/vendor/leaflet/ folder, including the images/ subfolder inside it. '
        . 'File managers sometimes skip nested folders. Until this is fixed the tracking page will show '
        . '"The map could not be loaded" instead of the map.'
    );
}

// ---------------------------------------------------------------
// PHP itself
// ---------------------------------------------------------------
check(
    'PHP version',
    version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'fail',
    'Running PHP ' . PHP_VERSION . '.',
    version_compare(PHP_VERSION, '8.0', '>=') ? '' : 'This site needs PHP 8.0 or newer. Change it in your hosting control panel under "PHP Configuration".'
);

$extensions = [
    'pdo_mysql' => 'connecting to the database (nothing works without it)',
    'gd'        => 'drawing the barcode on waybills and labels',
    'mbstring'  => 'handling accented characters correctly',
    'curl'      => 'the "Find on map" address lookup in the admin panel',
    'openssl'   => 'sending email securely over SMTP',
];
foreach ($extensions as $ext => $why) {
    $loaded = extension_loaded($ext);
    check(
        'PHP extension: ' . $ext,
        $loaded ? 'ok' : ($ext === 'pdo_mysql' ? 'fail' : 'warn'),
        $loaded ? 'Installed. Used for ' . $why . '.' : 'Not installed. Needed for ' . $why . '.',
        $loaded ? '' : 'Enable "' . $ext . '" in your hosting control panel under "PHP Configuration" → extensions.'
    );
}

// ---------------------------------------------------------------
// Database
// ---------------------------------------------------------------
$expectedTables = [
    'admins', 'couriers', 'shipments', 'tracking_events', 'settings',
    'color_palettes', 'templates', 'shipment_requests', 'login_attempts', 'admin_activity_log',
    'shipment_statuses',
];
try {
    $found = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($expectedTables, $found));
    if (!$missing) {
        check('Database tables', 'ok', 'Connected, and all ' . count($expectedTables) . ' tables the site needs are present.');
    } else {
        check(
            'Database tables',
            'fail',
            'Connected, but these tables are missing: ' . implode(', ', $missing) . '.',
            'Import sql/schema.sql through phpMyAdmin if this is a fresh install, or the matching file '
            . 'from sql/migrations/ if you are updating an existing site. See the README section '
            . '"Updating an existing site".'
        );
    }
} catch (PDOException $e) {
    check('Database tables', 'fail', 'Could not read the table list from the database.', 'Check the database settings in config/config.php.');
}

// ---------------------------------------------------------------
// Is the database actually up to date with this copy of the code?
//
// Uploading new files without importing the matching SQL is the easiest
// mistake to make when updating, and it usually shows up later as a blank
// error page at the worst moment. These two checks compare what the code
// expects against what the database really has, and name the file to import.
// ---------------------------------------------------------------
$columnsNeeded = [
    'shipments' => [
        'sender_email'   => 'sql/migrations/014_contact_details_and_tracking_display.sql',
        'sender_phone'   => 'sql/migrations/014_contact_details_and_tracking_display.sql',
        'receiver_phone' => 'sql/migrations/014_contact_details_and_tracking_display.sql',
        'estimated_delivery_time' => 'sql/migrations/016_in_transit_status_and_delivery_time.sql',
    ],
];

// Whole tables added by later versions. A missing one is not fatal, every
// piece of code that touches these fails open, but the feature it powers
// is quietly doing nothing, which is worth saying out loud.
$tablesNeeded = [
    'rate_limits'     => 'sql/migrations/019_security_and_seo.sql',
    'security_events' => 'sql/migrations/019_security_and_seo.sql',
    'seo_pages'       => 'sql/migrations/019_security_and_seo.sql',
];
$missingColumns = [];
foreach ($columnsNeeded as $table => $columns) {
    foreach ($columns as $column => $file) {
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int) $stmt->fetchColumn() === 0) {
                $missingColumns[$file][] = $table . '.' . $column;
            }
        } catch (PDOException $e) {
            // Some shared hosts restrict information_schema. Skipping the
            // check is fine: it only means this page cannot confirm either way.
        }
    }
}
foreach ($tablesNeeded as $table => $file) {
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $missingColumns[$file][] = 'the ' . $table . ' table';
        }
    } catch (PDOException $e) {
        // As above: a host that restricts information_schema means this
        // page cannot confirm either way, not that anything is wrong.
    }
}

if ($missingColumns) {
    $lines = [];
    foreach ($missingColumns as $file => $cols) {
        $lines[] = implode(', ', $cols) . ' (from ' . $file . ')';
    }
    check(
        'Database is up to date',
        'fail',
        'Your files are newer than your database. Missing: ' . h(implode('; ', $lines)) . '.',
        'Import the file named above through phpMyAdmin (pick your database, click Import, choose the '
        . 'file, press Go). Until you do, saving a shipment will fail with a blank error page. '
        . 'The file is safe to run more than once.'
    );
} else {
    check('Database is up to date', 'ok', 'Every column this version of the code needs is present in the database.', '');
}

$settingsNeeded = [
    'live_map_enabled'      => 'sql/migrations/014_contact_details_and_tracking_display.sql',
    'tracking_show_logo'    => 'sql/migrations/014_contact_details_and_tracking_display.sql',
    'insurance_enabled'     => 'sql/migrations/015_custom_statuses_and_display_toggles.sql',
    'tracking_show_history' => 'sql/migrations/015_custom_statuses_and_display_toggles.sql',
    'collect_coordinates'   => 'sql/migrations/018_collect_coordinates_switch.sql',
    'live_chat_enabled'     => 'sql/updates/002_live_chat_settings.sql',
    'live_chat_property_id' => 'sql/updates/002_live_chat_settings.sql',
    'live_chat_widget_id'   => 'sql/updates/002_live_chat_settings.sql',
    'seo_noindex_site'      => 'sql/migrations/019_security_and_seo.sql',
    'seo_google_verification' => 'sql/migrations/019_security_and_seo.sql',
    'header_tagline'        => 'sql/updates/003_header_tagline.sql',
    'logo_includes_name'    => 'sql/updates/004_logo_includes_name.sql',
];
try {
    $have = db()->query('SELECT setting_key FROM settings')->fetchAll(PDO::FETCH_COLUMN);
    $missingSettings = [];
    foreach ($settingsNeeded as $key => $file) {
        if (!in_array($key, $have, true)) {
            $missingSettings[$file][] = $key;
        }
    }
    if ($missingSettings) {
        $lines = [];
        foreach ($missingSettings as $file => $keys) {
            $lines[] = implode(', ', $keys) . ' (from ' . $file . ')';
        }
        check(
            'Settings rows',
            'warn',
            'These settings rows are missing: ' . h(implode('; ', $lines)) . '.',
            'Nothing is broken: a missing row falls back to its default, so the site behaves normally. '
            . 'Importing the file named above adds the rows so the setting is stored explicitly rather '
            . 'than assumed. Safe to run more than once.'
        );
    } else {
        check('Settings rows', 'ok', 'Every setting this version of the code uses is stored in the database.', '');
    }
} catch (PDOException $e) {
    check('Settings rows', 'warn', 'Could not read the settings table.', '');
}

// The password the setup guide prints is public knowledge the moment this
// site is online. An account still using it is not a warning, it is an open
// door, so this is a failure rather than a caution.
try {
    $defaultHash = '$2y$12$HYDffKZi7ppAiampmKCVU.Fm8Fk/S4.vKv.dvwoUYPRyvoXs.l9G.';
    $stmt = db()->prepare('SELECT username FROM admins WHERE password_hash = ? AND is_active = 1');
    $stmt->execute([$defaultHash]);
    $stillDefault = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($stillDefault) {
        check(
            'Starter password changed',
            'fail',
            'Still signing in with the password from the setup guide: '
            . h(implode(', ', $stillDefault)) . '.',
            'Anyone who has read the setup guide can sign in to this panel right now. Go to My Profile '
            . 'in the sidebar and set a real password, or, for another account, open Admin Accounts '
            . 'and set one for them.'
        );
    } else {
        check('Starter password changed', 'ok', 'No account is using the password from the setup guide.', '');
    }
} catch (PDOException $e) {
    check('Starter password changed', 'warn', 'Could not read the admin accounts table to check this.', '');
}

// Nothing that describes how the site is built should be readable over the
// web. The rules that block them live in .htaccess, and a host that ignores
// .htaccess would serve them silently, with nothing to notice.
$blockedFiles = [
    '/docs/OPERATIONS.md' => 'the operator guide, which names every page of this panel',
    '/sql/schema.sql'     => 'the database structure and the starter password',
    '/composer.json'      => 'the exact version of every library in use',
    '/config/config.php'  => 'the database and mailbox passwords',
];
$exposed = [];
$unchecked = [];
$refused = 0;
foreach ($blockedFiles as $path => $what) {
    if (!file_exists(dirname(__DIR__) . $path)) {
        continue;
    }

    $context = stream_context_create(['http' => [
        'method' => 'HEAD',
        'timeout' => 3,
        'ignore_errors' => true,   // a 403 is an answer, not a failure
        'follow_location' => 0,
    ]]);
    $headers = @get_headers(get_site_url() . $path, true, $context);
    $status = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';

    if ($status === '') {
        // No answer at all. That is NOT the same as "refused", and must
        // never be reported as if it were: the request may simply not have
        // got out, which says nothing about what a visitor would get.
        $unchecked[] = $path;
    } elseif (str_contains($status, ' 200')) {
        $exposed[] = $path . ' (' . $what . ')';
    } else {
        $refused++;
    }
}

if ($exposed) {
    check(
        'Private files are not served',
        'fail',
        'These are readable by anyone who types the address: ' . h(implode('; ', $exposed)) . '.',
        'The .htaccess file in the site root is supposed to refuse these. Check that it uploaded '
        . '(it starts with a dot, so some upload tools hide it), and that your host has .htaccess '
        . 'enabled. The operator guide under docs/ can also simply be deleted from the server: '
        . 'nothing on the site reads it.'
    );
} elseif ($unchecked) {
    check(
        'Private files are not served',
        'warn',
        'This page could not reach ' . h(implode(', ', $unchecked)) . ' to find out, so it cannot say either way.',
        'Nothing is necessarily wrong. Some hosts stop a site from making web requests back to itself, '
        . 'which is all this needs. To check by hand, open '
        . h(get_site_url() . array_key_first($blockedFiles)) . ' in a private browser window: you should '
        . 'see a "not found" or "forbidden" page, never the file itself.'
    );
} else {
    check(
        'Private files are not served',
        'ok',
        'All ' . $refused . ' checked: the setup guide, the database file and the config file are refused over the web.',
        ''
    );
}

// Design rows the public pages read on every request.
try {
    $paletteCount = (int) db()->query('SELECT COUNT(*) FROM color_palettes WHERE is_active = 1')->fetchColumn();
    $templateCount = (int) db()->query('SELECT COUNT(*) FROM templates WHERE is_active = 1')->fetchColumn();
    $designOk = $paletteCount === 1 && $templateCount === 1;
    check(
        'Active colour palette and template',
        $designOk ? 'ok' : 'warn',
        $designOk
            ? 'Exactly one colour palette and one template are active, as expected.'
            : 'Active colour palettes: ' . $paletteCount . ', active templates: ' . $templateCount . '.',
        $designOk ? '' : 'Go to Colors and to Templates in the sidebar and click "Activate" on the one you want. '
            . 'The site still works meanwhile. It falls back to the original red/classic look.'
    );
} catch (PDOException $e) {
    check('Active colour palette and template', 'warn', 'Could not check: the colour/template tables are missing.', 'Import sql/migrations/011_split_templates_and_colors.sql.');
}

// ---------------------------------------------------------------
// Uploads folder
// ---------------------------------------------------------------
$uploadDir = $root . '/assets/images/uploads';
if (!is_dir($uploadDir)) {
    check('Image uploads folder', 'warn', 'assets/images/uploads/ does not exist yet.', 'It is created automatically the first time you upload a logo or image. No action needed unless uploading fails.');
} elseif (!is_writable($uploadDir)) {
    check('Image uploads folder', 'fail', 'assets/images/uploads/ exists but is not writable.', 'In your file manager, set that folder\'s permissions to 755 so uploads can be saved.');
} else {
    check('Image uploads folder', 'ok', 'Exists and is writable, so logo and image uploads will work.');
}

// ---------------------------------------------------------------
// Site address
// ---------------------------------------------------------------
$configuredUrl = defined('SITE_URL') ? trim((string) SITE_URL) : '';
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if ($configuredUrl === '' || str_contains($configuredUrl, 'localhost')) {
    check(
        'Site address (SITE_URL)',
        'warn',
        'Not set, so the site is guessing its own address from each visit. Right now that gives ' . h(get_site_url()) . '.',
        'Set SITE_URL in config/config.php to your real address (for example https://' . h($currentHost) . '). '
        . 'This is what makes tracking links and password-reset links in emails point at the right place, '
        . 'and it also closes a security hole where a forged request could poison a reset link.'
    );
} elseif ($currentHost !== '' && !str_contains($configuredUrl, $currentHost)) {
    check(
        'Site address (SITE_URL)',
        'fail',
        'SITE_URL is set to ' . h($configuredUrl) . ', but you are viewing the site at ' . h($currentHost) . '.',
        'These must match. Update SITE_URL in config/config.php to https://' . h($currentHost)
        . ': otherwise every tracking link you email to customers points at the wrong (probably dead) address.'
    );
} else {
    check('Site address (SITE_URL)', 'ok', 'Set to ' . h($configuredUrl) . ', which matches the address you are using now.');
}

// ---------------------------------------------------------------
// Email
// ---------------------------------------------------------------
$smtp = smtp_config();
if ($smtp['host'] === '' || $smtp['user'] === '') {
    check('Email (SMTP)', 'warn', 'Not configured yet, so status-update emails to customers will not send.', 'Fill in your mailbox details under "Email (SMTP)" in the sidebar, then use its "Send Test Email" button.');
} elseif ($smtp['from_email'] === '' || !filter_var($smtp['from_email'], FILTER_VALIDATE_EMAIL)) {
    check('Email (SMTP)', 'fail', 'The "from" address is missing or not a valid email address.', 'Set a valid "From" Email under "Email (SMTP)" in the sidebar.');
} elseif (is_reserved_test_domain($smtp['from_email'])) {
    check(
        'Email (SMTP)',
        'fail',
        'The "from" address is ' . h($smtp['from_email']) . ', which uses a placeholder domain that does not exist on the internet.',
        'Real mail servers reject this with "Sender address rejected: Domain not found", so no email will ever reach a customer. '
        . 'Change it under "Email (SMTP)" to an address on a domain you actually own.'
    );
} else {
    check('Email (SMTP)', 'ok', 'Configured, sending as ' . h($smtp['from_email']) . '. Use "Send Test Email" to confirm it actually delivers.');
}

// ---------------------------------------------------------------
// Tracking page display
// ---------------------------------------------------------------
if (live_map_enabled()) {
    check(
        'Tracking page: live map',
        'ok',
        'On. Customers see the live map, and the tracking page carries coordinates as normal.',
        ''
    );
} else {
    check(
        'Tracking page: live map',
        'warn',
        'Switched OFF, so no map and no coordinates appear on the public tracking page.',
        'This is a deliberate setting, not a fault. If customers are asking why there is no map, '
        . 'turn it back on under "Tracking Page" in the sidebar. Nothing was deleted: staff still '
        . 'record coordinates, and turning it on restores the map immediately.'
    );
}
check(
    'Tracking page: company logo',
    'ok',
    tracking_shows_logo()
        ? 'Shown above each tracked shipment. Change the logo itself under Branding.'
        : 'Not shown. Turn it on under "Tracking Page" in the sidebar if you want tracked results branded.',
    ''
);

// ---------------------------------------------------------------
// Live chat
// ---------------------------------------------------------------
$chat = live_chat_settings();
if (!$chat['enabled'] && $chat['property_id'] === '') {
    check(
        'Live chat',
        'ok',
        'Not set up, which is fine: the site works exactly as normal without it.',
        'To offer visitors a chat bubble, connect a free Tawk.to account under "Live Chat" in the sidebar.'
    );
} elseif (!live_chat_is_configured()) {
    check(
        'Live chat',
        'fail',
        'The saved Tawk.to codes are incomplete or malformed, so no chat bubble will appear.',
        'Open "Live Chat" in the sidebar and paste the Widget Code from your Tawk.to dashboard again.'
    );
} elseif (!$chat['enabled']) {
    check(
        'Live chat',
        'warn',
        'Your Tawk.to account is connected but chat is switched off, so visitors see no chat bubble.',
        'Tick "Show the chat bubble on the public site" under "Live Chat" in the sidebar to turn it back on.'
    );
} else {
    check(
        'Live chat',
        'ok',
        'On, loading from ' . h(live_chat_embed_url()) . '. Browsers are being told to allow that one outside host. '
        . 'Open the public site to confirm the bubble appears at the bottom-right.',
        ''
    );
}

// ---------------------------------------------------------------
// Security
// ---------------------------------------------------------------
try {
    $hashes = db()->query('SELECT username, password_hash FROM admins')->fetchAll();
    $stillDefault = [];
    foreach ($hashes as $row) {
        if (password_verify('ChangeMe123!', $row['password_hash'])) {
            $stillDefault[] = $row['username'];
        }
    }
    if ($stillDefault) {
        check(
            'Admin passwords',
            'fail',
            'Still using the default password from the installation guide: ' . h(implode(', ', $stillDefault)) . '.',
            'Anyone who has seen this project knows that password. Change it now under "My Profile" in the sidebar.'
        );
    } else {
        check('Admin passwords', 'ok', 'No account is using the default installation password.');
    }
} catch (PDOException $e) {
    check('Admin passwords', 'warn', 'Could not check the admin accounts table.', '');
}

check(
    'Secure connection (HTTPS)',
    is_https() ? 'ok' : 'warn',
    is_https() ? 'This page was loaded over HTTPS, so logins and customer data are encrypted in transit.' : 'This page was loaded over plain HTTP.',
    is_https() ? '' : 'Turn on the free SSL certificate in your hosting control panel and use the https:// address. '
        . 'Without it, admin passwords travel over the network unencrypted.'
);

// Login/session cookie sanity. A mismatch here is what makes a correct
// password come back as "your session expired", usually only on phones,
// because desktop users tend to arrive via an https:// bookmark.
$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 0);
$cookieSecure = is_https_for_cookie();
if ($forwardedProto === 'https' && !is_https_for_cookie()) {
    check(
        'Login sessions',
        'warn',
        'Your host reports this request as HTTPS, but it arrived on port ' . $serverPort . ', which is the plain HTTP port. '
        . 'The session cookie is deliberately being sent without the "Secure" flag so that logins keep working.',
        'Nothing is broken. This is the safe fallback. To get the extra hardening back, make the site always '
        . 'use https:// (turn on "Force HTTPS" in your hosting control panel), then re-check this page.'
    );
} elseif (!is_https() && !$cookieSecure) {
    check(
        'Login sessions',
        'warn',
        'The site is being used over plain HTTP, so the login cookie cannot be marked "Secure".',
        'Turn on the free SSL certificate and "Force HTTPS" in your hosting control panel, then use the https:// address.'
    );
} else {
    check('Login sessions', 'ok', 'The session cookie is correctly marked Secure, HttpOnly and SameSite=Lax: logins will work and stay protected.');
}

$protectedDirs = ['config', 'includes', 'sql', 'vendor'];
$missingHtaccess = [];
foreach ($protectedDirs as $dir) {
    if (!is_file($root . '/' . $dir . '/.htaccess')) {
        $missingHtaccess[] = $dir . '/';
    }
}
check(
    'Protected folders',
    $missingHtaccess ? 'fail' : 'ok',
    $missingHtaccess
        ? 'Missing .htaccess protection in: ' . implode(', ', $missingHtaccess)
        : 'All sensitive folders have their .htaccess protection in place.',
    $missingHtaccess
        ? 'These files block visitors from browsing straight to your database settings and source code. '
        . 'They start with a dot, so file managers often hide them: turn on "show hidden files" and re-upload them.'
        : ''
);

$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $c) { $counts[$c['state']]++; }

$activeAdminNav = 'health';
$pageTitle = 'System Health';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>System Health</h1>
</div>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  A plain-English check of whether this installation is set up correctly. Nothing here changes
  anything. It only looks and reports. Worth opening right after moving the site to a new
  server, and any time something seems off.
</p>

<div class="health-summary">
  <span class="health-pill health-pill-ok"><?= (int) $counts['ok'] ?> OK</span>
  <span class="health-pill health-pill-warn"><?= (int) $counts['warn'] ?> to look at</span>
  <span class="health-pill health-pill-fail"><?= (int) $counts['fail'] ?> needs fixing</span>
</div>

<?php if ($counts['fail'] === 0 && $counts['warn'] === 0): ?>
  <div class="alert alert-success">Everything checks out. This installation looks healthy.</div>
<?php endif; ?>

<div class="health-list">
  <?php foreach ($checks as $c): ?>
    <div class="health-item health-item-<?= h($c['state']) ?>">
      <div class="health-item-head">
        <span class="health-badge health-badge-<?= h($c['state']) ?>">
          <?= $c['state'] === 'ok' ? 'OK' : ($c['state'] === 'warn' ? 'CHECK' : 'FIX') ?>
        </span>
        <strong><?= h($c['label']) ?></strong>
      </div>
      <div class="health-item-detail"><?= h($c['detail']) ?></div>
      <?php if ($c['fix'] !== ''): ?>
        <div class="health-item-fix"><strong>What to do:</strong> <?= h($c['fix']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
