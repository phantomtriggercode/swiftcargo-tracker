<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

// The Go-Live Alert is a deployment tool, not day-to-day site branding, so
// only a super admin sees or manages it. Regular admins get this page with
// that section simply absent.
$__canManageDeployAlert = ($__a = current_admin()) && $__a['is_super_admin'];

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset_logo') {
        delete_uploaded_image(get_setting('logo_path', ''));
        set_setting('logo_path', '');
        flash_set('success', 'Logo reset to the default mark.');
        redirect('/admin/branding.php');
    }

    if ($action === 'tracking_format') {
        $prefix = strtoupper(trim($_POST['tracking_number_prefix'] ?? ''));
        $suffix = strtoupper(trim($_POST['tracking_number_suffix'] ?? ''));
        if (!preg_match('/^[A-Z0-9]{0,8}$/', $prefix)) {
            flash_set('error', 'Prefix can only contain letters and numbers, up to 8 characters.');
            redirect('/admin/branding.php');
        }
        if (!preg_match('/^[A-Z0-9]{0,8}$/', $suffix)) {
            flash_set('error', 'Suffix can only contain letters and numbers, up to 8 characters.');
            redirect('/admin/branding.php');
        }
        set_setting('tracking_number_prefix', $prefix);
        set_setting('tracking_number_suffix', $suffix);
        flash_set('success', 'Tracking number format updated. This only affects shipments created from now on, existing tracking numbers don\'t change.');
        redirect('/admin/branding.php');
    }

    if ($action === 'go_live_alert') {
        if (!$__canManageDeployAlert) {
            // Posted by someone who cannot see this section. Say nothing
            // about why, just send them back unchanged.
            redirect('/admin/branding.php');
        }
        $notifyEmail = trim($_POST['deploy_notify_email'] ?? '');
        if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Enter a valid email address, or leave it blank to turn this off.');
            redirect('/admin/branding.php');
        }
        set_setting('deploy_notify_email', $notifyEmail);
        flash_set('success', $notifyEmail !== '' ? 'Go-live alerts turned on.' : 'Go-live alerts turned off.');
        redirect('/admin/branding.php');
    }

    if ($action === 'signin_access') {
        if (!$__canManageDeployAlert) {
            redirect('/admin/branding.php');
        }

        $showLogin = !empty($_POST['header_show_login']) ? '1' : '0';

        // A key of letters, digits, dash and underscore, long enough to be
        // unguessable. Blank turns the gate off, so the sign-in page is
        // reachable normally again.
        $rawKey = trim($_POST['admin_access_key'] ?? '');
        if ($rawKey !== '' && !preg_match('/^[A-Za-z0-9_-]{6,64}$/', $rawKey)) {
            flash_set('error', 'The access key must be 6 to 64 letters, digits, dashes or underscores, and nothing else.');
            redirect('/admin/branding.php');
        }

        set_setting('header_show_login', $showLogin);
        set_setting('admin_access_key', $rawKey);
        log_admin_activity(
            'Changed staff sign-in access',
            'Header link ' . ($showLogin === '1' ? 'shown' : 'hidden')
            . ', access key ' . ($rawKey === '' ? 'off' : 'set')
        );
        flash_set('success', $rawKey === ''
            ? 'Sign-in access saved. The sign-in page is reachable at its normal address.'
            : 'Sign-in access saved. Bookmark the sign-in link shown below: without its key the page now returns "not found".');
        redirect('/admin/branding.php');
    }

    $siteName = trim($_POST['site_name'] ?? '');
    if ($siteName === '') {
        $errors[] = 'Site name cannot be empty.';
    }

    $upload = handle_image_upload('logo', 'logo', 2 * 1024 * 1024);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    $showTitle = !empty($_POST['header_show_title']) ? '1' : '0';
    $showTagline = !empty($_POST['header_show_tagline']) ? '1' : '0';

    // Picking an image already on the server, instead of uploading one.
    // The path is checked against the listing the picker actually offered,
    // not merely pattern-matched, so nothing outside those folders can be
    // set as the logo by editing the form.
    $chosenLogo = trim($_POST['logo_choice'] ?? '');
    if ($chosenLogo !== '' && !is_allowed_library_image($chosenLogo)) {
        $errors[] = 'That image is not one of the pictures on this site, so it was not used.';
        $chosenLogo = '';
    }

    $headerTagline = trim($_POST['header_tagline'] ?? '');
    if (mb_strlen($headerTagline) > 40) {
        $errors[] = 'The tagline has to be 40 characters or fewer, or it will not fit under the name.';
    }

    if (!$errors) {
        set_setting('site_name', $siteName);
        set_setting('header_tagline', $headerTagline);
        set_setting('header_show_title', $showTitle);
        set_setting('header_show_tagline', $showTagline);
        if ($upload['path'] !== null) {
            // A freshly uploaded file always wins: choosing one from the
            // list and also picking a file to upload means the upload is
            // the more deliberate of the two.
            $oldLogo = get_setting('logo_path', '');
            set_setting('logo_path', $upload['path']);
            delete_uploaded_image($oldLogo);
        } elseif ($chosenLogo !== '' && $chosenLogo !== get_setting('logo_path', '')) {
            // Nothing is deleted here. The previous logo may be a picture
            // that is in use elsewhere on the site, or one the owner put on
            // the server themselves, and removing it because the logo
            // changed would be destroying a file nobody asked to lose.
            set_setting('logo_path', $chosenLogo);
        }
        flash_set('success', 'Branding updated.');
        redirect('/admin/branding.php');
    }
}

