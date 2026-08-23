<?php
/**
 * Address -> coordinates lookup, proxied server-side to OpenStreetMap's
 * Nominatim geocoder (https://nominatim.org) — free and keyless, same
 * project that provides our map tiles. No third-party paid API involved.
 *
 * Admin-only: this makes an outbound request per call, and Nominatim's
 * usage policy caps public use at 1 request/second, so it's gated behind
 * login rather than exposed on the public site.
 *
 * Nominatim requires a real identifying User-Agent on every request — see
 * https://operations.osmfoundation.org/policies/nominatim/
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing address.']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'format' => 'jsonv2',
    'q' => $q,
    'limit' => 1,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'User-Agent: ' . str_replace(' ', '', get_site_name()) . 'Tracker/1.0 (' . get_site_url() . ')',
        'Accept-Language: en',
    ],
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Lookup service unavailable (' . ($curlError ?: 'HTTP ' . $httpCode) . ').']);
    exit;
}

$results = json_decode($response, true);
if (!is_array($results) || empty($results)) {
    echo json_encode(['ok' => false, 'error' => 'No location found for that address. Try a nearby city or landmark, or enter coordinates manually.']);
    exit;
}

$first = $results[0];
echo json_encode([
    'ok' => true,
    'lat' => (float) $first['lat'],
    'lng' => (float) $first['lon'],
    'display_name' => $first['display_name'],
]);
