<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'services';
$pageTitle = 'Services';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;">Our Services</h1>
    <p class="lead">Flexible shipping options for every kind of package, budget and deadline.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid-3">
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/rocket.svg" alt="" width="24" height="24"></div>
        <h3>Priority</h3>
        <p>Our fastest service for time-critical shipments, with premium handling and priority routing at every hub.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/plane.svg" alt="" width="24" height="24"></div>
        <h3>Express</h3>
        <p>Reliable, fast international delivery — ideal for business documents and time-sensitive parcels.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/truck.svg" alt="" width="24" height="24"></div>
        <h3>Standard</h3>
        <p>Cost-effective shipping for everyday parcels, with the same live tracking and email alerts.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Every plan includes</div>
      <h2>Full visibility, no extra cost</h2>
    </div>
    <div class="grid-4">
      <div class="card"><div class="icon"><img src="/assets/images/icons/map-pin.svg" alt="" width="24" height="24"></div><h3>Live Map Tracking</h3><p>Free on every shipment, every service tier.</p></div>
      <div class="card"><div class="icon"><img src="/assets/images/icons/mail.svg" alt="" width="24" height="24"></div><h3>Email Alerts</h3><p>Automatic updates sent to your receiver's inbox.</p></div>
      <div class="card"><div class="icon"><img src="/assets/images/icons/clock.svg" alt="" width="24" height="24"></div><h3>Delivery Timeline</h3><p>A timestamped history from pickup to drop-off.</p></div>
      <div class="card"><div class="icon"><img src="/assets/images/icons/shield.svg" alt="" width="24" height="24"></div><h3>24/7 Support</h3><p>Our team is available around the clock.</p></div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
