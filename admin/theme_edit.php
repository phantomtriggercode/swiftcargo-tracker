<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_super_admin();

$id = (int) ($_GET['id'] ?? 0);
$theme = get_theme($id);
if (!$theme) {
    flash_set('error', 'Theme not found.');
    redirect('/admin/themes.php');
}

$errors = [];
$hexPattern = '/^#[0-9a-fA-F]{6}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $styleKey = $_POST['style_key'] ?? 'classic';
    if ($name === '') {
        $errors[] = 'Theme name is required.';
    }
    if (!array_key_exists($styleKey, THEME_STYLE_KEYS)) {
        $errors[] = 'Please choose a valid design style.';
    }

    $colorValues = [];
    foreach (THEME_COLOR_FIELDS as $column => $meta) {
        $value = trim($_POST[$column] ?? '');
        if (!preg_match($hexPattern, $value)) {
            $errors[] = $meta['label'] . ' must be a valid hex color (e.g. #d40511).';
            continue;
        }
        $colorValues[$column] = strtolower($value);
    }

    if (!$errors) {
        $columns = array_keys(THEME_COLOR_FIELDS);
        $setClause = implode(', ', array_map(fn($c) => "$c = ?", $columns));
        $stmt = db()->prepare("UPDATE themes SET name = ?, style_key = ?, $setClause WHERE id = ?");
        $stmt->execute(array_merge([$name, $styleKey], array_map(fn($c) => $colorValues[$c], $columns), [$id]));
        flash_set('success', 'Theme colors updated.' . ($theme['is_active'] ? ' Changes are live site-wide.' : ''));
        redirect('/admin/theme_edit.php?id=' . $id);
    }
    // Re-fetch so the form below reflects the failed submission, not stale DB values.
    $theme = array_merge($theme, ['name' => $name, 'style_key' => $styleKey], $colorValues);
}

$activeAdminNav = 'themes';
$pageTitle = 'Edit Theme';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Edit Theme: <?= h($theme['name']) ?></h1>
  <a href="/admin/themes.php" class="btn btn-outline btn-sm">&larr; All Themes</a>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:640px;">
  <form method="post">
    <div class="form-row">
      <div class="form-group">
        <label>Theme Name</label>
        <input type="text" name="name" value="<?= h($theme['name']) ?>" required>
      </div>
      <div class="form-group">
        <label>Design Style</label>
        <select name="style_key">
          <?php foreach (THEME_STYLE_KEYS as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $theme['style_key'] === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3>Colors</h3>
    <div class="theme-color-fields">
      <?php foreach (THEME_COLOR_FIELDS as $column => $meta): ?>
        <div class="theme-color-field">
          <label for="<?= $column ?>_picker"><?= h($meta['label']) ?></label>
          <div class="theme-color-input">
            <input type="color" id="<?= $column ?>_picker" data-pairs-with="<?= $column ?>" value="<?= h($theme[$column]) ?>">
            <input type="text" id="<?= $column ?>" name="<?= $column ?>" data-pairs-with="<?= $column ?>_picker"
              value="<?= h($theme[$column]) ?>" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" required>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="margin-top:18px;">Save Colors</button>
  </form>
</div>

<script>
  document.querySelectorAll('.theme-color-input').forEach(function (wrap) {
    var picker = wrap.querySelector('input[type="color"]');
    var text = wrap.querySelector('input[type="text"]');
    picker.addEventListener('input', function () { text.value = picker.value; });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
  });
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
