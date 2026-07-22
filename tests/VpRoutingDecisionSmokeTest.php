<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function vpRoutingAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createVpMediationWorkflow(
    AgreementRepository $agreementRepository,
    ApprovalService $approvalService,
    int $deanId,
    int $vpId,
    int $legalId,
    string $title
): array {
    $agreementId =
        $agreementRepository->create([
            'title' => $title,
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' => $deanId,
            'status' => 'DRAFT',
        ]);

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
            'Legal clauses require additional work'
        );

    return [
        'agreement_id' => $agreementId,
        'instance_id' => $instanceId,
    ];
}

function routingStatuses(
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

function routingInboxContains(
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

vpRoutingAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null
    && $finance !== null,
    'Required development workflow users were not found'
);

$deanId = (int) $dean['user_id'];
$vpId = (int) $vp['user_id'];
$legalId = (int) $legal['user_id'];
$financeId = (int) $finance['user_id'];

$db->beginTransaction();

try {
    /*
     * Destination 1: Creator redraft
     */
    $creatorFlow =
        createVpMediationWorkflow(
            $agreementRepository,
            $approvalService,
            $deanId,
            $vpId,
            $legalId,
            'Temporary Creator Routing Test'
        );

    $creatorResult =
        $approvalService
            ->routeAgreementChangeRequest(
                $creatorFlow['instance_id'],
                $vpId,
                'CREATOR',
                'Creator must revise the legal clauses'
            );

    $creatorStatuses =
        routingStatuses(
            $workflowRepository,
            $creatorFlow['instance_id']
        );

    $creatorAgreement =
        $agreementRepository->findById(
            $creatorFlow['agreement_id']
        );

    vpRoutingAssert(
        $creatorResult['current_stage']
            === 'CREATOR_REDRAFT',
        'Creator redraft stage was not returned'
    );

    vpRoutingAssert(
        $creatorStatuses['CREATOR']
            === 'IN_PROGRESS',
        'Creator step was not reactivated'
    );

    vpRoutingAssert(
        $creatorStatuses['VP_FINAL']
            === 'PENDING',
        'VP mediation step was not closed'
    );

    vpRoutingAssert(
        $creatorAgreement !== null
        && $creatorAgreement['status']
            === 'REVISION_REQUIRED',
        'Agreement was not marked REVISION_REQUIRED'
    );

    vpRoutingAssert(
        routingInboxContains(
            $workflowRepository,
            $deanId,
            $creatorFlow['instance_id'],
            'CREATOR'
        ),
        'Creator redraft was not added to creator inbox'
    );

    /*
     * Destination 2: Legal clarification
     */
    $legalFlow =
        createVpMediationWorkflow(
            $agreementRepository,
            $approvalService,
            $deanId,
            $vpId,
            $legalId,
            'Temporary Legal Routing Test'
        );

    $legalResult =
        $approvalService
            ->routeAgreementChangeRequest(
                $legalFlow['instance_id'],
                $vpId,
                'LEGAL',
                'Legal Office must clarify its requested clause'
            );

    $legalStatuses =
        routingStatuses(
            $workflowRepository,
            $legalFlow['instance_id']
        );

    vpRoutingAssert(
        $legalResult['target_step_key']
            === 'LEGAL_REVIEW',
        'VP did not select Legal review'
    );

    vpRoutingAssert(
        $legalStatuses['LEGAL_REVIEW']
            === 'IN_PROGRESS',
        'Legal review was not reactivated'
    );

    vpRoutingAssert(
        $legalStatuses['VP_FINAL']
            === 'PENDING',
        'VP mediation remained active after Legal routing'
    );

    vpRoutingAssert(
        routingInboxContains(
            $workflowRepository,
            $legalId,
            $legalFlow['instance_id'],
            'LEGAL_REVIEW'
        ),
        'Rerouted Legal review was not added to Legal inbox'
    );

    /*
     * Destination 3: Finance clarification
     */
    $financeFlow =
        createVpMediationWorkflow(
            $agreementRepository,
            $approvalService,
            $deanId,
            $vpId,
            $legalId,
            'Temporary Finance Routing Test'
        );

    $financeResult =
        $approvalService
            ->routeAgreementChangeRequest(
                $financeFlow['instance_id'],
                $vpId,
                'FINANCE',
                'Finance Office must assess the revised cost'
            );

    $financeStatuses =
        routingStatuses(
            $workflowRepository,
            $financeFlow['instance_id']
        );

    $financeInstance =
        $workflowRepository->findInstanceById(
            $financeFlow['instance_id']
        );

    vpRoutingAssert(
        $financeResult['target_step_key']
            === 'FINANCE_REVIEW',
        'VP did not select Finance review'
    );

    vpRoutingAssert(
        $financeStatuses['FINANCE_REVIEW']
            === 'IN_PROGRESS',
        'Finance review was not reactivated'
    );

    vpRoutingAssert(
        $financeInstance !== null
        && $financeInstance[
            'finance_review_required'
        ] === true,
        'Finance requirement was not enabled'
    );

    vpRoutingAssert(
        routingInboxContains(
            $workflowRepository,
            $financeId,
            $financeFlow['instance_id'],
            'FINANCE_REVIEW'
        ),
        'Rerouted Finance review was not added to Finance inbox'
    );

    /*
     * Destination 4: Terminal rejection
     */
    $rejectFlow =
        createVpMediationWorkflow(
            $agreementRepository,
            $approvalService,
            $deanId,
            $vpId,
            $legalId,
            'Temporary VP Rejection Test'
        );

    $rejectResult =
        $approvalService
            ->routeAgreementChangeRequest(
                $rejectFlow['instance_id'],
                $vpId,
                'REJECT',
                'The Agreement cannot proceed'
            );

    $rejectStatuses =
        routingStatuses(
            $workflowRepository,
            $rejectFlow['instance_id']
        );

    $rejectedInstance =
        $workflowRepository->findInstanceById(
            $rejectFlow['instance_id']
        );

    $rejectedAgreement =
        $agreementRepository->findById(
            $rejectFlow['agreement_id']
        );

    vpRoutingAssert(
        $rejectResult['workflow_status']
            === 'REJECTED',
        'VP rejection result is incorrect'
    );

    vpRoutingAssert(
        $rejectStatuses['VP_FINAL']
            === 'REJECTED',
        'VP mediation step was not rejected'
    );

    vpRoutingAssert(
        $rejectedInstance !== null
        && $rejectedInstance['status']
            === 'REJECTED'
        && $rejectedInstance['completed_at']
            !== null,
        'Workflow was not terminally rejected'
    );

    vpRoutingAssert(
        $rejectedAgreement !== null
        && $rejectedAgreement['status']
            === 'REJECTED',
        'Agreement was not terminally rejected'
    );

    vpRoutingAssert(
        !routingInboxContains(
            $workflowRepository,
            $vpId,
            $rejectFlow['instance_id'],
            'VP_FINAL'
        ),
        'Rejected workflow remained in VP inbox'
    );

    $historyStmt = $db->prepare(
        'SELECT action
         FROM workflow_history
         WHERE workflow_instance_id =
               :instance_id
           AND action IN (
               \'ROUTED_TO_CREATOR\',
               \'ROUTED_TO_LEGAL\',
               \'ROUTED_TO_FINANCE\',
               \'REJECTED\'
           )
         ORDER BY history_id'
    );

    $historyByDestination = [];

    foreach (
        [
            'CREATOR' => $creatorFlow,
            'LEGAL' => $legalFlow,
            'FINANCE' => $financeFlow,
            'REJECT' => $rejectFlow,
        ]
        as $destination => $flow
    ) {
        $historyStmt->execute([
            'instance_id' =>
                $flow['instance_id'],
        ]);

        $historyByDestination[$destination] =
            array_column(
                $historyStmt->fetchAll(),
                'action'
            );
    }

    vpRoutingAssert(
        $historyByDestination['CREATOR']
            === ['ROUTED_TO_CREATOR'],
        'Creator routing history is missing'
    );

    vpRoutingAssert(
        $historyByDestination['LEGAL']
            === ['ROUTED_TO_LEGAL'],
        'Legal routing history is missing'
    );

    vpRoutingAssert(
        $historyByDestination['FINANCE']
            === ['ROUTED_TO_FINANCE'],
        'Finance routing history is missing'
    );

    vpRoutingAssert(
        $historyByDestination['REJECT']
            === ['REJECTED'],
        'Rejection history is missing'
    );

    echo json_encode(
        [
            'success' => true,
            'creator_route' => [
                'statuses' => $creatorStatuses,
                'agreement_status' =>
                    $creatorAgreement['status'],
            ],
            'legal_route' => [
                'statuses' => $legalStatuses,
            ],
            'finance_route' => [
                'statuses' => $financeStatuses,
                'finance_required' =>
                    $financeInstance[
                        'finance_review_required'
                    ],
            ],
            'reject_route' => [
                'statuses' => $rejectStatuses,
                'workflow_status' =>
                    $rejectedInstance['status'],
                'agreement_status' =>
                    $rejectedAgreement['status'],
            ],
            'history' => $historyByDestination,
            'message' =>
                'VP routing decision test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}