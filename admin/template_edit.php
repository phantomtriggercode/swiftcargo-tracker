<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_super_admin();

$id = (int) ($_GET['id'] ?? 0);
$template = get_template($id);
if (!$template) {
    flash_set('error', 'Template not found.');
    redirect('/admin/templates.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset_logo') {
        delete_uploaded_image($template['logo_path'] ?? '');
        $defaultPath = '/assets/images/template-logos/' . $template['layout_key'] . '.svg';
        $stmt = db()->prepare('UPDATE templates SET logo_path = ? WHERE id = ?');
        $stmt->execute([$defaultPath, $id]);
        flash_set('success', 'Logo reset to the built-in default for this layout.');
        redirect('/admin/template_edit.php?id=' . $id);
    }

    $name = trim($_POST['name'] ?? '');
    $layoutKey = $_POST['layout_key'] ?? 'classic';
    $animationKey = $_POST['animation_key'] ?? 'fade';

    if ($name === '') $errors[] = 'Template name is required.';
    if (!array_key_exists($layoutKey, TEMPLATE_LAYOUT_KEYS)) $errors[] = 'Please choose a valid layout.';
    if (!array_key_exists($animationKey, TEMPLATE_ANIMATION_KEYS)) $errors[] = 'Please choose a valid animation style.';

    $upload = handle_image_upload('logo', 'template-logo', 2 * 1024 * 1024);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    }

    if (!$errors) {
        $logoPath = $template['logo_path'];
        if ($upload['path'] !== null) {
            delete_uploaded_image($template['logo_path'] ?? '');
            $logoPath = $upload['path'];
        }
        $stmt = db()->prepare('UPDATE templates SET name = ?, layout_key = ?, animation_key = ?, logo_path = ? WHERE id = ?');
        $stmt->execute([$name, $layoutKey, $animationKey, $logoPath, $id]);
        flash_set('success', 'Template updated.' . ($template['is_active'] ? ' Changes are live site-wide.' : ''));
        redirect('/admin/template_edit.php?id=' . $id);
    }
    $template = array_merge($template, ['name' => $name, 'layout_key' => $layoutKey, 'animation_key' => $animationKey]);
}

$activeAdminNav = 'templates';
$pageTitle = 'Edit Template';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Edit Template: <?= h($template['name']) ?></h1>
  <a href="/admin/templates.php" class="btn btn-outline btn-sm">&larr; All Templates</a>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:560px;">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>Template Name</label>
      <input type="text" name="name" value="<?= h($template['name']) ?>" required>
    </div>
    <div class="form-group">
      <label>Layout</label>
      <select name="layout_key">
        <?php foreach (TEMPLATE_LAYOUT_KEYS as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= $template['layout_key'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Scroll Animation</label>
      <select name="animation_key">
        <?php foreach (TEMPLATE_ANIMATION_KEYS as $key => $label): ?>
          <option value="<?= h($key) ?>" <?= $template['animation_key'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Template Logo</label>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
        <?php if ($template['logo_path']): ?>
          <img src="<?= h($template['logo_path']) ?>" alt="Current template logo" style="height:44px;width:auto;border:1px solid var(--border);border-radius:8px;padding:4px;">
        <?php endif; ?>
        <span style="font-size:12.5px;color:var(--muted);">
          Used site-wide whenever no custom logo is uploaded under
          <a href="/admin/branding.php" style="color:var(--brand-red);">Branding</a> — a Branding upload always wins over this.
        </span>
      </div>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">PNG, JPG, WEBP, GIF, or SVG. Max 2MB.</span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Template</button>
  </form>
</div>

<form method="post" style="max-width:560px;margin-top:14px;">
  <input type="hidden" name="action" value="reset_logo">
  <button type="submit" class="btn btn-outline btn-sm">Reset logo to this layout's built-in default</button>
</form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
