<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

ensure_session_started();

$packagingTypes = ['Box', 'Crate', 'Pallet', 'Loose Cargo', 'Full Container Load (FCL)', 'Less Than Container Load (LCL)', 'Envelope/Document'];
$shippingMethods = ['Air', 'Sea', 'Land'];
$landMethods = ['Van', 'Trailer', 'Train'];
$serviceTypes = ['Regular', 'Express'];
$pickupMethods = ['Pickup', 'Drop-off'];

$rates = [
    'base_fee' => get_setting_float('rate_base_fee', 15),
    'price_per_kg' => get_setting_float('rate_price_per_kg', 3.5),
    'air' => get_setting_float('rate_air_multiplier', 1.8),
    'sea' => get_setting_float('rate_sea_multiplier', 1.0),
    'land' => get_setting_float('rate_land_multiplier', 1.2),
    'express' => get_setting_float('rate_express_multiplier', 1.5),
    'insurance_percent' => get_setting_float('rate_insurance_percent', 2.5),
];

function calculate_estimate(array $rates, float $weightKg, string $shippingMethod, string $serviceType, bool $insured, float $insuranceValue): float
{
    $methodMultiplier = match ($shippingMethod) {
        'Air' => $rates['air'],
        'Sea' => $rates['sea'],
        'Land' => $rates['land'],
        default => 1.0,
    };
    $serviceMultiplier = $serviceType === 'Express' ? $rates['express'] : 1.0;

    $estimate = ($rates['base_fee'] + $rates['price_per_kg'] * $weightKg) * $methodMultiplier * $serviceMultiplier;
    if ($insured && $insuranceValue > 0) {
        $estimate += $insuranceValue * ($rates['insurance_percent'] / 100);
    }
    return round($estimate, 2);
}

$errors = [];
$submitted = false;
$referenceId = null;
$finalEstimate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shipFrom = trim($_POST['ship_from'] ?? '');
    $shipTo = trim($_POST['ship_to'] ?? '');
    $packageDescription = trim($_POST['package_description'] ?? '');
    $weightKg = (float) ($_POST['weight_kg'] ?? 0);
    $dimensions = trim($_POST['dimensions'] ?? '');
    $packagingType = $_POST['packaging_type'] ?? '';
    $shippingMethod = $_POST['shipping_method'] ?? '';
    $landMethod = trim($_POST['land_method'] ?? '') ?: null;
    $serviceType = $_POST['service_type'] ?? '';
    $insured = !empty($_POST['insured']);
    $insuranceValue = (float) ($_POST['insurance_value'] ?? 0);
    $preferredDate = trim($_POST['preferred_date'] ?? '') ?: null;
    $preferredTime = trim($_POST['preferred_time'] ?? '') ?: null;
    $pickupMethod = $_POST['pickup_method'] ?? 'Pickup';

    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($shipFrom === '') $errors[] = 'Pickup location is required.';
    if ($shipTo === '') $errors[] = 'Delivery destination is required.';
    if ($packageDescription === '') $errors[] = 'Please describe what you want to ship.';
    if ($weightKg <= 0) $errors[] = 'Weight must be greater than 0.';
    if (!in_array($packagingType, $packagingTypes, true)) $errors[] = 'Please choose a packaging type.';
    if (!in_array($shippingMethod, $shippingMethods, true)) $errors[] = 'Please choose a shipping method.';
    if ($shippingMethod === 'Land' && !in_array($landMethod, $landMethods, true)) $errors[] = 'Please choose a land transport type.';
    if (!in_array($serviceType, $serviceTypes, true)) $errors[] = 'Please choose a service type.';
    if (!in_array($pickupMethod, $pickupMethods, true)) $errors[] = 'Please choose a pickup method.';
    if ($insured && $insuranceValue <= 0) $errors[] = 'Enter a declared value to add insurance.';

    if (!$errors) {
        $finalEstimate = calculate_estimate($rates, $weightKg, $shippingMethod, $serviceType, $insured, $insuranceValue);

        $stmt = db()->prepare('
            INSERT INTO shipment_requests (
              full_name, email, phone, ship_from, ship_to, package_description,
              weight_kg, dimensions, packaging_type, shipping_method, land_method,
              service_type, insured, insurance_value, preferred_date, preferred_time,
              pickup_method, estimated_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $fullName, $email, $phone ?: null, $shipFrom, $shipTo, $packageDescription,
            $weightKg, $dimensions ?: null, $packagingType, $shippingMethod, $landMethod,
            $serviceType, $insured ? 1 : 0, $insured ? $insuranceValue : null, $preferredDate, $preferredTime,
            $pickupMethod, $finalEstimate,
        ]);

        $referenceId = (int) db()->lastInsertId();
        $submitted = true;
    }
}

