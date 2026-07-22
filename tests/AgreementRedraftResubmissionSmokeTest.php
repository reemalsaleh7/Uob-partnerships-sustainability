<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function redraftAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function redraftStatuses(
    WorkflowRepository $repository,
    int $instanceId
): array {
    $statuses = [];

    foreach ($repository->findSteps($instanceId) as $step) {
        $statuses[$step['step_key']] =
            $step['status'];
    }

    return $statuses;
}

function redraftInboxContains(
    WorkflowRepository $repository,
    int $userId,
    int $instanceId,
    string $stepKey
): bool {
    foreach (
        $repository->findInboxForUser($userId)
        as $item
    ) {
        if (
            (int) $item['workflow_instance_id']
                === $instanceId
            && $item['step_key'] === $stepKey
        ) {
            return true;
        }
    }

    return false;
}

$db = Database::connect();

$userRepository = new UserRepository();
$agreementRepository = new AgreementRepository();
$versionRepository =
    new AgreementVersionRepository();
$workflowRepository = new WorkflowRepository();
$approvalService = new ApprovalService();

$dean = $userRepository->findByEmail(
    'dev.dean@uob.test'
);

$vp = $userRepository->findByEmail(
    'dev.vp@uob.test'
);

$legal = $userRepository->findByEmail(
    'dev.legal@uob.test'
);

redraftAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null,
    'Required development workflow users were not found'
);

