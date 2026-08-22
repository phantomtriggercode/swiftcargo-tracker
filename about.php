<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'about';
$pageTitle = 'About Us';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;"><?= h(get_setting('about_title', 'About SwiftCargo')) ?></h1>
    <p class="lead"><?= h(get_setting('about_lead')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:800px;font-size:16px;color:var(--ink-soft);">
    <?= render_paragraphs(get_setting('about_body')) ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
