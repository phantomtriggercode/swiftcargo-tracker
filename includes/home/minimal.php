<?php
/**
 * Minimal homepage: a large centred serif headline with an underline that
 * draws itself in, a single-line tracking field, a slowly moving strip of
 * black-and-white photos that take colour on hover, then a numbered list
 * of services, one large photograph, plain figures, a single quote at a
 * time and a quiet list of partners.
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$title = site_copy('home_hero_title', 'Simply delivered.');
// The last word of the headline gets the hand-drawn underline.
$titleWords = preg_split('/\s+/', trim($title));
$lastWord = array_pop($titleWords);
$stripPhotos = ['port', 'boxes', 'air-load', 'truck', 'customs', 'van', 'ship', 'packing', 'crane', 'doorstep'];
$strip = '';
foreach ($stripPhotos as $key) {
    $strip .= '<figure class="strip-photo">' . photo_img($key, '', false) . '</figure>';
}
?>
<section class="hero hero-minimal">
  <div class="container">
    <div class="hero-minimal-index">Freight &amp; parcels &mdash; by air, sea and road</div>
    <h1><?= h(implode(' ', $titleWords)) ?> <span class="draw-underline"><?= h($lastWord) ?></span></h1>
    <p class="lead"><?= h(site_copy_map_aware('home_hero_lead', 'Less waiting, more knowing. Send it with {site} and follow every step without having to ask.', 'Less waiting, more knowing. Send it with {site} and follow every step without having to ask.')) ?></p>
    <?= render_track_form('track-form--line') ?>
  </div>
  <div class="photo-strip marquee marquee--slow" data-marquee aria-hidden="true">
    <div class="marquee-track">
      <div class="marquee-group"><?= $strip ?></div>
      <div class="marquee-group"><?= $strip ?></div>
    </div>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="split-head">
      <div class="eyebrow"><?= h(site_copy('home_features_eyebrow', 'What we do')) ?></div>
      <h2><?= h(site_copy('home_features_title', 'Every way to move things, done well.')) ?></h2>
    </div>
    <ol class="numbered-list">
      <?php foreach (array_slice(site_modes(), 0, 5) as $i => $m): ?>
        <li>
          <a href="/services.php">
            <span class="nl-num"><?= sprintf('%02d', $i + 1) ?></span>
            <span class="nl-title"><?= h($m['title']) ?></span>
            <span class="nl-desc"><?= h($m['desc']) ?></span>
            <span class="nl-thumb" aria-hidden="true"><?= photo_img($m['photo']) ?></span>
            <span class="nl-arrow" aria-hidden="true"><?= ui_icon('arrow', 20) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="statement" data-reveal>
  <div class="container">
    <figure class="statement-figure img-zoom"><?= photo_img('yard') ?></figure>
    <div class="statement-text">
      <p class="statement-quote"><?= h(site_copy('home_features_lead', 'Every shipment is watched from the first scan to the last signature, and nothing about it is ever a mystery.')) ?></p>
      <div class="plain-features">
        <?php foreach (site_features() as $f): ?>
          <div><h3><?= h($f['title']) ?></h3><p><?= h($f['desc']) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section plain-stats" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label); ?>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="split-head">
      <div class="eyebrow"><?= h(site_copy('home_steps_eyebrow', 'The process')) ?></div>
      <h2><?= h(site_copy('home_steps_title', 'Three quiet steps.')) ?></h2>
    </div>
    <div class="grid-3 plain-steps">
      <?php foreach (site_steps() as $i => $s): ?>
        <div><span class="nl-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= h($s['title']) ?></h3><p><?= h($s['desc']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => 'Kind words', 'title' => 'In their own words', 'more' => 'Read them all'], 'minimal') ?>

<?= render_partners_strip('In good company', 'minimal') ?>
