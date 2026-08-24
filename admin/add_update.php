<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM shipments WHERE id = ?');
$stmt->execute([$id]);
$shipment = $stmt->fetch();

if (!$shipment) {
    flash_set('error', 'Shipment not found.');
    redirect('/admin/dashboard.php');
}

$statuses = SHIPMENT_STATUSES;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $locationLabel = trim($_POST['location_label'] ?? '');
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if (!in_array($status, $statuses, true)) $errors[] = 'Please choose a valid status.';
    if ($locationLabel === '') $errors[] = 'Location label is required.';
    // Range-checked, not just "is it a number" — an out-of-range coordinate
    // saves fine but would break the live map for this shipment.
    if (!is_valid_latitude((string) $lat)) $errors[] = 'Latitude must be a number between -90 and 90 — use "Find on map" to fill it in automatically.';
    if (!is_valid_longitude((string) $lng)) $errors[] = 'Longitude must be a number between -180 and 180 — use "Find on map" to fill it in automatically.';

    if (!$errors) {
        // Staff left the note blank — fall back to that status's editable
        // default message (/admin/status_messages.php), baked in now so a
        // later edit to the template never rewrites what this update said.
        if ($note === '') {
            $note = get_status_message($status);
        }

        db()->beginTransaction();

        $insert = db()->prepare('
            INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([$shipment['id'], $status, $locationLabel, $lat, $lng, $note ?: null]);
        $eventId = (int) db()->lastInsertId();

        $update = db()->prepare('
            UPDATE shipments SET status = ?, current_lat = ?, current_lng = ? WHERE id = ?
        ');
        $update->execute([$status, $lat, $lng, $shipment['id']]);

        db()->commit();

        $mailResult = send_tracking_update_email($shipment, [
            'status' => $status,
            'location_label' => $locationLabel,
            'note' => $note,
        ]);

        $flagStmt = db()->prepare('UPDATE tracking_events SET email_sent = ? WHERE id = ?');
        $flagStmt->execute([$mailResult['ok'] ? 1 : 0, $eventId]);

        if ($mailResult['ok']) {
            flash_set('success', 'Update added and email alert sent to ' . $shipment['receiver_email'] . '.');
        } else {
            flash_set('error', 'Update added, but the email alert failed to send: ' . $mailResult['error']);
        }
        redirect('/admin/dashboard.php');
    }
}

$activeAdminNav = 'dashboard';
$pageTitle = 'Add Update';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Add Update — <?= h($shipment['tracking_number']) ?></h1>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:600px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    Receiver: <strong><?= h($shipment['receiver_name']) ?></strong> (<?= h($shipment['receiver_email']) ?>)<br>
    Current status: <span class="badge <?= status_badge_class($shipment['status']) ?>"><?= h($shipment['status']) ?></span>
  </p>

  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>New Status</label>
      <select name="status" required>
        <?php foreach ($statuses as $opt): ?>
          <option value="<?= $opt ?>" <?= $shipment['status'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Location — Address or Place</label>
      <div class="input-with-button">
        <input type="text" id="location_label" name="location_label" placeholder="Paste any address — street, home, or a place name" required>
        <button type="button" id="location-lookup-btn" class="btn btn-outline btn-sm">Find on map</button>
      </div>
      <span id="location-geocode-status" class="geocode-status"></span>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Latitude</label>
        <input type="text" id="lat" name="lat" placeholder="e.g. 39.7392" required>
      </div>
      <div class="form-group">
        <label>Longitude</label>
        <input type="text" id="lng" name="lng" placeholder="e.g. -104.9903" required>
      </div>
    </div>
    <p style="font-size:12.5px;color:var(--muted);margin:-6px 0 18px;">
      "Find on map" fills these in for you. If it's ever unavailable, type them
      by hand instead — open <strong>Google Maps</strong>, right-click the spot,
      and click the numbers at the top of the menu to copy them.
    </p>
    <div class="form-group">
      <label>Note (optional)</label>
      <textarea name="note" rows="3" placeholder="e.g. Departed regional hub, en route to next facility."></textarea>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Update &amp; Email Receiver</button>
  </form>
</div>

<script src="<?= h(asset_url('/assets/js/geocode.js')) ?>"></script>
<script>
  attachGeocodeLookup('location_label', 'lat', 'lng', 'location-lookup-btn', 'location-geocode-status');
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
