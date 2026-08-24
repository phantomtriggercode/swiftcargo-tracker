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
        $target = get_template($targetId);
        if ($target && activate_template($targetId)) {
            log_admin_activity('Activated template', $target['name']);
            flash_set('success', 'Template activated site-wide.');
        } else {
            flash_set('error', 'Template not found.');
        }
        redirect('/admin/templates.php');
    } elseif ($action === 'duplicate') {
        $source = get_template($targetId);
        if (!$source) {
            flash_set('error', 'Template not found.');
            redirect('/admin/templates.php');
        }
        $stmt = db()->prepare('INSERT INTO templates (name, layout_key, animation_key, logo_path, is_active, is_preset) VALUES (?, ?, ?, ?, 0, 0)');
        $stmt->execute(['Copy of ' . $source['name'], $source['layout_key'], $source['animation_key'], $source['logo_path']]);
        $newId = (int) db()->lastInsertId();
        flash_set('success', 'Template duplicated — customize it below.');
        redirect('/admin/template_edit.php?id=' . $newId);
    } elseif ($action === 'delete') {
        $target = get_template($targetId);
        $result = delete_template($targetId);
        if ($result['ok'] && $target) {
            log_admin_activity('Deleted template', $target['name']);
        }
        flash_set($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Template permanently deleted.' : $result['error']);
        redirect('/admin/templates.php');
    }
}

$templates = get_all_templates();

$activeAdminNav = 'templates';
$pageTitle = 'Templates';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Templates</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  Only super admins can see this page. The active template controls the
  site's structural design — homepage section order, hero treatment,
  corner/shadow style, scroll animations, and its own default logo — but
  never colors (that's a separate, independent choice at
  <a href="/admin/themes.php" style="color:var(--brand-red);">Colors</a>).
  Deleting a template is permanent and manual — there's no undo. The
  active template can't be deleted; activate a different one first.
</p>

<div class="theme-grid">
  <?php foreach ($templates as $t): ?>
    <div class="theme-card<?= $t['is_active'] ? ' theme-card-active' : '' ?>">
      <div style="display:flex;align-items:center;justify-content:center;height:80px;background:var(--bg-soft);">
        <?php if ($t['logo_path']): ?>
          <img src="<?= h($t['logo_path']) ?>" alt="" width="48" height="48">
        <?php else: ?>
          <span style="color:var(--muted);font-size:12px;">No logo set</span>
        <?php endif; ?>
      </div>
      <div class="theme-card-body">
        <div class="theme-card-title">
          <?= h($t['name']) ?>
          <?php if ($t['is_active']): ?><span class="badge badge-delivered">Active</span><?php endif; ?>
        </div>
        <div class="theme-card-meta">
          <?= h(TEMPLATE_LAYOUT_KEYS[$t['layout_key']] ?? $t['layout_key']) ?>
          <?php if ($t['is_preset']): ?> · <span style="color:var(--muted);">Preset</span><?php endif; ?>
        </div>
        <div class="theme-card-actions">
          <?php if (!$t['is_active']): ?>
            <form method="post">
    <?= csrf_field() ?>
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-primary btn-sm">Activate</button>
            </form>
          <?php endif; ?>
          <a href="/admin/template_edit.php?id=<?= (int) $t['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          <form method="post">
    <?= csrf_field() ?>
            <input type="hidden" name="action" value="duplicate">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">Duplicate</button>
          </form>
          <?php if (!$t['is_active']): ?>
            <form method="post" onsubmit="return confirm('Permanently delete the template &quot;<?= h(addslashes($t['name'])) ?>&quot;? This cannot be undone.');">
    <?= csrf_field() ?>
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
