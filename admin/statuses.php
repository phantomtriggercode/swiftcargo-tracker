<?php
/**
 * Shipment statuses: add, rename, recolour, reorder and remove the values
 * staff can pick from when adding a tracking update.
 *
 * Open to all admins, because this is day-to-day operational vocabulary
 * rather than a site-wide setting.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

const STATUS_BADGES = [
    'badge-pending'   => 'Grey, waiting or booked',
    'badge-transit'   => 'Blue, on the move',
    'badge-hold'      => 'Amber, held or being checked',
    'badge-delivered' => 'Green, finished',
    'badge-alert'     => 'Red, problem',
];

/** How many shipments and tracking updates still use this status name. */
function status_usage(string $name): int
{
    $a = db()->prepare('SELECT COUNT(*) FROM shipments WHERE status = ?');
    $a->execute([$name]);
    $b = db()->prepare('SELECT COUNT(*) FROM tracking_events WHERE status = ?');
    $b->execute([$name]);
    return (int) $a->fetchColumn() + (int) $b->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $badge = $_POST['badge_class'] ?? 'badge-pending';

        if ($name === '') {
            flash_set('error', 'Give the status a name.');
        } elseif (mb_strlen($name) > 50) {
            flash_set('error', 'A status name can be at most 50 characters.');
        } elseif (!array_key_exists($badge, STATUS_BADGES)) {
            flash_set('error', 'Pick one of the listed colours.');
        } else {
            $exists = db()->prepare('SELECT COUNT(*) FROM shipment_statuses WHERE name = ?');
            $exists->execute([$name]);
            if ((int) $exists->fetchColumn() > 0) {
                flash_set('error', 'There is already a status called "' . $name . '".');
            } else {
                $next = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM shipment_statuses')->fetchColumn();
                $ins = db()->prepare('INSERT INTO shipment_statuses (name, badge_class, sort_order) VALUES (?, ?, ?)');
                $ins->execute([$name, $badge, $next]);
                log_admin_activity('Added shipment status', $name);
                flash_set('success', 'Status "' . $name . '" added. Staff can pick it when adding an update, and you can write its default message under Status Messages.');
            }
        }
        redirect('/admin/statuses.php');
    }

    if ($action === 'save') {
        $ids = array_map('intval', $_POST['id'] ?? []);
        foreach ($ids as $rowId) {
            $badge = $_POST['badge_class'][$rowId] ?? 'badge-pending';
            $order = (int) ($_POST['sort_order'][$rowId] ?? 0);
            if (!array_key_exists($badge, STATUS_BADGES)) {
                continue;
            }
            $upd = db()->prepare('UPDATE shipment_statuses SET badge_class = ?, sort_order = ? WHERE id = ?');
            $upd->execute([$badge, $order, $rowId]);
        }
        log_admin_activity('Updated shipment status list');
        flash_set('success', 'Statuses saved.');
        redirect('/admin/statuses.php');
    }

    if ($action === 'delete') {
        $rowId = (int) ($_POST['id'] ?? 0);
        $row = db()->prepare('SELECT * FROM shipment_statuses WHERE id = ?');
        $row->execute([$rowId]);
        $status = $row->fetch();

        if (!$status) {
            flash_set('error', 'That status no longer exists.');
        } elseif ((int) $status['is_protected'] === 1) {
            flash_set('error', '"' . $status['name'] . '" cannot be removed: every new shipment is created with it.');
        } elseif (($used = status_usage($status['name'])) > 0) {
            // Deleting it would leave existing records pointing at a status
            // that no longer exists, so refuse and say exactly why.
            flash_set('error', '"' . $status['name'] . '" is still used by ' . $used . ' shipment or tracking update' . ($used === 1 ? '' : 's') . '. Move those to another status first, or keep this one.');
        } else {
            $del = db()->prepare('DELETE FROM shipment_statuses WHERE id = ?');
            $del->execute([$rowId]);
            log_admin_activity('Removed shipment status', $status['name']);
            flash_set('success', 'Status "' . $status['name'] . '" removed.');
        }
        redirect('/admin/statuses.php');
    }
}

$rows = db()->query('SELECT * FROM shipment_statuses ORDER BY sort_order, name')->fetchAll();
$usage = [];
foreach ($rows as $row) {
    $usage[$row['id']] = status_usage($row['name']);
}

$activeAdminNav = 'statuses';
$pageTitle = 'Shipment Statuses';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Shipment Statuses</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert alert-error"><?= h($msg) ?></div>
<?php endif; ?>

<p style="color:var(--muted);font-size:14px;max-width:760px;">
  These are the statuses staff can choose when adding a tracking update. Add
  your own (for example <strong>In Transit</strong> or <strong>At Sorting Facility</strong>),
  set the colour its badge shows in, and drag the order around by changing
  the numbers. Each status can also have a default message written for it
  under <a href="/admin/status_messages.php" style="color:var(--brand-red);">Status Messages</a>,
  used when staff leave the remark blank.
</p>

<div class="form-card" style="max-width:820px;">
  <h3 style="margin-top:0;">Add a status</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="form-group">
        <label>Status name</label>
        <input type="text" name="name" maxlength="50" placeholder="e.g. In Transit" required>
      </div>
      <div class="form-group">
        <label>Badge colour</label>
        <select name="badge_class">
          <?php foreach (STATUS_BADGES as $class => $label): ?>
            <option value="<?= h($class) ?>"><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Add Status</button>
  </form>
</div>

<div class="form-card" style="max-width:820px;margin-top:16px;">
  <h3 style="margin-top:0;">Current statuses</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Status</th><th style="width:210px;">Badge colour</th><th style="width:90px;">Order</th><th style="width:130px;">In use</th><th style="width:90px;">Remove</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td data-label="Status">
              <input type="hidden" name="id[]" value="<?= (int) $row['id'] ?>">
              <span class="badge <?= h($row['badge_class']) ?>"><?= h($row['name']) ?></span>
              <?php if ((int) $row['is_protected'] === 1): ?>
                <span style="display:block;font-size:11.5px;color:var(--muted);margin-top:4px;">Built in, cannot be removed</span>
              <?php endif; ?>
            </td>
            <td data-label="Badge colour">
              <select name="badge_class[<?= (int) $row['id'] ?>]">
                <?php foreach (STATUS_BADGES as $class => $label): ?>
                  <option value="<?= h($class) ?>" <?= $row['badge_class'] === $class ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td data-label="Order">
              <input type="number" name="sort_order[<?= (int) $row['id'] ?>]" value="<?= (int) $row['sort_order'] ?>" step="10" style="width:80px;">
            </td>
            <td data-label="In use">
              <?= $usage[$row['id']] > 0 ? (int) $usage[$row['id']] . ' record' . ($usage[$row['id']] === 1 ? '' : 's') : 'Not used yet' ?>
            </td>
            <td data-label="Remove">
              <?php if ((int) $row['is_protected'] === 1 || $usage[$row['id']] > 0): ?>
                <span style="color:var(--muted);font-size:13px;">&ndash;</span>
              <?php else: ?>
                <button type="submit" form="del-<?= (int) $row['id'] ?>" class="danger">Delete</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:14px;">Save Colours &amp; Order</button>
  </form>
</div>

<?php foreach ($rows as $row): ?>
  <?php if ((int) $row['is_protected'] !== 1 && $usage[$row['id']] === 0): ?>
    <form method="post" id="del-<?= (int) $row['id'] ?>" onsubmit="return confirm('Remove the status &quot;<?= h($row['name']) ?>&quot;?');" style="display:none;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    </form>
  <?php endif; ?>
<?php endforeach; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
