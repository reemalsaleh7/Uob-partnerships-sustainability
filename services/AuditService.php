<?php
require_once __DIR__ . '/../repositories/AuditRepository.php';

class AuditService {
    private AuditRepository $auditRepo;

    public function __construct() {
        $this->auditRepo = new AuditRepository();
    }

    public function logLogin(int $userId, array $context = []): void {
        $this->write('users', $userId, 'LOGIN', $userId, null, $context);
    }

    public function logLogout(int $userId, array $context = []): void {
        $this->write('users', $userId, 'LOGOUT', $userId, null, $context);
    }

    public function write(string $tableName, int $recordId, string $action, ?int $userId, mixed $oldData = null, mixed $newData = null): void {
        $this->auditRepo->create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'action' => $action,
            'user_id' => $userId,
            'old_data' => $oldData,
            'new_data' => $newData,
            'reason' => null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