$activeNav = 'request';
$pageTitle = 'Request a Shipment';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;"><?= h(get_setting('request_title', 'Request a Shipment')) ?></h1>
    <p class="lead"><?= h(get_setting('request_lead', "Tell us what you're shipping and when — we'll get back to you with a confirmed quote. Prices below are a live estimate.")) ?></p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:760px;">

    <?php if ($submitted): ?>
      <div class="alert alert-success">
        Thanks, <?= h($fullName) ?>! Your shipment request <strong>#REQ-<?= str_pad((string) $referenceId, 5, '0', STR_PAD_LEFT) ?></strong>
        has been received. Estimated cost: <strong>$<?= number_format($finalEstimate, 2) ?></strong>.
        Our team will confirm final pricing and pickup details by email at <?= h($email) ?>.
      </div>
      <p style="text-align:center;">
        <a href="/request-shipment.php" class="btn btn-outline">Submit another request</a>
      </p>
    <?php else: ?>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= h($err) ?></div>
      <?php endforeach; ?>

      <!-- step progress indicator -->
      <div class="wizard-steps" id="wizard-steps">
        <div class="wizard-step-node active" data-step="1">
          <div class="wizard-step-circle">1</div>
          <div class="wizard-step-label">Route &amp; Schedule</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="2">
          <div class="wizard-step-circle">2</div>
          <div class="wizard-step-label">Package Details</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="3">
          <div class="wizard-step-circle">3</div>
          <div class="wizard-step-label">Service Options</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="4">
          <div class="wizard-step-circle">4</div>
          <div class="wizard-step-label">Review &amp; Submit</div>
        </div>
      </div>

      <div class="form-card" style="max-width:none;">
        <form method="post" id="request-form">

          <!-- Step 1: Route & Schedule -->
          <div class="wizard-panel" data-step="1">
            <h3 style="margin-top:0;">Your Details</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?= h($_POST['full_name'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label>Phone (optional)</label>
              <input type="text" id="phone" name="phone" value="<?= h($_POST['phone'] ?? '') ?>">
            </div>

            <h3>Shipment Route</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Pickup Location</label>
                <input type="text" id="ship_from" name="ship_from" value="<?= h($_POST['ship_from'] ?? '') ?>" placeholder="e.g. Los Angeles, CA, USA" required>
              </div>
              <div class="form-group">
                <label>Delivery Destination</label>
                <input type="text" id="ship_to" name="ship_to" value="<?= h($_POST['ship_to'] ?? '') ?>" placeholder="e.g. New York, NY, USA" required>
              </div>
            </div>

            <h3>Pickup</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Preferred Date</label>
                <input type="date" id="preferred_date" name="preferred_date" value="<?= h($_POST['preferred_date'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Preferred Time</label>
                <select id="preferred_time" name="preferred_time">
                  <option value="">Any time</option>
                  <option value="Morning (8am–12pm)" <?= ($_POST['preferred_time'] ?? '') === 'Morning (8am–12pm)' ? 'selected' : '' ?>>Morning (8am–12pm)</option>
                  <option value="Afternoon (12pm–4pm)" <?= ($_POST['preferred_time'] ?? '') === 'Afternoon (12pm–4pm)' ? 'selected' : '' ?>>Afternoon (12pm–4pm)</option>
                  <option value="Evening (4pm–8pm)" <?= ($_POST['preferred_time'] ?? '') === 'Evening (4pm–8pm)' ? 'selected' : '' ?>>Evening (4pm–8pm)</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Pickup Method</label>
              <select id="pickup_method" name="pickup_method">
                <?php foreach ($pickupMethods as $opt): ?>
                  <option value="<?= h($opt) ?>" <?= ($_POST['pickup_method'] ?? 'Pickup') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="wizard-nav">
              <span></span>
              <button type="button" class="btn btn-primary wizard-next">Next: Package Details &rarr;</button>
            </div>
          </div>

          <!-- Step 2: Package Details -->
          <div class="wizard-panel" data-step="2" hidden>
            <h3 style="margin-top:0;">What You're Shipping</h3>
            <div class="form-group">
              <label>Package Description</label>
              <input type="text" id="package_description" name="package_description" value="<?= h($_POST['package_description'] ?? '') ?>" placeholder="e.g. Household furniture, 6 boxes" required>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Weight (kg)</label>
                <input type="number" step="0.01" min="0.01" id="weight_kg" name="weight_kg" value="<?= h($_POST['weight_kg'] ?? '1') ?>" required>
              </div>
              <div class="form-group">
                <label>Dimensions (optional)</label>
                <input type="text" id="dimensions" name="dimensions" value="<?= h($_POST['dimensions'] ?? '') ?>" placeholder="e.g. 24in x 18in x 12in">
              </div>
            </div>
            <div class="form-group">
              <label>Packaging Type</label>
              <select name="packaging_type" id="packaging_type">
                <?php foreach ($packagingTypes as $opt): ?>
                  <option value="<?= h($opt) ?>" <?= ($_POST['packaging_type'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="wizard-nav">
              <button type="button" class="btn btn-outline wizard-back">&larr; Back</button>
              <button type="button" class="btn btn-primary wizard-next">Next: Service Options &rarr;</button>
            </div>
          </div>

          <!-- Step 3: Service Options -->
          <div class="wizard-panel" data-step="3" hidden>
            <h3 style="margin-top:0;">How You Want It Shipped</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Shipping Method</label>
                <select name="shipping_method" id="shipping_method">
                  <?php foreach ($shippingMethods as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= ($_POST['shipping_method'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" id="land-method-group" style="display:none;">
                <label>Land Transport Type</label>
                <select name="land_method" id="land_method">
                  <?php foreach ($landMethods as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= ($_POST['land_method'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Service Type</label>
              <select name="service_type" id="service_type">
                <?php foreach ($serviceTypes as $opt): ?>
                  <option value="<?= h($opt) ?>" <?= ($_POST['service_type'] ?? '') === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                <input type="checkbox" name="insured" id="insured" value="1" style="width:auto;" <?= !empty($_POST['insured']) ? 'checked' : '' ?>>
                Add shipment insurance
              </label>
            </div>
            <div class="form-group" id="insurance-value-group" style="display:none;">
              <label>Declared Value (USD)</label>
              <input type="number" step="0.01" min="0" id="insurance_value" name="insurance_value" value="<?= h($_POST['insurance_value'] ?? '') ?>">
            </div>

            <div class="wizard-nav">
              <button type="button" class="btn btn-outline wizard-back">&larr; Back</button>
              <button type="button" class="btn btn-primary wizard-next">Next: Review &amp; Submit &rarr;</button>
            </div>
          </div>

          <!-- Step 4: Review & Submit -->
          <div class="wizard-panel" data-step="4" hidden>
            <h3 style="margin-top:0;">Review Your Request</h3>
            <div class="review-grid" id="review-summary"></div>

            <div class="calculator-box">
              <div class="calculator-label">Estimated Cost</div>
              <div class="calculator-amount" id="calc-amount">$0.00</div>
              <div class="calculator-note">Final pricing is confirmed by our team after review.</div>
            </div>

            <div class="wizard-nav">
              <button type="button" class="btn btn-outline wizard-back">&larr; Back</button>
              <button type="submit" class="btn btn-primary">Submit Shipment Request</button>
            </div>
          </div>

        </form>
      </div>

    <?php endif; ?>
  </div>
</section>

<script>
  window.SHIPPING_RATES = <?= json_encode($rates) ?>;
</script>
<script src="<?= h(asset_url('/assets/js/calculator.js')) ?>"></script>
<script src="<?= h(asset_url('/assets/js/wizard.js')) ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
