<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function approvalAssert(
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

approvalAssert(
    $dean !== null,
    'Development Dean was not found'
);

$db->beginTransaction();

try {
    $agreementId = $agreementRepository->create([
        'title' =>
            'Temporary Workflow Start Smoke Test',
        'agreement_type' => 'MOU',
        'description' =>
            'Rolled back after workflow verification',
        'created_by' => (int) $dean['user_id'],
        'status' => 'DRAFT',
    ]);

    $result = $approvalService
        ->startAgreementWorkflow(
            $agreementId,
            (int) $dean['user_id']
        );

    $instance = $workflowRepository
        ->findActiveByEntity(
            'AGREEMENT',
            $agreementId
        );

    approvalAssert(
        $instance !== null,
        'Active workflow instance was not created'
    );

    approvalAssert(
        $instance['finance_review_required'] === null,
        'Finance decision must initially be NULL'
    );

    $steps = $workflowRepository->findSteps(
        (int) $instance['workflow_instance_id']
    );

    approvalAssert(
        count($steps) === 6,
        'Workflow must contain six instance steps'
    );

    $statuses = [];

    foreach ($steps as $step) {
        $statuses[$step['step_key']] =
            $step['status'];
    }

    approvalAssert(
        $statuses['CREATOR'] === 'APPROVED',
        'Creator step must be approved'
    );

    approvalAssert(
        $statuses['VP_INITIAL'] === 'IN_PROGRESS',
        'Initial VP step must be active'
    );

    approvalAssert(
        $statuses['LEGAL_REVIEW'] === 'PENDING',
        'Legal step must initially be pending'
    );

    approvalAssert(
        $statuses['FINANCE_REVIEW'] === 'PENDING',
        'Finance step must initially be pending'
    );

    approvalAssert(
        $result['assigned_vp_users'] >= 1,
        'At least one VP user must be assigned'
    );

    echo json_encode(
        [
            'success' => true,
            'workflow_instance_id' =>
                $result['workflow_instance_id'],
            'assigned_vp_users' =>
                $result['assigned_vp_users'],
            'step_statuses' => $statuses,
            'message' =>
                'Approval workflow start smoke test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}