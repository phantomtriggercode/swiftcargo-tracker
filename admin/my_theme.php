<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$selectable = get_admin_selectable_themes();
$selectableIds = array_column($selectable, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($action === 'activate') {
        if (!in_array($targetId, $selectableIds, true)) {
            flash_set('error', 'That color option is not available.');
        } elseif (activate_theme($targetId)) {
            flash_set('success', 'Site color updated.');
        } else {
            flash_set('error', 'Theme not found.');
        }
        redirect('/admin/my_theme.php');
    }
}

$activeAdminNav = 'my_theme';
$pageTitle = 'Site Color';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Color</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:640px;">
  Choose the site's color everywhere — this applies to every page for
  every visitor, not just your own screen.
</p>

<div class="theme-grid" style="max-width:600px;">
  <?php foreach ($selectable as $t): ?>
    <div class="theme-card<?= $t['is_active'] ? ' theme-card-active' : '' ?>">
      <div class="theme-swatches">
        <span style="background:<?= h($t['color_primary']) ?>"></span>
        <span style="background:<?= h($t['color_primary_dark']) ?>"></span>
        <span style="background:<?= h($t['color_accent']) ?>"></span>
      </div>
      <div class="theme-card-body">
        <div class="theme-card-title">
          <?= h($t['name']) ?>
          <?php if ($t['is_active']): ?><span class="badge badge-delivered">Active</span><?php endif; ?>
        </div>
        <div class="theme-card-actions">
          <?php if (!$t['is_active']): ?>
            <form method="post">
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm">Use This Color</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$selectable): ?>
    <p style="color:var(--muted);">No color options are available right now.</p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
