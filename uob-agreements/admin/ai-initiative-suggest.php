<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_email'])) {
  echo json_encode(['ok'=>false, 'error'=>'Not logged in']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$college = trim($input['college'] ?? '');
$department = trim($input['department'] ?? '');

if ($college === '' && $department === '') {
  echo json_encode(['ok'=>false, 'error'=>'Missing college or department']);
  exit;
}

$initiatives = loadAllInitiatives(false);

$existingTitles = [];
foreach ($initiatives as $it) {
  if (!empty($it['title'])) {
    $existingTitles[] = $it['title'];
  }
}

$existingText = implode("\n- ", array_slice($existingTitles, 0, 40));

$apiKey = OPENAI_API_KEY;

$prompt = "
You are an expert in university partnerships, sustainability, SDGs, and impact initiatives.

Create 3 creative initiative suggestions for University of Bahrain staff.

User college: $college
User department: $department

Existing initiatives that should NOT be repeated:
- $existingText

Rules:
- Ideas must be new and not similar to existing titles.
- Ideas must be suitable for university staff/faculty, not students only.
- Link each idea to a clear SDG.
- Make each idea practical and easy to request approval for.

Return ONLY valid JSON like this:
{
  \"suggestions\": [
    {
      \"title\": \"...\",
      \"summary\": \"...\",
      \"sdg\": \"SDG 4\",
      \"target_group\": \"...\",
      \"expected_outputs\": \"...\",
      \"why_suitable\": \"...\"
    }
  ]
}
";

$payload = [
  "model" => "gpt-4o-mini",
  "messages" => [
    ["role" => "user", "content" => $prompt]
  ],
  "temperature" => 0.7
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");

curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer " . $apiKey,
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
  echo json_encode(['ok'=>false, 'error'=>$error]);
  exit;
}

$data = json_decode($response, true);
$text = $data['choices'][0]['message']['content'] ?? '';

$text = trim($text);
$text = preg_replace('/^```json\s*/', '', $text);
$text = preg_replace('/^```\s*/', '', $text);
$text = preg_replace('/\s*```$/', '', $text);

$json = json_decode($text, true);

if (!$json) {
  echo json_encode([
    'ok'=>false,
    'error'=>'AI returned invalid JSON',
    'raw'=>$text
  ]);
  exit;
}

echo json_encode([
  'ok'=>true,
  'college'=>$college,
  'department'=>$department,
  'suggestions'=>$json['suggestions'] ?? []
], JSON_UNESCAPED_UNICODE);