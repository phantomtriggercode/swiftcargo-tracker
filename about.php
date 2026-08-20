<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$activeNav = 'about';
$pageTitle = 'About Us';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;">About SwiftCargo</h1>
    <p class="lead">A demo global shipping brand built to showcase live tracking and automatic delivery alerts.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:800px;">
    <p style="font-size:16px;color:var(--ink-soft);margin-bottom:20px;">
      SwiftCargo is a demonstration project modeled after real-world global couriers. It shows how a
      logistics company can give customers full visibility into every shipment — an interactive live
      map, a detailed status timeline, and automatic email notifications sent the moment a package
      changes status.
    </p>
    <p style="font-size:16px;color:var(--ink-soft);margin-bottom:20px;">
      This site is not a real courier and does not move physical packages. All shipment data shown
      is either seeded demo data or was entered through the staff admin panel for demonstration
      purposes.
    </p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
