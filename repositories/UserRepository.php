<?php
require_once __DIR__ . '/../config/database.php';

class UserRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function beginTransaction(): void {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    public function commit(): void {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    public function rollBack(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->db->prepare(
            "SELECT user_id, first_name, last_name, email, password_hash, failed_login_attempts, locked_until, last_login, is_active FROM users WHERE email = :email LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmailForUpdate(string $email): ?array {
        $stmt = $this->db->prepare(
            "SELECT user_id, first_name, last_name, email, password_hash, failed_login_attempts, locked_until, last_login, is_active FROM users WHERE email = :email LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByUniversityId(string $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT user_id, first_name, last_name, email, password_hash FROM users WHERE university_id = :user_id LIMIT 1"
        );
        $stmt->execute(['user_id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findProfileById(int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT user_id, university_id, first_name, last_name,
                    email, phone, last_login, password_changed_at,
                    is_active, created_at
             FROM users
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare(
            "UPDATE users SET last_login = NOW() WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function recordFailedLogin(int $userId): void {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET failed_login_attempts = failed_login_attempts + 1,
                 locked_until = CASE
                     WHEN failed_login_attempts + 1 >= 5
                     THEN NOW() + INTERVAL '15 minutes'
                     ELSE locked_until
                 END
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function resetFailedAttempts(int $userId): void {
        $stmt = $this->db->prepare(
            "UPDATE users
             SET failed_login_attempts = 0,
                 locked_until = NULL
             WHERE user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function isActive(int $userId): bool {
        $stmt = $this->db->prepare(
            'SELECT is_active FROM users WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return in_array(
            $stmt->fetchColumn(),
            [true, 1, '1', 't', 'true'],
            true
        );
    }

    public function getRoles(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT r.role_name
            FROM roles r
            JOIN user_roles ur ON ur.role_id = r.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'role_name');
    }

    public function getPermissions(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.permission_code
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.permission_id
            JOIN user_roles ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'permission_code');
    }

    public function getActivePositions(int $userId): array {
        $stmt = $this->db->prepare("
            SELECT
                p.name AS position,
                ou.name AS organizational_unit
            FROM user_positions up
            JOIN positions p ON p.position_id = up.position_id
            JOIN organizational_units ou ON ou.unit_id = up.unit_id
            WHERE up.user_id = :user_id
              AND up.is_active = TRUE
              AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function createLegacyHandoff(
        int $userId,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): void {
        $delete = $this->db->prepare(
            'DELETE FROM workspace_legacy_handoffs
             WHERE user_id = :user_id
               AND (used_at IS NOT NULL OR expires_at < NOW())'
        );
        $delete->execute(['user_id' => $userId]);

        $insert = $this->db->prepare(
            'INSERT INTO workspace_legacy_handoffs (
                user_id, token_hash, expires_at
             ) VALUES (
                :user_id, :token_hash, :expires_at
             )'
        );
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }
}
