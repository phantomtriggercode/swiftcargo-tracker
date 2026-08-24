<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM admin_activity_log WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target) {
            $del = db()->prepare('DELETE FROM admin_activity_log WHERE id = ?');
            $del->execute([$id]);
            // Deleting an audit entry is itself worth a trace — logged after
            // the delete so this new row is the one that survives it.
            log_admin_activity('Deleted activity log entry', $target['action'] . ' — ' . $target['admin_name'] . ', ' . $target['created_at']);
            flash_set('success', 'Entry deleted.');
        } else {
            flash_set('error', 'Entry not found.');
        }
        redirect('/admin/activity_log.php');
    } elseif ($action === 'clear_all') {
        $count = (int) db()->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
        db()->exec('DELETE FROM admin_activity_log');
        log_admin_activity('Cleared entire activity log', $count . ' ' . ($count === 1 ? 'entry' : 'entries') . ' removed');
        flash_set('success', 'Activity log cleared.');
        redirect('/admin/activity_log.php');
    }
}

$entries = [];
try {
    $entries = db()->query('SELECT * FROM admin_activity_log ORDER BY created_at DESC LIMIT 200')->fetchAll();
} catch (PDOException $e) {
    // Table not migrated yet (see sql/migrations/013_admin_activity_log.sql) — show an empty list instead of a fatal error.
}

$activeAdminNav = 'activity_log';
$pageTitle = 'Activity Log';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Activity Log</h1>
  <?php if ($entries): ?>
    <form method="post" onsubmit="return confirm('Permanently clear the entire activity log? This cannot be undone.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_all">
      <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Clear Entire Log</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  A record of sensitive admin actions — account changes, shipment deletions, SMTP credential
  changes, and color/template activation — for accountability. Showing the most recent 200 entries.
  Deleting an entry (or clearing the log) is itself recorded, so wiping history always leaves a trace.
</p>

<?php if (!$entries): ?>
  <p style="color:var(--muted);">Nothing logged yet.</p>
<?php else: ?>
<div class="table-responsive">
<table class="data-table">
  <thead>
    <tr>
      <th>When</th>
      <th>Admin</th>
      <th>Action</th>
      <th>Details</th>
      <th>IP Address</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($entries as $e): ?>
      <tr>
        <td data-label="When" style="white-space:nowrap;"><?= h(date('M j, Y g:i A', strtotime($e['created_at']))) ?></td>
        <td data-label="Admin"><?= h($e['admin_name']) ?><?= $e['admin_id'] === null ? ' <span style="color:var(--muted);">(deleted)</span>' : '' ?></td>
        <td data-label="Action"><?= h($e['action']) ?></td>
        <td data-label="Details" style="color:var(--muted);"><?= h($e['details']) ?></td>
        <td data-label="IP Address" style="color:var(--muted);font-size:12.5px;"><?= h($e['ip_address']) ?></td>
        <td data-label="Actions">
          <form method="post" onsubmit="return confirm('Delete this log entry?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
