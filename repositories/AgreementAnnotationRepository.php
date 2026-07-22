<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class AgreementAnnotationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agreement_annotations (
                agreement_id, agreement_version_id, author_user_id,
                visibility, anchor_type, field_key, selected_text,
                selection_start, selection_end, comment_text
             ) VALUES (
                :agreement_id, :agreement_version_id, :author_user_id,
                :visibility, :anchor_type, :field_key, :selected_text,
                :selection_start, :selection_end, :comment_text
             )
             RETURNING annotation_id'
        );
        $stmt->execute([
            'agreement_id' => $data['agreement_id'],
            'agreement_version_id' => $data['agreement_version_id'],
            'author_user_id' => $data['author_user_id'],
            'visibility' => $data['visibility'],
            'anchor_type' => $data['anchor_type'],
            'field_key' => $data['field_key'],
            'selected_text' => $data['selected_text'],
            'selection_start' => $data['selection_start'],
            'selection_end' => $data['selection_end'],
            'comment_text' => $data['comment_text'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findVisibleForAgreement(int $agreementId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                aa.*,
                av.version_number,
                NULLIF(
                    TRIM(CONCAT(author.first_name, \' \', author.last_name)),
                    \'\'
                ) AS author_name,
                author.email AS author_email,
                NULLIF(
                    TRIM(CONCAT(resolver.first_name, \' \', resolver.last_name)),
                    \'\'
                ) AS resolver_name,
                resolver.email AS resolver_email
             FROM agreement_annotations aa
             JOIN agreement_versions av
               ON av.version_id = aa.agreement_version_id
             JOIN users author ON author.user_id = aa.author_user_id
             LEFT JOIN users resolver ON resolver.user_id = aa.resolved_by
             WHERE aa.agreement_id = :agreement_id
               AND (
                   aa.visibility = \'SHARED\'
                   OR aa.author_user_id = :user_id
               )
             ORDER BY
                CASE aa.status WHEN \'OPEN\' THEN 0 ELSE 1 END,
                aa.created_at DESC,
                aa.annotation_id DESC'
        );
        $stmt->execute([
            'agreement_id' => $agreementId,
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function findById(int $annotationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM agreement_annotations
             WHERE annotation_id = :annotation_id
             LIMIT 1'
        );
        $stmt->execute(['annotation_id' => $annotationId]);
        $annotation = $stmt->fetch();

        return $annotation ?: null;
    }

    public function resolve(int $annotationId, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE agreement_annotations
             SET status = \'RESOLVED\',
                 resolved_by = :user_id,
                 resolved_at = NOW(),
                 updated_at = NOW()
             WHERE annotation_id = :annotation_id
               AND status = \'OPEN\''
        );
        $stmt->execute([
            'annotation_id' => $annotationId,
            'user_id' => $userId,
        ]);
    }

    public function delete(int $annotationId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM agreement_annotations
             WHERE annotation_id = :annotation_id'
        );
        $stmt->execute(['annotation_id' => $annotationId]);
    }

    public function lastViewedVersion(int $agreementId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT av.version_id, av.version_number, auv.last_viewed_at
             FROM agreement_user_views auv
             JOIN agreement_versions av
               ON av.version_id = auv.last_viewed_version_id
             WHERE auv.agreement_id = :agreement_id
               AND auv.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'agreement_id' => $agreementId,
            'user_id' => $userId,
        ]);
        $view = $stmt->fetch();

        return $view ?: null;
    }

    public function markViewed(int $agreementId, int $userId, int $versionId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO agreement_user_views (
                agreement_id, user_id, last_viewed_version_id, last_viewed_at
             ) VALUES (
                :agreement_id, :user_id, :version_id, NOW()
             )
             ON CONFLICT (agreement_id, user_id)
             DO UPDATE SET
                last_viewed_version_id = EXCLUDED.last_viewed_version_id,
                last_viewed_at = EXCLUDED.last_viewed_at'
        );
        $stmt->execute([
            'agreement_id' => $agreementId,
            'user_id' => $userId,
            'version_id' => $versionId,
        ]);
    }
}
