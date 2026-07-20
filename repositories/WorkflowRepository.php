<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class WorkflowRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findActiveTemplate(
        string $processType
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM workflow_templates
             WHERE process_type = :process_type
               AND is_active = TRUE
             ORDER BY workflow_template_id
             LIMIT 1'
        );

        $stmt->execute([
            'process_type' => strtoupper($processType),
        ]);

        $template = $stmt->fetch();

        return $template ?: null;
    }

    public function findTemplateSteps(
        int $templateId
    ): array {
        $stmt = $this->db->prepare(
            'SELECT
                wts.*,
                ou.code AS required_unit_code
             FROM workflow_template_steps wts
             LEFT JOIN organizational_units ou
                ON ou.unit_id = wts.required_unit_id
             WHERE wts.workflow_template_id = :template_id
             ORDER BY wts.step_order'
        );

        $stmt->execute([
            'template_id' => $templateId,
        ]);

        return $stmt->fetchAll();
    }

    public function findActiveByEntity(
        string $entityType,
        int $entityId
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM workflow_instances
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND status = \'IN_PROGRESS\'
             LIMIT 1'
        );

        $stmt->execute([
            'entity_type' => strtoupper($entityType),
            'entity_id' => $entityId,
        ]);

        $instance = $stmt->fetch();

        return $instance ?: null;
    }

    public function findInstanceById(
        int $instanceId,
        bool $forUpdate = false
    ): ?array {
        $sql =
            'SELECT *
             FROM workflow_instances
             WHERE workflow_instance_id = :instance_id';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'instance_id' => $instanceId,
        ]);

        $instance = $stmt->fetch();

        return $instance ?: null;
    }

    public function createInstance(array $data): int
    {
        $financeReviewRequired =
            $data['finance_review_required'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO workflow_instances (
                workflow_template_id,
                entity_type,
                entity_id,
                current_step,
                finance_review_required,
                status,
                started_by,
                started_at
             ) VALUES (
                :workflow_template_id,
                :entity_type,
                :entity_id,
                :current_step,
                CAST(:finance_review_required AS BOOLEAN),
                CAST(:status AS workflow_status),
                :started_by,
                NOW()
             )
             RETURNING workflow_instance_id'
        );

        $stmt->execute([
            'workflow_template_id' =>
                $data['workflow_template_id'],
            'entity_type' =>
                strtoupper($data['entity_type']),
            'entity_id' => $data['entity_id'],
            'current_step' =>
                $data['current_step'] ?? 1,
            'finance_review_required' =>
                $financeReviewRequired === null
                    ? null
                    : $this->toPostgresBoolean(
                        $financeReviewRequired
                    ),
            'status' =>
                $data['status'] ?? 'IN_PROGRESS',
            'started_by' => $data['started_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function createInstanceStep(
        int $instanceId,
        array $templateStep,
        string $status = 'PENDING',
        ?int $actedBy = null
    ): int {
        $now = $this->currentTimestamp();

        $approvedAt =
            $actedBy !== null
                ? $now
                : null;

        $startedAt =
            in_array(
                $status,
                ['IN_PROGRESS', 'APPROVED'],
                true
            )
                ? $now
                : null;

        $completedAt =
            in_array(
                $status,
                [
                    'APPROVED',
                    'CHANGES_REQUESTED',
                    'REJECTED',
                    'SKIPPED',
                ],
                true
            )
                ? $now
                : null;

        $stmt = $this->db->prepare(
            'INSERT INTO workflow_instance_steps (
                workflow_instance_id,
                template_step_id,
                step_order,
                step_key,
                assigned_unit_id,
                assigned_position_id,
                is_optional,
                status,
                approved_by,
                approved_at,
                started_at,
                completed_at
             ) VALUES (
                :workflow_instance_id,
                :template_step_id,
                :step_order,
                :step_key,
                :assigned_unit_id,
                :assigned_position_id,
                CAST(:is_optional AS BOOLEAN),
                CAST(:status AS workflow_step_status),
                :acted_by,
                :approved_at,
                :started_at,
                :completed_at
             )
             RETURNING instance_step_id'
        );

        $stmt->execute([
            'workflow_instance_id' => $instanceId,
            'template_step_id' =>
                $templateStep['template_step_id'],
            'step_order' =>
                $templateStep['step_order'],
            'step_key' =>
                $templateStep['step_key'],
            'assigned_unit_id' =>
                $templateStep['required_unit_id'],
            'assigned_position_id' =>
                $templateStep['required_position_id'],
            'is_optional' =>
                $this->toPostgresBoolean(
                    $templateStep['is_optional']
                ),
            'status' => $status,
            'acted_by' => $actedBy,
            'approved_at' => $approvedAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findSteps(int $instanceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                wis.*,
                ou.code AS assigned_unit_code,
                ou.name AS assigned_unit_name
             FROM workflow_instance_steps wis
             LEFT JOIN organizational_units ou
                ON ou.unit_id = wis.assigned_unit_id
             WHERE wis.workflow_instance_id = :instance_id
             ORDER BY wis.step_order'
        );

        $stmt->execute([
            'instance_id' => $instanceId,
        ]);

        return $stmt->fetchAll();
    }

    public function findStepByKey(
        int $instanceId,
        string $stepKey,
        bool $forUpdate = false
    ): ?array {
        $sql =
            'SELECT *
             FROM workflow_instance_steps
             WHERE workflow_instance_id = :instance_id
               AND step_key = :step_key';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'instance_id' => $instanceId,
            'step_key' => $stepKey,
        ]);

        $step = $stmt->fetch();

        return $step ?: null;
    }

    public function setStepStatus(
        int $instanceStepId,
        string $status,
        ?int $actedBy = null,
        ?string $comments = null
    ): void {
        $fields = [
            'status = CAST(:status AS workflow_step_status)',
        ];

        $params = [
            'status' => $status,
            'instance_step_id' => $instanceStepId,
        ];

        if ($actedBy !== null) {
            $fields[] = 'approved_by = :acted_by';
            $fields[] = 'approved_at = NOW()';
            $params['acted_by'] = $actedBy;
        }

        if ($status === 'IN_PROGRESS') {
            $fields[] =
                'started_at = COALESCE(started_at, NOW())';
        }

        if (
            in_array(
                $status,
                ['APPROVED', 'REJECTED', 'SKIPPED'],
                true
            )
        ) {
            $fields[] = 'completed_at = NOW()';
        }

        if ($comments !== null) {
            $fields[] = 'comments = :comments';
            $params['comments'] = $comments;
        }

        $stmt = $this->db->prepare(
            'UPDATE workflow_instance_steps
             SET ' . implode(', ', $fields) . '
             WHERE instance_step_id = :instance_step_id'
        );

        $stmt->execute($params);
    }

    public function setFinanceReviewRequired(
        int $instanceId,
        bool $required
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE workflow_instances
             SET finance_review_required =
                 CAST(:required AS BOOLEAN)
             WHERE workflow_instance_id = :instance_id'
        );

        $stmt->execute([
            'required' =>
                $this->toPostgresBoolean($required),
            'instance_id' => $instanceId,
        ]);
    }

    public function setCurrentStep(
        int $instanceId,
        int $stepOrder
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE workflow_instances
             SET current_step = :step_order
             WHERE workflow_instance_id = :instance_id'
        );

        $stmt->execute([
            'step_order' => $stepOrder,
            'instance_id' => $instanceId,
        ]);
    }

    public function setInstanceStatus(
        int $instanceId,
        string $status
    ): void {
        $fields = [
            'status = CAST(:status AS workflow_status)',
        ];

        if (
            in_array(
                $status,
                ['COMPLETED', 'REJECTED', 'CANCELLED'],
                true
            )
        ) {
            $fields[] = 'completed_at = NOW()';
        } else {
            $fields[] = 'completed_at = NULL';
        }

        $stmt = $this->db->prepare(
            'UPDATE workflow_instances
             SET ' . implode(', ', $fields) . '
             WHERE workflow_instance_id = :instance_id'
        );

        $stmt->execute([
            'status' => $status,
            'instance_id' => $instanceId,
        ]);
    }

    public function assignEligibleUsersForUnit(
        int $instanceStepId,
        int $unitId,
        string $permissionCode = 'APPROVE_AGREEMENT'
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO workflow_step_assignments (
                workflow_instance_step_id,
                user_id,
                assigned_at,
                is_active
             )
             SELECT DISTINCT
                CAST(:instance_step_id AS BIGINT),
                up.user_id,
                NOW(),
                TRUE
             FROM user_positions up
             JOIN users u
                ON u.user_id = up.user_id
               AND u.is_active = TRUE
             JOIN user_roles ur
                ON ur.user_id = u.user_id
             JOIN role_permissions rp
                ON rp.role_id = ur.role_id
             JOIN permissions p
                ON p.permission_id = rp.permission_id
               AND p.permission_code = :permission_code
             WHERE up.unit_id = :unit_id
               AND up.is_active = TRUE
               AND (
                   up.end_date IS NULL
                   OR up.end_date >= CURRENT_DATE
               )
             ON CONFLICT DO NOTHING'
        );

        $stmt->execute([
            'instance_step_id' => $instanceStepId,
            'unit_id' => $unitId,
            'permission_code' => $permissionCode,
        ]);

        return $stmt->rowCount();
    }

    public function isUserAssignedToStep(
        int $instanceStepId,
        int $userId
    ): bool {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM workflow_step_assignments
             WHERE workflow_instance_step_id =
                   :instance_step_id
               AND user_id = :user_id
               AND is_active = TRUE
             LIMIT 1'
        );

        $stmt->execute([
            'instance_step_id' => $instanceStepId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function deactivateStepAssignments(
        int $instanceStepId
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE workflow_step_assignments
             SET is_active = FALSE
             WHERE workflow_instance_step_id =
                   :instance_step_id
               AND is_active = TRUE'
        );

        $stmt->execute([
            'instance_step_id' => $instanceStepId,
        ]);
    }

    public function prepareStepForReview(
    int $instanceStepId,
    string $status = 'IN_PROGRESS'
): void {
    if (
        !in_array(
            $status,
            ['PENDING', 'IN_PROGRESS'],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'A reset workflow step must be PENDING or IN_PROGRESS'
        );
    }

    $stmt = $this->db->prepare(
        'UPDATE workflow_instance_steps
         SET status =
                CAST(:status AS workflow_step_status),
             approved_by = NULL,
             approved_at = NULL,
             completed_at = NULL,
             comments = NULL,
             started_at =
                CASE
                    WHEN :status = \'IN_PROGRESS\'
                        THEN NOW()
                    ELSE NULL
                END
         WHERE instance_step_id =
               :instance_step_id'
    );

    $stmt->execute([
        'status' => $status,
        'instance_step_id' => $instanceStepId,
    ]);
}

public function assignUserToStep(
    int $instanceStepId,
    int $userId
): int {
    $stmt = $this->db->prepare(
        'INSERT INTO workflow_step_assignments (
            workflow_instance_step_id,
            user_id,
            assigned_at,
            is_active
         ) VALUES (
            :instance_step_id,
            :user_id,
            NOW(),
            TRUE
         )
         ON CONFLICT DO NOTHING'
    );

    $stmt->execute([
        'instance_step_id' => $instanceStepId,
        'user_id' => $userId,
    ]);

    return $stmt->rowCount();
}

