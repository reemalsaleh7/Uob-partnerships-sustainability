<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/PermissionService.php';

class HierarchyResolver
{
    private WorkflowRepository $workflowRepository;
    private PermissionService $permissionService;

    public function __construct(
        ?WorkflowRepository $workflowRepository = null,
        ?PermissionService $permissionService = null
    ) {
        $this->workflowRepository =
            $workflowRepository ?? new WorkflowRepository();
        $this->permissionService =
            $permissionService ?? new PermissionService();
    }

    public function resolveUnit(string $unitCode): array
    {
        $normalizedCode = strtoupper(trim($unitCode));

        if ($normalizedCode === '') {
            throw new InvalidArgumentException(
                'Organizational unit code is required'
            );
        }

        $unit = $this->workflowRepository
            ->findActiveUnitByCode($normalizedCode);

        if ($unit === null) {
            throw new DomainException(
                "Active organizational unit {$normalizedCode} was not found"
            );
        }

        return $unit;
    }

    public function resolveEligibleApprovers(
        string $unitCode
    ): array {
        $unit = $this->resolveUnit($unitCode);

        $users = $this->workflowRepository
            ->findEligibleUsersForUnit(
                $unit['code'],
                'APPROVE_AGREEMENT'
            );

        if ($users === []) {
            throw new DomainException(
                "No eligible Agreement approvers are assigned to {$unit['code']}"
            );
        }

        return $users;
    }

    public function resolveApproversForStep(
        array $templateStep
    ): array {
        $unitCode =
            $templateStep['required_unit_code'] ?? null;

        if (!$unitCode) {
            throw new DomainException(
                'Workflow step does not specify a required office'
            );
        }

        return $this->resolveEligibleApprovers($unitCode);
    }

    public function isUserEligibleForUnit(
        int $userId,
        string $unitCode
    ): bool {
        $eligibleUsers =
            $this->resolveEligibleApprovers($unitCode);

        foreach ($eligibleUsers as $user) {
            if ((int) $user['user_id'] === $userId) {
                return true;
            }
        }

        return false;
    }

    public function canStartAgreement(int $userId): bool
    {
        return $this->permissionService->hasPermission(
            $userId,
            'CREATE_AGREEMENT'
        );
    }

    public function assertCanStartAgreement(
        int $userId
    ): void {
        if (!$this->canStartAgreement($userId)) {
            throw new DomainException(
                'Your account is not authorized to start an Agreement workflow'
            );
        }
    }
}
