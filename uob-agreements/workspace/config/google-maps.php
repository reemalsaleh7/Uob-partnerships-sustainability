<?php

declare(strict_types=1);

$apiKey = trim((string) getenv('GOOGLE_MAPS_API_KEY'));
$localConfigPath = __DIR__ . '/google-maps.local.php';

if ($apiKey === '' && is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (is_array($localConfig)) {
        $apiKey = trim((string) ($localConfig['api_key'] ?? ''));
    }
}

if (
    $apiKey === 'PASTE_GOOGLE_MAPS_API_KEY_HERE'
    || $apiKey === 'YOUR_GOOGLE_MAPS_API_KEY'
) {
    $apiKey = '';
}

return [
    'api_key' => $apiKey,
];