$deanId = (int) $dean['user_id'];
$vpId = (int) $vp['user_id'];
$legalId = (int) $legal['user_id'];

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Redraft Resubmission Test',
            'agreement_type' =>
                'MOU',
            'description' =>
                'Original Agreement description',
            'created_by' =>
                $deanId,
            'status' =>
                'DRAFT',
        ]);

    $initialSnapshot =
        $agreementRepository->findById(
            $agreementId
        );

    redraftAssert(
        $initialSnapshot !== null,
        'Initial Agreement was not found'
    );

    $versionRepository->create(
        $agreementId,
        [
            'version_number' => 1,
            'change_summary' =>
                'Initial Agreement version',
            'agreement_snapshot' =>
                $initialSnapshot,
            'created_by' =>
                $deanId,
        ]
    );

    $started =
        $approvalService
            ->startAgreementWorkflow(
                $agreementId,
                $deanId
            );

    $instanceId =
        (int) $started['workflow_instance_id'];

    $approvalService
        ->completeInitialVpReview(
            $instanceId,
            $vpId,
            true,
            'Legal and Finance reviews required'
        );

    $approvalService
        ->requestAgreementChanges(
            $instanceId,
            'LEGAL_REVIEW',
            $legalId,
            'The legal clause requires revision'
        );

    $creatorRoute =
        $approvalService
            ->routeAgreementChangeRequest(
                $instanceId,
                $vpId,
                'CREATOR',
                'Creator must revise the requested clause'
            );

    redraftAssert(
        $creatorRoute['redraft_base_version']
            === 1,
        'Redraft baseline was not recorded as version 1'
    );

    /*
     * Attempt 1: no new version exists.
     * Resubmission must be rejected.
     */
    $unchangedResubmissionRejected = false;

    try {
        $approvalService
            ->resubmitAgreementAfterRedraft(
                $instanceId,
                $deanId,
                'Attempt without an updated version'
            );
    } catch (DomainException $exception) {
        $unchangedResubmissionRejected =
            str_contains(
                $exception->getMessage(),
                'updated and versioned'
            );
    }

    redraftAssert(
        $unchangedResubmissionRejected,
        'Unchanged Agreement was allowed to resubmit'
    );

    $beforeRevision =
        $workflowRepository->findInstanceById(
            $instanceId
        );

    redraftAssert(
        $beforeRevision !== null
        && (int) $beforeRevision[
            'redraft_base_version'
        ] === 1,
        'Redraft baseline was lost after rejected resubmission'
    );

    /*
     * Creator revises the Agreement and creates version 2.
     */
    $agreementRepository->update(
        $agreementId,
        [
            'description' =>
                'Revised legal clause and implementation obligations',
        ]
    );

    $revisedSnapshot =
        $agreementRepository->findById(
            $agreementId
        );

    redraftAssert(
        $revisedSnapshot !== null,
        'Revised Agreement was not found'
    );

    $versionRepository->create(
        $agreementId,
        [
            'version_number' => 2,
            'change_summary' =>
                'Revised after Legal change request',
            'agreement_snapshot' =>
                $revisedSnapshot,
            'created_by' =>
                $deanId,
        ]
    );

    $resubmissionResult =
        $approvalService
            ->resubmitAgreementAfterRedraft(
                $instanceId,
                $deanId,
                'Legal clause revised in version 2'
            );

    $statuses =
        redraftStatuses(
            $workflowRepository,
            $instanceId
        );

    $updatedInstance =
        $workflowRepository->findInstanceById(
            $instanceId
        );

    $updatedAgreement =
        $agreementRepository->findById(
            $agreementId
        );

    redraftAssert(
        $resubmissionResult['review_cycle']
            === 2,
        'Review cycle did not increase to 2'
    );

    redraftAssert(
        $resubmissionResult['submitted_version']
            === 2,
        'Resubmission did not use Agreement version 2'
    );

    redraftAssert(
        $resubmissionResult['vp_assignments']
            >= 1,
        'Initial VP was not assigned after resubmission'
    );

    redraftAssert(
        $statuses['CREATOR'] === 'APPROVED',
        'Creator redraft step was not approved'
    );

    redraftAssert(
        $statuses['VP_INITIAL']
            === 'IN_PROGRESS',
        'Initial VP review was not reactivated'
    );

    foreach (
        [
            'LEGAL_REVIEW',
            'FINANCE_REVIEW',
            'VP_FINAL',
            'PRESIDENT_APPROVAL',
        ]
        as $pendingStep
    ) {
        redraftAssert(
            $statuses[$pendingStep] === 'PENDING',
            "{$pendingStep} was not reset to PENDING"
        );
    }

    redraftAssert(
        $updatedInstance !== null
        && (int) $updatedInstance[
            'review_cycle'
        ] === 2,
        'Review cycle 2 was not stored'
    );

    redraftAssert(
        $updatedInstance[
            'redraft_base_version'
        ] === null,
        'Redraft baseline was not cleared'
    );

    redraftAssert(
        $updatedInstance[
            'finance_review_required'
        ] === null,
        'Previous Finance decision was not cleared'
    );

    redraftAssert(
        (int) $updatedInstance[
            'current_step'
        ] === 2,
        'Workflow did not return to Initial VP'
    );

    redraftAssert(
        $updatedAgreement !== null
        && $updatedAgreement['status']
            === 'UNDER_REVIEW',
        'Agreement did not return to UNDER_REVIEW'
    );

    redraftAssert(
        redraftInboxContains(
            $workflowRepository,
            $vpId,
            $instanceId,
            'VP_INITIAL'
        ),
        'Revised Agreement was not added to VP inbox'
    );

    $historyStmt = $db->prepare(
        'SELECT action
         FROM workflow_history
         WHERE workflow_instance_id =
               :instance_id
           AND action = \'RESUBMITTED\''
    );

    $historyStmt->execute([
        'instance_id' => $instanceId,
    ]);

    redraftAssert(
        $historyStmt->fetchColumn()
            === 'RESUBMITTED',
        'Resubmission history was not recorded'
    );

    echo json_encode(
        [
            'success' => true,
            'unchanged_resubmission_rejected' =>
                $unchangedResubmissionRejected,
            'redraft_base_version' =>
                $creatorRoute[
                    'redraft_base_version'
                ],
            'submitted_version' =>
                $resubmissionResult[
                    'submitted_version'
                ],
            'review_cycle' =>
                $resubmissionResult[
                    'review_cycle'
                ],
            'statuses' =>
                $statuses,
            'agreement_status' =>
                $updatedAgreement['status'],
            'finance_decision_cleared' =>
                $updatedInstance[
                    'finance_review_required'
                ] === null,
            'baseline_cleared' =>
                $updatedInstance[
                    'redraft_base_version'
                ] === null,
            'message' =>
                'Agreement redraft resubmission test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}