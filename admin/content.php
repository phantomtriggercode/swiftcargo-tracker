<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$sections = [
    'home' => ['home_hero_title', 'home_hero_lead', 'stat_countries', 'stat_ontime', 'stat_support', 'stat_delivered'],
    'about' => ['about_title', 'about_lead', 'about_body'],
    'contact' => ['contact_intro', 'contact_phone', 'contact_email', 'contact_address'],
    'footer' => ['footer_tagline', 'footer_bottom_note'],
    'countries' => ['countries_intro', 'countries_list'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    if (isset($sections[$section])) {
        foreach ($sections[$section] as $key) {
            if (isset($_POST[$key])) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }
        flash_set('success', ucfirst($section) . ' content updated.');
    }
    redirect('/admin/content.php?tab=' . urlencode($section ?: 'home'));
}

$activeTab = $_GET['tab'] ?? 'home';
if (!isset($sections[$activeTab])) {
    $activeTab = 'home';
}

$activeAdminNav = 'content';
$pageTitle = 'Site Content';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Content</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<div class="content-tabs">
  <?php foreach (array_keys($sections) as $tab): ?>
    <a href="/admin/content.php?tab=<?= $tab ?>" class="content-tab <?= $activeTab === $tab ? 'active' : '' ?>"><?= ucfirst($tab) ?></a>
  <?php endforeach; ?>
</div>

<div class="form-card" style="max-width:720px;">

  <?php if ($activeTab === 'home'): ?>
    <form method="post">
      <input type="hidden" name="section" value="home">
      <div class="form-group">
        <label>Hero Title</label>
        <input type="text" name="home_hero_title" value="<?= h(get_setting('home_hero_title')) ?>">
      </div>
      <div class="form-group">
        <label>Hero Subtext</label>
        <textarea name="home_hero_lead" rows="3"><?= h(get_setting('home_hero_lead')) ?></textarea>
      </div>
      <h3>Stats Strip</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Countries Served</label>
          <input type="text" name="stat_countries" value="<?= h(get_setting('stat_countries')) ?>">
        </div>
        <div class="form-group">
          <label>On-Time Rate</label>
          <input type="text" name="stat_ontime" value="<?= h(get_setting('stat_ontime')) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Support Availability</label>
          <input type="text" name="stat_support" value="<?= h(get_setting('stat_support')) ?>">
        </div>
        <div class="form-group">
          <label>Packages Delivered</label>
          <input type="text" name="stat_delivered" value="<?= h(get_setting('stat_delivered')) ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Save Home Content</button>
    </form>

  <?php elseif ($activeTab === 'about'): ?>
    <form method="post">
      <input type="hidden" name="section" value="about">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="about_title" value="<?= h(get_setting('about_title')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="about_lead" rows="2"><?= h(get_setting('about_lead')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Body (separate paragraphs with a blank line)</label>
        <textarea name="about_body" rows="10"><?= h(get_setting('about_body')) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save About Content</button>
    </form>

  <?php elseif ($activeTab === 'contact'): ?>
    <form method="post">
      <input type="hidden" name="section" value="contact">
      <div class="form-group">
        <label>Intro Text</label>
        <textarea name="contact_intro" rows="2"><?= h(get_setting('contact_intro')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="contact_phone" value="<?= h(get_setting('contact_phone')) ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="text" name="contact_email" value="<?= h(get_setting('contact_email')) ?>">
      </div>
      <div class="form-group">
        <label>Address</label>
        <input type="text" name="contact_address" value="<?= h(get_setting('contact_address')) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Save Contact Content</button>
    </form>

  <?php elseif ($activeTab === 'footer'): ?>
    <form method="post">
      <input type="hidden" name="section" value="footer">
      <div class="form-group">
        <label>Footer Tagline</label>
        <textarea name="footer_tagline" rows="3"><?= h(get_setting('footer_tagline')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Footer Bottom Note</label>
        <input type="text" name="footer_bottom_note" value="<?= h(get_setting('footer_bottom_note')) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Save Footer Content</button>
    </form>

  <?php elseif ($activeTab === 'countries'): ?>
    <form method="post">
      <input type="hidden" name="section" value="countries">
      <div class="form-group">
        <label>Intro Text</label>
        <textarea name="countries_intro" rows="2"><?= h(get_setting('countries_intro')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Countries List (one per line)</label>
        <textarea name="countries_list" rows="16" style="font-family:monospace;font-size:13px;"><?php
          echo h(implode("\n", get_countries_list()));
        ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Countries List</button>
    </form>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
