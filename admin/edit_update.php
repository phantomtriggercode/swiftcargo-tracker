<?php
/**
 * Correct a single tracking update: its status, place, coordinates, remark
 * and, most importantly, the date and time the customer sees against it.
 *
 * No email is sent from here. The alert went out when the update was first
 * added, and re-sending it every time a typo is fixed would be worse than
 * useless to the receiver.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$eventId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM tracking_events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    flash_set('error', 'That update no longer exists.');
    redirect('/admin/dashboard.php');
}

$shipStmt = db()->prepare('SELECT * FROM shipments WHERE id = ?');
$shipStmt->execute([$event['shipment_id']]);
$shipment = $shipStmt->fetch();

$statuses = get_shipment_status_names();
// When coordinates are not being collected they are not shown or asked
// for, and the checkpoint keeps whatever position it already had.
$collectCoords = coordinates_collected();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $locationLabel = trim($_POST['location_label'] ?? '');
    if ($collectCoords) {
        $lat = $_POST['lat'] ?? '';
        $lng = $_POST['lng'] ?? '';
    } else {
        $lat = $event['lat'];
        $lng = $event['lng'];
    }
    $note = trim($_POST['note'] ?? '');
    $eventTimeSql = parse_admin_date_and_time($_POST['event_date'] ?? '', $_POST['event_time'] ?? '');

    // A status that has since been removed from the list is still allowed to
    // stay on an update that already carries it, so editing the remark on an
    // old checkpoint does not force it onto a different status.
    if (!in_array($status, $statuses, true) && $status !== $event['status']) {
        $errors[] = 'Please choose a valid status.';
    }
    if ($locationLabel === '') $errors[] = 'Location is required.';
    if ($collectCoords) {
        if (!is_valid_latitude((string) $lat)) $errors[] = 'Latitude must be a number between -90 and 90.';
        if (!is_valid_longitude((string) $lng)) $errors[] = 'Longitude must be a number between -180 and 180.';
    }
    if ($eventTimeSql === null) $errors[] = 'Pick a real date and time for this update.';

    if (!$errors) {
        $upd = db()->prepare(
            'UPDATE tracking_events
             SET status = ?, location_label = ?, lat = ?, lng = ?, note = ?, event_time = ?
             WHERE id = ?'
        );
        $upd->execute([$status, $locationLabel, $lat, $lng, $note !== '' ? $note : null, $eventTimeSql, $eventId]);

        // Retiming an update can change which one is newest, so the
        // shipment's own status and position are re-derived afterwards.
        resync_shipment_from_events((int) $event['shipment_id']);

        log_admin_activity('Edited tracking update', $shipment['tracking_number'] . ' update #' . $eventId);
        flash_set('success', 'Update saved. No email was sent, this was an edit to an existing checkpoint.');
        redirect('/admin/updates.php?shipment=' . (int) $event['shipment_id']);
    }
}

$activeAdminNav = 'dashboard';
$pageTitle = 'Edit Update';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Edit Update for <?= h($shipment['tracking_number']) ?></h1>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:600px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $eventId ?>">

    <div class="form-group">
      <label>Status</label>
      <?php $selected = $_POST['status'] ?? $event['status']; ?>
      <select name="status" required>
        <?php foreach ($statuses as $opt): ?>
          <option value="<?= h($opt) ?>" <?= $selected === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
        <?php endforeach; ?>
        <?php if (!in_array($event['status'], $statuses, true)): ?>
          <option value="<?= h($event['status']) ?>" selected><?= h($event['status']) ?> (no longer in the status list)</option>
        <?php endif; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Date of this update</label>
        <input type="date" name="event_date" value="<?= h($_POST['event_date'] ?? date_input_value($event['event_time'])) ?>" required>
      </div>
      <div class="form-group">
        <label>Time of this update</label>
        <input type="time" name="event_time" value="<?= h($_POST['event_time'] ?? time_input_value($event['event_time'])) ?>" required>
      </div>
    </div>
    <p style="font-size:12.5px;color:var(--muted);margin:-6px 0 18px;">
      Click either box to pick from a calendar and a clock. This is exactly
      what the customer sees against this checkpoint. Changing it can also
      change which update counts as the newest, and the shipment's status
      follows the newest one.
    </p>

    <div class="form-group">
      <label>Location: Address or Place</label>
      <div class="<?= $collectCoords ? 'input-with-button' : '' ?>">
        <input type="text" id="location_label" name="location_label" value="<?= h($_POST['location_label'] ?? $event['location_label']) ?>" required>
        <?php if ($collectCoords): ?>
          <button type="button" id="location-lookup-btn" class="btn btn-outline btn-sm">Find on map</button>
        <?php endif; ?>
      </div>
      <span id="location-geocode-status" class="geocode-status"></span>
    </div>

    <?php if ($collectCoords): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Latitude</label>
        <input type="text" id="lat" name="lat" value="<?= h($_POST['lat'] ?? $event['lat']) ?>" required>
      </div>
      <div class="form-group">
        <label>Longitude</label>
        <input type="text" id="lng" name="lng" value="<?= h($_POST['lng'] ?? $event['lng']) ?>" required>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Remark or comment (optional)</label>
      <textarea name="note" rows="3"><?= h($_POST['note'] ?? (string) $event['note']) ?></textarea>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        Shown to the customer under this checkpoint. Clearing it leaves the
        checkpoint with no remark rather than refilling the default message.
      </span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
  </form>
</div>

<p style="margin-top:14px;">
  <a href="/admin/updates.php?shipment=<?= (int) $event['shipment_id'] ?>" class="btn btn-outline btn-sm">Cancel</a>
</p>

<?php if ($collectCoords): ?>
<script src="<?= h(asset_url('/assets/js/geocode.js')) ?>" defer></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
