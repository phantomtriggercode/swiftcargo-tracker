<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'about';
$pageTitle = 'About Us';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    site_copy_tpl('about_title', [
        'classic' => 'About {site}', 'modern' => 'The team behind your deliveries', 'minimal' => 'About us',
        'bold' => 'Who we are', 'corporate' => 'About the Company', 'dark-header' => 'About {site}',
    ]),
    site_copy('about_lead', 'A freight and parcel carrier built around one idea: you should always know exactly where your shipment is.'),
    ['key' => 'about', 'photo' => 'supervisor', 'crumb' => 'About', 'number' => '06',
     'kicker' => (string) tpl(['modern' => 'Our story', 'bold' => 'Our story', 'corporate' => 'Who we are', 'classic' => 'About us'])]
) ?>

<section class="section about-story" data-reveal>
  <div class="container about-grid">
    <div class="about-media">
      <div class="img-zoom about-photo-main"><img src="<?= h(get_site_image('about_hero_image', site_photo('clipboard')['src'])) ?>" alt="Our team reviewing a shipment checklist in the warehouse"></div>
      <div class="img-zoom about-photo-small"><?= photo_img('port-team') ?></div>
    </div>
    <div class="about-copy">
      <?= render_paragraphs(map_free_body(site_body('about_body'))) ?>
    </div>
  </div>
</section>

<section class="section section-soft about-stats" data-reveal>
  <div class="container grid-4">
    <?php foreach (site_stats() as [$value, $label]) echo render_stat($value, $label, 'stat stat-tile'); ?>
  </div>
</section>

<?= render_reviews_slider(['eyebrow' => (string) tpl(['corporate' => 'Client testimonials', 'minimal' => 'Kind words', 'classic' => 'Customer reviews']), 'title' => (string) tpl(['corporate' => 'Trusted by our clients', 'minimal' => 'In their own words', 'bold' => 'Customers say it best', 'modern' => 'Real words from real customers', 'dark-header' => 'Signal from our customers', 'classic' => 'What our customers say']), 'more' => 'Read all reviews'], tpl(['dark-header' => 'dark', 'classic' => 'classic', 'modern' => 'modern', 'minimal' => 'minimal', 'bold' => 'bold', 'corporate' => 'corporate'])) ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
