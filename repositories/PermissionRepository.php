<?php
require_once __DIR__ . '/../config/database.php';

class PermissionRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function getPermissionsForUser(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.permission_code
            FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p ON p.permission_id = rp.permission_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'permission_code');
    }

    public function hasActivePositionForUnit(int $userId, int $unitId): bool {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM user_positions up
            WHERE up.user_id = :user_id
              AND up.unit_id = :unit_id
              AND up.is_active = TRUE
              AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
            LIMIT 1
        ");
        $stmt->execute([
            'user_id' => $userId,
            'unit_id' => $unitId,
        ]);
        return (bool) $stmt->fetch();
    }
}
