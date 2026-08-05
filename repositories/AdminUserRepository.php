<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class AdminUserRepository
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

    /**
     * @return array{items:list<array<string,mixed>>,pagination:array<string,int>}
     */
    public function searchUsers(
        string $search,
        ?bool $active,
        ?int $unitId,
        int $page,
        int $limit
    ): array {
        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = "LOWER(CONCAT_WS(' ',
                users.university_id,
                users.first_name,
                users.last_name,
                users.email,
                COALESCE(users.phone, '')
            )) LIKE :search";
            $parameters['search'] = '%' . mb_strtolower($search) . '%';
        }

        if ($active !== null) {
            $where[] = 'users.is_active = :is_active';
            $parameters['is_active'] = $active;
        }

        if ($unitId !== null) {
            $where[] = "EXISTS (
                SELECT 1
                FROM user_positions unit_filter
                WHERE unit_filter.user_id = users.user_id
                  AND unit_filter.unit_id = :unit_id
                  AND unit_filter.is_active = TRUE
                  AND (
                      unit_filter.end_date IS NULL
                      OR unit_filter.end_date >= CURRENT_DATE
                  )
            )";
            $parameters['unit_id'] = $unitId;
        }

        $whereSql = $where === []
            ? ''
            : 'WHERE ' . implode(' AND ', $where);

        $countStatement = $this->db->prepare(
            "SELECT COUNT(*)
             FROM users
             {$whereSql}"
        );
        $this->bindSearchParameters($countStatement, $parameters);
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();

        $offset = ($page - 1) * $limit;
        $listParameters = $parameters;
        $listParameters['limit'] = $limit;
        $listParameters['offset'] = $offset;

        $statement = $this->db->prepare(
            "SELECT
                users.user_id,
                users.university_id,
                users.first_name,
                users.last_name,
                users.email,
                users.phone,
                users.is_active,
                users.last_login,
                users.updated_at,
                COALESCE(role_data.roles, '[]'::JSONB) AS roles,
                position_data.user_position_id,
                position_data.position_id,
                position_data.position_name,
                position_data.unit_id,
                position_data.unit_name,
                position_data.unit_code,
                position_data.unit_type
             FROM users
             LEFT JOIN LATERAL (
                SELECT JSONB_AGG(
                    JSONB_BUILD_OBJECT(
                        'role_id', role.role_id,
                        'role_name', role.role_name
                    )
                    ORDER BY role.role_name
                ) AS roles
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                WHERE user_role.user_id = users.user_id
             ) role_data ON TRUE
             LEFT JOIN LATERAL (
                SELECT
                    user_position.user_position_id,
                    position.position_id,
                    position.name AS position_name,
                    unit.unit_id,
                    unit.name AS unit_name,
                    unit.code AS unit_code,
                    unit.unit_type::TEXT AS unit_type
                FROM user_positions user_position
                JOIN positions position
                  ON position.position_id = user_position.position_id
                JOIN organizational_units unit
                  ON unit.unit_id = user_position.unit_id
                WHERE user_position.user_id = users.user_id
                  AND user_position.is_active = TRUE
                  AND (
                      user_position.end_date IS NULL
                      OR user_position.end_date >= CURRENT_DATE
                  )
                ORDER BY
                    user_position.start_date DESC,
                    user_position.user_position_id DESC
                LIMIT 1
             ) position_data ON TRUE
             {$whereSql}
             ORDER BY
                users.is_active DESC,
                LOWER(users.first_name),
                LOWER(users.last_name),
                users.user_id
             LIMIT :limit OFFSET :offset"
        );

        $this->bindSearchParameters($statement, $listParameters);
        $statement->execute();

        $items = array_map(
            fn (array $row): array => $this->normalizeUserRow($row),
            $statement->fetchAll()
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
        ];
    }

    public function findUserForUpdate(int $userId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT
                user_id,
                university_id,
                first_name,
                last_name,
                email,
                phone,
                is_active,
                updated_at
             FROM users
             WHERE user_id = :user_id
             FOR UPDATE'
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function findUserDetail(int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                users.user_id,
                users.university_id,
                users.first_name,
                users.last_name,
                users.email,
                users.phone,
                users.is_active,
                users.last_login,
                users.failed_login_attempts,
                users.locked_until,
                users.password_changed_at,
                users.created_at,
                users.updated_at,
                COALESCE(role_data.roles, '[]'::JSONB) AS roles,
                COALESCE(permission_data.permissions, '[]'::JSONB) AS permissions,
                COALESCE(position_data.positions, '[]'::JSONB) AS positions
             FROM users
             LEFT JOIN LATERAL (
                SELECT JSONB_AGG(
                    JSONB_BUILD_OBJECT(
                        'role_id', role.role_id,
                        'role_name', role.role_name,
                        'description', role.description
                    )
                    ORDER BY role.role_name
                ) AS roles
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                WHERE user_role.user_id = users.user_id
             ) role_data ON TRUE
             LEFT JOIN LATERAL (
                SELECT JSONB_AGG(permission.permission_code ORDER BY permission.permission_code)
                    AS permissions
                FROM (
                    SELECT DISTINCT permission.permission_code
                    FROM user_roles user_role
                    JOIN role_permissions role_permission
                      ON role_permission.role_id = user_role.role_id
                    JOIN permissions permission
                      ON permission.permission_id = role_permission.permission_id
                    WHERE user_role.user_id = users.user_id
                ) permission
             ) permission_data ON TRUE
             LEFT JOIN LATERAL (
                SELECT JSONB_AGG(
                    JSONB_BUILD_OBJECT(
                        'user_position_id', history.user_position_id,
                        'position_id', history.position_id,
                        'position_name', history.position_name,
                        'unit_id', history.unit_id,
                        'unit_name', history.unit_name,
                        'unit_code', history.unit_code,
                        'unit_type', history.unit_type,
                        'start_date', history.start_date,
                        'end_date', history.end_date,
                        'is_active', history.is_active
                    )
                    ORDER BY history.is_active DESC, history.start_date DESC,
                        history.user_position_id DESC
                ) AS positions
                FROM (
                    SELECT
                        user_position.user_position_id,
                        position.position_id,
                        position.name AS position_name,
                        unit.unit_id,
                        unit.name AS unit_name,
                        unit.code AS unit_code,
                        unit.unit_type::TEXT AS unit_type,
                        user_position.start_date,
                        user_position.end_date,
                        user_position.is_active
                    FROM user_positions user_position
                    JOIN positions position
                      ON position.position_id = user_position.position_id
                    JOIN organizational_units unit
                      ON unit.unit_id = user_position.unit_id
                    WHERE user_position.user_id = users.user_id
                    ORDER BY
                        user_position.is_active DESC,
                        user_position.start_date DESC,
                        user_position.user_position_id DESC
                    LIMIT 25
                ) history
             ) position_data ON TRUE
             WHERE users.user_id = :user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch();

        if (!$user) {
            return null;
        }

        return $this->normalizeUserRow($user);
    }

    /** @return list<array<string,mixed>> */
    public function listRoles(): array
    {
        $statement = $this->db->query(
            "SELECT
                role.role_id,
                role.role_name,
                role.description,
                COALESCE(permission_data.permissions, '[]'::JSONB) AS permissions
             FROM roles role
             LEFT JOIN LATERAL (
                SELECT JSONB_AGG(
                    permission.permission_code
                    ORDER BY permission.permission_code
                ) AS permissions
                FROM role_permissions role_permission
                JOIN permissions permission
                  ON permission.permission_id = role_permission.permission_id
                WHERE role_permission.role_id = role.role_id
             ) permission_data ON TRUE
             ORDER BY role.role_name"
        );

        return array_map(function (array $row): array {
            $row['role_id'] = (int) $row['role_id'];
            $row['permissions'] = $this->decodeJsonArray($row['permissions'] ?? []);
            return $row;
        }, $statement->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listUnits(): array
    {
        $statement = $this->db->query(
            "WITH RECURSIVE unit_tree AS (
                SELECT
                    unit.unit_id,
                    unit.parent_unit_id,
                    unit.name,
                    unit.code,
                    unit.unit_type::TEXT AS unit_type,
                    unit.display_order,
                    unit.is_active,
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
                    child.display_order,
                    child.is_active,
                    (parent.path || ' / ' || child.name)::TEXT AS path,
                    parent.depth + 1 AS depth
                FROM organizational_units child
                JOIN unit_tree parent
                  ON parent.unit_id = child.parent_unit_id
            )
            SELECT
                unit_id,
                parent_unit_id,
                name,
                code,
                unit_type,
                display_order,
                is_active,
                path,
                depth
            FROM unit_tree
            ORDER BY path, display_order, name"
        );

        return array_map(static function (array $row): array {
            $row['unit_id'] = (int) $row['unit_id'];
            $row['parent_unit_id'] = $row['parent_unit_id'] === null
                ? null
                : (int) $row['parent_unit_id'];
            $row['display_order'] = (int) $row['display_order'];
            $row['depth'] = (int) $row['depth'];
            $row['is_active'] = self::databaseBoolean($row['is_active']);
            return $row;
        }, $statement->fetchAll());
    }

    /** @return list<array<string,mixed>> */
    public function listPositions(): array
    {
        $statement = $this->db->query(
            "SELECT
                position.position_id,
                position.name,
                position.description,
                position.is_unique,
                position_type.position_type_id,
                position_type.name AS position_type
             FROM positions position
             JOIN position_types position_type
               ON position_type.position_type_id = position.position_type_id
             ORDER BY position_type.name, position.name"
        );

        return array_map(static function (array $row): array {
            $row['position_id'] = (int) $row['position_id'];
            $row['position_type_id'] = (int) $row['position_type_id'];
            $row['is_unique'] = self::databaseBoolean($row['is_unique']);
            return $row;
        }, $statement->fetchAll());
    }

    /** @return list<int> */
    public function existingRoleIds(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        [$placeholders, $parameters] = $this->integerListParameters(
            $roleIds,
            'role_id'
        );
        $statement = $this->db->prepare(
            'SELECT role_id FROM roles WHERE role_id IN ('
            . implode(', ', $placeholders)
            . ') ORDER BY role_id'
        );
        $statement->execute($parameters);

        return array_map('intval', array_column($statement->fetchAll(), 'role_id'));
    }

    public function findUnit(int $unitId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT
                unit_id,
                name,
                code,
                unit_type::TEXT AS unit_type,
                is_active
             FROM organizational_units
             WHERE unit_id = :unit_id
             LIMIT 1"
        );
        $statement->execute(['unit_id' => $unitId]);
        $unit = $statement->fetch();

        if (!$unit) {
            return null;
        }

        $unit['unit_id'] = (int) $unit['unit_id'];
        $unit['is_active'] = self::databaseBoolean($unit['is_active']);
        return $unit;
    }

    public function findPosition(int $positionId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT
                position_id,
                name,
                description,
                is_unique
             FROM positions
             WHERE position_id = :position_id
             LIMIT 1'
        );
        $statement->execute(['position_id' => $positionId]);
        $position = $statement->fetch();

        if (!$position) {
            return null;
        }

        $position['position_id'] = (int) $position['position_id'];
        $position['is_unique'] = self::databaseBoolean(
            $position['is_unique']
        );
        return $position;
    }

    public function unitExists(int $unitId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1
             FROM organizational_units
             WHERE unit_id = :unit_id
               AND is_active = TRUE'
        );
        $statement->execute(['unit_id' => $unitId]);
        return (bool) $statement->fetchColumn();
    }

    public function positionExists(int $positionId): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM positions WHERE position_id = :position_id'
        );
        $statement->execute(['position_id' => $positionId]);
        return (bool) $statement->fetchColumn();
    }

    public function hasUniquePositionConflict(
        int $userId,
        int $positionId,
        int $unitId
    ): bool {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM positions position
                JOIN user_positions user_position
                  ON user_position.position_id = position.position_id
                JOIN users account
                  ON account.user_id = user_position.user_id
                 AND account.is_active = TRUE
                WHERE position.position_id = :position_id
                  AND position.is_unique = TRUE
                  AND user_position.unit_id = :unit_id
                  AND user_position.user_id <> :user_id
                  AND user_position.is_active = TRUE
                  AND (
                      user_position.end_date IS NULL
                      OR user_position.end_date >= CURRENT_DATE
                  )
            )"
        );
        $statement->execute([
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'user_id' => $userId,
        ]);

        return self::databaseBoolean($statement->fetchColumn());
    }

    /** @return list<int> */
    public function roleIdsGrantingPermission(string $permissionCode): array
    {
        $statement = $this->db->prepare(
            "SELECT DISTINCT role_permission.role_id
             FROM role_permissions role_permission
             JOIN permissions permission
               ON permission.permission_id = role_permission.permission_id
             WHERE permission.permission_code = :permission_code
             ORDER BY role_permission.role_id"
        );
        $statement->execute(['permission_code' => $permissionCode]);
        return array_map('intval', array_column($statement->fetchAll(), 'role_id'));
    }

    public function countActiveManagersExcluding(int $excludedUserId): int
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(DISTINCT account.user_id)
             FROM users account
             JOIN user_roles user_role
               ON user_role.user_id = account.user_id
             JOIN role_permissions role_permission
               ON role_permission.role_id = user_role.role_id
             JOIN permissions permission
               ON permission.permission_id = role_permission.permission_id
             WHERE account.is_active = TRUE
               AND account.user_id <> :excluded_user_id
               AND permission.permission_code = 'MANAGE_USERS'"
        );
        $statement->execute(['excluded_user_id' => $excludedUserId]);
        return (int) $statement->fetchColumn();
    }

    public function updateUser(int $userId, array $data): void
    {
        $statement = $this->db->prepare(
            'UPDATE users
             SET university_id = :university_id,
                 first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 phone = :phone,
                 is_active = :is_active,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        );
        $statement->bindValue(
            ':university_id',
            (string) $data['university_id'],
            PDO::PARAM_STR
        );
        $statement->bindValue(
            ':first_name',
            (string) $data['first_name'],
            PDO::PARAM_STR
        );
        $statement->bindValue(
            ':last_name',
            (string) $data['last_name'],
            PDO::PARAM_STR
        );
        $statement->bindValue(':email', (string) $data['email'], PDO::PARAM_STR);
        $statement->bindValue(
            ':phone',
            $data['phone'],
            $data['phone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR
        );
        $statement->bindValue(
            ':is_active',
            (bool) $data['is_active'],
            PDO::PARAM_BOOL
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
    }

    /** @param list<int> $roleIds */
    public function replaceRoles(int $userId, array $roleIds): void
    {
        $delete = $this->db->prepare(
            'DELETE FROM user_roles WHERE user_id = :user_id'
        );
        $delete->execute(['user_id' => $userId]);

        if ($roleIds === []) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)
             ON CONFLICT (user_id, role_id) DO NOTHING'
        );

        foreach ($roleIds as $roleId) {
            $insert->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function replaceActivePosition(
        int $userId,
        ?int $positionId,
        ?int $unitId,
        string $effectiveDate
    ): void {
        $close = $this->db->prepare(
            "UPDATE user_positions
             SET is_active = FALSE,
                 end_date = COALESCE(
                     end_date,
                     GREATEST(CAST(:effective_date AS DATE) - 1, start_date)
                 ),
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id
               AND is_active = TRUE"
        );
        $close->execute([
            'effective_date' => $effectiveDate,
            'user_id' => $userId,
        ]);

        if ($positionId === null || $unitId === null) {
            return;
        }

        $insert = $this->db->prepare(
            'INSERT INTO user_positions (
                user_id,
                position_id,
                unit_id,
                start_date,
                end_date,
                is_active,
                created_at,
                updated_at
             ) VALUES (
                :user_id,
                :position_id,
                :unit_id,
                CAST(:effective_date AS DATE),
                NULL,
                TRUE,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
             )'
        );
        $insert->execute([
            'user_id' => $userId,
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'effective_date' => $effectiveDate,
        ]);
    }

    public function insertAudit(
        int $actorUserId,
        int $targetUserId,
        array $before,
        array $after,
        string $reason
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
                'users',
                :record_id,
                'UPDATE',
                :actor_user_id,
                CAST(:old_data AS JSONB),
                CAST(:new_data AS JSONB),
                :reason,
                :ip_address,
                CURRENT_TIMESTAMP
             )"
        );
        $statement->execute([
            'record_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'old_data' => json_encode(
                $before,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'new_data' => json_encode(
                $after,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    private function bindSearchParameters(
        PDOStatement $statement,
        array $parameters
    ): void {
        foreach ($parameters as $key => $value) {
            if (in_array($key, ['limit', 'offset', 'unit_id'], true)) {
                $statement->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
            } elseif ($key === 'is_active') {
                $statement->bindValue(':' . $key, (bool) $value, PDO::PARAM_BOOL);
            } else {
                $statement->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
            }
        }
    }

    private function normalizeUserRow(array $row): array
    {
        foreach (['roles', 'permissions', 'positions'] as $jsonField) {
            if (array_key_exists($jsonField, $row)) {
                $row[$jsonField] = $this->decodeJsonArray($row[$jsonField]);
            }
        }

        foreach (
            [
                'user_id',
                'user_position_id',
                'position_id',
                'unit_id',
                'failed_login_attempts',
            ] as $integerField
        ) {
            if (array_key_exists($integerField, $row)) {
                $row[$integerField] = $row[$integerField] === null
                    ? null
                    : (int) $row[$integerField];
            }
        }

        if (array_key_exists('is_active', $row)) {
            $row['is_active'] = self::databaseBoolean($row['is_active']);
        }

        return $row;
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param list<int> $values
     * @return array{0:list<string>,1:array<string,int>}
     */
    private function integerListParameters(array $values, string $prefix): array
    {
        $placeholders = [];
        $parameters = [];

        foreach (array_values($values) as $index => $value) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $parameters[$key] = (int) $value;
        }

        return [$placeholders, $parameters];
    }

    private static function databaseBoolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true);
    }
}
