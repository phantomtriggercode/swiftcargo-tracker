<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="/index.php" class="logo" style="margin-bottom:14px;">
          <?php if ($logoUrl = get_logo_url()): ?>
            <img src="<?= h($logoUrl) ?>" alt="" width="34" height="34" class="mark-img">
          <?php else: ?>
            <img src="/assets/images/logo-mark.svg" alt="" width="34" height="34" class="mark-img">
          <?php endif; ?>
          <span style="color:#fff;"><?= h(get_site_name()) ?></span>
        </a>
        <p style="color:#9ca3af;font-size:14px;max-width:320px;">
          <?= h(get_setting('footer_tagline')) ?>
        </p>
      </div>
      <div>
        <h4>Company</h4>
        <ul>
          <li><a href="/about.php">About Us</a></li>
          <li><a href="/services.php">Services</a></li>
          <li><a href="/countries.php">Countries We Ship To</a></li>
          <li><a href="/contact.php">Contact</a></li>
        </ul>
      </div>
      <div>
        <h4>Support</h4>
        <ul>
          <li><a href="/track.php">Track a Shipment</a></li>
          <li><a href="/request-shipment.php">Request a Shipment</a></li>
          <li><a href="/contact.php">Help Center</a></li>
          <li><a href="/admin/login.php">Login</a></li>
        </ul>
      </div>
      <div>
        <h4>Get in Touch</h4>
        <ul>
          <li><?= h(get_setting('contact_email')) ?></li>
          <li><?= h(get_setting('contact_phone')) ?></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> <?= h(get_site_name()) ?>. <?= h(get_setting('footer_rights_text', 'All rights reserved.')) ?></div>
      <div><?= h(get_setting('footer_bottom_note')) ?></div>
    </div>
  </div>
</footer>
<script src="<?= h(asset_url('/assets/js/reveal.js')) ?>" defer></script>
</body>
</html>
