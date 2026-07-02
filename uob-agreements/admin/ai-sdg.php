<?php
header('Content-Type: application/json');

// 1. نقرأ البيانات
$input = json_decode(file_get_contents('php://input'), true);

$title = $input['title'] ?? '';
$desc  = $input['desc'] ?? '';

// لو فاضي
if (!$title && !$desc) {
  echo json_encode(['sdgs' => []]);
  exit;
}

// 2. مفتاح API (حطيه عندك)
$apiKey = "";  // 

// 3. البرومبت (تعليمات للذكاء)
$prompt = "
اقرأ هذه المبادرة:
العنوان: $title
الوصف: $desc

اختر أرقام أهداف التنمية المناسبة فقط (من 1 إلى 17)
وارجعها بهذا الشكل:
[4,9,17]

بدون شرح.
";

// 4. الاتصال بـ OpenAI
$ch = curl_init();

curl_setopt_array($ch, [
  CURLOPT_URL => "https://api.openai.com/v1/chat/completions",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS => json_encode([
    "model" => "gpt-4o-mini",
    "messages" => [
      ["role" => "user", "content" => $prompt]
    ],
    "temperature" => 0.2
  ])
]);

$response = curl_exec($ch);
curl_close($ch);

// 5. نحلل الرد
$data = json_decode($response, true);
$text = $data['choices'][0]['message']['content'] ?? '[]';

// نطلع الأرقام فقط
preg_match('/\[(.*?)\]/', $text, $matches);

$numbers = [];

if (!empty($matches[1])) {
  $numbers = array_map('trim', explode(',', $matches[1]));
}

// 6. نرجعها للـ JS
echo json_encode([
  'sdgs' => $numbers
]);