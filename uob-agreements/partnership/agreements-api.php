<?php
header('Content-Type: application/json; charset=utf-8');

$csvFile = __DIR__ . '/../data/agreements.csv';

if (!file_exists($csvFile)) {
    echo json_encode([]);
    exit;
}

$rows = [];

if (($handle = fopen($csvFile, 'r')) !== false) {

    $headers = fgetcsv($handle);

    while (($data = fgetcsv($handle)) !== false) {

        $row = array_combine($headers, $data);

        // فقط الاتفاقيات المعتمدة
        if (($row['admin_status'] ?? '') !== 'معتمد') {
            continue;
        }

        $rows[] = [
    "Record ID" => $row["agreement_code"] ?? "",
    "Agreement Title" => $row["agreement_name"] ?? "",
    "Partner Institution" => $row["partner_entity"] ?? "",
    "Partner Type" => $row["entity_type"] ?? "",
    "Country" => $row["country"] ?? "",
    "City" => $row["city"] ?? "",
    "Agreement Type" => $row["agreement_type"] ?? "",
    "Start Date" => $row["start_date"] ?? "",
    "End Date" => $row["end_date"] ?? "",
    "Implementing Unit" => $row["owner_entity"] ?? "",
    "Focus Area" => $row["focus_area"] ?? "",
    "Agreement Summary" => $row["agreement_summary"] ?? "",
    "SDGs" => $row["sdgs"] ?? "",
    "Supports QS Ranking" => $row["supports_qs_ranking"] ?? "",
    "Supports UI GreenMetric" => $row["supports_ui_greenmetric"] ?? "",
    "Partner Website" => $row["partner_website"] ?? "",
    "Agreement Signing Link" => $row["agreement_signing_link"] ?? "",
    "Students Exchanged" => $row["students_exchanged"] ?? "",
    "Faculty Exchanged" => $row["faculty_exchanged"] ?? "",
    "Joint Programs" => $row["joint_programs"] ?? "",
    "Latitude" => $row["latitude"] ?? "",
    "Longitude" => $row["longitude"] ?? ""
];
    }

    fclose($handle);
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE);