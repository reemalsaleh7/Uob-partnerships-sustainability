<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AgreementOperationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function lockAgreement(int $agreementId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM agreements WHERE agreement_id = :agreement_id FOR UPDATE'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        $agreement = $statement->fetch();
        return $agreement ?: null;
    }

    public function findSigningRecord(int $agreementId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT
                sr.*,
                ad.file_name AS signed_document_name,
                ad.document_type AS signed_document_type,
                ad.mime_type AS signed_document_mime_type,
                NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\')
                    AS finalized_by_name,
                u.email AS finalized_by_email
             FROM agreement_signing_records sr
             JOIN agreement_documents ad
               ON ad.document_id = sr.signed_document_id
             LEFT JOIN users u ON u.user_id = sr.finalized_by
             WHERE sr.agreement_id = :agreement_id
             LIMIT 1'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        $record = $statement->fetch();
        if (!$record) {
            return null;
        }
        if (is_string($record['signatory_snapshot'] ?? null)) {
            $record['signatory_snapshot'] = json_decode(
                $record['signatory_snapshot'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }
        return $record;
    }

    public function findEligibleSignedDocuments(int $agreementId): array
    {
        $statement = $this->db->prepare(
            'SELECT document_id, file_name, document_type, mime_type,
                    file_size_bytes, uploaded_at
             FROM agreement_documents
             WHERE agreement_id = :agreement_id
               AND storage_key IS NOT NULL
               AND document_type = \'SIGNED_AGREEMENT\'
             ORDER BY uploaded_at DESC, document_id DESC'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        return $statement->fetchAll();
    }

    public function findDocumentForAgreement(
        int $agreementId,
        int $documentId
    ): ?array {
        $statement = $this->db->prepare(
            'SELECT *
             FROM agreement_documents
             WHERE agreement_id = :agreement_id
               AND document_id = :document_id
               AND storage_key IS NOT NULL
             LIMIT 1'
        );
        $statement->execute([
            'agreement_id' => $agreementId,
            'document_id' => $documentId,
        ]);
        $document = $statement->fetch();
        return $document ?: null;
    }

    public function createSigningRecord(int $agreementId, array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO agreement_signing_records (
                agreement_id, signed_document_id, signing_date,
                effective_date, expiry_date, venue,
                public_announcement_url, ceremony_notes,
                signatory_snapshot, finalized_by, finalized_at
             ) VALUES (
                :agreement_id, :signed_document_id, :signing_date,
                :effective_date, :expiry_date, :venue,
                :public_announcement_url, :ceremony_notes,
                CAST(:signatory_snapshot AS JSONB), :finalized_by, NOW()
             )
             RETURNING signing_record_id'
        );
        $statement->execute([
            'agreement_id' => $agreementId,
            'signed_document_id' => $data['signed_document_id'],
            'signing_date' => $data['signing_date'],
            'effective_date' => $data['effective_date'],
            'expiry_date' => $data['expiry_date'],
            'venue' => $data['venue'],
            'public_announcement_url' => $data['public_announcement_url'],
            'ceremony_notes' => $data['ceremony_notes'],
            'signatory_snapshot' => json_encode(
                $data['signatories'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'finalized_by' => $data['finalized_by'],
        ]);
        return (int) $statement->fetchColumn();
    }

    public function transitionStatus(
        int $agreementId,
        string $fromStatus,
        string $toStatus,
        DateTimeImmutable $asOf,
        string $reason,
        ?int $performedBy
    ): bool {
        $timestampColumn = $toStatus === 'ACTIVE'
            ? 'activated_at'
            : 'expired_at';
        $statement = $this->db->prepare(
            "UPDATE agreements
             SET status = :to_status,
                 {$timestampColumn} = COALESCE({$timestampColumn}, NOW())
             WHERE agreement_id = :agreement_id
               AND status = :from_status"
        );
        $statement->execute([
            'agreement_id' => $agreementId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
        if ($statement->rowCount() !== 1) {
            return false;
        }

        $event = $this->db->prepare(
            'INSERT INTO agreement_status_events (
                agreement_id, from_status, to_status, effective_as_of,
                reason, performed_by, created_at
             ) VALUES (
                :agreement_id, :from_status, :to_status, :effective_as_of,
                :reason, :performed_by, NOW()
             )
             ON CONFLICT (agreement_id, to_status) DO NOTHING'
        );
        $event->execute([
            'agreement_id' => $agreementId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'effective_as_of' => $asOf->format('Y-m-d'),
            'reason' => $reason,
            'performed_by' => $performedBy,
        ]);
        return true;
    }

    public function findTransitionCandidates(DateTimeImmutable $asOf): array
    {
        $statement = $this->db->prepare(
            'SELECT
                a.agreement_id, a.title, a.status,
                sr.effective_date, sr.expiry_date,
                CASE
                    WHEN a.status = \'APPROVED\'
                         AND sr.expiry_date < :as_of_expired
                    THEN \'EXPIRED\'
                    WHEN a.status = \'APPROVED\'
                         AND sr.effective_date <= :as_of_active
                    THEN \'ACTIVE\'
                    WHEN a.status = \'ACTIVE\'
                         AND sr.expiry_date < :as_of_active_expired
                    THEN \'EXPIRED\'
                END AS target_status
             FROM agreements a
             JOIN agreement_signing_records sr
               ON sr.agreement_id = a.agreement_id
             WHERE (
                    a.status = \'APPROVED\'
                    AND sr.effective_date <= :as_of_approved
                  )
                OR (
                    a.status = \'ACTIVE\'
                    AND sr.expiry_date < :as_of_current_active
                )
             ORDER BY a.agreement_id'
        );
        $date = $asOf->format('Y-m-d');
        $statement->execute([
            'as_of_expired' => $date,
            'as_of_active' => $date,
            'as_of_active_expired' => $date,
            'as_of_approved' => $date,
            'as_of_current_active' => $date,
        ]);
        return $statement->fetchAll();
    }

    public function findStatusEvents(int $agreementId): array
    {
        $statement = $this->db->prepare(
            'SELECT se.*,
                    NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\')
                        AS performed_by_name,
                    u.email AS performed_by_email
             FROM agreement_status_events se
             LEFT JOIN users u ON u.user_id = se.performed_by
             WHERE se.agreement_id = :agreement_id
             ORDER BY se.created_at, se.status_event_id'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        return $statement->fetchAll();
    }
}
