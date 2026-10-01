<?php
/**
 * Shared public footer. Each template gives it its own closing call to
 * action and its own shape: a dark four-column footer, a soft rounded one,
 * a single quiet line, a loud one with the company name set huge, a
 * corporate one with contact details, or a dark one with a glowing edge.
 */
$__t = site_template();
$__phone = trim(get_setting('contact_phone', ''));
$__email = trim(get_setting('contact_email', ''));
$__address = trim(get_setting('contact_address', ''));
$__year = date('Y');
$__rights = get_setting('footer_rights_text', 'All rights reserved.');
$__note = trim(get_setting('footer_bottom_note', ''));
$__footerTagline = get_setting('footer_tagline');

// The closing call to action, in each template's own words. Left off the
// pages that already are the call to action.
$__showCta = !in_array($activeNav ?? '', ['contact', 'request'], true) && http_response_code() !== 404;
$__cta = tpl([
    'classic'     => ['Ready to send your next shipment?', 'Get a quote in minutes, or talk to our team about regular freight.', 'Get a quote'],
    'modern'      => ['Your next parcel is a few clicks away.', 'Book online, track every step, and get an email the moment it lands.', 'Start shipping'],
    'minimal'     => ['Have something to send?', 'Tell us where it is going. We will take it from there.', 'Write to us'],
    'bold'        => ['Ready to ship?', 'Big or small, near or far. Let\'s get it moving today.', 'Ship now'],
    'corporate'   => ['Looking for a reliable logistics partner?', 'Speak to our team about one-off shipments or a regular freight contract.', 'Request a Quote'],
    'dark-header' => ['Put your next shipment on the radar.', 'Every checkpoint logged, every update emailed, around the clock.', 'Get started'],
]);

$__companyLinks = [['/about.php', (string) tpl(['classic' => 'About Us', 'corporate' => 'About the Company', 'minimal' => 'About'])]];
$__companyLinks[] = ['/services.php', 'Services'];
$__companyLinks[] = ['/countries.php', (string) tpl(['modern' => 'Coverage', 'minimal' => 'Destinations', 'corporate' => 'Global Network', 'dark-header' => 'Network', 'classic' => 'Countries We Ship To', 'bold' => 'Countries'])];
if (reviews_visible()) {
    $__companyLinks[] = ['/testimonials.php', (string) tpl(['corporate' => 'Testimonials', 'minimal' => 'Kind words', 'classic' => 'Reviews'])];
}
$__companyLinks[] = ['/contact.php', 'Contact'];

$__supportLinks = [['/track.php', 'Track a Shipment']];
if (request_shipment_enabled()) {
    $__supportLinks[] = ['/request-shipment.php', 'Request a Shipment'];
}
$__supportLinks[] = ['/contact.php', 'Help Center'];
// The sign-in link follows the same switch as the one in the header, so
// turning it off at Branding really does take it off every public page.
if (header_shows_login()) {
    $__supportLinks[] = [admin_login_url(), 'Staff Login'];
}

$__headings = tpl([
    'classic'     => ['Company', 'Support', 'Get in Touch'],
    'modern'      => ['Company', 'Help', 'Say hello'],
    'minimal'     => ['', '', ''],
    'bold'        => ['Company', 'Support', 'Talk to us'],
    'corporate'   => ['Quick Links', 'Customer Service', 'Contact Information'],
    'dark-header' => ['Navigate', 'Support', 'Contact'],
]);
?>
</main>