public function deactivateInstanceAssignments(
    int $instanceId
): void {
    $stmt = $this->db->prepare(
        'UPDATE workflow_step_assignments
         SET is_active = FALSE
         WHERE is_active = TRUE
           AND workflow_instance_step_id IN (
               SELECT instance_step_id
               FROM workflow_instance_steps
               WHERE workflow_instance_id =
                     :instance_id
           )'
    );

    $stmt->execute([
        'instance_id' => $instanceId,
    ]);
}

public function incrementReviewCycle(
    int $instanceId
): int {
    $stmt = $this->db->prepare(
        'UPDATE workflow_instances
         SET review_cycle = review_cycle + 1,
             completed_at = NULL
         WHERE workflow_instance_id =
               :instance_id
         RETURNING review_cycle'
    );

    $stmt->execute([
        'instance_id' => $instanceId,
    ]);

    $reviewCycle = $stmt->fetchColumn();

    if ($reviewCycle === false) {
        throw new DomainException(
            'Workflow instance was not found'
        );
    }

    return (int) $reviewCycle;
}

    public function setRedraftBaseVersion(
    int $instanceId,
    int $versionNumber
): void {
    if ($versionNumber < 0) {
        throw new InvalidArgumentException(
            'Redraft base version cannot be negative'
        );
    }

    $stmt = $this->db->prepare(
        'UPDATE workflow_instances
         SET redraft_base_version =
             :version_number
         WHERE workflow_instance_id =
               :instance_id'
    );

    $stmt->execute([
        'version_number' => $versionNumber,
        'instance_id' => $instanceId,
    ]);
}

