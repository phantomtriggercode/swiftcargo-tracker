<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = '';
$pageTitle = 'Terms of Service';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;"><?= h(get_setting('terms_title', 'Terms of Service')) ?></h1>
    <p class="lead"><?= h(get_setting('terms_lead', 'The terms that apply when you use our site and shipping services.')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:800px;font-size:16px;color:var(--ink-soft);">
    <?= render_paragraphs(get_setting('terms_body')) ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
