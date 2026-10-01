<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

http_response_code(404);

$pageTitle = 'Page Not Found';
// Not one of the tunable pages, and never one to be indexed.
$seoPage = '404';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    (string) tpl(['classic' => 'Page Not Found', 'modern' => 'This page took a wrong turn', 'minimal' => 'Not found.', 'bold' => 'Lost in transit', 'corporate' => 'Page Not Found', 'dark-header' => 'Signal lost']),
    "The page you're looking for doesn't exist, may have moved, or the link might be out of date. If you're trying to track a shipment, use the button below.",
    ['key' => '404', 'photo' => 'highway', 'crumb' => 'Not found', 'kicker' => 'Error 404', 'center' => true,
     'extra' => '<div class="banner-actions"><a href="/index.php" class="btn btn-outline">Go to Homepage</a><a href="/track.php" class="btn btn-primary">Track a Shipment</a></div>']
) ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
