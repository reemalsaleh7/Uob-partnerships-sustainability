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

public function completeSpecialistReview(
    int $instanceId,
    string $stepKey,
    int $performedBy,
    ?string $comments = null
): array {
    $ownsTransaction =
        !$this->db->inTransaction();

    if ($ownsTransaction) {
        $this->db->beginTransaction();
    }

    try {
        $result =
            $this->processSpecialistReview(
                $instanceId,
                $stepKey,
                $performedBy,
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

private function processSpecialistReview(
    int $instanceId,
    string $stepKey,
    int $performedBy,
    ?string $comments
): array {
    $stepKey = strtoupper(trim($stepKey));

    if (
        !in_array(
            $stepKey,
            [
                'LEGAL_REVIEW',
                'FINANCE_REVIEW',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Only Legal or Finance specialist reviews may be completed here'
        );
    }

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

    // Lock in a consistent order so parallel Legal and
    // Finance decisions cannot activate final VP twice.
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

    $targetStep =
        $stepKey === 'LEGAL_REVIEW'
            ? $legalStep
            : $financeStep;

    if ($targetStep['status'] !== 'IN_PROGRESS') {
        throw new DomainException(
            'The selected specialist review is not active'
        );
    }

    if (
        $stepKey === 'FINANCE_REVIEW'
        && $instance['finance_review_required'] !== true
    ) {
        throw new DomainException(
            'Finance review was not requested for this workflow'
        );
    }

    $targetStepId =
        (int) $targetStep['instance_step_id'];

    $isAssigned =
        $this->workflowRepository
            ->isUserAssignedToStep(
                $targetStepId,
                $performedBy
            );

    if (!$isAssigned) {
        throw new DomainException(
            'User is not assigned to this specialist review'
        );
    }

    $this->workflowRepository
        ->setStepStatus(
            $targetStepId,
            'APPROVED',
            $performedBy,
            $comments
        );

    $this->workflowRepository
        ->deactivateStepAssignments(
            $targetStepId
        );

    $officeName =
        $stepKey === 'LEGAL_REVIEW'
            ? 'Legal'
            : 'Finance';

    $historyComment =
        "{$officeName} review approved";

    if ($comments) {
        $historyComment .=
            '. Reviewer comments: ' . $comments;
    }

    $this->workflowRepository->addHistory(
        $instanceId,
        $targetStepId,
        'APPROVED',
        $performedBy,
        $historyComment
    );

    $legalCompleted =
        $stepKey === 'LEGAL_REVIEW'
        || $legalStep['status'] === 'APPROVED';

    $financeRequired =
        $instance['finance_review_required'] === true;

    $financeCompleted =
        !$financeRequired
        || $stepKey === 'FINANCE_REVIEW'
        || $financeStep['status'] === 'APPROVED';

    $finalVpActivated = false;
    $vpAssignments = 0;

    if (
        $legalCompleted
        && $financeCompleted
    ) {
        $finalVpStep =
            $this->workflowRepository
                ->findStepByKey(
                    $instanceId,
                    'VP_FINAL',
                    true
                );

        if ($finalVpStep === null) {
            throw new DomainException(
                'Final VP workflow step was not found'
            );
        }

        if ($finalVpStep['status'] === 'PENDING') {
            $vpAssignments =
                $this->activateOfficeStep(
                    $finalVpStep,
                    'Final VP'
                );

            $this->workflowRepository
                ->setCurrentStep(
                    $instanceId,
                    5
                );

            $finalVpActivated = true;
        } elseif (
            $finalVpStep['status']
            !== 'IN_PROGRESS'
        ) {
            throw new DomainException(
                'Final VP workflow step cannot be activated'
            );
        }
    }

    return [
        'success' => true,
        'workflow_instance_id' =>
            $instanceId,
        'completed_step_key' =>
            $stepKey,
        'legal_completed' =>
            $legalCompleted,
        'finance_review_required' =>
            $financeRequired,
        'finance_completed' =>
            $financeCompleted,
        'final_vp_activated' =>
            $finalVpActivated,
        'vp_assignments' =>
            $vpAssignments,
        'current_stage' =>
            $finalVpActivated
                ? 'VP_FINAL'
                : 'SPECIALIST_REVIEW',
    ];
}

public function completeFinalVpReview(
    int $instanceId,
    int $performedBy,
    ?string $comments = null
): array {
    $ownsTransaction =
        !$this->db->inTransaction();

    if ($ownsTransaction) {
        $this->db->beginTransaction();
    }

    try {
        $result = $this->processFinalVpReview(
            $instanceId,
            $performedBy,
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

private function processFinalVpReview(
    int $instanceId,
    int $performedBy,
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

    $finalVpStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'VP_FINAL',
                true
            );

    $presidentStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'PRESIDENT_APPROVAL',
                true
            );

    if (
        $finalVpStep === null
        || $presidentStep === null
    ) {
        throw new DomainException(
            'Final approval steps were not found'
        );
    }

    if ($finalVpStep['status'] !== 'IN_PROGRESS') {
        throw new DomainException(
            'Final VP review is not active'
        );
    }

    if ($presidentStep['status'] !== 'PENDING') {
        throw new DomainException(
            'President approval step is not pending'
        );
    }

    $finalVpStepId =
        (int) $finalVpStep['instance_step_id'];

    $isAssigned =
        $this->workflowRepository
            ->isUserAssignedToStep(
                $finalVpStepId,
                $performedBy
            );

    if (!$isAssigned) {
        throw new DomainException(
            'User is not assigned to the final VP review'
        );
    }

    $this->workflowRepository
        ->setStepStatus(
            $finalVpStepId,
            'APPROVED',
            $performedBy,
            $comments
        );

    $this->workflowRepository
        ->deactivateStepAssignments(
            $finalVpStepId
        );

    $historyComment =
        'Final VP review approved; President approval requested';

    if ($comments) {
        $historyComment .=
            '. VP comments: ' . $comments;
    }

    $this->workflowRepository->addHistory(
        $instanceId,
        $finalVpStepId,
        'APPROVED',
        $performedBy,
        $historyComment
    );

    $presidentAssignments =
        $this->activateOfficeStep(
            $presidentStep,
            'President'
        );

    $this->workflowRepository
        ->setCurrentStep(
            $instanceId,
            6
        );

    return [
        'success' => true,
        'workflow_instance_id' =>
            $instanceId,
        'completed_step_key' =>
            'VP_FINAL',
        'president_step_activated' =>
            true,
        'president_assignments' =>
            $presidentAssignments,
        'current_stage' =>
            'PRESIDENT_APPROVAL',
    ];
}

public function completePresidentApproval(
    int $instanceId,
    int $performedBy,
    ?string $comments = null
): array {
    $ownsTransaction =
        !$this->db->inTransaction();

    if ($ownsTransaction) {
        $this->db->beginTransaction();
    }

    try {
        $result =
            $this->processPresidentApproval(
                $instanceId,
                $performedBy,
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

private function processPresidentApproval(
    int $instanceId,
    int $performedBy,
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

    $presidentStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'PRESIDENT_APPROVAL',
                true
            );

    if ($presidentStep === null) {
        throw new DomainException(
            'President approval step was not found'
        );
    }

    if (
        $presidentStep['status']
        !== 'IN_PROGRESS'
    ) {
        throw new DomainException(
            'President approval is not active'
        );
    }

    $presidentStepId =
        (int) $presidentStep[
            'instance_step_id'
        ];

    $isAssigned =
        $this->workflowRepository
            ->isUserAssignedToStep(
                $presidentStepId,
                $performedBy
            );

    if (!$isAssigned) {
        throw new DomainException(
            'User is not assigned to President approval'
        );
    }

    $agreementId =
        (int) $instance['entity_id'];

    $agreement =
        $this->agreementRepository
            ->findById($agreementId);

    if ($agreement === null) {
        throw new DomainException(
            'Agreement associated with the workflow was not found'
        );
    }

    $this->workflowRepository
        ->setStepStatus(
            $presidentStepId,
            'APPROVED',
            $performedBy,
            $comments
        );

    $this->workflowRepository
        ->deactivateStepAssignments(
            $presidentStepId
        );

    $historyComment =
        'President approved the Agreement';

    if ($comments) {
        $historyComment .=
            '. President comments: ' . $comments;
    }

    $this->workflowRepository->addHistory(
        $instanceId,
        $presidentStepId,
        'APPROVED',
        $performedBy,
        $historyComment
    );

    $this->workflowRepository
        ->setInstanceStatus(
            $instanceId,
            'COMPLETED'
        );

    $this->agreementRepository
        ->changeStatus(
            $agreementId,
            'APPROVED'
        );

    return [
        'success' => true,
        'workflow_instance_id' =>
            $instanceId,
        'agreement_id' =>
            $agreementId,
        'completed_step_key' =>
            'PRESIDENT_APPROVAL',
        'workflow_status' =>
            'COMPLETED',
        'agreement_status' =>
            'APPROVED',
        'current_stage' =>
            'COMPLETED',
    ];
}

public function requestAgreementChanges(
    int $instanceId,
    string $stepKey,
    int $performedBy,
    string $reason
): array {
    $ownsTransaction =
        !$this->db->inTransaction();

    if ($ownsTransaction) {
        $this->db->beginTransaction();
    }

    try {
        $result =
            $this->processAgreementChangeRequest(
                $instanceId,
                $stepKey,
                $performedBy,
                $reason
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

private function processAgreementChangeRequest(
    int $instanceId,
    string $stepKey,
    int $performedBy,
    string $reason
): array {
    $stepKey = strtoupper(trim($stepKey));
    $reason = trim($reason);

    $allowedSteps = [
        'LEGAL_REVIEW',
        'FINANCE_REVIEW',
        'PRESIDENT_APPROVAL',
    ];

    if (
        !in_array(
            $stepKey,
            $allowedSteps,
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Only Legal, Finance, or President may route a change request to VP'
        );
    }

    if ($reason === '') {
        throw new InvalidArgumentException(
            'A reason is required when requesting changes'
        );
    }

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

    $sourceStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                $stepKey,
                true
            );

    if ($sourceStep === null) {
        throw new DomainException(
            'Change-request source step was not found'
        );
    }

    if ($sourceStep['status'] !== 'IN_PROGRESS') {
        throw new DomainException(
            'The selected review step is not active'
        );
    }

    $sourceStepId =
        (int) $sourceStep['instance_step_id'];

    if (
        !$this->workflowRepository
            ->isUserAssignedToStep(
                $sourceStepId,
                $performedBy
            )
    ) {
        throw new DomainException(
            'User is not assigned to the selected review step'
        );
    }

    $finalVpStep =
        $this->workflowRepository
            ->findStepByKey(
                $instanceId,
                'VP_FINAL',
                true
            );

    if ($finalVpStep === null) {
        throw new DomainException(
            'VP mediation step was not found'
        );
    }

    $this->workflowRepository
        ->setStepStatus(
            $sourceStepId,
            'CHANGES_REQUESTED',
            $performedBy,
            $reason
        );

    /*
     * Pause every current assignment. The VP mediation
     * assignment is created again below.
     */
    $this->workflowRepository
        ->deactivateInstanceAssignments(
            $instanceId
        );

    /*
     * If Legal or Finance requested changes while the other
     * specialist was still working, pause that parallel step.
     * Previously completed specialist decisions remain in
     * place until the VP decides whether they need repeating.
     */
    if (
        in_array(
            $stepKey,
            [
                'LEGAL_REVIEW',
                'FINANCE_REVIEW',
            ],
            true
        )
    ) {
        $parallelStepKey =
            $stepKey === 'LEGAL_REVIEW'
                ? 'FINANCE_REVIEW'
                : 'LEGAL_REVIEW';

        $parallelStep =
            $this->workflowRepository
                ->findStepByKey(
                    $instanceId,
                    $parallelStepKey,
                    true
                );

        if (
            $parallelStep !== null
            && $parallelStep['status']
                === 'IN_PROGRESS'
        ) {
            $this->workflowRepository
                ->prepareStepForReview(
                    (int) $parallelStep[
                        'instance_step_id'
                    ],
                    'PENDING'
                );
        }
    }

    /*
     * VP_FINAL doubles as the VP mediation stage after a
     * returned review. Its previous decision is cleared if
     * the request came back from the President.
     */
    $this->workflowRepository
        ->prepareStepForReview(
            (int) $finalVpStep[
                'instance_step_id'
            ],
            'PENDING'
        );

    $vpAssignments =
        $this->activateOfficeStep(
            $finalVpStep,
            'VP mediation'
        );

    $this->workflowRepository
        ->setCurrentStep(
            $instanceId,
            5
        );

    $sourceName = match ($stepKey) {
        'LEGAL_REVIEW' =>
            'Legal',
        'FINANCE_REVIEW' =>
            'Finance',
        'PRESIDENT_APPROVAL' =>
            'President',
    };

    $this->workflowRepository->addHistory(
        $instanceId,
        $sourceStepId,
        'CHANGES_REQUESTED',
        $performedBy,
        "{$sourceName} requested changes: {$reason}"
    );

    $this->workflowRepository->addHistory(
        $instanceId,
        (int) $finalVpStep[
            'instance_step_id'
        ],
        'ROUTED_TO_VP',
        $performedBy,
        "{$sourceName} change request routed to VP for mediation"
    );

    return [
        'success' => true,
        'workflow_instance_id' =>
            $instanceId,
        'source_step_key' =>
            $stepKey,
        'source_step_status' =>
            'CHANGES_REQUESTED',
        'vp_mediation_activated' =>
            true,
        'vp_assignments' =>
            $vpAssignments,
        'current_stage' =>
            'VP_MEDIATION',
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