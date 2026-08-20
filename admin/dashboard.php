<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$shipments = db()->query('SELECT * FROM shipments ORDER BY created_at DESC')->fetchAll();

$activeAdminNav = 'dashboard';
$pageTitle = 'Dashboard';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Shipments</h1>
  <a href="/admin/shipment_form.php" class="btn btn-primary btn-sm">+ New Shipment</a>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<table class="data-table">
  <thead>
    <tr>
      <th>Tracking #</th>
      <th>Receiver</th>
      <th>Route</th>
      <th>Status</th>
      <th>Updated</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$shipments): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);">No shipments yet. Create one to get started.</td></tr>
    <?php endif; ?>
    <?php foreach ($shipments as $s): ?>
      <tr>
        <td><strong><?= h($s['tracking_number']) ?></strong></td>
        <td><?= h($s['receiver_name']) ?><br><span style="color:var(--muted);font-size:12.5px;"><?= h($s['receiver_email']) ?></span></td>
        <td style="font-size:13px;"><?= h($s['origin_label']) ?> &rarr; <?= h($s['destination_label']) ?></td>
        <td><span class="badge <?= status_badge_class($s['status']) ?>"><?= h($s['status']) ?></span></td>
        <td style="font-size:13px;color:var(--muted);"><?= h(date('M j, g:i A', strtotime($s['updated_at']))) ?></td>
        <td class="actions">
          <a class="btn btn-outline btn-sm" href="/admin/add_update.php?id=<?= (int) $s['id'] ?>">Add Update</a>
          <a class="btn btn-outline btn-sm" href="/admin/shipment_form.php?id=<?= (int) $s['id'] ?>">Edit</a>
          <a class="btn btn-outline btn-sm" href="/track.php?tn=<?= urlencode($s['tracking_number']) ?>" target="_blank">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