public function clearRedraftBaseVersion(
    int $instanceId
): void {
    $stmt = $this->db->prepare(
        'UPDATE workflow_instances
         SET redraft_base_version = NULL
         WHERE workflow_instance_id =
               :instance_id'
    );

    $stmt->execute([
        'instance_id' => $instanceId,
    ]);
}

    public function clearFinanceReviewRequired(
    int $instanceId
): void {
    $stmt = $this->db->prepare(
        'UPDATE workflow_instances
         SET finance_review_required = NULL
         WHERE workflow_instance_id =
               :instance_id'
    );

    $stmt->execute([
        'instance_id' => $instanceId,
    ]);
}
    public function addHistory(
        int $instanceId,
        int $instanceStepId,
        string $action,
        int $performedBy,
        ?string $comments = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO workflow_history (
                workflow_instance_id,
                workflow_step_id,
                action,
                performed_by,
                comments,
                created_at
             ) VALUES (
                :instance_id,
                :instance_step_id,
                CAST(:action AS workflow_action_type),
                :performed_by,
                :comments,
                NOW()
             )
             RETURNING history_id'
        );

        $stmt->execute([
            'instance_id' => $instanceId,
            'instance_step_id' => $instanceStepId,
            'action' => $action,
            'performed_by' => $performedBy,
            'comments' => $comments,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findInboxForUser(
        int $userId
    ): array {
        $stmt = $this->db->prepare(
            'SELECT
                wi.workflow_instance_id,
                wi.entity_type,
                wi.entity_id,
                wi.finance_review_required,
                wis.instance_step_id,
                wis.step_order,
                wis.step_key,
                wis.status,
                wis.started_at,
                ou.code AS assigned_unit_code,
                ou.name AS assigned_unit_name
             FROM workflow_step_assignments wsa
             JOIN workflow_instance_steps wis
                ON wis.instance_step_id =
                   wsa.workflow_instance_step_id
             JOIN workflow_instances wi
                ON wi.workflow_instance_id =
                   wis.workflow_instance_id
             LEFT JOIN organizational_units ou
                ON ou.unit_id = wis.assigned_unit_id
             WHERE wsa.user_id = :user_id
               AND wsa.is_active = TRUE
               AND wi.status = \'IN_PROGRESS\'
               AND wis.status = \'IN_PROGRESS\'
             ORDER BY
                wis.started_at,
                wi.workflow_instance_id'
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function findActiveUnitByCode(
        string $unitCode
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM organizational_units
             WHERE code = :unit_code
               AND is_active = TRUE
             LIMIT 1'
        );

        $stmt->execute([
            'unit_code' => strtoupper($unitCode),
        ]);

        $unit = $stmt->fetch();

        return $unit ?: null;
    }

    public function findEligibleUsersForUnit(
        string $unitCode,
        string $permissionCode =
            'APPROVE_AGREEMENT'
    ): array {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT
                u.user_id,
                u.email,
                u.first_name,
                u.last_name
             FROM organizational_units ou
             JOIN user_positions up
                ON up.unit_id = ou.unit_id
               AND up.is_active = TRUE
               AND (
                   up.end_date IS NULL
                   OR up.end_date >= CURRENT_DATE
               )
             JOIN users u
                ON u.user_id = up.user_id
               AND u.is_active = TRUE
             JOIN user_roles ur
                ON ur.user_id = u.user_id
             JOIN role_permissions rp
                ON rp.role_id = ur.role_id
             JOIN permissions p
                ON p.permission_id = rp.permission_id
               AND p.permission_code = :permission_code
             WHERE ou.code = :unit_code
               AND ou.is_active = TRUE
             ORDER BY u.user_id'
        );

        $stmt->execute([
            'unit_code' => strtoupper($unitCode),
            'permission_code' => $permissionCode,
        ]);

        return $stmt->fetchAll();
    }

    public function findActiveMembershipsForUser(
        int $userId
    ): array {
        $stmt = $this->db->prepare(
            'SELECT
                up.user_position_id,
                p.name AS position_name,
                p.is_unique,
                ou.unit_id,
                ou.code AS unit_code,
                ou.name AS unit_name,
                ou.unit_type
             FROM user_positions up
             JOIN positions p
                ON p.position_id = up.position_id
             JOIN organizational_units ou
                ON ou.unit_id = up.unit_id
               AND ou.is_active = TRUE
             WHERE up.user_id = :user_id
               AND up.is_active = TRUE
               AND (
                   up.end_date IS NULL
                   OR up.end_date >= CURRENT_DATE
               )
             ORDER BY up.user_position_id'
        );

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    private function toPostgresBoolean(
        bool|string|int $value
    ): string {
        return in_array(
            $value,
            [true, 1, '1', 't', 'true'],
            true
        )
            ? 'true'
            : 'false';
    }

    private function currentTimestamp(): string
    {
        return (new DateTimeImmutable())
            ->format('Y-m-d H:i:s.u');
    }
}