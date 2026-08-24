<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

http_response_code(404);

$pageTitle = 'Page Not Found';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;text-align:center;">
  <div class="container">
    <h1 style="font-size:34px;">Page Not Found</h1>
    <p class="lead">
      The page you're looking for doesn't exist, may have moved, or the link might be
      out of date. If you're trying to track a shipment, use the button below.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px;">
      <a href="/index.php" class="btn btn-outline">Go to Homepage</a>
      <a href="/track.php" class="btn btn-primary">Track a Shipment</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
