<?php
/**
 * Our own JSON endpoint (not a third-party API) used by track.php's JavaScript
 * to poll for live position/status updates without reloading the page.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';

header('Content-Type: application/json');
// The tracking page polls this every 15 seconds while it is open, so the
// ceiling has to sit well above four calls a minute. What it stops is a
// script walking through tracking numbers to harvest customer movements:
// that needs thousands of calls, and gets fifteen minutes of nothing
// instead.
rate_limit_enforce('track_feed', 150, 300, '', 900);

// When a super admin switches the live map off, coordinates are not just
// hidden on the page, they are left out of this feed too. Otherwise anyone
// could read them straight out of this endpoint and the switch would mean
// nothing. Place names and status still come through, so the timeline works.
$mapOn = live_map_enabled();

$tn = trim($_GET['tn'] ?? '');
if ($tn === '' || !is_valid_tracking_number($tn)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or malformed tracking number']);
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

// A shipment booked while the map was off has no coordinates. Casting
// those to a float would report 0,0, a real place in the Atlantic, so they
// are sent as null and the map leaves the point out.
$coord = static fn($value) => $value === null ? null : (float) $value;

if ($mapOn) {
    $payload += [
        'current_lat' => $coord($shipment['current_lat'] ?? $shipment['origin_lat']),
        'current_lng' => $coord($shipment['current_lng'] ?? $shipment['origin_lng']),
        'origin_lat' => $coord($shipment['origin_lat']),
        'origin_lng' => $coord($shipment['origin_lng']),
        'destination_lat' => $coord($shipment['destination_lat']),
        'destination_lng' => $coord($shipment['destination_lng']),
    ];
}

echo json_encode([
    'ok' => true,
    'shipment' => $payload,
    'events' => array_map(static function (array $e) use ($mapOn, $coord) {
        $out = [
            'id' => (int) $e['id'],
            'status' => $e['status'],
            'location_label' => $e['location_label'],
            'note' => $e['note'],
            'event_time' => $e['event_time'],
        ];
        if ($mapOn) {
            $out['lat'] = $coord($e['lat']);
            $out['lng'] = $coord($e['lng']);
        }
        return $out;
    }, $events),
]);
