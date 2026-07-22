<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/DocumentStorageService.php';

function showcaseAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();

$agreementCount = (int) $db->query(
    "SELECT COUNT(*) FROM agreements WHERE agreement_code LIKE 'DEMO-%'"
)->fetchColumn();
showcaseAssert(
    $agreementCount === 6,
    'Expected six idempotent showcase Agreements'
);

$activeCount = (int) $db->query(
    "SELECT COUNT(*) FROM agreements
     WHERE agreement_code LIKE 'DEMO-%' AND status = 'ACTIVE'"
)->fetchColumn();
showcaseAssert(
    $activeCount === 5,
    'Expected five active showcase Agreements'
);

$reportCount = (int) $db->query(
    "SELECT COUNT(*)
     FROM agreement_performance_reports pr
     JOIN agreements a ON a.agreement_id = pr.agreement_id
     WHERE a.agreement_code LIKE 'DEMO-%'"
)->fetchColumn();
showcaseAssert(
    $reportCount === 5,
    'Expected five showcase reporting periods'
);

$acceptedCount = (int) $db->query(
    "SELECT COUNT(*)
     FROM agreement_performance_reports pr
     JOIN agreements a ON a.agreement_id = pr.agreement_id
     WHERE a.agreement_code LIKE 'DEMO-%'
       AND pr.status = 'ACCEPTED'"
)->fetchColumn();
showcaseAssert(
    $acceptedCount === 2,
    'Expected two accepted showcase annual reports'
);

$documents = $db->query(
    "SELECT ad.storage_key, ad.file_size_bytes, ad.sha256_checksum
     FROM agreement_documents ad
     JOIN agreements a ON a.agreement_id = ad.agreement_id
     WHERE a.agreement_code LIKE 'DEMO-%'
       AND ad.document_type = 'ANNUAL_REPORT'
     ORDER BY a.agreement_code"
)->fetchAll();
showcaseAssert(
    count($documents) === 4,
    'Expected four secure showcase annual-report documents'
);

$storage = new DocumentStorageService();
foreach ($documents as $document) {
    $path = $storage->absolutePath((string) $document['storage_key']);
    showcaseAssert($path !== null, 'A showcase annual-report file is unavailable');
    showcaseAssert(
        filesize($path) === (int) $document['file_size_bytes'],
        'A showcase annual-report file size does not match the database'
    );
    $checksum = hash_file('sha256', $path);
    showcaseAssert(
        is_string($checksum)
            && hash_equals((string) $document['sha256_checksum'], $checksum),
        'A showcase annual-report checksum does not match the database'
    );
}

$facultyCanView = (bool) $db->query(
    "SELECT EXISTS (
        SELECT 1
        FROM roles r
        JOIN role_permissions rp ON rp.role_id = r.role_id
        JOIN permissions p ON p.permission_id = rp.permission_id
        WHERE r.role_name = 'Initiative Creator'
          AND p.permission_code = 'VIEW_AGREEMENT'
     )"
)->fetchColumn();
showcaseAssert(
    $facultyCanView,
    'Initiative creators cannot view active Agreements'
);

$deanPortfolio = (int) $db->query(
    "SELECT COUNT(*)
     FROM agreements a
     JOIN users u ON u.user_id = a.created_by
     WHERE a.agreement_code LIKE 'DEMO-%'
       AND a.status = 'ACTIVE'
       AND u.email = 'dev.dean@uob.test'"
)->fetchColumn();
showcaseAssert(
    $deanPortfolio === 3,
    'Expected three active showcase Agreements in the Dean portfolio'
);

echo json_encode([
    'success' => true,
    'showcase_agreements' => $agreementCount,
    'active_agreements' => $activeCount,
    'annual_reports' => $reportCount,
    'secure_report_documents' => count($documents),
    'dean_active_portfolio' => $deanPortfolio,
    'faculty_active_agreement_access' => true,
    'message' => 'Workspace showcase data smoke test passed',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
