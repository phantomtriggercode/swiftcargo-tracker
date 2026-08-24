<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$tn = trim($_GET['tn'] ?? '');
$shipment = null;
$events = [];
$notFound = false;

if ($tn !== '') {
    $shipment = get_shipment_by_tracking($tn);
    if ($shipment) {
        $events = get_shipment_events((int) $shipment['id']);
    } else {
        $notFound = true;
    }
}

$activeNav = 'track';
$pageTitle = 'Track Shipment';
include __DIR__ . '/includes/header.php';
?>

<!-- Leaflet is served from this site, not a CDN. The live map is the most
     important thing on the page, so it must not depend on a third-party
     host that can be blocked by a firewall or ad blocker, blocked in a
     whole country, or simply have an outage. See assets/vendor/leaflet/. -->
<link rel="stylesheet" href="<?= h(asset_url('/assets/vendor/leaflet/leaflet.css')) ?>">

<section class="track-hero">
  <div class="container">
    <h3 style="margin:0 0 4px;">Track your shipment</h3>
    <p style="color:#d1d5db;margin:0 0 16px;font-size:14px;">Enter a tracking number to see live location and full status history.</p>
    <form class="track-form" action="/track.php" method="get">
      <input type="text" name="tn" value="<?= h($tn) ?>" placeholder="Enter your tracking number" required autocomplete="off">
      <button type="submit" class="btn btn-yellow">Track</button>
    </form>
  </div>
</section>

