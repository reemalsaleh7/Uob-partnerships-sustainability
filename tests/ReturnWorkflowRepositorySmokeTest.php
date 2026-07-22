<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function returnRepositoryAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
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

returnRepositoryAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null,
    'Required development users were not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Return Repository Test',
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

    $finalVpStep =
        $workflowRepository->findStepByKey(
            $instanceId,
            'VP_FINAL'
        );

    $presidentStep =
        $workflowRepository->findStepByKey(
            $instanceId,
            'PRESIDENT_APPROVAL'
        );

    returnRepositoryAssert(
        $finalVpStep !== null
        && $presidentStep !== null,
        'Required workflow steps were not found'
    );

    returnRepositoryAssert(
        $finalVpStep['status'] === 'APPROVED',
        'Final VP step was not approved before reset'
    );

    returnRepositoryAssert(
        $presidentStep['status'] === 'IN_PROGRESS',
        'President step was not active before reset'
    );

    $workflowRepository
        ->deactivateInstanceAssignments(
            $instanceId
        );

    $workflowRepository
        ->prepareStepForReview(
            (int) $finalVpStep['instance_step_id'],
            'IN_PROGRESS'
        );

    $newVpAssignments =
        $workflowRepository
            ->assignEligibleUsersForUnit(
                (int) $finalVpStep[
                    'instance_step_id'
                ],
                (int) $finalVpStep[
                    'assigned_unit_id'
                ]
            );

    $reviewCycle =
        $workflowRepository
            ->incrementReviewCycle($instanceId);

    $workflowRepository
        ->setCurrentStep(
            $instanceId,
            5
        );

    $resetVpStep =
        $workflowRepository->findStepByKey(
            $instanceId,
            'VP_FINAL'
        );

    $instance =
        $workflowRepository->findInstanceById(
            $instanceId
        );

    returnRepositoryAssert(
        $newVpAssignments >= 1,
        'VP assignment was not recreated'
    );

    returnRepositoryAssert(
        $reviewCycle === 2,
        'Review cycle did not increase from 1 to 2'
    );

    returnRepositoryAssert(
        $instance !== null
        && (int) $instance['review_cycle'] === 2,
        'Updated review cycle was not stored'
    );

    returnRepositoryAssert(
        (int) $instance['current_step'] === 5,
        'Current workflow step was not returned to VP'
    );

    returnRepositoryAssert(
        $resetVpStep !== null,
        'Reset VP step was not found'
    );

    returnRepositoryAssert(
        $resetVpStep['status'] === 'IN_PROGRESS',
        'Final VP step was not reactivated'
    );

    returnRepositoryAssert(
        $resetVpStep['approved_by'] === null,
        'Previous VP approver was not cleared'
    );

    returnRepositoryAssert(
        $resetVpStep['approved_at'] === null,
        'Previous VP approval time was not cleared'
    );

    returnRepositoryAssert(
        $resetVpStep['completed_at'] === null,
        'Previous VP completion time was not cleared'
    );

    returnRepositoryAssert(
        $resetVpStep['comments'] === null,
        'Previous VP comments were not cleared'
    );

    $assignmentStmt = $db->prepare(
        'SELECT
            COUNT(*) FILTER (
                WHERE is_active = TRUE
            ) AS active_count,
            COUNT(*) FILTER (
                WHERE is_active = FALSE
            ) AS inactive_count
         FROM workflow_step_assignments
         WHERE workflow_instance_step_id =
               :step_id
           AND user_id = :user_id'
    );

    $assignmentStmt->execute([
        'step_id' =>
            (int) $finalVpStep[
                'instance_step_id'
            ],
        'user_id' =>
            (int) $vp['user_id'],
    ]);

    $assignmentCounts =
        $assignmentStmt->fetch();

    returnRepositoryAssert(
        (int) $assignmentCounts['active_count']
            === 1,
        'VP step does not have exactly one active assignment'
    );

    returnRepositoryAssert(
        (int) $assignmentCounts['inactive_count']
            >= 1,
        'Previous VP assignment was not retained as inactive history'
    );

    $presidentAssignmentStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM workflow_step_assignments
         WHERE workflow_instance_step_id =
               :step_id
           AND is_active = TRUE'
    );

    $presidentAssignmentStmt->execute([
        'step_id' =>
            (int) $presidentStep[
                'instance_step_id'
            ],
    ]);

    $activePresidentAssignments =
        (int) $presidentAssignmentStmt
            ->fetchColumn();

    returnRepositoryAssert(
        $activePresidentAssignments === 0,
        'Obsolete President assignments remained active'
    );

    echo json_encode(
        [
            'success' => true,
            'review_cycle' =>
                $reviewCycle,
            'vp_status' =>
                $resetVpStep['status'],
            'vp_active_assignments' =>
                (int) $assignmentCounts[
                    'active_count'
                ],
            'vp_inactive_assignments' =>
                (int) $assignmentCounts[
                    'inactive_count'
                ],
            'president_active_assignments' =>
                $activePresidentAssignments,
            'previous_decision_cleared' =>
                $resetVpStep['approved_by'] === null
                && $resetVpStep['approved_at'] === null
                && $resetVpStep['completed_at'] === null,
            'message' =>
                'Return workflow repository test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}