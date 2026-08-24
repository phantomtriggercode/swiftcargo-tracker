<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/mailer.php';

ensure_session_started();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($message === '') $errors[] = 'Please enter a message.';

    if (!$errors) {
        $supportEmail = get_setting('contact_email');
        $siteName = get_site_name();

        $theme = get_active_palette();
        $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . h($theme['color_ink']) . ';">'
            . '<p><strong>New message from the ' . h($siteName) . ' contact form</strong></p>'
            . '<p><strong>Name:</strong> ' . h($name) . '<br>'
            . '<strong>Email:</strong> ' . h($email) . '</p>'
            . '<p style="white-space:pre-wrap;border-left:3px solid ' . h($theme['color_primary']) . ';padding-left:12px;">' . h($message) . '</p>'
            . '</div>';
        $altBody = "New message from the {$siteName} contact form\n\nName: {$name}\nEmail: {$email}\n\n{$message}";

        $result = filter_var($supportEmail, FILTER_VALIDATE_EMAIL)
            ? send_smtp_mail($supportEmail, $siteName . ' Support', 'Contact form: ' . $name, $htmlBody, $altBody, $email, $name)
            : ['ok' => false, 'error' => 'No support email address is configured.'];

        if ($result['ok']) {
            flash_set('contact_success', 'Thanks for reaching out! Our team will get back to you shortly.');
        } else {
            flash_set('contact_error', "Sorry, your message couldn't be sent (" . $result['error'] . '). Please try again or reach us by phone.');
        }
        redirect('/contact.php');
    }
}

$activeNav = 'contact';
$pageTitle = 'Contact Us';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;">Contact Us</h1>
    <p class="lead"><?= h(get_setting('contact_intro')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="tracking-layout" style="grid-template-columns: 0.9fr 1.1fr;">
      <div class="timeline">
        <h3>Get in Touch</h3>
        <div class="shipment-meta" style="grid-template-columns:1fr;">
          <div class="meta-box">
            <div class="meta-label">Phone</div>
            <div class="meta-value"><?= h(get_setting('contact_phone')) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Email</div>
            <div class="meta-value"><?= h(get_setting('contact_email')) ?></div>
          </div>
          <div class="meta-box">
            <div class="meta-label">Address</div>
            <div class="meta-value"><?= h(get_setting('contact_address')) ?></div>
          </div>
        </div>
      </div>

      <div class="form-card" style="max-width:none;">
        <?php if ($msg = flash_get('contact_success')): ?>
          <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('contact_error')): ?>
          <div class="alert alert-error"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-error"><?= h($err) ?></div>
        <?php endforeach; ?>
        <form method="post">
          <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" required><?= h($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-block">Send Message</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
