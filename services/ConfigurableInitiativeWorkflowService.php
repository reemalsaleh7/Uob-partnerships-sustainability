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
            "SELECT
                request.workflow_template_id IS NOT NULL
                OR EXISTS (
                    SELECT 1
                    FROM workflow_instances instance
                    WHERE instance.entity_type = 'INITIATIVE_REQUEST'
                      AND instance.entity_id = request.request_id
                )
             FROM initiative_requests request
             WHERE request.request_id = :request_id"
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

        $steps = $this->templateSteps(
            (int) $template['workflow_template_id']
        );
        if ($steps === [] || (string) $steps[0]['step_key'] !== 'CREATOR') {
            throw new DomainException(
                'The Initiative Workflow template is invalid.'
            );
        }

        $request = $this->requestSnapshot($requestId);
        $instanceId = $this->ensureInstance(
            $requestId,
            $cycleNumber,
            (int) $template['workflow_template_id'],
            (int) $template['version_number'],
            (int) $request['requester_id'],
            (string) $request['status'],
            $request['submitted_at'] ?? $request['created_at'] ?? null
        );

        if ($this->instanceHasSteps($instanceId)) {
            throw new DomainException(
                'This Initiative approval cycle already has a Workflow snapshot.'
            );
        }

        $skippedCore = $this->coreStagesBeforeRequester(
            $requesterRoleKey
        );
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
                $instanceId,
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

        $this->db->prepare(
            "UPDATE initiative_requests
             SET workflow_template_id = :template_id,
                 workflow_template_version = :template_version,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        )->execute([
            'template_id' => (int) $template['workflow_template_id'],
            'template_version' => (int) $template['version_number'],
            'request_id' => $requestId,
        ]);

        $firstPhase = $this->firstPendingPhase($inserted);
        if ($firstPhase === null) {
            $this->updateRequestApprovedWithoutStages(
                $requestId,
                $instanceId,
                (int) $template['workflow_template_id'],
                (int) $template['version_number']
            );
            return;
        }

        $this->activatePhase($instanceId, $firstPhase);
        $this->updateRequestCurrentPhase(
            $requestId,
            $instanceId,
            $firstPhase,
            (int) $template['workflow_template_id'],
            (int) $template['version_number']
        );
        $this->notifyActivePhase(
            $requestId,
            $instanceId,
            $firstPhase
        );
    }

    public function decisionActorContext(
        int $requestId,
        int $userId
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT
                step.instance_step_id AS request_stage_id,
                assignment.user_id AS assigned_user_id,
                COALESCE(
                    NULLIF(
                        TRIM(
                            CONCAT(
                                principal.first_name,
                                ' ',
                                principal.last_name
                            )
                        ),
                        ''
                    ),
                    principal.email
                ) AS assigned_user_name,
                CASE
                    WHEN assignment.user_id = :direct_user_id
                        THEN FALSE
                    ELSE TRUE
                END AS acting_as_delegate
             FROM initiative_requests request
             JOIN workflow_instances instance
               ON instance.entity_type = 'INITIATIVE_REQUEST'
              AND instance.entity_id = request.request_id
              AND instance.cycle_number = request.revision_cycle
             JOIN workflow_instance_steps step
               ON step.workflow_instance_id =
                  instance.workflow_instance_id
              AND step.phase_order = request.current_phase_order
              AND step.status IN (
                    'IN_PROGRESS',
                    'DISCUSSING_REVISION'
              )
             JOIN LATERAL (
                SELECT step_assignment.user_id
                FROM workflow_step_assignments step_assignment
                WHERE step_assignment.workflow_instance_step_id =
                      step.instance_step_id
                  AND step_assignment.is_active = TRUE
                ORDER BY
                    step_assignment.assigned_at DESC,
                    step_assignment.assignment_id DESC
                LIMIT 1
             ) assignment
               ON TRUE
             JOIN users principal
               ON principal.user_id = assignment.user_id
             WHERE request.request_id = :request_id
               AND request.status IN (
                    'UNDER_REVIEW',
                    'RESUBMITTED',
                    'REVISION_DISCUSSION'
               )
               AND (
                    assignment.user_id = :assigned_user_id
                    OR (
                        step.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions delegate_assignment
                            JOIN users delegate
                              ON delegate.user_id =
                                 delegate_assignment.user_id
                             AND delegate.is_active = TRUE
                            JOIN user_roles user_role
                              ON user_role.user_id =
                                 delegate.user_id
                            JOIN role_permissions role_permission
                              ON role_permission.role_id =
                                 user_role.role_id
                            JOIN permissions permission
                              ON permission.permission_id =
                                 role_permission.permission_id
                             AND permission.permission_code =
                                 step.required_permission_code
                            WHERE delegate_assignment.user_id =
                                  :delegate_user_id
                              AND delegate_assignment.unit_id =
                                  step.assigned_unit_id
                              AND delegate_assignment.is_active = TRUE
                              AND (
                                   delegate_assignment.end_date IS NULL
                                   OR delegate_assignment.end_date >=
                                      CURRENT_DATE
                              )
                        )
                    )
               )
             ORDER BY step.step_order
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

    public function markCurrentStageOpened(
        int $requestId,
        int $userId
    ): void {
        $actor = $this->decisionActorContext($requestId, $userId);
        if ($actor === null) {
            return;
        }

        $statement = $this->db->prepare(
            "UPDATE workflow_instance_steps
             SET opened_at = COALESCE(
                    opened_at,
                    CURRENT_TIMESTAMP
                 )
             WHERE instance_step_id = :stage_id
               AND status IN (
                    'IN_PROGRESS',
                    'DISCUSSING_REVISION'
               )"
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
        if (!in_array(
            $action,
            ['APPROVE', 'REQUEST_REVISION', 'REJECT'],
            true
        )) {
            throw new InvalidArgumentException(
                'The selected Initiative decision is not supported.'
            );
        }

        $comment = $this->nullableText($comment);
        if (
            in_array($action, ['REQUEST_REVISION', 'REJECT'], true)
            && ($comment === null || mb_strlen($comment) < 10)
        ) {
            throw new InvalidArgumentException(
                'A reason of at least 10 characters is required.'
            );
        }

        $this->db->beginTransaction();

        try {
            $context = $this->decisionContextForUpdate(
                $requestId,
                $userId
            );
            if ($context === null) {
                throw new InvalidArgumentException(
                    'This Initiative request is not awaiting your decision.'
                );
            }

            $stageId = (int) $context['request_stage_id'];
            $instanceId = (int) $context['workflow_instance_id'];
            $phaseOrder = (int) $context['phase_order'];
            $principalId = (int) $context['assigned_user_id'];
            $actedOnBehalf = $principalId === $userId
                ? null
                : $principalId;

            if ($action === 'APPROVE') {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'APPROVED',
                    $comment,
                    $actedOnBehalf
                );

                if ($this->phaseHasOpenStages(
                    $instanceId,
                    $phaseOrder
                )) {
                    $this->updateRequestCurrentPhase(
                        $requestId,
                        $instanceId,
                        $phaseOrder,
                        (int) $context['workflow_template_id'],
                        (int) $context['workflow_template_version']
                    );
                    $result = [
                        'request_id' => $requestId,
                        'status' => 'UNDER_REVIEW',
                        'current_phase_order' => $phaseOrder,
                        'current_stage_label' =>
                            'Parallel reviews in progress',
                    ];
                } else {
                    $nextPhase = $this->nextPendingPhase(
                        $instanceId,
                        $phaseOrder
                    );

                    if ($nextPhase === null) {
                        $this->approveRequest(
                            $requestId,
                            $instanceId
                        );
                        $result = [
                            'request_id' => $requestId,
                            'status' => 'APPROVED',
                            'current_phase_order' => null,
                            'current_stage_label' => null,
                        ];
                    } else {
                        $this->activatePhase(
                            $instanceId,
                            $nextPhase
                        );
                        $this->updateRequestCurrentPhase(
                            $requestId,
                            $instanceId,
                            $nextPhase,
                            (int) $context['workflow_template_id'],
                            (int) $context['workflow_template_version']
                        );
                        $this->notifyActivePhase(
                            $requestId,
                            $instanceId,
                            $nextPhase
                        );
                        $result = [
                            'request_id' => $requestId,
                            'status' => 'UNDER_REVIEW',
                            'current_phase_order' => $nextPhase,
                            'current_stage_label' =>
                                $this->phaseLabel(
                                    $instanceId,
                                    $nextPhase
                                ),
                        ];
                    }
                }

                $this->recordEvent(
                    $requestId,
                    $instanceId,
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
                        'acted_on_behalf_of_user_id' =>
                            $actedOnBehalf,
                    ]
                );
            } elseif ($action === 'REQUEST_REVISION') {
                if (!$this->databaseBoolean(
                    $context['allow_revision']
                )) {
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
                $this->returnRequestForRevision(
                    $context,
                    $userId,
                    $comment ?? ''
                );
                $result = [
                    'request_id' => $requestId,
                    'status' => 'REVISION_REQUIRED',
                    'current_phase_order' => $phaseOrder,
                    'current_stage_label' =>
                        (string) $context['stage_label'],
                ];
            } else {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'REJECTED',
                    $comment,
                    $actedOnBehalf
                );
                $this->rejectRequest(
                    $requestId,
                    $instanceId
                );
                $this->recordEvent(
                    $requestId,
                    $instanceId,
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

    public function adminSkip(
        int $requestId,
        int $userId,
        string $reason
    ): array {
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
                    request.current_phase_order,
                    request.workflow_template_id,
                    request.workflow_template_version,
                    instance.workflow_instance_id,
                    step.instance_step_id AS request_stage_id,
                    step.step_label AS stage_label,
                    assignment.user_id AS assigned_user_id
                 FROM initiative_requests request
                 JOIN workflow_instances instance
                   ON instance.entity_type = 'INITIATIVE_REQUEST'
                  AND instance.entity_id = request.request_id
                  AND instance.cycle_number =
                      request.revision_cycle
                 JOIN workflow_instance_steps step
                   ON step.workflow_instance_id =
                      instance.workflow_instance_id
                  AND step.phase_order =
                      request.current_phase_order
                  AND step.status = 'IN_PROGRESS'
                  AND step.is_skippable = TRUE
                 LEFT JOIN LATERAL (
                    SELECT step_assignment.user_id
                    FROM workflow_step_assignments
                         step_assignment
                    WHERE step_assignment.workflow_instance_step_id =
                          step.instance_step_id
                      AND step_assignment.is_active = TRUE
                    ORDER BY
                        step_assignment.assigned_at DESC,
                        step_assignment.assignment_id DESC
                    LIMIT 1
                 ) assignment
                   ON TRUE
                 WHERE request.request_id = :request_id
                 ORDER BY step.step_order
                 LIMIT 1
                 FOR UPDATE OF request, step"
            );
            $statement->execute(['request_id' => $requestId]);
            $context = $statement->fetch();

            if (!$context) {
                throw new InvalidArgumentException(
                    'This Initiative request has no current skippable stage.'
                );
            }

            $stageId = (int) $context['request_stage_id'];
            $instanceId = (int) $context['workflow_instance_id'];
            $phaseOrder = (int) $context['current_phase_order'];

            $this->completeStage(
                $stageId,
                $userId,
                'SKIPPED',
                $reason
            );
            $this->insertSkipRecord(
                $instanceId,
                $stageId,
                $userId,
                $context['assigned_user_id'] === null
                    ? null
                    : (int) $context['assigned_user_id'],
                $reason
            );

            if (!$this->phaseHasOpenStages(
                $instanceId,
                $phaseOrder
            )) {
                $nextPhase = $this->nextPendingPhase(
                    $instanceId,
                    $phaseOrder
                );

                if ($nextPhase === null) {
                    $this->approveRequest(
                        $requestId,
                        $instanceId
                    );
                    $status = 'APPROVED';
                } else {
                    $this->activatePhase(
                        $instanceId,
                        $nextPhase
                    );
                    $this->updateRequestCurrentPhase(
                        $requestId,
                        $instanceId,
                        $nextPhase,
                        (int) $context['workflow_template_id'],
                        (int) $context['workflow_template_version']
                    );
                    $this->notifyActivePhase(
                        $requestId,
                        $instanceId,
                        $nextPhase
                    );
                    $status = 'UNDER_REVIEW';
                }
            } else {
                $this->updateRequestCurrentPhase(
                    $requestId,
                    $instanceId,
                    $phaseOrder,
                    (int) $context['workflow_template_id'],
                    (int) $context['workflow_template_version']
                );
                $nextPhase = $phaseOrder;
                $status = 'UNDER_REVIEW';
            }

            $this->recordEvent(
                $requestId,
                $instanceId,
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
                'skipped_stage_label' =>
                    (string) $context['stage_label'],
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function requestSnapshot(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                requester_id,
                status,
                submitted_at,
                created_at
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new InvalidArgumentException(
                'The Initiative request was not found.'
            );
        }

        return $row;
    }

    private function templateForRequest(
        int $requestId,
        int $cycleNumber
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT workflow_template_id
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        $existingTemplateId = $statement->fetchColumn();

        if (
            $cycleNumber > 0
            && $existingTemplateId !== false
            && $existingTemplateId !== null
        ) {
            $template = $this->db->prepare(
                "SELECT *
                 FROM workflow_templates
                 WHERE workflow_template_id = :template_id
                   AND template_key = 'INITIATIVE_APPROVAL'"
            );
            $template->execute([
                'template_id' => (int) $existingTemplateId,
            ]);
            $row = $template->fetch();
            return $row ?: null;
        }

        $template = $this->db->query(
            "SELECT *
             FROM workflow_templates
             WHERE template_key = 'INITIATIVE_APPROVAL'
               AND process_type = 'INITIATIVE'
               AND is_active = TRUE
             ORDER BY
                version_number DESC,
                workflow_template_id DESC
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
    private function coreStagesBeforeRequester(
        string $roleKey
    ): array {
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

    private function ensureInstance(
        int $requestId,
        int $cycleNumber,
        int $templateId,
        int $templateVersion,
        int $requesterId,
        string $requestStatus,
        mixed $startedAt
    ): int {
        $statement = $this->db->prepare(
            "SELECT workflow_instance_id
             FROM workflow_instances
             WHERE entity_type = 'INITIATIVE_REQUEST'
               AND entity_id = :request_id
               AND cycle_number = :cycle_number
             ORDER BY workflow_instance_id
             LIMIT 1"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
        ]);
        $existing = $statement->fetchColumn();

        if ($existing !== false) {
            $instanceId = (int) $existing;
            if (!$this->instanceHasSteps($instanceId)) {
                $update = $this->db->prepare(
                    "UPDATE workflow_instances
                     SET workflow_template_id = :template_id,
                         template_version_number =
                            :template_version,
                         status = 'IN_PROGRESS',
                         current_step = 1,
                         current_phase_order = NULL,
                         completed_at = NULL,
                         engine_version =
                            'CONFIGURABLE_INITIATIVE_V2'
                     WHERE workflow_instance_id =
                           :instance_id"
                );
                $update->execute([
                    'template_id' => $templateId,
                    'template_version' => $templateVersion,
                    'instance_id' => $instanceId,
                ]);
            }

            return $instanceId;
        }

        $status = match (strtoupper($requestStatus)) {
            'APPROVED', 'CONVERTING', 'CONVERTED' => 'COMPLETED',
            'REJECTED' => 'REJECTED',
            'CANCELLED' => 'CANCELLED',
            default => 'IN_PROGRESS',
        };

        $insert = $this->db->prepare(
            "INSERT INTO workflow_instances (
                workflow_template_id,
                entity_type,
                entity_id,
                current_step,
                status,
                started_by,
                started_at,
                completed_at,
                current_phase_order,
                template_version_number,
                engine_version,
                cycle_number
             ) VALUES (
                :template_id,
                'INITIATIVE_REQUEST',
                :request_id,
                1,
                CAST(:status AS workflow_status),
                :started_by,
                COALESCE(
                    CAST(:started_at AS TIMESTAMP),
                    CURRENT_TIMESTAMP
                ),
                NULL,
                NULL,
                :template_version,
                'CONFIGURABLE_INITIATIVE_V2',
                :cycle_number
             )
             RETURNING workflow_instance_id"
        );
        $insert->execute([
            'template_id' => $templateId,
            'request_id' => $requestId,
            'status' => $status,
            'started_by' => $requesterId,
            'started_at' => $startedAt,
            'template_version' => $templateVersion,
            'cycle_number' => $cycleNumber,
        ]);

        return (int) $insert->fetchColumn();
    }

    private function instanceHasSteps(int $instanceId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM workflow_instance_steps
                WHERE workflow_instance_id = :instance_id
             )"
        );
        $statement->execute(['instance_id' => $instanceId]);

        return $this->databaseBoolean($statement->fetchColumn());
    }

    private function insertStage(
        int $instanceId,
        array $step,
        ?int $unitId,
        ?int $positionId,
        ?int $assigneeId,
        string $status
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_instance_steps (
                workflow_instance_id,
                template_step_id,
                step_order,
                step_key,
                step_label,
                phase_order,
                execution_mode,
                responsibility_type,
                responsibility_scope,
                required_permission_code,
                reminder_after_days,
                allow_revision,
                assigned_unit_id,
                assigned_position_id,
                status,
                started_at,
                completed_at,
                is_optional,
                is_office_delegable,
                is_skippable,
                created_at
             ) VALUES (
                :instance_id,
                :template_step_id,
                :step_order,
                :step_key,
                :step_label,
                :phase_order,
                :execution_mode,
                :responsibility_type,
                :responsibility_scope,
                :required_permission_code,
                :reminder_after_days,
                :allow_revision,
                :assigned_unit_id,
                :assigned_position_id,
                CAST(:status AS workflow_step_status),
                CASE
                    WHEN :status_for_started = 'SKIPPED'
                        THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END,
                CASE
                    WHEN :status_for_completed = 'SKIPPED'
                        THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END,
                :is_optional,
                :is_office_delegable,
                TRUE,
                CURRENT_TIMESTAMP
             )
             RETURNING instance_step_id"
        );

        $officeDelegable =
            (string) $step['responsibility_type'] === 'UNIT'
            || in_array(
                (string) $step['step_key'],
                ['VICE_PRESIDENT', 'PRESIDENT'],
                true
            );

        $statement->execute([
            'instance_id' => $instanceId,
            'template_step_id' => (int) $step['template_step_id'],
            'step_order' => (int) $step['step_order'],
            'step_key' => (string) $step['step_key'],
            'step_label' => (string) $step['step_label'],
            'phase_order' => (int) $step['phase_order'],
            'execution_mode' => (string) $step['execution_mode'],
            'responsibility_type' =>
                (string) $step['responsibility_type'],
            'responsibility_scope' =>
                (string) $step['responsibility_scope'],
            'required_permission_code' =>
                (string) $step['required_permission_code'],
            'reminder_after_days' =>
                (int) $step['reminder_after_days'],
            'allow_revision' =>
                $this->databaseBoolean($step['allow_revision']),
            'assigned_unit_id' => $unitId,
            'assigned_position_id' => $positionId,
            'status' => $status,
            'status_for_started' => $status,
            'status_for_completed' => $status,
            'is_optional' =>
                $this->databaseBoolean($step['is_optional']),
            'is_office_delegable' => $officeDelegable,
        ]);
        $stageId = (int) $statement->fetchColumn();

        if ($assigneeId !== null) {
            $assignment = $this->db->prepare(
                "INSERT INTO workflow_step_assignments (
                    workflow_instance_step_id,
                    user_id,
                    assigned_at,
                    is_active
                 ) VALUES (
                    :stage_id,
                    :user_id,
                    CURRENT_TIMESTAMP,
                    TRUE
                 )"
            );
            $assignment->execute([
                'stage_id' => $stageId,
                'user_id' => $assigneeId,
            ]);
        }

        return $stageId;
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

    private function activatePhase(
        int $instanceId,
        int $phaseOrder
    ): void {
        $statement = $this->db->prepare(
            "UPDATE workflow_instance_steps step
             SET status = 'IN_PROGRESS',
                 started_at = COALESCE(
                    step.started_at,
                    CURRENT_TIMESTAMP
                 ),
                 due_at = CURRENT_TIMESTAMP
                    + make_interval(
                        days => step.reminder_after_days
                      )
             WHERE step.workflow_instance_id = :instance_id
               AND step.phase_order = :phase_order
               AND step.status = 'PENDING'
               AND EXISTS (
                    SELECT 1
                    FROM workflow_step_assignments assignment
                    WHERE assignment.workflow_instance_step_id =
                          step.instance_step_id
                      AND assignment.is_active = TRUE
               )"
        );
        $statement->execute([
            'instance_id' => $instanceId,
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
        int $instanceId,
        int $phaseOrder,
        int $templateId,
        int $templateVersion
    ): void {
        $activeStatement = $this->db->prepare(
            "SELECT
                step.step_order,
                step.assigned_unit_id,
                assignment.user_id AS assigned_user_id
             FROM workflow_instance_steps step
             JOIN LATERAL (
                SELECT step_assignment.user_id
                FROM workflow_step_assignments step_assignment
                WHERE step_assignment.workflow_instance_step_id =
                      step.instance_step_id
                  AND step_assignment.is_active = TRUE
                ORDER BY
                    step_assignment.assigned_at DESC,
                    step_assignment.assignment_id DESC
                LIMIT 1
             ) assignment
               ON TRUE
             WHERE step.workflow_instance_id = :instance_id
               AND step.phase_order = :phase_order
               AND step.status = 'IN_PROGRESS'
             ORDER BY step.step_order
             LIMIT 1"
        );
        $activeStatement->execute([
            'instance_id' => $instanceId,
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
                 workflow_template_version =
                    :template_version,
                 current_phase_order = :phase_order,
                 current_stage_order = :stage_order,
                 current_assignee_id = :assigned_user_id,
                 current_assignee_unit_id =
                    :responsible_unit_id,
                 status = 'UNDER_REVIEW',
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'template_id' => $templateId,
            'template_version' => $templateVersion,
            'phase_order' => $phaseOrder,
            'stage_order' => (int) $active['step_order'],
            'assigned_user_id' =>
                (int) $active['assigned_user_id'],
            'responsible_unit_id' =>
                $active['assigned_unit_id'] === null
                    ? null
                    : (int) $active['assigned_unit_id'],
            'request_id' => $requestId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                'The Initiative request could not move to the selected Workflow phase.'
            );
        }

        $instance = $this->db->prepare(
            "UPDATE workflow_instances
             SET current_phase_order = :phase_order,
                 current_step = :step_order,
                 status = 'IN_PROGRESS',
                 completed_at = NULL,
                 engine_version =
                    'CONFIGURABLE_INITIATIVE_V2'
             WHERE workflow_instance_id = :instance_id"
        );
        $instance->execute([
            'phase_order' => $phaseOrder,
            'step_order' => (int) $active['step_order'],
            'instance_id' => $instanceId,
        ]);
    }

    private function updateRequestApprovedWithoutStages(
        int $requestId,
        int $instanceId,
        int $templateId,
        int $templateVersion
    ): void {
        $statement = $this->db->prepare(
            "UPDATE initiative_requests
             SET workflow_template_id = :template_id,
                 workflow_template_version =
                    :template_version,
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

        $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'COMPLETED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        )->execute(['instance_id' => $instanceId]);
    }

    private function decisionContextForUpdate(
        int $requestId,
        int $userId
    ): ?array {
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
                instance.workflow_instance_id,
                step.instance_step_id AS request_stage_id,
                step.step_order AS stage_order,
                step.phase_order,
                step.step_key AS stage_key,
                step.step_label AS stage_label,
                assignment.user_id AS assigned_user_id,
                step.assigned_unit_id AS responsible_unit_id,
                step.allow_revision,
                CASE
                    WHEN assignment.user_id = :direct_user_id
                        THEN FALSE
                    ELSE TRUE
                END AS acting_as_delegate
             FROM initiative_requests request
             JOIN workflow_instances instance
               ON instance.entity_type = 'INITIATIVE_REQUEST'
              AND instance.entity_id = request.request_id
              AND instance.cycle_number =
                  request.revision_cycle
             JOIN workflow_instance_steps step
               ON step.workflow_instance_id =
                  instance.workflow_instance_id
              AND step.phase_order =
                  request.current_phase_order
              AND step.status = 'IN_PROGRESS'
             JOIN LATERAL (
                SELECT step_assignment.user_id
                FROM workflow_step_assignments step_assignment
                WHERE step_assignment.workflow_instance_step_id =
                      step.instance_step_id
                  AND step_assignment.is_active = TRUE
                ORDER BY
                    step_assignment.assigned_at DESC,
                    step_assignment.assignment_id DESC
                LIMIT 1
             ) assignment
               ON TRUE
             WHERE request.request_id = :request_id
               AND request.status IN (
                    'UNDER_REVIEW',
                    'RESUBMITTED'
               )
               AND (
                    assignment.user_id = :assigned_user_id
                    OR (
                        step.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions delegate_assignment
                            JOIN users delegate
                              ON delegate.user_id =
                                 delegate_assignment.user_id
                             AND delegate.is_active = TRUE
                            JOIN user_roles user_role
                              ON user_role.user_id =
                                 delegate.user_id
                            JOIN role_permissions role_permission
                              ON role_permission.role_id =
                                 user_role.role_id
                            JOIN permissions permission
                              ON permission.permission_id =
                                 role_permission.permission_id
                             AND permission.permission_code =
                                 step.required_permission_code
                            WHERE delegate_assignment.user_id =
                                  :delegate_user_id
                              AND delegate_assignment.unit_id =
                                  step.assigned_unit_id
                              AND delegate_assignment.is_active = TRUE
                              AND (
                                   delegate_assignment.end_date IS NULL
                                   OR delegate_assignment.end_date >=
                                      CURRENT_DATE
                              )
                        )
                    )
               )
             ORDER BY step.step_order
             LIMIT 1
             FOR UPDATE OF request, step"
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
            "UPDATE workflow_instance_steps
             SET status = CAST(:status AS workflow_step_status),
                 approved_by = :user_id,
                 approved_at = CASE
                    WHEN :status_for_approval = 'APPROVED'
                        THEN CURRENT_TIMESTAMP
                    ELSE NULL
                 END,
                 acted_on_behalf_of_user_id =
                    :acted_on_behalf,
                 completed_at = CURRENT_TIMESTAMP,
                 opened_at = COALESCE(
                    opened_at,
                    CURRENT_TIMESTAMP
                 ),
                 comments = :comment
             WHERE instance_step_id = :stage_id
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'status_for_approval' => $status,
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
        int $instanceId,
        int $phaseOrder
    ): bool {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM workflow_instance_steps
                WHERE workflow_instance_id = :instance_id
                  AND phase_order = :phase_order
                  AND status = 'IN_PROGRESS'
             )"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);

        return $this->databaseBoolean($statement->fetchColumn());
    }

    private function nextPendingPhase(
        int $instanceId,
        int $phaseOrder
    ): ?int {
        $statement = $this->db->prepare(
            "SELECT MIN(phase_order)
             FROM workflow_instance_steps
             WHERE workflow_instance_id = :instance_id
               AND phase_order > :phase_order
               AND status = 'PENDING'"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null
            ? null
            : (int) $value;
    }

    private function approveRequest(
        int $requestId,
        int $instanceId
    ): void {
        $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'APPROVED',
                 approved_at = CURRENT_TIMESTAMP,
                 current_phase_order = NULL,
                 current_stage_order = NULL,
                 current_assignee_id = NULL,
                 current_assignee_unit_id = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        )->execute(['request_id' => $requestId]);

        $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'COMPLETED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        )->execute(['instance_id' => $instanceId]);
    }

    private function rejectRequest(
        int $requestId,
        int $instanceId
    ): void {
        $this->db->prepare(
            "UPDATE workflow_instance_steps
             SET status = 'CANCELLED',
                 completed_at = COALESCE(
                    completed_at,
                    CURRENT_TIMESTAMP
                 )
             WHERE workflow_instance_id = :instance_id
               AND status IN ('PENDING', 'IN_PROGRESS')"
        )->execute(['instance_id' => $instanceId]);

        $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'REJECTED',
                 rejected_at = CURRENT_TIMESTAMP,
                 current_phase_order = NULL,
                 current_stage_order = NULL,
                 current_assignee_id = NULL,
                 current_assignee_unit_id = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        )->execute(['request_id' => $requestId]);

        $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'REJECTED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        )->execute(['instance_id' => $instanceId]);
    }

    private function returnRequestForRevision(
        array $context,
        int $userId,
        string $comment
    ): void {
        $requestId = (int) $context['request_id'];
        $instanceId = (int) $context['workflow_instance_id'];

        $this->db->prepare(
            "UPDATE workflow_instance_steps
             SET status = 'CANCELLED',
                 completed_at = COALESCE(
                    completed_at,
                    CURRENT_TIMESTAMP
                 )
             WHERE workflow_instance_id = :instance_id
               AND status IN ('PENDING', 'IN_PROGRESS')"
        )->execute(['instance_id' => $instanceId]);

        $this->db->prepare(
            "UPDATE initiative_requests
             SET status = 'REVISION_REQUIRED',
                 current_assignee_id = requester_id,
                 current_assignee_unit_id =
                    requester_unit_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id"
        )->execute(['request_id' => $requestId]);

        $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'CANCELLED',
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        )->execute(['instance_id' => $instanceId]);

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
            'request_id' => $requestId,
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
            $requestId,
            $instanceId,
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

    private function notifyActivePhase(
        int $requestId,
        int $instanceId,
        int $phaseOrder
    ): void {
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
                :request_id,
                assignment.user_id,
                'APPROVAL_ASSIGNED',
                'Initiative request awaiting your decision',
                step.step_label ||
                    ' is now awaiting your review.',
                jsonb_build_object(
                    'request_stage_id',
                    step.instance_step_id,
                    'phase_order',
                    step.phase_order
                )
             FROM workflow_instance_steps step
             JOIN workflow_step_assignments assignment
               ON assignment.workflow_instance_step_id =
                  step.instance_step_id
              AND assignment.is_active = TRUE
             WHERE step.workflow_instance_id = :instance_id
               AND step.phase_order = :phase_order
               AND step.status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'request_id' => $requestId,
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);
    }

    private function phaseLabel(
        int $instanceId,
        int $phaseOrder
    ): string {
        $statement = $this->db->prepare(
            "SELECT STRING_AGG(
                step_label,
                ' + '
                ORDER BY step_order
             )
             FROM workflow_instance_steps
             WHERE workflow_instance_id = :instance_id
               AND phase_order = :phase_order
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);

        return (string) (
            $statement->fetchColumn()
            ?: 'Workflow review'
        );
    }

    private function insertSkipRecord(
        int $instanceId,
        int $stageId,
        int $skippedBy,
        ?int $skippedUserId,
        string $reason
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_history (
                workflow_instance_id,
                workflow_step_id,
                action,
                performed_by,
                comments,
                event_type,
                target_user_id,
                to_status,
                event_data
             ) VALUES (
                :instance_id,
                :stage_id,
                NULL,
                :skipped_by,
                :reason,
                'ADMIN_SKIP_DETAIL',
                :skipped_user_id,
                'SKIPPED',
                '{}'::JSONB
             )"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'stage_id' => $stageId,
            'skipped_by' => $skippedBy,
            'reason' => $reason,
            'skipped_user_id' => $skippedUserId,
        ]);
    }

    private function recordEvent(
        int $requestId,
        int $instanceId,
        ?int $actorUserId,
        string $eventType,
        string $note,
        ?int $stageId,
        ?int $targetUserId,
        ?string $fromStatus,
        ?string $toStatus,
        array $eventData
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_history (
                workflow_instance_id,
                workflow_step_id,
                action,
                performed_by,
                comments,
                event_type,
                target_user_id,
                from_status,
                to_status,
                event_data
             ) VALUES (
                :instance_id,
                :stage_id,
                NULL,
                :actor_user_id,
                :event_note,
                :event_type,
                :target_user_id,
                :from_status,
                :to_status,
                CAST(:event_data AS JSONB)
             )"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'stage_id' => $stageId,
            'actor_user_id' => $actorUserId,
            'event_note' => $note,
            'event_type' => $eventType,
            'target_user_id' => $targetUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_data' => json_encode(
                $eventData,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
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
                  AND role.role_name =
                      'System Administrator'
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

        return in_array(
            strtolower((string) $value),
            ['1', 't', 'true', 'yes', 'on'],
            true
        );
    }
}
