<?php
/**
 * Classic homepage: a full-width photo slider behind the headline, the
 * tracking box overlapping its bottom edge, then stats, reasons to choose
 * the company, how it operates, the ways it ships, steps, reviews,
 * partners and a photo gallery.
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$heroSlides = [
    get_site_image('home_hero_image', site_photo('semi-sunset')['src']),
    site_photo('yard')['src'],
    site_photo('stacking')['src'],
];
?>
<section class="hero hero-classic">
  <div class="hero-slides" data-fade-slider data-interval="6000" aria-hidden="true">
    <?php foreach ($heroSlides as $i => $src): ?>
      <div class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" style="background-image:url('<?= h($src) ?>')"></div>
    <?php endforeach; ?>
  </div>
  <div class="hero-shade" aria-hidden="true"></div>
  <div class="container hero-classic-inner">
    <div class="hero-kicker"><?= ui_icon('globe', 16) ?> Freight &amp; parcel delivery, worldwide</div>
    <h1><?= h(site_copy('home_hero_title', 'Trusted freight, delivered door to door.')) ?></h1>
    <p class="lead"><?= h(site_copy_map_aware('home_hero_lead', '{site} moves parcels and freight by air, sea and road, and keeps you and your receiver informed at every checkpoint.', '{site} moves parcels and freight by air, sea and road, and keeps you and your receiver informed at every checkpoint.')) ?></p>
    <div class="hero-actions">
      <a href="/track.php" class="btn btn-primary btn-lg">Track a shipment</a>
      <a href="<?= h(quote_url()) ?>" class="btn btn-ghost-light btn-lg"><?= request_shipment_enabled() ? 'Get a quote' : 'Contact us' ?></a>
    </div>
    <div class="hero-dots" data-fade-dots aria-hidden="true"><?php foreach ($heroSlides as $i => $_): ?><span class="<?= $i === 0 ? 'is-active' : '' ?>"></span><?php endforeach; ?></div>
  </div>
</section>

<div class="container">
  <div class="track-card track-card--classic">
    <div>
      <h3><?= h(site_copy('home_track_title', 'Where is my shipment?')) ?></h3>
      <p><?= h(site_copy_map_aware('home_track_lead', 'Enter your tracking number for its live status and full delivery history.', 'Enter your tracking number for its current status and full delivery history.')) ?></p>
    </div>
    <?= render_track_form('track-form--classic') ?>
  </div>
</div>

<section class="stats-strip" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label); ?>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_features_eyebrow', 'Why choose {site}')) ?></div>
      <h2><?= h(site_copy('home_features_title', 'Shipping you never have to chase')) ?></h2>
      <p><?= h(site_copy('home_features_lead', 'Every shipment is watched from pickup to signature, and your receiver hears about each step the moment it happens.')) ?></p>
    </div>
    <div class="grid-3 feature-grid">
      <?php foreach (site_features() as $f): ?>
        <div class="card feature-card">
          <div class="icon"><?= feature_icon($f['icon']) ?></div>
          <h3><?= h($f['title']) ?></h3>
          <p><?= h($f['desc']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">Air, sea and road</div>
      <h2>One partner for every way of shipping</h2>
    </div>
    <div class="grid-3 mode-grid">
      <?php foreach (array_slice(site_modes(), 0, 3) as $m): ?>
        <a class="mode-card" href="/services.php">
          <div class="mode-card-media"><?= photo_img($m['photo']) ?></div>
          <div class="mode-card-body">
            <h3><?= h($m['title']) ?></h3>
            <p><?= h($m['desc']) ?></p>
            <span class="more-link">Learn more <?= ui_icon('arrow', 16) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_operate_eyebrow', 'How we work')) ?></div>
      <h2><?= h(site_copy('home_operate_title', 'Trained people, a dependable fleet')) ?></h2>
      <p><?= h(site_copy('home_operate_lead', 'From the warehouse floor to the front door, the same care at every handover.')) ?></p>
    </div>
    <?php foreach (site_operate_rows() as $i => $row): ?>
      <div class="feature-row<?= $i % 2 ? ' reverse' : '' ?>">
        <div class="img-zoom"><img src="<?= h($row['image']) ?>" alt="<?= h($row['alt']) ?>" loading="lazy"></div>
        <div>
          <h3><?= h($row['title']) ?></h3>
          <p><?= h($row['desc']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_steps_eyebrow', 'Getting started')) ?></div>
      <h2><?= h(site_copy('home_steps_title', 'Three steps to delivered')) ?></h2>
    </div>
    <div class="steps-line grid-3">
      <?php foreach (site_steps() as $i => $s): ?>
        <div class="step">
          <div class="step-num"><?= $i + 1 ?></div>
          <h3><?= h($s['title']) ?></h3>
          <p><?= h($s['desc']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => 'Customer reviews', 'title' => 'What our customers say', 'more' => 'Read all reviews'], 'classic') ?>

<?= render_partners_strip('Working alongside') ?>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_gallery_eyebrow', 'On the job')) ?></div>
      <h2><?= h(site_copy('home_gallery_title', 'Our teams at work')) ?></h2>
    </div>
    <div class="gallery-mosaic">
      <?php foreach (['yard', 'scan', 'air-ground', 'van', 'forklift', 'inspector'] as $key): $p = site_photo($key); ?>
        <figure class="img-zoom"><img src="<?= h($p['src']) ?>" alt="<?= h($p['alt']) ?>" loading="lazy"><figcaption><?= h($p['alt']) ?></figcaption></figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
