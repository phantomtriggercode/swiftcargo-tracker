<?php
/**
 * Corporate homepage: a full-width slider of three photographs, each with
 * its own message panel, arrows and dots; a tabbed Track / Quote / Contact
 * widget overlapping it; a company introduction with a checklist; service
 * cards with photos; figures over a fixed photograph; a four-step process;
 * reviews two at a time; partners.
 * Included by index.php; never requested on its own.
 */
if (!function_exists('site_template')) { http_response_code(404); exit; }
$slides = [
    ['photo' => get_site_image('home_hero_image', site_photo('semi-mountains')['src']),
     'kicker' => 'Integrated logistics',
     'title' => site_copy('home_hero_title', 'Global logistics solutions you can rely on'),
     'lead' => site_copy_map_aware('home_hero_lead', '{site} plans, moves and tracks freight and parcels across borders, with one point of contact from booking to delivery.', '{site} plans, moves and tracks freight and parcels across borders, with one point of contact from booking to delivery.')],
    ['photo' => site_photo('yard')['src'], 'kicker' => 'Ocean & road freight',
     'title' => 'Container and pallet freight, port to door',
     'lead' => 'Full and part loads handled through every leg of the journey, with customs paperwork prepared in advance.'],
    ['photo' => site_photo('clipboard')['src'], 'kicker' => 'Visibility',
     'title' => 'Every checkpoint recorded, every update shared',
     'lead' => 'Your receiver is emailed at each status change, and the full history is always one tracking number away.'],
];
$phone = trim(get_setting('contact_phone', ''));
$email = trim(get_setting('contact_email', ''));
$features = site_features();
?>
<section class="hero hero-corporate carousel" data-carousel data-autoplay="7000" data-fade aria-roledescription="carousel" aria-label="Highlights">
  <div class="carousel-viewport"><div class="carousel-track">
    <?php foreach ($slides as $i => $s): ?>
      <div class="carousel-slide corp-slide" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($slides) ?>" style="--slide-photo:url('<?= h($s['photo']) ?>')">
        <div class="container">
          <div class="corp-slide-panel">
            <div class="corp-slide-kicker"><?= h($s['kicker']) ?></div>
            <?php if ($i === 0): ?><h1><?= h($s['title']) ?></h1><?php else: ?><h2 class="corp-slide-title"><?= h($s['title']) ?></h2><?php endif; ?>
            <p><?= h($s['lead']) ?></p>
            <div class="hero-actions">
              <a href="/services.php" class="btn btn-primary">Our services</a>
              <a href="<?= h(quote_url()) ?>" class="btn btn-ghost-light"><?= request_shipment_enabled() ? 'Request a quote' : 'Contact us' ?></a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div></div>
  <div class="carousel-controls">
    <button type="button" class="carousel-btn carousel-prev" aria-label="Previous slide"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    <div class="carousel-dots" role="tablist" aria-label="Choose a slide"></div>
    <button type="button" class="carousel-btn carousel-pause" aria-label="Pause the slides" aria-pressed="false"><svg class="i-pause" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg><svg class="i-play" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg></button>
    <button type="button" class="carousel-btn carousel-next" aria-label="Next slide"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
  </div>
</section>

