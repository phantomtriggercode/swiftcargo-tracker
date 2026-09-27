<?php
/**
 * Our own JSON endpoint (not a third-party API) used by track.php's JavaScript
 * to poll for live position/status updates without reloading the page.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';

header('Content-Type: application/json');

// When a super admin switches the live map off, coordinates are not just
// hidden on the page, they are left out of this feed too. Otherwise anyone
// could read them straight out of this endpoint and the switch would mean
// nothing. Place names and status still come through, so the timeline works.
$mapOn = live_map_enabled();

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

$payload = [
    'tracking_number' => $shipment['tracking_number'],
    'status' => $shipment['status'],
    'current_location_label' => $events ? end($events)['location_label'] : $shipment['origin_label'],
    'origin_label' => $shipment['origin_label'],
    'destination_label' => $shipment['destination_label'],
    'updated_at' => $shipment['updated_at'],
    'map_enabled' => $mapOn,
];

if ($mapOn) {
    $payload += [
        'current_lat' => (float) $shipment['current_lat'],
        'current_lng' => (float) $shipment['current_lng'],
        'origin_lat' => (float) $shipment['origin_lat'],
        'origin_lng' => (float) $shipment['origin_lng'],
        'destination_lat' => (float) $shipment['destination_lat'],
        'destination_lng' => (float) $shipment['destination_lng'],
    ];
}

echo json_encode([
    'ok' => true,
    'shipment' => $payload,
    'events' => array_map(static function (array $e) use ($mapOn) {
        $out = [
            'id' => (int) $e['id'],
            'status' => $e['status'],
            'location_label' => $e['location_label'],
            'note' => $e['note'],
            'event_time' => $e['event_time'],
        ];
        if ($mapOn) {
            $out['lat'] = (float) $e['lat'];
            $out['lng'] = (float) $e['lng'];
        }
        return $out;
    }, $events),
]);
