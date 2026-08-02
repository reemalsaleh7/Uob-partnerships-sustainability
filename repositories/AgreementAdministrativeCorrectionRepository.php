<?php
require_once __DIR__ . '/../config/database.php';

class AgreementAdministrativeCorrectionRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(
        int $agreementId,
        int $versionId,
        int $correctedBy,
        string $reason
    ): int {
        $statement = $this->db->prepare('
            INSERT INTO agreement_administrative_corrections (
                agreement_id, version_id, corrected_by, reason
            ) VALUES (
                :agreement_id, :version_id, :corrected_by, :reason
            )
            RETURNING correction_id
        ');
        $statement->execute([
            'agreement_id' => $agreementId,
            'version_id' => $versionId,
            'corrected_by' => $correctedBy,
            'reason' => $reason,
        ]);

        return (int) $statement->fetchColumn();
    }
}
