<?php
/**
 * Customer reviews: a star rating, a title and a message, written by staff
 * under Reviews in the admin panel and shown on the public site as a
 * looping slider and on the Testimonials page.
 *
 * One switch (reviews_enabled) controls all of it. Off, and the slider,
 * the Testimonials page (which then answers 404), its menu and footer
 * links and its sitemap entry all disappear together. On but with no
 * published review, they stay hidden too, so the site never shows an
 * empty reviews section.
 *
 * Requires config/db.php and includes/settings.php to already be loaded.
 */

/** Longest title and message accepted, matching the columns. */
const REVIEW_TITLE_MAX = 150;
const REVIEW_MESSAGE_MAX = 2000;
/** Reviews the slider carries at most; the Testimonials page has the rest. */
const REVIEW_SLIDER_LIMIT = 12;
/** Reviews per page on the Testimonials page. */
const REVIEWS_PER_PAGE = 12;

function reviews_enabled(): bool
{
    return get_setting('reviews_enabled', '1') === '1';
}

/** Published reviews, in display order. Empty on any database problem. */
function published_reviews(int $limit = 0, int $offset = 0): array
{
    try {
        $sql = 'SELECT id, rating, title, message, created_at FROM reviews
                WHERE is_published = 1 ORDER BY sort_order ASC, id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, (int) $offset);
        }
        return db()->query($sql)->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Number of published reviews, cached for the request. */
function published_review_count(): int
{
    static $count = null;
    if ($count === null) {
        try {
            $count = (int) db()->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1')->fetchColumn();
        } catch (PDOException $e) {
            $count = 0;
        }
    }
    return $count;
}

/** Average star rating of the published reviews, or null with none. */
function published_review_average(): ?float
{
    try {
        $avg = db()->query('SELECT AVG(rating) FROM reviews WHERE is_published = 1')->fetchColumn();
    } catch (PDOException $e) {
        return null;
    }
    return $avg === null || $avg === false ? null : round((float) $avg, 1);
}

/**
 * Whether anything about reviews should appear on the public site: the
 * switch is on and there is at least one review to show.
 */
function reviews_visible(): bool
{
    return reviews_enabled() && published_review_count() > 0;
}

/**
 * Star rating as five small SVG stars, with the rating spelled out for
 * screen readers. Half values (for an average) round to the nearest half.
 */
function render_stars(float $rating, string $class = 'rv-stars'): string
{
    $rating = max(0.0, min(5.0, $rating));
    $rounded = round($rating * 2) / 2;
    $label = ($rating == (int) $rating ? (int) $rating : number_format($rating, 1)) . ' out of 5 stars';

    $star = 'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z';
    $html = '<span class="' . h($class) . '" role="img" aria-label="' . h($label) . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rounded >= $i) {
            $state = 'full';
        } elseif ($rounded >= $i - 0.5) {
            $state = 'half';
        } else {
            $state = 'empty';
        }
        $html .= '<svg class="rv-star rv-star--' . $state . '" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">';
        if ($state === 'half') {
            $gid = 'rvh' . bin2hex(random_bytes(3));
            $html .= '<defs><linearGradient id="' . $gid . '"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="currentColor" stop-opacity="0.22"/></linearGradient></defs>'
                . '<path d="' . $star . '" fill="url(#' . $gid . ')"/>';
        } else {
            $html .= '<path d="' . $star . '"/>';
        }
        $html .= '</svg>';
    }
    return $html . '</span>';
}

/**
 * Page links for a long list: first, previous, a few around the current
 * page, next and last, so a list of any length stays one short row.
 * $baseUrl must already end in "?" or "&".
 */
