<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementOperationService.php';
require_once __DIR__ . '/../services/AgreementService.php';

function operationalAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function operationalUserId(PDO $db, string $email): int
{
    $statement = $db->prepare('SELECT user_id FROM users WHERE email = :email');
    $statement->execute(['email' => $email]);
    $userId = $statement->fetchColumn();
    if ($userId === false) {
        throw new RuntimeException("Development user {$email} was not found");
    }
    return (int) $userId;
}

function operationalAgreement(
    AgreementRepository $agreements,
    AgreementVersionRepository $versions,
    int $creatorId,
    int $partnerId,
    string $title
): int {
    $agreementId = $agreements->create([
        'title' => $title,
        'agreement_type' => 'Memorandum of Understanding',
        'description' => 'Rollback-only operational status test Agreement.',
        'start_date' => '2026-01-01',
        'end_date' => '2100-12-31',
        'created_by' => $creatorId,
        'status' => 'APPROVED',
    ]);
    $agreements->replacePartners($agreementId, [$partnerId]);
    $versions->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Operational status smoke test',
        'agreement_snapshot' => $agreements->findById($agreementId),
        'created_by' => $creatorId,
    ]);
    return $agreementId;
}

function operationalDocument(
    PDO $db,
    int $agreementId,
    int $uploadedBy,
    array &$createdPaths
): int {
    $storageKey = date('Y/m') . '/' . bin2hex(random_bytes(32)) . '.pdf';
    $absolutePath = dirname(__DIR__)
        . '/storage/private/agreement-documents/' . $storageKey;
    if (!is_dir(dirname($absolutePath))) {
        mkdir(dirname($absolutePath), 0750, true);
    }
    $contents = "%PDF-1.4\n% rollback-only operational smoke test\n%%EOF\n";
    file_put_contents($absolutePath, $contents);
    $createdPaths[] = $absolutePath;

    $version = $db->prepare(
        'SELECT version_id FROM agreement_versions
         WHERE agreement_id = :agreement_id
         ORDER BY version_number DESC LIMIT 1'
    );
    $version->execute(['agreement_id' => $agreementId]);
    $statement = $db->prepare(
        'INSERT INTO agreement_documents (
            agreement_id, agreement_version_id, file_name, storage_key,
            mime_type, file_size_bytes, sha256_checksum, document_type,
            uploaded_by, uploaded_at
         ) VALUES (
            :agreement_id, :agreement_version_id, :file_name, :storage_key,
            :mime_type, :file_size_bytes, :sha256_checksum,
            \'SIGNED_AGREEMENT\', :uploaded_by, NOW()
         ) RETURNING document_id'
    );
    $statement->execute([
        'agreement_id' => $agreementId,
        'agreement_version_id' => (int) $version->fetchColumn(),
        'file_name' => 'operational-smoke-signed.pdf',
        'storage_key' => $storageKey,
        'mime_type' => 'application/pdf',
        'file_size_bytes' => strlen($contents),
        'sha256_checksum' => hash('sha256', $contents),
        'uploaded_by' => $uploadedBy,
    ]);
    return (int) $statement->fetchColumn();
}

function operationalSigningInput(
    int $documentId,
    int $partnerId,
    string $effectiveDate,
    string $expiryDate
): array {
    return [
        'signed_document_id' => $documentId,
        'signing_date' => '2026-07-21',
        'effective_date' => $effectiveDate,
        'expiry_date' => $expiryDate,
        'venue' => 'University of Bahrain',
        'public_announcement_url' => 'https://www.uob.edu.bh/',
        'ceremony_notes' => 'Rollback-only smoke test.',
        'signatories' => [
            [
                'party_type' => 'UOB',
                'full_name' => 'UOB Test Signatory',
                'job_title' => 'Authorized Representative',
                'organization_name' => 'University of Bahrain',
            ],
            [
                'party_type' => 'PARTNER',
                'partner_id' => $partnerId,
                'full_name' => 'Partner Test Signatory',
                'job_title' => 'Authorized Representative',
                'organization_name' => 'Operational Test Partner',
            ],
        ],
    ];
}

$db = Database::connect();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$operations = new AgreementOperationService();
$agreementService = new AgreementService();
$createdPaths = [];

