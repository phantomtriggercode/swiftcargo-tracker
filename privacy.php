<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = '';
$seoPage = 'privacy';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    get_setting('privacy_title', 'Privacy Policy'),
    get_setting('privacy_lead', 'How we collect, use, and protect the information you share with us.'),
    ['key' => 'privacy', 'photo' => 'clipboard', 'crumb' => 'Privacy Policy', 'kicker' => 'Legal']
) ?>

<section class="section">
  <div class="container prose">
    <?= render_paragraphs(get_setting('privacy_body')) ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
