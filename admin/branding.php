<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$errors = [];
$success = null;

$uploadDir = __DIR__ . '/../assets/images/uploads/';
$allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
$maxBytes = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset_logo') {
        set_setting('logo_path', '');
        flash_set('success', 'Logo reset to the default mark.');
        redirect('/admin/branding.php');
    }

    $siteName = trim($_POST['site_name'] ?? '');
    if ($siteName === '') {
        $errors[] = 'Site name cannot be empty.';
    }

    $logoPath = null;
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload failed. Please try again.';
        } elseif ($_FILES['logo']['size'] > $maxBytes) {
            $errors[] = 'Logo file is too large (max 2MB).';
        } else {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'Logo must be a PNG, JPG, WEBP, GIF, or SVG image.';
            } elseif ($ext !== 'svg' && @getimagesize($_FILES['logo']['tmp_name']) === false) {
                $errors[] = 'That file does not look like a valid image.';
            } elseif ($ext === 'svg' && stripos((string) file_get_contents($_FILES['logo']['tmp_name']), '<script') !== false) {
                $errors[] = 'SVG file rejected for containing a script tag.';
            } else {
                $filename = 'logo-' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                    $logoPath = '/assets/images/uploads/' . $filename;
                } else {
                    $errors[] = 'Could not save the uploaded logo.';
                }
            }
        }
    }

    if (!$errors) {
        set_setting('site_name', $siteName);
        if ($logoPath !== null) {
            $oldLogo = get_setting('logo_path', '');
            set_setting('logo_path', $logoPath);
            if ($oldLogo !== '' && str_starts_with($oldLogo, '/assets/images/uploads/')) {
                @unlink(__DIR__ . '/../' . ltrim($oldLogo, '/'));
            }
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
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:560px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    This name and logo appear everywhere across the site (header, footer, staff login,
    emails). Nothing here is tied to any domain — deploy this codebase under any
    domain name and set whatever brand you want.
  </p>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>Site / Company Name</label>
      <input type="text" name="site_name" value="<?= h(get_site_name()) ?>" required>
    </div>

    <div class="form-group">
      <label>Logo</label>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
        <?php if ($logoUrl = get_logo_url()): ?>
          <img src="<?= h($logoUrl) ?>" alt="Current logo" style="height:44px;width:auto;border:1px solid var(--border);border-radius:8px;padding:4px;">
        <?php else: ?>
          <img src="/assets/images/logo-mark.svg" alt="Default logo mark" style="height:44px;width:44px;">
        <?php endif; ?>
        <span style="font-size:12.5px;color:var(--muted);">Current logo</span>
      </div>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">PNG, JPG, WEBP, GIF, or SVG. Max 2MB.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Branding</button>
  </form>
</div>

<?php if (get_logo_url()): ?>
  <form method="post" style="max-width:560px;margin-top:14px;">
    <input type="hidden" name="action" value="reset_logo">
    <button type="submit" class="btn btn-outline btn-sm">Reset to default logo mark</button>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
