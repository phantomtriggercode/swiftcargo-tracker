<?php
/**
 * Installation health check — a plain-English "is everything set up
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
require_admin();

/** @var array<int, array{label:string, state:string, detail:string, fix:string}> */
$checks = [];

function check(string $label, string $state, string $detail, string $fix = ''): void
{
    global $checks;
    $checks[] = ['label' => $label, 'state' => $state, 'detail' => $detail, 'fix' => $fix];
}

// ---------------------------------------------------------------
// The live map — the most important thing on the site, so it's first.
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
    check('Live map files', 'ok', 'All map files are present and served from this site — the map does not depend on any outside service to load.');
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
            . 'The site still works meanwhile — it falls back to the original red/classic look.'
    );
} catch (PDOException $e) {
    check('Active colour palette and template', 'warn', 'Could not check — the colour/template tables are missing.', 'Import sql/migrations/011_split_templates_and_colors.sql.');
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
        . ' — otherwise every tracking link you email to customers points at the wrong (probably dead) address.'
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
        . 'They start with a dot, so file managers often hide them — turn on "show hidden files" and re-upload them.'
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
  anything — it only looks and reports. Worth opening right after moving the site to a new
  server, and any time something seems off.
</p>

<div class="health-summary">
  <span class="health-pill health-pill-ok"><?= (int) $counts['ok'] ?> OK</span>
  <span class="health-pill health-pill-warn"><?= (int) $counts['warn'] ?> to look at</span>
  <span class="health-pill health-pill-fail"><?= (int) $counts['fail'] ?> needs fixing</span>
</div>

<?php if ($counts['fail'] === 0 && $counts['warn'] === 0): ?>
  <div class="alert alert-success">Everything checks out — this installation looks healthy.</div>
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
