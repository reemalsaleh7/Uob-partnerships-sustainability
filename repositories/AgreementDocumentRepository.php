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
                agreement_version_id,
                file_name,
                file_path,
                storage_key,
                mime_type,
                file_size_bytes,
                sha256_checksum,
                document_type,
                uploaded_by,
                uploaded_at
            ) VALUES (
                :agreement_id,
                :agreement_version_id,
                :file_name,
                NULL,
                :storage_key,
                :mime_type,
                :file_size_bytes,
                :sha256_checksum,
                :document_type,
                :uploaded_by,
                NOW()
            ) RETURNING document_id
        ");

        $stmt->execute([
            'agreement_id' => $agreementId,
            'agreement_version_id' => $data['agreement_version_id'],
            'file_name' => $data['file_name'],
            'storage_key' => $data['storage_key'],
            'mime_type' => $data['mime_type'],
            'file_size_bytes' => $data['file_size_bytes'],
            'sha256_checksum' => $data['sha256_checksum'],
            'document_type' => $data['document_type'] ?? 'GENERAL',
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByAgreement(int $agreementId): array {
        $stmt = $this->db->prepare('
            SELECT
                ad.document_id,
                ad.agreement_id,
                ad.agreement_version_id,
                av.version_number,
                ad.file_name,
                ad.document_type,
                ad.storage_key,
                ad.mime_type,
                ad.file_size_bytes,
                ad.sha256_checksum,
                ad.uploaded_by,
                ad.uploaded_at,
                CASE
                    WHEN ad.storage_key IS NOT NULL THEN TRUE
                    ELSE FALSE
                END AS has_stored_file,
                NULLIF(
                    TRIM(CONCAT(u.first_name, \' \', u.last_name)),
                    \'\'
                ) AS uploader_name,
                u.email AS uploader_email
            FROM agreement_documents ad
            LEFT JOIN agreement_versions av
                ON av.version_id = ad.agreement_version_id
            LEFT JOIN users u
                ON u.user_id = ad.uploaded_by
            WHERE ad.agreement_id = :agreement_id
            ORDER BY ad.uploaded_at DESC, ad.document_id DESC
        ');
        $stmt->execute(['agreement_id' => $agreementId]);
        return $stmt->fetchAll();
    }

    public function findById(int $documentId): ?array {
        $stmt = $this->db->prepare('
            SELECT
                ad.*,
                av.version_number,
                NULLIF(
                    TRIM(CONCAT(u.first_name, \' \', u.last_name)),
                    \'\'
                ) AS uploader_name,
                u.email AS uploader_email
            FROM agreement_documents ad
            LEFT JOIN agreement_versions av
                ON av.version_id = ad.agreement_version_id
            LEFT JOIN users u
                ON u.user_id = ad.uploaded_by
            WHERE ad.document_id = :document_id
            LIMIT 1
        ');
        $stmt->execute(['document_id' => $documentId]);
        $document = $stmt->fetch();
        return $document ?: null;
    }

    public function delete(int $documentId): void {
        $stmt = $this->db->prepare('DELETE FROM agreement_documents WHERE document_id = :document_id');
        $stmt->execute(['document_id' => $documentId]);
    }

    public function isFinalizedSigningDocument(int $documentId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM agreement_signing_records
             WHERE signed_document_id = :document_id
             LIMIT 1'
        );
        $stmt->execute(['document_id' => $documentId]);
        return (bool) $stmt->fetchColumn();
    }
}
