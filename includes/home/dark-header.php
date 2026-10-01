<?php
/**
 * Dark homepage: a night-time hero with a moving grid and a glow, a
 * headline whose last line cycles through air, sea and road, a glowing
 * tracking box, and two rows of photos sliding past in opposite
 * directions; then live-style figures, feature cards lit by a spotlight
 * that follows the pointer, the statuses every shipment goes through,
 * photo tiles, reviews and partners.
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$rowA = '';
foreach (['port', 'air-load', 'truck', 'scan', 'ship', 'crane'] as $k) {
    $rowA .= '<figure class="film-frame">' . photo_img($k, '', false) . '</figure>';
}
$rowB = '';
foreach (['doorstep', 'customs', 'forklift', 'van', 'network', 'supervisor'] as $k) {
    $rowB .= '<figure class="film-frame">' . photo_img($k, '', false) . '</figure>';
}
// The statuses this site actually uses, in order, for the timeline.
// The usual path from booking to delivery, using the statuses this site
// actually has (any that were renamed or removed are simply skipped).
$allStatuses = get_shipment_status_names();
$statusNames = array_values(array_filter(
    ['Pending', 'Picked Up', 'In Transit', 'Customs Clearance', 'Out for Delivery', 'Delivered'],
    static fn($s) => in_array($s, $allStatuses, true)
));
if (count($statusNames) < 3) {
    $statusNames = array_slice($allStatuses, 0, 6);
}
?>
<section class="hero hero-dark">
  <span class="grid-bg" aria-hidden="true"></span>
  <span class="hero-glow" aria-hidden="true"></span>
  <div class="container hero-dark-grid">
    <div class="hero-dark-copy">
      <div class="hero-kicker"><span class="pulse-dot"></span> network online</div>
      <h1><?= h(site_copy('home_hero_title', 'Tracking that never sleeps.')) ?></h1>
      <p class="rotate-line">By <span class="rotate-words" data-rotate-words="air,sea,road,rail,van">air</span>, logged at every stop.</p>
      <p class="lead"><?= h(site_copy_map_aware('home_hero_lead', 'Every scan, every handover and every delay is recorded the moment it happens, and your receiver hears about it by email.', 'Every scan, every handover and every delay is recorded the moment it happens, and your receiver hears about it by email.')) ?></p>
      <?= render_track_form('track-form--glow') ?>
    </div>
    <div class="filmstrip" aria-hidden="true">
      <div class="marquee" data-marquee><div class="marquee-track"><div class="marquee-group"><?= $rowA ?></div><div class="marquee-group"><?= $rowA ?></div></div></div>
      <div class="marquee marquee--reverse" data-marquee><div class="marquee-track"><div class="marquee-group"><?= $rowB ?></div><div class="marquee-group"><?= $rowB ?></div></div></div>
    </div>
  </div>
</section>

<section class="net-stats" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label, 'stat net-stat'); ?>
  </div>
</section>

<section class="section section-dark" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">// <?= h(mb_strtolower(site_copy('home_features_eyebrow', 'why {site}'))) ?></div>
      <h2><?= h(site_copy('home_features_title', 'Your cargo, our obsession.')) ?></h2>
      <p><?= h(site_copy('home_features_lead', 'Nothing moves without being logged, and nothing is logged without you knowing.')) ?></p>
    </div>
    <div class="grid-3 spot-grid">
      <?php foreach (site_features() as $f): ?>
        <div class="spot-card" data-spotlight>
          <span class="spot-icon"><?= feature_icon($f['icon']) ?></span>
          <h3><?= h($f['title']) ?></h3>
          <p><?= h($f['desc']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container timeline-split">
    <div>
      <div class="eyebrow">// every checkpoint</div>
      <h2>What you see on every shipment</h2>
      <p class="intro-lead">Each stage is stamped with the time and place as it happens, so the tracking page always tells the full story.</p>
      <a href="/track.php" class="btn btn-primary">Track a shipment</a>
    </div>
    <ol class="glow-timeline">
      <?php foreach ($statusNames as $i => $name): ?>
        <li style="--i:<?= $i ?>"><span class="gt-dot"></span><span class="gt-label"><?= h($name) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section section-dark" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">// <?= h(mb_strtolower(site_copy('home_operate_eyebrow', 'on the ground'))) ?></div>
      <h2><?= h(site_copy('home_operate_title', 'People and machines, in sync.')) ?></h2>
    </div>
    <div class="neon-tiles">
      <?php foreach (site_operate_rows() as $row): ?>
        <figure class="neon-tile">
          <img src="<?= h($row['image']) ?>" alt="<?= h($row['alt']) ?>" loading="lazy">
          <figcaption><strong><?= h($row['title']) ?></strong><span><?= h($row['desc']) ?></span></figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => '// reviews', 'title' => 'Signal from our customers', 'more' => 'All reviews'], 'dark') ?>

<?= render_partners_strip('Connected with', 'dark') ?>
