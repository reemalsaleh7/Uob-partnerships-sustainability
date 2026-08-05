<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/AdminWorkflowTemplateRepository.php';

final class AdminWorkflowTemplateException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class AdminWorkflowTemplateService
{
    private const TEMPLATE_MAP = [
        'AGREEMENT_APPROVAL' => [
            'process_type' => 'AGREEMENT',
            'permission' => 'APPROVE_AGREEMENT',
            'base_name' => 'Agreement Approval',
        ],
        'INITIATIVE_APPROVAL' => [
            'process_type' => 'INITIATIVE',
            'permission' => 'APPROVE_INITIATIVE',
            'base_name' => 'Initiative Approval',
        ],
    ];

    private const AGREEMENT_CORE_ORDER = [
        'CREATOR',
        'VP_INITIAL',
        'LEGAL_REVIEW',
        'FINANCE_REVIEW',
        'VP_FINAL',
        'PRESIDENT_APPROVAL',
    ];

    private const INITIATIVE_SYSTEM_STAGES = [
        'CREATOR',
        'DEPARTMENT_HEAD',
        'DEAN',
        'VICE_PRESIDENT',
        'PRESIDENT',
    ];

    private AdminWorkflowTemplateRepository $repository;

    public function __construct(?AdminWorkflowTemplateRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminWorkflowTemplateRepository();
    }

    public function index(): array
    {
        return [
            'templates' => $this->repository->activeTemplates(),
            'rules' => [
                'changes_apply_to_new_workflows_only' => true,
                'existing_workflow_snapshots_are_preserved' => true,
                'maximum_stages' => 30,
                'agreement_core_stages_are_protected' => true,
            ],
        ];
    }

    public function options(): array
    {
        return [
            'units' => $this->repository->units(),
            'positions' => $this->repository->positions(),
            'responsibility_types' => ['POSITION', 'UNIT'],
            'responsibility_scopes' => [
                'FIXED_UNIT',
                'REQUESTER_DEPARTMENT',
                'REQUESTER_COLLEGE',
                'UNIVERSITY',
            ],
            'execution_modes' => ['SEQUENTIAL', 'PARALLEL'],
        ];
    }

    public function show(string $templateKey): array
    {
        $templateKey = $this->templateKey($templateKey);
        $template = $this->repository->activeTemplate($templateKey);
        if ($template === null) {
            throw new AdminWorkflowTemplateException(
                'The active Workflow template was not found.',
                404
            );
        }

        $template['steps'] = $this->repository->templateSteps(
            (int) $template['workflow_template_id']
        );

        return [
            'template' => $template,
            'versions' => $this->repository->versions($templateKey),
        ];
    }

    public function version(string $templateKey, int $templateId): array
    {
        $templateKey = $this->templateKey($templateKey);
        $template = $this->repository->templateById($templateId);
        if (
            $template === null
            || (string) $template['template_key'] !== $templateKey
        ) {
            throw new AdminWorkflowTemplateException(
                'The Workflow template version was not found.',
                404
            );
        }

        $template['steps'] = $this->repository->templateSteps($templateId);
        return ['template' => $template];
    }

