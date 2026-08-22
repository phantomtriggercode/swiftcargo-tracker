<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$fields = [
    'rate_base_fee' => 'Base Fee (USD, flat per shipment)',
    'rate_price_per_kg' => 'Price per kg (USD)',
    'rate_air_multiplier' => 'Air Shipping Multiplier',
    'rate_sea_multiplier' => 'Sea Shipping Multiplier',
    'rate_land_multiplier' => 'Land Shipping Multiplier',
    'rate_express_multiplier' => 'Express Service Multiplier',
    'rate_insurance_percent' => 'Insurance Rate (% of declared value)',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $key => $label) {
        $value = trim($_POST[$key] ?? '');
        if (!is_numeric($value) || (float) $value < 0) {
            $errors[] = "$label must be a positive number.";
        }
    }
    if (!$errors) {
        foreach ($fields as $key => $label) {
            set_setting($key, trim($_POST[$key]));
        }
        flash_set('success', 'Calculator rates updated.');
        redirect('/admin/rates.php');
    }
}

$activeAdminNav = 'rates';
$pageTitle = 'Calculator Rates';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Shipping Calculator Rates</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:560px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    These rates drive the live cost estimate on the public
    <a href="/request-shipment.php" target="_blank" style="color:var(--brand-red);">Request a Shipment</a> page:
    <code>estimate = (base fee + price/kg &times; weight) &times; method multiplier &times; service multiplier</code>,
    plus insurance (% of declared value) if selected.
  </p>
  <form method="post">
    <?php foreach ($fields as $key => $label): ?>
      <div class="form-group">
        <label><?= h($label) ?></label>
        <input type="text" name="<?= h($key) ?>" value="<?= h(get_setting($key)) ?>" inputmode="decimal" required>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-block">Save Rates</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
