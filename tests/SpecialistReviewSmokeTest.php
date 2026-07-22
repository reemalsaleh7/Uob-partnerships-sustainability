<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function specialistAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function specialistStatuses(
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

specialistAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null
    && $finance !== null,
    'Required development workflow users were not found'
);

$db->beginTransaction();

try {
    /*
     * Scenario 1:
     * Finance is requested and completes first.
     * Final VP must wait until Legal also completes.
     */
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Parallel Specialist Test',
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

    $parallelInstanceId =
        (int) $started['workflow_instance_id'];

    $approvalService
        ->completeInitialVpReview(
            $parallelInstanceId,
            (int) $vp['user_id'],
            true,
            'Legal and Finance reviews required'
        );

    $financeResult =
        $approvalService
            ->completeSpecialistReview(
                $parallelInstanceId,
                'FINANCE_REVIEW',
                (int) $finance['user_id'],
                'Finance review approved'
            );

    $afterFinance =
        specialistStatuses(
            $workflowRepository,
            $parallelInstanceId
        );

    specialistAssert(
        $financeResult['final_vp_activated']
            === false,
        'Final VP activated before Legal completed'
    );

    specialistAssert(
        $afterFinance['FINANCE_REVIEW']
            === 'APPROVED',
        'Finance review was not approved'
    );

    specialistAssert(
        $afterFinance['LEGAL_REVIEW']
            === 'IN_PROGRESS',
        'Legal review should remain active'
    );

    specialistAssert(
        $afterFinance['VP_FINAL']
            === 'PENDING',
        'Final VP should still be pending'
    );

    $legalResult =
        $approvalService
            ->completeSpecialistReview(
                $parallelInstanceId,
                'LEGAL_REVIEW',
                (int) $legal['user_id'],
                'Legal review approved'
            );

    $afterLegal =
        specialistStatuses(
            $workflowRepository,
            $parallelInstanceId
        );

    specialistAssert(
        $legalResult['final_vp_activated']
            === true,
        'Final VP did not activate after both reviews'
    );

    specialistAssert(
        $legalResult['vp_assignments'] >= 1,
        'Final VP reviewer was not assigned'
    );

    specialistAssert(
        $afterLegal['LEGAL_REVIEW']
            === 'APPROVED',
        'Legal review was not approved'
    );

    specialistAssert(
        $afterLegal['FINANCE_REVIEW']
            === 'APPROVED',
        'Finance approval was not retained'
    );

    specialistAssert(
        $afterLegal['VP_FINAL']
            === 'IN_PROGRESS',
        'Final VP review was not activated'
    );

    /*
     * Scenario 2:
     * Finance is not requested.
     * Legal completion alone activates final VP.
     */
    $noFinanceAgreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Legal Only Specialist Test',
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' =>
                (int) $dean['user_id'],
            'status' => 'DRAFT',
        ]);

    $noFinanceStarted =
        $approvalService
            ->startAgreementWorkflow(
                $noFinanceAgreementId,
                (int) $dean['user_id']
            );

    $noFinanceInstanceId =
        (int) $noFinanceStarted[
            'workflow_instance_id'
        ];

    $approvalService
        ->completeInitialVpReview(
            $noFinanceInstanceId,
            (int) $vp['user_id'],
            false,
            'Legal review only'
        );

    $legalOnlyResult =
        $approvalService
            ->completeSpecialistReview(
                $noFinanceInstanceId,
                'LEGAL_REVIEW',
                (int) $legal['user_id'],
                'Legal-only review approved'
            );

    $legalOnlyStatuses =
        specialistStatuses(
            $workflowRepository,
            $noFinanceInstanceId
        );

    specialistAssert(
        $legalOnlyResult['final_vp_activated']
            === true,
        'Final VP did not activate after required Legal review'
    );

    specialistAssert(
        $legalOnlyStatuses['LEGAL_REVIEW']
            === 'APPROVED',
        'Legal-only review was not approved'
    );

    specialistAssert(
        $legalOnlyStatuses['FINANCE_REVIEW']
            === 'SKIPPED',
        'Optional Finance review was not skipped'
    );

    specialistAssert(
        $legalOnlyStatuses['VP_FINAL']
            === 'IN_PROGRESS',
        'Final VP was not activated after Legal-only review'
    );

    echo json_encode(
        [
            'success' => true,
            'parallel_review' => [
                'finance_finished_first' =>
                    $afterFinance,
                'both_finished' =>
                    $afterLegal,
            ],
            'legal_only_review' =>
                $legalOnlyStatuses,
            'message' =>
                'Specialist review test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}