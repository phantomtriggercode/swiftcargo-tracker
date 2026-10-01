<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = '';
$seoPage = 'terms';
$pageTitle = 'Terms of Service';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    get_setting('terms_title', 'Terms of Service'),
    get_setting('terms_lead', 'The terms that apply when you use our site and shipping services.'),
    ['key' => 'terms', 'photo' => 'clipboard', 'crumb' => 'Terms of Service', 'kicker' => 'Legal']
) ?>

<section class="section">
  <div class="container prose">
    <?= render_paragraphs(get_setting('terms_body')) ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
