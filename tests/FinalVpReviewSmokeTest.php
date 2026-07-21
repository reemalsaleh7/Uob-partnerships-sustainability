<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function finalVpAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function finalVpStatuses(
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

finalVpAssert(
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
                'Temporary Final VP Review Test',
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
            'Legal review required; Finance not required'
        );

    $approvalService
        ->completeSpecialistReview(
            $instanceId,
            'LEGAL_REVIEW',
            (int) $legal['user_id'],
            'Legal review approved'
        );

    $beforeFinalVp =
        finalVpStatuses(
            $workflowRepository,
            $instanceId
        );

    finalVpAssert(
        $beforeFinalVp['VP_FINAL']
            === 'IN_PROGRESS',
        'Final VP review was not active'
    );

    finalVpAssert(
        $beforeFinalVp['PRESIDENT_APPROVAL']
            === 'PENDING',
        'President approval should initially be pending'
    );

    $finalVpInboxAssignment = null;

    foreach (
        $workflowRepository->findInboxForUser(
            (int) $vp['user_id']
        ) as $inboxItem
    ) {
        if (
            (int) $inboxItem[
                'workflow_instance_id'
            ] === $instanceId
            && $inboxItem['step_key']
                === 'VP_FINAL'
        ) {
            $finalVpInboxAssignment = $inboxItem;
            break;
        }
    }

    finalVpAssert(
        $finalVpInboxAssignment !== null
        && $finalVpInboxAssignment['task_mode']
            === 'REVIEW'
        && $finalVpInboxAssignment[
            'change_request_step_key'
        ] === null
        && $finalVpInboxAssignment[
            'legal_review_status'
        ] === 'APPROVED'
        && $finalVpInboxAssignment[
            'finance_review_status'
        ] === 'SKIPPED',
        'Final VP inbox did not expose ordinary review context'
    );

    $result =
        $approvalService
            ->completeFinalVpReview(
                $instanceId,
                (int) $vp['user_id'],
                'Final VP review approved'
            );

    $afterFinalVp =
        finalVpStatuses(
            $workflowRepository,
            $instanceId
        );

    finalVpAssert(
        $result['president_step_activated']
            === true,
        'President approval was not activated'
    );

    finalVpAssert(
        $result['president_assignments'] >= 1,
        'President Office approver was not assigned'
    );

    finalVpAssert(
        $result['current_stage']
            === 'PRESIDENT_APPROVAL',
        'Unexpected current workflow stage'
    );

    finalVpAssert(
        $afterFinalVp['VP_FINAL']
            === 'APPROVED',
        'Final VP review was not approved'
    );

    finalVpAssert(
        $afterFinalVp['PRESIDENT_APPROVAL']
            === 'IN_PROGRESS',
        'President approval did not become active'
    );

    $instance =
        $workflowRepository->findInstanceById(
            $instanceId
        );

    finalVpAssert(
        $instance !== null,
        'Workflow instance was not found'
    );

    finalVpAssert(
        (int) $instance['current_step'] === 6,
        'Workflow current step was not changed to 6'
    );

    finalVpAssert(
        $instance['status'] === 'IN_PROGRESS',
        'Workflow should remain in progress'
    );

    $presidentInbox =
        $workflowRepository->findInboxForUser(
            (int) $president['user_id']
        );

    $presidentHasAssignment = false;

    foreach ($presidentInbox as $inboxItem) {
        if (
            (int) $inboxItem[
                'workflow_instance_id'
            ] === $instanceId
            && $inboxItem['step_key']
                === 'PRESIDENT_APPROVAL'
        ) {
            $presidentHasAssignment = true;
            break;
        }
    }

    finalVpAssert(
        $presidentHasAssignment,
        'President approval was not added to the President inbox'
    );

    echo json_encode(
        [
            'success' => true,
            'before_final_vp' =>
                $beforeFinalVp,
            'after_final_vp' =>
                $afterFinalVp,
            'president_assignments' =>
                $result['president_assignments'],
            'current_step' =>
                (int) $instance['current_step'],
            'workflow_status' =>
                $instance['status'],
            'message' =>
                'Final VP review test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
