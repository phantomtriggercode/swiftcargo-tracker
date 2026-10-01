<?php
/**
 * Customer reviews: add as many as you like, edit, hide or delete them,
 * and one switch that shows or hides reviews across the whole public site.
 * Any admin can manage these.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/reviews.php';
require_admin();

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

/** The review row, or null. */
function find_review(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM reviews WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$tableReady = true;
try {
    db()->query('SELECT 1 FROM reviews LIMIT 1');
} catch (PDOException $e) {
    $tableReady = false;
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_site') {
        $on = !reviews_enabled();
        set_setting('reviews_enabled', $on ? '1' : '0');
        log_admin_activity($on ? 'Turned reviews on' : 'Turned reviews off', 'Slider, Testimonials page and menu link ' . ($on ? 'shown' : 'hidden'));
        flash_set('success', $on
            ? 'Reviews are on. The slider, the Testimonials page and its menu link are back on the site.'
            : 'Reviews are off. The slider, the Testimonials page and its menu link have been removed from the site. Your reviews are kept.');
        redirect('/admin/reviews.php');
    }

    if ($action === 'save') {
        $rating = (int) ($_POST['rating'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim(str_replace("\r\n", "\n", (string) ($_POST['message'] ?? '')));
        $published = !empty($_POST['is_published']) ? 1 : 0;
        $order = (int) ($_POST['sort_order'] ?? 0);

        if ($rating < 1 || $rating > 5) $errors[] = 'Choose a star rating from 1 to 5.';
        if ($title === '') $errors[] = 'Give the review a title.';
        if (mb_strlen($title) > REVIEW_TITLE_MAX) $errors[] = 'Keep the title under ' . REVIEW_TITLE_MAX . ' characters.';
        if ($message === '') $errors[] = 'Write the review message.';
        if (mb_strlen($message) > REVIEW_MESSAGE_MAX) $errors[] = 'Keep the message under ' . REVIEW_MESSAGE_MAX . ' characters.';
        if ($order < -9999 || $order > 9999) $errors[] = 'Display order must be between -9999 and 9999.';

        if (!$errors) {
            if ($id > 0 && find_review($id)) {
                $stmt = db()->prepare('UPDATE reviews SET rating = ?, title = ?, message = ?, is_published = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$rating, $title, $message, $published, $order, $id]);
                log_admin_activity('Edited a review', $title);
                flash_set('success', 'Review updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO reviews (rating, title, message, is_published, sort_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$rating, $title, $message, $published, $order]);
                log_admin_activity('Added a review', $title);
                flash_set('success', $published ? 'Review added and published.' : 'Review added, hidden from the site for now.');
            }
            redirect('/admin/reviews.php');
        }
        $editId = $id;
    }

    if ($action === 'toggle_publish' && ($review = find_review($id))) {
        $stmt = db()->prepare('UPDATE reviews SET is_published = ? WHERE id = ?');
        $stmt->execute([$review['is_published'] ? 0 : 1, $id]);
        log_admin_activity($review['is_published'] ? 'Hid a review' : 'Published a review', $review['title']);
        flash_set('success', $review['is_published'] ? 'Review hidden from the site.' : 'Review published.');
        redirect('/admin/reviews.php' . (isset($_POST['return_page']) ? '?page=' . (int) $_POST['return_page'] : ''));
    }

    if ($action === 'delete' && ($review = find_review($id))) {
        $stmt = db()->prepare('DELETE FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity('Deleted a review', $review['title']);
        flash_set('success', 'Review deleted.');
        redirect('/admin/reviews.php');
    }
}

$editing = ($tableReady && $editId > 0) ? find_review($editId) : null;
$form = [
    'id' => $editing['id'] ?? 0,
    'rating' => (int) ($_POST['rating'] ?? ($editing['rating'] ?? 5)),
    'title' => (string) ($_POST['title'] ?? ($editing['title'] ?? '')),
    'message' => (string) ($_POST['message'] ?? ($editing['message'] ?? '')),
    'is_published' => $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['is_published']) : (bool) ($editing['is_published'] ?? true),
    'sort_order' => (int) ($_POST['sort_order'] ?? ($editing['sort_order'] ?? 0)),
];

// Listing, newest-first within display order, 20 to a page.
$filter = (string) ($_GET['show'] ?? 'all');
$where = match ($filter) {
    'published' => 'WHERE is_published = 1',
    'hidden' => 'WHERE is_published = 0',
    default => '',
};
$perPage = 20;
$page = max(1, (int) ($_GET['page'] ?? 1));
$counts = ['all' => 0, 'published' => 0, 'hidden' => 0];
$rows = [];
$average = null;
if ($tableReady) {
    $counts['all'] = (int) db()->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
    $counts['published'] = (int) db()->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1')->fetchColumn();
    $counts['hidden'] = $counts['all'] - $counts['published'];
    $average = published_review_average();
    $filtered = $counts[$filter] ?? $counts['all'];
    $pages = max(1, (int) ceil($filtered / $perPage));
    $page = min($page, $pages);
    $rows = db()->query("SELECT * FROM reviews {$where} ORDER BY sort_order ASC, id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage))->fetchAll();
} else {
    $pages = 1;
}

$siteOn = reviews_enabled();

$activeAdminNav = 'reviews';
$pageTitle = 'Reviews';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Reviews</h1>
  <?php if ($tableReady): ?><a href="#review-form" class="btn btn-primary btn-sm">+ Add a review</a><?php endif; ?>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<?php if (!$tableReady): ?>
  <div class="alert alert-error">
    The reviews table is not in the database yet. Import the latest <code>sql/schema.sql</code>
    (it skips everything already there), then reload this page.
  </div>
<?php else: ?>

<div class="form-card switch-card" style="max-width:820px;">
  <div class="switch-card-row">
    <div>
      <h3 style="margin:0 0 6px;">Reviews on the website</h3>
      <p style="margin:0;font-size:13.5px;color:var(--ink-soft);">
        One switch for everything: the looping reviews slider on the homepage and
        About page, the Testimonials page, and the "Reviews" link in the menu and
        footer. Off removes all of them at once; your reviews stay saved here.
      </p>
      <div class="status-line <?= $siteOn ? 'status-line--on' : 'status-line--off' ?>" style="margin-top:10px;">
        <?php if (!$siteOn): ?>
          <strong>Off.</strong> Nothing about reviews is shown on the site.
        <?php elseif ($counts['published'] === 0): ?>
          <strong>On</strong>, but nothing shows yet: publish at least one review below.
        <?php else: ?>
          <strong>On.</strong> <?= $counts['published'] ?> published review<?= $counts['published'] === 1 ? '' : 's' ?> showing on the site.
        <?php endif; ?>
      </div>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_site">
      <button type="submit" class="switch-toggle <?= $siteOn ? 'is-on' : '' ?>" aria-pressed="<?= $siteOn ? 'true' : 'false' ?>">
        <span class="switch-toggle-track"><span class="switch-toggle-knob"></span></span>
        <span><?= $siteOn ? 'On' : 'Off' ?></span>
      </button>
    </form>
  </div>
</div>

<div class="form-card" id="review-form" style="max-width:820px;margin-top:16px;">
  <h3 style="margin-top:0;"><?= $editing ? 'Edit review' : 'Add a review' ?></h3>
  <p style="margin:-4px 0 16px;font-size:13px;color:var(--muted);">
    A star rating, a title and the message: that is all a review shows. Paste in feedback your customers have sent you.
  </p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

    <div class="form-group">
      <span class="field-label" id="rating-label">Star rating</span>
      <div class="star-input" role="radiogroup" aria-labelledby="rating-label">
        <?php for ($r = 5; $r >= 1; $r--): ?>
          <input type="radio" id="rating-<?= $r ?>" name="rating" value="<?= $r ?>" <?= $form['rating'] === $r ? 'checked' : '' ?>>
          <label for="rating-<?= $r ?>" title="<?= $r ?> star<?= $r === 1 ? '' : 's' ?>"><span class="sr-only"><?= $r ?> star<?= $r === 1 ? '' : 's' ?></span>&#9733;</label>
        <?php endfor; ?>
      </div>
    </div>

    <div class="form-group">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" maxlength="<?= REVIEW_TITLE_MAX ?>" required
             value="<?= h($form['title']) ?>" placeholder="e.g. Arrived two days early">
    </div>
    <div class="form-group">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5" maxlength="<?= REVIEW_MESSAGE_MAX ?>" required
                placeholder="What the customer said"><?= h($form['message']) ?></textarea>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">Up to <?= REVIEW_MESSAGE_MAX ?> characters. The slider shows the start of long messages; the Testimonials page shows them in full.</span>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="sort_order">Display order <span style="font-weight:normal;color:var(--muted);">(optional)</span></label>
        <input type="number" id="sort_order" name="sort_order" min="-9999" max="9999" step="1" value="<?= (int) $form['sort_order'] ?>">
        <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">Lower numbers show first. Leave at 0 to show newest first.</span>
      </div>
      <div class="form-group" style="display:flex;align-items:center;">
        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin:0;">
          <input type="checkbox" name="is_published" value="1" <?= $form['is_published'] ? 'checked' : '' ?>>
          Show this review on the site
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add review' ?></button>
      <?php if ($editing): ?><a href="/admin/reviews.php" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="admin-subhead" style="margin-top:26px;">
  <div class="content-tabs" style="margin-bottom:0;border-bottom:0;">
    <?php foreach (['all' => 'All', 'published' => 'Published', 'hidden' => 'Hidden'] as $key => $label): ?>
      <a href="/admin/reviews.php?show=<?= $key ?>" class="content-tab <?= $filter === $key || ($key === 'all' && !isset($counts[$filter])) ? 'active' : '' ?>"><?= $label ?> (<?= $counts[$key] ?>)</a>
    <?php endforeach; ?>
  </div>
  <?php if ($average !== null): ?>
    <div class="admin-subhead-note"><?= render_stars($average, 'rv-stars rv-stars--admin') ?> <strong><?= h(number_format($average, 1)) ?></strong> average of published reviews</div>
  <?php endif; ?>
</div>

<div class="table-responsive" style="margin-top:10px;">
<table class="data-table reviews-table">
  <thead>
    <tr><th style="width:120px;">Rating</th><th>Review</th><th style="width:110px;">Status</th><th style="width:80px;">Order</th><th style="width:120px;">Added</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);">No reviews here yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td data-label="Rating"><?= render_stars((float) $r['rating'], 'rv-stars rv-stars--admin') ?></td>
        <td data-label="Review">
          <strong><?= h($r['title']) ?></strong>
          <div style="color:var(--muted);font-size:13px;margin-top:2px;"><?= h(mb_strimwidth((string) $r['message'], 0, 160, '…')) ?></div>
        </td>
        <td data-label="Status"><span class="status-pill <?= $r['is_published'] ? 'status-converted' : 'status-closed' ?>"><?= $r['is_published'] ? 'Published' : 'Hidden' ?></span></td>
        <td data-label="Order"><?= (int) $r['sort_order'] ?></td>
        <td data-label="Added" style="font-size:13px;color:var(--muted);"><?= h(date('M j, Y', strtotime((string) $r['created_at']))) ?></td>
        <td class="actions" data-label="Actions">
          <div class="row-actions">
            <button type="button" class="row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for review <?= h($r['title']) ?>">&#8942;</button>
            <template class="row-actions-source">
              <a href="/admin/reviews.php?edit=<?= (int) $r['id'] ?>#review-form">Edit</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="return_page" value="<?= (int) $page ?>">
                <button type="submit"><?= $r['is_published'] ? 'Hide from site' : 'Publish' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this review permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="danger">Delete</button>
              </form>
            </template>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?= pager_links('/admin/reviews.php?show=' . rawurlencode($filter) . '&', $page, $pages, 'Review pages') ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