<?php if ($__showCta): ?>
<section class="cta-band cta-band--<?= h($__t) ?>" data-reveal>
  <div class="container cta-band-inner">
    <div class="cta-band-text">
      <h2><?= h($__cta[0]) ?></h2>
      <p><?= h($__cta[1]) ?></p>
    </div>
    <div class="cta-band-actions">
      <a href="<?= h(quote_url()) ?>" class="btn btn-cta"><?= h($__cta[2]) ?></a>
      <?php if ($__phone !== ''): ?>
        <a href="<?= h(phone_href()) ?>" class="btn btn-cta-ghost"><?= ui_icon('phone', 16) ?> <?= h($__phone) ?></a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<footer class="site-footer ftr-<?= h($__t) ?>">
  <?php if ($__t === 'bold'): ?>
    <div class="ftr-giant" aria-hidden="true"><?= h(get_site_name()) ?></div>
  <?php endif; ?>
  <?php if ($__t === 'dark-header'): ?><span class="ftr-glow" aria-hidden="true"></span><?php endif; ?>
  <div class="container">

    <?php if ($__t === 'minimal'): ?>
      <div class="ftr-minimal-row">
        <?= render_brand(['mark_h' => 40, 'lockup_h' => 48, 'mark_max_w' => 70, 'wide_max_w' => 140, 'lockup_max_w' => 200]) ?>
        <nav class="ftr-minimal-nav" aria-label="Footer">
          <?php foreach (array_merge($__companyLinks, $__supportLinks) as [$__href, $__label]): ?>
            <a href="<?= h($__href) ?>"><?= h($__label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
    <?php else: ?>
      <div class="footer-grid">
        <div class="footer-brand">
          <?= render_brand([
                'mark_h' => 44, 'lockup_h' => 52, 'mark_max_w' => 76, 'wide_max_w' => 150, 'lockup_max_w' => 220,
                'on_dark' => $__t !== 'modern',
              ]) ?>
          <p class="footer-tagline"><?= h($__footerTagline) ?></p>
          <?php if ($__t === 'modern' || $__t === 'dark-header'): ?>
            <?= render_track_form('track-form--footer') ?>
          <?php endif; ?>
        </div>
        <div>
          <h4><?= h($__headings[0]) ?></h4>
          <ul>
            <?php foreach ($__companyLinks as [$__href, $__label]): ?>
              <li><a href="<?= h($__href) ?>"><?= h($__label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h4><?= h($__headings[1]) ?></h4>
          <ul>
            <?php foreach ($__supportLinks as [$__href, $__label]): ?>
              <li><a href="<?= h($__href) ?>"><?= h($__label) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h4><?= h($__headings[2]) ?></h4>
          <ul class="footer-contact">
            <?php if ($__email !== ''): ?><li><?= ui_icon('mail', 16) ?><a href="mailto:<?= h($__email) ?>"><?= h($__email) ?></a></li><?php endif; ?>
            <?php if ($__phone !== ''): ?><li><?= ui_icon('phone', 16) ?><a href="<?= h(phone_href()) ?>"><?= h($__phone) ?></a></li><?php endif; ?>
            <?php if ($__address !== '' && in_array($__t, ['corporate', 'classic', 'dark-header'], true)): ?><li><?= ui_icon('pin', 16) ?><span><?= h($__address) ?></span></li><?php endif; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="footer-bottom">
      <div>&copy; <?= $__year ?> <?= h(get_site_name()) ?>. <?= h($__rights) ?></div>
      <div class="footer-legal">
        <a href="/privacy.php">Privacy Policy</a>
        <a href="/terms.php">Terms of Service</a>
        <?php if ($__note !== ''): ?><span><?= h($__note) ?></span><?php endif; ?>
      </div>
    </div>
  </div>
</footer>
<script src="<?= h(asset_url('/assets/js/reveal.js')) ?>" defer></script>
<script src="<?= h(asset_url('/assets/js/site.js')) ?>" defer></script>
<script src="<?= h(asset_url('/assets/js/pickers.js')) ?>" defer></script>
<?php
// The live chat bubble (bottom-right of every public page), only when the
// site owner has switched it on at /admin/live_chat.php. Printed last so a
// slow or unreachable chat service can never hold up the rest of the page.
require_once __DIR__ . '/live_chat.php';
echo live_chat_script_tag();
?>
</body>
</html>
