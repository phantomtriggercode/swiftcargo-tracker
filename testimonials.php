<?php
/**
 * Every published review, newest first (or in the order staff set), with
 * the average rating. Exists only while reviews are switched on and at
 * least one is published; otherwise it answers exactly like a page that
 * was never there.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/reviews.php';

if (!reviews_visible()) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$total = published_review_count();
$pages = max(1, (int) ceil($total / REVIEWS_PER_PAGE));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$reviews = published_reviews(REVIEWS_PER_PAGE, ($page - 1) * REVIEWS_PER_PAGE);
$average = published_review_average();

$activeNav = 'testimonials';
$seoPage = 'testimonials';
$pageTitle = 'Customer Reviews';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    (string) tpl(['classic' => 'Customer Reviews', 'modern' => 'What our customers say', 'minimal' => 'Kind words', 'bold' => 'Straight from our customers', 'corporate' => 'Client Testimonials', 'dark-header' => 'Customer signal']),
    (string) tpl([
        'classic' => 'Feedback from the people who ship with us.',
        'modern' => 'Real feedback from people who ship with us every day.',
        'minimal' => 'A few words from the people we deliver for.',
        'bold' => 'No filter. Just what customers told us.',
        'corporate' => 'Feedback from the businesses and individuals we work with.',
        'dark-header' => 'Every review below came from a customer we delivered for.',
    ]),
    ['key' => 'testimonials', 'photo' => 'doorstep', 'crumb' => (string) tpl(['corporate' => 'Testimonials', 'minimal' => 'Kind words', 'classic' => 'Reviews']), 'number' => '05',
     'kicker' => (string) tpl(['modern' => 'Reviews', 'bold' => 'Reviews', 'corporate' => 'Testimonials', 'classic' => 'Reviews'])]
) ?>

<section class="section rv-page rv-page--<?= h(site_template()) ?>">
  <div class="container">
    <?php if ($average !== null): ?>
      <div class="rv-page-summary">
        <span class="rv-big"><?= h(number_format($average, 1)) ?></span>
        <?= render_stars($average) ?>
        <span>Average of <?= $total ?> <?= $total === 1 ? 'review' : 'reviews' ?></span>
      </div>
    <?php endif; ?>
    <div class="rv-wall">
      <?php foreach ($reviews as $review): ?>
        <?= render_review_card($review) ?>
      <?php endforeach; ?>
    </div>
    <?= pager_links('/testimonials.php?', $page, $pages, 'Review pages') ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
