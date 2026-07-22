<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function presidentRejectAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function presidentRejectStatuses(
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

$president = $userRepository->findByEmail(
    'dev.president@uob.test'
);

presidentRejectAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null
    && $president !== null,
    'Required development workflow users were not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary President Rejection Test',
            'agreement_type' =>
                'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' =>
                'DRAFT',
        ]);

    $started =
        $approvalService
            ->startAgreementWorkflow(
                $agreementId,
                (int) $dean['user_id']
            );

    $instanceId =
        (int) $started['workflow_instance_id'];

    $approvalService
        ->completeInitialVpReview(
            $instanceId,
            (int) $vp['user_id'],
            false,
            'Legal review only'
        );

    $approvalService
        ->completeSpecialistReview(
            $instanceId,
            'LEGAL_REVIEW',
            (int) $legal['user_id'],
            'Legal review approved'
        );

    $approvalService
        ->completeFinalVpReview(
            $instanceId,
            (int) $vp['user_id'],
            'Final VP review approved'
        );

    $beforeRejection =
        presidentRejectStatuses(
            $workflowRepository,
            $instanceId
        );

    presidentRejectAssert(
        $beforeRejection[
            'PRESIDENT_APPROVAL'
        ] === 'IN_PROGRESS',
        'President approval was not active'
    );

    $result =
        $approvalService
            ->rejectAgreementByPresident(
                $instanceId,
                (int) $president['user_id'],
                'Agreement does not meet final institutional requirements'
            );

    $afterRejection =
        presidentRejectStatuses(
            $workflowRepository,
            $instanceId
        );

    $instance =
        $workflowRepository->findInstanceById(
            $instanceId
        );

    $agreement =
        $agreementRepository->findById(
            $agreementId
        );

    presidentRejectAssert(
        $result['decision'] === 'REJECT',
        'President result did not report rejection'
    );

    presidentRejectAssert(
        $afterRejection[
            'PRESIDENT_APPROVAL'
        ] === 'REJECTED',
        'President step was not rejected'
    );

    presidentRejectAssert(
        $instance !== null
        && $instance['status'] === 'REJECTED',
        'Workflow was not rejected'
    );

    presidentRejectAssert(
        $instance['completed_at'] !== null,
        'Workflow rejection timestamp was not recorded'
    );

    presidentRejectAssert(
        $agreement !== null
        && $agreement['status'] === 'REJECTED',
        'Agreement was not rejected'
    );

    $presidentInbox =
        $workflowRepository->findInboxForUser(
            (int) $president['user_id']
        );

    $stillInInbox = false;

    foreach ($presidentInbox as $item) {
        if (
            (int) $item[
                'workflow_instance_id'
            ] === $instanceId
        ) {
            $stillInInbox = true;
            break;
        }
    }

    presidentRejectAssert(
        !$stillInInbox,
        'Rejected Agreement remained in President inbox'
    );

    $historyStmt = $db->prepare(
        'SELECT action, comments
         FROM workflow_history
         WHERE workflow_instance_id =
               :instance_id
           AND action = \'REJECTED\'
         ORDER BY history_id DESC
         LIMIT 1'
    );

    $historyStmt->execute([
        'instance_id' => $instanceId,
    ]);

    $history = $historyStmt->fetch();

    presidentRejectAssert(
        $history !== false
        && $history['action'] === 'REJECTED',
        'President rejection history was not recorded'
    );

    $duplicateRejectionRejected = false;

    try {
        $approvalService
            ->rejectAgreementByPresident(
                $instanceId,
                (int) $president['user_id'],
                'Duplicate rejection'
            );
    } catch (DomainException) {
        $duplicateRejectionRejected = true;
    }

    presidentRejectAssert(
        $duplicateRejectionRejected,
        'Completed rejection accepted another decision'
    );

    echo json_encode(
        [
            'success' => true,
            'before_rejection' =>
                $beforeRejection,
            'after_rejection' =>
                $afterRejection,
            'workflow_status' =>
                $instance['status'],
            'workflow_completed_at_recorded' =>
                $instance['completed_at']
                    !== null,
            'agreement_status' =>
                $agreement['status'],
            'removed_from_president_inbox' =>
                !$stillInInbox,
            'history_action' =>
                $history['action'],
            'duplicate_rejection_blocked' =>
                $duplicateRejectionRejected,
            'message' =>
                'President rejection test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}