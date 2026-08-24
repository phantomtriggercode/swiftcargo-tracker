<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = '';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;"><?= h(get_setting('privacy_title', 'Privacy Policy')) ?></h1>
    <p class="lead"><?= h(get_setting('privacy_lead', 'How we collect, use, and protect the information you share with us.')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:800px;font-size:16px;color:var(--ink-soft);">
    <?= render_paragraphs(get_setting('privacy_body')) ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
