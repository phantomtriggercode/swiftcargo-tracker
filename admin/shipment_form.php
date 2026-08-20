<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$shipment = null;
$errors = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM shipments WHERE id = ?');
    $stmt->execute([$id]);
    $shipment = $stmt->fetch();
    if (!$shipment) {
        flash_set('error', 'Shipment not found.');
        redirect('/admin/dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderName = trim($_POST['sender_name'] ?? '');
    $senderAddress = trim($_POST['sender_address'] ?? '');
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverEmail = trim($_POST['receiver_email'] ?? '');
    $receiverAddress = trim($_POST['receiver_address'] ?? '');
    $packageDescription = trim($_POST['package_description'] ?? '');
    $weightKg = (float) ($_POST['weight_kg'] ?? 1);
    $serviceType = $_POST['service_type'] ?? 'Standard';
    $originLabel = trim($_POST['origin_label'] ?? '');
    $originLat = $_POST['origin_lat'] ?? '';
    $originLng = $_POST['origin_lng'] ?? '';
    $destinationLabel = trim($_POST['destination_label'] ?? '');
    $destinationLat = $_POST['destination_lat'] ?? '';
    $destinationLng = $_POST['destination_lng'] ?? '';
    $estimatedDelivery = trim($_POST['estimated_delivery'] ?? '') ?: null;

    if ($senderName === '') $errors[] = 'Sender name is required.';
    if ($receiverName === '') $errors[] = 'Receiver name is required.';
    if (!filter_var($receiverEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid receiver email is required.';
    if ($packageDescription === '') $errors[] = 'Package description is required.';
    if ($originLabel === '' || $originLat === '' || $originLng === '') $errors[] = 'Origin (label + coordinates) is required.';
    if ($destinationLabel === '' || $destinationLat === '' || $destinationLng === '') $errors[] = 'Destination (label + coordinates) is required.';
    if (!in_array($serviceType, ['Standard', 'Express', 'Priority'], true)) $errors[] = 'Invalid service type.';

    if (!$errors) {
        if ($shipment) {
            $stmt = db()->prepare('
                UPDATE shipments SET
                  sender_name = ?, sender_address = ?, receiver_name = ?, receiver_email = ?, receiver_address = ?,
                  package_description = ?, weight_kg = ?, service_type = ?,
                  origin_label = ?, origin_lat = ?, origin_lng = ?,
                  destination_label = ?, destination_lat = ?, destination_lng = ?,
                  estimated_delivery = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $senderName, $senderAddress, $receiverName, $receiverEmail, $receiverAddress,
                $packageDescription, $weightKg, $serviceType,
                $originLabel, $originLat, $originLng,
                $destinationLabel, $destinationLat, $destinationLng,
                $estimatedDelivery, $shipment['id'],
            ]);
            flash_set('success', 'Shipment ' . $shipment['tracking_number'] . ' updated.');
            redirect('/admin/dashboard.php');
        } else {
            $trackingNumber = generate_tracking_number();
            // Ensure uniqueness (astronomically unlikely to collide, but be safe).
            $check = db()->prepare('SELECT id FROM shipments WHERE tracking_number = ?');
            do {
                $check->execute([$trackingNumber]);
                if ($check->fetch()) {
                    $trackingNumber = generate_tracking_number();
                } else {
                    break;
                }
            } while (true);

            $stmt = db()->prepare('
                INSERT INTO shipments (
                  tracking_number, sender_name, sender_address, receiver_name, receiver_email, receiver_address,
                  package_description, weight_kg, service_type, status,
                  origin_label, origin_lat, origin_lng,
                  destination_label, destination_lat, destination_lng,
                  current_lat, current_lng, estimated_delivery
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $trackingNumber, $senderName, $senderAddress, $receiverName, $receiverEmail, $receiverAddress,
                $packageDescription, $weightKg, $serviceType,
                $originLabel, $originLat, $originLng,
                $destinationLabel, $destinationLat, $destinationLng,
                $originLat, $originLng, $estimatedDelivery,
            ]);
            $newId = (int) db()->lastInsertId();

            $eventStmt = db()->prepare('
                INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note)
                VALUES (?, \'Pending\', ?, ?, ?, \'Shipment booked and label created.\')
            ');
            $eventStmt->execute([$newId, $originLabel, $originLat, $originLng]);

            $newShipment = ['id' => $newId, 'tracking_number' => $trackingNumber, 'receiver_name' => $receiverName, 'receiver_email' => $receiverEmail];
            $newEvent = ['status' => 'Pending', 'location_label' => $originLabel, 'note' => 'Shipment booked and label created.'];
            $mailResult = send_tracking_update_email($newShipment, $newEvent);

            flash_set('success', 'Shipment ' . $trackingNumber . ' created.' . ($mailResult['ok'] ? ' Confirmation email sent to receiver.' : ' (Email could not be sent: ' . $mailResult['error'] . ')'));
            redirect('/admin/dashboard.php');
        }
    }
}

$activeAdminNav = 'new';
$pageTitle = $shipment ? 'Edit Shipment' : 'New Shipment';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1><?= $shipment ? 'Edit Shipment ' . h($shipment['tracking_number']) : 'New Shipment' ?></h1>
</div>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:720px;">
  <form method="post">
    <h3 style="margin-top:0;">Sender</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Sender Name</label>
        <input type="text" name="sender_name" value="<?= h($shipment['sender_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Sender Address</label>
        <input type="text" name="sender_address" value="<?= h($shipment['sender_address'] ?? '') ?>">
      </div>
    </div>

    <h3>Receiver</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Receiver Name</label>
        <input type="text" name="receiver_name" value="<?= h($shipment['receiver_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Receiver Email (alerts sent here)</label>
        <input type="email" name="receiver_email" value="<?= h($shipment['receiver_email'] ?? '') ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>Receiver Address</label>
      <input type="text" name="receiver_address" value="<?= h($shipment['receiver_address'] ?? '') ?>">
    </div>

    <h3>Package</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="package_description" value="<?= h($shipment['package_description'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Weight (kg)</label>
        <input type="number" step="0.01" min="0.01" name="weight_kg" value="<?= h((string) ($shipment['weight_kg'] ?? '1.00')) ?>" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Service Type</label>
        <select name="service_type">
          <?php foreach (['Standard', 'Express', 'Priority'] as $opt): ?>
            <option value="<?= $opt ?>" <?= ($shipment['service_type'] ?? 'Standard') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Estimated Delivery Date</label>
        <input type="date" name="estimated_delivery" value="<?= h($shipment['estimated_delivery'] ?? '') ?>">
      </div>
    </div>

    <h3>Origin</h3>
    <div class="form-group">
      <label>Origin Label (city, country)</label>
      <input type="text" name="origin_label" value="<?= h($shipment['origin_label'] ?? '') ?>" placeholder="e.g. Lagos, Nigeria" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Origin Latitude</label>
        <input type="text" name="origin_lat" value="<?= h($shipment['origin_lat'] ?? '') ?>" placeholder="e.g. 6.5244" required>
      </div>
      <div class="form-group">
        <label>Origin Longitude</label>
        <input type="text" name="origin_lng" value="<?= h($shipment['origin_lng'] ?? '') ?>" placeholder="e.g. 3.3792" required>
      </div>
    </div>

    <h3>Destination</h3>
    <div class="form-group">
      <label>Destination Label (city, country)</label>
      <input type="text" name="destination_label" value="<?= h($shipment['destination_label'] ?? '') ?>" placeholder="e.g. London, United Kingdom" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Destination Latitude</label>
        <input type="text" name="destination_lat" value="<?= h($shipment['destination_lat'] ?? '') ?>" placeholder="e.g. 51.5072" required>
      </div>
      <div class="form-group">
        <label>Destination Longitude</label>
        <input type="text" name="destination_lng" value="<?= h($shipment['destination_lng'] ?? '') ?>" placeholder="e.g. -0.1276" required>
      </div>
    </div>

    <p style="font-size:12.5px;color:var(--muted);margin:-6px 0 18px;">
      Tip: look up coordinates for any place at
      <a href="https://www.latlong.net" target="_blank" style="color:var(--brand-red);">latlong.net</a>.
    </p>

    <button type="submit" class="btn btn-primary btn-block"><?= $shipment ? 'Save Changes' : 'Create Shipment' ?></button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
