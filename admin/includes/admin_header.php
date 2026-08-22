<?php
/**
 * Shared admin layout header. Caller must have already required config/db.php,
 * includes/functions.php and includes/auth.php, and called require_admin().
 */
$activeAdminNav = $activeAdminNav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — Admin' : 'Admin' ?> | <?= h(SITE_NAME) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="admin-wrap">
  <aside class="admin-sidebar">
    <a href="/admin/dashboard.php" class="logo">
      <img src="/assets/images/logo-mark.svg" alt="" width="34" height="34" class="mark-img">
      <span class="word-cargo">SwiftCargo</span>
    </a>
    <nav>
      <a href="/admin/dashboard.php" class="<?= $activeAdminNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="/admin/shipment_form.php" class="<?= $activeAdminNav === 'new' ? 'active' : '' ?>">New Shipment</a>
      <a href="/admin/requests.php" class="<?= $activeAdminNav === 'requests' ? 'active' : '' ?>">Shipment Requests</a>
      <a href="/admin/content.php" class="<?= $activeAdminNav === 'content' ? 'active' : '' ?>">Site Content</a>
      <a href="/admin/rates.php" class="<?= $activeAdminNav === 'rates' ? 'active' : '' ?>">Calculator Rates</a>
      <a href="/track.php" target="_blank">View Public Site &#8599;</a>
      <a href="/admin/logout.php">Logout</a>
    </nav>
  </aside>
  <main class="admin-main">
