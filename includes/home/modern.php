<?php
/**
 * Modern homepage: soft drifting colour blobs, a gradient headline, a pill
 * tracking box and a stack of floating photo cards with live-status chips;
 * then partners, a bento grid of features, a swipeable carousel of
 * services, a progress-line process, stats tiles, reviews three at a time
 * and a rounded closing panel (in the footer).
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$avg = reviews_visible() ? published_review_average() : null;
$features = site_features();
?>
<section class="hero hero-modern">
  <span class="blob blob-a" aria-hidden="true"></span>
  <span class="blob blob-b" aria-hidden="true"></span>
  <span class="blob blob-c" aria-hidden="true"></span>
  <div class="container hero-modern-grid">
    <div class="hero-modern-copy">
      <span class="pill-badge"><span class="pulse-dot"></span> Every checkpoint, emailed as it happens</span>
      <h1><?= h(site_copy('home_hero_title', 'Shipping, made simple.')) ?></h1>
      <p class="lead"><?= h(site_copy_map_aware('home_hero_lead', 'Book in minutes, follow every move, and let your receiver know the moment it lands. {site} handles the rest, by air, sea and road.', 'Book in minutes, follow every move, and let your receiver know the moment it lands. {site} handles the rest, by air, sea and road.')) ?></p>
      <?= render_track_form('track-form--pill') ?>
      <div class="hero-trust">
        <?php if ($avg !== null): ?>
          <span><?= render_stars($avg) ?> <strong><?= h(number_format($avg, 1)) ?></strong> from <?= published_review_count() ?> reviews</span>
        <?php endif; ?>
        <span><?= ui_icon('globe', 16) ?> <?= h(get_setting('stat_countries', '195+')) ?> countries</span>
        <?php if (insurance_enabled()): ?><span><?= ui_icon('shield', 16) ?> Insurance available</span><?php endif; ?>
      </div>
    </div>
    <div class="hero-modern-art" aria-hidden="true">
      <div class="float-card float-card--a"><?= photo_img('air-load', '', false) ?></div>
      <div class="float-card float-card--b"><?= photo_img('port', '', false) ?></div>
      <div class="float-card float-card--c"><?= photo_img('doorstep', '', false) ?></div>
      <div class="float-chip float-chip--a"><span class="pulse-dot"></span> In transit &middot; next stop logged</div>
      <div class="float-chip float-chip--b"><?= ui_icon('check', 16) ?> Delivered &amp; signed for</div>
    </div>
  </div>
</section>

<?= render_partners_strip('Companies we work with', 'modern') ?>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_features_eyebrow', 'Everything in one place')) ?></div>
      <h2><?= h(site_copy('home_features_title', 'Built for the way you ship today')) ?></h2>
      <p><?= h(site_copy('home_features_lead', 'No phone calls to find out where something is. It is all on one page, and in your inbox.')) ?></p>
    </div>
    <?php
      // Tile shapes chosen so the grid always closes up with no gap:
      // six features fill nine cells as wide + tall + wide; with five
      // (the map card dropped) the last one runs the full width.
      $shapes = count($features) >= 6
          ? [0 => 'wide', 1 => 'tall', 4 => 'wide']
          : [0 => 'wide', 1 => 'tall', 4 => 'full'];
    ?>
    <div class="bento">
      <?php foreach ($features as $i => $f): ?>
        <div class="bento-tile<?= isset($shapes[$i]) ? ' bento-tile--' . $shapes[$i] : '' ?>">
          <?php if ($i === 0): ?><div class="bento-photo"><?= photo_img('network') ?></div><?php endif; ?>
          <?php if ($i === 1): ?><div class="bento-photo"><?= photo_img('packing') ?></div><?php endif; ?>
          <div class="bento-body">
            <span class="bento-icon"><?= feature_icon($f['icon']) ?></span>
            <h3><?= h($f['title']) ?></h3>
            <p><?= h($f['desc']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head section-head--split">
      <div>
        <div class="eyebrow">What we move</div>
        <h2>Pick the route that fits</h2>
      </div>
      <a href="/services.php" class="btn btn-outline">All services</a>
    </div>
    <div class="carousel mode-carousel" data-carousel data-autoplay="4500" aria-roledescription="carousel" aria-label="Our services">
      <div class="carousel-viewport"><div class="carousel-track">
        <?php foreach (site_modes() as $i => $m): ?>
          <div class="carousel-slide" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count(site_modes()) ?>">
            <a class="glass-card" href="/services.php">
              <div class="glass-card-media"><?= photo_img($m['photo']) ?></div>
              <div class="glass-card-body"><h3><?= h($m['title']) ?></h3><p><?= h($m['desc']) ?></p></div>
            </a>
          </div>
        <?php endforeach; ?>
      </div></div>
      <div class="carousel-controls">
        <button type="button" class="carousel-btn carousel-prev" aria-label="Previous"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <div class="carousel-dots" role="tablist" aria-label="Choose a service"></div>
        <button type="button" class="carousel-btn carousel-pause" aria-label="Pause" aria-pressed="false"><svg class="i-pause" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg><svg class="i-play" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg></button>
        <button type="button" class="carousel-btn carousel-next" aria-label="Next"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
      </div>
    </div>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_steps_eyebrow', 'How it works')) ?></div>
      <h2><?= h(site_copy('home_steps_title', 'From booking to doorstep')) ?></h2>
    </div>
    <ol class="progress-steps">
      <?php foreach (site_steps() as $i => $s): ?>
        <li><span class="progress-dot"><?= $i + 1 ?></span><h3><?= h($s['title']) ?></h3><p><?= h($s['desc']) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container stat-tiles">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label, 'stat stat-tile'); ?>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => 'Loved by shippers', 'title' => 'Real words from real customers', 'more' => 'See every review'], 'modern') ?>
