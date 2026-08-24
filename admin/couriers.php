<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $errors[] = 'Carrier name is required.';
        }

        if (!$errors) {
            $dup = db()->prepare('SELECT id FROM couriers WHERE name = ?');
            $dup->execute([$name]);
            if ($dup->fetch()) {
                $errors[] = 'A carrier with that name already exists.';
            }
        }

        if (!$errors) {
            $maxOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM couriers')->fetchColumn();
            $stmt = db()->prepare('INSERT INTO couriers (name, sort_order) VALUES (?, ?)');
            $stmt->execute([$name, $maxOrder + 1]);
            flash_set('success', 'Carrier added.');
            redirect('/admin/couriers.php');
        }
    } elseif ($action === 'toggle_active') {
        $stmt = db()->prepare('UPDATE couriers SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$targetId]);
        flash_set('success', 'Carrier updated.');
        redirect('/admin/couriers.php');
    } elseif ($action === 'rename') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash_set('error', 'Carrier name cannot be empty.');
        } else {
            $dup = db()->prepare('SELECT id FROM couriers WHERE name = ? AND id != ?');
            $dup->execute([$name, $targetId]);
            if ($dup->fetch()) {
                flash_set('error', 'A carrier with that name already exists.');
            } else {
                $stmt = db()->prepare('UPDATE couriers SET name = ? WHERE id = ?');
                $stmt->execute([$name, $targetId]);
                flash_set('success', 'Carrier renamed.');
            }
        }
        redirect('/admin/couriers.php');
    } elseif ($action === 'delete') {
        $inUse = db()->prepare('SELECT COUNT(*) FROM shipments WHERE courier_id = ?');
        $inUse->execute([$targetId]);
        if ((int) $inUse->fetchColumn() > 0) {
            flash_set('error', "Can't delete a carrier that's assigned to existing shipments — deactivate it instead.");
        } else {
            $stmt = db()->prepare('DELETE FROM couriers WHERE id = ?');
            $stmt->execute([$targetId]);
            flash_set('success', 'Carrier deleted.');
        }
        redirect('/admin/couriers.php');
    }
}

$couriers = db()->query('SELECT c.*, (SELECT COUNT(*) FROM shipments s WHERE s.courier_id = c.id) AS shipment_count FROM couriers c ORDER BY sort_order ASC, name ASC')->fetchAll();

$activeAdminNav = 'couriers';
$pageTitle = 'Couriers & Carriers';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Couriers &amp; Carriers</h1>
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

<p style="color:var(--muted);font-size:14px;max-width:720px;">
  These are the carrier options offered when creating or editing a shipment
  (DHL, UPS, FedEx, USPS, etc). Deactivate a carrier to hide it from the
  dropdown without losing it from shipments that already use it — only
  carriers with zero shipments can be deleted outright.
</p>

<div class="table-responsive">
<table class="data-table">
  <thead>
    <tr>
      <th>Carrier</th>
      <th>Status</th>
      <th>Shipments</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($couriers as $c): ?>
      <tr>
        <td data-label="Carrier"><?= h($c['name']) ?></td>
        <td data-label="Status"><?= $c['is_active'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge badge-alert">Hidden</span>' ?></td>
        <td data-label="Shipments"><?= (int) $c['shipment_count'] ?></td>
        <td class="actions" data-label="Actions">
          <div class="row-actions">
            <button type="button" class="row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= h($c['name']) ?>">&#8942;</button>
            <template class="row-actions-source">
              <div class="row-actions-status">
                <label>Rename</label>
                <form method="post" style="display:flex;gap:6px;">
    <?= csrf_field() ?>
                  <input type="hidden" name="action" value="rename">
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input type="text" name="name" value="<?= h($c['name']) ?>" required style="min-width:0;flex:1;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;">
                  <button type="submit" class="btn btn-outline btn-sm">Save</button>
                </form>
              </div>
              <form method="post">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button type="submit"><?= $c['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Delete the carrier &quot;<?= h(addslashes($c['name'])) ?>&quot;?');">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="danger" <?= $c['shipment_count'] > 0 ? 'disabled title="In use by existing shipments — deactivate instead"' : '' ?>>Delete</button>
              </form>
            </template>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$couriers): ?>
      <tr><td colspan="4" style="text-align:center;color:var(--muted);">No carriers yet — add one below.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
</div>

<div class="form-card" style="max-width:480px;margin-top:20px;">
  <h3 style="margin-top:0;">Add a Carrier</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group">
      <label>Carrier Name</label>
      <input type="text" name="name" placeholder="e.g. DHL, UPS, FedEx, Blue Dart" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Add Carrier</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
