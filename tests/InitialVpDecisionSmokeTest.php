<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function vpDecisionAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();

$userRepository = new UserRepository();
$agreementRepository =
    new AgreementRepository();
$workflowRepository =
    new WorkflowRepository();
$approvalService =
    new ApprovalService();

$dean = $userRepository->findByEmail(
    'dev.dean@uob.test'
);

$vp = $userRepository->findByEmail(
    'dev.vp@uob.test'
);

vpDecisionAssert(
    $dean !== null && $vp !== null,
    'Development Dean or VP was not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary VP Decision Test',
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' => 'DRAFT',
        ]);

    $started =
        $approvalService
            ->startAgreementWorkflow(
                $agreementId,
                (int) $dean['user_id']
            );

    $result =
        $approvalService
            ->completeInitialVpReview(
                (int) $started[
                    'workflow_instance_id'
                ],
                (int) $vp['user_id'],
                true,
                'Legal and Finance review required'
            );

    $instance =
        $workflowRepository
            ->findInstanceById(
                (int) $started[
                    'workflow_instance_id'
                ]
            );

    vpDecisionAssert(
        $instance !== null
        && $instance[
            'finance_review_required'
        ] === true,
        'Finance decision was not stored'
    );

    $steps =
        $workflowRepository->findSteps(
            (int) $instance[
                'workflow_instance_id'
            ]
        );

    $statuses = [];

    foreach ($steps as $step) {
        $statuses[$step['step_key']] =
            $step['status'];
    }

    vpDecisionAssert(
        $statuses['VP_INITIAL'] ===
            'APPROVED',
        'Initial VP step was not approved'
    );

    vpDecisionAssert(
        $statuses['LEGAL_REVIEW'] ===
            'IN_PROGRESS',
        'Legal review was not activated'
    );

    vpDecisionAssert(
        $statuses['FINANCE_REVIEW'] ===
            'IN_PROGRESS',
        'Finance review was not activated'
    );

    vpDecisionAssert(
        $statuses['VP_FINAL'] ===
            'PENDING',
        'Final VP review activated too early'
    );

    vpDecisionAssert(
        $result['legal_assignments'] >= 1
        && $result[
            'finance_assignments'
        ] >= 1,
        'Specialist reviewers were not assigned'
    );

    echo json_encode(
        [
            'success' => true,
            'finance_review_required' =>
                $result[
                    'finance_review_required'
                ],
            'legal_assignments' =>
                $result['legal_assignments'],
            'finance_assignments' =>
                $result[
                    'finance_assignments'
                ],
            'step_statuses' => $statuses,
            'message' =>
                'Initial VP decision test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}