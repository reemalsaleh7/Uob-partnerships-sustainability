<?php

require_once __DIR__ . '/../config/database.php';

class WorkflowRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findActiveTemplate(string $processType): ?array
    {
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

    public function findTemplateSteps(int $templateId): array
    {
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
                :finance_review_required,
                CAST(:status AS workflow_status),
                :started_by,
                NOW()
             )
             RETURNING workflow_instance_id'
        );

        $stmt->execute([
            'workflow_template_id' => $data['workflow_template_id'],
            'entity_type' => strtoupper($data['entity_type']),
            'entity_id' => $data['entity_id'],
            'current_step' => $data['current_step'] ?? 1,
            'finance_review_required' =>
                $data['finance_review_required'] ?? null,
            'status' => $data['status'] ?? 'IN_PROGRESS',
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
                :is_optional,
                CAST(:status AS workflow_step_status),
                :acted_by,
                CASE
                    WHEN :acted_by IS NOT NULL THEN NOW()
                    ELSE NULL
                END,
                CASE
                    WHEN :status IN (\'IN_PROGRESS\', \'APPROVED\')
                    THEN NOW()
                    ELSE NULL
                END,
                CASE
                    WHEN :status IN (\'APPROVED\', \'REJECTED\', \'SKIPPED\')
                    THEN NOW()
                    ELSE NULL
                END
             )
             RETURNING instance_step_id'
        );

        $stmt->execute([
            'workflow_instance_id' => $instanceId,
            'template_step_id' => $templateStep['template_step_id'],
            'step_order' => $templateStep['step_order'],
            'step_key' => $templateStep['step_key'],
            'assigned_unit_id' => $templateStep['required_unit_id'],
            'assigned_position_id' =>
                $templateStep['required_position_id'],
            'is_optional' => $templateStep['is_optional'],
            'status' => $status,
            'acted_by' => $actedBy,
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
        $stmt = $this->db->prepare(
            'UPDATE workflow_instance_steps
             SET
                status = CAST(:status AS workflow_step_status),
                approved_by = CASE
                    WHEN :acted_by IS NOT NULL THEN :acted_by
                    ELSE approved_by
                END,
                approved_at = CASE
                    WHEN :acted_by IS NOT NULL THEN NOW()
                    ELSE approved_at
                END,
                started_at = CASE
                    WHEN :status = \'IN_PROGRESS\'
                    THEN COALESCE(started_at, NOW())
                    ELSE started_at
                END,
                completed_at = CASE
                    WHEN :status IN (\'APPROVED\', \'REJECTED\', \'SKIPPED\')
                    THEN NOW()
                    ELSE completed_at
                END,
                comments = COALESCE(:comments, comments)
             WHERE instance_step_id = :instance_step_id'
        );

        $stmt->execute([
            'status' => $status,
            'acted_by' => $actedBy,
            'comments' => $comments,
            'instance_step_id' => $instanceStepId,
        ]);
    }

    public function setFinanceReviewRequired(
        int $instanceId,
        bool $required
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE workflow_instances
             SET finance_review_required = :required
             WHERE workflow_instance_id = :instance_id'
        );

        $stmt->execute([
            'required' => $required,
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
        $stmt = $this->db->prepare(
            'UPDATE workflow_instances
             SET
                status = CAST(:status AS workflow_status),
                completed_at = CASE
                    WHEN :status IN (\'COMPLETED\', \'REJECTED\', \'CANCELLED\')
                    THEN NOW()
                    ELSE NULL
                END
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
                :instance_step_id,
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
             WHERE workflow_instance_step_id = :instance_step_id
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
             WHERE workflow_instance_step_id = :instance_step_id
               AND is_active = TRUE'
        );

        $stmt->execute([
            'instance_step_id' => $instanceStepId,
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

    public function findInboxForUser(int $userId): array
    {
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
             ORDER BY wis.started_at, wi.workflow_instance_id'
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
        string $permissionCode = 'APPROVE_AGREEMENT'
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
}