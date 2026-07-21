<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function changeRequestAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function changeRequestStatuses(
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

function inboxContainsWorkflow(
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

$finance = $userRepository->findByEmail(
    'dev.finance@uob.test'
);

$president = $userRepository->findByEmail(
    'dev.president@uob.test'
);

changeRequestAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null
    && $finance !== null
    && $president !== null,
    'Required development workflow users were not found'
);

$db->beginTransaction();

try {
    /*
     * Scenario 1:
     * Legal requests changes while Finance is active.
     * Finance pauses and VP mediation becomes active.
     */
    $specialistAgreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Legal Change Request Test',
            'agreement_type' =>
                'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' =>
                'DRAFT',
        ]);

    $specialistStarted =
        $approvalService
            ->startAgreementWorkflow(
                $specialistAgreementId,
                (int) $dean['user_id']
            );

    $specialistInstanceId =
        (int) $specialistStarted[
            'workflow_instance_id'
        ];

    $approvalService
        ->completeInitialVpReview(
            $specialistInstanceId,
            (int) $vp['user_id'],
            true,
            'Legal and Finance reviews required'
        );

    $legalChangeResult =
        $approvalService
            ->requestAgreementChanges(
                $specialistInstanceId,
                'LEGAL_REVIEW',
                (int) $legal['user_id'],
                'The termination clause requires revision'
            );

    $specialistStatuses =
        changeRequestStatuses(
            $workflowRepository,
            $specialistInstanceId
        );

    changeRequestAssert(
        $legalChangeResult[
            'vp_mediation_activated'
        ] === true,
        'Legal change request did not activate VP mediation'
    );

    changeRequestAssert(
        $legalChangeResult['vp_assignments'] >= 1,
        'VP mediator was not assigned'
    );

    changeRequestAssert(
        $specialistStatuses['LEGAL_REVIEW']
            === 'CHANGES_REQUESTED',
        'Legal step was not marked CHANGES_REQUESTED'
    );

    changeRequestAssert(
        $specialistStatuses['FINANCE_REVIEW']
            === 'PENDING',
        'Parallel Finance review was not paused'
    );

    changeRequestAssert(
        $specialistStatuses['VP_FINAL']
            === 'IN_PROGRESS',
        'VP mediation step was not activated'
    );

    changeRequestAssert(
        inboxContainsWorkflow(
            $workflowRepository,
            (int) $vp['user_id'],
            $specialistInstanceId,
            'VP_FINAL'
        ),
        'Legal change request was not added to VP inbox'
    );

    $vpMediationAssignment = null;

    foreach (
        $workflowRepository->findInboxForUser(
            (int) $vp['user_id']
        ) as $inboxItem
    ) {
        if (
            (int) $inboxItem[
                'workflow_instance_id'
            ] === $specialistInstanceId
            && $inboxItem['step_key']
                === 'VP_FINAL'
        ) {
            $vpMediationAssignment = $inboxItem;
            break;
        }
    }

    changeRequestAssert(
        $vpMediationAssignment !== null
        && $vpMediationAssignment['task_mode']
            === 'VP_MEDIATION'
        && $vpMediationAssignment[
            'change_request_step_key'
        ] === 'LEGAL_REVIEW'
        && $vpMediationAssignment[
            'change_request_reason'
        ] === 'The termination clause requires revision',
        'VP inbox did not expose the Legal mediation context'
    );

    changeRequestAssert(
        !inboxContainsWorkflow(
            $workflowRepository,
            (int) $finance['user_id'],
            $specialistInstanceId,
            'FINANCE_REVIEW'
        ),
        'Paused Finance review remained in Finance inbox'
    );

    /*
     * Scenario 2:
     * President requests changes after Final VP approval.
     * President assignment closes and VP mediation reopens.
     */
    $presidentAgreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary President Change Request Test',
            'agreement_type' =>
                'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' =>
                'DRAFT',
        ]);

    $presidentStarted =
        $approvalService
            ->startAgreementWorkflow(
                $presidentAgreementId,
                (int) $dean['user_id']
            );

    $presidentInstanceId =
        (int) $presidentStarted[
            'workflow_instance_id'
        ];

    $approvalService
        ->completeInitialVpReview(
            $presidentInstanceId,
            (int) $vp['user_id'],
            false,
            'Legal review only'
        );

    $approvalService
        ->completeSpecialistReview(
            $presidentInstanceId,
            'LEGAL_REVIEW',
            (int) $legal['user_id'],
            'Legal review approved'
        );

    $approvalService
        ->completeFinalVpReview(
            $presidentInstanceId,
            (int) $vp['user_id'],
            'Final VP review approved'
        );

    $presidentChangeResult =
        $approvalService
            ->requestAgreementChanges(
                $presidentInstanceId,
                'PRESIDENT_APPROVAL',
                (int) $president['user_id'],
                'Clarify the implementation obligations'
            );

    $presidentStatuses =
        changeRequestStatuses(
            $workflowRepository,
            $presidentInstanceId
        );

    $resetFinalVp =
        $workflowRepository->findStepByKey(
            $presidentInstanceId,
            'VP_FINAL'
        );

    changeRequestAssert(
        $presidentChangeResult[
            'vp_mediation_activated'
        ] === true,
        'President change request did not activate VP mediation'
    );

    changeRequestAssert(
        $presidentStatuses[
            'PRESIDENT_APPROVAL'
        ] === 'CHANGES_REQUESTED',
        'President step was not marked CHANGES_REQUESTED'
    );

    changeRequestAssert(
        $presidentStatuses['VP_FINAL']
            === 'IN_PROGRESS',
        'Final VP was not reopened for mediation'
    );

    changeRequestAssert(
        $resetFinalVp !== null
        && $resetFinalVp['approved_by'] === null
        && $resetFinalVp['approved_at'] === null
        && $resetFinalVp['completed_at'] === null,
        'Previous Final VP decision was not cleared'
    );

    changeRequestAssert(
        inboxContainsWorkflow(
            $workflowRepository,
            (int) $vp['user_id'],
            $presidentInstanceId,
            'VP_FINAL'
        ),
        'President change request was not added to VP inbox'
    );

    changeRequestAssert(
        !inboxContainsWorkflow(
            $workflowRepository,
            (int) $president['user_id'],
            $presidentInstanceId,
            'PRESIDENT_APPROVAL'
        ),
        'Returned President step remained in President inbox'
    );

    $historyStmt = $db->prepare(
        'SELECT action
         FROM workflow_history
         WHERE workflow_instance_id =
               :instance_id
           AND action IN (
               \'CHANGES_REQUESTED\',
               \'ROUTED_TO_VP\'
           )
         ORDER BY history_id'
    );

    $historyStmt->execute([
        'instance_id' =>
            $presidentInstanceId,
    ]);

    $historyActions =
        array_column(
            $historyStmt->fetchAll(),
            'action'
        );

    changeRequestAssert(
        $historyActions === [
            'CHANGES_REQUESTED',
            'ROUTED_TO_VP',
        ],
        'Change-request history actions are incomplete'
    );

    echo json_encode(
        [
            'success' => true,
            'legal_change_request' => [
                'statuses' =>
                    $specialistStatuses,
                'vp_assignments' =>
                    $legalChangeResult[
                        'vp_assignments'
                    ],
                'finance_paused' =>
                    $specialistStatuses[
                        'FINANCE_REVIEW'
                    ] === 'PENDING',
            ],
            'president_change_request' => [
                'statuses' =>
                    $presidentStatuses,
                'previous_vp_decision_cleared' =>
                    $resetFinalVp[
                        'approved_by'
                    ] === null,
                'history_actions' =>
                    $historyActions,
            ],
            'message' =>
                'Agreement change request test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
