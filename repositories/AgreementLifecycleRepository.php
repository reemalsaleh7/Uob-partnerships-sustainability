<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AgreementLifecycleRepository
{
    private PDO $db;

    private const FIELDS = [
        'request_type', 'justification', 'activities_summary', 'achieved_value',
        'proposed_start_date', 'proposed_end_date', 'financial_amount',
        'financial_currency', 'financial_description', 'amendment_type',
        'amendment_reason', 'terms_to_amend', 'termination_reason',
        'proposed_termination_date', 'previous_initiatives',
    ];

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(int $agreementId, int $requestedBy, array $data): int
    {
        $columns = ['agreement_id', 'requested_by', 'status'];
        $values = [':agreement_id', ':requested_by', "'DRAFT'"];
        $params = [
            'agreement_id' => $agreementId,
            'requested_by' => $requestedBy,
        ];

        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $columns[] = $field;
            $values[] = ':' . $field;
            $params[$field] = $this->databaseValue($field, $data[$field]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO agreement_lifecycle_requests ('
            . implode(', ', $columns)
            . ') VALUES (' . implode(', ', $values) . ')
               RETURNING lifecycle_request_id'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function hasOpenRequest(int $agreementId, string $requestType): bool
    {
        $stmt = $this->db->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM agreement_lifecycle_requests
                WHERE agreement_id = :agreement_id
                  AND request_type = :request_type
                  AND status IN (\'DRAFT\', \'UNDER_REVIEW\', \'REVISION_REQUIRED\')
            )'
        );
        $stmt->execute([
            'agreement_id' => $agreementId,
            'request_type' => strtoupper($requestType),
        ]);
        return (bool) $stmt->fetchColumn();
    }

    public function update(int $requestId, array $data): void
    {
        $sets = [];
        $params = ['request_id' => $requestId];

        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = "{$field} = :{$field}";
            $params[$field] = $this->databaseValue($field, $data[$field]);
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $stmt = $this->db->prepare(
            'UPDATE agreement_lifecycle_requests
             SET ' . implode(', ', $sets) . '
             WHERE lifecycle_request_id = :request_id'
        );
        $stmt->execute($params);
    }

    public function findById(int $requestId, bool $forUpdate = false): ?array
    {
        $sql =
            'SELECT
                lr.*,
                a.title AS agreement_title,
                a.title_ar AS agreement_title_ar,
                a.status AS agreement_status,
                a.start_date AS agreement_start_date,
                a.end_date AS agreement_end_date,
                u.email AS requester_email,
                CONCAT_WS(\' \', u.first_name, u.last_name) AS requester_name
             FROM agreement_lifecycle_requests lr
             JOIN agreements a ON a.agreement_id = lr.agreement_id
             JOIN users u ON u.user_id = lr.requested_by
             WHERE lr.lifecycle_request_id = :request_id';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE OF lr';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['request_id' => $requestId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findVisibleToUser(int $userId, bool $isAdministrator): array
    {
        $where = $isAdministrator
            ? 'TRUE'
            : '(lr.requested_by = :requester_user_id
                OR lr.status IN (\'APPROVED\', \'REJECTED\')
                OR EXISTS (
                    SELECT 1
                    FROM workflow_instances wi
                    JOIN workflow_instance_steps wis
                      ON wis.workflow_instance_id = wi.workflow_instance_id
                    JOIN workflow_step_assignments wsa
                      ON wsa.workflow_instance_step_id = wis.instance_step_id
                    WHERE wi.entity_type = \'AGREEMENT_LIFECYCLE\'
                      AND wi.entity_id = lr.lifecycle_request_id
                      AND wi.status = \'IN_PROGRESS\'
                      AND wis.status = \'IN_PROGRESS\'
                      AND wsa.user_id = :reviewer_user_id
                      AND wsa.is_active = TRUE
                ))';

        $stmt = $this->db->prepare(
            'SELECT
                lr.*,
                a.title AS agreement_title,
                a.status AS agreement_status
             FROM agreement_lifecycle_requests lr
             JOIN agreements a ON a.agreement_id = lr.agreement_id
             WHERE ' . $where . '
             ORDER BY lr.updated_at DESC, lr.lifecycle_request_id DESC'
        );
        $stmt->execute($isAdministrator ? [] : [
            'requester_user_id' => $userId,
            'reviewer_user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function isVisibleToUser(
        array $request,
        int $userId,
        bool $isAdministrator
    ): bool {
        if ($isAdministrator || (int) $request['requested_by'] === $userId) {
            return true;
        }

        if (in_array($request['status'], ['APPROVED', 'REJECTED'], true)) {
            return true;
        }

        $stmt = $this->db->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM workflow_instances wi
                JOIN workflow_instance_steps wis
                  ON wis.workflow_instance_id = wi.workflow_instance_id
                JOIN workflow_step_assignments wsa
                  ON wsa.workflow_instance_step_id = wis.instance_step_id
                WHERE wi.entity_type = \'AGREEMENT_LIFECYCLE\'
                  AND wi.entity_id = :request_id
                  AND wi.status = \'IN_PROGRESS\'
                  AND wis.status = \'IN_PROGRESS\'
                  AND wsa.user_id = :user_id
                  AND wsa.is_active = TRUE
            )'
        );
        $stmt->execute([
            'request_id' => $request['lifecycle_request_id'],
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function changeStatus(
        int $requestId,
        string $status,
        ?int $decidedBy = null,
        ?string $comments = null
    ): void {
        $terminal = in_array($status, ['APPROVED', 'REJECTED'], true);
        $stmt = $this->db->prepare(
            'UPDATE agreement_lifecycle_requests
             SET status = :status,
                 updated_at = NOW(),
                 decided_at = CASE WHEN :terminal_at THEN NOW() ELSE decided_at END,
                 decided_by = CASE WHEN :terminal_by THEN :decided_by ELSE decided_by END,
                 decision_comments = CASE WHEN :terminal_comments THEN :comments ELSE decision_comments END,
                 applied_at = CASE WHEN :applied_status = \'APPROVED\' THEN NOW() ELSE applied_at END
             WHERE lifecycle_request_id = :request_id'
        );
        $stmt->execute([
            'status' => $status,
            'terminal_at' => $terminal ? 'true' : 'false',
            'terminal_by' => $terminal ? 'true' : 'false',
            'terminal_comments' => $terminal ? 'true' : 'false',
            'applied_status' => $status,
            'decided_by' => $decidedBy,
            'comments' => $comments,
            'request_id' => $requestId,
        ]);
    }

    public function attachWorkflow(int $requestId, int $instanceId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE agreement_lifecycle_requests
             SET workflow_instance_id = :instance_id,
                 status = \'UNDER_REVIEW\',
                 submitted_at = COALESCE(submitted_at, NOW()),
                 updated_at = NOW()
             WHERE lifecycle_request_id = :request_id'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'instance_id' => $instanceId,
        ]);
    }

    public function createVersion(
        int $requestId,
        array $snapshot,
        int $createdBy,
        string $summary
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO agreement_lifecycle_request_versions (
                lifecycle_request_id, version_number, request_snapshot,
                change_summary, created_by
             ) VALUES (
                :request_id,
                COALESCE((
                    SELECT MAX(version_number) + 1
                    FROM agreement_lifecycle_request_versions
                    WHERE lifecycle_request_id = :version_request_id
                ), 1),
                CAST(:snapshot AS JSONB), :summary, :created_by
             ) RETURNING version_number'
        );
        $stmt->execute([
            'request_id' => $requestId,
            'version_request_id' => $requestId,
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'summary' => $summary,
            'created_by' => $createdBy,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findVersions(int $requestId): array
    {
        $stmt = $this->db->prepare(
            'SELECT version_number, change_summary, created_by, created_at
             FROM agreement_lifecycle_request_versions
             WHERE lifecycle_request_id = :request_id
             ORDER BY version_number DESC'
        );
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetchAll();
    }

    public function latestVersionNumber(int $requestId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(version_number), 0)
             FROM agreement_lifecycle_request_versions
             WHERE lifecycle_request_id = :request_id'
        );
        $stmt->execute(['request_id' => $requestId]);
        return (int) $stmt->fetchColumn();
    }

    public function recordTermination(int $agreementId, int $performedBy, string $reason): void
    {
        $stmt = $this->db->prepare(
            'UPDATE agreements SET status = \'TERMINATED\' WHERE agreement_id = :agreement_id'
        );
        $stmt->execute(['agreement_id' => $agreementId]);

        $stmt = $this->db->prepare(
            'INSERT INTO agreement_actions (agreement_id, action_type, reason, performed_by)
             VALUES (:agreement_id, \'TERMINATION_APPROVED\', :reason, :performed_by)'
        );
        $stmt->execute([
            'agreement_id' => $agreementId,
            'reason' => $reason,
            'performed_by' => $performedBy,
        ]);
    }

    private function databaseValue(string $field, mixed $value): mixed
    {
        if ($field === 'previous_initiatives') {
            if ($value === null || $value === '') {
                return null;
            }
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        return $value === '' ? null : $value;
    }
}
