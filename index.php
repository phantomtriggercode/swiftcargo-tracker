<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$activeNav = 'home';
$pageTitle = 'Global Shipping & Live Package Tracking';
include __DIR__ . '/includes/header.php';

// Every template has its own homepage: its own hero, its own sections in
// its own order, its own wording. The content they are built from (the
// stats, the feature cards, the photos, the reviews) is the same.
include __DIR__ . '/includes/home/' . site_template() . '.php';

include __DIR__ . '/includes/footer.php';
