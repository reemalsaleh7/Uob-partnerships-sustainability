<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$text = trim($data['text'] ?? '');

if ($text === '') {
  echo json_encode(['result' => '']);
  exit;
}

// 🔥 ترجمة مجانية بدون API key
$url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=ar&tl=en&dt=t&q=" . urlencode($text);

$response = file_get_contents($url);

$result = '';

if ($response) {
  $json = json_decode($response, true);
  $result = $json[0][0][0] ?? '';
}

echo json_encode([
  'result' => $result
]);