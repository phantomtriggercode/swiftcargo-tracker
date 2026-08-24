<?php
/**
 * Downloadable PDF air waybill for a shipment. Accessible to anyone who
 * knows the tracking number (same access model as the public tracking
 * page) so both staff and the receiver can print it.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/barcode.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$tn = trim($_GET['tn'] ?? '');
$shipment = $tn !== '' ? get_shipment_by_tracking($tn) : null;

if (!$shipment) {
    http_response_code(404);
    echo 'Shipment not found.';
    exit;
}

$siteName = get_site_name();
$logoUrl = get_logo_url();
$logoPath = $logoUrl ? __DIR__ . '/..' . $logoUrl : __DIR__ . '/../assets/images/logo-mark.svg';
$logoDataUri = null;
if (is_file($logoPath)) {
    $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp',
        'gif' => 'image/gif', default => 'image/jpeg',
    };
    $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
}

$barcode = barcode_data_uri($shipment['tracking_number'], 2, 55);

$methodLabel = h($shipment['shipping_method']) . ($shipment['land_method'] ? ' &mdash; ' . h($shipment['land_method']) : '');
$insuranceLabel = $shipment['insured']
    ? 'Insured' . ($shipment['insurance_value'] ? ' &mdash; $' . number_format((float) $shipment['insurance_value'], 2) . ' declared value' : '')
    : 'Not insured';
$estDelivery = $shipment['estimated_delivery'] ? date('F j, Y', strtotime($shipment['estimated_delivery'])) : 'TBD';
$createdDate = date('F j, Y', strtotime($shipment['created_at']));

// Colors follow the active site theme (see includes/theme.php) so this
// PDF matches whatever's live at /admin/themes.php instead of staying
// hardcoded to one brand's colors.
$theme = get_active_theme();
$primary = h($theme['color_primary']);
$accent = h($theme['color_accent']);
$ink = h($theme['color_ink']);
$border = h($theme['color_border']);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px 34px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: <?= $ink ?>; }
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .header-table td { vertical-align: middle; }
  .brand { font-size: 20px; font-weight: bold; color: <?= $ink ?>; }
  .doc-title { text-align: right; }
  .doc-title .t1 { font-size: 20px; font-weight: bold; color: <?= $primary ?>; letter-spacing: 1px; }
  .doc-title .t2 { font-size: 11px; color: #6b7280; }
  .tn-bar { background: <?= $ink ?>; color: #ffffff; padding: 10px 16px; border-radius: 4px; margin-bottom: 14px; }
  .tn-bar table { width: 100%; }
  .tn-bar .tn-value { font-size: 18px; font-weight: bold; letter-spacing: 1px; }
  .tn-bar .tn-label { font-size: 9px; color: #d1d5db; text-transform: uppercase; letter-spacing: 0.5px; }
  .status-chip { display: inline; background: <?= $accent ?>; color: <?= $ink ?>; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; }

  .addr-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .addr-table td { width: 50%; vertical-align: top; padding: 10px 14px; border: 1px solid <?= $border ?>; }
  .addr-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; font-weight: bold; margin-bottom: 4px; }
  .addr-name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
  .addr-line { font-size: 11.5px; color: #374151; }

  .details-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .details-table th, .details-table td { border: 1px solid <?= $border ?>; padding: 7px 10px; font-size: 11px; text-align: left; }
  .details-table th { background: #f9fafb; color: #6b7280; text-transform: uppercase; font-size: 9px; letter-spacing: 0.5px; width: 33%; }

  .route-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .route-table td { width: 50%; padding: 10px 14px; border: 1px solid <?= $border ?>; }
  .route-dot { color: <?= $primary ?>; font-weight: bold; }

  .barcode-block { text-align: center; margin: 18px 0 10px; }
  .barcode-block img { height: 55px; }
  .barcode-block .tn-text { font-size: 13px; letter-spacing: 3px; font-weight: bold; margin-top: 4px; }

  .footer-note { margin-top: 16px; padding-top: 10px; border-top: 1px solid <?= $border ?>; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

  <table class="header-table">
    <tr>
      <td style="width:60%;">
        <?php if ($logoDataUri): ?><img src="<?= $logoDataUri ?>" style="height:32px;vertical-align:middle;margin-right:8px;"><?php endif; ?>
        <span class="brand"><?= h($siteName) ?></span>
      </td>
      <td class="doc-title">
        <div class="t1">SHIPPING WAYBILL</div>
        <div class="t2">Issued <?= h($createdDate) ?></div>
      </td>
    </tr>
  </table>

  <div class="tn-bar">
    <table>
      <tr>
        <td>
          <div class="tn-label">Tracking Number</div>
          <div class="tn-value"><?= h($shipment['tracking_number']) ?></div>
        </td>
        <td style="text-align:right;">
          <span class="status-chip"><?= h($shipment['status']) ?></span>
        </td>
      </tr>
    </table>
  </div>

  <table class="route-table">
    <tr>
      <td><span class="route-dot">&#9679;</span> <strong>Origin:</strong> <?= h($shipment['origin_label']) ?></td>
      <td><span class="route-dot">&#9679;</span> <strong>Destination:</strong> <?= h($shipment['destination_label']) ?></td>
    </tr>
  </table>

  <table class="addr-table">
    <tr>
      <td>
        <div class="addr-label">Ship From</div>
        <div class="addr-name"><?= h($shipment['sender_name']) ?></div>
        <div class="addr-line"><?= h($shipment['sender_address']) ?></div>
      </td>
      <td>
        <div class="addr-label">Ship To</div>
        <div class="addr-name"><?= h($shipment['receiver_name']) ?></div>
        <div class="addr-line"><?= h($shipment['receiver_address']) ?></div>
        <div class="addr-line"><?= h($shipment['receiver_email']) ?></div>
      </td>
    </tr>
  </table>

  <table class="details-table">
    <tr><th>Carrier</th><td><?= h($shipment['courier_name'] ?: $siteName) ?></td><th>Service Type</th><td><?= h($shipment['service_type']) ?></td></tr>
    <tr><th>Shipping Method</th><td colspan="3"><?= $methodLabel ?></td></tr>
    <tr><th>Package</th><td><?= h($shipment['package_description']) ?></td><th>Packaging Type</th><td><?= h($shipment['packaging_type']) ?></td></tr>
    <tr><th>Weight</th><td><?= h((string) $shipment['weight_kg']) ?> kg</td><th>Dimensions</th><td><?= $shipment['dimensions'] ? h($shipment['dimensions']) : '&mdash;' ?></td></tr>
    <tr><th>Insurance</th><td><?= $insuranceLabel ?></td><th>Estimated Delivery</th><td><?= h($estDelivery) ?></td></tr>
    <tr><th>Payment</th><td colspan="3"><?= h(payment_status_label($shipment)) ?></td></tr>
  </table>

  <div class="barcode-block">
    <img src="<?= $barcode ?>">
    <div class="tn-text"><?= h($shipment['tracking_number']) ?></div>
  </div>

  <div class="footer-note">
    This waybill is a record of shipment details at time of booking. Scan or enter the tracking number above at
    <?= h(get_site_url()) ?>/track.php to see live status. Generated <?= h(date('F j, Y g:i A')) ?>.
  </div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

$filename = 'Waybill-' . preg_replace('/[^A-Za-z0-9\-]/', '', $shipment['tracking_number']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
