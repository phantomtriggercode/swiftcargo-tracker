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
        if (activate_theme($targetId)) {
            flash_set('success', 'Theme activated site-wide.');
        } else {
            flash_set('error', 'Theme not found.');
        }
        redirect('/admin/themes.php');
    } elseif ($action === 'duplicate') {
        $source = get_theme($targetId);
        if (!$source) {
            flash_set('error', 'Theme not found.');
            redirect('/admin/themes.php');
        }
        $columns = array_keys(THEME_COLOR_FIELDS);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = db()->prepare('INSERT INTO themes (name, style_key, is_active, is_preset, ' . implode(', ', $columns) . ') VALUES (?, ?, 0, 0, ' . $placeholders . ')');
        $values = array_merge(['Copy of ' . $source['name'], $source['style_key']], array_map(fn($c) => $source[$c], $columns));
        $stmt->execute($values);
        $newId = (int) db()->lastInsertId();
        flash_set('success', 'Theme duplicated — customize its colors below.');
        redirect('/admin/theme_edit.php?id=' . $newId);
    } elseif ($action === 'delete') {
        $result = delete_theme($targetId);
        flash_set($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Theme permanently deleted.' : $result['error']);
        redirect('/admin/themes.php');
    }
}

$themes = get_all_themes();

$activeAdminNav = 'themes';
$pageTitle = 'Themes';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Themes</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  Only super admins can see this page. The active theme's colors and
  design style apply site-wide — every public page, the staff login, and
  the rest of this admin panel. Deleting a theme is permanent and manual —
  there's no undo, and it's always your decision to do it, never automatic.
  The active theme can't be deleted; activate a different one first.
</p>

<div class="theme-grid">
  <?php foreach ($themes as $t): ?>
    <div class="theme-card<?= $t['is_active'] ? ' theme-card-active' : '' ?>">
      <div class="theme-swatches">
        <span style="background:<?= h($t['color_primary']) ?>"></span>
        <span style="background:<?= h($t['color_primary_dark']) ?>"></span>
        <span style="background:<?= h($t['color_accent']) ?>"></span>
        <span style="background:<?= h($t['color_ink']) ?>"></span>
        <span style="background:<?= h($t['color_bg_soft']) ?>;border:1px solid var(--border);"></span>
      </div>
      <div class="theme-card-body">
        <div class="theme-card-title">
          <?= h($t['name']) ?>
          <?php if ($t['is_active']): ?><span class="badge badge-delivered">Active</span><?php endif; ?>
        </div>
        <div class="theme-card-meta">
          <?= h(THEME_STYLE_KEYS[$t['style_key']] ?? $t['style_key']) ?>
          <?php if ($t['is_preset']): ?> · <span style="color:var(--muted);">Preset</span><?php endif; ?>
        </div>
        <div class="theme-card-actions">
          <?php if (!$t['is_active']): ?>
            <form method="post">
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm">Activate</button>
            </form>
          <?php endif; ?>
          <a href="/admin/theme_edit.php?id=<?= (int) $t['id'] ?>" class="btn btn-outline btn-sm">Edit Colors</a>
          <form method="post">
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Duplicate</button>
          </form>
          <?php if (!$t['is_active']): ?>
            <form method="post" onsubmit="return confirm('Permanently delete the theme &quot;<?= h(addslashes($t['name'])) ?>&quot;? This cannot be undone.');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
