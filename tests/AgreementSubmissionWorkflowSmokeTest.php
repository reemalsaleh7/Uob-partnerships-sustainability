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

$partnerId = $db->query(
    'SELECT partner_id
     FROM partners
     WHERE is_active = TRUE
     ORDER BY partner_id
     LIMIT 1'
)->fetchColumn();

submissionAssert(
    $partnerId !== false,
    'At least one active partner is required'
);

$db->beginTransaction();

try {
    $agreementId =
        $agreementRepository->create([
            'title' =>
                'Temporary Submission Workflow Test',
            'title_ar' =>
                'اختبار مؤقت لمسار تقديم الاتفاقية',
            'agreement_type' => 'MOU',
            'description' =>
                'Rolled back after verification',
            'geographic_scope' => 'LOCAL',
            'start_date' =>
                date('Y-m-d', strtotime('+30 days')),
            'end_date' =>
                date('Y-m-d', strtotime('+395 days')),
            'signing_date' => date('Y-m-d'),
            'effective_date' =>
                date('Y-m-d', strtotime('+30 days')),
            'auto_renew' => true,
            'renewal_term_months' => 12,
            'need_justification' =>
                'Required for submission workflow verification',
            'expected_value' =>
                'Verifies the complete submission transaction',
            'objectives' =>
                'Confirm workflow creation and initial VP routing',
            'collaboration_areas' =>
                'Academic and institutional cooperation',
            'implementation_methods' =>
                'Joint coordination and periodic review',
            'created_by' =>
                (int) $dean['user_id'],
            'status' => 'DRAFT',
        ]);

    $agreementRepository->replacePartners(
        $agreementId,
        [(int) $partnerId]
    );

    $agreementRepository->replaceContacts(
        $agreementId,
        [
            [
                'party_type' => 'UOB',
                'contact_role' => 'COORDINATOR',
                'full_name' => 'UOB Test Coordinator',
                'job_title' => 'Partnership Coordinator',
                'email' => 'submission.coordinator@uob.test',
                'phone' => '+973 1700 0001',
                'is_primary' => true,
            ],
            [
                'party_type' => 'PARTNER',
                'contact_role' => 'COORDINATOR',
                'partner_id' => (int) $partnerId,
                'full_name' => 'Partner Test Coordinator',
                'job_title' => 'International Relations Coordinator',
                'email' => 'coordinator@example.test',
                'phone' => '+973 1700 0002',
                'is_primary' => true,
            ],
            [
                'party_type' => 'UOB',
                'contact_role' => 'SIGNATORY',
                'full_name' => 'UOB Test Signatory',
                'job_title' => 'Authorized University Signatory',
                'email' => 'submission.signatory@uob.test',
                'phone' => '+973 1700 0003',
                'is_primary' => true,
            ],
            [
                'party_type' => 'PARTNER',
                'contact_role' => 'SIGNATORY',
                'partner_id' => (int) $partnerId,
                'full_name' => 'Partner Test Signatory',
                'job_title' => 'Authorized Partner Signatory',
                'email' => 'signatory@example.test',
                'phone' => '+973 1700 0004',
                'is_primary' => true,
            ],
        ]
    );

    $agreementRepository->replaceExecutivePrograms(
        $agreementId,
        [[
            'title' => 'Submission Workflow Test Programme',
            'description' =>
                'Temporary programme used only to verify submission.',
            'objectives' =>
                'Verify complete programme validation and workflow routing.',
            'expected_outputs' =>
                'A successfully created Agreement approval workflow.',
            'start_date' =>
                date('Y-m-d', strtotime('+45 days')),
            'end_date' =>
                date('Y-m-d', strtotime('+180 days')),
            'responsible_entity' =>
                'University of Bahrain and test partner',
            'applicant_name' => 'Development Dean',
        ]]
    );

    $agreementRepository->replaceMetrics(
        $agreementId,
        [
            [
                'metric_code' => 'STUDENTS_EXCHANGED',
                'planned_value' => 10,
                'actual_value' => 0,
                'notes' => 'Planned student exchange participants',
            ],
            [
                'metric_code' => 'FACULTY_EXCHANGED',
                'planned_value' => 4,
                'actual_value' => 0,
                'notes' => 'Planned faculty exchange participants',
            ],
            [
                'metric_code' => 'JOINT_PROGRAMS',
                'planned_value' => 1,
                'actual_value' => 0,
                'notes' => 'Planned joint programme',
            ],
            [
                'metric_code' => 'TRAINED_STUDENTS',
                'planned_value' => 20,
                'actual_value' => 0,
                'notes' => 'Planned trained students',
            ],
        ]
    );

    $result =
        $agreementService->submitAgreement(
            $agreementId,
            (int) $dean['user_id']
        );

    submissionAssert(
        $result['success'] === true,
        'Agreement submission failed: '
            . implode(
                '; ',
                $result['errors']
                    ?? ['Unknown error']
            )
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
