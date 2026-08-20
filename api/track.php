<?php
/**
 * Our own JSON endpoint (not a third-party API) used by track.php's JavaScript
 * to poll for live position/status updates without reloading the page.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$tn = trim($_GET['tn'] ?? '');
if ($tn === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing tracking number']);
    exit;
}

$shipment = get_shipment_by_tracking($tn);
if (!$shipment) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Shipment not found']);
    exit;
}

$events = get_shipment_events((int) $shipment['id']);

echo json_encode([
    'ok' => true,
    'shipment' => [
        'tracking_number' => $shipment['tracking_number'],
        'status' => $shipment['status'],
        'current_lat' => (float) $shipment['current_lat'],
        'current_lng' => (float) $shipment['current_lng'],
        'origin_label' => $shipment['origin_label'],
        'origin_lat' => (float) $shipment['origin_lat'],
        'origin_lng' => (float) $shipment['origin_lng'],
        'destination_label' => $shipment['destination_label'],
        'destination_lat' => (float) $shipment['destination_lat'],
        'destination_lng' => (float) $shipment['destination_lng'],
        'updated_at' => $shipment['updated_at'],
    ],
    'events' => array_map(static function (array $e) {
        return [
            'id' => (int) $e['id'],
            'status' => $e['status'],
            'location_label' => $e['location_label'],
            'lat' => (float) $e['lat'],
            'lng' => (float) $e['lng'],
            'note' => $e['note'],
            'event_time' => $e['event_time'],
        ];
    }, $events),
]);
