<?php
/**
 * Small shared helpers used across public + admin pages.
 */

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_tracking_number(): string
{
    // e.g. SC7482913KE — SC prefix + 7 random digits + 2 random letters
    $digits = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    $letters = '';
    for ($i = 0; $i < 2; $i++) {
        $letters .= chr(random_int(65, 90));
    }
    return 'SC' . $digits . $letters;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Delivered' => 'badge-delivered',
        'Out for Delivery' => 'badge-transit',
        'En Route', 'In Transit' => 'badge-transit',
        'Customs Clearance', 'Insurance Clearance' => 'badge-hold',
        'Picked Up' => 'badge-pending',
        'Pending' => 'badge-pending',
        'On Hold' => 'badge-hold',
        'Delayed', 'Exception' => 'badge-alert',
        default => 'badge-pending',
    };
}

function get_shipment_by_tracking(string $trackingNumber): ?array
{
    $stmt = db()->prepare('SELECT * FROM shipments WHERE tracking_number = ? LIMIT 1');
    $stmt->execute([strtoupper(trim($trackingNumber))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_shipment_events(int $shipmentId): array
{
    $stmt = db()->prepare('SELECT * FROM tracking_events WHERE shipment_id = ? ORDER BY event_time ASC');
    $stmt->execute([$shipmentId]);
    return $stmt->fetchAll();
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
