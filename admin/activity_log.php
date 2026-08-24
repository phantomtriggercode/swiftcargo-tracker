<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_super_admin();

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
</div>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  A record of sensitive admin actions — account changes, shipment deletions, SMTP credential
  changes, and color/template activation — for accountability. Showing the most recent 200 entries.
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
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
