<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$statuses = ['New', 'Contacted', 'Converted', 'Closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($id && in_array($status, $statuses, true)) {
        $stmt = db()->prepare('UPDATE shipment_requests SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        flash_set('success', 'Request #REQ-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT) . ' marked ' . $status . '.');
    }
    redirect('/admin/requests.php');
}

$requests = db()->query('SELECT * FROM shipment_requests ORDER BY created_at DESC')->fetchAll();

$statusClass = static fn(string $s) => 'status-' . strtolower($s);

$activeAdminNav = 'requests';
$pageTitle = 'Shipment Requests';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Public Shipment Requests</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<table class="data-table">
  <thead>
    <tr>
      <th>Ref</th>
      <th>From / To</th>
      <th>Contact</th>
      <th>Details</th>
      <th>Est. Cost</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$requests): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);">No shipment requests yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td><strong>REQ-<?= str_pad((string) $r['id'], 5, '0', STR_PAD_LEFT) ?></strong><br>
          <span style="color:var(--muted);font-size:12px;"><?= h(date('M j, g:i A', strtotime($r['created_at']))) ?></span>
        </td>
        <td style="font-size:13px;"><?= h($r['ship_from']) ?> &rarr; <?= h($r['ship_to']) ?></td>
        <td><?= h($r['full_name']) ?><br><span style="color:var(--muted);font-size:12.5px;"><?= h($r['email']) ?></span></td>
        <td style="font-size:13px;">
          <?= h($r['package_description']) ?><br>
          <span style="color:var(--muted);"><?= h($r['weight_kg']) ?>kg &middot; <?= h($r['packaging_type']) ?> &middot; <?= h($r['shipping_method']) ?><?= $r['land_method'] ? ' (' . h($r['land_method']) . ')' : '' ?> &middot; <?= h($r['service_type']) ?></span>
        </td>
        <td><?= $r['estimated_cost'] !== null ? '$' . number_format((float) $r['estimated_cost'], 2) : '—' ?></td>
        <td><span class="status-pill <?= $statusClass($r['status']) ?>"><?= h($r['status']) ?></span></td>
        <td class="actions">
          <form method="post" style="display:flex;gap:6px;">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <select name="status" onchange="this.form.submit()" class="btn-sm" style="border-radius:6px;border:1.5px solid var(--border);">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <a class="btn btn-outline btn-sm" href="/admin/shipment_form.php?from_request=<?= (int) $r['id'] ?>">Convert</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