<div class="container tracking-result">

  <?php if ($notFound): ?>
    <div class="alert alert-error">
      No shipment found for tracking number "<?= h($tn) ?>". Please check the number and try again.
    </div>
  <?php elseif ($shipment): ?>

    <div class="tn-heading">
      <div>
        <h2>Shipment <?= h($shipment['tracking_number']) ?></h2>
        <div class="tn-code"><?= h($shipment['origin_label']) ?> &rarr; <?= h($shipment['destination_label']) ?></div>
      </div>
      <span class="badge <?= status_badge_class($shipment['status']) ?>" id="status-badge"><?= h($shipment['status']) ?></span>
    </div>

    <div class="doc-actions">
      <a href="/documents/waybill.php?tn=<?= urlencode($shipment['tracking_number']) ?>" target="_blank" class="btn btn-outline btn-sm">
        <img src="/assets/images/icons/box.svg" alt="" width="14" height="14" style="vertical-align:-2px;"> Print Waybill (PDF)
      </a>
      <a href="/documents/label.php?tn=<?= urlencode($shipment['tracking_number']) ?>" target="_blank" class="btn btn-outline btn-sm">
        <img src="/assets/images/icons/box.svg" alt="" width="14" height="14" style="vertical-align:-2px;"> Print Label (PDF)
      </a>
    </div>

    <div class="tracking-layout">
      <div>
        <div id="map">
          <noscript>
            <div class="map-unavailable-inner" style="padding:28px 22px;text-align:center;">
              <strong>The live map needs JavaScript.</strong>
              <span>Turn JavaScript on to see the map. The full status history and every
              shipment detail below are already shown and up to date without it.</span>
            </div>
          </noscript>
        </div>
        <div class="map-live-tag"><span class="dot"></span> Live position — auto-refreshes every 15s</div>
        <div class="map-legend">
          <span><span class="swatch swatch-origin"></span> Origin</span>
          <span><span class="swatch swatch-history"></span> Past location (footprint)</span>
          <span><span class="swatch swatch-current"></span> Current position</span>
          <span><span class="swatch swatch-dest"></span> Destination</span>
          <span><span class="swatch-line swatch-line-traveled"></span> Traveled</span>
          <span><span class="swatch-line swatch-line-remaining"></span> Remaining</span>
        </div>

        <div class="shipment-meta">
          <div class="meta-box">
            <div class="meta-label">Receiver</div>
            <div class="meta-value"><?= h($shipment['receiver_name']) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Carrier</div>
            <div class="meta-value"><?= h($shipment['courier_name'] ?: get_site_name()) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Service</div>
            <div class="meta-value"><?= h($shipment['service_type']) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Shipping Method</div>
            <div class="meta-value">
              <?= h($shipment['shipping_method']) ?><?= $shipment['land_method'] ? ' — ' . h($shipment['land_method']) : '' ?>
            </div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Package</div>
            <div class="meta-value"><?= h($shipment['package_description']) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Packaging</div>
            <div class="meta-value"><?= h($shipment['packaging_type']) ?><?= $shipment['dimensions'] ? ' · ' . h($shipment['dimensions']) : '' ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Weight</div>
            <div class="meta-value"><?= h((string) $shipment['weight_kg']) ?> kg</div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Insurance</div>
            <div class="meta-value">
              <?php if ($shipment['insured']): ?>
                Insured <?php if ($shipment['insurance_value']): ?>($<?= number_format((float) $shipment['insurance_value'], 2) ?>)<?php endif; ?>
              <?php else: ?>
                Not insured
              <?php endif; ?>
            </div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Estimated Delivery</div>
            <div class="meta-value"><?= $shipment['estimated_delivery'] ? h(date('M j, Y', strtotime($shipment['estimated_delivery']))) : 'TBD' ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Payment</div>
            <div class="meta-value"><?= h(payment_status_label($shipment)) ?></div>
          </div>
        </div>
      </div>

      <div class="timeline" id="timeline">
        <h3>Status Timeline</h3>
        <?php
        $reversed = array_reverse($events);
        foreach ($reversed as $i => $ev):
        ?>
          <div class="timeline-item <?= $i === 0 ? '' : 'past' ?>">
            <div class="timeline-dot"></div>
            <div class="timeline-body">
              <div class="t-status"><?= h($ev['status']) ?></div>
              <div class="t-loc"><?= h($ev['location_label']) ?></div>
              <?php if (!empty($ev['note'])): ?>
                <div class="t-note"><?= h($ev['note']) ?></div>
              <?php endif; ?>
              <div class="t-time"><?= h(date('M j, Y g:i A', strtotime($ev['event_time']))) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <script src="<?= h(asset_url('/assets/vendor/leaflet/leaflet.js')) ?>"></script>
    <script>
      window.SHIPMENT_INIT = <?= json_encode([
          'tracking_number' => $shipment['tracking_number'],
          'status' => $shipment['status'],
          'current_lat' => (float) $shipment['current_lat'],
          'current_lng' => (float) $shipment['current_lng'],
          'current_location_label' => $events ? end($events)['location_label'] : $shipment['origin_label'],
          'origin_label' => $shipment['origin_label'],
          'origin_lat' => (float) $shipment['origin_lat'],
          'origin_lng' => (float) $shipment['origin_lng'],
          'destination_label' => $shipment['destination_label'],
          'destination_lat' => (float) $shipment['destination_lat'],
          'destination_lng' => (float) $shipment['destination_lng'],
          'events' => array_map(static function (array $e) {
              return [
                  'id' => (int) $e['id'],
                  'status' => $e['status'],
                  'location_label' => $e['location_label'],
                  'lat' => (float) $e['lat'],
                  'lng' => (float) $e['lng'],
                  'note' => $e['note'],
                  'event_time' => $e['event_time'],
              ];
          }, $events),
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="<?= h(asset_url('/assets/js/map.js')) ?>"></script>
    <script>
      /* Last-resort safety net for the live map, deliberately inline so it
         cannot itself be blocked or fail to download. map.js handles every
         failure it can see, but it can't report a problem if map.js is the
         thing that never loaded (bad upload, aggressive blocker). A few
         seconds after load, if the map area is still empty and no message
         has been shown, explain it rather than leaving a blank grey box —
         and drop the "auto-refreshes" line, since nothing is refreshing. */
      (function () {
        window.setTimeout(function () {
          var el = document.getElementById('map');
          if (!el) return;
          if (el.querySelector('.leaflet-map-pane') || el.querySelector('.map-unavailable-inner')) return;

          el.className += ' map-unavailable';
          var box = document.createElement('div');
          box.className = 'map-unavailable-inner';
          var t = document.createElement('strong');
          t.textContent = 'The map could not be loaded.';
          var b = document.createElement('span');
          b.textContent = 'Something on this network or browser stopped it from loading. '
            + 'All tracking details and the full status history below are still up to date.';
          box.appendChild(t);
          box.appendChild(b);
          el.appendChild(box);

          var tag = document.querySelector('.map-live-tag');
          if (tag) tag.style.display = 'none';
          var legend = document.querySelector('.map-legend');
          if (legend) legend.style.display = 'none';
        }, 5000);
      })();
    </script>

  <?php else: ?>
    <div class="alert alert-info">Enter a tracking number above to see live status and map location.</div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
