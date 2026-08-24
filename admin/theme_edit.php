<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_super_admin();

$id = (int) ($_GET['id'] ?? 0);
$palette = get_palette($id);
if (!$palette) {
    flash_set('error', 'Color palette not found.');
    redirect('/admin/themes.php');
}

$errors = [];
$hexPattern = '/^#[0-9a-fA-F]{6}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $errors[] = 'Palette name is required.';
    }

    $colorValues = [];
    foreach (PALETTE_COLOR_FIELDS as $column => $meta) {
        $value = trim($_POST[$column] ?? '');
        if (!preg_match($hexPattern, $value)) {
            $errors[] = $meta['label'] . ' must be a valid hex color (e.g. #d40511).';
            continue;
        }
        $colorValues[$column] = strtolower($value);
    }

    if (!$errors) {
        $columns = array_keys(PALETTE_COLOR_FIELDS);
        $setClause = implode(', ', array_map(fn($c) => "$c = ?", $columns));
        $stmt = db()->prepare("UPDATE color_palettes SET name = ?, $setClause WHERE id = ?");
        $stmt->execute(array_merge([$name], array_map(fn($c) => $colorValues[$c], $columns), [$id]));
        flash_set('success', 'Colors updated.' . ($palette['is_active'] ? ' Changes are live site-wide.' : ''));
        redirect('/admin/theme_edit.php?id=' . $id);
    }
    // Re-fetch so the form below reflects the failed submission, not stale DB values.
    $palette = array_merge($palette, ['name' => $name], $colorValues);
}

$activeAdminNav = 'themes';
$pageTitle = 'Edit Colors';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Edit Colors: <?= h($palette['name']) ?></h1>
  <a href="/admin/themes.php" class="btn btn-outline btn-sm">&larr; All Colors</a>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<div class="form-card" style="max-width:640px;">
  <form method="post">
    <div class="form-group">
      <label>Palette Name</label>
      <input type="text" name="name" value="<?= h($palette['name']) ?>" required>
    </div>

    <h3>Colors</h3>
    <div id="contrast-warnings"></div>
    <div class="theme-color-fields">
      <?php foreach (PALETTE_COLOR_FIELDS as $column => $meta): ?>
        <div class="theme-color-field">
          <label for="<?= $column ?>_picker"><?= h($meta['label']) ?></label>
          <div class="theme-color-input">
            <input type="color" id="<?= $column ?>_picker" data-pairs-with="<?= $column ?>" value="<?= h($palette[$column]) ?>">
            <input type="text" id="<?= $column ?>" name="<?= $column ?>" data-pairs-with="<?= $column ?>_picker"
              value="<?= h($palette[$column]) ?>" pattern="^#[0-9a-fA-F]{6}$" maxlength="7" required>
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
    picker.addEventListener('input', function () { text.value = picker.value; checkContrast(); });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
      checkContrast();
    });
  });

  // Live WCAG contrast check — warns (never blocks) when a chosen pair of
  // colors would make text hard or impossible to read, per the same
  // ratios verified against every preset palette.
  function hexToRgb(hex) {
    var n = parseInt(hex.replace('#', ''), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }
  function relLuminance(rgb) {
    var c = rgb.map(function (v) {
      v = v / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function contrastRatio(hex1, hex2) {
    if (!/^#[0-9a-fA-F]{6}$/.test(hex1) || !/^#[0-9a-fA-F]{6}$/.test(hex2)) return null;
    var l1 = relLuminance(hexToRgb(hex1));
    var l2 = relLuminance(hexToRgb(hex2));
    var hi = Math.max(l1, l2), lo = Math.min(l1, l2);
    return (hi + 0.05) / (lo + 0.05);
  }
  function fieldValue(column) {
    var el = document.getElementById(column);
    return el ? el.value : '';
  }
  var CONTRAST_PAIRS = [
    ['Body text on page background', 'color_ink', 'color_white', 4.5],
    ['Body text on soft background', 'color_ink', 'color_bg_soft', 4.5],
    ['Button text on primary button', 'color_white', 'color_primary', 4.5],
  ];
  function checkContrast() {
    var box = document.getElementById('contrast-warnings');
    var warnings = [];
    CONTRAST_PAIRS.forEach(function (pair) {
      var ratio = contrastRatio(fieldValue(pair[1]), fieldValue(pair[2]));
      if (ratio !== null && ratio < pair[3]) {
        warnings.push(pair[0] + ' is only ' + ratio.toFixed(2) + ':1 (aim for at least ' + pair[3] + ':1) — text may be hard to read.');
      }
    });
    box.innerHTML = warnings.length
      ? '<div class="alert alert-error">' + warnings.map(function (w) { return w; }).join('<br>') + '</div>'
      : '';
  }
  checkContrast();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
