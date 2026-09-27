<?php
/**
 * Every tracking update on one shipment, with the ability to correct or
 * remove any of them. Open to all admins: fixing a mistyped checkpoint is
 * everyday work, not an administrative privilege.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$shipmentId = (int) ($_GET['shipment'] ?? $_POST['shipment'] ?? 0);
$stmt = db()->prepare('SELECT * FROM shipments WHERE id = ?');
$stmt->execute([$shipmentId]);
$shipment = $stmt->fetch();

if (!$shipment) {
    flash_set('error', 'Shipment not found.');
    redirect('/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $eventId = (int) ($_POST['event_id'] ?? 0);

    $count = db()->prepare('SELECT COUNT(*) FROM tracking_events WHERE shipment_id = ?');
    $count->execute([$shipmentId]);

    if ((int) $count->fetchColumn() <= 1) {
        // A shipment with no history at all would show an empty timeline to
        // the customer, so the last remaining update stays put.
        flash_set('error', 'This is the only update on this shipment. Edit it instead of deleting it, or the tracking page would have nothing to show.');
    } else {
        $del = db()->prepare('DELETE FROM tracking_events WHERE id = ? AND shipment_id = ?');
        $del->execute([$eventId, $shipmentId]);
        resync_shipment_from_events($shipmentId);
        log_admin_activity('Deleted tracking update', $shipment['tracking_number'] . ' update #' . $eventId);
        flash_set('success', 'Update deleted, and the shipment status set back to whatever its newest remaining update says.');
    }
    redirect('/admin/updates.php?shipment=' . $shipmentId);
}

$events = db()->prepare(
    'SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY event_time DESC, id DESC'
);
$events->execute([$shipmentId]);
$rows = $events->fetchAll();

$activeAdminNav = 'dashboard';
$pageTitle = 'Tracking Updates';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Updates for <?= h($shipment['tracking_number']) ?></h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;">
  Current status:
  <span class="badge <?= status_badge_class($shipment['status']) ?>"><?= h($shipment['status']) ?></span>
  &nbsp;
  <a href="/admin/add_update.php?id=<?= (int) $shipment['id'] ?>" class="btn btn-primary btn-sm">Add Update</a>
  <a href="/admin/dashboard.php" class="btn btn-outline btn-sm">Back to Shipments</a>
</p>

<p style="color:var(--muted);font-size:13.5px;max-width:720px;">
  The date and time on each update is whatever was typed when it was saved,
  never the moment it was submitted, so correcting one here changes exactly
  what the customer sees on the timeline. The shipment's own status always
  follows its newest update.
</p>

<div class="table-responsive">
  <table class="data-table">
    <thead>
      <tr><th style="width:170px;">Date &amp; time</th><th>Status</th><th>Location</th><th>Remark</th><th style="width:150px;">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $ev): ?>
        <tr>
          <td data-label="Date &amp; time">
            <?= h(date('M j, Y g:i A', strtotime($ev['event_time']))) ?>
            <?php if ($i === 0): ?>
              <span style="display:block;font-size:11.5px;color:var(--muted);">Newest, sets the current status</span>
            <?php endif; ?>
          </td>
          <td data-label="Status"><span class="badge <?= status_badge_class($ev['status']) ?>"><?= h($ev['status']) ?></span></td>
          <td data-label="Location"><?= h($ev['location_label']) ?></td>
          <td data-label="Remark"><?= $ev['note'] !== null && $ev['note'] !== '' ? h($ev['note']) : '<span style="color:var(--muted);">None</span>' ?></td>
          <td data-label="Actions">
            <a href="/admin/edit_update.php?id=<?= (int) $ev['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <?php if (count($rows) > 1): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete this update? The customer will no longer see this checkpoint.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="shipment" value="<?= (int) $shipmentId ?>">
                <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
                <button type="submit" class="danger">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--muted);">No updates yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
