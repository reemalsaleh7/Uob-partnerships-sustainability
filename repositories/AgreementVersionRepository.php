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
                agreement_snapshot,
                created_by,
                created_at
            ) VALUES (
                :agreement_id,
                :version_number,
                :document_path,
                :change_summary,
                CAST(:agreement_snapshot AS JSONB),
                :created_by,
                NOW()
            ) RETURNING version_id
        ");

        $stmt->execute([
            'agreement_id' => $agreementId,
            'version_number' => $data['version_number'],
            'document_path' => $data['document_path'] ?? null,
            'change_summary' => $data['change_summary'] ?? null,
            'agreement_snapshot' => json_encode($data['agreement_snapshot'], JSON_THROW_ON_ERROR),
            'created_by' => $data['created_by'],
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findByAgreement(int $agreementId): array {
        $stmt = $this->db->prepare('SELECT * FROM agreement_versions WHERE agreement_id = :agreement_id ORDER BY version_number DESC');
        $stmt->execute(['agreement_id' => $agreementId]);
        return array_map(fn(array $version): array => $this->hydrateSnapshot($version), $stmt->fetchAll());
    }

    public function findByAgreementAndVersion(int $agreementId, int $versionNumber): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM agreement_versions WHERE agreement_id = :agreement_id AND version_number = :version_number LIMIT 1'
        );
        $stmt->execute(['agreement_id' => $agreementId, 'version_number' => $versionNumber]);
        $version = $stmt->fetch();
        return $version ? $this->hydrateSnapshot($version) : null;
    }

    public function findLatest(int $agreementId): ?array {
        $stmt = $this->db->prepare('
            SELECT *
            FROM agreement_versions
            WHERE agreement_id = :agreement_id
            ORDER BY version_number DESC
            LIMIT 1
        ');
        $stmt->execute(['agreement_id' => $agreementId]);
        $version = $stmt->fetch();

        return $version ? $this->hydrateSnapshot($version) : null;
    }

    public function findLatestVersionNumber(
    int $agreementId
): int {
    $stmt = $this->db->prepare(
        'SELECT COALESCE(
            MAX(version_number),
            0
         )
         FROM agreement_versions
         WHERE agreement_id = :agreement_id'
    );

    $stmt->execute([
        'agreement_id' => $agreementId,
    ]);

    return (int) $stmt->fetchColumn();
}
    private function hydrateSnapshot(array $version): array {
        if (is_string($version['agreement_snapshot'] ?? null)) {
            $version['agreement_snapshot'] = json_decode($version['agreement_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        }
        return $version;
    }
}
