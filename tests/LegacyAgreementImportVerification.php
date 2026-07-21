<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/PublicAgreementRepository.php';

function legacyImportAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$count = (int) $db->query("SELECT COUNT(*) FROM agreement_legacy_imports
    WHERE source_file = 'agreements.csv'")->fetchColumn();
legacyImportAssert($count === 41, "Expected 41 tracked imports, found {$count}");

$bad = (int) $db->query("SELECT COUNT(*)
    FROM agreement_legacy_imports ali
    JOIN agreements a ON a.agreement_id = ali.agreement_id
    WHERE ali.source_file = 'agreements.csv'
      AND (
        a.status <> 'ACTIVE'
        OR a.agreement_code IS NULL
        OR a.source_record_id IS NULL
      )")->fetchColumn();
legacyImportAssert($bad === 0, 'Imported Agreements have invalid status or source identity');

$badVersions = (int) $db->query("SELECT COUNT(*) FROM (
    SELECT ali.agreement_id, COUNT(av.version_id) AS version_count
    FROM agreement_legacy_imports ali
    LEFT JOIN agreement_versions av ON av.agreement_id = ali.agreement_id
    WHERE ali.source_file = 'agreements.csv'
    GROUP BY ali.agreement_id
    HAVING COUNT(av.version_id) <> 1
) invalid_versions")->fetchColumn();
legacyImportAssert($badVersions === 0, 'Each imported Agreement must have exactly one immutable version');

$workflowCount = (int) $db->query("SELECT COUNT(*)
    FROM agreement_legacy_imports ali
    JOIN workflow_instances wi
      ON wi.entity_type = 'AGREEMENT' AND wi.entity_id = ali.agreement_id
    WHERE ali.source_file = 'agreements.csv'")->fetchColumn();
legacyImportAssert($workflowCount === 0, 'Historical imports must not create approval workflow instances');

$missingAudit = (int) $db->query("SELECT COUNT(*)
    FROM agreement_legacy_imports ali
    WHERE ali.source_file = 'agreements.csv'
      AND NOT EXISTS (
        SELECT 1 FROM audit_logs al
        WHERE al.table_name = 'agreements'
          AND al.record_id = ali.agreement_id
          AND al.action = 'INSERT'
          AND al.reason LIKE 'Controlled legacy CSV import%'
      )")->fetchColumn();
legacyImportAssert($missingAudit === 0, 'Imported Agreement audit entries are missing');

$public = new PublicAgreementRepository();
$publishedCodes = array_flip(array_column($public->findPublished(), 'public_reference'));
$importedCodes = $db->query("SELECT a.agreement_code
    FROM agreement_legacy_imports ali
    JOIN agreements a ON a.agreement_id = ali.agreement_id
    WHERE ali.source_file = 'agreements.csv'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($importedCodes as $code) {
    legacyImportAssert(isset($publishedCodes[$code]), 'Imported Agreement is absent from public catalogue: ' . $code);
}

echo "Legacy Agreement import verification passed.\n";
