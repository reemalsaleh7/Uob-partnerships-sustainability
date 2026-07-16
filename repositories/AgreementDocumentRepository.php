<?php
require_once __DIR__ . '/../config/database.php';

class AgreementDocumentRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $agreementId, array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO agreement_documents (
                agreement_id,
                file_name,
                file_path,
                document_type,
                uploaded_by,
                uploaded_at
            ) VALUES (
                :agreement_id,
                :file_name,
                :file_path,
                :document_type,
                :uploaded_by,
                NOW()
            ) RETURNING document_id
        ");

        $stmt->execute([
            'agreement_id' => $agreementId,
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'document_type' => $data['document_type'] ?? 'GENERAL',
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByAgreement(int $agreementId): array {
        $stmt = $this->db->prepare('SELECT * FROM agreement_documents WHERE agreement_id = :agreement_id ORDER BY uploaded_at DESC');
        $stmt->execute(['agreement_id' => $agreementId]);
        return $stmt->fetchAll();
    }

    public function delete(int $documentId): void {
        $stmt = $this->db->prepare('DELETE FROM agreement_documents WHERE document_id = :document_id');
        $stmt->execute(['document_id' => $documentId]);
    }
}
