<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

$couriers = get_active_couriers();
$packagingTypes = ['Box', 'Crate', 'Pallet', 'Loose Cargo', 'Full Container Load (FCL)', 'Less Than Container Load (LCL)', 'Envelope/Document'];
$shippingMethods = ['Air', 'Sea', 'Land'];
$landMethods = ['Van', 'Trailer', 'Train'];
$serviceTypes = ['Regular', 'Express'];

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

// Prefill from a public shipment request, when creating a brand-new shipment.
$prefill = null;
$fromRequestId = isset($_GET['from_request']) ? (int) $_GET['from_request'] : (isset($_POST['from_request_id']) ? (int) $_POST['from_request_id'] : null);
if (!$shipment && $fromRequestId) {
    $stmt = db()->prepare('SELECT * FROM shipment_requests WHERE id = ?');
    $stmt->execute([$fromRequestId]);
    $prefill = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderName = trim($_POST['sender_name'] ?? '');
    $senderAddress = trim($_POST['sender_address'] ?? '');
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverEmail = trim($_POST['receiver_email'] ?? '');
    $receiverAddress = trim($_POST['receiver_address'] ?? '');
    $packageDescription = trim($_POST['package_description'] ?? '');
    $packagingType = $_POST['packaging_type'] ?? 'Box';
    $weightKg = (float) ($_POST['weight_kg'] ?? 1);
    $dimensions = trim($_POST['dimensions'] ?? '');
    $serviceType = $_POST['service_type'] ?? 'Regular';
    $shippingMethod = $_POST['shipping_method'] ?? 'Air';
    $landMethod = trim($_POST['land_method'] ?? '') ?: null;
    $courierId = !empty($_POST['courier_id']) ? (int) $_POST['courier_id'] : null;
    $insured = !empty($_POST['insured']);
    $insuranceValue = (float) ($_POST['insurance_value'] ?? 0);
    $paymentType = $_POST['payment_type'] ?? 'Full Payment';
    $paymentPrice = trim($_POST['payment_price'] ?? '');
    $paymentInitialAmount = trim($_POST['payment_initial_amount'] ?? '');
    $paymentAmountPaid = trim($_POST['payment_amount_paid'] ?? '');
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
    if (!in_array($packagingType, $packagingTypes, true)) $errors[] = 'Please choose a valid packaging type.';
    if ($originLabel === '' || $originLat === '' || $originLng === '') {
        $errors[] = 'Origin (label + coordinates) is required.';
    } elseif (!is_numeric($originLat) || !is_numeric($originLng)) {
        $errors[] = 'Origin coordinates must be valid numbers — use "Find on map" to look them up.';
    }
    if ($destinationLabel === '' || $destinationLat === '' || $destinationLng === '') {
        $errors[] = 'Destination (label + coordinates) is required.';
    } elseif (!is_numeric($destinationLat) || !is_numeric($destinationLng)) {
        $errors[] = 'Destination coordinates must be valid numbers — use "Find on map" to look them up.';
    }
    if (!in_array($serviceType, $serviceTypes, true)) $errors[] = 'Invalid service type.';
    if (!in_array($shippingMethod, $shippingMethods, true)) $errors[] = 'Invalid shipping method.';
    if ($shippingMethod === 'Land' && !in_array($landMethod, $landMethods, true)) $errors[] = 'Please choose a land transport type.';
    if ($insured && $insuranceValue <= 0) $errors[] = 'Enter a declared value to add insurance.';
    if ($courierId !== null) {
        $courierCheck = db()->prepare('SELECT id FROM couriers WHERE id = ?');
        $courierCheck->execute([$courierId]);
        if (!$courierCheck->fetch()) {
            $errors[] = 'Please choose a valid carrier.';
        }
    }

    $paymentTypes = ['Full Payment', 'Partial Payment', 'Payment on Arrival'];
    if (!in_array($paymentType, $paymentTypes, true)) $errors[] = 'Please choose a valid payment type.';
    $dbPaymentPrice = null;
    $dbPaymentInitial = null;
    $dbPaymentPaid = null;
    if ($paymentType === 'Partial Payment') {
        if ($paymentInitialAmount === '' || !is_numeric($paymentInitialAmount) || (float) $paymentInitialAmount <= 0) {
            $errors[] = 'Enter the initial amount expected for a partial payment.';
        }
        if ($paymentAmountPaid === '' || !is_numeric($paymentAmountPaid) || (float) $paymentAmountPaid < 0) {
            $errors[] = 'Enter the amount paid so far (0 if nothing has been paid yet).';
        }
        if (!$errors && (float) $paymentAmountPaid > (float) $paymentInitialAmount) {
            $errors[] = 'Amount paid cannot be more than the initial amount expected.';
        }
        if (!$errors) {
            $dbPaymentInitial = (float) $paymentInitialAmount;
            $dbPaymentPaid = (float) $paymentAmountPaid;
        }
    } else {
        if ($paymentPrice !== '' && (!is_numeric($paymentPrice) || (float) $paymentPrice < 0)) {
            $errors[] = 'Enter a valid price, or leave it blank.';
        } elseif ($paymentPrice !== '') {
            $dbPaymentPrice = (float) $paymentPrice;
        }
    }

    if (!$errors) {
        if ($shipment) {
            $stmt = db()->prepare('
                UPDATE shipments SET
                  sender_name = ?, sender_address = ?, receiver_name = ?, receiver_email = ?, receiver_address = ?,
                  package_description = ?, packaging_type = ?, weight_kg = ?, dimensions = ?,
                  service_type = ?, shipping_method = ?, land_method = ?, courier_id = ?, insured = ?, insurance_value = ?,
                  payment_type = ?, payment_price = ?, payment_initial_amount = ?, payment_amount_paid = ?,
                  origin_label = ?, origin_lat = ?, origin_lng = ?,
                  destination_label = ?, destination_lat = ?, destination_lng = ?,
                  estimated_delivery = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $senderName, $senderAddress, $receiverName, $receiverEmail, $receiverAddress,
                $packageDescription, $packagingType, $weightKg, $dimensions ?: null,
                $serviceType, $shippingMethod, $landMethod, $courierId, $insured ? 1 : 0, $insured ? $insuranceValue : null,
                $paymentType, $dbPaymentPrice, $dbPaymentInitial, $dbPaymentPaid,
                $originLabel, $originLat, $originLng,
                $destinationLabel, $destinationLat, $destinationLng,
                $estimatedDelivery, $shipment['id'],
            ]);

            // Insurance status changed — let the receiver know either way
            // (newly insured, or insurance removed), not just silently.
            if ((bool) $shipment['insured'] !== $insured) {
                send_insurance_status_email([
                    'tracking_number' => $shipment['tracking_number'],
                    'receiver_name' => $receiverName,
                    'receiver_email' => $receiverEmail,
                ], $insured, $insuranceValue);
            }

            flash_set('success', 'Shipment ' . $shipment['tracking_number'] . ' updated.');
            redirect('/admin/dashboard.php');
        } else {
            $trackingNumber = generate_tracking_number();
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
                  package_description, packaging_type, weight_kg, dimensions,
                  service_type, shipping_method, land_method, courier_id, insured, insurance_value,
                  payment_type, payment_price, payment_initial_amount, payment_amount_paid, status,
                  origin_label, origin_lat, origin_lng,
                  destination_label, destination_lat, destination_lng,
                  current_lat, current_lng, estimated_delivery
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'Pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $trackingNumber, $senderName, $senderAddress, $receiverName, $receiverEmail, $receiverAddress,
                $packageDescription, $packagingType, $weightKg, $dimensions ?: null,
                $serviceType, $shippingMethod, $landMethod, $courierId, $insured ? 1 : 0, $insured ? $insuranceValue : null,
                $paymentType, $dbPaymentPrice, $dbPaymentInitial, $dbPaymentPaid,
                $originLabel, $originLat, $originLng,
                $destinationLabel, $destinationLat, $destinationLng,
                $originLat, $originLng, $estimatedDelivery,
            ]);
            $newId = (int) db()->lastInsertId();

            $pendingNote = get_status_message('Pending') ?: 'Shipment booked and label created.';
            $eventStmt = db()->prepare('
                INSERT INTO tracking_events (shipment_id, status, location_label, lat, lng, note)
                VALUES (?, \'Pending\', ?, ?, ?, ?)
            ');
            $eventStmt->execute([$newId, $originLabel, $originLat, $originLng, $pendingNote]);

            if ($fromRequestId) {
                $convertStmt = db()->prepare("UPDATE shipment_requests SET status = 'Converted' WHERE id = ?");
                $convertStmt->execute([$fromRequestId]);
            }

            $newShipment = ['id' => $newId, 'tracking_number' => $trackingNumber, 'receiver_name' => $receiverName, 'receiver_email' => $receiverEmail];
            $newEvent = ['status' => 'Pending', 'location_label' => $originLabel, 'note' => $pendingNote];
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

<?php if ($prefill): ?>
  <div class="alert alert-info">Pre-filled from public request #REQ-<?= str_pad((string) $prefill['id'], 5, '0', STR_PAD_LEFT) ?>. Review and complete the remaining fields below.</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:720px;">
  <form method="post">
    <?= csrf_field() ?>
    <?php if ($fromRequestId): ?><input type="hidden" name="from_request_id" value="<?= (int) $fromRequestId ?>"><?php endif; ?>

    <h3 style="margin-top:0;">Sender</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Sender Name</label>
        <input type="text" name="sender_name" value="<?= h($shipment['sender_name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Sender Address</label>
        <input type="text" name="sender_address" value="<?= h($shipment['sender_address'] ?? ($prefill['ship_from'] ?? '')) ?>">
      </div>
    </div>

    <h3>Receiver</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Receiver Name</label>
        <input type="text" name="receiver_name" value="<?= h($shipment['receiver_name'] ?? ($prefill['full_name'] ?? '')) ?>" required>
      </div>
      <div class="form-group">
        <label>Receiver Email (alerts sent here)</label>
        <input type="email" name="receiver_email" value="<?= h($shipment['receiver_email'] ?? ($prefill['email'] ?? '')) ?>" required>
      </div>
    </div>
    <div class="form-group">
      <label>Receiver Address</label>
      <input type="text" name="receiver_address" value="<?= h($shipment['receiver_address'] ?? ($prefill['ship_to'] ?? '')) ?>">
    </div>

    <h3>Package</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="package_description" value="<?= h($shipment['package_description'] ?? ($prefill['package_description'] ?? '')) ?>" required>
      </div>
      <div class="form-group">
        <label>Weight (kg)</label>
        <input type="number" step="0.01" min="0.01" name="weight_kg" value="<?= h((string) ($shipment['weight_kg'] ?? ($prefill['weight_kg'] ?? '1.00'))) ?>" required>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Dimensions (optional)</label>
        <input type="text" name="dimensions" value="<?= h($shipment['dimensions'] ?? ($prefill['dimensions'] ?? '')) ?>" placeholder="e.g. 24in x 18in x 12in">
      </div>
      <div class="form-group">
        <label>Packaging Type</label>
        <select name="packaging_type">
          <?php $curPack = $shipment['packaging_type'] ?? ($prefill['packaging_type'] ?? 'Box'); ?>
          <?php foreach ($packagingTypes as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $curPack === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3>Service</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Service Type</label>
        <select name="service_type">
          <?php $curService = $shipment['service_type'] ?? ($prefill['service_type'] ?? 'Regular'); ?>
          <?php foreach ($serviceTypes as $opt): ?>
            <option value="<?= $opt ?>" <?= $curService === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Estimated Delivery Date</label>
        <input type="date" name="estimated_delivery" value="<?= h($shipment['estimated_delivery'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Shipping Method</label>
        <select name="shipping_method" id="shipping_method_admin">
          <?php $curMethod = $shipment['shipping_method'] ?? ($prefill['shipping_method'] ?? 'Air'); ?>
          <?php foreach ($shippingMethods as $opt): ?>
            <option value="<?= $opt ?>" <?= $curMethod === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="land-method-admin-group">
        <label>Land Transport Type</label>
        <select name="land_method">
          <?php $curLand = $shipment['land_method'] ?? ($prefill['land_method'] ?? ''); ?>
          <option value="">—</option>
          <?php foreach ($landMethods as $opt): ?>
            <option value="<?= $opt ?>" <?= $curLand === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Carrier</label>
      <?php
        $curCourierId = $shipment ? (int) ($shipment['courier_id'] ?? 0) : 0;
        $courierOptions = $couriers;
        if ($curCourierId && !in_array($curCourierId, array_column($couriers, 'id'), true)) {
            $inactiveCurrent = get_courier($curCourierId);
            if ($inactiveCurrent) {
                $inactiveCurrent['name'] .= ' (inactive)';
                $courierOptions[] = $inactiveCurrent;
            }
        }
      ?>
      <select name="courier_id">
        <option value="">— Unbranded / not set —</option>
        <?php foreach ($courierOptions as $co): ?>
          <option value="<?= (int) $co['id'] ?>" <?= $curCourierId === (int) $co['id'] ? 'selected' : '' ?>><?= h($co['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Manage the list of carriers (add DHL, UPS, FedEx, etc) at
        <a href="/admin/couriers.php" style="color:var(--brand-red);">Couriers &amp; Carriers</a>.
      </span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="checkbox" name="insured" id="insured_admin" value="1" style="width:auto;" <?= !empty($shipment['insured']) || !empty($prefill['insured']) ? 'checked' : '' ?>>
        Shipment is insured
      </label>
    </div>
    <div class="form-group" id="insurance-value-admin-group">
      <label>Declared Value (USD)</label>
      <input type="number" step="0.01" min="0" name="insurance_value" value="<?= h($shipment['insurance_value'] ?? ($prefill['insurance_value'] ?? '')) ?>">
    </div>

    <h3>Payment</h3>
    <div class="form-group">
      <label>Payment Type</label>
      <?php $curPaymentType = $shipment['payment_type'] ?? 'Full Payment'; ?>
      <select name="payment_type" id="payment_type_admin">
        <option value="Full Payment" <?= $curPaymentType === 'Full Payment' ? 'selected' : '' ?>>Full Payment</option>
        <option value="Partial Payment" <?= $curPaymentType === 'Partial Payment' ? 'selected' : '' ?>>Partial Payment</option>
        <option value="Payment on Arrival" <?= $curPaymentType === 'Payment on Arrival' ? 'selected' : '' ?>>Payment on Arrival (receiver pays on delivery)</option>
      </select>
    </div>
    <div class="form-group" id="payment-price-admin-group">
      <label>Price (USD)</label>
      <input type="number" step="0.01" min="0" name="payment_price" value="<?= h($shipment['payment_price'] ?? '') ?>" placeholder="Leave blank if not decided yet">
    </div>
    <div class="form-row" id="payment-partial-admin-group">
      <div class="form-group">
        <label>Initial Amount Expected (USD)</label>
        <input type="number" step="0.01" min="0" name="payment_initial_amount" value="<?= h($shipment['payment_initial_amount'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Amount Paid So Far (USD)</label>
        <input type="number" step="0.01" min="0" name="payment_amount_paid" id="payment_amount_paid_admin" value="<?= h($shipment['payment_amount_paid'] ?? '') ?>">
      </div>
    </div>
    <p id="payment-balance-admin" style="font-size:13.5px;color:var(--muted);margin:-10px 0 18px;">
      Remaining balance: <strong>$<?= number_format((float) ($shipment['payment_initial_amount'] ?? 0) - (float) ($shipment['payment_amount_paid'] ?? 0), 2) ?></strong>
    </p>

    <h3>Origin</h3>
    <div class="form-group">
      <label>Origin Address or Place</label>
      <div class="input-with-button">
        <input type="text" id="origin_label" name="origin_label" value="<?= h($shipment['origin_label'] ?? ($prefill['ship_from'] ?? '')) ?>" placeholder="Paste any address — street, home, or a place name" required>
        <button type="button" id="origin-lookup-btn" class="btn btn-outline btn-sm">Find on map</button>
      </div>
      <span id="origin-geocode-status" class="geocode-status"></span>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Origin Latitude</label>
        <input type="text" id="origin_lat" name="origin_lat" value="<?= h($shipment['origin_lat'] ?? '') ?>" placeholder="e.g. 34.0522" required>
      </div>
      <div class="form-group">
        <label>Origin Longitude</label>
        <input type="text" id="origin_lng" name="origin_lng" value="<?= h($shipment['origin_lng'] ?? '') ?>" placeholder="e.g. -118.2437" required>
      </div>
    </div>

    <h3>Destination</h3>
    <div class="form-group">
      <label>Destination Address or Place</label>
      <div class="input-with-button">
        <input type="text" id="destination_label" name="destination_label" value="<?= h($shipment['destination_label'] ?? ($prefill['ship_to'] ?? '')) ?>" placeholder="Paste any address — street, home, or a place name" required>
        <button type="button" id="destination-lookup-btn" class="btn btn-outline btn-sm">Find on map</button>
      </div>
      <span id="destination-geocode-status" class="geocode-status"></span>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Destination Latitude</label>
        <input type="text" id="destination_lat" name="destination_lat" value="<?= h($shipment['destination_lat'] ?? '') ?>" placeholder="e.g. 40.7128" required>
      </div>
      <div class="form-group">
        <label>Destination Longitude</label>
        <input type="text" id="destination_lng" name="destination_lng" value="<?= h($shipment['destination_lng'] ?? '') ?>" placeholder="e.g. -74.0060" required>
      </div>
    </div>

    <p style="font-size:12.5px;color:var(--muted);margin:-6px 0 18px;">
      Click "Find on map" to auto-fill coordinates from the address above, or type
      coordinates manually — look them up at
      <a href="https://www.latlong.net" target="_blank" style="color:var(--brand-red);">latlong.net</a>
      if a lookup doesn't find a precise match.
    </p>

    <button type="submit" class="btn btn-primary btn-block"><?= $shipment ? 'Save Changes' : 'Create Shipment' ?></button>
  </form>
</div>

<script src="<?= h(asset_url('/assets/js/geocode.js')) ?>"></script>
<script>
  attachGeocodeLookup('origin_label', 'origin_lat', 'origin_lng', 'origin-lookup-btn', 'origin-geocode-status');
  attachGeocodeLookup('destination_label', 'destination_lat', 'destination_lng', 'destination-lookup-btn', 'destination-geocode-status');

  (function () {
    var methodSelect = document.getElementById('shipping_method_admin');
    var landGroup = document.getElementById('land-method-admin-group');
    var insuredBox = document.getElementById('insured_admin');
    var insuranceGroup = document.getElementById('insurance-value-admin-group');

    function toggleLand() { landGroup.style.display = methodSelect.value === 'Land' ? '' : 'none'; }
    function toggleInsurance() { insuranceGroup.style.display = insuredBox.checked ? '' : 'none'; }

    methodSelect.addEventListener('change', toggleLand);
    insuredBox.addEventListener('change', toggleInsurance);
    toggleLand();
    toggleInsurance();
  })();

  (function () {
    var paymentType = document.getElementById('payment_type_admin');
    var priceGroup = document.getElementById('payment-price-admin-group');
    var partialGroup = document.getElementById('payment-partial-admin-group');
    var balanceEl = document.getElementById('payment-balance-admin');
    var initialInput = partialGroup.querySelector('[name="payment_initial_amount"]');
    var paidInput = document.getElementById('payment_amount_paid_admin');

    function togglePaymentFields() {
      var isPartial = paymentType.value === 'Partial Payment';
      priceGroup.style.display = isPartial ? 'none' : '';
      partialGroup.style.display = isPartial ? '' : 'none';
      if (balanceEl) balanceEl.style.display = isPartial ? '' : 'none';
    }

    function updateBalance() {
      if (!balanceEl) return;
      var initial = parseFloat(initialInput.value) || 0;
      var paid = parseFloat(paidInput.value) || 0;
      balanceEl.innerHTML = 'Remaining balance: <strong>$' + (initial - paid).toFixed(2) + '</strong>';
    }

    paymentType.addEventListener('change', togglePaymentFields);
    initialInput.addEventListener('input', updateBalance);
    paidInput.addEventListener('input', updateBalance);
    togglePaymentFields();
  })();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
