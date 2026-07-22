<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AgreementLifecycleDocumentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(int $requestId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agreement_lifecycle_request_documents (
                lifecycle_request_id, lifecycle_request_version_id,
                file_name, storage_key, mime_type, file_size_bytes,
                sha256_checksum, document_type, uploaded_by
             ) VALUES (
                :request_id, :version_id, :file_name, :storage_key,
                :mime_type, :file_size_bytes, :sha256_checksum,
                :document_type, :uploaded_by
             ) RETURNING lifecycle_request_document_id'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'version_id' => $data['lifecycle_request_version_id'],
            'file_name' => $data['file_name'],
            'storage_key' => $data['storage_key'],
            'mime_type' => $data['mime_type'],
            'file_size_bytes' => $data['file_size_bytes'],
            'sha256_checksum' => $data['sha256_checksum'],
            'document_type' => $data['document_type'],
            'uploaded_by' => $data['uploaded_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByRequest(int $requestId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.lifecycle_request_document_id,
                d.lifecycle_request_id,
                d.lifecycle_request_version_id,
                v.version_number,
                d.file_name,
                d.storage_key,
                d.mime_type,
                d.file_size_bytes,
                d.sha256_checksum,
                d.document_type,
                d.uploaded_by,
                d.uploaded_at,
                NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\')
                    AS uploader_name,
                u.email AS uploader_email
             FROM agreement_lifecycle_request_documents d
             LEFT JOIN agreement_lifecycle_request_versions v
               ON v.lifecycle_request_version_id = d.lifecycle_request_version_id
             LEFT JOIN users u ON u.user_id = d.uploaded_by
             WHERE d.lifecycle_request_id = :request_id
             ORDER BY d.uploaded_at DESC,
                      d.lifecycle_request_document_id DESC'
        );
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetchAll();
    }

    public function findById(int $documentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                d.*,
                v.version_number,
                NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\')
                    AS uploader_name,
                u.email AS uploader_email
             FROM agreement_lifecycle_request_documents d
             LEFT JOIN agreement_lifecycle_request_versions v
               ON v.lifecycle_request_version_id = d.lifecycle_request_version_id
             LEFT JOIN users u ON u.user_id = d.uploaded_by
             WHERE d.lifecycle_request_document_id = :document_id'
        );
        $stmt->execute(['document_id' => $documentId]);
        $document = $stmt->fetch();
        return $document ?: null;
    }

    public function latestVersion(int $requestId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT lifecycle_request_version_id, version_number
             FROM agreement_lifecycle_request_versions
             WHERE lifecycle_request_id = :request_id
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $stmt->execute(['request_id' => $requestId]);
        $version = $stmt->fetch();
        return $version ?: null;
    }

    public function delete(int $documentId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM agreement_lifecycle_request_documents
             WHERE lifecycle_request_document_id = :document_id'
        );
        $stmt->execute(['document_id' => $documentId]);
    }
}
