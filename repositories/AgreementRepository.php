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
                created_by,
                status,
                created_at
            ) VALUES (
                :title,
                :agreement_type,
                :description,
                :created_by,
                :status,
                NOW()
            ) RETURNING agreement_id
        ");

        $stmt->execute([
            'title' => $data['title'],
            'agreement_type' => $data['agreement_type'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => $data['created_by'],
            'status' => $data['status'] ?? 'DRAFT',
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $agreementId, array $data): void {
        $fields = [];
        $params = ['agreement_id' => $agreementId];

        foreach (['title', 'agreement_type', 'description', 'status'] as $field) {
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
        $stmt = $this->db->prepare('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            WHERE a.agreement_id = :agreement_id
            ORDER BY ap.partner_id
            LIMIT 1
        ');
        $stmt->execute(['agreement_id' => $agreementId]);
        $agreement = $stmt->fetch();
        return $agreement ?: null;
    }

    public function findAll(): array {
        $stmt = $this->db->query('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            ORDER BY a.created_at DESC, ap.partner_id
        ');
        return $stmt->fetchAll();
    }

    public function findByStatus(string $status): array {
        $stmt = $this->db->prepare('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            WHERE a.status = :status
            ORDER BY a.created_at DESC, ap.partner_id
        ');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function changeStatus(int $agreementId, string $status): void {
        $stmt = $this->db->prepare('UPDATE agreements SET status = :status WHERE agreement_id = :agreement_id');
        $stmt->execute(['status' => $status, 'agreement_id' => $agreementId]);
    }

    public function replacePartners(int $agreementId, array $partnerIds): void {
        $delete = $this->db->prepare('DELETE FROM agreement_partners WHERE agreement_id = :agreement_id');
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare(
            'INSERT INTO agreement_partners (agreement_id, partner_id) VALUES (:agreement_id, :partner_id)'
        );
        foreach (array_unique(array_map('intval', $partnerIds)) as $partnerId) {
            $insert->execute(['agreement_id' => $agreementId, 'partner_id' => $partnerId]);
        }
    }
}
