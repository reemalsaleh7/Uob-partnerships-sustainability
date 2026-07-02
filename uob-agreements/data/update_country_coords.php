<?php
// data/update_country_coords.php
// يستقبل: POST { country, lat, lng } ويحدّث country_coords.json

header('Content-Type: application/json; charset=utf-8');

$country = trim($_POST['country'] ?? '');
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;

if ($country === '' || $lat === null || $lng === null) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_fields'], JSON_UNESCAPED_UNICODE);
  exit;
}

$lat = floatval($lat);
$lng = floatval($lng);

$path = __DIR__ . '/country_coords.json';
if (!file_exists($path)) file_put_contents($path, "{}");

$raw = file_get_contents($path);
$data = json_decode($raw, true);
if (!is_array($data)) $data = [];

$data[$country] = ['lat'=>$lat, 'lng'=>$lng];

file_put_contents(
  $path,
  json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);