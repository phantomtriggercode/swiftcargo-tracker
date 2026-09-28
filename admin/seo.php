<?php
/**
 * Search engine settings.
 *
 * Two jobs on one screen. The top half connects the site to Google Search
 * Console and Bing Webmaster Tools, which is what lets the site owner see
 * what people actually searched for to find them. The bottom half is
 * where staff write the title, the description and the target keywords
 * for each public page, with a preview of how that page will look in a
 * search result.
 *
 * Open to every admin, not super admins only: writing page copy and
 * writing the search text for that copy are the same job, and Site
 * Content is already open to everyone.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$pages = seo_pages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_verification') {
        // Stored exactly as pasted. seo_verification_token() is what pulls
        // the code out of a whole <meta> tag and refuses anything that is
        // not a verification code, so a bad paste can be seen and
        // corrected here rather than silently dropped.
        set_setting('seo_google_verification', trim((string) ($_POST['seo_google_verification'] ?? '')));
        set_setting('seo_bing_verification', trim((string) ($_POST['seo_bing_verification'] ?? '')));

        $file = trim((string) ($_POST['seo_google_verification_file'] ?? ''));
        // Google's file is always google<something>.html. Anything else is
        // a paste that went wrong, and is refused rather than saved.
        if ($file !== '' && !preg_match('/^google[A-Za-z0-9_\-]+\.html$/', $file)) {
            flash_set('error', 'The verification file name should look like google1a2b3c4d5e6f.html.');
            redirect('/admin/seo.php');
        }
        set_setting('seo_google_verification_file', $file);

        set_setting('seo_default_description', trim((string) ($_POST['seo_default_description'] ?? '')));
        set_setting('seo_share_image', trim((string) ($_POST['seo_share_image'] ?? '')));
        set_setting('seo_noindex_site', !empty($_POST['seo_noindex_site']) ? '1' : '0');

        log_admin_activity(
            'Changed search engine settings',
            'Site visible to search engines: ' . (empty($_POST['seo_noindex_site']) ? 'yes' : 'no')
        );
        flash_set('success', 'Search engine settings saved.');
        redirect('/admin/seo.php');
    }

    if ($action === 'save_page') {
        $pageKey = (string) ($_POST['page_key'] ?? '');
        if (!isset($pages[$pageKey])) {
            flash_set('error', 'That page does not exist.');
            redirect('/admin/seo.php');
        }

        save_seo_page($pageKey, [
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'focus_keyword' => $_POST['focus_keyword'] ?? '',
            'meta_keywords' => $_POST['meta_keywords'] ?? '',
            'og_title' => $_POST['og_title'] ?? '',
            'og_description' => $_POST['og_description'] ?? '',
            'canonical_path' => $_POST['canonical_path'] ?? '',
            'noindex' => $_POST['noindex'] ?? '',
        ]);

        log_admin_activity('Changed page search text', $pages[$pageKey]['label']);
        flash_set('success', $pages[$pageKey]['label'] . ' search settings saved.');
        redirect('/admin/seo.php?tab=' . urlencode($pageKey));
    }
}

$activeTab = (string) ($_GET['tab'] ?? 'home');
if (!isset($pages[$activeTab])) {
    $activeTab = 'home';
}

$record = get_seo_page($activeTab);
$page = $pages[$activeTab];
$siteUrl = get_site_url();

$googleToken = seo_verification_token('seo_google_verification');
$googleRaw = get_setting('seo_google_verification', '');
$bingToken = seo_verification_token('seo_bing_verification');
$bingRaw = get_setting('seo_bing_verification', '');
$verifyFile = get_setting('seo_google_verification_file', '');
$siteHidden = seo_site_hidden();

$activeAdminNav = 'seo';
$pageTitle = 'Search Engines';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Search Engines</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<?php if ($siteHidden): ?>
  <div class="alert alert-error">
    <strong>This site is currently hidden from search engines.</strong>
    Nothing on it can appear in Google or Bing results while this is on.
    Turn it off below once the site is ready for customers to find.
  </div>
<?php endif; ?>

<div class="form-card" style="max-width:760px;">
  <h3 style="margin-top:0;">Google Search Console &amp; Bing</h3>
  <p style="color:var(--muted);font-size:14px;margin-top:0;">
    Search Console is free and shows you what people typed to find this
    site, which pages they landed on, and anything Google could not read.
    To connect it, open
    <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" style="color:var(--brand-red);">Google Search Console</a>,
    add <strong><?= h($siteUrl) ?></strong> as a URL prefix property, choose
    the <strong>HTML tag</strong> method, and paste what it gives you into
    the first box below. Save, then press Verify back in Search Console.
  </p>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_verification">

    <div class="form-group">
      <label>Google Search Console verification</label>
      <input type="text" name="seo_google_verification" value="<?= h($googleRaw) ?>"
             placeholder='Paste the whole tag, or just the code inside it'>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        <?php if ($googleRaw !== '' && $googleToken === ''): ?>
          <strong style="color:var(--brand-red);">This does not look like a verification code.</strong>
          Nothing is being added to your pages. Copy the tag again from
          Search Console and paste the whole thing, including the quotes.
        <?php elseif ($googleToken !== ''): ?>
          <strong>Active.</strong> Every public page now carries this code.
          Press Verify in Search Console to finish.
        <?php else: ?>
          Paste either the whole <code>&lt;meta name="google-site-verification" ...&gt;</code>
          tag or just the code from inside it. Both work.
        <?php endif; ?>
      </span>
    </div>

    <div class="form-group">
      <label>Google verification file (only if you chose the file method)</label>
      <input type="text" name="seo_google_verification_file" value="<?= h($verifyFile) ?>"
             placeholder="google1a2b3c4d5e6f.html">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        If Search Console offered you a file to download instead of a tag,
        you do not need to upload it. Type its name here and this site will
        answer for it at
        <code><?= h($siteUrl) ?>/<?= h($verifyFile !== '' ? $verifyFile : 'google....html') ?></code>.
        Leave this blank if you used the tag above.
      </span>
    </div>

    <div class="form-group">
      <label>Bing Webmaster Tools verification</label>
      <input type="text" name="seo_bing_verification" value="<?= h($bingRaw) ?>"
             placeholder='Paste the whole tag, or just the code inside it'>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        <?php if ($bingRaw !== '' && $bingToken === ''): ?>
          <strong style="color:var(--brand-red);">This does not look like a verification code.</strong>
          Nothing is being added to your pages.
        <?php elseif ($bingToken !== ''): ?>
          <strong>Active.</strong>
        <?php else: ?>
          Optional. Bing sends a useful share of traffic and costs nothing
          to add.
        <?php endif; ?>
      </span>
    </div>

    <h3>Site-wide defaults</h3>

    <div class="form-group">
      <label>Fallback description</label>
      <textarea name="seo_default_description" rows="2" maxlength="320"
                placeholder="One sentence describing the company."><?= h(get_setting('seo_default_description', '')) ?></textarea>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        Used for any page below that has no description of its own. One
        clear sentence, around 150 characters.
      </span>
    </div>

    <div class="form-group">
      <label>Share image</label>
      <input type="text" name="seo_share_image" value="<?= h(get_setting('seo_share_image', '')) ?>"
             placeholder="/assets/images/uploads/share.jpg">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        The picture shown when someone pastes a link to this site into a
        message or posts it. Upload it under
        <a href="/admin/images.php" style="color:var(--brand-red);">Site Images</a>
        first, then paste its address here. Landscape, around 1200 by 630
        pixels. Left blank, your logo is used.
      </span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="seo_noindex_site" value="1" <?= $siteHidden ? 'checked' : '' ?>>
        Hide this whole site from search engines
      </label>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        For while the site is still being built. Leaving this on after
        launch is the single most common reason a new site never appears in
        Google at all, so turn it off the day you go live.
      </span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Search Engine Settings</button>
  </form>
</div>

<div class="form-card" style="max-width:760px;margin-top:20px;">
  <h3 style="margin-top:0;">Page by page</h3>
  <p style="color:var(--muted);font-size:14px;margin-top:0;">
    What each page says about itself in a search result, and which words
    it is trying to rank for. Write for the person searching, not for the
    search engine: a title that reads like a sentence and names what the
    page actually offers beats a list of keywords every time.
  </p>
</div>

<div class="content-tabs">
  <?php foreach ($pages as $key => $meta): ?>
    <a href="/admin/seo.php?tab=<?= h($key) ?>" class="content-tab <?= $activeTab === $key ? 'active' : '' ?>"><?= h($meta['label']) ?></a>
  <?php endforeach; ?>
</div>

<div class="form-card" style="max-width:760px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_page">
    <input type="hidden" name="page_key" value="<?= h($activeTab) ?>">

    <p style="margin-top:0;color:var(--muted);font-size:13.5px;">
      <strong><?= h($page['label']) ?></strong> &mdash;
      <a href="<?= h($page['path']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--brand-red);"><?= h($page['path']) ?></a><br>
      <?= h($page['hint']) ?>
    </p>

    <div class="form-group">
      <label for="focus_keyword">Target keyword</label>
      <input type="text" id="focus_keyword" name="focus_keyword" maxlength="120"
             value="<?= h($record['focus_keyword']) ?>"
             placeholder="e.g. international parcel delivery">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        The one phrase you most want this page to be found for. Use it in
        the title and in the description below, and in the page's own
        heading and opening paragraph under
        <a href="/admin/content.php" style="color:var(--brand-red);">Site Content</a>.
        One page, one target: two pages chasing the same phrase compete
        with each other and both do worse.
      </span>
    </div>

    <div class="form-group">
      <label for="meta_title">Search result title</label>
      <input type="text" id="meta_title" name="meta_title" maxlength="255"
             value="<?= h($record['meta_title']) ?>"
             data-count-for="meta_title_count" data-ideal-min="30" data-ideal-max="60"
             placeholder="<?= h($page['label'] . ' | ' . get_site_name()) ?>">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        <span id="meta_title_count"></span>
        Around 60 characters. Longer and Google cuts it off mid-word. Put
        the target keyword near the front. Blank uses the page heading plus
        your site name.
      </span>
    </div>

    <div class="form-group">
      <label for="meta_description">Search result description</label>
      <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"
                data-count-for="meta_description_count" data-ideal-min="120" data-ideal-max="155"
                placeholder="One or two sentences that make someone want to click."><?= h($record['meta_description']) ?></textarea>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        <span id="meta_description_count"></span>
        Around 155 characters. This does not change your ranking directly,
        but it is what decides whether anyone clicks the result, which
        eventually does.
      </span>
    </div>

    <div class="form-group">
      <label for="meta_keywords">Supporting keywords</label>
      <input type="text" id="meta_keywords" name="meta_keywords" maxlength="500"
             value="<?= h($record['meta_keywords']) ?>"
             placeholder="courier service, express shipping, parcel tracking">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        Comma separated. Related phrases someone might search for instead.
        Worth keeping as a record of what this page is aiming at, and used
        by some smaller search engines; Google itself stopped reading this
        tag years ago, so what matters far more is that these words appear
        naturally in the page's own text.
      </span>
    </div>

    <h3>Preview</h3>
    <div id="serp-preview" style="border:1px solid var(--border);border-radius:8px;padding:14px 16px;background:var(--bg-soft);margin-bottom:20px;">
      <div id="serp-url" style="font-size:12.5px;color:#0b7a3b;word-break:break-all;"></div>
      <div id="serp-title" style="font-size:18px;color:#1a0dab;line-height:1.3;margin:2px 0 3px;"></div>
      <div id="serp-desc" style="font-size:13px;color:#4d5156;line-height:1.5;"></div>
    </div>

    <h3>Advanced</h3>

    <div class="form-group">
      <label for="canonical_path">Preferred address</label>
      <input type="text" id="canonical_path" name="canonical_path" maxlength="255"
             value="<?= h($record['canonical_path']) ?>"
             placeholder="<?= h($page['path']) ?>">
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        Leave blank unless you know you need this. It tells search engines
        which address is the real one for this page when it can be reached
        by more than one.
      </span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="noindex" value="1" <?= (int) $record['noindex'] === 1 ? 'checked' : '' ?>>
        Keep this page out of search results
      </label>
      <span style="display:block;font-size:12.5px;color:var(--muted);margin-top:6px;">
        The page stays online and anyone with the link can open it, but it
        is left out of the sitemap and search engines are told not to list
        it.
      </span>
    </div>

    <h3>When shared in a message</h3>

    <div class="form-group">
      <label for="og_title">Share title</label>
      <input type="text" id="og_title" name="og_title" maxlength="255" value="<?= h($record['og_title']) ?>"
             placeholder="Blank uses the search result title above">
    </div>

    <div class="form-group">
      <label for="og_description">Share description</label>
      <textarea id="og_description" name="og_description" rows="2" maxlength="320"
                placeholder="Blank uses the search result description above"><?= h($record['og_description']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save <?= h($page['label']) ?> Settings</button>
  </form>
</div>

<script>
  /* Live character counts and a preview of the search result, so whoever
     is writing can see the cut-off point rather than guessing at it.
     Entirely cosmetic: the page saves exactly the same either way. */
  (function () {
    var siteUrl = <?= json_encode($siteUrl . $page['path']) ?>;
    var fallbackTitle = <?= json_encode($page['label'] . ' | ' . get_site_name()) ?>;
    var fallbackDesc = <?= json_encode(get_setting('seo_default_description', '') ?: 'No description written yet for this page.') ?>;

    var titleField = document.getElementById('meta_title');
    var descField = document.getElementById('meta_description');
    var serpTitle = document.getElementById('serp-title');
    var serpDesc = document.getElementById('serp-desc');
    var serpUrl = document.getElementById('serp-url');
    if (!titleField || !descField || !serpTitle) return;

    serpUrl.textContent = siteUrl;

    function countInto(field) {
      var target = document.getElementById(field.getAttribute('data-count-for'));
      if (!target) return;
      var len = field.value.length;
      var min = parseInt(field.getAttribute('data-ideal-min'), 10);
      var max = parseInt(field.getAttribute('data-ideal-max'), 10);
      var note = len + ' characters';
      var color = '';
      if (len === 0) {
        note = 'Empty, the fallback below will be used.';
      } else if (len < min) {
        note += ' (short, aim for ' + min + ' to ' + max + ')';
      } else if (len > max) {
        note += ' (over ' + max + ', likely to be cut off)';
        color = '#b45309';
      } else {
        note += ' (good)';
        color = '#15803d';
      }
      target.textContent = note;
      target.style.color = color;
      target.style.display = 'block';
      target.style.fontWeight = '600';
    }

    /* Roughly where Google truncates. Not exact, it measures pixels
       rather than characters, but close enough to be useful. */
    function clip(text, limit) {
      return text.length > limit ? text.slice(0, limit - 1).trimEnd() + '…' : text;
    }

    function refresh() {
      countInto(titleField);
      countInto(descField);
      serpTitle.textContent = clip(titleField.value || fallbackTitle, 60);
      serpDesc.textContent = clip(descField.value || fallbackDesc, 158);
    }

    titleField.addEventListener('input', refresh);
    descField.addEventListener('input', refresh);
    refresh();
  })();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