function pager_links(string $baseUrl, int $page, int $pages, string $label = 'Pages'): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = static function (int $p, string $text, string $class = '', string $aria = '') use ($baseUrl): string {
        return '<a href="' . h($baseUrl . 'page=' . $p) . '"' . ($class !== '' ? ' class="' . $class . '"' : '')
            . ($aria !== '' ? ' aria-label="' . h($aria) . '"' : '') . '>' . $text . '</a>';
    };

    $html = '<nav class="pager" aria-label="' . h($label) . '">';
    if ($page > 1) {
        $html .= $link($page - 1, '&lsaquo;', 'pager-step', 'Previous page');
    }
    $from = max(1, $page - 2);
    $to = min($pages, $page + 2);
    if ($from > 1) {
        $html .= $link(1, '1');
        if ($from > 2) {
            $html .= '<span class="pager-gap">&hellip;</span>';
        }
    }
    for ($p = $from; $p <= $to; $p++) {
        $html .= $p === $page
            ? '<span class="active" aria-current="page">' . $p . '</span>'
            : $link($p, (string) $p);
    }
    if ($to < $pages) {
        if ($to < $pages - 1) {
            $html .= '<span class="pager-gap">&hellip;</span>';
        }
        $html .= $link($pages, (string) $pages);
    }
    if ($page < $pages) {
        $html .= $link($page + 1, '&rsaquo;', 'pager-step', 'Next page');
    }
    return $html . '</nav>';
}

/**
 * One review card, the same markup everywhere; each template styles it.
 */
function render_review_card(array $review, string $extraClass = ''): string
{
    $message = trim((string) $review['message']);
    return '<article class="rv-card' . ($extraClass !== '' ? ' ' . h($extraClass) : '') . '">'
        . '<span class="rv-quote" aria-hidden="true">&ldquo;</span>'
        . render_stars((float) $review['rating'])
        . '<h3 class="rv-title">' . h((string) $review['title']) . '</h3>'
        . '<div class="rv-message">' . nl2br(h($message)) . '</div>'
        . '</article>';
}

/**
 * The looping reviews slider. Returns '' when reviews are off or there are
 * none, so a page can simply echo it.
 *
 * $heading: ['eyebrow' => ..., 'title' => ..., 'lead' => ...] in the
 * active template's own words.
 */
function render_reviews_slider(array $heading = [], string $variant = ''): string
{
    if (!reviews_visible()) {
        return '';
    }
    $reviews = published_reviews(REVIEW_SLIDER_LIMIT);
    if (!$reviews) {
        return '';
    }

    $total = published_review_count();
    $average = published_review_average();

    $html = '<section class="section rv-section' . ($variant !== '' ? ' rv-section--' . h($variant) : '') . '" data-reveal aria-label="Customer reviews">'
        . '<div class="container">';

    if ($heading) {
        $html .= '<div class="section-head rv-head">';
        if (!empty($heading['eyebrow'])) {
            $html .= '<div class="eyebrow">' . h($heading['eyebrow']) . '</div>';
        }
        if (!empty($heading['title'])) {
            $html .= '<h2>' . h($heading['title']) . '</h2>';
        }
        if ($average !== null) {
            $html .= '<div class="rv-summary">' . render_stars($average, 'rv-stars rv-stars--summary')
                . '<span><strong>' . h(number_format($average, 1)) . '</strong> out of 5 &middot; '
                . $total . ' ' . ($total === 1 ? 'review' : 'reviews') . '</span></div>';
        }
        if (!empty($heading['lead'])) {
            $html .= '<p>' . h($heading['lead']) . '</p>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="carousel rv-carousel" data-carousel data-autoplay="6000" aria-roledescription="carousel">'
        . '<div class="carousel-viewport"><div class="carousel-track">';
    foreach ($reviews as $i => $review) {
        $html .= '<div class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Review ' . ($i + 1) . ' of ' . count($reviews) . '">'
            . render_review_card($review)
            . '</div>';
    }
    $html .= '</div></div>'
        . '<div class="carousel-controls">'
        . '<button type="button" class="carousel-btn carousel-prev" aria-label="Previous review"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
        . '<div class="carousel-dots" role="tablist" aria-label="Choose a review"></div>'
        . '<button type="button" class="carousel-btn carousel-pause" aria-label="Pause the reviews" aria-pressed="false"><svg class="i-pause" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5v14M16 5v14" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg><svg class="i-play" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M8 5l11 7-11 7z" fill="currentColor"/></svg></button>'
        . '<button type="button" class="carousel-btn carousel-next" aria-label="Next review"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
        . '</div>'
        . '</div>';

    $html .= '<div class="rv-more"><a href="/testimonials.php" class="rv-more-link">' . h($heading['more'] ?? 'Read all reviews') . ' <span aria-hidden="true">&rarr;</span></a></div>';

    return $html . '</div></section>';
}
