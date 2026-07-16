<?php
require_once __DIR__ . '/../config/database.php';

class AgreementRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO agreements (
                title,
                agreement_type,
                description,
                partner_id,
                created_by,
                status,
                created_at
            ) VALUES (
                :title,
                :agreement_type,
                :description,
                :partner_id,
                :created_by,
                :status,
                NOW()
            ) RETURNING agreement_id
        ");

        $stmt->execute([
            'title' => $data['title'],
            'agreement_type' => $data['agreement_type'] ?? null,
            'description' => $data['description'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
            'created_by' => $data['created_by'],
            'status' => $data['status'] ?? 'DRAFT',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $agreementId, array $data): void {
        $fields = [];
        $params = ['agreement_id' => $agreementId];

        foreach (['title', 'agreement_type', 'description', 'partner_id', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE agreements SET ' . implode(', ', $fields) . ' WHERE agreement_id = :agreement_id'
        );
        $stmt->execute($params);
    }

    public function delete(int $agreementId): void {
        $stmt = $this->db->prepare('DELETE FROM agreements WHERE agreement_id = :agreement_id');
        $stmt->execute(['agreement_id' => $agreementId]);
    }

    public function findById(int $agreementId): ?array {
        $stmt = $this->db->prepare('SELECT * FROM agreements WHERE agreement_id = :agreement_id LIMIT 1');
        $stmt->execute(['agreement_id' => $agreementId]);
        $agreement = $stmt->fetch();
        return $agreement ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->query('SELECT * FROM agreements ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function findByStatus(string $status): array {
        $stmt = $this->db->prepare('SELECT * FROM agreements WHERE status = :status ORDER BY created_at DESC');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function changeStatus(int $agreementId, string $status): void {
        $stmt = $this->db->prepare('UPDATE agreements SET status = :status WHERE agreement_id = :agreement_id');
        $stmt->execute(['status' => $status, 'agreement_id' => $agreementId]);
    }
}
