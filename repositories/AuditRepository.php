<?php
require_once __DIR__ . '/../config/database.php';

class AuditRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (
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
                :table_name,
                :record_id,
                :action,
                :user_id,
                CAST(:old_data AS JSONB),
                CAST(:new_data AS JSONB),
                :reason,
                :ip_address,
                NOW()
            ) RETURNING audit_id
        ");

        $stmt->execute([
            'table_name' => $data['table_name'],
            'record_id' => $data['record_id'],
            'action' => $data['action'],
            'user_id' => $data['user_id'] ?? null,
            'old_data' => $data['old_data'] ? json_encode($data['old_data']) : null,
            'new_data' => $data['new_data'] ? json_encode($data['new_data']) : null,
            'reason' => $data['reason'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
        ]);

        return (int) $stmt->fetchColumn();
    }
}
