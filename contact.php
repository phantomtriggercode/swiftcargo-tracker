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
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $honeypot = (string) ($_POST['website'] ?? '');

    // A person sends one message and waits for a reply. Anything past a
    // handful an hour is a script using the form as a relay.
    if (!rate_limit_hit('contact', 5, 3600, '', 3600)['allowed']) {
        $errors[] = 'You have already sent several messages recently. Please wait a while before sending another.';
    }

    if (!$errors && honeypot_tripped($honeypot)) {
        // Bots that fill in the hidden field never see it fail: pretend
        // success without actually sending anything, so nothing tells the
        // bot to adjust its behavior.
        flash_set('contact_success', 'Thanks for reaching out! Our team will get back to you shortly.');
        redirect('/contact.php');
    }

    if (!$errors) {
        if ($name === '') $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if ($message === '') $errors[] = 'Please enter a message.';
        if (strlen($message) > 5000) $errors[] = 'Please keep your message under 5000 characters.';
        // The subject is optional on purpose. It is there so the email
        // arriving in the office inbox says what it is about at a glance,
        // which is worth a lot when several come in at once; it is not
        // worth turning someone away over.
        if (mb_strlen($subject) > 150) $errors[] = 'Please keep the subject under 150 characters.';
    }

    if (!$errors) {
        $supportEmail = get_setting('contact_email');
        $siteName = get_site_name();

        // What the office sees in its inbox list. Their own subject when
        // they wrote one, so a list of enquiries can be triaged without
        // opening any of them, and the sender's name either way so it is
        // always obvious who it came from.
        $subjectLine = $subject !== ''
            ? $subject . ' (' . $name . ')'
            : 'Contact form: ' . $name;

        $theme = get_active_palette();
        $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . h($theme['color_ink']) . ';">'
            . '<p><strong>New message from the ' . h($siteName) . ' contact form</strong></p>'
            . '<p><strong>Name:</strong> ' . h($name) . '<br>'
            . '<strong>Email:</strong> ' . h($email)
            . ($subject !== '' ? '<br><strong>Subject:</strong> ' . h($subject) : '') . '</p>'
            . '<p style="white-space:pre-wrap;border-left:3px solid ' . h($theme['color_primary']) . ';padding-left:12px;">' . h($message) . '</p>'
            . '</div>';
        $altBody = "New message from the {$siteName} contact form\n\nName: {$name}\nEmail: {$email}\n"
            . ($subject !== '' ? "Subject: {$subject}\n" : '')
            . "\n{$message}";

        $result = filter_var($supportEmail, FILTER_VALIDATE_EMAIL)
            ? send_smtp_mail($supportEmail, $siteName . ' Support', $subjectLine, $htmlBody, $altBody, $email, $name)
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

<?= page_banner(
    (string) tpl(['classic' => 'Contact Us', 'modern' => "Let's talk", 'minimal' => 'Contact', 'bold' => 'Talk to us', 'corporate' => 'Contact Us', 'dark-header' => 'Get in touch']),
    site_copy('contact_intro', (string) tpl([
        'classic' => 'Questions about a shipment, a quote, or our services? Reach our support team any time.',
        'modern' => 'A real person reads every message. Ask us anything about a shipment, a quote or a route.',
        'minimal' => 'Write to us, or call. We reply to every message.',
        'bold' => 'Questions? Quotes? Something stuck? Fire away.',
        'corporate' => 'Our customer service team is ready to help with shipments, quotes and account enquiries.',
        'dark-header' => 'Send us a message and our team will pick it up.',
    ])),
    ['key' => 'contact', 'photo' => 'port-team', 'crumb' => 'Contact', 'number' => '07',
     'kicker' => (string) tpl(['modern' => 'Support', 'bold' => 'Contact', 'corporate' => 'Get in touch', 'classic' => 'Contact'])]
) ?>

<section class="section">
  <div class="container">
    <div class="tracking-layout tracking-layout--contact">
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
          <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
          </div>
          <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label for="subject">Subject <span style="font-weight:normal;color:var(--muted);">(optional)</span></label>
            <input type="text" id="subject" name="subject" maxlength="150"
                   value="<?= h($_POST['subject'] ?? '') ?>"
                   placeholder="e.g. Quote for a pallet to Berlin">
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