$activeAdminNav = 'branding';
$pageTitle = 'Branding';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Branding</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:560px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    This name and logo appear everywhere across the site (header, footer, staff login,
    emails). Nothing here is tied to any domain, deploy this codebase under any
    domain name and set whatever brand you want.
  </p>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>Site / Company Name</label>
      <input type="text" name="site_name" value="<?= h(get_site_name()) ?>" maxlength="60" required>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        The header is built to hold up to <strong>24 characters</strong> on one
        line, at any screen size, and a short name is shown larger to fill the
        same space. Your name is
        <strong><?= (int) mb_strlen(get_site_name()) ?> characters</strong>.
        Longer than 24 still shows in full, just at the smallest size.
      </span>
    </div>

    <div class="form-group">
      <label>Header Tagline</label>
      <input type="text" name="header_tagline" value="<?= h(get_header_tagline()) ?>"
             maxlength="40" placeholder="Fast, secure and reliable">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        The small line under the company name in the header. Shown in capitals
        whatever you type here, so write it normally.
      </span>
    </div>

    <div class="form-group">
      <label>What the header shows beside the logo</label>
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-top:8px;">
        <input type="checkbox" name="header_show_title" value="1" <?= header_shows_title() ? 'checked' : '' ?>>
        Show the company name
      </label>
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-top:8px;">
        <input type="checkbox" name="header_show_tagline" value="1" <?= header_shows_tagline() ? 'checked' : '' ?>>
        Show the tagline
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:8px;">
        Untick both and the logo gets the whole brand area to itself and is
        shown considerably larger, which is what you want when the logo
        already has the company name written into it. The name above stays
        editable either way: it is still used in the page title, in emails,
        on the waybill and everywhere else, so it is worth keeping right
        even when the header does not show it.
      </span>
    </div>

    <?php if (logo_is_wide() && header_shows_title()): ?>
      <div class="alert alert-success" style="font-size:13px;">
        This logo is wider than it is tall, which usually means the company
        name is already written into the picture. If it is, untick
        <strong>Show the company name</strong> above: the name will stop
        appearing twice and the logo will be shown larger. If the logo is
        just a wide symbol, leave it ticked.
      </div>
    <?php endif; ?>

    <?php
      $logoDims = logo_file_dimensions(active_logo_url());
      $logoRatio = logo_aspect_ratio();
      $logoShape = $logoDims === null
        ? 'unknown'
        : ($logoRatio >= 1.6 ? 'wide' : ($logoRatio <= 0.7 ? 'tall' : 'square'));
      $currentLogo = get_setting('logo_path', '');
      $library = site_image_library();
    ?>
    <div class="form-group">
      <label>Logo</label>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
        <?php // Shown at its own proportions, so what you see here is the
              // shape the site is actually working with. ?>
        <span style="display:inline-flex;align-items:center;justify-content:center;height:64px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg-soft);">
          <?= logo_img_tag(52, 240, '', 'Current logo') ?>
        </span>
        <span style="font-size:12.5px;color:var(--muted);line-height:1.6;">
          Current logo<br>
          <?php if ($logoDims !== null): ?>
            <strong><?= (int) $logoDims['width'] ?> &times; <?= (int) $logoDims['height'] ?></strong>
            <?php if ($logoShape === 'wide'): ?>
              &mdash; a wide name-plate
            <?php elseif ($logoShape === 'tall'): ?>
              &mdash; taller than it is wide
            <?php else: ?>
              &mdash; a square badge
            <?php endif; ?>
          <?php endif; ?>
        </span>
      </div>

      <?php if ($library): ?>
        <p style="margin:0 0 8px;font-size:13px;color:var(--ink-soft);">
          <strong>Pick one that is already on the site</strong>, or upload a
          new one below.
        </p>
        <div style="max-height:320px;overflow:auto;border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:14px;">
          <?php foreach ($library as $groupLabel => $files): ?>
            <div style="font-size:11.5px;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);font-weight:700;margin:6px 0 8px;"><?= h($groupLabel) ?></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:10px;margin-bottom:14px;">
              <?php foreach ($files as $file): ?>
                <?php $isCurrent = $file['path'] === $currentLogo; ?>
                <label style="display:block;cursor:pointer;border:2px solid <?= $isCurrent ? 'var(--brand-red)' : 'var(--border)' ?>;border-radius:8px;padding:6px;text-align:center;background:var(--white);">
                  <input type="radio" name="logo_choice" value="<?= h($file['path']) ?>" <?= $isCurrent ? 'checked' : '' ?> style="margin-bottom:6px;">
                  <?php // A chequered backdrop so a transparent logo is
                        // visibly transparent rather than looking white. ?>
                  <span style="display:flex;align-items:center;justify-content:center;height:56px;border-radius:5px;
                               background-color:#f3f4f6;
                               background-image:linear-gradient(45deg,#e5e7eb 25%,transparent 25%,transparent 75%,#e5e7eb 75%),linear-gradient(45deg,#e5e7eb 25%,transparent 25%,transparent 75%,#e5e7eb 75%);
                               background-size:12px 12px;background-position:0 0,6px 6px;">
                    <img src="<?= h(str_replace('%2F', '/', rawurlencode($file['path']))) ?>" alt=""
                         style="max-height:50px;max-width:96px;width:auto;height:auto;object-fit:contain;">
                  </span>
                  <span style="display:block;font-size:10.5px;color:var(--muted);margin-top:5px;word-break:break-all;line-height:1.3;">
                    <?= h(mb_strimwidth($file['name'], 0, 26, '...')) ?><br>
                    <?php if ($file['dims'] !== null): ?>
                      <?= (int) $file['dims']['width'] ?>&times;<?= (int) $file['dims']['height'] ?>,
                    <?php endif; ?>
                    <?= $file['size'] > 1024 * 100 ? '<strong style="color:#b45309;">' . (int) round($file['size'] / 1024) . 'KB</strong>' : (int) round($file['size'] / 1024) . 'KB' ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <label style="font-size:13px;">Or upload a new one</label>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        PNG, JPG, WEBP, GIF, or SVG. Max 2MB. Any shape works: the site
        measures the file and gives it the room it needs, so a wide logo is
        never squashed into a square and a square one is never stranded in a
        wide gap. Around 600px on the longest side is plenty; anything much
        larger only slows every page down. Uploading a file overrides a
        picture picked above.
      </span>
    </div>

    <?php if ($logoShape === 'square'): ?>
      <div class="alert alert-error" style="font-size:13px;">
        <strong>About small print inside a square logo.</strong>
        This logo is roughly square, so in a page header it can only ever be
        about as tall as a line of text. Any wording drawn inside it, a
        tagline under the company initials for instance, will be a few
        pixels high there and will not be readable, at any size that still
        leaves room for the menu. That is a limit of the shape, not a
        setting. Two things that do work: the <strong>Header Tagline</strong>
        above says the same thing in real text right beside the logo, at a
        size that reads on a phone; and a <strong>wide</strong> version of
        the logo, with the name and tagline set alongside the symbol rather
        than under it, is shown far larger and stays legible. If you have
        both, use the wide one.
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary btn-block">Save Branding</button>
  </form>
</div>

<?php if (get_logo_url()): ?>
  <form method="post" style="max-width:560px;margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_logo">
    <button type="submit" class="btn btn-outline btn-sm">Reset to default logo mark</button>
  </form>
<?php endif; ?>

<div class="form-card" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Tracking Number Format</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    New shipments get a tracking number built as
    <strong>prefix + 7 digits + 2 letters + suffix</strong> (e.g. with prefix
    "SC" that's <code>SC7482913KE</code>). Change either side here. It only
    applies going forward, existing tracking numbers are untouched. Leave a
    field blank to drop that part entirely.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="tracking_format">
    <div class="form-row">
      <div class="form-group">
        <label>Prefix</label>
        <input type="text" name="tracking_number_prefix" value="<?= h(get_setting('tracking_number_prefix', 'SC')) ?>" maxlength="8" placeholder="e.g. SC">
      </div>
      <div class="form-group">
        <label>Suffix</label>
        <input type="text" name="tracking_number_suffix" value="<?= h(get_setting('tracking_number_suffix', '')) ?>" maxlength="8" placeholder="e.g. US">
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Format</button>
  </form>
</div>

<?php if ($__canManageDeployAlert): ?>
<div class="form-card form-card--super" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Staff sign-in access</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Controls how the staff sign-in page is reached. The sign-in link is
    hidden from the public site by default. Setting an access key removes
    the page from view entirely: without the key it answers
    <strong>"not found"</strong>, so automated scanners looking for a login
    page never find one. The real protection is still the password and the
    lockout; this just takes the door off the street.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="signin_access">

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="header_show_login" value="1" <?= header_shows_login() ? 'checked' : '' ?>>
        Show a "Staff Login" link in the header of the public site
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Off by default. With an access key set, the link carries the key so
        it still works, which also means showing the link gives the key
        away to anyone who reads the page source. Leave it off if you are
        relying on the key for secrecy.
      </span>
    </div>

    <div class="form-group">
      <label>Access key</label>
      <input type="text" name="admin_access_key" id="admin_access_key"
             value="<?= h(get_setting('admin_access_key', '')) ?>"
             maxlength="64" autocomplete="off" spellcheck="false"
             placeholder="Leave blank to keep the sign-in page open">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        6 to 64 letters, digits, dashes or underscores.
        <button type="button" id="gen-access-key" class="btn btn-outline btn-sm" style="margin-left:6px;">Suggest one</button>
      </span>
      <?php $__key = trim(get_setting('admin_access_key', '')); ?>
      <?php if ($__key !== ''): ?>
        <div class="alert" style="margin-top:10px;font-size:12.5px;background:var(--bg-soft);border:1px solid var(--border);color:var(--ink);">
          <strong>Your sign-in link (bookmark it):</strong><br>
          <code style="word-break:break-all;"><?= h(get_site_url() . '/admin/login.php?k=' . rawurlencode($__key)) ?></code><br>
          <span style="color:var(--muted);">Anyone reaching <code>/admin/login.php</code> without the key sees a "not found" page. If you lose the key, clear this field from a signed-in session, or reset it in the database.</span>
        </div>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Sign-in Access</button>
  </form>
</div>

<script>
  (function () {
    var btn = document.getElementById('gen-access-key');
    var field = document.getElementById('admin_access_key');
    if (!btn || !field) return;
    btn.addEventListener('click', function () {
      var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
      var out = '';
      var buf = new Uint32Array(24);
      (window.crypto || window.msCrypto).getRandomValues(buf);
      for (var i = 0; i < 24; i++) { out += chars[buf[i] % chars.length]; }
      field.value = out;
    });
  })();
</script>
<?php endif; ?>

<?php if ($__canManageDeployAlert): ?>
<div class="form-card form-card--super" style="max-width:560px;margin-top:16px;">
  <h3 style="margin-top:0;">Go-Live Alert</h3>
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Get an email the first time this site is visited on a new domain, useful if you
    deploy this codebase somewhere new and want to know the moment it's actually live.
    It fires once per domain (tracked in a setting, not hidden anywhere), then stays
    quiet until the domain changes again. Leave this blank to turn it off.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="go_live_alert">
    <div class="form-group">
      <label>Notify Email</label>
      <input type="email" name="deploy_notify_email" value="<?= h(get_setting('deploy_notify_email', '')) ?>" placeholder="you@yourdomain.com">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save</button>
  </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
