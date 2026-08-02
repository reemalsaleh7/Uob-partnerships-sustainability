<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeAccessPolicy
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function canCreate(int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                LEFT JOIN role_permissions role_permission
                  ON role_permission.role_id = role.role_id
                LEFT JOIN permissions permission
                  ON permission.permission_id =
                     role_permission.permission_id
                WHERE user_role.user_id = :role_user_id
                  AND (
                        permission.permission_code =
                            'CREATE_INITIATIVE'
                        OR LOWER(role.role_name) IN (
                            'student',
                            'faculty',
                            'initiative creator',
                            'department head',
                            'dean',
                            'vice president',
                            'vice president office',
                            'system administrator'
                        )
                  )

                UNION ALL

                SELECT 1
                FROM user_positions user_position
                JOIN positions position
                  ON position.position_id =
                     user_position.position_id
                WHERE user_position.user_id =
                    :position_user_id
                  AND user_position.is_active = TRUE
                  AND (
                        user_position.end_date IS NULL
                        OR user_position.end_date >= CURRENT_DATE
                  )
                  AND (
                        LOWER(position.name) IN (
                            'student',
                            'faculty member',
                            'department head',
                            'head of department',
                            'dean',
                            'vice president',
                            'vice president office',
                            'vice president office member'
                        )
                        OR LOWER(position.name)
                            LIKE '%professor%'
                        OR LOWER(position.name)
                            LIKE '%lecturer%'
                  )
            )"
        );

        $statement->execute([
            'role_user_id' => $userId,
            'position_user_id' => $userId,
        ]);

        return in_array(
            $statement->fetchColumn(),
            [true, 1, '1', 't', 'true'],
            true
        );
    }
}