<?php
require_once __DIR__ . '/../config/database.php';

class AgreementVersionRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $agreementId, array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO agreement_versions (
                agreement_id,
                version_number,
                document_path,
                change_summary,
                created_by,
                created_at
            ) VALUES (
                :agreement_id,
                :version_number,
                :document_path,
                :change_summary,
                :created_by,
                NOW()
            ) RETURNING version_id
        ");

        $stmt->execute([
            'agreement_id' => $agreementId,
            'version_number' => $data['version_number'],
            'document_path' => $data['document_path'] ?? null,
            'change_summary' => $data['change_summary'] ?? null,
            'created_by' => $data['created_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByAgreement(int $agreementId): array {
        $stmt = $this->db->prepare('SELECT * FROM agreement_versions WHERE agreement_id = :agreement_id ORDER BY version_number DESC');
        $stmt->execute(['agreement_id' => $agreementId]);
        return $stmt->fetchAll();
    }
}
