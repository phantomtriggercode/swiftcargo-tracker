<?php
/**
 * Downloadable PDF shipping label (4x6in, standard carrier label size)
 * for a shipment. Same access model as the waybill and the public
 * tracking page — anyone with the tracking number can print it.
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

$barcode = barcode_data_uri($shipment['tracking_number'], 2, 60);
$isExpress = $shipment['service_type'] === 'Express';
$methodLabel = h($shipment['shipping_method']) . ($shipment['land_method'] ? ' / ' . h($shipment['land_method']) : '');

// Colors follow the active site theme (see includes/theme.php) so this
// PDF matches whatever's live at /admin/themes.php instead of staying
// hardcoded to one brand's colors.
$theme = get_active_palette();
$primary = h($theme['color_primary']);
$ink = h($theme['color_ink']);

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 14pt; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: <?= $ink ?>; margin: 0; padding: 0; }

  .top-row table { width: 100%; border-collapse: collapse; }
  .brand-cell { font-size: 13pt; font-weight: bold; vertical-align: middle; }
  .service-badge {
    display: inline-block; padding: 5pt 12pt; border-radius: 3pt;
    font-size: 12pt; font-weight: bold; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5pt;
  }

  .divider { border-top: 2pt solid <?= $ink ?>; margin: 8pt 0; }
  .divider-thin { border-top: 1pt solid #d1d5db; margin: 8pt 0; }

  .section-label { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #6b7280; font-weight: bold; margin-bottom: 3pt; }
  .ship-to-name { font-size: 17pt; font-weight: bold; line-height: 1.25; }
  .ship-to-addr { font-size: 12.5pt; line-height: 1.4; margin-top: 3pt; }

  .from-name { font-size: 10.5pt; font-weight: bold; }
  .from-addr { font-size: 9.5pt; color: #374151; line-height: 1.4; }

  .meta-table { width: 100%; border-collapse: collapse; margin-top: 4pt; }
  .meta-table td { padding: 4pt 0; font-size: 9pt; vertical-align: top; }
  .meta-table .k { color: #6b7280; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.4pt; }
  .meta-table .v { font-weight: bold; font-size: 10pt; }

  .insured-stamp {
    display: inline-block; border: 1.5pt solid <?= $primary ?>; color: <?= $primary ?>;
    font-size: 8pt; font-weight: bold; padding: 3pt 8pt; border-radius: 3pt;
    text-transform: uppercase; letter-spacing: 0.5pt; margin-top: 6pt;
  }

  .barcode-block { text-align: center; margin-top: 10pt; }
  .barcode-block img { height: 60pt; width: 100%; }
  .barcode-block .tn-text { font-size: 13pt; letter-spacing: 2.5pt; font-weight: bold; margin-top: 3pt; }

  .route-line { font-size: 9pt; color: #374151; margin-top: 4pt; }
</style>
</head>
<body>
    <div class="top-row">
      <table>
        <tr>
          <td class="brand-cell">
            <?php if ($logoDataUri): ?><img src="<?= $logoDataUri ?>" style="height:18pt;vertical-align:middle;margin-right:5pt;"><?php endif; ?>
            <?= h($siteName) ?>
          </td>
          <td style="text-align:right;">
            <span class="service-badge" style="background:<?= $isExpress ? $primary : '#374151' ?>;">
              <?= h($shipment['service_type']) ?>
            </span>
          </td>
        </tr>
      </table>
    </div>

    <div class="divider"></div>

    <div class="section-label">Ship To</div>
    <div class="ship-to-name"><?= h($shipment['receiver_name']) ?></div>
    <div class="ship-to-addr"><?= h($shipment['receiver_address']) ?></div>

    <div class="divider-thin"></div>

    <div class="section-label">From</div>
    <div class="from-name"><?= h($shipment['sender_name']) ?></div>
    <div class="from-addr"><?= h($shipment['sender_address']) ?></div>

    <div class="route-line"><strong><?= h($shipment['origin_label']) ?></strong> &rarr; <strong><?= h($shipment['destination_label']) ?></strong></div>

    <table class="meta-table">
      <tr>
        <td style="width:25%;">
          <div class="k">Carrier</div>
          <div class="v"><?= h($shipment['courier_name'] ?: $siteName) ?></div>
        </td>
        <td style="width:25%;">
          <div class="k">Weight</div>
          <div class="v"><?= h((string) $shipment['weight_kg']) ?> kg</div>
        </td>
        <td style="width:25%;">
          <div class="k">Packaging</div>
          <div class="v"><?= h($shipment['packaging_type']) ?></div>
        </td>
        <td style="width:25%;">
          <div class="k">Method</div>
          <div class="v"><?= $methodLabel ?></div>
        </td>
      </tr>
    </table>

    <?php if ($shipment['insured']): ?>
      <div class="insured-stamp">&#10003; Insured Shipment</div>
    <?php endif; ?>

    <div class="barcode-block">
      <img src="<?= $barcode ?>">
      <div class="tn-text"><?= h($shipment['tracking_number']) ?></div>
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
$dompdf->setPaper([0, 0, 288, 432], 'portrait'); // 4in x 6in
$dompdf->render();

$filename = 'Label-' . preg_replace('/[^A-Za-z0-9\-]/', '', $shipment['tracking_number']) . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
