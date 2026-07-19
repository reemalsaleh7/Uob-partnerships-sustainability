<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/HierarchyResolver.php';

class ApprovalService
{
    private PDO $db;
    private AgreementRepository $agreementRepository;
    private WorkflowRepository $workflowRepository;
    private HierarchyResolver $hierarchyResolver;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->agreementRepository =
            new AgreementRepository();
        $this->workflowRepository =
            new WorkflowRepository();
        $this->hierarchyResolver =
            new HierarchyResolver($this->workflowRepository);
    }

    public function startAgreementWorkflow(
        int $agreementId,
        int $startedBy
    ): array {
        $ownsTransaction = !$this->db->inTransaction();

        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $this->createAgreementWorkflow(
                $agreementId,
                $startedBy
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if (
                $ownsTransaction
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function createAgreementWorkflow(
        int $agreementId,
        int $startedBy
    ): array {
        $agreement = $this->agreementRepository
            ->findById($agreementId);

        if ($agreement === null) {
            throw new DomainException(
                'Agreement not found'
            );
        }

        if ($agreement['status'] !== 'DRAFT') {
            throw new DomainException(
                'Only a DRAFT Agreement may be submitted'
            );
        }

        $this->hierarchyResolver
            ->assertCanStartAgreement($startedBy);

        $activeWorkflow = $this->workflowRepository
            ->findActiveByEntity(
                'AGREEMENT',
                $agreementId
            );

        if ($activeWorkflow !== null) {
            throw new DomainException(
                'Agreement already has an active workflow'
            );
        }

        $template = $this->workflowRepository
            ->findActiveTemplate('AGREEMENT');

        if ($template === null) {
            throw new DomainException(
                'Active Agreement workflow template was not found'
            );
        }

        $templateId =
            (int) $template['workflow_template_id'];

        $templateSteps = $this->workflowRepository
            ->findTemplateSteps($templateId);

        $this->assertExpectedTemplate($templateSteps);

        $instanceId = $this->workflowRepository
            ->createInstance([
                'workflow_template_id' => $templateId,
                'entity_type' => 'AGREEMENT',
                'entity_id' => $agreementId,
                'current_step' => 2,
                'finance_review_required' => null,
                'status' => 'IN_PROGRESS',
                'started_by' => $startedBy,
            ]);

        $createdSteps = [];

        foreach ($templateSteps as $templateStep) {
            $stepKey = $templateStep['step_key'];

            $status = match ($stepKey) {
                'CREATOR' => 'APPROVED',
                'VP_INITIAL' => 'IN_PROGRESS',
                default => 'PENDING',
            };

            $actedBy =
                $stepKey === 'CREATOR'
                    ? $startedBy
                    : null;

            $instanceStepId =
                $this->workflowRepository
                    ->createInstanceStep(
                        $instanceId,
                        $templateStep,
                        $status,
                        $actedBy
                    );

            $createdSteps[$stepKey] = [
                'instance_step_id' =>
                    $instanceStepId,
                'status' => $status,
                'required_unit_id' =>
                    $templateStep['required_unit_id'],
                'required_unit_code' =>
                    $templateStep['required_unit_code'],
            ];
        }

        $creatorStep = $createdSteps['CREATOR'];
        $initialVpStep = $createdSteps['VP_INITIAL'];

        $assignedVpUsers =
            $this->workflowRepository
                ->assignEligibleUsersForUnit(
                    $initialVpStep['instance_step_id'],
                    (int) $initialVpStep[
                        'required_unit_id'
                    ]
                );

        if ($assignedVpUsers < 1) {
            throw new DomainException(
                'No eligible VP Office approver is available'
            );
        }

        $this->workflowRepository->addHistory(
            $instanceId,
            $creatorStep['instance_step_id'],
            'SUBMITTED',
            $startedBy,
            'Agreement submitted for initial VP review'
        );

        return [
            'workflow_instance_id' => $instanceId,
            'current_step_key' => 'VP_INITIAL',
            'assigned_vp_users' => $assignedVpUsers,
            'steps' => $createdSteps,
        ];
    }
    public function completeInitialVpReview(
    int $instanceId,
    int $performedBy,
    bool $includeFinance,
    ?string $comments = null
): array {
    $ownsTransaction =
        !$this->db->inTransaction();

    if ($ownsTransaction) {
        $this->db->beginTransaction();
    }

    try {
        $result =
            $this->processInitialVpReview(
                $instanceId,
                $performedBy,
                $includeFinance,
                $comments
            );

        if ($ownsTransaction) {
            $this->db->commit();
        }

        return $result;
    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $this->db->inTransaction()
        ) {
            $this->db->rollBack();
        }

        throw $exception;
    }
}

private function processInitialVpReview(
    int $instanceId,
    int $performedBy,
    bool $includeFinance,
    ?string $comments
): array {
    $instance =
        $this->workflowRepository
            ->findInstanceById(
                $instanceId,
                true
            );

    if ($instance === null) {
        throw new DomainException(
            'Workflow instance not found'
        );
    }

    if (
        $instance['entity_type'] !== 'AGREEMENT'
        || $instance['status'] !== 'IN_PROGRESS'
    ) {
        throw new DomainException(
            'Agreement workflow is not active'
        );
    }

    $initialVpStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'VP_INITIAL',
                true
            );

    if ($initialVpStep === null) {
        throw new DomainException(
            'Initial VP workflow step was not found'
        );
    }

    if (
        $initialVpStep['status']
        !== 'IN_PROGRESS'
    ) {
        throw new DomainException(
            'Initial VP review is not active'
        );
    }

    $isAssigned =
        $this->workflowRepository
            ->isUserAssignedToStep(
                (int) $initialVpStep[
                    'instance_step_id'
                ],
                $performedBy
            );

    if (!$isAssigned) {
        throw new DomainException(
            'User is not assigned to the initial VP review'
        );
    }

    $legalStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'LEGAL_REVIEW',
                true
            );

    $financeStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'FINANCE_REVIEW',
                true
            );

    if (
        $legalStep === null
        || $financeStep === null
    ) {
        throw new DomainException(
            'Specialist review steps were not found'
        );
    }

    $this->workflowRepository
        ->setStepStatus(
            (int) $initialVpStep[
                'instance_step_id'
            ],
            'APPROVED',
            $performedBy,
            $comments
        );

    $this->workflowRepository
        ->deactivateStepAssignments(
            (int) $initialVpStep[
                'instance_step_id'
            ]
        );

    $this->workflowRepository
        ->setFinanceReviewRequired(
            $instanceId,
            $includeFinance
        );

    $legalAssignments =
        $this->activateOfficeStep(
            $legalStep,
            'Legal'
        );

    $financeAssignments = 0;

    if ($includeFinance) {
        $financeAssignments =
            $this->activateOfficeStep(
                $financeStep,
                'Finance'
            );
    } else {
        $this->workflowRepository
            ->setStepStatus(
                (int) $financeStep[
                    'instance_step_id'
                ],
                'SKIPPED',
                null,
                'Finance review was not requested by VP'
            );
    }

    $this->workflowRepository
        ->setCurrentStep(
            $instanceId,
            3
        );

    $historyComment = $includeFinance
        ? 'Initial VP review approved; Legal and Finance reviews requested'
        : 'Initial VP review approved; Legal review requested and Finance skipped';

    if ($comments) {
        $historyComment .=
            '. VP comments: ' . $comments;
    }

    $this->workflowRepository->addHistory(
        $instanceId,
        (int) $initialVpStep[
            'instance_step_id'
        ],
        'APPROVED',
        $performedBy,
        $historyComment
    );

    return [
        'success' => true,
        'workflow_instance_id' =>
            $instanceId,
        'finance_review_required' =>
            $includeFinance,
        'legal_assignments' =>
            $legalAssignments,
        'finance_assignments' =>
            $financeAssignments,
        'current_stage' =>
            'SPECIALIST_REVIEW',
    ];
}

private function activateOfficeStep(
    array $step,
    string $officeName
): int {
    $unitId =
        (int) ($step['assigned_unit_id'] ?? 0);

    if ($unitId <= 0) {
        throw new DomainException(
            "{$officeName} review has no assigned office"
        );
    }

    $instanceStepId =
        (int) $step['instance_step_id'];

    $this->workflowRepository
        ->setStepStatus(
            $instanceStepId,
            'IN_PROGRESS'
        );

    $assignments =
        $this->workflowRepository
            ->assignEligibleUsersForUnit(
                $instanceStepId,
                $unitId
            );

    if ($assignments < 1) {
        throw new DomainException(
            "No eligible {$officeName} reviewer is available"
        );
    }

    return $assignments;
}
    private function assertExpectedTemplate(
        array $templateSteps
    ): void {
        $expectedKeys = [
            'CREATOR',
            'VP_INITIAL',
            'LEGAL_REVIEW',
            'FINANCE_REVIEW',
            'VP_FINAL',
            'PRESIDENT_APPROVAL',
        ];

        $actualKeys =
            array_column($templateSteps, 'step_key');

        if ($actualKeys !== $expectedKeys) {
            throw new DomainException(
                'Agreement workflow template is invalid'
            );
        }
    }
}