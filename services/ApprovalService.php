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