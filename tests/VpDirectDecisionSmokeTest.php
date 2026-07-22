<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../services/ApprovalService.php';

function vpDirectAssert(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createVersionedWorkflow(
    AgreementRepository $agreementRepository,
    AgreementVersionRepository $versionRepository,
    ApprovalService $approvalService,
    int $creatorId,
    string $title
): array {
    $agreementId =
        $agreementRepository->create([
            'title' => $title,
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'created_by' => $creatorId,
            'status' => 'DRAFT',
        ]);

    $snapshot =
        $agreementRepository->findById(
            $agreementId
        );

    if ($snapshot === null) {
        throw new RuntimeException(
            'Created Agreement was not found'
        );
    }

    $versionRepository->create(
        $agreementId,
        [
            'version_number' => 1,
            'change_summary' =>
                'Initial Agreement version',
            'agreement_snapshot' => $snapshot,
            'created_by' => $creatorId,
        ]
    );

    $started =
        $approvalService
            ->startAgreementWorkflow(
                $agreementId,
                $creatorId
            );

    return [
        'agreement_id' => $agreementId,
        'instance_id' =>
            (int) $started[
                'workflow_instance_id'
            ],
    ];
}

function vpDirectStatuses(
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
$versionRepository =
    new AgreementVersionRepository();
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

vpDirectAssert(
    $dean !== null
    && $vp !== null
    && $legal !== null,
    'Required development workflow users were not found'
);

$deanId = (int) $dean['user_id'];
$vpId = (int) $vp['user_id'];
$legalId = (int) $legal['user_id'];

$db->beginTransaction();

try {
    /*
     * Initial VP returns to creator.
     */
    $initialReturn =
        createVersionedWorkflow(
            $agreementRepository,
            $versionRepository,
            $approvalService,
            $deanId,
            'Temporary Initial VP Return Test'
        );

    $initialReturnResult =
        $approvalService
            ->decideVpReviewOutcome(
                $initialReturn['instance_id'],
                'VP_INITIAL',
                $vpId,
                'RETURN_TO_CREATOR',
                'Agreement scope must be clarified'
            );

    $initialReturnStatuses =
        vpDirectStatuses(
            $workflowRepository,
            $initialReturn['instance_id']
        );

    $initialReturnAgreement =
        $agreementRepository->findById(
            $initialReturn['agreement_id']
        );

    vpDirectAssert(
        $initialReturnResult[
            'redraft_base_version'
        ] === 1,
        'Initial VP return baseline is incorrect'
    );

    vpDirectAssert(
        $initialReturnStatuses['VP_INITIAL']
            === 'CHANGES_REQUESTED',
        'Initial VP was not marked CHANGES_REQUESTED'
    );

    vpDirectAssert(
        $initialReturnStatuses['CREATOR']
            === 'IN_PROGRESS',
        'Creator was not activated after Initial VP return'
    );

    vpDirectAssert(
        $initialReturnAgreement !== null
        && $initialReturnAgreement['status']
            === 'REVISION_REQUIRED',
        'Initial VP return did not update Agreement status'
    );

    /*
     * Initial VP terminally rejects.
     */
    $initialReject =
        createVersionedWorkflow(
            $agreementRepository,
            $versionRepository,
            $approvalService,
            $deanId,
            'Temporary Initial VP Rejection Test'
        );

    $initialRejectResult =
        $approvalService
            ->decideVpReviewOutcome(
                $initialReject['instance_id'],
                'VP_INITIAL',
                $vpId,
                'REJECT',
                'Agreement is outside university priorities'
            );

    $initialRejectInstance =
        $workflowRepository->findInstanceById(
            $initialReject['instance_id']
        );

    $initialRejectedAgreement =
        $agreementRepository->findById(
            $initialReject['agreement_id']
        );

    vpDirectAssert(
        $initialRejectResult['decision']
            === 'REJECT',
        'Initial VP rejection result is incorrect'
    );

    vpDirectAssert(
        $initialRejectInstance !== null
        && $initialRejectInstance['status']
            === 'REJECTED',
        'Initial VP did not reject the workflow'
    );

    vpDirectAssert(
        $initialRejectedAgreement !== null
        && $initialRejectedAgreement['status']
            === 'REJECTED',
        'Initial VP did not reject the Agreement'
    );

    /*
     * Final VP returns to creator.
     */
    $finalReturn =
        createVersionedWorkflow(
            $agreementRepository,
            $versionRepository,
            $approvalService,
            $deanId,
            'Temporary Final VP Return Test'
        );

    $approvalService
        ->completeInitialVpReview(
            $finalReturn['instance_id'],
            $vpId,
            false,
            'Legal review only'
        );

    $approvalService
        ->completeSpecialistReview(
            $finalReturn['instance_id'],
            'LEGAL_REVIEW',
            $legalId,
            'Legal review approved'
        );

    $finalReturnResult =
        $approvalService
            ->decideVpReviewOutcome(
                $finalReturn['instance_id'],
                'VP_FINAL',
                $vpId,
                'RETURN_TO_CREATOR',
                'Implementation obligations require revision'
            );

    $finalReturnStatuses =
        vpDirectStatuses(
            $workflowRepository,
            $finalReturn['instance_id']
        );

    $finalReturnAgreement =
        $agreementRepository->findById(
            $finalReturn['agreement_id']
        );

    vpDirectAssert(
        $finalReturnResult[
            'redraft_base_version'
        ] === 1,
        'Final VP return baseline is incorrect'
    );

    vpDirectAssert(
        $finalReturnStatuses['VP_FINAL']
            === 'CHANGES_REQUESTED',
        'Final VP was not marked CHANGES_REQUESTED'
    );

    vpDirectAssert(
        $finalReturnStatuses['CREATOR']
            === 'IN_PROGRESS',
        'Creator was not activated after Final VP return'
    );

    vpDirectAssert(
        $finalReturnAgreement !== null
        && $finalReturnAgreement['status']
            === 'REVISION_REQUIRED',
        'Final VP return did not update Agreement status'
    );

    /*
     * Final VP terminally rejects.
     */
    $finalReject =
        createVersionedWorkflow(
            $agreementRepository,
            $versionRepository,
            $approvalService,
            $deanId,
            'Temporary Final VP Rejection Test'
        );

    $approvalService
        ->completeInitialVpReview(
            $finalReject['instance_id'],
            $vpId,
            false,
            'Legal review only'
        );

    $approvalService
        ->completeSpecialistReview(
            $finalReject['instance_id'],
            'LEGAL_REVIEW',
            $legalId,
            'Legal review approved'
        );

    $finalRejectResult =
        $approvalService
            ->decideVpReviewOutcome(
                $finalReject['instance_id'],
                'VP_FINAL',
                $vpId,
                'REJECT',
                'Final review determined the Agreement cannot proceed'
            );

    $finalRejectStatuses =
        vpDirectStatuses(
            $workflowRepository,
            $finalReject['instance_id']
        );

    $finalRejectInstance =
        $workflowRepository->findInstanceById(
            $finalReject['instance_id']
        );

    $finalRejectedAgreement =
        $agreementRepository->findById(
            $finalReject['agreement_id']
        );

    vpDirectAssert(
        $finalRejectResult['decision']
            === 'REJECT',
        'Final VP rejection result is incorrect'
    );

    vpDirectAssert(
        $finalRejectStatuses['VP_FINAL']
            === 'REJECTED',
        'Final VP step was not rejected'
    );

    vpDirectAssert(
        $finalRejectInstance !== null
        && $finalRejectInstance['status']
            === 'REJECTED'
        && $finalRejectInstance['completed_at']
            !== null,
        'Final VP did not terminally reject the workflow'
    );

    vpDirectAssert(
        $finalRejectedAgreement !== null
        && $finalRejectedAgreement['status']
            === 'REJECTED',
        'Final VP did not terminally reject the Agreement'
    );

    echo json_encode(
        [
            'success' => true,
            'initial_vp_return' => [
                'statuses' =>
                    $initialReturnStatuses,
                'agreement_status' =>
                    $initialReturnAgreement[
                        'status'
                    ],
            ],
            'initial_vp_reject' => [
                'workflow_status' =>
                    $initialRejectInstance[
                        'status'
                    ],
                'agreement_status' =>
                    $initialRejectedAgreement[
                        'status'
                    ],
            ],
            'final_vp_return' => [
                'statuses' =>
                    $finalReturnStatuses,
                'agreement_status' =>
                    $finalReturnAgreement[
                        'status'
                    ],
            ],
            'final_vp_reject' => [
                'statuses' =>
                    $finalRejectStatuses,
                'workflow_status' =>
                    $finalRejectInstance[
                        'status'
                    ],
                'agreement_status' =>
                    $finalRejectedAgreement[
                        'status'
                    ],
            ],
            'message' =>
                'Direct VP decision test passed and will be rolled back',
        ],
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}