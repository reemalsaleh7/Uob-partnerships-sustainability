<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementLifecycleRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/PermissionService.php';
require_once __DIR__ . '/AuditService.php';

class AgreementLifecycleService
{
    private PDO $db;
    private AgreementLifecycleRepository $requests;
    private AgreementRepository $agreements;
    private WorkflowRepository $workflows;
    private PermissionService $permissions;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->requests = new AgreementLifecycleRepository();
        $this->agreements = new AgreementRepository();
        $this->workflows = new WorkflowRepository();
        $this->permissions = new PermissionService();
        $this->audit = new AuditService();
    }

    public function findAll(int $userId): array
    {
        return $this->requests->findVisibleToUser(
            $userId,
            $this->isAdministrator($userId)
        );
    }

    public function findByIdForUser(int $requestId, int $userId): ?array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return null;
        }

        return $this->requests->isVisibleToUser(
            $request,
            $userId,
            $this->isAdministrator($userId)
        ) ? $request : null;
    }

    public function findVersionsForUser(int $requestId, int $userId): ?array
    {
        if ($this->findByIdForUser($requestId, $userId) === null) {
            return null;
        }
        return $this->requests->findVersions($requestId);
    }

    public function create(int $agreementId, int $userId, array $data): array
    {
        $agreement = $this->agreements->findById($agreementId);
        if ($agreement === null || !in_array($agreement['status'], ['APPROVED', 'ACTIVE'], true)) {
            return ['success' => false, 'errors' => [
                'Lifecycle requests may start only from an approved or active Agreement.',
            ]];
        }

        $errors = $this->validate($data, false);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }
        if ($this->requests->hasOpenRequest(
            $agreementId,
            (string) $data['request_type']
        )) {
            return ['success' => false, 'errors' => [
                'An open request of this type already exists for the Agreement.',
            ]];
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $requestId = $this->requests->create($agreementId, $userId, $data);
            $snapshot = $this->requests->findById($requestId);
            $this->requests->createVersion(
                $requestId,
                $snapshot ?? $data,
                $userId,
                'Initial lifecycle request draft'
            );
            $this->audit->write(
                'agreement_lifecycle_requests',
                $requestId,
                'INSERT',
                $userId,
                null,
                $snapshot
            );
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return ['success' => true, 'lifecycle_request_id' => $requestId];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function update(int $requestId, int $userId, array $data): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return ['success' => false, 'errors' => ['Lifecycle request not found.']];
        }
        if ((int) $request['requested_by'] !== $userId) {
            return ['success' => false, 'errors' => ['Only the requester may edit this request.']];
        }
        if (!in_array($request['status'], ['DRAFT', 'REVISION_REQUIRED'], true)) {
            return ['success' => false, 'errors' => ['This lifecycle request is not editable.']];
        }

        $data['request_type'] = $request['request_type'];
        $errors = $this->validate($data, false);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->requests->update($requestId, $data);
            $updated = $this->requests->findById($requestId);
            $this->requests->createVersion(
                $requestId,
                $updated ?? $data,
                $userId,
                trim((string) ($data['change_summary'] ?? '')) ?: 'Lifecycle request updated'
            );
            $this->audit->write(
                'agreement_lifecycle_requests',
                $requestId,
                'UPDATE',
                $userId,
                $request,
                $updated
            );
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return ['success' => true, 'lifecycle_request_id' => $requestId];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function submit(int $requestId, int $userId): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $request = $this->requests->findById($requestId, true);
            if ($request === null) {
                throw new DomainException('Lifecycle request not found');
            }
            if ((int) $request['requested_by'] !== $userId) {
                throw new DomainException('Only the requester may submit this request');
            }
            if ($request['status'] === 'REVISION_REQUIRED') {
                $result = $this->resubmitLocked($request, $userId);
                if ($ownsTransaction && ($result['success'] ?? false)) {
                    $this->db->commit();
                } elseif ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return $result;
            }
            if ($request['status'] !== 'DRAFT') {
                throw new DomainException('Only a draft request may be submitted');
            }

            if ($this->workflows->findActiveByEntity('AGREEMENT_LIFECYCLE', $requestId) !== null) {
                throw new DomainException('Lifecycle request already has an active workflow');
            }

            $errors = $this->validate($request, true);
            if ($errors !== []) {
                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'errors' => $errors];
            }

            $template = $this->workflows->findActiveTemplate('AGREEMENT_LIFECYCLE');
            if ($template === null) {
                throw new DomainException('Lifecycle workflow template was not found');
            }
            $steps = $this->workflows->findTemplateSteps((int) $template['workflow_template_id']);
            $this->assertTemplate($steps);

            $instanceId = $this->workflows->createInstance([
                'workflow_template_id' => $template['workflow_template_id'],
                'entity_type' => 'AGREEMENT_LIFECYCLE',
                'entity_id' => $requestId,
                'current_step' => 2,
                'status' => 'IN_PROGRESS',
                'started_by' => $userId,
            ]);

            $created = [];
            foreach ($steps as $step) {
                $status = $step['step_key'] === 'CREATOR'
                    ? 'APPROVED'
                    : ($step['step_key'] === 'VP_INITIAL' ? 'IN_PROGRESS' : 'PENDING');
                $created[$step['step_key']] = $this->workflows->createInstanceStep(
                    $instanceId,
                    $step,
                    $status,
                    $step['step_key'] === 'CREATOR' ? $userId : null
                );
            }

            $vpStep = $this->workflows->findStepByKey($instanceId, 'VP_INITIAL', true);
            $assignments = $this->activateOfficeStep($vpStep, 'Initial VP');
            $this->workflows->addHistory(
                $instanceId,
                $created['CREATOR'],
                'SUBMITTED',
                $userId,
                'Lifecycle request submitted for Initial VP review'
            );
            $this->requests->attachWorkflow($requestId, $instanceId);
            $this->audit->write(
                'agreement_lifecycle_requests',
                $requestId,
                'UPDATE',
                $userId,
                $request,
                ['status' => 'UNDER_REVIEW', 'workflow_instance_id' => $instanceId]
            );
            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'success' => true,
                'lifecycle_request_id' => $requestId,
                'workflow_instance_id' => $instanceId,
                'vp_assignments' => $assignments,
                'status' => 'UNDER_REVIEW',
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function decide(
        int $instanceId,
        int $userId,
        string $action,
        ?string $comments,
        bool $includeFinance = false
    ): array {
        $action = strtoupper(trim($action));
        $comments = trim((string) $comments);
        if (!in_array($action, ['APPROVE', 'RETURN', 'REJECT'], true)) {
            throw new InvalidArgumentException('Decision must be APPROVE, RETURN, or REJECT');
        }
        if (in_array($action, ['RETURN', 'REJECT'], true) && $comments === '') {
            throw new InvalidArgumentException('A reason is required for this decision');
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $instance = $this->workflows->findInstanceById($instanceId, true);
            if ($instance === null
                || $instance['entity_type'] !== 'AGREEMENT_LIFECYCLE'
                || $instance['status'] !== 'IN_PROGRESS') {
                throw new DomainException('Lifecycle workflow is not active');
            }
            $request = $this->requests->findById((int) $instance['entity_id'], true);
            if ($request === null || $request['status'] !== 'UNDER_REVIEW') {
                throw new DomainException('Lifecycle request is not under review');
            }

            $activeStep = $this->activeAssignedStep($instanceId, $userId);
            $stepKey = $activeStep['step_key'];
            $mediation = $stepKey === 'VP_FINAL' && $this->hasChangeRequest($instanceId);

            if ($action === 'REJECT') {
                $result = $this->reject($instance, $request, $activeStep, $userId, $comments);
            } elseif ($action === 'RETURN') {
                $result = in_array($stepKey, ['LEGAL_REVIEW', 'FINANCE_REVIEW', 'PRESIDENT_APPROVAL'], true)
                    ? $this->routeChangeRequestToVp($instance, $request, $activeStep, $userId, $comments)
                    : $this->returnToCreator($instance, $request, $activeStep, $userId, $comments);
            } else {
                if ($mediation) {
                    throw new DomainException('VP mediation must return the request to its creator or reject it');
                }
                $result = $this->approveStep(
                    $instance,
                    $request,
                    $activeStep,
                    $userId,
                    $comments === '' ? null : $comments,
                    $includeFinance
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function approveStep(
        array $instance,
        array $request,
        array $step,
        int $userId,
        ?string $comments,
        bool $includeFinance
    ): array {
        $instanceId = (int) $instance['workflow_instance_id'];
        $stepId = (int) $step['instance_step_id'];
        $stepKey = $step['step_key'];
        $this->workflows->setStepStatus($stepId, 'APPROVED', $userId, $comments);
        $this->workflows->deactivateStepAssignments($stepId);
        $this->workflows->addHistory(
            $instanceId,
            $stepId,
            'APPROVED',
            $userId,
            $comments ?: "{$stepKey} approved the lifecycle request"
        );

        if ($stepKey === 'VP_INITIAL') {
            $this->workflows->setFinanceReviewRequired($instanceId, $includeFinance);
            $legal = $this->workflows->findStepByKey($instanceId, 'LEGAL_REVIEW', true);
            $finance = $this->workflows->findStepByKey($instanceId, 'FINANCE_REVIEW', true);
            $this->activateOfficeStep($legal, 'Legal');
            if ($includeFinance) {
                $this->activateOfficeStep($finance, 'Finance');
            } else {
                $this->workflows->setStepStatus(
                    (int) $finance['instance_step_id'],
                    'SKIPPED',
                    null,
                    'Finance review not requested by Initial VP'
                );
            }
            $this->workflows->setCurrentStep($instanceId, 3);
            return $this->decisionResult($request, $instanceId, 'SPECIALIST_REVIEW');
        }

        if (in_array($stepKey, ['LEGAL_REVIEW', 'FINANCE_REVIEW'], true)) {
            $legal = $this->workflows->findStepByKey($instanceId, 'LEGAL_REVIEW', true);
            $finance = $this->workflows->findStepByKey($instanceId, 'FINANCE_REVIEW', true);
            $specialistsComplete = $legal['status'] === 'APPROVED'
                && in_array($finance['status'], ['APPROVED', 'SKIPPED'], true);
            if ($specialistsComplete) {
                $finalVp = $this->workflows->findStepByKey($instanceId, 'VP_FINAL', true);
                $this->activateOfficeStep($finalVp, 'Final VP');
                $this->workflows->setCurrentStep($instanceId, 5);
                return $this->decisionResult($request, $instanceId, 'VP_FINAL');
            }
            return $this->decisionResult($request, $instanceId, 'SPECIALIST_REVIEW');
        }

        if ($stepKey === 'VP_FINAL') {
            $president = $this->workflows->findStepByKey($instanceId, 'PRESIDENT_APPROVAL', true);
            $this->activateOfficeStep($president, 'President');
            $this->workflows->setCurrentStep($instanceId, 6);
            return $this->decisionResult($request, $instanceId, 'PRESIDENT_APPROVAL');
        }

        if ($stepKey === 'PRESIDENT_APPROVAL') {
            $this->workflows->setInstanceStatus($instanceId, 'COMPLETED');
            $this->requests->changeStatus(
                (int) $request['lifecycle_request_id'],
                'APPROVED',
                $userId,
                $comments
            );
            if ($request['request_type'] === 'TERMINATION') {
                $this->requests->recordTermination(
                    (int) $request['agreement_id'],
                    $userId,
                    (string) $request['termination_reason']
                );
            }
            $this->audit->write(
                'agreement_lifecycle_requests',
                (int) $request['lifecycle_request_id'],
                'APPROVE',
                $userId,
                ['status' => 'UNDER_REVIEW'],
                ['status' => 'APPROVED']
            );
            return $this->decisionResult($request, $instanceId, 'COMPLETED', 'APPROVED');
        }

        throw new DomainException('The active lifecycle step cannot be approved');
    }

    private function routeChangeRequestToVp(
        array $instance,
        array $request,
        array $step,
        int $userId,
        string $reason
    ): array {
        $instanceId = (int) $instance['workflow_instance_id'];
        $stepId = (int) $step['instance_step_id'];
        $this->workflows->deactivateInstanceAssignments($instanceId);
        $this->workflows->setStepStatus($stepId, 'CHANGES_REQUESTED', $userId, $reason);
        $finalVp = $this->workflows->findStepByKey($instanceId, 'VP_FINAL', true);
        if ($finalVp === null) {
            throw new DomainException('VP mediation step was not found');
        }
        $this->workflows->prepareStepForReview((int) $finalVp['instance_step_id'], 'PENDING');
        $this->activateOfficeStep($finalVp, 'VP mediation');
        $this->workflows->setCurrentStep($instanceId, 5);
        $this->workflows->addHistory(
            $instanceId,
            $stepId,
            'ROUTED_TO_VP',
            $userId,
            $reason
        );
        return $this->decisionResult($request, $instanceId, 'VP_MEDIATION');
    }

    private function returnToCreator(
        array $instance,
        array $request,
        array $step,
        int $userId,
        string $reason
    ): array {
        $instanceId = (int) $instance['workflow_instance_id'];
        $stepId = (int) $step['instance_step_id'];
        $this->workflows->deactivateInstanceAssignments($instanceId);
        $this->workflows->setStepStatus($stepId, 'CHANGES_REQUESTED', $userId, $reason);
        $creator = $this->workflows->findStepByKey($instanceId, 'CREATOR', true);
        if ($creator === null) {
            throw new DomainException('Creator revision step was not found');
        }
        $creatorId = (int) $creator['instance_step_id'];
        $this->workflows->prepareStepForReview($creatorId, 'IN_PROGRESS');
        $this->workflows->assignUserToStep($creatorId, (int) $request['requested_by']);
        $this->workflows->setRedraftBaseVersion(
            $instanceId,
            $this->requests->latestVersionNumber((int) $request['lifecycle_request_id'])
        );
        $this->workflows->setCurrentStep($instanceId, 1);
        $this->requests->changeStatus((int) $request['lifecycle_request_id'], 'REVISION_REQUIRED');
        $this->workflows->addHistory(
            $instanceId,
            $stepId,
            'ROUTED_TO_CREATOR',
            $userId,
            $reason
        );
        return $this->decisionResult($request, $instanceId, 'CREATOR_REVISION', 'REVISION_REQUIRED');
    }

    private function reject(
        array $instance,
        array $request,
        array $step,
        int $userId,
        string $reason
    ): array {
        $instanceId = (int) $instance['workflow_instance_id'];
        $stepId = (int) $step['instance_step_id'];
        $this->workflows->deactivateInstanceAssignments($instanceId);
        $this->workflows->setStepStatus($stepId, 'REJECTED', $userId, $reason);
        $this->workflows->setInstanceStatus($instanceId, 'REJECTED');
        $this->requests->changeStatus(
            (int) $request['lifecycle_request_id'],
            'REJECTED',
            $userId,
            $reason
        );
        $this->workflows->addHistory($instanceId, $stepId, 'REJECTED', $userId, $reason);
        $this->audit->write(
            'agreement_lifecycle_requests',
            (int) $request['lifecycle_request_id'],
            'REJECT',
            $userId,
            ['status' => 'UNDER_REVIEW'],
            ['status' => 'REJECTED', 'reason' => $reason]
        );
        return $this->decisionResult($request, $instanceId, 'REJECTED', 'REJECTED');
    }

    private function resubmitLocked(array $request, int $userId): array
    {
        $instanceId = (int) ($request['workflow_instance_id'] ?? 0);
        $instance = $this->workflows->findInstanceById($instanceId, true);
        if ($instance === null || $instance['status'] !== 'IN_PROGRESS') {
            throw new DomainException('Lifecycle workflow is not active');
        }
        $errors = $this->validate($request, true);
        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }
        $base = (int) ($instance['redraft_base_version'] ?? 0);
        if ($this->requests->latestVersionNumber((int) $request['lifecycle_request_id']) <= $base) {
            throw new DomainException('Save a revised request version before resubmitting');
        }

        $this->workflows->deactivateInstanceAssignments($instanceId);
        foreach (['CREATOR', 'VP_INITIAL', 'LEGAL_REVIEW', 'FINANCE_REVIEW', 'VP_FINAL', 'PRESIDENT_APPROVAL'] as $key) {
            $step = $this->workflows->findStepByKey($instanceId, $key, true);
            if ($step === null) {
                throw new DomainException("Lifecycle workflow step {$key} was not found");
            }
            $this->workflows->prepareStepForReview(
                (int) $step['instance_step_id'],
                $key === 'CREATOR' ? 'IN_PROGRESS' : 'PENDING'
            );
        }
        $creator = $this->workflows->findStepByKey($instanceId, 'CREATOR', true);
        $this->workflows->setStepStatus(
            (int) $creator['instance_step_id'],
            'APPROVED',
            $userId,
            'Revised lifecycle request resubmitted'
        );
        $vp = $this->workflows->findStepByKey($instanceId, 'VP_INITIAL', true);
        $this->activateOfficeStep($vp, 'Initial VP');
        $this->workflows->clearFinanceReviewRequired($instanceId);
        $this->workflows->clearRedraftBaseVersion($instanceId);
        $this->workflows->incrementReviewCycle($instanceId);
        $this->workflows->setCurrentStep($instanceId, 2);
        $this->requests->changeStatus((int) $request['lifecycle_request_id'], 'UNDER_REVIEW');
        $this->workflows->addHistory(
            $instanceId,
            (int) $creator['instance_step_id'],
            'RESUBMITTED',
            $userId,
            'Revised lifecycle request resubmitted to Initial VP'
        );
        return $this->decisionResult($request, $instanceId, 'VP_INITIAL', 'UNDER_REVIEW');
    }

    private function activeAssignedStep(int $instanceId, int $userId): array
    {
        foreach ($this->workflows->findSteps($instanceId) as $step) {
            if ($step['status'] === 'IN_PROGRESS'
                && $this->workflows->isUserAssignedToStep((int) $step['instance_step_id'], $userId)) {
                return $step;
            }
        }
        throw new DomainException('User is not assigned to the active lifecycle review');
    }

    private function hasChangeRequest(int $instanceId): bool
    {
        foreach ($this->workflows->findSteps($instanceId) as $step) {
            if ($step['status'] === 'CHANGES_REQUESTED') {
                return true;
            }
        }
        return false;
    }

    private function activateOfficeStep(?array $step, string $officeName): int
    {
        if ($step === null || (int) ($step['assigned_unit_id'] ?? 0) <= 0) {
            throw new DomainException("{$officeName} step has no assigned office");
        }
        $stepId = (int) $step['instance_step_id'];
        $this->workflows->setStepStatus($stepId, 'IN_PROGRESS');
        $count = $this->workflows->assignEligibleUsersForUnit(
            $stepId,
            (int) $step['assigned_unit_id']
        );
        if ($count < 1) {
            throw new DomainException("No eligible {$officeName} reviewer is available");
        }
        return $count;
    }

    private function validate(array $data, bool $forSubmission): array
    {
        $errors = [];
        $type = strtoupper(trim((string) ($data['request_type'] ?? '')));
        if (!in_array($type, ['RENEWAL', 'AMENDMENT', 'TERMINATION'], true)) {
            $errors[] = 'Choose renewal, amendment, or termination.';
            return $errors;
        }
        if (!$forSubmission) {
            return $errors;
        }
        if (trim((string) ($data['justification'] ?? '')) === '') {
            $errors[] = 'Justification is required before submission.';
        }
        if ($type === 'RENEWAL') {
            foreach ([
                'activities_summary' => 'Activities summary',
                'achieved_value' => 'Achieved value',
                'proposed_start_date' => 'Proposed start date',
                'proposed_end_date' => 'Proposed end date',
            ] as $field => $label) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[] = "{$label} is required for renewal.";
                }
            }
        } elseif ($type === 'AMENDMENT') {
            foreach ([
                'amendment_type' => 'Amendment type',
                'amendment_reason' => 'Amendment reason',
                'terms_to_amend' => 'Terms to amend',
            ] as $field => $label) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[] = "{$label} is required for amendment.";
                }
            }
        } else {
            if (trim((string) ($data['termination_reason'] ?? '')) === '') {
                $errors[] = 'Termination reason is required.';
            }
            if (trim((string) ($data['proposed_termination_date'] ?? '')) === '') {
                $errors[] = 'Proposed termination date is required.';
            }
            if (!array_key_exists('previous_initiatives', $data) || $data['previous_initiatives'] === null || $data['previous_initiatives'] === '') {
                $errors[] = 'State whether previous initiatives exist.';
            }
        }
        if (!empty($data['proposed_start_date']) && !empty($data['proposed_end_date'])
            && $data['proposed_end_date'] < $data['proposed_start_date']) {
            $errors[] = 'Proposed end date cannot precede the proposed start date.';
        }
        if ($data['financial_amount'] !== null && $data['financial_amount'] !== ''
            && (!is_numeric($data['financial_amount']) || (float) $data['financial_amount'] < 0)) {
            $errors[] = 'Financial amount must be zero or greater.';
        }
        return $errors;
    }

    private function assertTemplate(array $steps): void
    {
        $expected = ['CREATOR', 'VP_INITIAL', 'LEGAL_REVIEW', 'FINANCE_REVIEW', 'VP_FINAL', 'PRESIDENT_APPROVAL'];
        if (array_column($steps, 'step_key') !== $expected) {
            throw new DomainException('Lifecycle workflow template is invalid');
        }
    }

    private function decisionResult(
        array $request,
        int $instanceId,
        string $stage,
        ?string $status = null
    ): array {
        return [
            'success' => true,
            'workflow_instance_id' => $instanceId,
            'lifecycle_request_id' => (int) $request['lifecycle_request_id'],
            'agreement_id' => (int) $request['agreement_id'],
            'request_type' => $request['request_type'],
            'status' => $status ?? 'UNDER_REVIEW',
            'current_stage' => $stage,
        ];
    }

    private function isAdministrator(int $userId): bool
    {
        return in_array('System Administrator', $this->permissions->getRoleNames($userId), true);
    }
}