$db->beginTransaction();
try {
    $creator = operationalUserId($db, 'dev.dean@uob.test');
    $admin = operationalUserId($db, 'dev.admin@uob.test');
    $partnerId = (int) $db->query(
        'SELECT partner_id FROM partners ORDER BY partner_id LIMIT 1'
    )->fetchColumn();
    operationalAssert($partnerId > 0, 'A development partner is required');

    $today = new DateTimeImmutable('today');
    $activeAgreementId = operationalAgreement(
        $agreements,
        $versions,
        $creator,
        $partnerId,
        'Immediate operational activation smoke test'
    );
    $activeDocumentId = operationalDocument(
        $db,
        $activeAgreementId,
        $creator,
        $createdPaths
    );
    $activeResult = $operations->finalizeSigning(
        $activeAgreementId,
        $creator,
        operationalSigningInput(
            $activeDocumentId,
            $partnerId,
            $today->format('Y-m-d'),
            $today->modify('+1 day')->format('Y-m-d')
        )
    );
    operationalAssert($activeResult['status'] === 'ACTIVE', 'Due Agreement was not activated');
    $summary = $operations->summary($activeAgreementId, $creator);
    operationalAssert($summary !== null, 'Operational summary was not returned');
    operationalAssert($summary['operational_state'] === 'ACTIVE', 'Active state is incorrect');
    operationalAssert(count($summary['signing_record']['signatory_snapshot']) === 2, 'Signatory snapshot is incomplete');
    operationalAssert(count($summary['status_events']) === 1, 'Activation event was not recorded once');

    try {
        $operations->finalizeSigning(
            $activeAgreementId,
            $creator,
            operationalSigningInput(
                $activeDocumentId,
                $partnerId,
                $today->format('Y-m-d'),
                $today->modify('+1 day')->format('Y-m-d')
            )
        );
        throw new RuntimeException('Duplicate signing finalization was accepted');
    } catch (DomainException $expected) {
        operationalAssert(
            str_contains($expected->getMessage(), 'APPROVED')
                || str_contains($expected->getMessage(), 'already'),
            'Unexpected duplicate-finalization error'
        );
    }

    try {
        $agreementService->deleteDocument($activeDocumentId, $admin);
        throw new RuntimeException('Final signed document was deleted');
    } catch (DomainException $expected) {
        operationalAssert(
            str_contains($expected->getMessage(), 'finalized signed'),
            'Unexpected signed-document protection error'
        );
    }

    $scheduledAgreementId = operationalAgreement(
        $agreements,
        $versions,
        $creator,
        $partnerId,
        'Scheduled activation and expiry smoke test'
    );
    $scheduledDocumentId = operationalDocument(
        $db,
        $scheduledAgreementId,
        $creator,
        $createdPaths
    );
    $scheduledResult = $operations->finalizeSigning(
        $scheduledAgreementId,
        $creator,
        operationalSigningInput(
            $scheduledDocumentId,
            $partnerId,
            '2099-01-01',
            '2099-12-31'
        )
    );
    operationalAssert($scheduledResult['status'] === 'APPROVED', 'Future Agreement activated early');
    operationalAssert($scheduledResult['operational_state'] === 'SCHEDULED', 'Future Agreement was not scheduled');

    $operations->synchronize(new DateTimeImmutable('2099-01-01'));
    operationalAssert(
        $agreements->findById($scheduledAgreementId)['status'] === 'ACTIVE',
        'Scheduled Agreement did not activate on its effective date'
    );
    $operations->synchronize(new DateTimeImmutable('2100-01-01'));
    operationalAssert(
        $agreements->findById($scheduledAgreementId)['status'] === 'EXPIRED',
        'Active Agreement did not expire after its expiry date'
    );
    $operations->synchronize(new DateTimeImmutable('2100-01-01'));
    $eventCount = $db->prepare(
        'SELECT COUNT(*) FROM agreement_status_events
         WHERE agreement_id = :agreement_id'
    );
    $eventCount->execute(['agreement_id' => $scheduledAgreementId]);
    operationalAssert((int) $eventCount->fetchColumn() === 2, 'Status synchronization was not idempotent');

    $db->rollBack();
    echo "Agreement operational status smoke test passed; transaction rolled back.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    foreach ($createdPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