    public function publish(
        int $actorUserId,
        string $templateKey,
        array $input
    ): array {
        if ($actorUserId <= 0) {
            throw new AdminWorkflowTemplateException(
                'The administrator session is unavailable.',
                401
            );
        }

        $templateKey = $this->templateKey($templateKey);
        $definition = self::TEMPLATE_MAP[$templateKey];
        $expectedTemplateId = $this->positiveInteger(
            $input['expected_template_id'] ?? null,
            'Reload the active Workflow template before publishing.'
        );
        $description = $this->nullableText(
            $input['description'] ?? null,
            1000,
            'Template description'
        );
        $reason = $this->requiredText(
            $input['reason'] ?? null,
            'Publishing reason',
            5,
            500
        );

        $rawStages = $input['stages'] ?? null;
        if (!is_array($rawStages)) {
            throw new AdminWorkflowTemplateException(
                'Workflow stages must be provided as a list.'
            );
        }

        $stages = $this->normalizeStages(
            $templateKey,
            $definition['permission'],
            $rawStages
        );

        $this->repository->beginTransaction();

        try {
            $active = $this->repository->activeTemplate(
                $templateKey,
                true
            );
            if ($active === null) {
                throw new AdminWorkflowTemplateException(
                    'The active Workflow template was not found.',
                    404
                );
            }
            if ((int) $active['workflow_template_id'] !== $expectedTemplateId) {
                throw new AdminWorkflowTemplateException(
                    'Another administrator published a newer template. Reload before publishing.',
                    409
                );
            }

            $version = $this->repository->nextVersionNumber($templateKey);
            $name = sprintf('%s v%d', $definition['base_name'], $version);

            $this->repository->deactivateTemplate($expectedTemplateId);
            $newTemplateId = $this->repository->createTemplateVersion(
                $templateKey,
                $definition['process_type'],
                $version,
                $name,
                $description,
                $actorUserId,
                $reason
            );

            foreach ($stages as $stage) {
                $this->repository->insertTemplateStep(
                    $newTemplateId,
                    $stage
                );
            }

            $this->repository->insertPublication(
                $templateKey,
                $expectedTemplateId,
                $newTemplateId,
                $actorUserId,
                $reason
            );

            $this->repository->commit();
            return $this->show($templateKey);
        } catch (Throwable $exception) {
            $this->repository->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    private function normalizeStages(
        string $templateKey,
        string $permissionCode,
        array $rawStages
    ): array {
        if (count($rawStages) < 2 || count($rawStages) > 30) {
            throw new AdminWorkflowTemplateException(
                'A Workflow template must contain between 2 and 30 stages.'
            );
        }

        $normalized = [];
        $usedKeys = [];
        $phaseOrder = 0;

        foreach (array_values($rawStages) as $index => $rawStage) {
            if (!is_array($rawStage)) {
                throw new AdminWorkflowTemplateException(
                    'Each Workflow stage must be an object.'
                );
            }

            $stepOrder = $index + 1;
            $label = $this->requiredText(
                $rawStage['step_label'] ?? null,
                'Stage label',
                2,
                150
            );
            $requestedKey = strtoupper(trim((string) (
                $rawStage['step_key'] ?? ''
            )));
            if ($requestedKey === '') {
                $requestedKey = $this->customStepKey($label, $usedKeys);
            }
            $isSystem = in_array(
                $requestedKey,
                $templateKey === 'AGREEMENT_APPROVAL'
                    ? self::AGREEMENT_CORE_ORDER
                    : self::INITIATIVE_SYSTEM_STAGES,
                true
            );
            if (preg_match('/^[A-Z][A-Z0-9_]{1,79}$/', $requestedKey) !== 1) {
                throw new AdminWorkflowTemplateException(
                    "The stage key {$requestedKey} is invalid."
                );
            }
            if (isset($usedKeys[$requestedKey])) {
                throw new AdminWorkflowTemplateException(
                    "The stage key {$requestedKey} is duplicated."
                );
            }
            $usedKeys[$requestedKey] = true;

            $executionMode = strtoupper(trim((string) (
                $rawStage['execution_mode'] ?? 'SEQUENTIAL'
            )));
            if (!in_array($executionMode, ['SEQUENTIAL', 'PARALLEL'], true)) {
                throw new AdminWorkflowTemplateException(
                    "The execution mode for {$label} is invalid."
                );
            }
            if ($stepOrder === 1 && $executionMode === 'PARALLEL') {
                throw new AdminWorkflowTemplateException(
                    'The first Workflow stage cannot run in parallel with a previous stage.'
                );
            }
            if ($executionMode === 'SEQUENTIAL') {
                $phaseOrder++;
            }

            $isCreator = $requestedKey === 'CREATOR';
            $responsibilityType = $isCreator
                ? 'CREATOR'
                : strtoupper(trim((string) (
                    $rawStage['responsibility_type'] ?? 'POSITION'
                )));
            $responsibilityScope = $isCreator
                ? 'NONE'
                : strtoupper(trim((string) (
                    $rawStage['responsibility_scope'] ?? 'FIXED_UNIT'
                )));

            if (!in_array($responsibilityType, ['CREATOR', 'POSITION', 'UNIT'], true)) {
                throw new AdminWorkflowTemplateException(
                    "The responsibility type for {$label} is invalid."
                );
            }
            if (!in_array($responsibilityScope, [
                'NONE',
                'FIXED_UNIT',
                'REQUESTER_DEPARTMENT',
                'REQUESTER_COLLEGE',
                'UNIVERSITY',
            ], true)) {
                throw new AdminWorkflowTemplateException(
                    "The responsibility scope for {$label} is invalid."
                );
            }

            $unitId = $this->nullablePositiveInteger(
                $rawStage['required_unit_id'] ?? null
            );
            $positionId = $this->nullablePositiveInteger(
                $rawStage['required_position_id'] ?? null
            );

            if ($isCreator) {
                $unitId = null;
                $positionId = null;
                $responsibilityType = 'CREATOR';
                $responsibilityScope = 'NONE';
            } else {
                if ($responsibilityType === 'POSITION' && $positionId === null) {
                    throw new AdminWorkflowTemplateException(
                        "Choose a responsible position for {$label}."
                    );
                }
                if (
                    $responsibilityScope === 'FIXED_UNIT'
                    && $unitId === null
                ) {
                    throw new AdminWorkflowTemplateException(
                        "Choose the fixed organizational unit for {$label}."
                    );
                }
                if (
                    $responsibilityScope !== 'FIXED_UNIT'
                    && $unitId !== null
                ) {
                    $unitId = null;
                }
                if ($unitId !== null) {
                    $unit = $this->repository->unit($unitId);
                    if ($unit === null || !$this->boolean($unit['is_active'] ?? false)) {
                        throw new AdminWorkflowTemplateException(
                            "The organizational unit selected for {$label} is unavailable."
                        );
                    }
                }
                if ($positionId !== null && $this->repository->position($positionId) === null) {
                    throw new AdminWorkflowTemplateException(
                        "The position selected for {$label} is unavailable."
                    );
                }
            }

            $isOptional = $isCreator
                ? false
                : $this->boolean($rawStage['is_optional'] ?? false);
            $allowRevision = $isCreator
                ? false
                : $this->boolean($rawStage['allow_revision'] ?? true);
            $reminderDays = (int) ($rawStage['reminder_after_days'] ?? 3);
            if ($reminderDays < 1 || $reminderDays > 90) {
                throw new AdminWorkflowTemplateException(
                    "Reminder days for {$label} must be between 1 and 90."
                );
            }

            if (
                !$isCreator
                && !$isOptional
                && $responsibilityScope === 'FIXED_UNIT'
                && $this->repository->eligibleReviewerCount(
                    $permissionCode,
                    $unitId,
                    $responsibilityType === 'POSITION' ? $positionId : null
                ) < 1
            ) {
                throw new AdminWorkflowTemplateException(
                    "No active user currently has the required permission and responsibility for {$label}."
                );
            }

            $normalized[] = [
                'step_order' => $stepOrder,
                'step_key' => $requestedKey,
                'step_label' => $label,
                'phase_order' => $phaseOrder,
                'execution_mode' => $executionMode,
                'approval_type' => $isCreator ? 'CREATOR' : 'APPROVAL',
                'required_unit_id' => $unitId,
                'required_position_id' => $positionId,
                'is_optional' => $isOptional,
                'responsibility_type' => $responsibilityType,
                'responsibility_scope' => $responsibilityScope,
                'required_permission_code' => $permissionCode,
                'reminder_after_days' => $reminderDays,
                'is_system_step' => $isSystem,
                'allow_revision' => $allowRevision,
            ];
        }

        $this->assertTemplateRules($templateKey, $normalized);
        return $normalized;
    }

    private function assertTemplateRules(string $templateKey, array $stages): void
    {
        $keys = array_column($stages, 'step_key');
        if ($keys[0] !== 'CREATOR') {
            throw new AdminWorkflowTemplateException(
                'The Creator must remain the first system stage.'
            );
        }

        if ($templateKey === 'AGREEMENT_APPROVAL') {
            foreach (self::AGREEMENT_CORE_ORDER as $coreKey) {
                if (!in_array($coreKey, $keys, true)) {
                    throw new AdminWorkflowTemplateException(
                        "The protected Agreement stage {$coreKey} cannot be deleted."
                    );
                }
            }

            $positions = array_flip($keys);
            $last = -1;
            foreach (self::AGREEMENT_CORE_ORDER as $coreKey) {
                $current = (int) $positions[$coreKey];
                if ($current <= $last) {
                    throw new AdminWorkflowTemplateException(
                        'The protected Agreement stages must retain their relative order.'
                    );
                }
                $last = $current;
            }
            if (end($keys) !== 'PRESIDENT_APPROVAL') {
                throw new AdminWorkflowTemplateException(
                    'President approval must remain the final Agreement stage.'
                );
            }
        } else {
            if (count($stages) < 2) {
                throw new AdminWorkflowTemplateException(
                    'The Initiative template requires at least one approval stage after the Creator.'
                );
            }
        }

        $phaseGroups = [];
        foreach ($stages as $stage) {
            $phaseGroups[$stage['phase_order']][] = $stage;
        }
        foreach ($phaseGroups as $phaseStages) {
            if (count($phaseStages) > 1) {
                foreach ($phaseStages as $offset => $stage) {
                    if ($stage['step_key'] === 'CREATOR') {
                        throw new AdminWorkflowTemplateException(
                            'The Creator cannot run in parallel with an approval stage.'
                        );
                    }
                    if ($offset > 0 && $stage['execution_mode'] !== 'PARALLEL') {
                        throw new AdminWorkflowTemplateException(
                            'Stages sharing one phase must be marked Parallel after the first stage.'
                        );
                    }
                }
            }
        }
    }

    private function templateKey(string $value): string
    {
        $key = strtoupper(trim($value));
        if (!array_key_exists($key, self::TEMPLATE_MAP)) {
            throw new AdminWorkflowTemplateException(
                'The selected Workflow template is not supported.',
                404
            );
        }
        return $key;
    }

    private function customStepKey(string $label, array $usedKeys): string
    {
        $base = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $label));
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'STAGE';
        }
        $base = substr($base, 0, 48);
        $key = 'CUSTOM_' . $base;
        $suffix = 1;
        while (isset($usedKeys[$key])) {
            $suffix++;
            $key = 'CUSTOM_' . substr($base, 0, 42) . '_' . $suffix;
        }
        return $key;
    }

    private function requiredText(
        mixed $value,
        string $label,
        int $minimum,
        int $maximum
    ): string {
        $text = trim((string) $value);
        $length = mb_strlen($text);
        if ($length < $minimum || $length > $maximum) {
            throw new AdminWorkflowTemplateException(
                "{$label} must contain between {$minimum} and {$maximum} characters."
            );
        }
        return $text;
    }

    private function nullableText(mixed $value, int $maximum, string $label): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maximum) {
            throw new AdminWorkflowTemplateException(
                "{$label} cannot exceed {$maximum} characters."
            );
        }
        return $text;
    }

    private function positiveInteger(mixed $value, string $message): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($integer === false) {
            throw new AdminWorkflowTemplateException($message, 409);
        }
        return (int) $integer;
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($integer === false) {
            throw new AdminWorkflowTemplateException(
                'A selected identifier is invalid.'
            );
        }
        return (int) $integer;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
