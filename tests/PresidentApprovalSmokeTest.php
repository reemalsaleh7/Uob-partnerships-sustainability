<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function presidentAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function presidentStatuses(
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

$finance = $userRepository->findByEmail(
    'dev.finance@uob.test'
);

$president = $userRepository->findByEmail(
    'dev.president@uob.test'
);

presidentAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null
    && $finance !== null
    && $president !== null,
    'Required development workflow users were not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary President Approval Test',
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
            true,
            'Legal and Finance reviews required'
        );

    $approvalService
        ->completeSpecialistReview(
            $instanceId,
            'LEGAL_REVIEW',
            (int) $legal['user_id'],
            'Legal review approved'
        );

    $approvalService
        ->completeSpecialistReview(
            $instanceId,
            'FINANCE_REVIEW',
            (int) $finance['user_id'],
            'Finance review approved'
        );

    $approvalService
        ->completeFinalVpReview(
            $instanceId,
            (int) $vp['user_id'],
            'Final VP review approved'
        );

    $beforePresident =
        presidentStatuses(
            $workflowRepository,
            $instanceId
        );

    presidentAssert(
        $beforePresident['VP_FINAL']
            === 'APPROVED',
        'Final VP review was not approved'
    );

    presidentAssert(
        $beforePresident['PRESIDENT_APPROVAL']
            === 'IN_PROGRESS',
        'President approval was not active'
    );

    $result =
        $approvalService
            ->completePresidentApproval(
                $instanceId,
                (int) $president['user_id'],
                'Agreement approved by President'
            );

    $afterPresident =
        presidentStatuses(
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

    presidentAssert(
        $result['workflow_status']
            === 'COMPLETED',
        'President result did not report a completed workflow'
    );

    presidentAssert(
        $result['agreement_status']
            === 'APPROVED',
        'President result did not report an approved Agreement'
    );

    presidentAssert(
        $afterPresident['PRESIDENT_APPROVAL']
            === 'APPROVED',
        'President workflow step was not approved'
    );

    presidentAssert(
        $instance !== null,
        'Workflow instance was not found'
    );

    presidentAssert(
        $instance['status'] === 'COMPLETED',
        'Workflow instance was not completed'
    );

    presidentAssert(
        $instance['completed_at'] !== null,
        'Workflow completion timestamp was not recorded'
    );

    presidentAssert(
        $agreement !== null,
        'Agreement was not found'
    );

    presidentAssert(
        $agreement['status'] === 'APPROVED',
        'Agreement status was not changed to APPROVED'
    );

    $presidentInbox =
        $workflowRepository->findInboxForUser(
            (int) $president['user_id']
        );

    $stillInPresidentInbox = false;

    foreach ($presidentInbox as $inboxItem) {
        if (
            (int) $inboxItem[
                'workflow_instance_id'
            ] === $instanceId
        ) {
            $stillInPresidentInbox = true;
            break;
        }
    }

    presidentAssert(
        !$stillInPresidentInbox,
        'Completed approval remained in the President inbox'
    );

    $repeatApprovalRejected = false;

    try {
        $approvalService
            ->completePresidentApproval(
                $instanceId,
                (int) $president['user_id'],
                'Duplicate approval attempt'
            );
    } catch (DomainException) {
        $repeatApprovalRejected = true;
    }

    presidentAssert(
        $repeatApprovalRejected,
        'A completed workflow accepted a duplicate President approval'
    );

    echo json_encode(
        [
            'success' => true,
            'before_president' =>
                $beforePresident,
            'after_president' =>
                $afterPresident,
            'workflow_status' =>
                $instance['status'],
            'workflow_completed_at_recorded' =>
                $instance['completed_at'] !== null,
            'agreement_status' =>
                $agreement['status'],
            'removed_from_president_inbox' =>
                !$stillInPresidentInbox,
            'duplicate_approval_rejected' =>
                $repeatApprovalRejected,
            'message' =>
                'President approval test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}