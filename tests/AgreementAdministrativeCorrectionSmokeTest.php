<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementService.php';

function correctionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$userRepository = new UserRepository();
$agreementRepository = new AgreementRepository();
$versionRepository = new AgreementVersionRepository();
$agreementService = new AgreementService();

$administrator = $userRepository->findByEmail('dev.admin@uob.test');
$dean = $userRepository->findByEmail('dev.dean@uob.test');
$partnerId = $db->query(
    'SELECT partner_id FROM partners WHERE is_active = TRUE ORDER BY partner_id LIMIT 1'
)->fetchColumn();

correctionAssert($administrator !== null, 'Development administrator was not found');
correctionAssert($dean !== null, 'Development Dean was not found');
correctionAssert($partnerId !== false, 'At least one active partner is required');

$db->beginTransaction();

try {
    $agreementId = $agreementRepository->create([
        'title' => 'Legacy Agreement Before Correction',
        'agreement_type' => 'MOU',
        'description' => 'Historical Agreement imported for correction verification.',
        'geographic_scope' => 'LOCAL',
        'start_date' => '2024-01-01',
        'end_date' => '2026-12-31',
        'created_by' => (int) $administrator['user_id'],
        'status' => 'ACTIVE',
        'record_origin' => 'LEGACY_IMPORT',
    ]);
    $agreementRepository->replacePartners($agreementId, [(int) $partnerId]);

    $tracking = $db->prepare('
        INSERT INTO agreement_legacy_imports (
            import_batch_id, source_file, source_row_number, source_record_id,
            source_hash, agreement_id, imported_by, source_payload, import_warnings
        ) VALUES (
            gen_random_uuid(), :source_file, 2, :source_record_id,
            :source_hash, :agreement_id, :imported_by,
            CAST(:source_payload AS JSONB), CAST(\'[]\' AS JSONB)
        )
    ');
    $tracking->execute([
        'source_file' => 'administrative-correction-smoke.csv',
        'source_record_id' => 'CORRECTION-SMOKE-' . $agreementId,
        'source_hash' => hash('sha256', 'administrative-correction-smoke-' . $agreementId),
        'agreement_id' => $agreementId,
        'imported_by' => (int) $administrator['user_id'],
        'source_payload' => json_encode(
            ['owner_entity' => 'Legacy University Office', 'original_title' => 'Legacy Agreement Before Correction'],
            JSON_THROW_ON_ERROR
        ),
    ]);

    $original = $agreementRepository->findById($agreementId);
    correctionAssert($original !== null, 'Synthetic legacy Agreement could not be loaded');
    $versionRepository->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Controlled legacy import for correction test',
        'agreement_snapshot' => $original,
        'created_by' => (int) $administrator['user_id'],
    ]);

    $payload = [
        'title' => 'Legacy Agreement After Verified Correction',
        'description' => 'Historical Agreement with its inaccurate title corrected.',
        'partner_ids' => [(int) $partnerId],
        'correction_reason' => 'Corrected the title against the signed legacy Agreement document.',
    ];

    $nonAdministratorDenied = false;
    try {
        $agreementService->administrativelyCorrectLegacyAgreement(
            $agreementId,
            $payload,
            (int) $dean['user_id']
        );
    } catch (DomainException $exception) {
        $nonAdministratorDenied = str_contains(
            $exception->getMessage(),
            'System Administrator'
        );
    }
    correctionAssert(
        $nonAdministratorDenied,
        'A non-administrator was allowed to correct a legacy Agreement'
    );

    $result = $agreementService->administrativelyCorrectLegacyAgreement(
        $agreementId,
        $payload,
        (int) $administrator['user_id']
    );
    correctionAssert($result['success'] === true, 'Administrative correction failed');
    correctionAssert($result['version_number'] === 2, 'Correction did not create version 2');

    $corrected = $agreementRepository->findById($agreementId);
    correctionAssert($corrected !== null, 'Corrected Agreement could not be loaded');
    correctionAssert(
        $corrected['title'] === 'Legacy Agreement After Verified Correction',
        'Corrected title was not saved'
    );
    correctionAssert($corrected['status'] === 'ACTIVE', 'Correction changed Agreement status');
    correctionAssert(
        $corrected['record_origin'] === 'LEGACY_IMPORT',
        'Correction changed legacy provenance'
    );
    correctionAssert(
        count($corrected['administrative_corrections']) === 1,
        'Structured correction history was not recorded'
    );

    $versions = $versionRepository->findByAgreement($agreementId);
    correctionAssert(count($versions) === 2, 'Original and corrected versions were not preserved');
    correctionAssert(
        str_starts_with((string) $versions[0]['change_summary'], 'Administrative correction:'),
        'Correction version is not clearly identified'
    );

    $provenance = $db->prepare('
        SELECT source_file, source_payload->>\'original_title\' AS original_title
        FROM agreement_legacy_imports
        WHERE agreement_id = :agreement_id
    ');
    $provenance->execute(['agreement_id' => $agreementId]);
    $source = $provenance->fetch();
    correctionAssert(
        $source !== false
            && $source['source_file'] === 'administrative-correction-smoke.csv'
            && $source['original_title'] === 'Legacy Agreement Before Correction',
        'Legacy source provenance was overwritten'
    );

    $audit = $db->prepare('
        SELECT reason
        FROM audit_logs
        WHERE table_name = \'agreements\'
          AND record_id = :agreement_id
          AND action = \'UPDATE\'
        ORDER BY audit_id DESC
        LIMIT 1
    ');
    $audit->execute(['agreement_id' => $agreementId]);
    correctionAssert(
        str_starts_with((string) $audit->fetchColumn(), 'Administrative correction:'),
        'Correction reason was not written to the audit log'
    );

    echo "Agreement administrative correction smoke test passed; transaction rolled back.\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
