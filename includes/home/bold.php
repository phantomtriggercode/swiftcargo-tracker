<?php
/**
 * Bold homepage: a diagonal slab with a huge capitalised headline and a
 * collage of tilted photos drifting beside it, a band of giant outlined
 * words scrolling past, oversized counting stats, picture cards that flood
 * with colour on hover, numbered reasons, a slanted "how it works" panel,
 * reviews on the brand colour and the partner strip.
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$words = ['Air', 'Sea', 'Road', 'Customs', 'Warehousing', 'Last mile'];
$band = '';
foreach ($words as $w) {
    $band .= '<span>' . h($w) . '</span><span class="band-sep" aria-hidden="true">/</span>';
}
?>
<section class="hero hero-bold">
  <div class="container hero-bold-grid">
    <div class="hero-bold-copy">
      <div class="hero-kicker">No delays. No excuses.</div>
      <h1><?= h(site_copy('home_hero_title', 'Move the world. Faster.')) ?></h1>
      <p class="lead"><?= h(site_copy_map_aware('home_hero_lead', 'Big loads, tight deadlines, far-off places. {site} gets it there and tells you every step of the way.', 'Big loads, tight deadlines, far-off places. {site} gets it there and tells you every step of the way.')) ?></p>
      <?= render_track_form('track-form--slab') ?>
      <a href="<?= h(quote_url()) ?>" class="btn btn-accent btn-lg hero-bold-cta"><?= request_shipment_enabled() ? 'Ship now' : 'Talk to us' ?> <?= ui_icon('arrow', 18) ?></a>
    </div>
    <div class="hero-bold-collage" aria-hidden="true">
      <div class="tilt-photo tilt-photo--a"><?= photo_img('loader', '', false) ?></div>
      <div class="tilt-photo tilt-photo--b"><?= photo_img('highway', '', false) ?></div>
      <div class="tilt-photo tilt-photo--c"><?= photo_img('air-ground', '', false) ?></div>
    </div>
  </div>
</section>

<div class="word-band marquee" data-marquee aria-hidden="true">
  <div class="marquee-track">
    <div class="marquee-group"><?= $band . $band ?></div>
    <div class="marquee-group"><?= $band . $band ?></div>
  </div>
</div>

<section class="mega-stats" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label); ?>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head section-head--left">
      <div class="eyebrow">What we move</div>
      <h2>Big loads. Tight deadlines.</h2>
    </div>
    <div class="flood-cards">
      <?php foreach (array_slice(site_modes(), 0, 3) as $m): ?>
        <a class="flood-card" href="/services.php">
          <?= photo_img($m['photo']) ?>
          <span class="flood-card-body">
            <span class="flood-card-title"><?= h($m['title']) ?></span>
            <span class="flood-card-desc"><?= h($m['desc']) ?></span>
            <span class="flood-card-more">Learn more <?= ui_icon('arrow', 16) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head section-head--left">
      <div class="eyebrow"><?= h(site_copy('home_features_eyebrow', 'Why {site}')) ?></div>
      <h2><?= h(site_copy('home_features_title', 'Built to deliver. Every time.')) ?></h2>
    </div>
    <div class="grid-3 numbered-blocks">
      <?php foreach (site_features() as $i => $f): ?>
        <div class="numbered-block"><span class="nb-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= h($f['title']) ?></h3><p><?= h($f['desc']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="slant-panel" data-reveal>
  <div class="container">
    <div class="section-head section-head--left">
      <div class="eyebrow"><?= h(site_copy('home_steps_eyebrow', 'How it works')) ?></div>
      <h2><?= h(site_copy('home_steps_title', 'Book it. Move it. Done.')) ?></h2>
    </div>
    <div class="grid-3 big-steps">
      <?php foreach (site_steps() as $i => $s): ?>
        <div><span class="big-step-num"><?= $i + 1 ?></span><h3><?= h($s['title']) ?></h3><p><?= h($s['desc']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => 'Hear it from them', 'title' => 'Customers say it best', 'more' => 'All reviews'], 'bold') ?>

<?= render_partners_strip('We work with', 'bold') ?>
