<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class AdminWorkflowTemplateRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /** @return list<array<string,mixed>> */
    public function activeTemplates(): array
    {
        $statement = $this->db->query(
            "SELECT
                template.workflow_template_id,
                template.template_key,
                template.version_number,
                template.name,
                template.description,
                template.process_type,
                template.is_active,
                template.change_reason,
                template.published_at,
                template.updated_at,
                COUNT(step.template_step_id) AS stage_count,
                COUNT(DISTINCT step.phase_order) AS phase_count
             FROM workflow_templates template
             LEFT JOIN workflow_template_steps step
               ON step.workflow_template_id = template.workflow_template_id
             WHERE template.template_key IN (
                'AGREEMENT_APPROVAL',
                'INITIATIVE_APPROVAL'
             )
               AND template.is_active = TRUE
             GROUP BY template.workflow_template_id
             ORDER BY template.process_type, template.version_number DESC"
        );

        return array_map(
            fn (array $row): array => $this->normalizeTemplate($row),
            $statement->fetchAll()
        );
    }

    public function activeTemplate(string $templateKey, bool $forUpdate = false): ?array
    {
        $sql =
            "SELECT
                workflow_template_id,
                template_key,
                version_number,
                name,
                description,
                process_type,
                is_active,
                change_reason,
                published_at,
                created_at,
                updated_at
             FROM workflow_templates
             WHERE template_key = :template_key
               AND is_active = TRUE
             ORDER BY version_number DESC, workflow_template_id DESC
             LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['template_key' => $templateKey]);
        $template = $statement->fetch();

        return $template ? $this->normalizeTemplate($template) : null;
    }

    public function templateById(int $templateId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                workflow_template_id,
                template_key,
                version_number,
                name,
                description,
                process_type,
                is_active,
                change_reason,
                published_at,
                created_at,
                updated_at
             FROM workflow_templates
             WHERE workflow_template_id = :template_id"
        );
        $statement->execute(['template_id' => $templateId]);
        $template = $statement->fetch();

        return $template ? $this->normalizeTemplate($template) : null;
    }

    /** @return list<array<string,mixed>> */
    public function templateSteps(int $templateId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                step.template_step_id,
                step.workflow_template_id,
                step.step_order,
                step.step_key,
                step.step_label,
                step.phase_order,
                step.execution_mode,
                step.approval_type::TEXT AS approval_type,
                step.is_optional,
                step.responsibility_type,
                step.responsibility_scope,
                step.required_unit_id,
                unit.name AS required_unit_name,
                unit.code AS required_unit_code,
                unit.unit_type::TEXT AS required_unit_type,
                step.required_position_id,
                position.name AS required_position_name,
                step.required_permission_code,
                step.reminder_after_days,
                step.is_system_step,
                step.allow_revision
             FROM workflow_template_steps step
             LEFT JOIN organizational_units unit
               ON unit.unit_id = step.required_unit_id
             LEFT JOIN positions position
               ON position.position_id = step.required_position_id
             WHERE step.workflow_template_id = :template_id
             ORDER BY step.step_order, step.template_step_id"
        );
        $statement->execute(['template_id' => $templateId]);

        return array_map(
            fn (array $row): array => $this->normalizeStep($row),
            $statement->fetchAll()
        );
    }

    /** @return list<array<string,mixed>> */
    public function versions(string $templateKey): array
    {
        $statement = $this->db->prepare(
            "SELECT
                template.workflow_template_id,
                template.template_key,
                template.version_number,
                template.name,
                template.description,
                template.process_type,
                template.is_active,
                template.change_reason,
                template.published_at,
                template.created_at,
                template.updated_at,
                CONCAT(account.first_name, ' ', account.last_name)
                    AS published_by_name,
                account.email AS published_by_email,
                COUNT(step.template_step_id) AS stage_count,
                COUNT(DISTINCT step.phase_order) AS phase_count
             FROM workflow_templates template
             LEFT JOIN users account
               ON account.user_id = template.created_by
             LEFT JOIN workflow_template_steps step
               ON step.workflow_template_id = template.workflow_template_id
             WHERE template.template_key = :template_key
             GROUP BY
                template.workflow_template_id,
                account.first_name,
                account.last_name,
                account.email
             ORDER BY template.version_number DESC, template.workflow_template_id DESC
             LIMIT 30"
        );
        $statement->execute(['template_key' => $templateKey]);

        return array_map(
            fn (array $row): array => $this->normalizeTemplate($row),
            $statement->fetchAll()
        );
    }

    /** @return list<array<string,mixed>> */
    public function units(): array
    {
        $statement = $this->db->query(
            "WITH RECURSIVE unit_tree AS (
                SELECT
                    unit.unit_id,
                    unit.parent_unit_id,
                    unit.name,
                    unit.code,
                    unit.unit_type::TEXT AS unit_type,
                    unit.is_active,
                    unit.display_order,
                    unit.name::TEXT AS path,
                    0 AS depth
                FROM organizational_units unit
                WHERE unit.parent_unit_id IS NULL

                UNION ALL

                SELECT
                    child.unit_id,
                    child.parent_unit_id,
                    child.name,
                    child.code,
                    child.unit_type::TEXT AS unit_type,
                    child.is_active,
                    child.display_order,
                    (parent.path || ' / ' || child.name)::TEXT AS path,
                    parent.depth + 1
                FROM organizational_units child
                JOIN unit_tree parent
                  ON parent.unit_id = child.parent_unit_id
            )
            SELECT *
            FROM unit_tree
            WHERE is_active = TRUE
            ORDER BY path, display_order, name"
        );

        return array_map(static function (array $row): array {
            $row['unit_id'] = (int) $row['unit_id'];
            $row['parent_unit_id'] = $row['parent_unit_id'] === null
                ? null
                : (int) $row['parent_unit_id'];
            $row['depth'] = (int) $row['depth'];
            $row['is_active'] = self::dbBoolean($row['is_active']);
            return $row;
        }, $statement->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function positions(): array
    {
        $statement = $this->db->query(
            "SELECT
                position.position_id,
                position.name,
                position.description,
                position.is_unique,
                position_type.name AS position_type
             FROM positions position
             JOIN position_types position_type
               ON position_type.position_type_id = position.position_type_id
             ORDER BY position_type.name, position.name"
        );

        return array_map(static function (array $row): array {
            $row['position_id'] = (int) $row['position_id'];
            $row['is_unique'] = self::dbBoolean($row['is_unique']);
            return $row;
        }, $statement->fetchAll());
    }

    public function unit(int $unitId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                unit_id,
                name,
                code,
                unit_type::TEXT AS unit_type,
                is_active
             FROM organizational_units
             WHERE unit_id = :unit_id"
        );
        $statement->execute(['unit_id' => $unitId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $row['unit_id'] = (int) $row['unit_id'];
        $row['is_active'] = self::dbBoolean($row['is_active']);
        return $row;
    }

    public function position(int $positionId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT position_id, name, description, is_unique
             FROM positions
             WHERE position_id = :position_id"
        );
        $statement->execute(['position_id' => $positionId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        $row['position_id'] = (int) $row['position_id'];
        $row['is_unique'] = self::dbBoolean($row['is_unique']);
        return $row;
    }

    public function eligibleReviewerCount(
        string $permissionCode,
        ?int $unitId,
        ?int $positionId
    ): int {
        $where = [
            'account.is_active = TRUE',
            'assignment.is_active = TRUE',
            '(assignment.end_date IS NULL OR assignment.end_date >= CURRENT_DATE)',
            'permission.permission_code = :permission_code',
        ];
        $parameters = ['permission_code' => $permissionCode];

        if ($unitId !== null) {
            $where[] = 'assignment.unit_id = :unit_id';
            $parameters['unit_id'] = $unitId;
        }
        if ($positionId !== null) {
            $where[] = 'assignment.position_id = :position_id';
            $parameters['position_id'] = $positionId;
        }

        $statement = $this->db->prepare(
            "SELECT COUNT(DISTINCT account.user_id)
             FROM users account
             JOIN user_positions assignment
               ON assignment.user_id = account.user_id
             JOIN user_roles user_role
               ON user_role.user_id = account.user_id
             JOIN role_permissions role_permission
               ON role_permission.role_id = user_role.role_id
             JOIN permissions permission
               ON permission.permission_id = role_permission.permission_id
             WHERE " . implode(' AND ', $where)
        );
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    public function nextVersionNumber(string $templateKey): int
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(MAX(version_number), 0) + 1
             FROM workflow_templates
             WHERE template_key = :template_key'
        );
        $statement->execute(['template_key' => $templateKey]);
        return (int) $statement->fetchColumn();
    }

    public function deactivateTemplate(int $templateId): void
    {
        $statement = $this->db->prepare(
            'UPDATE workflow_templates
             SET is_active = FALSE,
                 updated_at = CURRENT_TIMESTAMP
             WHERE workflow_template_id = :template_id'
        );
        $statement->execute(['template_id' => $templateId]);
    }

    public function createTemplateVersion(
        string $templateKey,
        string $processType,
        int $versionNumber,
        string $name,
        ?string $description,
        int $actorUserId,
        string $reason
    ): int {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_templates (
                name,
                description,
                process_type,
                is_active,
                template_key,
                version_number,
                created_by,
                change_reason,
                published_at,
                updated_at
             ) VALUES (
                :name,
                :description,
                :process_type,
                TRUE,
                :template_key,
                :version_number,
                :created_by,
                :change_reason,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
             )
             RETURNING workflow_template_id"
        );
        $statement->execute([
            'name' => $name,
            'description' => $description,
            'process_type' => $processType,
            'template_key' => $templateKey,
            'version_number' => $versionNumber,
            'created_by' => $actorUserId,
            'change_reason' => $reason,
        ]);
        return (int) $statement->fetchColumn();
    }

    public function insertTemplateStep(int $templateId, array $step): int
    {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_template_steps (
                workflow_template_id,
                step_order,
                step_key,
                step_label,
                phase_order,
                execution_mode,
                approval_type,
                required_unit_id,
                required_position_id,
                is_optional,
                responsibility_type,
                responsibility_scope,
                required_permission_code,
                reminder_after_days,
                is_system_step,
                allow_revision
             ) VALUES (
                :workflow_template_id,
                :step_order,
                :step_key,
                :step_label,
                :phase_order,
                :execution_mode,
                CAST(:approval_type AS workflow_approval_type),
                :required_unit_id,
                :required_position_id,
                :is_optional,
                :responsibility_type,
                :responsibility_scope,
                :required_permission_code,
                :reminder_after_days,
                :is_system_step,
                :allow_revision
             )
             RETURNING template_step_id"
        );
        $statement->bindValue(':workflow_template_id', $templateId, PDO::PARAM_INT);
        $statement->bindValue(':step_order', (int) $step['step_order'], PDO::PARAM_INT);
        $statement->bindValue(':step_key', (string) $step['step_key'], PDO::PARAM_STR);
        $statement->bindValue(':step_label', (string) $step['step_label'], PDO::PARAM_STR);
        $statement->bindValue(':phase_order', (int) $step['phase_order'], PDO::PARAM_INT);
        $statement->bindValue(':execution_mode', (string) $step['execution_mode'], PDO::PARAM_STR);
        $statement->bindValue(':approval_type', (string) $step['approval_type'], PDO::PARAM_STR);
        $statement->bindValue(
            ':required_unit_id',
            $step['required_unit_id'],
            $step['required_unit_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $statement->bindValue(
            ':required_position_id',
            $step['required_position_id'],
            $step['required_position_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $statement->bindValue(':is_optional', (bool) $step['is_optional'], PDO::PARAM_BOOL);
        $statement->bindValue(':responsibility_type', (string) $step['responsibility_type'], PDO::PARAM_STR);
        $statement->bindValue(':responsibility_scope', (string) $step['responsibility_scope'], PDO::PARAM_STR);
        $statement->bindValue(':required_permission_code', (string) $step['required_permission_code'], PDO::PARAM_STR);
        $statement->bindValue(':reminder_after_days', (int) $step['reminder_after_days'], PDO::PARAM_INT);
        $statement->bindValue(':is_system_step', (bool) $step['is_system_step'], PDO::PARAM_BOOL);
        $statement->bindValue(':allow_revision', (bool) $step['allow_revision'], PDO::PARAM_BOOL);
        $statement->execute();
        return (int) $statement->fetchColumn();
    }

    public function insertPublication(
        string $templateKey,
        int $previousTemplateId,
        int $publishedTemplateId,
        int $actorUserId,
        string $reason
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO workflow_template_publications (
                template_key,
                previous_template_id,
                published_template_id,
                published_by,
                change_reason
             ) VALUES (
                :template_key,
                :previous_template_id,
                :published_template_id,
                :published_by,
                :change_reason
             )"
        );
        $statement->execute([
            'template_key' => $templateKey,
            'previous_template_id' => $previousTemplateId,
            'published_template_id' => $publishedTemplateId,
            'published_by' => $actorUserId,
            'change_reason' => $reason,
        ]);
    }

    private function normalizeTemplate(array $row): array
    {
        foreach (['workflow_template_id', 'version_number', 'stage_count', 'phase_count'] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = self::dbBoolean($row['is_active']);
        }
        return $row;
    }

    private function normalizeStep(array $row): array
    {
        foreach ([
            'template_step_id',
            'workflow_template_id',
            'step_order',
            'phase_order',
            'required_unit_id',
            'required_position_id',
            'reminder_after_days',
        ] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                $row[$key] = (int) $row[$key];
            }
        }
        foreach (['is_optional', 'is_system_step', 'allow_revision'] as $key) {
            if (array_key_exists($key, $row)) {
                $row[$key] = self::dbBoolean($row[$key]);
            }
        }
        return $row;
    }

    private static function dbBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
