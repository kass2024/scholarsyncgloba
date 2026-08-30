<?php
require_once __DIR__ . '/helpers/env_load.php';
xander_load_env_file();
$key = xander_env_get('GOOGLE_MAPS_API_KEY');

if ($key === '') {
  http_response_code(503);
  echo json_encode(['error' => 'Maps service is not configured']);
  exit;
}

$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';

if (!$lat || !$lng) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing lat/lng']);
  exit;
}

// Call Google Places Nearby Search REST API
$url = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=$lat,$lng&radius=100&key=$key";

$response = file_get_contents($url);

header('Content-Type: application/json');
echo $response;
