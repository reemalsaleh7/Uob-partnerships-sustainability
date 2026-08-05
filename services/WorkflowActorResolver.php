<?php

declare(strict_types=1);

final class WorkflowActorResolver
{
    public function __construct(private PDO $db)
    {
    }

    public function resolveUnitId(
        string $scope,
        ?int $fixedUnitId,
        ?int $requesterUnitId
    ): ?int {
        $scope = strtoupper(trim($scope));

        if ($scope === 'NONE') {
            return null;
        }
        if ($scope === 'FIXED_UNIT') {
            return $fixedUnitId;
        }
        if ($scope === 'UNIVERSITY') {
            $statement = $this->db->query(
                "SELECT unit_id
                 FROM organizational_units
                 WHERE is_active = TRUE
                   AND UPPER(unit_type::TEXT) = 'UNIVERSITY'
                 ORDER BY unit_id
                 LIMIT 1"
            );
            $value = $statement->fetchColumn();
            return $value === false ? null : (int) $value;
        }
        if ($requesterUnitId === null) {
            return null;
        }

        $targetType = match ($scope) {
            'REQUESTER_DEPARTMENT' => 'DEPARTMENT',
            'REQUESTER_COLLEGE' => 'COLLEGE',
            default => null,
        };
        if ($targetType === null) {
            return null;
        }

        $statement = $this->db->prepare(
            "WITH RECURSIVE unit_chain AS (
                SELECT
                    unit_id,
                    parent_unit_id,
                    unit_type::TEXT AS unit_type,
                    0 AS depth
                FROM organizational_units
                WHERE unit_id = :requester_unit_id
                  AND is_active = TRUE

                UNION ALL

                SELECT
                    parent.unit_id,
                    parent.parent_unit_id,
                    parent.unit_type::TEXT AS unit_type,
                    chain.depth + 1
                FROM organizational_units parent
                JOIN unit_chain chain
                  ON parent.unit_id = chain.parent_unit_id
                WHERE parent.is_active = TRUE
            )
            SELECT unit_id
            FROM unit_chain
            WHERE UPPER(unit_type) = :target_type
            ORDER BY depth
            LIMIT 1"
        );
        $statement->execute([
            'requester_unit_id' => $requesterUnitId,
            'target_type' => $targetType,
        ]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    /** @return list<int> */
    public function eligibleUserIds(
        string $permissionCode,
        ?int $unitId,
        ?int $positionId
    ): array {
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
            "SELECT DISTINCT account.user_id
             FROM users account
             JOIN user_positions assignment
               ON assignment.user_id = account.user_id
             JOIN user_roles user_role
               ON user_role.user_id = account.user_id
             JOIN role_permissions role_permission
               ON role_permission.role_id = user_role.role_id
             JOIN permissions permission
               ON permission.permission_id = role_permission.permission_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY account.user_id"
        );
        $statement->execute($parameters);

        return array_map(
            static fn (mixed $value): int => (int) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /** @return array{unit_id:?int,position_id:?int} */
    public function activeAssignmentForUser(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT unit_id, position_id
             FROM user_positions
             WHERE user_id = :user_id
               AND is_active = TRUE
               AND (end_date IS NULL OR end_date >= CURRENT_DATE)
             ORDER BY start_date DESC, user_position_id DESC
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return [
            'unit_id' => $row && $row['unit_id'] !== null
                ? (int) $row['unit_id']
                : null,
            'position_id' => $row && $row['position_id'] !== null
                ? (int) $row['position_id']
                : null,
        ];
    }

    public function userName(int $userId): string
    {
        $statement = $this->db->prepare(
            "SELECT COALESCE(
                NULLIF(TRIM(CONCAT(first_name, ' ', last_name)), ''),
                email,
                'User #' || user_id::TEXT
             )
             FROM users
             WHERE user_id = :user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $value = $statement->fetchColumn();
        return $value === false ? 'User #' . $userId : (string) $value;
    }
}
