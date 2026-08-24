<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/dashboard.php');
}

$id = (int) ($_POST['id'] ?? 0);
$stmt = db()->prepare('SELECT tracking_number FROM shipments WHERE id = ?');
$stmt->execute([$id]);
$shipment = $stmt->fetch();

if (!$shipment) {
    flash_set('error', 'Shipment not found.');
    redirect('/admin/dashboard.php');
}

$del = db()->prepare('DELETE FROM shipments WHERE id = ?');
$del->execute([$id]);

log_admin_activity('Deleted shipment', $shipment['tracking_number']);
flash_set('success', 'Shipment ' . $shipment['tracking_number'] . ' and its tracking history were deleted.');
redirect('/admin/dashboard.php');
