<?php
/**
 * Partners: the companies shown in the moving logo strip on the homepage.
 * A name, and optionally a logo and a link. One switch hides the strip.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/partners.php';
require_admin();

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);

function find_partner(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

$tableReady = true;
try {
    db()->query('SELECT 1 FROM partners LIMIT 1');
} catch (PDOException $e) {
    $tableReady = false;
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle_site') {
        $on = !partners_enabled();
        set_setting('partners_enabled', $on ? '1' : '0');
        log_admin_activity($on ? 'Turned the partner strip on' : 'Turned the partner strip off');
        flash_set('success', $on ? 'The partner strip is on.' : 'The partner strip is off and no longer shown on the site.');
        redirect('/admin/partners.php');
    }

    if ($action === 'save') {
        $existing = $id > 0 ? find_partner($id) : null;
        $name = trim((string) ($_POST['name'] ?? ''));
        $url = trim((string) ($_POST['website_url'] ?? ''));
        $published = !empty($_POST['is_published']) ? 1 : 0;
        $order = (int) ($_POST['sort_order'] ?? 0);
        $removeLogo = !empty($_POST['remove_logo']);

        if ($name === '') $errors[] = 'Enter the company name.';
        if (mb_strlen($name) > 120) $errors[] = 'Keep the name under 120 characters.';
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url))) {
            $errors[] = 'The website must be a full address starting with https:// (or leave it blank).';
        }
        if (mb_strlen($url) > 255) $errors[] = 'The website address is too long.';
        if ($order < -9999 || $order > 9999) $errors[] = 'Display order must be between -9999 and 9999.';

        $upload = handle_image_upload('logo', 'partner', 2 * 1024 * 1024);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        }

        if (!$errors) {
            $logo = $existing['logo_path'] ?? null;
            if ($upload['path'] !== null) {
                if ($logo) delete_uploaded_image($logo);
                $logo = $upload['path'];
            } elseif ($removeLogo && $logo) {
                delete_uploaded_image($logo);
                $logo = null;
            }

            if ($existing) {
                $stmt = db()->prepare('UPDATE partners SET name = ?, logo_path = ?, website_url = ?, is_published = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$name, $logo, $url !== '' ? $url : null, $published, $order, $id]);
                log_admin_activity('Edited a partner', $name);
                flash_set('success', 'Partner updated.');
            } else {
                $stmt = db()->prepare('INSERT INTO partners (name, logo_path, website_url, is_published, sort_order) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$name, $logo, $url !== '' ? $url : null, $published, $order]);
                log_admin_activity('Added a partner', $name);
                flash_set('success', 'Partner added.');
            }
            redirect('/admin/partners.php');
        } elseif ($upload['path'] !== null) {
            // The form is being shown again; do not leave the file behind.
            delete_uploaded_image($upload['path']);
        }
        $editId = $id;
    }

    if ($action === 'toggle_publish' && ($partner = find_partner($id))) {
        $stmt = db()->prepare('UPDATE partners SET is_published = ? WHERE id = ?');
        $stmt->execute([$partner['is_published'] ? 0 : 1, $id]);
        log_admin_activity($partner['is_published'] ? 'Hid a partner' : 'Showed a partner', $partner['name']);
        flash_set('success', $partner['is_published'] ? 'Partner hidden from the site.' : 'Partner shown on the site.');
        redirect('/admin/partners.php');
    }

    if ($action === 'delete' && ($partner = find_partner($id))) {
        if (!empty($partner['logo_path'])) {
            delete_uploaded_image((string) $partner['logo_path']);
        }
        $stmt = db()->prepare('DELETE FROM partners WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity('Deleted a partner', $partner['name']);
        flash_set('success', 'Partner deleted.');
        redirect('/admin/partners.php');
    }
}

$editing = ($tableReady && $editId > 0) ? find_partner($editId) : null;
$form = [
    'id' => $editing['id'] ?? 0,
    'name' => (string) ($_POST['name'] ?? ($editing['name'] ?? '')),
    'website_url' => (string) ($_POST['website_url'] ?? ($editing['website_url'] ?? '')),
    'is_published' => $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['is_published']) : (bool) ($editing['is_published'] ?? true),
    'sort_order' => (int) ($_POST['sort_order'] ?? ($editing['sort_order'] ?? 0)),
    'logo_path' => (string) ($editing['logo_path'] ?? ''),
];

$rows = $tableReady ? db()->query('SELECT * FROM partners ORDER BY sort_order ASC, name ASC')->fetchAll() : [];
$siteOn = partners_enabled();
$publishedCount = count(array_filter($rows, static fn($r) => (int) $r['is_published'] === 1));

$activeAdminNav = 'partners';
$pageTitle = 'Partners';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Partners</h1>
  <?php if ($tableReady): ?><a href="#partner-form" class="btn btn-primary btn-sm">+ Add a partner</a><?php endif; ?>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<?php if (!$tableReady): ?>
  <div class="alert alert-error">
    The partners table is not in the database yet. Import the latest <code>sql/schema.sql</code>
    (it skips everything already there), then reload this page.
  </div>
<?php else: ?>

<div class="form-card switch-card" style="max-width:820px;">
  <div class="switch-card-row">
    <div>
      <h3 style="margin:0 0 6px;">Partner strip on the website</h3>
      <p style="margin:0;font-size:13.5px;color:var(--ink-soft);">
        A slowly moving row of partner logos on the homepage. It only appears
        while this is on and at least one partner below is shown.
      </p>
      <div class="status-line <?= $siteOn && $publishedCount ? 'status-line--on' : 'status-line--off' ?>" style="margin-top:10px;">
        <?php if (!$siteOn): ?>
          <strong>Off.</strong> The strip is hidden.
        <?php elseif (!$publishedCount): ?>
          <strong>On</strong>, but nothing shows yet: add a partner below.
        <?php else: ?>
          <strong>On.</strong> <?= $publishedCount ?> partner<?= $publishedCount === 1 ? '' : 's' ?> in the strip.
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

<div class="form-card" id="partner-form" style="max-width:820px;margin-top:16px;">
  <h3 style="margin-top:0;"><?= $editing ? 'Edit partner' : 'Add a partner' ?></h3>
  <p style="margin:-4px 0 16px;font-size:13px;color:var(--muted);">
    A company's name and logo are its trademarks. List companies you have an
    agreement with, using the logo file they supplied, and follow their brand
    rules. Without a logo the name is shown in the site's own lettering.
  </p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <div class="form-row">
      <div class="form-group">
        <label for="name">Company name</label>
        <input type="text" id="name" name="name" maxlength="120" required value="<?= h($form['name']) ?>">
      </div>
      <div class="form-group">
        <label for="website_url">Website <span style="font-weight:normal;color:var(--muted);">(optional)</span></label>
        <input type="url" id="website_url" name="website_url" maxlength="255" value="<?= h($form['website_url']) ?>" placeholder="https://">
      </div>
    </div>
    <div class="form-group">
      <label for="logo">Logo <span style="font-weight:normal;color:var(--muted);">(optional, PNG, JPG, WEBP or SVG, up to 2MB)</span></label>
      <?php if ($form['logo_path'] !== ''): ?>
        <div class="partner-logo-preview"><img src="<?= h($form['logo_path']) ?>" alt=""></div>
        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin:8px 0;">
          <input type="checkbox" name="remove_logo" value="1"> Remove this logo
        </label>
      <?php endif; ?>
      <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="sort_order">Display order</label>
        <input type="number" id="sort_order" name="sort_order" min="-9999" max="9999" value="<?= (int) $form['sort_order'] ?>">
        <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">Lower numbers first; equal numbers in A to Z order.</span>
      </div>
      <div class="form-group" style="display:flex;align-items:center;">
        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin:0;">
          <input type="checkbox" name="is_published" value="1" <?= $form['is_published'] ? 'checked' : '' ?>>
          Show in the strip
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary"><?= $editing ? 'Save changes' : 'Add partner' ?></button>
      <?php if ($editing): ?><a href="/admin/partners.php" class="btn btn-outline">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="table-responsive" style="margin-top:20px;">
<table class="data-table">
  <thead>
    <tr><th style="width:150px;">Logo</th><th>Company</th><th style="width:110px;">Status</th><th style="width:80px;">Order</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--muted);">No partners yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td data-label="Logo">
          <?php if (!empty($p['logo_path'])): ?>
            <img src="<?= h($p['logo_path']) ?>" alt="" style="max-height:34px;max-width:120px;width:auto;object-fit:contain;">
          <?php else: ?>
            <span style="color:var(--muted);font-size:12.5px;">Name only</span>
          <?php endif; ?>
        </td>
        <td data-label="Company">
          <strong><?= h($p['name']) ?></strong>
          <?php if (!empty($p['website_url'])): ?><div style="font-size:12.5px;color:var(--muted);overflow-wrap:anywhere;"><?= h($p['website_url']) ?></div><?php endif; ?>
        </td>
        <td data-label="Status"><span class="status-pill <?= $p['is_published'] ? 'status-converted' : 'status-closed' ?>"><?= $p['is_published'] ? 'Shown' : 'Hidden' ?></span></td>
        <td data-label="Order"><?= (int) $p['sort_order'] ?></td>
        <td class="actions" data-label="Actions">
          <div class="row-actions">
            <button type="button" class="row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Actions for <?= h($p['name']) ?>">&#8942;</button>
            <template class="row-actions-source">
              <a href="/admin/partners.php?edit=<?= (int) $p['id'] ?>#partner-form">Edit</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button type="submit"><?= $p['is_published'] ? 'Hide from site' : 'Show on site' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this partner permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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

<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
