<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/mailer.php';

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

$requestEnabled = request_shipment_enabled();

if ($requestEnabled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // A booking request is a considered thing to send. More than a few an
    // hour from one address is a script filling the table with rubbish.
    rate_limit_enforce('shipment_request', 6, 3600, '', 3600);

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $senderName = trim($_POST['sender_name'] ?? '');
    $senderPhone = trim($_POST['sender_phone'] ?? '');
    $senderEmail = trim($_POST['sender_email'] ?? '');
    $senderAddress = trim($_POST['sender_address'] ?? '');
    $receiverName = trim($_POST['receiver_name'] ?? '');
    $receiverPhone = trim($_POST['receiver_phone'] ?? '');
    $receiverEmail = trim($_POST['receiver_email'] ?? '');
    $receiverAddress = trim($_POST['receiver_address'] ?? '');
    $shipFrom = trim($_POST['ship_from'] ?? '');
    $shipTo = trim($_POST['ship_to'] ?? '');
    $packageDescription = trim($_POST['package_description'] ?? '');
    $weightKg = (float) ($_POST['weight_kg'] ?? 0);
    $dimensions = trim($_POST['dimensions'] ?? '');
    $packagingType = $_POST['packaging_type'] ?? '';
    $shippingMethod = $_POST['shipping_method'] ?? '';
    $landMethod = trim($_POST['land_method'] ?? '') ?: null;
    $serviceType = $_POST['service_type'] ?? '';
    $insured = insurance_enabled() && !empty($_POST['insured']);
    $insuranceValue = (float) ($_POST['insurance_value'] ?? 0);
    $preferredDate = trim($_POST['preferred_date'] ?? '') ?: null;
    $preferredTime = trim($_POST['preferred_time'] ?? '') ?: null;
    $pickupMethod = $_POST['pickup_method'] ?? 'Pickup';
    $honeypot = (string) ($_POST['website'] ?? '');

    if (honeypot_tripped($honeypot)) {
        // Bots that fill in the hidden field never see it fail: show the
        // normal success screen without ever saving a request or sending
        // an email, so nothing tells the bot to adjust its behavior.
        $submitted = true;
        $referenceId = 0;
        $finalEstimate = 0.0;
    } else {
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
        // Sender and receiver are optional, but if an email is typed it has
        // to be a real one.
        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'The sender email does not look valid.';
        if ($receiverEmail !== '' && !filter_var($receiverEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'The receiver email does not look valid.';
    }

    if (!$errors && !$submitted) {
        $finalEstimate = calculate_estimate($rates, $weightKg, $shippingMethod, $serviceType, $insured, $insuranceValue);

        $stmt = db()->prepare('
            INSERT INTO shipment_requests (
              full_name, email, phone,
              sender_name, sender_phone, sender_email, sender_address,
              receiver_name, receiver_phone, receiver_email, receiver_address,
              ship_from, ship_to, package_description,
              weight_kg, dimensions, packaging_type, shipping_method, land_method,
              service_type, insured, insurance_value, preferred_date, preferred_time,
              pickup_method, estimated_cost
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $fullName, $email, $phone ?: null,
            $senderName ?: null, $senderPhone ?: null, $senderEmail ?: null, $senderAddress ?: null,
            $receiverName ?: null, $receiverPhone ?: null, $receiverEmail ?: null, $receiverAddress ?: null,
            $shipFrom, $shipTo, $packageDescription,
            $weightKg, $dimensions ?: null, $packagingType, $shippingMethod, $landMethod,
            $serviceType, $insured ? 1 : 0, $insured ? $insuranceValue : null, $preferredDate, $preferredTime,
            $pickupMethod, $finalEstimate,
        ]);

        $referenceId = (int) db()->lastInsertId();
        $submitted = true;

        // Best-effort: the request is already saved either way, and staff
        // can always see it at /admin/requests.php: a failed email here
        // (unconfigured SMTP, etc.) shouldn't block the confirmation page.
        $refCode = 'REQ-' . str_pad((string) $referenceId, 5, '0', STR_PAD_LEFT);
        $siteName = get_site_name();
        $theme = get_active_palette();
        $ink = h($theme['color_ink']);
        $primary = h($theme['color_primary']);

        send_smtp_mail(
            $email,
            $fullName,
            $siteName . ': shipment request ' . $refCode . ' received',
            '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . $ink . ';">'
                . '<p>Hi ' . h($fullName) . ',</p>'
                . '<p>Thanks for requesting a shipment with ' . h($siteName) . '. We\'ve received your request '
                . '<strong>' . h($refCode) . '</strong> and a member of our team will follow up shortly to confirm details and pricing.</p>'
                . '<p><strong>Estimated cost:</strong> <span style="color:' . $primary . ';font-weight:bold;">$' . number_format($finalEstimate, 2) . '</span> (subject to confirmation)</p>'
                . '<p><strong>From:</strong> ' . h($shipFrom) . '<br><strong>To:</strong> ' . h($shipTo) . '<br><strong>Package:</strong> ' . h($packageDescription) . '</p>'
                . '<p style="color:#6b7280;font-size:12.5px;">If you didn\'t request this, you can ignore this email.</p>'
                . '</div>',
            "Hi {$fullName},\n\nThanks for requesting a shipment with {$siteName}. We've received your request {$refCode} and will follow up shortly.\n\nEstimated cost: \${$finalEstimate} (subject to confirmation)\nFrom: {$shipFrom}\nTo: {$shipTo}\nPackage: {$packageDescription}\n"
        );

        $notifyEmail = get_setting('contact_email', '');
        if (filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
            send_smtp_mail(
                $notifyEmail,
                $siteName,
                'New shipment request ' . $refCode . ' from ' . $fullName,
                '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . $ink . ';">'
                    . '<p><strong>New shipment request ' . h($refCode) . '</strong></p>'
                    . '<p><strong>From:</strong> ' . h($fullName) . ' &lt;' . h($email) . '&gt;' . ($phone ? ' · ' . h($phone) : '') . '</p>'
                    . '<p><strong>Route:</strong> ' . h($shipFrom) . ' &rarr; ' . h($shipTo) . '</p>'
                    . '<p><strong>Package:</strong> ' . h($packageDescription) . ' (' . h((string) $weightKg) . ' kg, ' . h($packagingType) . ')</p>'
                    . '<p><strong>Estimated cost:</strong> $' . number_format($finalEstimate, 2) . '</p>'
                    . '<p><a href="' . h(get_site_url()) . '/admin/requests.php" style="color:' . $primary . ';">View in admin panel</a></p>'
                    . '</div>',
                "New shipment request {$refCode}\n\nFrom: {$fullName} <{$email}>\nRoute: {$shipFrom} -> {$shipTo}\nPackage: {$packageDescription} ({$weightKg} kg, {$packagingType})\nEstimated cost: \${$finalEstimate}\n\nView in admin panel: " . get_site_url() . "/admin/requests.php\n",
                $email,
                $fullName
            );
        }
    }
}

$activeNav = 'request';
$pageTitle = 'Request a Shipment';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    site_copy_tpl('request_title', [
        'classic' => 'Request a Shipment', 'modern' => 'Book your shipment', 'minimal' => 'Book a shipment',
        'bold' => 'Ship it now', 'corporate' => 'Request a Quote', 'dark-header' => 'Start a shipment',
    ]),
    site_copy('request_lead', "Tell us what you're shipping and when. We'll get back to you with a confirmed quote. Prices below are a live estimate."),
    ['key' => 'request', 'photo' => 'forklift', 'crumb' => (string) tpl(['corporate' => 'Request a Quote', 'classic' => 'Ship Now', 'minimal' => 'Book']), 'number' => '03',
     'kicker' => (string) tpl(['modern' => 'Five quick steps', 'bold' => 'Five steps. Done.', 'corporate' => 'Online booking', 'classic' => 'Ship now'])]
) ?>

<section class="section">
  <div class="container" style="max-width:760px;">

    <?php if (!$requestEnabled): ?>
      <div class="form-card" style="max-width:none;text-align:center;">
        <h3 style="margin-top:0;">Online booking is closed right now</h3>
        <p style="color:var(--muted);font-size:15px;line-height:1.7;margin:0 0 20px;">
          We are not taking shipment requests through the website at the
          moment. Please reach us directly and we will be glad to help.
        </p>
        <a href="/contact.php" class="btn btn-primary">Contact Us</a>
      </div>
    <?php elseif ($submitted): ?>
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
          <div class="wizard-step-label">Sender &amp; Receiver</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="3">
          <div class="wizard-step-circle">3</div>
          <div class="wizard-step-label">Package Details</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="4">
          <div class="wizard-step-circle">4</div>
          <div class="wizard-step-label">Service Options</div>
        </div>
        <div class="wizard-step-line"></div>
        <div class="wizard-step-node" data-step="5">
          <div class="wizard-step-circle">5</div>
          <div class="wizard-step-label">Review &amp; Submit</div>
        </div>
      </div>

      <div class="form-card" style="max-width:none;">
        <form method="post" id="request-form">
          <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
          </div>

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
                  <option value="Morning (8am to 12pm)" <?= ($_POST['preferred_time'] ?? '') === 'Morning (8am to 12pm)' ? 'selected' : '' ?>>Morning (8am to 12pm)</option>
                  <option value="Afternoon (12pm to 4pm)" <?= ($_POST['preferred_time'] ?? '') === 'Afternoon (12pm to 4pm)' ? 'selected' : '' ?>>Afternoon (12pm to 4pm)</option>
                  <option value="Evening (4pm to 8pm)" <?= ($_POST['preferred_time'] ?? '') === 'Evening (4pm to 8pm)' ? 'selected' : '' ?>>Evening (4pm to 8pm)</option>
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
              <button type="button" class="btn btn-primary wizard-next">Next: Sender &amp; Receiver &rarr;</button>
            </div>
          </div>

          <!-- Step 2: Sender & Receiver (optional) -->
          <div class="wizard-panel" data-step="2" hidden>
            <h3 style="margin-top:0;">Sender &amp; Receiver <span style="font-weight:normal;color:var(--muted);font-size:14px;">(optional)</span></h3>
            <p style="margin:0 0 16px;color:var(--muted);font-size:13px;">
              Add who the shipment is from and who it is going to. You can
              leave any of these blank and our team will confirm them with you.
            </p>

            <h3 style="margin-top:0;">Sender</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Sender Name</label>
                <input type="text" id="sender_name" name="sender_name" value="<?= h($_POST['sender_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Sender Phone</label>
                <input type="text" id="sender_phone" name="sender_phone" value="<?= h($_POST['sender_phone'] ?? '') ?>" placeholder="e.g. +1 800 555 0199">
              </div>
            </div>
            <div class="form-group">
              <label>Sender Email</label>
              <input type="email" id="sender_email" name="sender_email" value="<?= h($_POST['sender_email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Sender Address</label>
              <input type="text" id="sender_address" name="sender_address" value="<?= h($_POST['sender_address'] ?? '') ?>" placeholder="Street, city, country">
            </div>

            <h3>Receiver</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Receiver Name</label>
                <input type="text" id="receiver_name" name="receiver_name" value="<?= h($_POST['receiver_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Receiver Phone</label>
                <input type="text" id="receiver_phone" name="receiver_phone" value="<?= h($_POST['receiver_phone'] ?? '') ?>" placeholder="e.g. +44 20 7946 0958">
              </div>
            </div>
            <div class="form-group">
              <label>Receiver Email</label>
              <input type="email" id="receiver_email" name="receiver_email" value="<?= h($_POST['receiver_email'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Receiver Address</label>
              <input type="text" id="receiver_address" name="receiver_address" value="<?= h($_POST['receiver_address'] ?? '') ?>" placeholder="Street, city, country">
            </div>

            <div class="wizard-nav">
              <button type="button" class="btn btn-outline wizard-back">&larr; Back</button>
              <button type="button" class="btn btn-primary wizard-next">Next: Package Details &rarr;</button>
            </div>
          </div>

          <!-- Step 3: Package Details -->
          <div class="wizard-panel" data-step="3" hidden>
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

          <!-- Step 4: Service Options -->
          <div class="wizard-panel" data-step="4" hidden>
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

            <?php if (insurance_enabled()): ?>
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
            <?php endif; ?>

            <div class="wizard-nav">
              <button type="button" class="btn btn-outline wizard-back">&larr; Back</button>
              <button type="button" class="btn btn-primary wizard-next">Next: Review &amp; Submit &rarr;</button>
            </div>
          </div>

          <!-- Step 5: Review & Submit -->
          <div class="wizard-panel" data-step="5" hidden>
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
  window.SHIPPING_RATES = <?= json_encode($rates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= h(asset_url('/assets/js/calculator.js')) ?>"></script>
<script src="<?= h(asset_url('/assets/js/wizard.js')) ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