<div class="container">
  <div class="tab-widget" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Quick actions">
      <button type="button" role="tab" id="tab-track" aria-controls="panel-track" aria-selected="true"><?= ui_icon('search', 16) ?> Track</button>
      <button type="button" role="tab" id="tab-quote" aria-controls="panel-quote" aria-selected="false" tabindex="-1"><?= ui_icon('box', 16) ?> <?= request_shipment_enabled() ? 'Get a Quote' : 'Enquire' ?></button>
      <button type="button" role="tab" id="tab-contact" aria-controls="panel-contact" aria-selected="false" tabindex="-1"><?= ui_icon('phone', 16) ?> Contact</button>
    </div>
    <div class="tab-panel" role="tabpanel" id="panel-track" aria-labelledby="tab-track">
      <p><?= h(site_copy_map_aware('home_track_lead', 'Enter your tracking number to see where your consignment is and every checkpoint so far.', 'Enter your tracking number to see where your consignment is and every checkpoint so far.')) ?></p>
      <?= render_track_form('track-form--corporate') ?>
    </div>
    <div class="tab-panel" role="tabpanel" id="panel-quote" aria-labelledby="tab-quote" hidden>
      <p><?= request_shipment_enabled()
        ? 'Tell us what you are sending, from where and to where. You will see an estimate straight away, and our team confirms the quote.'
        : 'Tell us about your shipment and our team will come back to you with a quote.' ?></p>
      <a href="<?= h(quote_url()) ?>" class="btn btn-primary"><?= request_shipment_enabled() ? 'Start a quote' : 'Send an enquiry' ?> <?= ui_icon('arrow', 16) ?></a>
    </div>
    <div class="tab-panel" role="tabpanel" id="panel-contact" aria-labelledby="tab-contact" hidden>
      <div class="tab-contact">
        <?php if ($phone !== ''): ?><a href="<?= h(phone_href()) ?>"><?= ui_icon('phone', 18) ?> <?= h($phone) ?></a><?php endif; ?>
        <?php if ($email !== ''): ?><a href="mailto:<?= h($email) ?>"><?= ui_icon('mail', 18) ?> <?= h($email) ?></a><?php endif; ?>
        <a href="/contact.php"><?= ui_icon('arrow', 18) ?> Contact form</a>
      </div>
    </div>
  </div>
</div>

<section class="section" data-reveal>
  <div class="container intro-split">
    <div class="intro-media">
      <div class="img-zoom"><?= photo_img('boxes') ?></div>
      <div class="intro-badge"><strong><?= h(get_setting('stat_countries', '195+')) ?></strong><span>countries &amp; territories served</span></div>
    </div>
    <div>
      <div class="eyebrow"><?= h(site_copy('home_operate_eyebrow', 'About {site}')) ?></div>
      <h2><?= h(site_copy('home_operate_title', 'A logistics partner built around accountability')) ?></h2>
      <p class="intro-lead"><?= h(site_copy('home_operate_lead', 'Trained teams at every hub, a fleet for every distance, and a full record of every handover.')) ?></p>
      <ul class="tick-list">
        <?php foreach (array_slice($features, 0, 4) as $f): ?>
          <li><?= ui_icon('check', 18) ?><span><strong><?= h($f['title']) ?>.</strong> <?= h($f['desc']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <a href="/about.php" class="btn btn-outline">More about us</a>
    </div>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">What we do</div>
      <h2>Integrated freight services</h2>
      <p>One provider for every leg, from collection and customs to the final signature.</p>
    </div>
    <div class="grid-3 service-cards">
      <?php foreach (site_modes() as $m): ?>
        <article class="service-card">
          <div class="service-card-media img-zoom"><?= photo_img($m['photo']) ?></div>
          <div class="service-card-body">
            <h3><?= h($m['title']) ?></h3>
            <p><?= h($m['desc']) ?></p>
            <a href="/services.php" class="more-link">Read more <?= ui_icon('arrow', 16) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="parallax-stats" style="--parallax-photo:url('<?= h(site_photo('port')['src']) ?>')" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label); ?>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('home_steps_eyebrow', 'Our process')) ?></div>
      <h2><?= h(site_copy('home_steps_title', 'How we handle your shipment')) ?></h2>
    </div>
    <div class="process-row">
      <?php $proc = site_steps(); $proc[] = ['title' => 'Proof of delivery', 'desc' => 'The delivery is recorded with its date and time and the shipment is marked delivered.']; ?>
      <?php foreach ($proc as $i => $s): ?>
        <div class="process-step"><span class="process-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= h($s['title']) ?></h3><p><?= h($s['desc']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => 'Client testimonials', 'title' => 'What our clients say about us', 'more' => 'View all testimonials'], 'corporate') ?>

<?= render_partners_strip('Our partners', 'corporate') ?>
