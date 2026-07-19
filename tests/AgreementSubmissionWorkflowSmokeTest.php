<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/AgreementService.php';

function submissionAssert(
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
$versionRepository =
    new AgreementVersionRepository();
$workflowRepository =
    new WorkflowRepository();
$agreementService = new AgreementService();

$dean = $userRepository->findByEmail(
    'dev.dean@uob.test'
);

submissionAssert(
    $dean !== null,
    'Development Dean was not found'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Submission Workflow Test',
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' => 'DRAFT',
        ]);

    $result =
        $agreementService->submitAgreement(
            $agreementId,
            (int) $dean['user_id']
        );

    submissionAssert(
        $result['success'] === true,
        'Agreement submission failed'
    );

    $agreement =
        $agreementRepository->findById(
            $agreementId
        );

    submissionAssert(
        $agreement !== null
        && $agreement['status'] ===
            'UNDER_REVIEW',
        'Agreement was not moved to UNDER_REVIEW'
    );

    $workflow =
        $workflowRepository->findActiveByEntity(
            'AGREEMENT',
            $agreementId
        );

    submissionAssert(
        $workflow !== null,
        'Active workflow was not created'
    );

    $versions =
        $versionRepository->findByAgreement(
            $agreementId
        );

    submissionAssert(
        count($versions) === 1,
        'Submission version was not created'
    );

    $steps = $workflowRepository->findSteps(
        (int) $workflow[
            'workflow_instance_id'
        ]
    );

    $statuses = [];

    foreach ($steps as $step) {
        $statuses[$step['step_key']] =
            $step['status'];
    }

    submissionAssert(
        $statuses['VP_INITIAL'] ===
            'IN_PROGRESS',
        'Initial VP review is not active'
    );

    echo json_encode(
        [
            'success' => true,
            'agreement_status' =>
                $agreement['status'],
            'workflow_instance_id' =>
                $result['workflow_instance_id'],
            'current_step_key' =>
                $result['current_step_key'],
            'version_count' =>
                count($versions),
            'step_statuses' => $statuses,
            'message' =>
                'Agreement submission workflow test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}