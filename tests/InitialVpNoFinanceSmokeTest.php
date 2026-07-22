<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function noFinanceAssert(
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

noFinanceAssert(
    $dean !== null && $vp !== null,
    'Development Dean or VP was not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary VP No-Finance Test',
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
                false,
                'Legal review only'
            );

    $instance =
        $workflowRepository
            ->findInstanceById(
                (int) $started[
                    'workflow_instance_id'
                ]
            );

    noFinanceAssert(
        $instance !== null
        && $instance[
            'finance_review_required'
        ] === false,
        'Finance decision was not stored as false'
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

    noFinanceAssert(
        $statuses['VP_INITIAL'] ===
            'APPROVED',
        'Initial VP step was not approved'
    );

    noFinanceAssert(
        $statuses['LEGAL_REVIEW'] ===
            'IN_PROGRESS',
        'Legal review was not activated'
    );

    noFinanceAssert(
        $statuses['FINANCE_REVIEW'] ===
            'SKIPPED',
        'Finance review was not skipped'
    );

    noFinanceAssert(
        $statuses['VP_FINAL'] ===
            'PENDING',
        'Final VP review activated too early'
    );

    noFinanceAssert(
        $result['legal_assignments'] >= 1,
        'Legal reviewer was not assigned'
    );

    noFinanceAssert(
        $result['finance_assignments'] === 0,
        'Finance reviewer should not be assigned'
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
                'Initial VP no-Finance test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}