<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$agreements = readAgreements();
echo "Agreements: " . count($agreements) . PHP_EOL;

$initiatives = loadAllInitiatives();
echo "Initiatives: " . count($initiatives) . PHP_EOL;

echo PHP_EOL . "Sample initiative:" . PHP_EOL;
print_r($initiatives[0] ?? []);