<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementService.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function documentAuthorizationAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function documentListDenied(
    AgreementService $service,
    int $agreementId,
    int $userId
): bool {
    try {
        $service->listDocuments($agreementId, $userId);
    } catch (DomainException $exception) {
        return true;
    }

    return false;
}

$db = Database::connect();
$users = new UserRepository();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$agreementService = new AgreementService();
$approvalService = new ApprovalService();

$dean = $users->findByEmail('dev.dean@uob.test');
$vp = $users->findByEmail('dev.vp@uob.test');
$legal = $users->findByEmail('dev.legal@uob.test');

documentAuthorizationAssert(
    $dean !== null && $vp !== null && $legal !== null,
    'Required development users were not found'
);

$db->beginTransaction();

try {
    $agreementId = $agreements->create([
        'title' => 'Temporary Document Authorization Test',
        'agreement_type' => 'MOU',
        'description' => 'Rolled back after verification',
        'created_by' => (int) $dean['user_id'],
        'status' => 'DRAFT',
    ]);

    $versions->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Document security test version',
        'agreement_snapshot' => $agreements->findById($agreementId),
        'created_by' => (int) $dean['user_id'],
    ]);

    $draftDocuments = $agreementService->listDocuments(
        $agreementId,
        (int) $dean['user_id']
    );

    documentAuthorizationAssert(
        $draftDocuments['can_upload'] === true,
        'Draft creator was not allowed to upload documents'
    );

    documentAuthorizationAssert(
        documentListDenied(
            $agreementService,
            $agreementId,
            (int) $vp['user_id']
        ),
        'VP could see documents for an unsubmitted Dean draft'
    );

    $started = $approvalService->startAgreementWorkflow(
        $agreementId,
        (int) $dean['user_id']
    );
    $agreements->changeStatus($agreementId, 'UNDER_REVIEW');

    $creatorUnderReview = $agreementService->listDocuments(
        $agreementId,
        (int) $dean['user_id']
    );
    $initialVpDocuments = $agreementService->listDocuments(
        $agreementId,
        (int) $vp['user_id']
    );

    documentAuthorizationAssert(
        $creatorUnderReview['can_upload'] === false,
        'Creator could upload while the Agreement was under review'
    );
    documentAuthorizationAssert(
        $initialVpDocuments['can_upload'] === true,
        'Active Initial VP reviewer could not upload a document'
    );

    $approvalService->completeInitialVpReview(
        (int) $started['workflow_instance_id'],
        (int) $vp['user_id'],
        false,
        'Legal review only'
    );

    documentAuthorizationAssert(
        documentListDenied(
            $agreementService,
            $agreementId,
            (int) $vp['user_id']
        ),
        'VP retained document access after its assignment ended'
    );

    $legalDocuments = $agreementService->listDocuments(
        $agreementId,
        (int) $legal['user_id']
    );

    documentAuthorizationAssert(
        $legalDocuments['can_upload'] === true,
        'Active Legal reviewer could not upload a document'
    );

    $db->rollBack();
    echo "Agreement document authorization smoke test passed.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
