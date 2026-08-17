<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/WorkflowActorResolver.php';

final class ConfigurableAgreementWorkflowService
{
    private PDO $db;
    private WorkflowActorResolver $resolver;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->resolver = new WorkflowActorResolver($this->db);
    }

    public function activeTemplate(): ?array
    {
        $statement = $this->db->prepare(
            "SELECT *
             FROM workflow_templates
             WHERE template_key = 'AGREEMENT_APPROVAL'
               AND process_type = 'AGREEMENT'
               AND is_active = TRUE
             ORDER BY version_number DESC, workflow_template_id DESC
             LIMIT 1"
        );
        $statement->execute();
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function shouldStartConfigurable(): bool
    {
        $template = $this->activeTemplate();
        return $template !== null
            && isset($template['version_number'])
            && (int) $template['version_number'] >= 1;
    }

    public function start(int $agreementId, int $startedBy): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $agreement = $this->agreementForUpdate($agreementId);
            if ($agreement === null) {
                throw new DomainException('Agreement not found');
            }
            if ((string) $agreement['status'] !== 'DRAFT') {
                throw new DomainException(
                    'Only a DRAFT Agreement may be submitted.'
                );
            }
            if ((int) $agreement['created_by'] !== $startedBy) {
                $this->assertCanSubmitAgreement($startedBy);
            }
            if ($this->activeInstanceExists($agreementId)) {
                throw new DomainException(
                    'Agreement already has an active Workflow.'
                );
            }

            $template = $this->activeTemplate();
            if ($template === null) {
                throw new DomainException(
                    'The active Agreement Workflow template was not found.'
                );
            }
            $steps = $this->templateSteps(
                (int) $template['workflow_template_id']
            );
            if ($steps === [] || (string) $steps[0]['step_key'] !== 'CREATOR') {
                throw new DomainException(
                    'The Agreement Workflow template is invalid.'
                );
            }

            $creatorAssignment = $this->resolver->activeAssignmentForUser(
                (int) $agreement['created_by']
            );
            $instanceId = $this->insertInstance(
                $agreementId,
                (int) $template['workflow_template_id'],
                (int) $template['version_number'],
                $startedBy
            );

            $created = [];
            foreach ($steps as $step) {
                $isCreator = (string) $step['step_key'] === 'CREATOR';
                $unitId = $isCreator
                    ? $creatorAssignment['unit_id']
                    : $this->resolver->resolveUnitId(
                        (string) $step['responsibility_scope'],
                        $step['required_unit_id'] === null
                            ? null
                            : (int) $step['required_unit_id'],
                        $creatorAssignment['unit_id']
                    );
                $positionId = $isCreator
                    ? $creatorAssignment['position_id']
                    : ($step['required_position_id'] === null
                        ? null
                        : (int) $step['required_position_id']);

                if (!$isCreator && $unitId === null) {
                    throw new DomainException(
                        sprintf(
                            'The organizational unit for Workflow stage %s could not be resolved.',
                            (string) $step['step_label']
                        )
                    );
                }

                $instanceStepId = $this->insertInstanceStep(
                    $instanceId,
                    $step,
                    $unitId,
                    $positionId,
                    $isCreator ? 'APPROVED' : 'PENDING',
                    $isCreator ? $startedBy : null
                );

                $eligible = [];
                if (!$isCreator) {
                    $eligible = $this->resolver->eligibleUserIds(
                        (string) $step['required_permission_code'],
                        $unitId,
                        (string) $step['responsibility_type'] === 'POSITION'
                            ? $positionId
                            : null
                    );
                    if ($eligible === [] && !$this->databaseBoolean($step['is_optional'])) {
                        throw new DomainException(
                            sprintf(
                                'No active reviewer is available for Workflow stage: %s.',
                                (string) $step['step_label']
                            )
                        );
                    }
                    foreach ($eligible as $eligibleUserId) {
                        $this->assignUser($instanceStepId, $eligibleUserId);
                    }
                }

                $created[] = [
                    'instance_step_id' => $instanceStepId,
                    'step_order' => (int) $step['step_order'],
                    'phase_order' => (int) $step['phase_order'],
                    'step_key' => (string) $step['step_key'],
                    'step_label' => (string) $step['step_label'],
                    'is_optional' => $this->databaseBoolean($step['is_optional']),
                    'eligible_user_count' => count($eligible),
                ];
            }

            $firstPhase = $this->firstApprovalPhase($created);
            if ($firstPhase === null) {
                throw new DomainException(
                    'The Workflow template contains no approval stages.'
                );
            }
            $this->activatePhase($instanceId, $firstPhase, null, true);
            $this->setInstancePhase($instanceId, $firstPhase);
            $this->changeAgreementStatus($agreementId, 'UNDER_REVIEW');
            $this->insertAudit(
                $startedBy,
                $agreementId,
                'Agreement Workflow started',
                [
                    'workflow_instance_id' => $instanceId,
                    'template_id' => (int) $template['workflow_template_id'],
                    'template_version' => (int) $template['version_number'],
                ]
            );

            if ($ownsTransaction) {
                $this->db->commit();
            }

            return [
                'workflow_instance_id' => $instanceId,
                'current_phase_order' => $firstPhase,
                'template_version' => (int) $template['version_number'],
                'engine_version' => 'CONFIGURABLE',
                'steps' => $created,
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function configurableInbox(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                instance.workflow_instance_id,
                instance.entity_type,
                instance.entity_id,
                instance.entity_id AS subject_agreement_id,
                instance.template_version_number,
                instance.engine_version,
                assignment.read_at,
                (assignment.read_at IS NOT NULL) AS is_read,
                step.instance_step_id,
                step.step_order,
                step.phase_order,
                step.step_key,
                step.step_label,
                step.execution_mode,
                step.is_optional,
                step.allow_revision,
                step.status,
                step.started_at,
                unit.code AS assigned_unit_code,
                unit.name AS assigned_unit_name,
                'REVIEW' AS task_mode
             FROM workflow_step_assignments assignment
             JOIN workflow_instance_steps step
               ON step.instance_step_id = assignment.workflow_instance_step_id
             JOIN workflow_instances instance
               ON instance.workflow_instance_id = step.workflow_instance_id
             LEFT JOIN organizational_units unit
               ON unit.unit_id = step.assigned_unit_id
             WHERE assignment.user_id = :user_id
               AND assignment.is_active = TRUE
               AND instance.engine_version = 'CONFIGURABLE'
               AND instance.entity_type = 'AGREEMENT'
               AND instance.status = 'IN_PROGRESS'
               AND step.status = 'IN_PROGRESS'
             ORDER BY step.started_at, instance.workflow_instance_id"
        );
        $statement->execute(['user_id' => $userId]);
        return array_map(function (array $row): array {
            foreach ([
                'workflow_instance_id',
                'entity_id',
                'subject_agreement_id',
                'template_version_number',
                'instance_step_id',
                'step_order',
                'phase_order',
            ] as $field) {
                $row[$field] = (int) $row[$field];
            }
            $row['is_optional'] = $this->databaseBoolean($row['is_optional']);
            $row['allow_revision'] = $this->databaseBoolean($row['allow_revision']);
            $row['is_read'] = $this->databaseBoolean($row['is_read']);
            return $row;
        }, $statement->fetchAll());
    }

    public function enrichInbox(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $instanceIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['workflow_instance_id'],
            $rows
        )));
        $placeholders = implode(',', array_fill(0, count($instanceIds), '?'));
        $statement = $this->db->prepare(
            "SELECT
                instance.workflow_instance_id,
                instance.engine_version,
                instance.template_version_number,
                step.instance_step_id,
                step.step_label,
                step.phase_order,
                step.execution_mode,
                step.is_optional,
                step.allow_revision
             FROM workflow_instances instance
             JOIN workflow_instance_steps step
               ON step.workflow_instance_id = instance.workflow_instance_id
             WHERE instance.workflow_instance_id IN ({$placeholders})"
        );
        foreach ($instanceIds as $offset => $instanceId) {
            $statement->bindValue($offset + 1, $instanceId, PDO::PARAM_INT);
        }
        $statement->execute();
        $metadata = [];
        foreach ($statement->fetchAll() as $row) {
            $metadata[(int) $row['instance_step_id']] = $row;
        }

        foreach ($rows as &$row) {
            $step = $metadata[(int) $row['instance_step_id']] ?? null;
            $row['engine_version'] = $step['engine_version'] ?? 'LEGACY';
            $row['template_version_number'] = isset($step['template_version_number'])
                ? (int) $step['template_version_number']
                : null;
            $row['step_label'] = $step['step_label']
                ?? $this->fallbackStepLabel((string) ($row['step_key'] ?? ''));
            $row['phase_order'] = isset($step['phase_order'])
                ? (int) $step['phase_order']
                : (int) ($row['step_order'] ?? 0);
            $row['execution_mode'] = $step['execution_mode'] ?? 'SEQUENTIAL';
            $row['is_optional'] = $this->databaseBoolean(
                $step['is_optional'] ?? false
            );
            $row['allow_revision'] = $this->databaseBoolean(
                $step['allow_revision'] ?? true
            );
        }
        unset($row);
        return $rows;
    }

    public function reviewDetail(int $instanceId, int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                instance.workflow_instance_id,
                instance.entity_id AS agreement_id,
                instance.current_phase_order,
                instance.template_version_number,
                instance.engine_version,
                agreement.title AS agreement_title,
                agreement.description AS agreement_description,
                step.instance_step_id,
                step.step_order,
                step.phase_order,
                step.step_key,
                step.step_label,
                step.execution_mode,
                step.is_optional,
                step.allow_revision,
                step.status,
                step.comments,
                unit.name AS assigned_unit_name,
                position.name AS assigned_position_name
             FROM workflow_instances instance
             JOIN agreements agreement
               ON agreement.agreement_id = instance.entity_id
             JOIN workflow_instance_steps step
               ON step.workflow_instance_id = instance.workflow_instance_id
              AND step.phase_order = instance.current_phase_order
              AND step.status = 'IN_PROGRESS'
             JOIN workflow_step_assignments assignment
               ON assignment.workflow_instance_step_id = step.instance_step_id
              AND assignment.user_id = :user_id
              AND assignment.is_active = TRUE
             LEFT JOIN organizational_units unit
               ON unit.unit_id = step.assigned_unit_id
             LEFT JOIN positions position
               ON position.position_id = step.assigned_position_id
             WHERE instance.workflow_instance_id = :instance_id
               AND instance.entity_type = 'AGREEMENT'
               AND instance.engine_version = 'CONFIGURABLE'
               AND instance.status = 'IN_PROGRESS'
             ORDER BY step.step_order
             LIMIT 1"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'user_id' => $userId,
        ]);
        $detail = $statement->fetch();
        if (!$detail) {
            throw new DomainException(
                'This configurable Workflow stage is not assigned to you.'
            );
        }

        foreach (['workflow_instance_id', 'agreement_id', 'current_phase_order', 'template_version_number', 'instance_step_id', 'step_order', 'phase_order'] as $field) {
            $detail[$field] = (int) $detail[$field];
        }
        $detail['is_optional'] = $this->databaseBoolean($detail['is_optional']);
        $detail['allow_revision'] = $this->databaseBoolean($detail['allow_revision']);
        $detail['phase_steps'] = $this->phaseSteps($instanceId, (int) $detail['phase_order']);
        $detail['next_optional_steps'] = $this->canCompletePhase(
            $instanceId,
            (int) $detail['phase_order'],
            (int) $detail['instance_step_id']
        ) ? $this->nextOptionalSteps($instanceId, (int) $detail['phase_order']) : [];
        return $detail;
    }

    public function decide(
        int $instanceId,
        int $userId,
        string $action,
        ?string $comment,
        array $includeOptionalStepKeys = []
    ): array {
        $action = strtoupper(trim($action));
        if (!in_array($action, ['APPROVE', 'REQUEST_CHANGES', 'REJECT'], true)) {
            throw new InvalidArgumentException('The selected decision is invalid.');
        }
        $comment = $this->nullableText($comment);
        if (in_array($action, ['REQUEST_CHANGES', 'REJECT'], true)
            && ($comment === null || mb_strlen($comment) < 10)) {
            throw new InvalidArgumentException(
                'A reason of at least 10 characters is required.'
            );
        }
        $selectedOptional = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtoupper(trim((string) $value)),
            $includeOptionalStepKeys
        ))));

        $this->db->beginTransaction();
        try {
            $context = $this->decisionContextForUpdate($instanceId, $userId);
            if ($context === null) {
                throw new DomainException(
                    'This configurable Workflow stage is no longer awaiting your decision.'
                );
            }
            $stepId = (int) $context['instance_step_id'];
            $agreementId = (int) $context['entity_id'];
            $phaseOrder = (int) $context['phase_order'];

            if ($action === 'REQUEST_CHANGES'
                && !$this->databaseBoolean($context['allow_revision'])) {
                throw new DomainException(
                    'This Workflow stage does not allow returning the Agreement for changes.'
                );
            }

            if ($action === 'APPROVE') {
                $this->finishStep($stepId, 'APPROVED', $userId, $comment);
                $this->deactivateStepAssignments($stepId);

                if ($this->phaseHasOpenSteps($instanceId, $phaseOrder)) {
                    $result = [
                        'workflow_instance_id' => $instanceId,
                        'agreement_id' => $agreementId,
                        'status' => 'UNDER_REVIEW',
                        'current_phase_order' => $phaseOrder,
                        'waiting_for_parallel_reviews' => true,
                    ];
                } else {
                    $nextPhase = $this->nextPhase($instanceId, $phaseOrder);
                    while ($nextPhase !== null) {
                        $activated = $this->activatePhase(
                            $instanceId,
                            $nextPhase,
                            $selectedOptional,
                            false
                        );
                        if ($activated > 0) {
                            break;
                        }
                        $nextPhase = $this->nextPhase($instanceId, $nextPhase);
                    }

                    if ($nextPhase === null) {
                        $this->completeInstance($instanceId);
                        $this->changeAgreementStatus($agreementId, 'APPROVED');
                        $result = [
                            'workflow_instance_id' => $instanceId,
                            'agreement_id' => $agreementId,
                            'status' => 'APPROVED',
                            'current_phase_order' => null,
                            'waiting_for_parallel_reviews' => false,
                        ];
                    } else {
                        $this->setInstancePhase($instanceId, $nextPhase);
                        $result = [
                            'workflow_instance_id' => $instanceId,
                            'agreement_id' => $agreementId,
                            'status' => 'UNDER_REVIEW',
                            'current_phase_order' => $nextPhase,
                            'waiting_for_parallel_reviews' => false,
                        ];
                    }
                }
            } elseif ($action === 'REQUEST_CHANGES') {
                $this->finishStep($stepId, 'CHANGES_REQUESTED', $userId, $comment);
                $this->deactivateInstanceAssignments($instanceId);
                $this->cancelInstance($instanceId);
                $this->changeAgreementStatus($agreementId, 'DRAFT');
                $result = [
                    'workflow_instance_id' => $instanceId,
                    'agreement_id' => $agreementId,
                    'status' => 'DRAFT',
                    'returned_for_changes' => true,
                ];
            } else {
                $this->finishStep($stepId, 'REJECTED', $userId, $comment);
                $this->deactivateInstanceAssignments($instanceId);
                $this->rejectInstance($instanceId);
                $this->changeAgreementStatus($agreementId, 'REJECTED');
                $result = [
                    'workflow_instance_id' => $instanceId,
                    'agreement_id' => $agreementId,
                    'status' => 'REJECTED',
                    'returned_for_changes' => false,
                ];
            }

            $this->insertAudit(
                $userId,
                $agreementId,
                'Configurable Workflow decision: ' . $action,
                [
                    'workflow_instance_id' => $instanceId,
                    'instance_step_id' => $stepId,
                    'step_key' => (string) $context['step_key'],
                    'phase_order' => $phaseOrder,
                    'comment' => $comment,
                    'selected_optional_steps' => $selectedOptional,
                    'result' => $result,
                ]
            );

            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function agreementForUpdate(int $agreementId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT agreement_id, title, status::TEXT AS status, created_by
             FROM agreements
             WHERE agreement_id = :agreement_id
             FOR UPDATE'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function assertCanSubmitAgreement(int $userId): void
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN role_permissions role_permission
                  ON role_permission.role_id = user_role.role_id
                JOIN permissions permission
                  ON permission.permission_id = role_permission.permission_id
                JOIN users account
                  ON account.user_id = user_role.user_id
                 AND account.is_active = TRUE
                WHERE user_role.user_id = :user_id
                  AND permission.permission_code = 'CREATE_AGREEMENT'
             )"
        );
        $statement->execute(['user_id' => $userId]);
        if (!$this->databaseBoolean($statement->fetchColumn())) {
            throw new DomainException(
                'You are not allowed to submit this Agreement.'
            );
        }
    }

    private function activeInstanceExists(int $agreementId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM workflow_instances
                WHERE entity_type = 'AGREEMENT'
                  AND entity_id = :agreement_id
                  AND status = 'IN_PROGRESS'
             )"
        );
        $statement->execute(['agreement_id' => $agreementId]);
        return $this->databaseBoolean($statement->fetchColumn());
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

    private function insertInstance(
        int $agreementId,
        int $templateId,
        int $version,
        int $startedBy
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_instances (
                workflow_template_id,
                entity_type,
                entity_id,
                current_step,
                current_phase_order,
                template_version_number,
                engine_version,
                finance_review_required,
                status,
                started_by,
                started_at
             ) VALUES (
                :template_id,
                'AGREEMENT',
                :agreement_id,
                1,
                NULL,
                :version_number,
                'CONFIGURABLE',
                NULL,
                'IN_PROGRESS',
                :started_by,
                CURRENT_TIMESTAMP
             )
             RETURNING workflow_instance_id"
        );
        $statement->execute([
            'template_id' => $templateId,
            'agreement_id' => $agreementId,
            'version_number' => $version,
            'started_by' => $startedBy,
        ]);
        return (int) $statement->fetchColumn();
    }

    private function insertInstanceStep(
        int $instanceId,
        array $step,
        ?int $unitId,
        ?int $positionId,
        string $status,
        ?int $actedBy
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
                assigned_unit_id,
                assigned_position_id,
                is_optional,
                responsibility_type,
                responsibility_scope,
                required_permission_code,
                reminder_after_days,
                allow_revision,
                status,
                approved_by,
                approved_at,
                started_at,
                completed_at
             ) VALUES (
                :instance_id,
                :template_step_id,
                :step_order,
                :step_key,
                :step_label,
                :phase_order,
                :execution_mode,
                :assigned_unit_id,
                :assigned_position_id,
                :is_optional,
                :responsibility_type,
                :responsibility_scope,
                :required_permission_code,
                :reminder_after_days,
                :allow_revision,
                CAST(:status AS workflow_step_status),
                :approved_by,
                :approved_at,
                :started_at,
                :completed_at
             )
             RETURNING instance_step_id"
        );
        $isCompleted = in_array($status, ['APPROVED', 'REJECTED', 'SKIPPED'], true);
        $values = [
            ':instance_id' => [$instanceId, PDO::PARAM_INT],
            ':template_step_id' => [(int) $step['template_step_id'], PDO::PARAM_INT],
            ':step_order' => [(int) $step['step_order'], PDO::PARAM_INT],
            ':step_key' => [(string) $step['step_key'], PDO::PARAM_STR],
            ':step_label' => [(string) $step['step_label'], PDO::PARAM_STR],
            ':phase_order' => [(int) $step['phase_order'], PDO::PARAM_INT],
            ':execution_mode' => [(string) $step['execution_mode'], PDO::PARAM_STR],
            ':assigned_unit_id' => [$unitId, $unitId === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':assigned_position_id' => [$positionId, $positionId === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':is_optional' => [$this->databaseBoolean($step['is_optional']), PDO::PARAM_BOOL],
            ':responsibility_type' => [(string) $step['responsibility_type'], PDO::PARAM_STR],
            ':responsibility_scope' => [(string) $step['responsibility_scope'], PDO::PARAM_STR],
            ':required_permission_code' => [(string) $step['required_permission_code'], PDO::PARAM_STR],
            ':reminder_after_days' => [(int) $step['reminder_after_days'], PDO::PARAM_INT],
            ':allow_revision' => [$this->databaseBoolean($step['allow_revision']), PDO::PARAM_BOOL],
            ':status' => [$status, PDO::PARAM_STR],
            ':approved_by' => [$actedBy, $actedBy === null ? PDO::PARAM_NULL : PDO::PARAM_INT],
            ':approved_at' => [$actedBy === null ? null : date('Y-m-d H:i:s'), $actedBy === null ? PDO::PARAM_NULL : PDO::PARAM_STR],
            ':started_at' => [$status === 'APPROVED' ? date('Y-m-d H:i:s') : null, $status === 'APPROVED' ? PDO::PARAM_STR : PDO::PARAM_NULL],
            ':completed_at' => [$isCompleted ? date('Y-m-d H:i:s') : null, $isCompleted ? PDO::PARAM_STR : PDO::PARAM_NULL],
        ];
        foreach ($values as $name => [$value, $type]) {
            $statement->bindValue($name, $value, $type);
        }
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    private function assignUser(int $stepId, int $userId): void
    {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_step_assignments (
                workflow_instance_step_id,
                user_id,
                assigned_at,
                is_active
             ) VALUES (
                :step_id,
                :user_id,
                CURRENT_TIMESTAMP,
                TRUE
             )
             ON CONFLICT DO NOTHING"
        );
        $statement->execute(['step_id' => $stepId, 'user_id' => $userId]);
    }

    private function firstApprovalPhase(array $steps): ?int
    {
        foreach ($steps as $step) {
            if ((string) $step['step_key'] !== 'CREATOR') {
                return (int) $step['phase_order'];
            }
        }
        return null;
    }

    private function activatePhase(
        int $instanceId,
        int $phaseOrder,
        ?array $selectedOptionalKeys,
        bool $includeOptionalByDefault
    ): int {
        $statement = $this->db->prepare(
            "SELECT
                step.instance_step_id,
                step.step_key,
                step.step_label,
                step.is_optional,
                COUNT(assignment.assignment_id) FILTER (
                    WHERE assignment.is_active = TRUE
                ) AS assignment_count
             FROM workflow_instance_steps step
             LEFT JOIN workflow_step_assignments assignment
               ON assignment.workflow_instance_step_id = step.instance_step_id
             WHERE step.workflow_instance_id = :instance_id
               AND step.phase_order = :phase_order
               AND step.status = 'PENDING'
             GROUP BY step.instance_step_id
             ORDER BY step.step_order"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);
        $activated = 0;
        foreach ($statement->fetchAll() as $step) {
            $optional = $this->databaseBoolean($step['is_optional']);
            $include = !$optional
                || $includeOptionalByDefault
                || ($selectedOptionalKeys !== null
                    && in_array((string) $step['step_key'], $selectedOptionalKeys, true));
            $assignmentCount = (int) $step['assignment_count'];
            if (!$include || ($optional && $assignmentCount < 1)) {
                $this->setStepSkipped((int) $step['instance_step_id']);
                continue;
            }
            if ($assignmentCount < 1) {
                throw new DomainException(
                    'No reviewer is assigned to required stage: '
                    . (string) $step['step_label'] . '.'
                );
            }
            $activate = $this->db->prepare(
                "UPDATE workflow_instance_steps
                 SET status = 'IN_PROGRESS',
                     started_at = COALESCE(started_at, CURRENT_TIMESTAMP)
                 WHERE instance_step_id = :step_id
                   AND status = 'PENDING'"
            );
            $activate->execute(['step_id' => (int) $step['instance_step_id']]);
            $activated += $activate->rowCount();
        }
        return $activated;
    }

    private function setStepSkipped(int $stepId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_instance_steps
             SET status = 'SKIPPED',
                 comments = COALESCE(comments, 'Optional stage was not selected.'),
                 completed_at = CURRENT_TIMESTAMP
             WHERE instance_step_id = :step_id
               AND status = 'PENDING'"
        );
        $statement->execute(['step_id' => $stepId]);
        $this->deactivateStepAssignments($stepId);
    }

    private function setInstancePhase(int $instanceId, int $phaseOrder): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_instances
             SET current_phase_order = :phase_order,
                 current_step = (
                    SELECT MIN(step_order)
                    FROM workflow_instance_steps
                    WHERE workflow_instance_id = :instance_id_lookup
                      AND phase_order = :phase_order_lookup
                      AND status = 'IN_PROGRESS'
                 )
             WHERE workflow_instance_id = :instance_id"
        );
        $statement->execute([
            'phase_order' => $phaseOrder,
            'instance_id_lookup' => $instanceId,
            'phase_order_lookup' => $phaseOrder,
            'instance_id' => $instanceId,
        ]);
    }

    private function phaseSteps(int $instanceId, int $phaseOrder): array
    {
        $statement = $this->db->prepare(
            "SELECT
                step.instance_step_id,
                step.step_key,
                step.step_label,
                step.status::TEXT AS status,
                step.is_optional,
                unit.name AS assigned_unit_name,
                position.name AS assigned_position_name,
                STRING_AGG(
                    DISTINCT COALESCE(
                        NULLIF(TRIM(CONCAT(account.first_name, ' ', account.last_name)), ''),
                        account.email
                    ),
                    ', '
                ) FILTER (WHERE account.user_id IS NOT NULL) AS reviewer_names
             FROM workflow_instance_steps step
             LEFT JOIN workflow_step_assignments assignment
               ON assignment.workflow_instance_step_id = step.instance_step_id
              AND assignment.is_active = TRUE
             LEFT JOIN users account
               ON account.user_id = assignment.user_id
             LEFT JOIN organizational_units unit
               ON unit.unit_id = step.assigned_unit_id
             LEFT JOIN positions position
               ON position.position_id = step.assigned_position_id
             WHERE step.workflow_instance_id = :instance_id
               AND step.phase_order = :phase_order
             GROUP BY step.instance_step_id, unit.name, position.name
             ORDER BY step.step_order"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
        ]);
        return array_map(function (array $row): array {
            $row['instance_step_id'] = (int) $row['instance_step_id'];
            $row['is_optional'] = $this->databaseBoolean($row['is_optional']);
            return $row;
        }, $statement->fetchAll());
    }

    private function canCompletePhase(int $instanceId, int $phaseOrder, int $currentStepId): bool
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*)
             FROM workflow_instance_steps
             WHERE workflow_instance_id = :instance_id
               AND phase_order = :phase_order
               AND status = 'IN_PROGRESS'
               AND instance_step_id <> :current_step_id"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $phaseOrder,
            'current_step_id' => $currentStepId,
        ]);
        return (int) $statement->fetchColumn() === 0;
    }

    private function nextOptionalSteps(int $instanceId, int $phaseOrder): array
    {
        $nextPhase = $this->nextPhase($instanceId, $phaseOrder);
        if ($nextPhase === null) {
            return [];
        }
        $statement = $this->db->prepare(
            "SELECT step_key, step_label
             FROM workflow_instance_steps
             WHERE workflow_instance_id = :instance_id
               AND phase_order = :phase_order
               AND status = 'PENDING'
               AND is_optional = TRUE
             ORDER BY step_order"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'phase_order' => $nextPhase,
        ]);
        return $statement->fetchAll();
    }

    private function decisionContextForUpdate(int $instanceId, int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                instance.workflow_instance_id,
                instance.entity_id,
                instance.current_phase_order,
                step.instance_step_id,
                step.phase_order,
                step.step_key,
                step.step_label,
                step.allow_revision
             FROM workflow_instances instance
             JOIN workflow_instance_steps step
               ON step.workflow_instance_id = instance.workflow_instance_id
              AND step.phase_order = instance.current_phase_order
              AND step.status = 'IN_PROGRESS'
             JOIN workflow_step_assignments assignment
               ON assignment.workflow_instance_step_id = step.instance_step_id
              AND assignment.user_id = :user_id
              AND assignment.is_active = TRUE
             WHERE instance.workflow_instance_id = :instance_id
               AND instance.entity_type = 'AGREEMENT'
               AND instance.engine_version = 'CONFIGURABLE'
               AND instance.status = 'IN_PROGRESS'
             ORDER BY step.step_order
             LIMIT 1
             FOR UPDATE OF instance, step"
        );
        $statement->execute([
            'instance_id' => $instanceId,
            'user_id' => $userId,
        ]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    private function finishStep(
        int $stepId,
        string $status,
        int $userId,
        ?string $comment
    ): void {
        $statement = $this->db->prepare(
            "UPDATE workflow_instance_steps
             SET status = CAST(:status AS workflow_step_status),
                 approved_by = :user_id,
                 approved_at = CURRENT_TIMESTAMP,
                 completed_at = CURRENT_TIMESTAMP,
                 comments = :comments
             WHERE instance_step_id = :step_id
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'comments' => $comment,
            'step_id' => $stepId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new DomainException('The Workflow stage could not be completed.');
        }
    }

    private function phaseHasOpenSteps(int $instanceId, int $phaseOrder): bool
    {
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

    private function nextPhase(int $instanceId, int $phaseOrder): ?int
    {
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
        return $value === false || $value === null ? null : (int) $value;
    }

    private function completeInstance(int $instanceId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'COMPLETED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        );
        $statement->execute(['instance_id' => $instanceId]);
        $this->deactivateInstanceAssignments($instanceId);
    }

    private function cancelInstance(int $instanceId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'CANCELLED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        );
        $statement->execute(['instance_id' => $instanceId]);
    }

    private function rejectInstance(int $instanceId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_instances
             SET status = 'REJECTED',
                 current_phase_order = NULL,
                 completed_at = CURRENT_TIMESTAMP
             WHERE workflow_instance_id = :instance_id"
        );
        $statement->execute(['instance_id' => $instanceId]);
    }

    private function changeAgreementStatus(int $agreementId, string $status): void
    {
        $statement = $this->db->prepare(
            "UPDATE agreements
             SET status = CAST(:status AS agreement_status),
                 updated_at = CURRENT_TIMESTAMP
             WHERE agreement_id = :agreement_id"
        );
        $statement->execute([
            'status' => $status,
            'agreement_id' => $agreementId,
        ]);
    }

    private function deactivateStepAssignments(int $stepId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_step_assignments
             SET is_active = FALSE
             WHERE workflow_instance_step_id = :step_id
               AND is_active = TRUE"
        );
        $statement->execute(['step_id' => $stepId]);
    }

    private function deactivateInstanceAssignments(int $instanceId): void
    {
        $statement = $this->db->prepare(
            "UPDATE workflow_step_assignments assignment
             SET is_active = FALSE
             FROM workflow_instance_steps step
             WHERE step.instance_step_id = assignment.workflow_instance_step_id
               AND step.workflow_instance_id = :instance_id
               AND assignment.is_active = TRUE"
        );
        $statement->execute(['instance_id' => $instanceId]);
    }

    private function insertAudit(
        int $actorUserId,
        int $agreementId,
        string $reason,
        array $data
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO audit_logs (
                table_name,
                record_id,
                action,
                user_id,
                old_data,
                new_data,
                reason,
                ip_address,
                created_at
             ) VALUES (
                'workflow_instances',
                :record_id,
                'UPDATE',
                :actor_user_id,
                '{}'::JSONB,
                CAST(:new_data AS JSONB),
                :reason,
                :ip_address,
                CURRENT_TIMESTAMP
             )"
        );
        $statement->execute([
            'record_id' => $agreementId,
            'actor_user_id' => $actorUserId,
            'new_data' => json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    private function fallbackStepLabel(string $stepKey): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $stepKey)));
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
