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

$statuses = ['Pending', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered', 'Delayed', 'Exception'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $locationLabel = trim($_POST['location_label'] ?? '');
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if (!in_array($status, $statuses, true)) $errors[] = 'Please choose a valid status.';
    if ($locationLabel === '') $errors[] = 'Location label is required.';
    if ($lat === '' || !is_numeric($lat)) $errors[] = 'A valid latitude is required.';
    if ($lng === '' || !is_numeric($lng)) $errors[] = 'A valid longitude is required.';

    if (!$errors) {
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
    <div class="form-group">
      <label>New Status</label>
      <select name="status" required>
        <?php foreach ($statuses as $opt): ?>
          <option value="<?= $opt ?>" <?= $shipment['status'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Location Label</label>
      <input type="text" name="location_label" placeholder="e.g. Casablanca, Morocco" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Latitude</label>
        <input type="text" name="lat" placeholder="e.g. 33.5731" required>
      </div>
      <div class="form-group">
        <label>Longitude</label>
        <input type="text" name="lng" placeholder="e.g. -7.5898" required>
      </div>
    </div>
    <div class="form-group">
      <label>Note (optional)</label>
      <textarea name="note" rows="3" placeholder="e.g. Departed regional hub, en route to next facility."></textarea>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save Update &amp; Email Receiver</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
