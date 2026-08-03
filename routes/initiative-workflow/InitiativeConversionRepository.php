<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeConversionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function visibleInitiatives(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT DISTINCT
                initiative.initiative_id,
                initiative.initiative_code,
                initiative.source_request_id,
                initiative.title,
                initiative.initiative_type,
                initiative.status,
                initiative.expected_budget,
                initiative.planned_start_date,
                initiative.planned_end_date,
                initiative.updated_at,
                initiative.record_origin,
                initiative.legacy_reference,
                initiative.legacy_approval_date,
                CONCAT(
                    creator.first_name,
                    ' ',
                    creator.last_name
                ) AS creator_name,
                request.request_code AS source_request_code
             FROM initiatives initiative
             JOIN users creator
               ON creator.user_id = initiative.created_by
             LEFT JOIN initiative_requests request
               ON request.request_id = initiative.source_request_id
             LEFT JOIN initiative_participants participant
               ON participant.initiative_id = initiative.initiative_id
              AND participant.user_id = :participant_user_id
             WHERE initiative.created_by = :owner_user_id
                OR participant.user_id IS NOT NULL
                OR EXISTS (
                    SELECT 1
                    FROM user_roles user_role
                    JOIN roles role
                      ON role.role_id = user_role.role_id
                    WHERE user_role.user_id = :role_user_id
                      AND role.role_name IN (
                          'System Administrator',
                          'Initiative Approver'
                      )
                )
             ORDER BY initiative.updated_at DESC,
                      initiative.initiative_id DESC"
        );
        $statement->execute([
            'participant_user_id' => $userId,
            'owner_user_id' => $userId,
            'role_user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    public function canViewInitiative(
        int $initiativeId,
        int $userId
    ): bool {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiatives initiative
                WHERE initiative.initiative_id =
                      :initiative_id
                  AND (
                        initiative.created_by =
                            :owner_user_id
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_participants
                                 participant
                            WHERE participant.initiative_id =
                                  initiative.initiative_id
                              AND participant.user_id =
                                  :participant_user_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM user_roles user_role
                            JOIN roles role
                              ON role.role_id =
                                 user_role.role_id
                            WHERE user_role.user_id =
                                  :role_user_id
                              AND role.role_name IN (
                                  'System Administrator',
                                  'Initiative Approver'
                              )
                        )
                  )
             )"
        );
        $statement->execute([
            'initiative_id' => $initiativeId,
            'owner_user_id' => $userId,
            'participant_user_id' => $userId,
            'role_user_id' => $userId,
        ]);

        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function initiativeDetail(
        int $initiativeId
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT
                initiative.initiative_id,
                initiative.initiative_code,
                initiative.source_request_id,
                initiative.title,
                initiative.description,
                initiative.objectives,
                initiative.expected_impact,
                initiative.beneficiaries,
                initiative.initiative_type,
                initiative.expected_budget,
                initiative.planned_start_date,
                initiative.planned_end_date,
                initiative.status,
                initiative.created_by,
                initiative.submitted_at,
                initiative.final_decision_at,
                initiative.created_at,
                initiative.updated_at,
                initiative.record_origin,
                initiative.legacy_reference,
                initiative.legacy_approval_date,
                CONCAT(
                    creator.first_name,
                    ' ',
                    creator.last_name
                ) AS creator_name,
                creator.email AS creator_email,
                request.request_code AS source_request_code
             FROM initiatives initiative
             JOIN users creator
               ON creator.user_id =
                  initiative.created_by
             LEFT JOIN initiative_requests request
               ON request.request_id =
                  initiative.source_request_id
             WHERE initiative.initiative_id =
                   :initiative_id"
        );
        $statement->execute([
            'initiative_id' => $initiativeId,
        ]);
        $initiative = $statement->fetch();

        if (!$initiative) {
            return null;
        }

        $agreementStatement = $this->db->prepare(
            "SELECT
                agreement.agreement_id,
                agreement.agreement_code,
                agreement.title,
                link.relation_notes
             FROM initiative_agreements link
             JOIN agreements agreement
               ON agreement.agreement_id =
                  link.agreement_id
             WHERE link.initiative_id =
                   :initiative_id
             ORDER BY agreement.title"
        );
        $agreementStatement->execute([
            'initiative_id' => $initiativeId,
        ]);

        $participantStatement = $this->db->prepare(
            "SELECT
                participant.user_id,
                participant.participant_role,
                CONCAT(
                    user_account.first_name,
                    ' ',
                    user_account.last_name
                ) AS full_name,
                user_account.email,
                participant.created_at
             FROM initiative_participants participant
             JOIN users user_account
               ON user_account.user_id =
                  participant.user_id
             WHERE participant.initiative_id =
                   :initiative_id
             ORDER BY
                CASE participant.participant_role
                    WHEN 'OWNER' THEN 0
                    WHEN 'CO_OWNER' THEN 1
                    ELSE 2
                END,
                user_account.first_name,
                user_account.last_name"
        );
        $participantStatement->execute([
            'initiative_id' => $initiativeId,
        ]);

        $versionStatement = $this->db->prepare(
            "SELECT
                version.version_number,
                version.change_summary,
                version.created_at,
                CONCAT(
                    author.first_name,
                    ' ',
                    author.last_name
                ) AS author_name
             FROM initiative_versions version
             JOIN users author
               ON author.user_id =
                  version.created_by
             WHERE version.initiative_id =
                   :initiative_id
             ORDER BY
                version.version_number DESC,
                version.created_at DESC"
        );
        $versionStatement->execute([
            'initiative_id' => $initiativeId,
        ]);

        $initiative['agreements'] =
            $agreementStatement->fetchAll();
        $initiative['participants'] =
            $participantStatement->fetchAll();
        $initiative['versions'] =
            $versionStatement->fetchAll();

        return $initiative;
    }

    public function accessSummary(int $requestId, int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT status, initiative_id
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        $request = $statement->fetch();

        if (!$request) {
            return [
                'can_convert' => false,
                'conversion_draft_exists' => false,
                'converted_initiative_id' => null,
            ];
        }

        $canConvert = in_array(
            (string) $request['status'],
            ['APPROVED', 'CONVERTING'],
            true
        ) && $this->userCanConvert($requestId, $userId);

        $draft = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_conversion_drafts
                WHERE request_id = :request_id
                  AND status = 'DRAFT'
             )"
        );
        $draft->execute(['request_id' => $requestId]);

        return [
            'can_convert' => $canConvert,
            'conversion_draft_exists' => filter_var(
                $draft->fetchColumn(),
                FILTER_VALIDATE_BOOLEAN
            ),
            'converted_initiative_id' => isset($request['initiative_id'])
                ? (int) $request['initiative_id']
                : null,
        ];
    }

    public function canConvert(int $requestId, int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT status
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
        $status = $statement->fetchColumn();

        return in_array(
            (string) $status,
            ['APPROVED', 'CONVERTING'],
            true
        ) && $this->userCanConvert($requestId, $userId);
    }

    public function conversionData(int $requestId, int $userId): array
    {
        if (!$this->canConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to convert this Initiative request.'
            );
        }

        $statement = $this->db->prepare(
            "SELECT
                request.*,
                agreement.title AS related_agreement_title,
                draft.conversion_draft_id,
                draft.prepared_by,
                draft.title AS draft_title,
                draft.description AS draft_description,
                draft.initiative_type AS draft_initiative_type,
                draft.related_agreement_id AS draft_related_agreement_id,
                draft.form_data,
                draft.status AS conversion_draft_status,
                draft.updated_at AS conversion_draft_updated_at
             FROM initiative_requests request
             LEFT JOIN agreements agreement
               ON agreement.agreement_id = request.related_agreement_id
             LEFT JOIN initiative_conversion_drafts draft
               ON draft.request_id = request.request_id
             WHERE request.request_id = :request_id
             LIMIT 1"
        );
        $statement->execute(['request_id' => $requestId]);
        $row = $statement->fetch();

        if (!$row) {
            throw new InvalidArgumentException(
                'Initiative request not found.'
            );
        }

        $formData = $this->decodeJsonObject(
            $row['form_data'] ?? null
        );
        $hasDraft = !empty($row['conversion_draft_id']);

        return [
            'request_id' => (int) $row['request_id'],
            'request_code' => $row['request_code'],
            'request_status' => $row['status'],
            'conversion_draft_id' => $hasDraft
                ? (int) $row['conversion_draft_id']
                : null,
            'conversion_draft_status' =>
                $row['conversion_draft_status'] ?? null,
            'conversion_draft_updated_at' =>
                $row['conversion_draft_updated_at'] ?? null,
            'title' => $hasDraft
                ? $row['draft_title']
                : $row['title'],
            'description' => $hasDraft
                ? $row['draft_description']
                : $row['description'],
            'initiative_type' => $hasDraft
                ? $row['draft_initiative_type']
                : $row['initiative_type'],
            'objectives' => $hasDraft
                ? ($formData['objectives'] ?? '')
                : ($row['objective'] ?? ''),
            'expected_impact' => $hasDraft
                ? ($formData['expected_impact'] ?? '')
                : ($row['expected_impact'] ?? ''),
            'beneficiaries' => $hasDraft
                ? ($formData['beneficiaries'] ?? '')
                : ($row['beneficiaries'] ?? ''),
            'expected_budget' => $formData['expected_budget'] ?? null,
            'planned_start_date' => $hasDraft
                ? ($formData['planned_start_date'] ?? null)
                : ($row['proposed_start_date'] ?? null),
            'planned_end_date' => $hasDraft
                ? ($formData['planned_end_date'] ?? null)
                : ($row['proposed_end_date'] ?? null),
            'related_agreement_id' => $hasDraft
                ? $row['draft_related_agreement_id']
                : $row['related_agreement_id'],
            'related_agreement_title' =>
                $row['related_agreement_title'] ?? null,
            'relation_notes' => $formData['relation_notes'] ?? '',
            'participants' => $this->requestParticipants($requestId),
        ];
    }

    public function saveDraft(
        int $requestId,
        int $userId,
        array $payload
    ): array {
        $data = $this->normalizePayload($payload);
        $this->db->beginTransaction();

        try {
            $request = $this->lockConvertibleRequest(
                $requestId,
                $userId
            );
            $previousStatus = (string) $request['status'];

            $this->upsertDraft(
                $requestId,
                $userId,
                $data,
                'DRAFT'
            );

            $update = $this->db->prepare(
                "UPDATE initiative_requests
                 SET status = 'CONVERTING',
                     updated_at = CURRENT_TIMESTAMP
                 WHERE request_id = :request_id
                   AND status IN ('APPROVED', 'CONVERTING')"
            );
            $update->execute(['request_id' => $requestId]);

            $this->recordEvent(
                $requestId,
                $userId,
                'CONVERSION_DRAFT_SAVED',
                'Approved Initiative request conversion draft saved.',
                $previousStatus,
                'CONVERTING',
                []
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $this->conversionData($requestId, $userId);
    }

    public function finalize(
        int $requestId,
        int $userId,
        array $payload
    ): array {
        $data = $this->normalizePayload($payload);
        $this->db->beginTransaction();

        try {
            $request = $this->lockConvertibleRequest(
                $requestId,
                $userId
            );
            $previousStatus = (string) $request['status'];

            $this->upsertDraft(
                $requestId,
                $userId,
                $data,
                'DRAFT'
            );

            $insert = $this->db->prepare(
                "INSERT INTO initiatives (
                    source_request_id,
                    title,
                    description,
                    objectives,
                    expected_impact,
                    beneficiaries,
                    initiative_type,
                    expected_budget,
                    planned_start_date,
                    planned_end_date,
                    status,
                    created_by,
                    submitted_at,
                    final_decision_at
                 ) VALUES (
                    CAST(:source_request_id AS BIGINT),
                    :title,
                    :description,
                    :objectives,
                    :expected_impact,
                    :beneficiaries,
                    :initiative_type,
                    CAST(:expected_budget AS NUMERIC),
                    CAST(:planned_start_date AS DATE),
                    CAST(:planned_end_date AS DATE),
                    'APPROVED',
                    CAST(:created_by AS BIGINT),
                    :submitted_at,
                    :final_decision_at
                 )
                 RETURNING initiative_id"
            );
            $insert->execute([
                'source_request_id' => $requestId,
                'title' => $data['title'],
                'description' => $data['description'],
                'objectives' => $data['objectives'],
                'expected_impact' => $data['expected_impact'],
                'beneficiaries' => $data['beneficiaries'],
                'initiative_type' => $data['initiative_type'],
                'expected_budget' => $data['expected_budget'],
                'planned_start_date' => $data['planned_start_date'],
                'planned_end_date' => $data['planned_end_date'],
                'created_by' => (int) $request['requester_id'],
                'submitted_at' => $request['submitted_at'],
                'final_decision_at' => $request['approved_at'],
            ]);
            $initiativeId = (int) $insert->fetchColumn();
            $initiativeCode = sprintf(
                'INI-%s-%06d',
                date('Y'),
                $initiativeId
            );

            $codeUpdate = $this->db->prepare(
                "UPDATE initiatives
                 SET initiative_code = :initiative_code,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE initiative_id = :initiative_id"
            );
            $codeUpdate->execute([
                'initiative_code' => $initiativeCode,
                'initiative_id' => $initiativeId,
            ]);

            if ($data['related_agreement_id'] !== null) {
                $agreement = $this->db->prepare(
                    "INSERT INTO initiative_agreements (
                        initiative_id,
                        agreement_id,
                        relation_notes
                     ) VALUES (
                        :initiative_id,
                        :agreement_id,
                        :relation_notes
                     )
                     ON CONFLICT (initiative_id, agreement_id)
                     DO UPDATE SET relation_notes = EXCLUDED.relation_notes"
                );
                $agreement->execute([
                    'initiative_id' => $initiativeId,
                    'agreement_id' => $data['related_agreement_id'],
                    'relation_notes' => $data['relation_notes'],
                ]);
            }

            $this->copyParticipants(
                $requestId,
                $initiativeId,
                (int) $request['requester_id'],
                $userId
            );

            $initiative = $this->db->prepare(
                "SELECT *
                 FROM initiatives
                 WHERE initiative_id = :initiative_id"
            );
            $initiative->execute(['initiative_id' => $initiativeId]);
            $initiativeRow = $initiative->fetch();

            $version = $this->db->prepare(
                "INSERT INTO initiative_versions (
                    initiative_id,
                    version_number,
                    initiative_snapshot,
                    change_summary,
                    created_by
                 ) VALUES (
                    :initiative_id,
                    1,
                    CAST(:initiative_snapshot AS JSONB),
                    'Created from approved Initiative request',
                    :created_by
                 )"
            );
            $version->execute([
                'initiative_id' => $initiativeId,
                'initiative_snapshot' => json_encode(
                    $initiativeRow,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
                ),
                'created_by' => $userId,
            ]);

            $finalizeDraft = $this->db->prepare(
                "UPDATE initiative_conversion_drafts
                 SET status = 'FINALIZED',
                     initiative_id = :initiative_id,
                     finalized_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE request_id = :request_id"
            );
            $finalizeDraft->execute([
                'initiative_id' => $initiativeId,
                'request_id' => $requestId,
            ]);

            $updateRequest = $this->db->prepare(
                "UPDATE initiative_requests
                 SET status = 'CONVERTED',
                     initiative_id = :initiative_id,
                     converted_by = :converted_by,
                     converted_at = CURRENT_TIMESTAMP,
                     current_stage_order = NULL,
                     current_assignee_id = NULL,
                     current_assignee_unit_id = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE request_id = :request_id
                   AND status IN ('APPROVED', 'CONVERTING')"
            );
            $updateRequest->execute([
                'initiative_id' => $initiativeId,
                'converted_by' => $userId,
                'request_id' => $requestId,
            ]);

            if ($updateRequest->rowCount() !== 1) {
                throw new DomainException(
                    'This Initiative request has already been converted.'
                );
            }

            $this->recordEvent(
                $requestId,
                $userId,
                'REQUEST_CONVERTED',
                sprintf(
                    'Approved Initiative request converted to %s.',
                    $initiativeCode
                ),
                $previousStatus,
                'CONVERTED',
                [
                    'initiative_id' => $initiativeId,
                    'initiative_code' => $initiativeCode,
                    'converted_by' => $userId,
                ]
            );

            $this->notifyParticipants(
                $requestId,
                'REQUEST_CONVERTED',
                'Initiative request converted',
                sprintf(
                    '%s was converted to Initiative %s.',
                    (string) $request['request_code'],
                    $initiativeCode
                )
            );

            $this->db->commit();

            return [
                'request_id' => $requestId,
                'initiative_id' => $initiativeId,
                'initiative_code' => $initiativeCode,
                'status' => 'CONVERTED',
                'initiative_url' =>
                    'initiative-view.php?id=' . $initiativeId,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function userCanConvert(int $requestId, int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_requests request
                WHERE request.request_id = :request_id
                  AND (
                        request.requester_id = :requester_user_id
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_members member
                            WHERE member.request_id = request.request_id
                              AND member.user_id = :member_user_id
                              AND member.can_convert_after_approval = TRUE
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM user_roles user_role
                            JOIN roles role
                              ON role.role_id = user_role.role_id
                            WHERE user_role.user_id = :administrator_user_id
                              AND role.role_name = 'System Administrator'
                        )
                  )
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'requester_user_id' => $userId,
            'member_user_id' => $userId,
            'administrator_user_id' => $userId,
        ]);

        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function lockConvertibleRequest(
        int $requestId,
        int $userId
    ): array {
        $statement = $this->db->prepare(
            "SELECT *
             FROM initiative_requests
             WHERE request_id = :request_id
               AND status IN ('APPROVED', 'CONVERTING')
               AND initiative_id IS NULL
             FOR UPDATE"
        );
        $statement->execute(['request_id' => $requestId]);
        $request = $statement->fetch();

        if (!$request) {
            throw new DomainException(
                'This Initiative request is not available for conversion.'
            );
        }

        if (!$this->userCanConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to convert this Initiative request.'
            );
        }

        return $request;
    }

    private function normalizePayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $type = trim((string) ($payload['initiative_type'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $objectives = trim((string) ($payload['objectives'] ?? ''));

        if ($title === '' || $type === '' || $description === '' || $objectives === '') {
            throw new InvalidArgumentException(
                'Title, type, description, and objectives are required.'
            );
        }

        $startDate = $this->nullableDate(
            $payload['planned_start_date'] ?? null
        );
        $endDate = $this->nullableDate(
            $payload['planned_end_date'] ?? null
        );

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new InvalidArgumentException(
                'The planned end date cannot be before the start date.'
            );
        }

        $budgetValue = $payload['expected_budget'] ?? null;
        $budget = null;
        if ($budgetValue !== null && trim((string) $budgetValue) !== '') {
            if (!is_numeric($budgetValue) || (float) $budgetValue < 0) {
                throw new InvalidArgumentException(
                    'Expected budget must be zero or a positive number.'
                );
            }
            $budget = number_format((float) $budgetValue, 2, '.', '');
        }

        return [
            'title' => $title,
            'description' => $description,
            'objectives' => $objectives,
            'expected_impact' => $this->nullableText(
                $payload['expected_impact'] ?? null
            ),
            'beneficiaries' => $this->nullableText(
                $payload['beneficiaries'] ?? null
            ),
            'initiative_type' => $type,
            'expected_budget' => $budget,
            'planned_start_date' => $startDate,
            'planned_end_date' => $endDate,
            'related_agreement_id' => $this->nullableInteger(
                $payload['related_agreement_id'] ?? null
            ),
            'relation_notes' => $this->nullableText(
                $payload['relation_notes'] ?? null
            ),
        ];
    }

    private function upsertDraft(
        int $requestId,
        int $userId,
        array $data,
        string $status
    ): void {
        $formData = [
            'objectives' => $data['objectives'],
            'expected_impact' => $data['expected_impact'],
            'beneficiaries' => $data['beneficiaries'],
            'expected_budget' => $data['expected_budget'],
            'planned_start_date' => $data['planned_start_date'],
            'planned_end_date' => $data['planned_end_date'],
            'relation_notes' => $data['relation_notes'],
        ];

        $statement = $this->db->prepare(
            "INSERT INTO initiative_conversion_drafts (
                request_id,
                prepared_by,
                title,
                description,
                initiative_type,
                related_agreement_id,
                form_data,
                status
             ) VALUES (
                :request_id,
                :prepared_by,
                :title,
                :description,
                :initiative_type,
                :related_agreement_id,
                CAST(:form_data AS JSONB),
                :status
             )
             ON CONFLICT (request_id)
             DO UPDATE SET
                prepared_by = EXCLUDED.prepared_by,
                title = EXCLUDED.title,
                description = EXCLUDED.description,
                initiative_type = EXCLUDED.initiative_type,
                related_agreement_id = EXCLUDED.related_agreement_id,
                form_data = EXCLUDED.form_data,
                status = EXCLUDED.status,
                updated_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'request_id' => $requestId,
            'prepared_by' => $userId,
            'title' => $data['title'],
            'description' => $data['description'],
            'initiative_type' => $data['initiative_type'],
            'related_agreement_id' => $data['related_agreement_id'],
            'form_data' => json_encode(
                $formData,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
            'status' => $status,
        ]);
    }

    private function requestParticipants(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                participant.user_id,
                participant.participant_role,
                CONCAT(user_account.first_name, ' ', user_account.last_name)
                    AS full_name,
                user_account.email
             FROM (
                SELECT requester_id AS user_id, 'OWNER' AS participant_role
                FROM initiative_requests
                WHERE request_id = :owner_request_id

                UNION ALL

                SELECT user_id, member_role AS participant_role
                FROM initiative_request_members
                WHERE request_id = :member_request_id
             ) participant
             JOIN users user_account
               ON user_account.user_id = participant.user_id
             ORDER BY
                CASE participant.participant_role
                    WHEN 'OWNER' THEN 0
                    WHEN 'CO_OWNER' THEN 1
                    ELSE 2
                END,
                user_account.first_name,
                user_account.last_name"
        );
        $statement->execute([
            'owner_request_id' => $requestId,
            'member_request_id' => $requestId,
        ]);

        return $statement->fetchAll();
    }

    private function copyParticipants(
        int $requestId,
        int $initiativeId,
        int $ownerUserId,
        int $addedBy
    ): void {
        $insert = $this->db->prepare(
            "INSERT INTO initiative_participants (
                initiative_id,
                user_id,
                participant_role,
                added_by
             ) VALUES (
                :initiative_id,
                :user_id,
                :participant_role,
                :added_by
             )
             ON CONFLICT (initiative_id, user_id)
             DO UPDATE SET participant_role = EXCLUDED.participant_role"
        );
        $insert->execute([
            'initiative_id' => $initiativeId,
            'user_id' => $ownerUserId,
            'participant_role' => 'OWNER',
            'added_by' => $addedBy,
        ]);

        $members = $this->db->prepare(
            "SELECT user_id, member_role
             FROM initiative_request_members
             WHERE request_id = :request_id"
        );
        $members->execute(['request_id' => $requestId]);

        foreach ($members->fetchAll() as $member) {
            $role = strtoupper((string) $member['member_role']);
            if (!in_array($role, ['CO_OWNER', 'COLLABORATOR'], true)) {
                $role = 'COLLABORATOR';
            }
            $insert->execute([
                'initiative_id' => $initiativeId,
                'user_id' => (int) $member['user_id'],
                'participant_role' => $role,
                'added_by' => $addedBy,
            ]);
        }
    }

    private function notifyParticipants(
        int $requestId,
        string $type,
        string $title,
        string $message
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_notifications (
                request_id,
                recipient_user_id,
                notification_type,
                title,
                message,
                payload
             )
             SELECT
                CAST(:request_id AS BIGINT),
                participant.user_id,
                :notification_type,
                :title,
                :message,
                jsonb_build_object(
                    'request_url',
                    CONCAT(
                        'initiative-workflow.php?view=detail&id=',
                        CAST(:payload_request_id AS BIGINT)
                    )
                )
             FROM (
                SELECT requester_id AS user_id
                FROM initiative_requests
                WHERE request_id = :owner_request_id
                UNION
                SELECT user_id
                FROM initiative_request_members
                WHERE request_id = :member_request_id
             ) participant"
        );
        $statement->execute([
            'request_id' => $requestId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'payload_request_id' => $requestId,
            'owner_request_id' => $requestId,
            'member_request_id' => $requestId,
        ]);
    }

    private function recordEvent(
        int $requestId,
        int $actorUserId,
        string $eventType,
        string $note,
        ?string $fromStatus,
        ?string $toStatus,
        array $eventData
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_request_events (
                request_id,
                cycle_number,
                event_type,
                actor_user_id,
                from_status,
                to_status,
                event_note,
                event_data
             )
             SELECT
                request_id,
                revision_cycle,
                :event_type,
                :actor_user_id,
                :from_status,
                :to_status,
                :event_note,
                CAST(:event_data AS JSONB)
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'request_id' => $requestId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_note' => $note,
            'event_data' => json_encode(
                $eventData === [] ? new stdClass() : $eventData,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
        ]);
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        return $integer === false || $integer < 1 ? null : $integer;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(
                'Dates must use the YYYY-MM-DD format.'
            );
        }
        return $value;
    }
}
