<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($action === 'activate') {
        $target = get_palette($targetId);
        if ($target && activate_palette($targetId)) {
            log_admin_activity('Activated color palette', $target['name']);
            flash_set('success', 'Color palette activated site-wide.');
        } else {
            flash_set('error', 'Color palette not found.');
        }
        redirect('/admin/themes.php');
    } elseif ($action === 'duplicate') {
        $source = get_palette($targetId);
        if (!$source) {
            flash_set('error', 'Color palette not found.');
            redirect('/admin/themes.php');
        }
        $columns = array_keys(PALETTE_COLOR_FIELDS);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = db()->prepare('INSERT INTO color_palettes (name, is_active, is_preset, is_admin_selectable, ' . implode(', ', $columns) . ') VALUES (?, 0, 0, 0, ' . $placeholders . ')');
        $values = array_merge(['Copy of ' . $source['name']], array_map(fn($c) => $source[$c], $columns));
        $stmt->execute($values);
        $newId = (int) db()->lastInsertId();
        flash_set('success', 'Color palette duplicated — customize its colors below.');
        redirect('/admin/theme_edit.php?id=' . $newId);
    } elseif ($action === 'delete') {
        $target = get_palette($targetId);
        $result = delete_palette($targetId);
        if ($result['ok'] && $target) {
            log_admin_activity('Deleted color palette', $target['name']);
        }
        flash_set($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Color palette permanently deleted.' : $result['error']);
        redirect('/admin/themes.php');
    }
}

$palettes = get_all_palettes();

$activeAdminNav = 'themes';
$pageTitle = 'Colors';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Colors</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  Only super admins can see this page. The active color palette applies
  site-wide — every public page, the staff login, and the rest of this
  admin panel — and only ever changes colors, never the page layout or
  design (that's a separate, independent choice at
  <a href="/admin/templates.php" style="color:var(--brand-red);">Templates</a>).
  Deleting a palette is permanent and manual — there's no undo, and it's
  always your decision to do it, never automatic. The active palette can't
  be deleted; activate a different one first.
</p>

<div class="theme-grid">
  <?php foreach ($palettes as $p): ?>
    <div class="theme-card<?= $p['is_active'] ? ' theme-card-active' : '' ?>">
      <div class="theme-swatches">
        <span style="background:<?= h($p['color_primary']) ?>"></span>
        <span style="background:<?= h($p['color_primary_dark']) ?>"></span>
        <span style="background:<?= h($p['color_accent']) ?>"></span>
        <span style="background:<?= h($p['color_ink']) ?>"></span>
        <span style="background:<?= h($p['color_bg_soft']) ?>;border:1px solid var(--border);"></span>
      </div>
      <div class="theme-card-body">
        <div class="theme-card-title">
          <?= h($p['name']) ?>
          <?php if ($p['is_active']): ?><span class="badge badge-delivered">Active</span><?php endif; ?>
        </div>
        <div class="theme-card-meta">
          <?php if ($p['is_preset']): ?><span style="color:var(--muted);">Preset</span><?php endif; ?>
        </div>
        <div class="theme-card-actions">
          <?php if (!$p['is_active']): ?>
            <form method="post">
    <?= csrf_field() ?>
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm">Activate</button>
            </form>
          <?php endif; ?>
          <a href="/admin/theme_edit.php?id=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm">Edit Colors</a>
          <form method="post">
    <?= csrf_field() ?>
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Duplicate</button>
          </form>
          <?php if (!$p['is_active']): ?>
            <form method="post" onsubmit="return confirm('Permanently delete the color palette &quot;<?= h(addslashes($p['name'])) ?>&quot;? This cannot be undone.');">
    <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
