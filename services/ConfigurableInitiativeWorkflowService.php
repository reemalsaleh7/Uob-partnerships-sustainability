<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/WorkflowActorResolver.php';

final class ConfigurableInitiativeWorkflowService
{
    private PDO $db;
    private WorkflowActorResolver $resolver;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->resolver = new WorkflowActorResolver($this->db);
    }

    public function isConfigurableRequest(int $requestId): bool
    {
        $statement = $this->db->prepare(
            "SELECT workflow_template_id IS NOT NULL
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        return $this->databaseBoolean($statement->fetchColumn());
    }

    public function createStages(
        int $requestId,
        string $requesterRoleKey,
        ?int $requesterUnitId,
        int $cycleNumber = 0
    ): void {
        $template = $this->templateForRequest($requestId, $cycleNumber);
        if ($template === null) {
            throw new DomainException(
                'The active Initiative Workflow template was not found.'
            );
        }
        $steps = $this->templateSteps((int) $template['workflow_template_id']);
        if ($steps === [] || (string) $steps[0]['step_key'] !== 'CREATOR') {
            throw new DomainException(
                'The Initiative Workflow template is invalid.'
            );
        }

        $skippedCore = $this->coreStagesBeforeRequester($requesterRoleKey);
        $inserted = [];
        foreach ($steps as $step) {
            $key = (string) $step['step_key'];
            if ($key === 'CREATOR' || in_array($key, $skippedCore, true)) {
                continue;
            }

            $unitId = $this->resolver->resolveUnitId(
                (string) $step['responsibility_scope'],
                $step['required_unit_id'] === null
                    ? null
                    : (int) $step['required_unit_id'],
                $requesterUnitId
            );
            $positionId = $step['required_position_id'] === null
                ? null
                : (int) $step['required_position_id'];
            if ($unitId === null) {
                throw new DomainException(
                    sprintf(
                        'The organizational unit for Initiative stage %s could not be resolved.',
                        (string) $step['step_label']
                    )
                );
            }
            $eligible = $this->resolver->eligibleUserIds(
                (string) $step['required_permission_code'],
                $unitId,
                (string) $step['responsibility_type'] === 'POSITION'
                    ? $positionId
                    : null
            );
            $optional = $this->databaseBoolean($step['is_optional']);
            if ($eligible === [] && !$optional) {
                throw new DomainException(
                    sprintf(
                        'No active reviewer is available for Initiative stage: %s.',
                        (string) $step['step_label']
                    )
                );
            }
            $assigneeId = $eligible[0] ?? null;
            $status = $assigneeId === null ? 'SKIPPED' : 'PENDING';
            $stageId = $this->insertStage(
                $requestId,
                $cycleNumber,
                $step,
                $unitId,
                $positionId,
                $assigneeId,
                $status
            );
            $inserted[] = [
                'request_stage_id' => $stageId,
                'stage_order' => (int) $step['step_order'],
                'phase_order' => (int) $step['phase_order'],
                'stage_key' => $key,
                'stage_label' => (string) $step['step_label'],
                'assigned_user_id' => $assigneeId,
                'responsible_unit_id' => $unitId,
                'status' => $status,
            ];
        }

        $firstPhase = $this->firstPendingPhase($inserted);
        if ($firstPhase === null) {
            $this->updateRequestApprovedWithoutStages(
                $requestId,
                (int) $template['workflow_template_id'],
                (int) $template['version_number']
            );
            return;
        }
        $this->activatePhase($requestId, $cycleNumber, $firstPhase);
        $this->updateRequestCurrentPhase(
            $requestId,
            $cycleNumber,
            $firstPhase,
            (int) $template['workflow_template_id'],
            (int) $template['version_number']
        );
        $this->notifyActivePhase($requestId, $cycleNumber, $firstPhase);
    }

    public function decisionActorContext(int $requestId, int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                stage.request_stage_id,
                stage.assigned_user_id,
                COALESCE(
                    NULLIF(TRIM(CONCAT(principal.first_name, ' ', principal.last_name)), ''),
                    principal.email
                ) AS assigned_user_name,
                CASE
                    WHEN stage.assigned_user_id = :direct_user_id THEN FALSE
                    ELSE TRUE
                END AS acting_as_delegate
             FROM initiative_requests request
             JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.phase_order = request.current_phase_order
              AND stage.status IN ('IN_PROGRESS', 'DISCUSSING_REVISION')
             JOIN users principal
               ON principal.user_id = stage.assigned_user_id
             WHERE request.request_id = :request_id
               AND request.workflow_template_id IS NOT NULL
               AND request.status IN (
                    'UNDER_REVIEW',
                    'RESUBMITTED',
                    'REVISION_DISCUSSION'
               )
               AND (
                    stage.assigned_user_id = :assigned_user_id
                    OR (
                        stage.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions assignment
                            JOIN users delegate
                              ON delegate.user_id = assignment.user_id
                             AND delegate.is_active = TRUE
                            JOIN user_roles user_role
                              ON user_role.user_id = delegate.user_id
                            JOIN role_permissions role_permission
                              ON role_permission.role_id = user_role.role_id
                            JOIN permissions permission
                              ON permission.permission_id = role_permission.permission_id
                             AND permission.permission_code =
                                 stage.required_permission_code
                            WHERE assignment.user_id = :delegate_user_id
                              AND assignment.unit_id = stage.responsible_unit_id
                              AND assignment.is_active = TRUE
                              AND (
                                   assignment.end_date IS NULL
                                   OR assignment.end_date >= CURRENT_DATE
                              )
                        )
                    )
               )
             ORDER BY stage.stage_order
             LIMIT 1"
        );
        $statement->execute([
            'direct_user_id' => $userId,
            'request_id' => $requestId,
            'assigned_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function markCurrentStageOpened(int $requestId, int $userId): void
    {
        $actor = $this->decisionActorContext($requestId, $userId);
        if ($actor === null) {
            return;
        }
        $statement = $this->db->prepare(
            "UPDATE initiative_request_stages
             SET opened_at = COALESCE(opened_at, CURRENT_TIMESTAMP)
             WHERE request_stage_id = :stage_id
               AND status IN ('IN_PROGRESS', 'DISCUSSING_REVISION')"
        );
        $statement->execute([
            'stage_id' => (int) $actor['request_stage_id'],
        ]);
    }

    public function decide(
        int $requestId,
        int $userId,
        string $action,
        ?string $comment
    ): array {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['APPROVE', 'REQUEST_REVISION', 'REJECT'], true)) {
            throw new InvalidArgumentException(
                'The selected Initiative decision is not supported.'
            );
        }
        $comment = $this->nullableText($comment);
        if (in_array($action, ['REQUEST_REVISION', 'REJECT'], true)
            && ($comment === null || mb_strlen($comment) < 10)) {
            throw new InvalidArgumentException(
                'A reason of at least 10 characters is required.'
            );
        }

        $this->db->beginTransaction();
        try {
            $context = $this->decisionContextForUpdate($requestId, $userId);
            if ($context === null) {
                throw new InvalidArgumentException(
                    'This Initiative request is not awaiting your decision.'
                );
            }
            $stageId = (int) $context['request_stage_id'];
            $phaseOrder = (int) $context['phase_order'];
            $cycleNumber = (int) $context['revision_cycle'];
            $principalId = (int) $context['assigned_user_id'];
            $actedOnBehalf = $principalId === $userId ? null : $principalId;

            if ($action === 'APPROVE') {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'APPROVED',
                    $comment,
                    $actedOnBehalf
                );

                if ($this->phaseHasOpenStages($requestId, $cycleNumber, $phaseOrder)) {
                    $this->updateRequestCurrentPhase(
                        $requestId,
                        $cycleNumber,
                        $phaseOrder,
                        (int) $context['workflow_template_id'],
                        (int) $context['workflow_template_version']
                    );
                    $result = [
                        'request_id' => $requestId,
                        'status' => 'UNDER_REVIEW',
                        'current_phase_order' => $phaseOrder,
                        'current_stage_label' => 'Parallel reviews in progress',
                    ];
                } else {
                    $nextPhase = $this->nextPendingPhase(
                        $requestId,
                        $cycleNumber,
                        $phaseOrder
                    );
                    if ($nextPhase === null) {
                        $this->approveRequest($requestId);
                        $result = [
                            'request_id' => $requestId,
                            'status' => 'APPROVED',
                            'current_phase_order' => null,
                            'current_stage_label' => null,
                        ];
                    } else {
                        $this->activatePhase($requestId, $cycleNumber, $nextPhase);
                        $this->updateRequestCurrentPhase(
                            $requestId,
                            $cycleNumber,
                            $nextPhase,
                            (int) $context['workflow_template_id'],
                            (int) $context['workflow_template_version']
                        );
                        $this->notifyActivePhase($requestId, $cycleNumber, $nextPhase);
                        $result = [
                            'request_id' => $requestId,
                            'status' => 'UNDER_REVIEW',
                            'current_phase_order' => $nextPhase,
                            'current_stage_label' => $this->phaseLabel(
                                $requestId,
                                $cycleNumber,
                                $nextPhase
                            ),
                        ];
                    }
                }
                $this->recordEvent(
                    $requestId,
                    $userId,
                    'STAGE_APPROVED',
                    sprintf(
                        '%s approved the Initiative request.',
                        (string) $context['stage_label']
                    ),
                    $stageId,
                    null,
                    (string) $context['request_status'],
                    (string) $result['status'],
                    [
                        'phase_order' => $phaseOrder,
                        'acted_on_behalf_of_user_id' => $actedOnBehalf,
                    ]
                );
            } elseif ($action === 'REQUEST_REVISION') {
                if (!$this->databaseBoolean($context['allow_revision'])) {
                    throw new DomainException(
                        'This Workflow stage does not allow revision requests.'
                    );
                }
                $this->completeStage(
                    $stageId,
                    $userId,
                    'CHANGES_REQUESTED',
                    $comment,
                    $actedOnBehalf
                );
                $this->returnRequestForRevision($context, $userId, $comment ?? '');
                $result = [
                    'request_id' => $requestId,
                    'status' => 'REVISION_REQUIRED',
                    'current_phase_order' => $phaseOrder,
                    'current_stage_label' => (string) $context['stage_label'],
                ];
            } else {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'REJECTED',
                    $comment,
                    $actedOnBehalf
                );
                $this->rejectRequest($requestId);
                $this->recordEvent(
                    $requestId,
                    $userId,
                    'REQUEST_REJECTED',
                    $comment ?? 'Initiative request rejected.',
                    $stageId,
                    (int) $context['requester_id'],
                    (string) $context['request_status'],
                    'REJECTED',
                    ['phase_order' => $phaseOrder]
                );
                $result = [
                    'request_id' => $requestId,
                    'status' => 'REJECTED',
                    'current_phase_order' => null,
                    'current_stage_label' => null,
                ];
            }

            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function adminSkip(int $requestId, int $userId, string $reason): array
    {
        if (!$this->isSystemAdministrator($userId)) {
            throw new DomainException(
                'Only a System Administrator can skip an Initiative approval stage.'
            );
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException(
                'An administrative skip reason of at least 10 characters is required.'
            );
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "SELECT
                    request.request_id,
                    request.revision_cycle,
                    request.current_phase_order,
                    request.workflow_template_id,
                    request.workflow_template_version,
                    stage.request_stage_id,
                    stage.stage_label,
                    stage.assigned_user_id
                 FROM initiative_requests request
                 JOIN initiative_request_stages stage
                   ON stage.request_id = request.request_id
                  AND stage.cycle_number = request.revision_cycle
                  AND stage.phase_order = request.current_phase_order
                  AND stage.status = 'IN_PROGRESS'
                  AND stage.is_skippable = TRUE
                 WHERE request.request_id = :request_id
                   AND request.workflow_template_id IS NOT NULL
                 ORDER BY stage.stage_order
                 LIMIT 1
                 FOR UPDATE OF request, stage"
            );
            $statement->execute(['request_id' => $requestId]);
            $context = $statement->fetch();
            if (!$context) {
                throw new InvalidArgumentException(
                    'This Initiative request has no current skippable stage.'
                );
            }
            $stageId = (int) $context['request_stage_id'];
            $phaseOrder = (int) $context['current_phase_order'];
            $cycleNumber = (int) $context['revision_cycle'];
            $this->completeStage($stageId, $userId, 'SKIPPED', $reason);
            $this->insertSkipRecord(
                $requestId,
                $stageId,
                $userId,
                $context['assigned_user_id'] === null
                    ? null
                    : (int) $context['assigned_user_id'],
                $reason
            );

            if (!$this->phaseHasOpenStages($requestId, $cycleNumber, $phaseOrder)) {
                $nextPhase = $this->nextPendingPhase(
                    $requestId,
                    $cycleNumber,
                    $phaseOrder
                );
                if ($nextPhase === null) {
                    $this->approveRequest($requestId);
                    $status = 'APPROVED';
                } else {
                    $this->activatePhase($requestId, $cycleNumber, $nextPhase);
                    $this->updateRequestCurrentPhase(
                        $requestId,
                        $cycleNumber,
                        $nextPhase,
                        (int) $context['workflow_template_id'],
                        (int) $context['workflow_template_version']
                    );
                    $this->notifyActivePhase($requestId, $cycleNumber, $nextPhase);
                    $status = 'UNDER_REVIEW';
                }
            } else {
                $this->updateRequestCurrentPhase(
                    $requestId,
                    $cycleNumber,
                    $phaseOrder,
                    (int) $context['workflow_template_id'],
                    (int) $context['workflow_template_version']
                );
                $nextPhase = $phaseOrder;
                $status = 'UNDER_REVIEW';
            }

            $this->recordEvent(
                $requestId,
                $userId,
                'STAGE_SKIPPED',
                sprintf(
                    '%s was skipped by a System Administrator. Reason: %s',
                    (string) $context['stage_label'],
                    $reason
                ),
                $stageId,
                null,
                'UNDER_REVIEW',
                $status,
                ['phase_order' => $phaseOrder]
            );
            $this->db->commit();
            return [
                'request_id' => $requestId,
                'status' => $status,
                'current_phase_order' => $nextPhase,
                'skipped_stage_label' => (string) $context['stage_label'],
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function templateForRequest(int $requestId, int $cycleNumber): ?array
    {
        $statement = $this->db->prepare(
            "SELECT workflow_template_id
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        $existingTemplateId = $statement->fetchColumn();

        if ($cycleNumber > 0 && $existingTemplateId !== false && $existingTemplateId !== null) {
            $template = $this->db->prepare(
                "SELECT *
                 FROM workflow_templates
                 WHERE workflow_template_id = :template_id
                   AND template_key = 'INITIATIVE_APPROVAL'"
            );
            $template->execute(['template_id' => (int) $existingTemplateId]);
            $row = $template->fetch();
            return $row ?: null;
        }

        $template = $this->db->query(
            "SELECT *
             FROM workflow_templates
             WHERE template_key = 'INITIATIVE_APPROVAL'
               AND process_type = 'INITIATIVE'
               AND is_active = TRUE
             ORDER BY version_number DESC, workflow_template_id DESC
             LIMIT 1"
        );
        $row = $template->fetch();
        return $row ?: null;
    }

    private function templateSteps(int $templateId): array
    {
        $statement = $this->db->prepare(
            "SELECT *
             FROM workflow_template_steps
             WHERE workflow_template_id = :template_id
             ORDER BY step_order"
        );
        $statement->execute(['template_id' => $templateId]);
        return $statement->fetchAll();
    }

    /** @return list<string> */
    private function coreStagesBeforeRequester(string $roleKey): array
    {
        return match (strtoupper(trim($roleKey))) {
            'DEPARTMENT_HEAD' => ['DEPARTMENT_HEAD'],
            'DEAN' => ['DEPARTMENT_HEAD', 'DEAN'],
            'VP_OFFICE' => ['DEPARTMENT_HEAD', 'DEAN'],
            'VICE_PRESIDENT' => [
                'DEPARTMENT_HEAD',
                'DEAN',
                'VICE_PRESIDENT',
            ],
            'PRESIDENT_OFFICE' => [
                'DEPARTMENT_HEAD',
                'DEAN',
                'VICE_PRESIDENT',
            ],
            'PRESIDENT' => [
                'DEPARTMENT_HEAD',
                'DEAN',
                'VICE_PRESIDENT',
                'PRESIDENT',
            ],
            default => [],
        };
    }

    private function insertStage(
        int $requestId,
        int $cycleNumber,
        array $step,
        ?int $unitId,
        ?int $positionId,
        ?int $assigneeId,
        string $status
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_request_stages (
                request_id,
                cycle_number,
                stage_order,
                stage_key,
                stage_label,
                responsible_unit_id,
                assigned_user_id,
                status,
                reminder_after_days,
                is_office_delegable,
                is_skippable,
                template_step_id,
                phase_order,
                execution_mode,
                is_optional,
                required_position_id,
                required_permission_code,
                responsibility_scope,
                received_at,
                due_at
             ) VALUES (
                :request_id,
                :cycle_number,
                :stage_order,
                :stage_key,
                :stage_label,
                :responsible_unit_id,
                :assigned_user_id,
                :status,
                :reminder_after_days,
                :is_office_delegable,
                TRUE,
                :template_step_id,
                :phase_order,
                :execution_mode,
                :is_optional,
                :required_position_id,
                :required_permission_code,
                :responsibility_scope,
                CASE WHEN :status_for_received = 'SKIPPED' THEN CURRENT_TIMESTAMP ELSE NULL END,
                NULL
             )
             RETURNING request_stage_id"
        );
        $values = [
            ':request_id' => [$requestId, PDO::PARAM_INT],
            ':cycle_number' => [$cycleNumber, PDO::PARAM_INT],
            ':stage_order' => [(int) $step['step_order'], PDO::PARAM_INT],
            ':stage_key' => [(string) $step['step_key'], PDO::PARAM_STR],
            ':stage_label' => [(string) $step['step_label'], PDO::PARAM_STR],
            ':responsible_unit_id' => [$unitId, $unitId === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':assigned_user_id' => [$assigneeId, $assigneeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':status' => [$status, PDO::PARAM_STR],
            ':reminder_after_days' => [(int) $step['reminder_after_days'], PDO::PARAM_INT],
            ':is_office_delegable' => [
                (string) $step['responsibility_type'] === 'UNIT'
                    || in_array(
                        (string) $step['step_key'],
                        ['VICE_PRESIDENT', 'PRESIDENT'],
                        true
                    ),
                PDO::PARAM_BOOL,
            ],
            ':template_step_id' => [(int) $step['template_step_id'], PDO::PARAM_INT],
            ':phase_order' => [(int) $step['phase_order'], PDO::PARAM_INT],
            ':execution_mode' => [(string) $step['execution_mode'], PDO::PARAM_STR],
            ':is_optional' => [$this->databaseBoolean($step['is_optional']), PDO::PARAM_BOOL],
            ':required_position_id' => [$positionId, $positionId === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':required_permission_code' => [(string) $step['required_permission_code'], PDO::PARAM_STR],
            ':responsibility_scope' => [(string) $step['responsibility_scope'], PDO::PARAM_STR],
            ':status_for_received' => [$status, PDO::PARAM_STR],
        ];
        foreach ($values as $name => [$value, $type]) {
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    private function firstPendingPhase(array $stages): ?int
    {
        $phases = [];
        foreach ($stages as $stage) {
            if ((string) $stage['status'] === 'PENDING') {
                $phases[] = (int) $stage['phase_order'];
            }
        }
        return $phases === [] ? null : min($phases);
    }

    private function activatePhase(int $requestId, int $cycleNumber, int $phaseOrder): void
    {
        $statement = $this->db->prepare(
            "UPDATE initiative_request_stages
             SET status = 'IN_PROGRESS',
                 received_at = COALESCE(received_at, CURRENT_TIMESTAMP),
                 due_at = CURRENT_TIMESTAMP
                    + make_interval(days => reminder_after_days)
             WHERE request_id = :request_id
               AND cycle_number = :cycle_number
               AND phase_order = :phase_order
               AND status = 'PENDING'
               AND assigned_user_id IS NOT NULL"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
        if ($statement->rowCount() < 1) {
            throw new DomainException(
                'The next Initiative Workflow phase could not be activated.'
            );
        }
    }

    private function updateRequestCurrentPhase(
        int $requestId,
        int $cycleNumber,
        int $phaseOrder,
        int $templateId,
        int $templateVersion
    ): void {
        $activeStatement = $this->db->prepare(
            "SELECT
                stage_order,
                assigned_user_id,
                responsible_unit_id
             FROM initiative_request_stages
             WHERE request_id = :request_id
               AND cycle_number = :cycle_number
               AND phase_order = :phase_order
               AND status = 'IN_PROGRESS'
             ORDER BY stage_order
             LIMIT 1"
        );
        $activeStatement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
        $active = $activeStatement->fetch();
        if (!$active) {
            throw new DomainException(
                'The Initiative Workflow phase has no active reviewer.'
            );
        }

        $statement = $this->db->prepare(
            "UPDATE initiative_requests
             SET workflow_template_id = :template_id,
                 workflow_template_version = :template_version,
                 current_phase_order = :phase_order,
                 current_stage_order = :stage_order,
                 current_assignee_id = :assigned_user_id,
                 current_assignee_unit_id = :responsible_unit_id,
                 status = 'UNDER_REVIEW',
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'template_id' => $templateId,
            'template_version' => $templateVersion,
            'phase_order' => $phaseOrder,
            'stage_order' => (int) $active['stage_order'],
            'assigned_user_id' => (int) $active['assigned_user_id'],
            'responsible_unit_id' => $active['responsible_unit_id'] === null
                ? null
                : (int) $active['responsible_unit_id'],
            'request_id' => $requestId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                'The Initiative request could not move to the selected Workflow phase.'
            );
        }
    }

    private function updateRequestApprovedWithoutStages(
        int $requestId,
        int $templateId,
        int $templateVersion
    ): void {
        $statement = $this->db->prepare(
            "UPDATE initiative_requests
             SET workflow_template_id = :template_id,
                 workflow_template_version = :template_version,
                 status = 'APPROVED',
                 approved_at = CURRENT_TIMESTAMP,
                 current_phase_order = NULL,
                 current_stage_order = NULL,
                 current_assignee_id = NULL,
                 current_assignee_unit_id = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'template_id' => $templateId,
            'template_version' => $templateVersion,
            'request_id' => $requestId,
        ]);
    }

    private function decisionContextForUpdate(int $requestId, int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                request.request_id,
                request.request_code,
                request.status AS request_status,
                request.revision_cycle,
                request.requester_id,
                request.requester_unit_id,
                request.workflow_template_id,
                request.workflow_template_version,
                stage.request_stage_id,
                stage.stage_order,
                stage.phase_order,
                stage.stage_key,
                stage.stage_label,
                stage.assigned_user_id,
                stage.responsible_unit_id,
                template_step.allow_revision,
                CASE WHEN stage.assigned_user_id = :direct_user_id
                    THEN FALSE ELSE TRUE END AS acting_as_delegate
             FROM initiative_requests request
             JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.phase_order = request.current_phase_order
              AND stage.status = 'IN_PROGRESS'
             JOIN workflow_template_steps template_step
               ON template_step.template_step_id = stage.template_step_id
             WHERE request.request_id = :request_id
               AND request.workflow_template_id IS NOT NULL
               AND request.status IN ('UNDER_REVIEW', 'RESUBMITTED')
               AND (
                    stage.assigned_user_id = :assigned_user_id
                    OR (
                        stage.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions assignment
                            JOIN users delegate
                              ON delegate.user_id = assignment.user_id
                             AND delegate.is_active = TRUE
                            JOIN user_roles user_role
                              ON user_role.user_id = delegate.user_id
                            JOIN role_permissions role_permission
                              ON role_permission.role_id = user_role.role_id
                            JOIN permissions permission
                              ON permission.permission_id = role_permission.permission_id
                             AND permission.permission_code =
                                 stage.required_permission_code
                            WHERE assignment.user_id = :delegate_user_id
                              AND assignment.unit_id = stage.responsible_unit_id
                              AND assignment.is_active = TRUE
                              AND (
                                   assignment.end_date IS NULL
                                   OR assignment.end_date >= CURRENT_DATE
                              )
                        )
                    )
               )
             ORDER BY stage.stage_order
             LIMIT 1
             FOR UPDATE OF request, stage"
        );
        $statement->execute([
            'direct_user_id' => $userId,
            'request_id' => $requestId,
            'assigned_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function completeStage(
        int $stageId,
        int $userId,
        string $status,
        ?string $comment,
        ?int $actedOnBehalf = null
    ): void {
        $statement = $this->db->prepare(
            "UPDATE initiative_request_stages
             SET status = :status,
                 acted_by_user_id = :user_id,
                 acted_on_behalf_of_user_id = :acted_on_behalf,
                 acted_at = CURRENT_TIMESTAMP,
                 opened_at = COALESCE(opened_at, CURRENT_TIMESTAMP),
                 decision_comment = :comment
             WHERE request_stage_id = :stage_id
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'acted_on_behalf' => $actedOnBehalf,
            'comment' => $comment,
            'stage_id' => $stageId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                'The Initiative Workflow stage could not be completed.'
            );
        }
    }

    private function phaseHasOpenStages(
        int $requestId,
        int $cycleNumber,
        int $phaseOrder
    ): bool {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_request_stages
                WHERE request_id = :request_id
                  AND cycle_number = :cycle_number
                  AND phase_order = :phase_order
                  AND status = 'IN_PROGRESS'
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
        return $this->databaseBoolean($statement->fetchColumn());
    }

    private function nextPendingPhase(
        int $requestId,
        int $cycleNumber,
        int $phaseOrder
    ): ?int {
        $statement = $this->db->prepare(
            "SELECT MIN(phase_order)
             FROM initiative_request_stages
             WHERE request_id = :request_id
               AND cycle_number = :cycle_number
               AND phase_order > :phase_order
               AND status = 'PENDING'"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
        $value = $statement->fetchColumn();
        return $value === false || $value === null ? null : (int) $value;
    }

    private function approveRequest(int $requestId): void
    {
        $statement = $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'APPROVED',
                 approved_at = CURRENT_TIMESTAMP,
                 current_phase_order = NULL,
                 current_stage_order = NULL,
                 current_assignee_id = NULL,
                 current_assignee_unit_id = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
    }

    private function rejectRequest(int $requestId): void
    {
        $statement = $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'REJECTED',
                 rejected_at = CURRENT_TIMESTAMP,
                 current_phase_order = NULL,
                 current_stage_order = NULL,
                 current_assignee_id = NULL,
                 current_assignee_unit_id = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
    }

    private function returnRequestForRevision(
        array $context,
        int $userId,
        string $comment
    ): void {
        $update = $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'REVISION_REQUIRED',
                 current_assignee_id = requester_id,
                 current_assignee_unit_id = requester_unit_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $update->execute(['request_id' => (int) $context['request_id']]);

        $thread = $this->db->prepare(
            "INSERT INTO initiative_revision_threads (
                request_id,
                cycle_number,
                requested_by_stage_id,
                counterpart_stage_id,
                requested_by_user_id,
                status,
                consolidated_note,
                sent_to_creator_at,
                last_comment_at
             ) VALUES (
                :request_id,
                :cycle_number,
                :stage_id,
                NULL,
                :user_id,
                'OPEN',
                :comment,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
             )
             RETURNING revision_thread_id"
        );
        $thread->execute([
            'request_id' => (int) $context['request_id'],
            'cycle_number' => (int) $context['revision_cycle'],
            'stage_id' => (int) $context['request_stage_id'],
            'user_id' => $userId,
            'comment' => $comment,
        ]);
        $threadId = (int) $thread->fetchColumn();
        $commentInsert = $this->db->prepare(
            "INSERT INTO initiative_revision_comments (
                revision_thread_id,
                author_user_id,
                visibility,
                comment_text
             ) VALUES (
                :thread_id,
                :user_id,
                'PUBLIC',
                :comment
             )"
        );
        $commentInsert->execute([
            'thread_id' => $threadId,
            'user_id' => $userId,
            'comment' => $comment,
        ]);
        $this->recordEvent(
            (int) $context['request_id'],
            $userId,
            'REVISION_REQUESTED',
            $comment,
            (int) $context['request_stage_id'],
            (int) $context['requester_id'],
            (string) $context['request_status'],
            'REVISION_REQUIRED',
            ['revision_thread_id' => $threadId]
        );
    }

    private function notifyActivePhase(int $requestId, int $cycleNumber, int $phaseOrder): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_notifications (
                request_id,
                recipient_user_id,
                notification_type,
                title,
                message,
                payload
             )
             SELECT
                stage.request_id,
                stage.assigned_user_id,
                'APPROVAL_ASSIGNED',
                'Initiative request awaiting your decision',
                stage.stage_label || ' is now awaiting your review.',
                jsonb_build_object(
                    'request_stage_id', stage.request_stage_id,
                    'phase_order', stage.phase_order
                )
             FROM initiative_request_stages stage
             WHERE stage.request_id = :request_id
               AND stage.cycle_number = :cycle_number
               AND stage.phase_order = :phase_order
               AND stage.status = 'IN_PROGRESS'
               AND stage.assigned_user_id IS NOT NULL"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
    }

    private function phaseLabel(int $requestId, int $cycleNumber, int $phaseOrder): string
    {
        $statement = $this->db->prepare(
            "SELECT STRING_AGG(stage_label, ' + ' ORDER BY stage_order)
             FROM initiative_request_stages
             WHERE request_id = :request_id
               AND cycle_number = :cycle_number
               AND phase_order = :phase_order
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'phase_order' => $phaseOrder,
        ]);
        return (string) ($statement->fetchColumn() ?: 'Workflow review');
    }

    private function insertSkipRecord(
        int $requestId,
        int $stageId,
        int $skippedBy,
        ?int $skippedUserId,
        string $reason
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_admin_skips (
                request_id,
                request_stage_id,
                skipped_by,
                skipped_user_id,
                mandatory_reason
             ) VALUES (
                :request_id,
                :stage_id,
                :skipped_by,
                :skipped_user_id,
                :reason
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'stage_id' => $stageId,
            'skipped_by' => $skippedBy,
            'skipped_user_id' => $skippedUserId,
            'reason' => $reason,
        ]);
    }

    private function recordEvent(
        int $requestId,
        int $actorUserId,
        string $eventType,
        string $note,
        ?int $stageId,
        ?int $targetUserId,
        ?string $fromStatus,
        ?string $toStatus,
        array $eventData
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_request_events (
                request_id,
                request_stage_id,
                event_type,
                actor_user_id,
                target_user_id,
                from_status,
                to_status,
                event_note,
                event_data
             ) VALUES (
                :request_id,
                :stage_id,
                :event_type,
                :actor_user_id,
                :target_user_id,
                :from_status,
                :to_status,
                :event_note,
                CAST(:event_data AS JSONB)
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'stage_id' => $stageId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_note' => $note,
            'event_data' => json_encode(
                $eventData,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    private function isSystemAdministrator(int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                JOIN users account
                  ON account.user_id = user_role.user_id
                 AND account.is_active = TRUE
                WHERE user_role.user_id = :user_id
                  AND role.role_name = 'System Administrator'
             )"
        );
        $statement->execute(['user_id' => $userId]);
        return $this->databaseBoolean($statement->fetchColumn());
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function databaseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
