<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (SHIPMENT_STATUSES as $status) {
        $key = status_message_key($status);
        $message = trim($_POST[$key] ?? '');
        set_setting($key, $message);
    }
    flash_set('success', 'Status messages updated.');
    redirect('/admin/status_messages.php');
}

$activeAdminNav = 'status_messages';
$pageTitle = 'Status Messages';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Status Messages</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:720px;">
  When staff add a tracking update at <strong>Add Update</strong> and leave the
  note blank, the message below for that status is used instead — in the
  status-change email sent to the receiver and on the public tracking page.
  Staff can still type a specific note on any individual update to override
  this. Editing a message here only affects updates made after you save;
  past updates keep whatever wording was sent at the time.
</p>

<div class="form-card" style="max-width:720px;">
  <form method="post">
    <?= csrf_field() ?>
    <?php foreach (SHIPMENT_STATUSES as $status): ?>
      <div class="form-group">
        <label><?= h($status) ?></label>
        <textarea name="<?= h(status_message_key($status)) ?>" rows="2"><?= h(get_status_message($status)) ?></textarea>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-block">Save Messages</button>
  </form>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
