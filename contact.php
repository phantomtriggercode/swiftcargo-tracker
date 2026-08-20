<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Demo contact form — logged only, not emailed (no real support inbox wired up).
    flash_set('contact_success', 'Thanks for reaching out! Our team will get back to you shortly.');
    redirect('/contact.php');
}

$activeNav = 'contact';
$pageTitle = 'Contact Us';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;">Contact Us</h1>
    <p class="lead">Questions about a shipment or our services? Send us a message.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="form-card">
      <?php if ($msg = flash_get('contact_success')): ?>
        <div class="alert alert-success"><?= h($msg) ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
          <label for="message">Message</label>
          <textarea id="message" name="message" rows="5" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Message</button>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
