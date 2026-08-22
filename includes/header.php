<?php
/**
 * Shared public site header. Expects $activeNav to be set (optional) by the caller.
 */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' . h(SITE_NAME) : h(SITE_NAME) . ' | Global Shipping & Tracking' ?></title>
<meta name="description" content="Track your shipment live on the map and get instant email alerts on every status update.">
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="topbar">
  <div class="container">
    <div>Customer Service: <a href="tel:+18005550199">+1 (800) 555-0199</a></div>
    <div class="topbar-links">
      <a href="/contact.php">Support</a>
      <a href="/admin/login.php">Staff Login</a>
    </div>
  </div>
</div>

<header class="site-header">
  <div class="container">
    <a href="/index.php" class="logo">
      <img src="/assets/images/logo-mark.svg" alt="" width="34" height="34" class="mark-img">
      <span><span class="word-swift">Swift</span><span class="word-cargo">Cargo</span></span>
    </a>
    <nav class="main-nav">
      <a href="/index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a>
      <a href="/track.php" class="<?= $activeNav === 'track' ? 'active' : '' ?>">Track Shipment</a>
      <a href="/services.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>">Services</a>
      <a href="/about.php" class="<?= $activeNav === 'about' ? 'active' : '' ?>">About</a>
      <a href="/contact.php" class="<?= $activeNav === 'contact' ? 'active' : '' ?>">Contact</a>
    </nav>
    <a href="/track.php" class="btn btn-primary">Track Now</a>
  </div>
</header>
