<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../includes/functions.php';

/**
 * Exact partner coordinates are preferred. For older partner records that do
 * not have them yet, use a stable country centre so every published Agreement
 * can still be discovered on the public map.
 */
function publicMapCountryCentres(): array
{
    $centres = [
        'australia' => [-25.2744, 133.7751],
        'bahrain' => [26.0667, 50.5577],
        'belgium' => [50.5039, 4.4699],
        'canada' => [56.1304, -106.3468],
        'china' => [35.8617, 104.1954],
        'denmark' => [56.2639, 9.5018],
        'egypt' => [26.8206, 30.8025],
        'finland' => [61.9241, 25.7482],
        'france' => [46.2276, 2.2137],
        'germany' => [51.1657, 10.4515],
        'india' => [20.5937, 78.9629],
        'italy' => [41.8719, 12.5674],
        'japan' => [36.2048, 138.2529],
        'jordan' => [30.5852, 36.2384],
        'kuwait' => [29.3117, 47.4818],
        'lebanon' => [33.8547, 35.8623],
        'malaysia' => [4.2105, 101.9758],
        'morocco' => [31.7917, -7.0926],
        'netherlands' => [52.1326, 5.2913],
        'new zealand' => [-40.9006, 174.8860],
        'norway' => [60.4720, 8.4689],
        'oman' => [21.4735, 55.9754],
        'pakistan' => [30.3753, 69.3451],
        'poland' => [51.9194, 19.1451],
        'qatar' => [25.3548, 51.1839],
        'saudi arabia' => [23.8859, 45.0792],
        'singapore' => [1.3521, 103.8198],
        'south africa' => [-30.5595, 22.9375],
        'south korea' => [35.9078, 127.7669],
        'spain' => [40.4637, -3.7492],
        'sweden' => [60.1282, 18.6435],
        'switzerland' => [46.8182, 8.2275],
        'tunisia' => [33.8869, 9.5375],
        'turkey' => [38.9637, 35.2433],
        'united arab emirates' => [23.4241, 53.8478],
        'uae' => [23.4241, 53.8478],
        'united kingdom' => [55.3781, -3.4360],
        'united states' => [37.0902, -95.7129],
        'united states of america' => [37.0902, -95.7129],
    ];

    $legacyPath = __DIR__ . '/../data/agreements.csv';
    foreach (readCsvRows($legacyPath) as $index => $row) {
        if ($index === 0) {
            $header = array_flip($row);
            continue;
        }
        if (!isset($header)) {
            break;
        }
        $country = trim((string) ($row[$header['country'] ?? -1] ?? ''));
        $latitude = (float) ($row[$header['latitude'] ?? -1] ?? 0);
        $longitude = (float) ($row[$header['longitude'] ?? -1] ?? 0);
        if ($country !== '' && $latitude !== 0.0 && $longitude !== 0.0) {
            $key = strtolower($country);
            $centres[$key] ??= [$latitude, $longitude];
        }
    }

    return $centres;
}

$countryCentres = publicMapCountryCentres();
$rows = [];
foreach (readAgreements(true) as $agreement) {
    $latitude = (float) ($agreement['latitude'] ?? 0);
    $longitude = (float) ($agreement['longitude'] ?? 0);
    if ($latitude === 0.0 || $longitude === 0.0) {
        $countryKey = strtolower(trim((string) (
            $agreement['country'] ?? ''
        )));
        [$latitude, $longitude] = $countryCentres[$countryKey]
            ?? [0.0, 0.0];
    }

    $rows[] = [
        'Record ID' => $agreement['agreement_code'] ?? '',
        'Agreement Title' => $agreement['agreement_name'] ?? '',
        'Partner Institution' => $agreement['partner_entity'] ?? '',
        'Partner Type' => $agreement['entity_type'] ?? '',
        'Country' => $agreement['country'] ?? '',
        'City' => $agreement['city'] ?? '',
        'Agreement Type' => $agreement['agreement_type'] ?? '',
        'Start Date' => $agreement['start_date'] ?? '',
        'End Date' => $agreement['end_date'] ?? '',
        'Implementing Unit' => $agreement['owner_entity'] ?? '',
        'Focus Area' => $agreement['focus_area'] ?? '',
        'Agreement Summary' => $agreement['agreement_summary'] ?? '',
        'SDGs' => $agreement['sdgs'] ?? '',
        'Partner Website' => $agreement['partner_website'] ?? '',
        'Students Exchanged' => $agreement['students_exchanged'] ?? '',
        'Trained Students' => $agreement['trained_students'] ?? '',
        'Faculty Exchanged' => $agreement['faculty_exchanged'] ?? '',
        'Joint Programs' => $agreement['joint_programs'] ?? '',
        'Latitude' => $latitude !== 0.0 ? $latitude : '',
        'Longitude' => $longitude !== 0.0 ? $longitude : '',
        'Status' => $agreement['status_code'] ?? '',
    ];
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
