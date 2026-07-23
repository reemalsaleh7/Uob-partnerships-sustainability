<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/LegacyAgreementCsvMapper.php';

function legacyMapperAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$path = __DIR__ . '/../uob-agreements/data/agreements.csv';
$handle = fopen($path, 'rb');
legacyMapperAssert($handle !== false, 'agreements.csv could not be opened');

$headers = fgetcsv($handle, 0, ',', '"', '');
legacyMapperAssert($headers !== false, 'agreements.csv has no header');
$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
$rows = [];
$line = 1;
while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    $line++;
    legacyMapperAssert(count($values) === count($headers), "Column mismatch on row {$line}");
    $raw = array_combine($headers, $values);
    legacyMapperAssert(is_array($raw), "Row {$line} could not be combined");
    $rows[] = ['raw' => $raw, 'mapped' => LegacyAgreementCsvMapper::map($raw, $line)];
}
fclose($handle);

legacyMapperAssert(count($rows) === 41, 'Expected 41 enriched legacy Agreement rows');
$codes = [];
$multiPartnerRows = 0;
foreach ($rows as $entry) {
    $mapped = $entry['mapped'];
    legacyMapperAssert($mapped['errors'] === [], 'Valid source row produced errors at row ' . $mapped['row_number']);
    $code = $mapped['agreement']['agreement_code'];
    legacyMapperAssert(!isset($codes[$code]), 'Duplicate Agreement code: ' . $code);
    $codes[$code] = true;
    legacyMapperAssert($mapped['agreement']['status'] === 'ACTIVE', 'Approved source status was not preserved');
    legacyMapperAssert(count($mapped['partners']) >= 1, 'Agreement has no normalized partner');
    if (count($mapped['partners']) > 1) {
        $multiPartnerRows++;
    }
}
legacyMapperAssert($multiPartnerRows === 2, 'Expected two multi-partner Agreement rows');

$first = $rows[0];
legacyMapperAssert($first['mapped']['agreement']['agreement_code'] === 'UOB-AGR-248', 'First code mapping failed');
legacyMapperAssert($first['mapped']['sdgs'] === [4, 9, 17], 'SDG mapping failed');
legacyMapperAssert($first['mapped']['rankings'] === ['QS_WORLD'], 'Ranking mapping failed');
legacyMapperAssert(count($first['mapped']['metrics']) === 3, 'Metric mapping failed');
legacyMapperAssert(
    LegacyAgreementCsvMapper::canonicalRowHash($first['raw'])
        === LegacyAgreementCsvMapper::canonicalRowHash(array_reverse($first['raw'], true)),
    'Canonical source hash changes with array key order'
);

$multi = null;
foreach ($rows as $entry) {
    if ($entry['mapped']['agreement']['agreement_code'] === 'UOB-AGR-295') {
        $multi = $entry['mapped'];
        break;
    }
}
legacyMapperAssert($multi !== null, 'Known multi-partner row was not found');
legacyMapperAssert(
    str_contains((string) $multi['partners'][0]['website'], 'youandkim'),
    'Multi-partner URL was attached to the wrong organization'
);
legacyMapperAssert(
    str_contains((string) $multi['partners'][1]['website'], 'techengbh'),
    'Second multi-partner URL was attached to the wrong organization'
);

$invalid = $first['raw'];
$invalid['agreement_code'] = '';
$invalid['end_date'] = 'not-a-date';
$invalidMapped = LegacyAgreementCsvMapper::map($invalid, 2);
legacyMapperAssert(count($invalidMapped['errors']) >= 2, 'Invalid source data was not rejected');

echo "Legacy Agreement CSV mapper smoke test passed.\n";
