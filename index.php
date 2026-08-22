<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'home';
$pageTitle = 'Global Shipping & Live Package Tracking';
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <h1><?= h(get_setting('home_hero_title', 'Ship anywhere. Track everything. Live.')) ?></h1>
      <p class="lead"><?= h(get_setting('home_hero_lead')) ?></p>
    </div>
    <img src="/assets/images/hero-illustration.svg" alt="Live shipment tracking preview showing a route on a map, a live position marker, and an email alert notification" class="hero-illustration">
  </div>
</section>

<div class="container">
  <div class="track-card">
    <h3>Track your shipment</h3>
    <p>Enter your tracking number to see live location and delivery status.</p>
    <form class="track-form" action="/track.php" method="get">
      <input type="text" name="tn" placeholder="e.g. SC1000000US" required autocomplete="off">
      <button type="submit" class="btn btn-primary">Track</button>
    </form>
    <div class="demo-hint">
      Demo tracking numbers: <code>SC1000000US</code> (en route) &middot; <code>SC1000001US</code> (delivered)
      &middot; Need a quote instead? <a href="/request-shipment.php" style="color:var(--brand-red);">Request a shipment</a>
    </div>
  </div>
</div>

<section class="stats-strip">
  <div class="container">
    <div class="grid-4">
      <div><div class="stat-num"><?= h(get_setting('stat_countries', '195+')) ?></div><div class="stat-label">Countries &amp; territories served</div></div>
      <div><div class="stat-num"><?= h(get_setting('stat_ontime', '98.6%')) ?></div><div class="stat-label">On-time delivery rate</div></div>
      <div><div class="stat-num"><?= h(get_setting('stat_support', '24/7')) ?></div><div class="stat-label">Live tracking &amp; support</div></div>
      <div><div class="stat-num"><?= h(get_setting('stat_delivered', '1.2M+')) ?></div><div class="stat-label">Packages delivered</div></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Why SwiftCargo</div>
      <h2>Built for peace of mind</h2>
      <p>Every shipment is monitored end-to-end, with automatic alerts so your receiver is never left guessing.</p>
    </div>
    <div class="grid-3">
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/map-pin.svg" alt="" width="24" height="24"></div>
        <h3>Live Map Tracking</h3>
        <p>Watch your package move across an interactive world map in real time, from pickup to doorstep.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/mail.svg" alt="" width="24" height="24"></div>
        <h3>Automatic Email Alerts</h3>
        <p>The receiver gets an email the instant a shipment status changes — picked up, in transit, out for delivery, delivered.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/clock.svg" alt="" width="24" height="24"></div>
        <h3>Real-Time Status Timeline</h3>
        <p>A full, timestamped history of every checkpoint your package has passed through.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/box.svg" alt="" width="24" height="24"></div>
        <h3>Regular &amp; Express Options</h3>
        <p>Choose the service level that matches your urgency, and ship by air, sea, or land.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/globe.svg" alt="" width="24" height="24"></div>
        <h3>Worldwide Coverage</h3>
        <p>From coast to coast across the U.S. and to <a href="/countries.php" style="color:var(--brand-red);">every country worldwide</a>, we move freight and parcels reliably.</p>
      </div>
      <div class="card">
        <div class="icon"><img src="/assets/images/icons/shield.svg" alt="" width="24" height="24"></div>
        <h3>Secure Handling</h3>
        <p>Every parcel is logged, verified and handled by trained staff at each checkpoint.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-soft">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">How it works</div>
      <h2>Three simple steps</h2>
    </div>
    <div class="grid-3">
      <div class="card">
        <div class="icon">1</div>
        <h3>Book a shipment</h3>
        <p>Our team creates your shipment and issues a unique tracking number.</p>
      </div>
      <div class="card">
        <div class="icon">2</div>
        <h3>We move it</h3>
        <p>Your package travels through our network of hubs — each checkpoint logged live.</p>
      </div>
      <div class="card">
        <div class="icon">3</div>
        <h3>You &amp; the receiver stay informed</h3>
        <p>Every update triggers an instant email, and anyone can watch progress on the live map.</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
