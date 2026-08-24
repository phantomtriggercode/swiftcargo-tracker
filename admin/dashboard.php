<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$shipments = db()->query('
    SELECT s.*, c.name AS courier_name
    FROM shipments s
    LEFT JOIN couriers c ON c.id = s.courier_id
    ORDER BY s.created_at DESC
')->fetchAll();

$activeAdminNav = 'dashboard';
$pageTitle = 'Shipments';
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

<div class="table-responsive">
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
        <td data-label="Tracking #"><strong><?= h($s['tracking_number']) ?></strong><?php if ($s['courier_name']): ?><br><span style="color:var(--muted);font-size:12px;"><?= h($s['courier_name']) ?></span><?php endif; ?></td>
        <td data-label="Receiver"><?= h($s['receiver_name']) ?><br><span style="color:var(--muted);font-size:12.5px;"><?= h($s['receiver_email']) ?></span></td>
        <td data-label="Route" style="font-size:13px;"><?= h($s['origin_label']) ?> &rarr; <?= h($s['destination_label']) ?></td>
        <td data-label="Status"><span class="badge <?= status_badge_class($s['status']) ?>"><?= h($s['status']) ?></span></td>
        <td data-label="Updated" style="font-size:13px;color:var(--muted);"><?= h(date('M j, g:i A', strtotime($s['updated_at']))) ?></td>
        <td class="actions" data-label="Actions">
          <div class="row-actions">
            <button type="button" class="row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= h($s['tracking_number']) ?>">&#8942;</button>
            <template class="row-actions-source">
              <a href="/admin/add_update.php?id=<?= (int) $s['id'] ?>">Update</a>
              <a href="/admin/shipment_form.php?id=<?= (int) $s['id'] ?>">Edit</a>
              <a href="/track.php?tn=<?= urlencode($s['tracking_number']) ?>" target="_blank">Track</a>
              <a href="/documents/waybill.php?tn=<?= urlencode($s['tracking_number']) ?>" target="_blank">Waybill</a>
              <a href="/documents/label.php?tn=<?= urlencode($s['tracking_number']) ?>" target="_blank">Label</a>
              <form method="post" action="/admin/shipment_delete.php" onsubmit="return confirm('Delete shipment <?= h(addslashes($s['tracking_number'])) ?> and its full tracking history? This cannot be undone.');">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="danger">Delete</button>
              </form>
            </template>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
