<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeAccessPolicy
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function canCreate(int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN role_permissions role_permission
                  ON role_permission.role_id = user_role.role_id
                JOIN permissions permission
                  ON permission.permission_id =
                     role_permission.permission_id
                WHERE user_role.user_id = :user_id
                  AND permission.permission_code =
                      'CREATE_INITIATIVE'
            )"
        );
        $statement->execute(['user_id' => $userId]);

        return in_array(
            $statement->fetchColumn(),
            [true, 1, '1', 't', 'true'],
            true
        );
    }
}
