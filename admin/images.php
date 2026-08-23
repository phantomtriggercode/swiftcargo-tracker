<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/uploads.php';
require_admin();

$slots = [
    'home_hero_image' => [
        'label' => 'Homepage Hero Image',
        'where' => 'Top of the homepage, next to the main headline.',
        'default' => '/assets/images/illustrations/photo-hero-collage.jpg',
    ],
    'home_row1_image' => [
        'label' => '"Careful handling at every hub"',
        'where' => 'Homepage — "How We Operate" section, row 1.',
        'default' => '/assets/images/illustrations/photo-warehouse-stacking.jpg',
    ],
    'home_row2_image' => [
        'label' => '"A fleet built for reliability"',
        'where' => 'Homepage — "How We Operate" section, row 2.',
        'default' => '/assets/images/illustrations/photo-semi-sunset.jpg',
    ],
    'home_row3_image' => [
        'label' => '"Fast, careful unloading"',
        'where' => 'Homepage — "How We Operate" section, row 3.',
        'default' => '/assets/images/illustrations/photo-warehouse-unloading.jpg',
    ],
    'home_row4_image' => [
        'label' => '"Right to your door"',
        'where' => 'Homepage — "How We Operate" section, row 4.',
        'default' => '/assets/images/illustrations/photo-doorstep-handoff.png',
    ],
    'home_gallery_image1' => [
        'label' => 'Fleet Gallery — Photo 1 (large)',
        'where' => 'Homepage — "Our Fleet in Motion" gallery, big tile.',
        'default' => '/assets/images/illustrations/photo-doorstep-alt.jpg',
    ],
    'home_gallery_image2' => [
        'label' => 'Fleet Gallery — Photo 2',
        'where' => 'Homepage — "Our Fleet in Motion" gallery.',
        'default' => '/assets/images/illustrations/photo-van-city.jpg',
    ],
    'home_gallery_image3' => [
        'label' => 'Fleet Gallery — Photo 3',
        'where' => 'Homepage — "Our Fleet in Motion" gallery.',
        'default' => '/assets/images/illustrations/photo-container-yard.jpg',
    ],
    'home_gallery_image4' => [
        'label' => 'Fleet Gallery — Photo 4',
        'where' => 'Homepage — "Our Fleet in Motion" gallery.',
        'default' => '/assets/images/illustrations/photo-van-dusk.jpg',
    ],
    'home_gallery_image5' => [
        'label' => 'Fleet Gallery — Photo 5',
        'where' => 'Homepage — "Our Fleet in Motion" gallery.',
        'default' => '/assets/images/illustrations/photo-porch-delivery.jpg',
    ],
    'about_hero_image' => [
        'label' => 'About Page Banner',
        'where' => 'About Us page, below the headline.',
        'default' => '/assets/images/illustrations/photo-staff-clipboard.jpg',
    ],
    'services_hero_image' => [
        'label' => 'Services Page Banner',
        'where' => 'Services page, below the headline.',
        'default' => '/assets/images/illustrations/photo-semi-mountains.jpg',
    ],
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot = $_POST['slot'] ?? '';
    $action = $_POST['action'] ?? 'save';

    if (!isset($slots[$slot])) {
        flash_set('error', 'Unknown image slot.');
        redirect('/admin/images.php');
    }

    if ($action === 'reset') {
        delete_uploaded_image(get_setting($slot, ''));
        set_setting($slot, '');
        flash_set('success', $slots[$slot]['label'] . ' reset to the default image.');
        redirect('/admin/images.php');
    }

    $upload = handle_image_upload('image', 'site');
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    } elseif ($upload['path'] === null) {
        $errors[] = 'Choose a file to upload first.';
    } else {
        delete_uploaded_image(get_setting($slot, ''));
        set_setting($slot, $upload['path']);
        flash_set('success', $slots[$slot]['label'] . ' updated.');
        redirect('/admin/images.php');
    }
}

$activeAdminNav = 'images';
$pageTitle = 'Site Images';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Images</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<p style="color:var(--muted);font-size:14px;max-width:640px;margin-top:0;">
  Replace any of the illustrations below with your own photo. Each one shows exactly
  where it appears on the public site. PNG, JPG, WEBP, or GIF, up to 5MB — the image
  will scale to fit its spot automatically.
  (The logo is managed separately under <a href="/admin/branding.php" style="color:var(--brand-red);">Branding</a>.)
</p>

<div class="image-slot-grid">
  <?php foreach ($slots as $key => $slot): ?>
    <?php $current = get_site_image($key, $slot['default']); ?>
    <div class="form-card image-slot-card">
      <img src="<?= h($current) ?>" alt="" class="image-slot-preview">
      <h3><?= h($slot['label']) ?></h3>
      <p class="image-slot-where"><?= h($slot['where']) ?></p>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="slot" value="<?= h($key) ?>">
        <input type="hidden" name="action" value="save">
        <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp,.gif,.jfif">
        <button type="submit" class="btn btn-primary btn-sm btn-block" style="margin-top:10px;">Upload Replacement</button>
      </form>

      <?php if ($current !== $slot['default']): ?>
        <form method="post" style="margin-top:8px;">
          <input type="hidden" name="slot" value="<?= h($key) ?>">
          <input type="hidden" name="action" value="reset">
          <button type="submit" class="btn btn-outline btn-sm btn-block">Reset to Default</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
