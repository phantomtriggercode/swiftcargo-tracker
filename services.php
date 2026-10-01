<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'services';
$pageTitle = 'Services';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    site_copy_tpl('services_title', [
        'classic' => 'Our Services', 'modern' => 'Services built around you', 'minimal' => 'Services',
        'bold' => 'What we move', 'corporate' => 'Logistics Services', 'dark-header' => 'Services',
    ]),
    site_copy('services_lead', (string) tpl([
        'classic' => 'Flexible shipping options for every kind of package, budget and deadline.',
        'modern' => 'Pick a speed, pick a route, and follow it all the way to the door.',
        'minimal' => 'Fewer options, chosen well. Each one tracked from start to finish.',
        'bold' => 'Fast, faster and fastest. Every shipment tracked, every time.',
        'corporate' => 'End-to-end freight and parcel services for businesses and individuals.',
        'dark-header' => 'Every service runs on the same network: logged, tracked and emailed at every stop.',
    ])),
    ['key' => 'services', 'photo' => 'semi-mountains', 'crumb' => 'Services', 'number' => '02',
     'kicker' => (string) tpl(['modern' => 'What we offer', 'bold' => 'Services', 'corporate' => 'What we do', 'classic' => 'Services'])]
) ?>

<section class="section" data-reveal>
  <div class="container">
    <div class="grid-3 tier-cards">
      <?php foreach (site_service_cards() as $i => $c): ?>
        <div class="card tier-card">
          <div class="tier-card-media img-zoom"><?= photo_img($c['photo']) ?></div>
          <div class="icon"><?= feature_icon($c['icon']) ?></div>
          <h3><?= h($c['title']) ?></h3>
          <p><?= h($c['desc']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h((string) tpl(['corporate' => 'Capabilities', 'bold' => 'Every way we move it', 'minimal' => 'How we move things', 'classic' => 'How we ship'])) ?></div>
      <h2><?= h((string) tpl(['corporate' => 'Integrated freight solutions', 'bold' => 'Air. Sea. Road. And the rest.', 'minimal' => 'By air, sea and road.', 'modern' => 'Every route, one place to track it', 'dark-header' => 'One network, every mode', 'classic' => 'Air, sea and road freight'])) ?></h2>
    </div>
    <div class="grid-3 mode-grid service-modes">
      <?php foreach (site_modes() as $m): ?>
        <div class="mode-card">
          <div class="mode-card-media"><?= photo_img($m['photo']) ?></div>
          <div class="mode-card-body"><h3><?= h($m['title']) ?></h3><p><?= h($m['desc']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" data-reveal>
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><?= h(site_copy('services_include_eyebrow', 'Every plan includes')) ?></div>
      <h2><?= h(site_copy('services_include_title', (string) tpl(['classic' => 'Full visibility, no extra cost', 'modern' => 'Included with every booking', 'minimal' => 'Always included.', 'bold' => 'No extras. All included.', 'corporate' => 'Standard with every service', 'dark-header' => 'Built into every shipment']))) ?></h2>
    </div>
    <?php
    $__showMapInclude = live_map_enabled()
        || !mentions_live_map(get_setting('services_include1_title', 'Live Map Tracking') . ' ' . get_setting('services_include1_desc', ''));
    ?>
    <div class="<?= $__showMapInclude ? 'grid-4' : 'grid-3' ?>">
      <?php if ($__showMapInclude): ?>
      <div class="card"><div class="icon"><?= feature_icon('/assets/images/icons/map-pin.svg') ?></div><h3><?= h(get_setting('services_include1_title', 'Live Map Tracking')) ?></h3><p><?= h(get_setting('services_include1_desc', 'Free on every shipment, every service tier.')) ?></p></div>
      <?php endif; ?>
      <div class="card"><div class="icon"><?= feature_icon('/assets/images/icons/mail.svg') ?></div><h3><?= h(get_setting('services_include2_title', 'Email Alerts')) ?></h3><p><?= h(get_setting('services_include2_desc', "Automatic updates sent to your receiver's inbox.")) ?></p></div>
      <div class="card"><div class="icon"><?= feature_icon('/assets/images/icons/clock.svg') ?></div><h3><?= h(get_setting('services_include3_title', 'Delivery Timeline')) ?></h3><p><?= h(get_setting('services_include3_desc', 'A timestamped history from pickup to drop-off.')) ?></p></div>
      <div class="card"><div class="icon"><?= feature_icon('/assets/images/icons/shield.svg') ?></div><h3><?= h(get_setting('services_include4_title', '24/7 Support')) ?></h3><p><?= h(get_setting('services_include4_desc', 'Our team is available around the clock.')) ?></p></div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
