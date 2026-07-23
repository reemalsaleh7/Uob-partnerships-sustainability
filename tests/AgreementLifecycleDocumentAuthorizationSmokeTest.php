<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../services/AgreementLifecycleService.php';

function lifecycleDocumentAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function lifecycleDocumentsDenied(
    AgreementLifecycleService $service,
    int $requestId,
    int $userId
): bool {
    try {
        $service->listDocuments($requestId, $userId);
    } catch (DomainException $exception) {
        return true;
    }
    return false;
}

$db = Database::connect();
$users = new UserRepository();
$agreements = new AgreementRepository();
$service = new AgreementLifecycleService();

$dean = $users->findByEmail('dev.dean@uob.test');
$vp = $users->findByEmail('dev.vp@uob.test');
$legal = $users->findByEmail('dev.legal@uob.test');

lifecycleDocumentAssert(
    $dean !== null && $vp !== null && $legal !== null,
    'Required development users were not found'
);

$db->beginTransaction();
try {
    $agreementId = $agreements->create([
        'title' => 'Temporary Lifecycle Document Authorization Test',
        'agreement_type' => 'MOU',
        'description' => 'Rolled back after verification',
        'created_by' => (int) $dean['user_id'],
        'status' => 'APPROVED',
    ]);
    $created = $service->create(
        $agreementId,
        (int) $dean['user_id'],
        [
            'request_type' => 'AMENDMENT',
            'justification' => 'Authorization smoke test',
            'amendment_type' => 'Administrative',
            'amendment_reason' => 'Verify private document access',
            'terms_to_amend' => 'Temporary test terms',
            'financial_amount' => null,
        ]
    );
    lifecycleDocumentAssert($created['success'] === true, 'Draft creation failed');
    $requestId = (int) $created['lifecycle_request_id'];

    $creatorDraft = $service->listDocuments(
        $requestId,
        (int) $dean['user_id']
    );
    lifecycleDocumentAssert(
        $creatorDraft['can_upload'] === true,
        'Draft requester could not upload a document'
    );
    lifecycleDocumentAssert(
        lifecycleDocumentsDenied($service, $requestId, (int) $vp['user_id']),
        'VP could access documents for a private draft'
    );

    $submitted = $service->submit($requestId, (int) $dean['user_id']);
    lifecycleDocumentAssert($submitted['success'] === true, 'Submission failed');

    $creatorReview = $service->listDocuments(
        $requestId,
        (int) $dean['user_id']
    );
    $vpReview = $service->listDocuments(
        $requestId,
        (int) $vp['user_id']
    );
    lifecycleDocumentAssert(
        $creatorReview['can_upload'] === false,
        'Requester could upload while the request was under review'
    );
    lifecycleDocumentAssert(
        $vpReview['can_upload'] === true,
        'Active Initial VP could not upload a review document'
    );

    $service->decide(
        (int) $submitted['workflow_instance_id'],
        (int) $vp['user_id'],
        'APPROVE',
        'Route to Legal',
        false
    );
    lifecycleDocumentAssert(
        lifecycleDocumentsDenied($service, $requestId, (int) $vp['user_id']),
        'VP retained document access after its assignment closed'
    );
    $legalReview = $service->listDocuments(
        $requestId,
        (int) $legal['user_id']
    );
    lifecycleDocumentAssert(
        $legalReview['can_upload'] === true,
        'Active Legal reviewer could not upload a document'
    );

    $db->rollBack();
    echo "Lifecycle request document authorization smoke test passed.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
