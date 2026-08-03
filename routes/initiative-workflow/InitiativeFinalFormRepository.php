<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeFinalFormRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function canConvert(int $requestId, int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_requests request
                WHERE request.request_id = :request_id
                  AND request.status IN ('APPROVED', 'CONVERTING')
                  AND request.initiative_id IS NULL
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

    public function conversionData(int $requestId, int $userId): array
    {
        if (!$this->canConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to prepare this approved Initiative.'
            );
        }

        $statement = $this->db->prepare(
            "SELECT
                request.*,
                agreement.title AS related_agreement_title,
                agreement.agreement_code AS related_agreement_code,
                draft.conversion_draft_id,
                draft.prepared_by,
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

        $participants = $this->requestParticipants($requestId);
        $draftData = $this->decodeJsonObject(
            $row['form_data'] ?? null
        );
        $hasDraft = !empty($row['conversion_draft_id']);

        $formData = $hasDraft
            ? array_replace_recursive(
                $this->requestPrefill($row, $participants),
                $draftData
            )
            : $this->requestPrefill($row, $participants);

        return [
            'request_id' => (int) $row['request_id'],
            'request_code' => (string) $row['request_code'],
            'request_status' => (string) $row['status'],
            'conversion_draft_id' => $hasDraft
                ? (int) $row['conversion_draft_id']
                : null,
            'conversion_draft_status' =>
                $row['conversion_draft_status'] ?? null,
            'conversion_draft_updated_at' =>
                $row['conversion_draft_updated_at'] ?? null,
            'source_title' => (string) $row['title'],
            'related_agreement_title' =>
                $row['related_agreement_title'] ?? null,
            'related_agreement_code' =>
                $row['related_agreement_code'] ?? null,
            'form_data' => $formData,
            'participants' => $participants,
            'request_attachments' =>
                $this->requestAttachments($requestId),
            'conversion_attachments' =>
                $this->conversionAttachments($requestId),
        ];
    }

    public function saveDraft(
        int $requestId,
        int $userId,
        array $payload
    ): array {
        $data = $this->normalizePayload($payload, false);
        $this->db->beginTransaction();

        try {
            $request = $this->lockConvertibleRequest(
                $requestId,
                $userId
            );
            $previousStatus = (string) $request['status'];
            $data['approval_request_id'] =
                (string) $request['request_code'];

            $data = $this->withApprovedLocationData(
                $data,
                $request
            );

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
                'The approved Initiative final-form draft was saved.',
                $previousStatus,
                'CONVERTING',
                [
                    'storage' => 'PostgreSQL',
                    'form_version' => 1,
                ]
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
        $data = $this->normalizePayload($payload, true);
        $this->db->beginTransaction();

        try {
            $request = $this->lockConvertibleRequest(
                $requestId,
                $userId
            );
            $previousStatus = (string) $request['status'];
            $data['approval_request_id'] =
                (string) $request['request_code'];

            $data = $this->withApprovedLocationData(
                $data,
                $request
            );

            $this->upsertDraft(
                $requestId,
                $userId,
                $data,
                'DRAFT'
            );

            $beneficiaries = $data['beneficiaries'];
            if ($beneficiaries === null) {
                $beneficiaries = implode(
                    ', ',
                    $data['target_groups']
                );
            }

            $expectedImpact =
                $data['societal_impact']
                ?? $data['expected_impact'];

            $expectedBudget =
                $data['expected_budget']
                ?? $data['internal_funding_bhd'];

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
                    proposed_venue,
                    proposed_venue_place_id,
                    proposed_venue_name,
                    proposed_venue_latitude,
                    proposed_venue_longitude,
                    proposed_venue_country_code,
                    status,
                    created_by,
                    submitted_at,
                    final_decision_at,
                    final_form_data,
                    final_form_version
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
                    :proposed_venue,
                    :proposed_venue_place_id,
                    :proposed_venue_name,
                    CAST(:proposed_venue_latitude AS NUMERIC),
                    CAST(:proposed_venue_longitude AS NUMERIC),
                    :proposed_venue_country_code,
                    'APPROVED',
                    CAST(:created_by AS BIGINT),
                    :submitted_at,
                    :final_decision_at,
                    CAST(:final_form_data AS JSONB),
                    1
                 )
                 RETURNING initiative_id"
            );
            $insert->execute([
                'source_request_id' => $requestId,
                'title' => $data['title'],
                'description' => $data['description'],
                'objectives' => $data['objectives'],
                'expected_impact' => $expectedImpact,
                'beneficiaries' => $beneficiaries,
                'initiative_type' => $data['initiative_type'],
                'expected_budget' => $expectedBudget,
                'planned_start_date' => $data['start_date'],
                'planned_end_date' => $data['end_date'],
                'proposed_venue' =>
                    $data['proposed_venue']
                    ?? $data['outside_location']
                    ?? null,
                'proposed_venue_place_id' =>
                    $data['proposed_venue_place_id'] ?? null,
                'proposed_venue_name' =>
                    $data['proposed_venue_name'] ?? null,
                'proposed_venue_latitude' =>
                    $data['proposed_venue_latitude'] ?? null,
                'proposed_venue_longitude' =>
                    $data['proposed_venue_longitude'] ?? null,
                'proposed_venue_country_code' =>
                    isset($data['proposed_venue_country_code'])
                        ? strtoupper(
                            (string) $data[
                                'proposed_venue_country_code'
                            ]
                        )
                        : null,
                'created_by' => (int) $request['requester_id'],
                'submitted_at' => $request['submitted_at'],
                'final_decision_at' => $request['approved_at'],
                'final_form_data' => json_encode(
                    $data,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
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
                     DO UPDATE SET
                        relation_notes = EXCLUDED.relation_notes"
                );
                $agreement->execute([
                    'initiative_id' => $initiativeId,
                    'agreement_id' =>
                        $data['related_agreement_id'],
                    'relation_notes' =>
                        $data['relation_notes'],
                ]);
            }

            $this->saveFinalPeople(
                $initiativeId,
                $data['contributors'],
                (int) $request['requester_id'],
                $userId
            );

            $this->copyAttachments(
                $requestId,
                $initiativeId,
                $userId,
                $data['excluded_request_attachment_ids']
            );

            $initiative = $this->db->prepare(
                "SELECT *
                 FROM initiatives
                 WHERE initiative_id = :initiative_id"
            );
            $initiative->execute([
                'initiative_id' => $initiativeId,
            ]);
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
                    'Created from approved Initiative request and finalized in PostgreSQL',
                    :created_by
                 )"
            );
            $version->execute([
                'initiative_id' => $initiativeId,
                'initiative_snapshot' => json_encode(
                    $initiativeRow,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
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
                    'The approved request was finalized as %s.',
                    $initiativeCode
                ),
                $previousStatus,
                'CONVERTED',
                [
                    'initiative_id' => $initiativeId,
                    'initiative_code' => $initiativeCode,
                    'storage' => 'PostgreSQL',
                    'form_version' => 1,
                ]
            );

            $this->notifyParticipants(
                $requestId,
                'REQUEST_CONVERTED',
                'Initiative request converted',
                sprintf(
                    '%s was finalized as Initiative %s.',
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


    public function legacyData(
        ?int $draftId,
        int $userId
    ): array {
        $draft = null;

        if ($draftId !== null) {
            $draft = $this->legacyDraftRow(
                $draftId,
                $userId,
                false
            );
        }

        $formData = $draft
            ? $this->decodeJsonObject($draft['form_data'] ?? null)
            : $this->legacyPrefill($userId);

        return [
            'legacy_mode' => true,
            'request_id' => null,
            'request_code' => $draft
                ? 'EXISTING-DRAFT-' . $draft['draft_id']
                : 'Existing Initiative',
            'request_status' => 'APPROVED',
            'source_title' => $formData['title']
                ?? 'Historical Initiative record',
            'conversion_draft_id' => $draft
                ? (int) $draft['draft_id']
                : null,
            'conversion_draft_status' => $draft['status'] ?? null,
            'conversion_draft_updated_at' =>
                $draft['updated_at'] ?? null,
            'form_data' => $formData,
            'participants' => [],
            'request_attachments' => [],
            'conversion_attachments' => $draft
                ? $this->legacyAttachments((int) $draft['draft_id'])
                : [],
        ];
    }

    public function saveLegacyDraft(
        ?int $draftId,
        int $userId,
        array $payload
    ): array {
        $data = $this->normalizePayload($payload, false);

        if ($draftId === null) {
            $statement = $this->db->prepare(
                "INSERT INTO initiative_legacy_drafts (
                    prepared_by,
                    form_data,
                    status
                 ) VALUES (
                    :prepared_by,
                    CAST(:form_data AS JSONB),
                    'DRAFT'
                 )
                 RETURNING draft_id"
            );
            $statement->execute([
                'prepared_by' => $userId,
                'form_data' => json_encode(
                    $data,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
            ]);
            $draftId = (int) $statement->fetchColumn();
        } else {
            $this->legacyDraftRow($draftId, $userId, true);
            $statement = $this->db->prepare(
                "UPDATE initiative_legacy_drafts
                 SET form_data = CAST(:form_data AS JSONB),
                     updated_at = CURRENT_TIMESTAMP
                 WHERE draft_id = :draft_id
                   AND status = 'DRAFT'"
            );
            $statement->execute([
                'form_data' => json_encode(
                    $data,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                'draft_id' => $draftId,
            ]);
        }

        return $this->legacyData($draftId, $userId);
    }

    public function finalizeLegacy(
        ?int $draftId,
        int $userId,
        array $payload
    ): array {
        $data = $this->normalizePayload($payload, true);
        $this->db->beginTransaction();

        try {
            if ($draftId === null) {
                $statement = $this->db->prepare(
                    "INSERT INTO initiative_legacy_drafts (
                        prepared_by,
                        form_data,
                        status
                     ) VALUES (
                        :prepared_by,
                        CAST(:form_data AS JSONB),
                        'DRAFT'
                     )
                     RETURNING draft_id"
                );
                $statement->execute([
                    'prepared_by' => $userId,
                    'form_data' => json_encode(
                        $data,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
                ]);
                $draftId = (int) $statement->fetchColumn();
            } else {
                $this->legacyDraftRow($draftId, $userId, true);
                $statement = $this->db->prepare(
                    "UPDATE initiative_legacy_drafts
                     SET form_data = CAST(:form_data AS JSONB),
                         updated_at = CURRENT_TIMESTAMP
                     WHERE draft_id = :draft_id
                       AND status = 'DRAFT'"
                );
                $statement->execute([
                    'form_data' => json_encode(
                        $data,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),
                    'draft_id' => $draftId,
                ]);
            }

            $beneficiaries = $data['beneficiaries'];
            if ($beneficiaries === null) {
                $beneficiaries = implode(', ', $data['target_groups']);
            }

            $expectedImpact =
                $data['societal_impact']
                ?? $data['expected_impact'];
            $expectedBudget =
                $data['expected_budget']
                ?? $data['internal_funding_bhd'];
            $recordDate =
                $data['legacy_approval_date']
                ?? $data['start_date'];

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
                    proposed_venue,
                    proposed_venue_place_id,
                    proposed_venue_name,
                    proposed_venue_latitude,
                    proposed_venue_longitude,
                    proposed_venue_country_code,
                    status,
                    created_by,
                    submitted_at,
                    final_decision_at,
                    final_form_data,
                    final_form_version,
                    record_origin,
                    legacy_reference,
                    legacy_approval_date,
                    legacy_recorded_by,
                    legacy_recorded_at
                 ) VALUES (
                    NULL,
                    :title,
                    :description,
                    :objectives,
                    :expected_impact,
                    :beneficiaries,
                    :initiative_type,
                    CAST(:expected_budget AS NUMERIC),
                    CAST(:planned_start_date AS DATE),
                    CAST(:planned_end_date AS DATE),
                    :proposed_venue,
                    :proposed_venue_place_id,
                    :proposed_venue_name,
                    CAST(:proposed_venue_latitude AS NUMERIC),
                    CAST(:proposed_venue_longitude AS NUMERIC),
                    :proposed_venue_country_code,
                    'APPROVED',
                    CAST(:created_by AS BIGINT),
                    CAST(:submitted_at AS TIMESTAMP),
                    CAST(:final_decision_at AS TIMESTAMP),
                    CAST(:final_form_data AS JSONB),
                    1,
                    'LEGACY',
                    :legacy_reference,
                    CAST(:legacy_approval_date AS DATE),
                    CAST(:legacy_recorded_by AS BIGINT),
                    CURRENT_TIMESTAMP
                 )
                 RETURNING initiative_id"
            );
            $insert->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'objectives' => $data['objectives'],
                'expected_impact' => $expectedImpact,
                'beneficiaries' => $beneficiaries,
                'initiative_type' => $data['initiative_type'],
                'expected_budget' => $expectedBudget,
                'planned_start_date' => $data['start_date'],
                'planned_end_date' => $data['end_date'],
                'proposed_venue' =>
                    $data['proposed_venue']
                    ?? $data['outside_location']
                    ?? null,
                'proposed_venue_place_id' =>
                    $data['proposed_venue_place_id'] ?? null,
                'proposed_venue_name' =>
                    $data['proposed_venue_name'] ?? null,
                'proposed_venue_latitude' =>
                    $data['proposed_venue_latitude'] ?? null,
                'proposed_venue_longitude' =>
                    $data['proposed_venue_longitude'] ?? null,
                'proposed_venue_country_code' =>
                    isset($data['proposed_venue_country_code'])
                        ? strtoupper(
                            (string) $data[
                                'proposed_venue_country_code'
                            ]
                        )
                        : null,
                'created_by' => $userId,
                'submitted_at' => $recordDate,
                'final_decision_at' => $recordDate,
                'final_form_data' => json_encode(
                    $data,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                'legacy_reference' =>
                    $data['approval_request_id'],
                'legacy_approval_date' =>
                    $data['legacy_approval_date'],
                'legacy_recorded_by' => $userId,
            ]);
            $initiativeId = (int) $insert->fetchColumn();
            $year = $data['start_date']
                ? substr($data['start_date'], 0, 4)
                : date('Y');
            $initiativeCode = sprintf(
                'INI-%s-%06d',
                $year,
                $initiativeId
            );

            $code = $this->db->prepare(
                "UPDATE initiatives
                 SET initiative_code = :initiative_code,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE initiative_id = :initiative_id"
            );
            $code->execute([
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
                     DO UPDATE SET
                        relation_notes = EXCLUDED.relation_notes"
                );
                $agreement->execute([
                    'initiative_id' => $initiativeId,
                    'agreement_id' =>
                        $data['related_agreement_id'],
                    'relation_notes' =>
                        $data['relation_notes'],
                ]);
            }

            $this->saveFinalPeople(
                $initiativeId,
                $data['contributors'],
                $userId,
                $userId
            );
            $this->copyLegacyAttachments(
                $draftId,
                $initiativeId,
                $userId
            );

            $initiative = $this->db->prepare(
                "SELECT *
                 FROM initiatives
                 WHERE initiative_id = :initiative_id"
            );
            $initiative->execute([
                'initiative_id' => $initiativeId,
            ]);
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
                    'Existing Initiative registered manually in the Workspace',
                    :created_by
                 )"
            );
            $version->execute([
                'initiative_id' => $initiativeId,
                'initiative_snapshot' => json_encode(
                    $initiativeRow,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                ),
                'created_by' => $userId,
            ]);

            $finish = $this->db->prepare(
                "UPDATE initiative_legacy_drafts
                 SET status = 'FINALIZED',
                     initiative_id = :initiative_id,
                     finalized_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE draft_id = :draft_id"
            );
            $finish->execute([
                'initiative_id' => $initiativeId,
                'draft_id' => $draftId,
            ]);

            $this->db->commit();

            return [
                'draft_id' => $draftId,
                'initiative_id' => $initiativeId,
                'initiative_code' => $initiativeCode,
                'status' => 'APPROVED',
                'record_origin' => 'LEGACY',
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

    public function uploadLegacyAttachments(
        int $draftId,
        int $userId,
        array $files
    ): array {
        $this->legacyDraftRow($draftId, $userId, false);
        $normalizedFiles = $this->normalizeUploadedFiles($files);

        if ($normalizedFiles === []) {
            throw new InvalidArgumentException(
                'Select at least one evidence file.'
            );
        }

        $count = $this->db->prepare(
            "SELECT COUNT(*)
             FROM initiative_legacy_attachments
             WHERE draft_id = :draft_id"
        );
        $count->execute(['draft_id' => $draftId]);

        if ((int) $count->fetchColumn() + count($normalizedFiles) > 10) {
            throw new InvalidArgumentException(
                'A maximum of 10 evidence files is allowed.'
            );
        }

        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'ppt', 'pptx', 'mp4', 'mov',
        ];
        $maximumSize = 20 * 1024 * 1024;
        $uploadDirectory = dirname(__DIR__, 2)
            . '/uob-agreements/uploads/initiative-evidence';

        if (
            !is_dir($uploadDirectory)
            && !mkdir($uploadDirectory, 0775, true)
            && !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'The Initiative evidence directory could not be created.'
            );
        }

        $insert = $this->db->prepare(
            "INSERT INTO initiative_legacy_attachments (
                draft_id,
                original_name,
                stored_name,
                storage_path,
                file_extension,
                mime_type,
                file_size_bytes,
                uploaded_by
             ) VALUES (
                :draft_id,
                :original_name,
                :stored_name,
                :storage_path,
                :file_extension,
                :mime_type,
                :file_size_bytes,
                :uploaded_by
             )"
        );

        foreach ($normalizedFiles as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    'One of the selected evidence files could not be uploaded.'
                );
            }

            $originalName = basename((string) ($file['name'] ?? ''));
            $temporaryPath = (string) ($file['tmp_name'] ?? '');
            $fileSize = (int) ($file['size'] ?? 0);
            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            if (
                $originalName === ''
                || !in_array($extension, $allowedExtensions, true)
            ) {
                throw new InvalidArgumentException(
                    'Supported evidence files are images, PDF, Office documents, MP4, and MOV.'
                );
            }
            if ($fileSize < 1 || $fileSize > $maximumSize) {
                throw new InvalidArgumentException(
                    'Each evidence file must be 20 MB or smaller.'
                );
            }
            if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                throw new InvalidArgumentException(
                    'The selected evidence upload is not valid.'
                );
            }

            $storedName = sprintf(
                'existing-%d-%s.%s',
                $draftId,
                bin2hex(random_bytes(16)),
                $extension
            );
            $absolutePath = $uploadDirectory
                . DIRECTORY_SEPARATOR . $storedName;
            $relativePath =
                'uob-agreements/uploads/initiative-evidence/'
                . $storedName;

            if (!move_uploaded_file($temporaryPath, $absolutePath)) {
                throw new RuntimeException(
                    'The evidence file could not be stored.'
                );
            }

            $mimeType = null;
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $fileInfo->file($absolutePath);
            if (is_string($detectedMime) && trim($detectedMime) !== '') {
                $mimeType = $detectedMime;
            }

            $insert->execute([
                'draft_id' => $draftId,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'storage_path' => $relativePath,
                'file_extension' => $extension,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSize,
                'uploaded_by' => $userId,
            ]);
        }

        return $this->legacyAttachments($draftId);
    }

    public function deleteLegacyAttachment(
        int $draftId,
        int $attachmentId,
        int $userId
    ): bool {
        $this->legacyDraftRow($draftId, $userId, false);
        $statement = $this->db->prepare(
            "SELECT storage_path
             FROM initiative_legacy_attachments
             WHERE attachment_id = :attachment_id
               AND draft_id = :draft_id"
        );
        $statement->execute([
            'attachment_id' => $attachmentId,
            'draft_id' => $draftId,
        ]);
        $row = $statement->fetch();
        if (!$row) {
            return false;
        }

        $delete = $this->db->prepare(
            "DELETE FROM initiative_legacy_attachments
             WHERE attachment_id = :attachment_id
               AND draft_id = :draft_id"
        );
        $delete->execute([
            'attachment_id' => $attachmentId,
            'draft_id' => $draftId,
        ]);

        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['storage_path']);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        return $delete->rowCount() === 1;
    }

    public function legacyAttachmentForDownload(
        int $draftId,
        int $attachmentId,
        int $userId
    ): ?array {
        $this->legacyDraftRow($draftId, $userId, false);
        $statement = $this->db->prepare(
            "SELECT *
             FROM initiative_legacy_attachments
             WHERE attachment_id = :attachment_id
               AND draft_id = :draft_id"
        );
        $statement->execute([
            'attachment_id' => $attachmentId,
            'draft_id' => $draftId,
        ]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        $row['absolute_path'] = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['storage_path']);

        return $row;
    }

    public function uploadAttachments(
        int $requestId,
        int $userId,
        array $files
    ): array {
        if (!$this->canConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to add evidence to this Initiative.'
            );
        }

        $normalizedFiles = $this->normalizeUploadedFiles($files);

        if ($normalizedFiles === []) {
            throw new InvalidArgumentException(
                'Select at least one evidence file.'
            );
        }

        $countStatement = $this->db->prepare(
            "SELECT
                (
                    SELECT COUNT(*)
                    FROM initiative_request_attachments
                    WHERE request_id = :request_attachment_id
                )
                +
                (
                    SELECT COUNT(*)
                    FROM initiative_conversion_attachments
                    WHERE request_id = :conversion_attachment_id
                )"
        );
        $countStatement->execute([
            'request_attachment_id' => $requestId,
            'conversion_attachment_id' => $requestId,
        ]);
        $existingCount =
            (int) $countStatement->fetchColumn();

        if ($existingCount + count($normalizedFiles) > 10) {
            throw new InvalidArgumentException(
                'A maximum of 10 evidence files is allowed.'
            );
        }

        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'ppt', 'pptx', 'mp4', 'mov',
        ];
        $maximumSize = 20 * 1024 * 1024;
        $uploadDirectory = dirname(__DIR__, 2)
            . '/uob-agreements/uploads/initiative-evidence';

        if (
            !is_dir($uploadDirectory)
            && !mkdir($uploadDirectory, 0775, true)
            && !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'The Initiative evidence directory could not be created.'
            );
        }

        $preparedFiles = [];

        foreach ($normalizedFiles as $file) {
            $error = (int) (
                $file['error'] ?? UPLOAD_ERR_NO_FILE
            );

            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    'One of the selected evidence files could not be uploaded.'
                );
            }

            $originalName = basename(
                (string) ($file['name'] ?? '')
            );
            $temporaryPath =
                (string) ($file['tmp_name'] ?? '');
            $fileSize = (int) ($file['size'] ?? 0);
            $extension = strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

            if (
                $originalName === ''
                || !in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Supported evidence files are images, PDF, Office documents, MP4, and MOV.'
                );
            }

            if (
                $fileSize < 1
                || $fileSize > $maximumSize
            ) {
                throw new InvalidArgumentException(
                    'Each evidence file must be 20 MB or smaller.'
                );
            }

            if (
                $temporaryPath === ''
                || !is_uploaded_file($temporaryPath)
            ) {
                throw new InvalidArgumentException(
                    'The selected evidence upload is not valid.'
                );
            }

            $storedName = sprintf(
                '%d-%s.%s',
                $requestId,
                bin2hex(random_bytes(16)),
                $extension
            );
            $absolutePath = $uploadDirectory
                . DIRECTORY_SEPARATOR
                . $storedName;
            $relativePath =
                'uob-agreements/uploads/initiative-evidence/'
                . $storedName;

            $mimeType = null;
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $fileInfo->file($temporaryPath);

            if (
                is_string($detectedMime)
                && trim($detectedMime) !== ''
            ) {
                $mimeType = $detectedMime;
            }

            $preparedFiles[] = [
                'original_name' => $originalName,
                'temporary_path' => $temporaryPath,
                'stored_name' => $storedName,
                'absolute_path' => $absolutePath,
                'storage_path' => $relativePath,
                'file_extension' => $extension,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSize,
            ];
        }

        $movedPaths = [];
        $this->db->beginTransaction();

        try {
            $insert = $this->db->prepare(
                "INSERT INTO initiative_conversion_attachments (
                    request_id,
                    original_name,
                    stored_name,
                    storage_path,
                    file_extension,
                    mime_type,
                    file_size_bytes,
                    uploaded_by
                 ) VALUES (
                    :request_id,
                    :original_name,
                    :stored_name,
                    :storage_path,
                    :file_extension,
                    :mime_type,
                    :file_size_bytes,
                    :uploaded_by
                 )"
            );

            foreach ($preparedFiles as $file) {
                if (
                    !move_uploaded_file(
                        $file['temporary_path'],
                        $file['absolute_path']
                    )
                ) {
                    throw new RuntimeException(
                        'An evidence file could not be stored.'
                    );
                }

                $movedPaths[] = $file['absolute_path'];

                $insert->execute([
                    'request_id' => $requestId,
                    'original_name' => $file['original_name'],
                    'stored_name' => $file['stored_name'],
                    'storage_path' => $file['storage_path'],
                    'file_extension' => $file['file_extension'],
                    'mime_type' => $file['mime_type'],
                    'file_size_bytes' => $file['file_size_bytes'],
                    'uploaded_by' => $userId,
                ]);
            }

            $this->recordEvent(
                $requestId,
                $userId,
                'CONVERSION_EVIDENCE_ADDED',
                sprintf(
                    '%d final-Initiative evidence file(s) added.',
                    count($preparedFiles)
                ),
                null,
                null,
                [
                    'file_names' => array_column(
                        $preparedFiles,
                        'original_name'
                    ),
                ]
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach ($movedPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            throw $exception;
        }

        return $this->conversionAttachments($requestId);
    }

    public function deleteAttachment(
        int $requestId,
        int $attachmentId,
        int $userId
    ): bool {
        if (!$this->canConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to remove this evidence file.'
            );
        }

        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                storage_path
             FROM initiative_conversion_attachments
             WHERE attachment_id = :attachment_id
               AND request_id = :request_id
             LIMIT 1"
        );
        $statement->execute([
            'attachment_id' => $attachmentId,
            'request_id' => $requestId,
        ]);
        $attachment = $statement->fetch();

        if (!$attachment) {
            return false;
        }

        $delete = $this->db->prepare(
            "DELETE FROM initiative_conversion_attachments
             WHERE attachment_id = :attachment_id
               AND request_id = :request_id"
        );
        $delete->execute([
            'attachment_id' => $attachmentId,
            'request_id' => $requestId,
        ]);

        if ($delete->rowCount() !== 1) {
            return false;
        }

        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                (string) $attachment['storage_path']
            );

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        return true;
    }

    public function attachmentForDownload(
        int $requestId,
        int $attachmentId,
        int $userId
    ): ?array {
        if (!$this->canConvert($requestId, $userId)) {
            return null;
        }

        $statement = $this->db->prepare(
            "SELECT *
             FROM initiative_conversion_attachments
             WHERE attachment_id = :attachment_id
               AND request_id = :request_id
             LIMIT 1"
        );
        $statement->execute([
            'attachment_id' => $attachmentId,
            'request_id' => $requestId,
        ]);
        $attachment = $statement->fetch();

        if (!$attachment) {
            return null;
        }

        $attachment['absolute_path'] = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                (string) $attachment['storage_path']
            );

        return $attachment;
    }

    private function requestPrefill(
        array $request,
        array $participants
    ): array {
        $secondaryTypes = $this->decodeJsonArray(
            $request['secondary_types'] ?? null
        );
        $targetGroups = $this->decodeJsonArray(
            $request['target_groups'] ?? null
        );
        $requiredResources = $this->decodeJsonArray(
            $request['required_resources'] ?? null
        );
        $sdgGoals = $this->decodeJsonArray(
            $request['sdg_goals'] ?? null
        );

        $providerCategories = match (
            strtoupper(
                (string) ($request['requester_type'] ?? '')
            )
        ) {
            'FACULTY' => ['ACADEMIC'],
            'STUDENT', 'STUDENT_GROUP' => ['STUDENTS'],
            default => ['ADMINISTRATIVE'],
        };

        $resourceMap = [
            'VENUE' => 'FACILITIES',
            'BUDGET' => 'BUDGET',
            'MEDIA' => 'MEDIA_SUPPORT',
            'TRANSPORT' => 'TRANSPORT',
            'EQUIPMENT' => 'EQUIPMENT',
            'OTHER' => 'OTHER',
        ];
        $resourcesMobilized = [];
        foreach ($requiredResources as $resource) {
            if ($resource === 'NONE') {
                continue;
            }
            $resourcesMobilized[] =
                $resourceMap[$resource] ?? $resource;
        }

        $contributors = [];
        foreach ($participants as $participant) {
            $contributors[] = [
                'user_id' => (int) $participant['user_id'],
                'name' => (string) $participant['full_name'],
                'email' => (string) $participant['email'],
                'mobile' =>
                    (string) ($participant['phone'] ?? ''),
                'role' => (string) $participant['participant_role'],
                'is_primary' =>
                    $participant['participant_role'] === 'OWNER',
                'is_coordinator' =>
                    $participant['participant_role'] === 'OWNER',
            ];
        }

        $academicYear = null;
        $startDate =
            $request['proposed_start_date'] ?? null;
        if (is_string($startDate) && $startDate !== '') {
            $year = (int) substr($startDate, 0, 4);
            $month = (int) substr($startDate, 5, 2);
            $academicYear = $month >= 8
                ? sprintf('%d/%d', $year, $year + 1)
                : sprintf('%d/%d', $year - 1, $year);
        }

        $primarySdg = $sdgGoals[0] ?? null;
        $secondarySdgs = array_values(
            array_slice($sdgGoals, 1)
        );

        return [
            'approval_request_id' =>
                (string) $request['request_code'],
            'requester_name' =>
                $request['requester_name_snapshot'] ?? null,
            'requester_email' =>
                $request['requester_email_snapshot'] ?? null,
            'requester_mobile' =>
                $request['requester_mobile'] ?? null,
            'requester_type' =>
                $request['requester_type'] ?? null,
            'requester_type_other' =>
                $request['requester_type_other'] ?? null,
            'requester_position' =>
                $request['requester_position_snapshot'] ?? null,
            'requester_entity' =>
                $request['requester_entity_snapshot'] ?? null,
            'requester_department' =>
                $request['requester_department_snapshot'] ?? null,
            'related_agreement' =>
                $this->databaseBoolean(
                    $request['has_related_agreement']
                        ?? (
                            $request['related_agreement_id']
                                !== null
                        )
                ),
            'related_agreement_id' =>
                $request['related_agreement_id'] !== null
                    ? (int) $request['related_agreement_id']
                    : null,
            'relation_notes' => null,
            'initiative_number' => null,
            'title' => (string) $request['title'],
            'initiative_type' =>
                (string) $request['initiative_type'],
            'initiative_type_other' =>
                $request['primary_type_other'] ?? null,
            'secondary_initiative_types' =>
                $secondaryTypes,
            'entity' =>
                $request['requester_entity_snapshot']
                ?? $request['requester_department_snapshot']
                ?? null,
            'external_entities' =>
                $request['external_partner_name'] ?? null,
            'provider_categories' => $providerCategories,
            'department_unit' =>
                $request['requester_department_snapshot']
                ?? null,
            'department_unit_other' => null,
            'department_within_college' => null,
            'contributors' => $contributors,
            'implementation_participants' => [],
            'start_date' => $startDate,
            'end_date' =>
                $request['proposed_end_date'] ?? null,
            'activity_status' => 'PLANNED',
            'activity_recurrence' => 'ONE_TIME',
            'academic_year' => $academicYear,
            'duration_hours' => null,
            'location_mode' =>
                $request['implementation_scope'] ?? null,
            'implementation_scope_other' =>
                $request['implementation_scope_other'] ?? null,
            'proposed_venue' =>
                $request['proposed_venue'] ?? null,
            'proposed_venue_place_id' =>
                $request['proposed_venue_place_id'] ?? null,
            'proposed_venue_name' =>
                $request['proposed_venue_name'] ?? null,
            'proposed_venue_latitude' =>
                $request['proposed_venue_latitude'] ?? null,
            'proposed_venue_longitude' =>
                $request['proposed_venue_longitude'] ?? null,
            'proposed_venue_country_code' =>
                $request['proposed_venue_country_code'] ?? null,
            'outside_location' =>
                $request['proposed_venue'] ?? null,
            'has_external_partner' =>
                $this->databaseBoolean(
                    $request['has_external_partner'] ?? false
                ),
            'external_partner_name' =>
                $request['external_partner_name'] ?? null,
            'external_partner_role' =>
                $request['external_partner_role'] ?? null,
            'international_participation' => false,
            'international_countries' => [],
            'international_participants' => null,
            'international_partner' => null,
            'international_partner_type' => null,
            'international_collaboration_nature' => [],
            'initiative_descriptors' => [],
            'description' =>
                $request['description'] ?? null,
            'objectives' =>
                $request['objective'] ?? null,
            'expected_impact' =>
                $request['expected_impact'] ?? null,
            'beneficiaries' =>
                $request['beneficiaries'] ?? null,
            'target_groups' => $targetGroups,
            'target_group_other' =>
                $request['target_group_other'] ?? null,
            'expected_participants' =>
                $request['expected_participants'] ?? null,
            'male_count' => 0,
            'female_count' => 0,
            'unspecified_count' =>
                $request['expected_participants'] ?? 0,
            'beneficiary_count_basis' => 'ESTIMATED',
            'youth_18_35' => null,
            'resources_mobilized_options' =>
                array_values(
                    array_unique($resourcesMobilized)
                ),
            'resources_mobilized_other' =>
                $request['resource_other'] ?? null,
            'expected_budget' =>
                $request['estimated_budget'] ?? null,
            'internal_funding_bhd' =>
                $request['estimated_budget'] ?? null,
            'in_kind_support_bhd' => null,
            'external_funding_amount' => null,
            'external_funding_currency' => null,
            'funding_entity' => null,
            'training_hours' => null,
            'trainees_count' => null,
            'volunteers_count' => null,
            'volunteer_hours_per_person' => null,
            'direct_outputs' => null,
            'societal_impact' =>
                $request['expected_impact'] ?? null,
            'ranking_framework' => 'NONE',
            'the_areas' => [],
            'qs_categories' => [],
            'environmental_impact_types' => [],
            'environmental_before_value' => null,
            'environmental_after_value' => null,
            'environmental_improvement_value' => null,
            'environmental_unit' => null,
            'environmental_measurement_basis' => null,
            'environmental_data_source' => null,
            'environmental_impact' => null,
            'supports_sdg' =>
                $this->databaseBoolean(
                    $request['supports_sdg'] ?? false
                ),
            'primary_sdg' => $primarySdg,
            'secondary_sdgs' => $secondarySdgs,
            'needs_media_support' =>
                $this->databaseBoolean(
                    $request['needs_media_support'] ?? false
                ),
            'publication_status' =>
                $this->databaseBoolean(
                    $request['needs_media_support'] ?? false
                )
                    ? 'IN_PROGRESS'
                    : 'NOT_PLANNED',
            'media_coverage_type' => null,
            'media_outlet_name' => null,
            'media_headline' => null,
            'media_publication_date' => null,
            'news_link' => null,
            'tv_channel' => null,
            'tv_program' => null,
            'tv_interview_topic' => null,
            'tv_interviewer' => null,
            'tv_uob_representatives' => null,
            'tv_interview_date' => null,
            'tv_duration_minutes' => null,
            'tv_broadcast_status' => null,
            'tv_broadcast_scope' => null,
            'tv_interview_language' => null,
            'tv_interview_link' => null,
            'tv_interview_highlights' => null,
            'evidence_types' => [],
            'evidence_document_type' => null,
            'evidence_date' => null,
            'evidence_owner' => null,
            'evidence_public_access' => null,
            'evidence_urls' => [],
            'evidence_explanation' => null,
            'public_sharing' => null,
            'notes_entity' => null,
            'declaration_confirmed' =>
                $this->databaseBoolean(
                    $request['declaration_confirmed']
                        ?? true
                ),
            'excluded_request_attachment_ids' => [],
        ];
    }

    private function normalizePayload(
        array $payload,
        bool $forFinalization
    ): array {
        $scalarFields = [
            'approval_request_id',
            'legacy_approval_date',
            'requester_name',
            'requester_email',
            'requester_mobile',
            'requester_type',
            'requester_type_other',
            'requester_position',
            'requester_entity',
            'requester_department',
            'initiative_number',
            'title',
            'initiative_type',
            'initiative_type_other',
            'entity',
            'external_entities',
            'department_unit',
            'department_unit_other',
            'department_within_college',
            'start_date',
            'end_date',
            'activity_status',
            'activity_recurrence',
            'academic_year',
            'duration_hours',
            'location_mode',
            'implementation_scope_other',
            'proposed_venue',
            'outside_location',
            'external_partner_name',
            'external_partner_role',
            'international_participants',
            'international_partner',
            'international_partner_type',
            'description',
            'objectives',
            'expected_impact',
            'beneficiaries',
            'target_group_other',
            'expected_participants',
            'male_count',
            'female_count',
            'unspecified_count',
            'beneficiary_count_basis',
            'youth_18_35',
            'resources_mobilized_other',
            'expected_budget',
            'internal_funding_bhd',
            'in_kind_support_bhd',
            'external_funding_amount',
            'external_funding_currency',
            'funding_entity',
            'training_hours',
            'trainees_count',
            'volunteers_count',
            'volunteer_hours_per_person',
            'direct_outputs',
            'societal_impact',
            'ranking_framework',
            'environmental_before_value',
            'environmental_after_value',
            'environmental_improvement_value',
            'environmental_unit',
            'environmental_measurement_basis',
            'environmental_data_source',
            'environmental_impact',
            'primary_sdg',
            'publication_status',
            'media_coverage_type',
            'media_outlet_name',
            'media_headline',
            'media_publication_date',
            'news_link',
            'tv_channel',
            'tv_program',
            'tv_interview_topic',
            'tv_interviewer',
            'tv_uob_representatives',
            'tv_interview_date',
            'tv_duration_minutes',
            'tv_broadcast_status',
            'tv_broadcast_scope',
            'tv_interview_language',
            'tv_interview_link',
            'tv_interview_highlights',
            'evidence_document_type',
            'evidence_date',
            'evidence_owner',
            'evidence_public_access',
            'evidence_explanation',
            'public_sharing',
            'notes_entity',
            'relation_notes',
        ];
        $arrayFields = [
            'secondary_initiative_types',
            'provider_categories',
            'international_countries',
            'international_collaboration_nature',
            'initiative_descriptors',
            'target_groups',
            'resources_mobilized_options',
            'the_areas',
            'qs_categories',
            'environmental_impact_types',
            'secondary_sdgs',
            'evidence_types',
            'evidence_urls',
        ];

        $data = [];

        foreach ($scalarFields as $field) {
            $data[$field] = $this->nullableText(
                $payload[$field] ?? null
            );
        }

        foreach ($arrayFields as $field) {
            $data[$field] = $this->normalizeStringArray(
                $payload[$field] ?? []
            );
        }

        $data['related_agreement'] =
            $this->nullableBoolean(
                $payload['related_agreement'] ?? null
            );
        $data['related_agreement_id'] =
            $this->nullableInteger(
                $payload['related_agreement_id'] ?? null
            );
        $data['has_external_partner'] =
            $this->nullableBoolean(
                $payload['has_external_partner'] ?? null
            );
        $data['needs_media_support'] =
            $this->nullableBoolean(
                $payload['needs_media_support'] ?? null
            );
        $data['international_participation'] =
            $this->nullableBoolean(
                $payload['international_participation']
                    ?? null
            );
        $data['supports_sdg'] =
            $this->nullableBoolean(
                $payload['supports_sdg'] ?? null
            );
        $data['declaration_confirmed'] =
            $this->nullableBoolean(
                $payload['declaration_confirmed']
                    ?? false
            ) ?? false;
        $data['excluded_request_attachment_ids'] =
            $this->normalizeIntegerArray(
                $payload['excluded_request_attachment_ids']
                    ?? []
            );
        $data['contributors'] = $this->normalizePeople(
            $payload['contributors'] ?? []
        );
        $data['implementation_participants'] =
            $this->normalizePeople(
                $payload['implementation_participants']
                    ?? []
            );

        if ($data['contributors'] !== []) {
            $hasPrimary = array_filter(
                $data['contributors'],
                static fn (array $person): bool =>
                    !empty($person['is_primary'])
            ) !== [];
            $hasCoordinator = array_filter(
                $data['contributors'],
                static fn (array $person): bool =>
                    !empty($person['is_coordinator'])
            ) !== [];

            if (!$hasPrimary) {
                $data['contributors'][0]['is_primary'] = true;
            }
            if (!$hasCoordinator) {
                $data['contributors'][0]['is_coordinator'] = true;
            }
        }

        foreach (
            [
                'duration_hours',
                'expected_participants',
                'international_participants',
                'male_count',
                'female_count',
                'unspecified_count',
                'expected_budget',
                'internal_funding_bhd',
                'in_kind_support_bhd',
                'external_funding_amount',
                'training_hours',
                'trainees_count',
                'volunteers_count',
                'volunteer_hours_per_person',
                'environmental_before_value',
                'environmental_after_value',
                'environmental_improvement_value',
                'tv_duration_minutes',
            ] as $numericField
        ) {
            $data[$numericField] =
                $this->nullableNonNegativeNumber(
                    $data[$numericField],
                    str_replace(
                        '_',
                        ' ',
                        $numericField
                    )
                );
        }

        $data['legacy_approval_date'] =
            $this->nullableDate(
                $data['legacy_approval_date']
            );
        $data['start_date'] =
            $this->nullableDate($data['start_date']);
        $data['end_date'] =
            $this->nullableDate($data['end_date']);
        $data['media_publication_date'] =
            $this->nullableDate(
                $data['media_publication_date']
            );
        $data['tv_interview_date'] =
            $this->nullableDate(
                $data['tv_interview_date']
            );
        $data['evidence_date'] =
            $this->nullableDate(
                $data['evidence_date']
            );

        if (
            $data['start_date'] !== null
            && $data['end_date'] !== null
            && $data['end_date'] < $data['start_date']
        ) {
            throw new InvalidArgumentException(
                'The Initiative end date cannot be before the start date.'
            );
        }

        if ($data['related_agreement'] !== true) {
            $data['related_agreement_id'] = null;
            $data['relation_notes'] = null;
        }

        if ($data['has_external_partner'] !== true) {
            $data['external_partner_name'] = null;
            $data['external_partner_role'] = null;
        }

        if ($data['international_participation'] !== true) {
            $data['international_countries'] = [];
            $data['international_participants'] = null;
            $data['international_partner'] = null;
            $data['international_partner_type'] = null;
            $data['international_collaboration_nature'] = [];
        }

        if ($data['supports_sdg'] !== true) {
            $data['primary_sdg'] = null;
            $data['secondary_sdgs'] = [];
        }

        if ($forFinalization) {
            $missing = [];

            foreach (
                [
                    'title' => 'Initiative title',
                    'initiative_type' => 'Initiative type',
                    'entity' => 'implementing entity',
                    'start_date' => 'start date',
                    'activity_status' => 'activity status',
                    'location_mode' => 'location mode',
                    'description' => 'description',
                    'objectives' => 'objectives',
                ] as $field => $label
            ) {
                if ($data[$field] === null) {
                    $missing[] = $label;
                }
            }

            if ($data['target_groups'] === []) {
                $missing[] = 'target groups';
            }

            if ($data['contributors'] === []) {
                $missing[] = 'at least one responsible person';
            }

            if (
                $data['related_agreement'] === true
                && $data['related_agreement_id'] === null
            ) {
                $missing[] = 'related Agreement';
            }

            if (
                $data['supports_sdg'] === true
                && $data['primary_sdg'] === null
            ) {
                $missing[] =
                    'primary Sustainable Development Goal';
            }

            if (!$data['declaration_confirmed']) {
                $missing[] = 'final declaration';
            }

            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Complete the following before finalizing: '
                    . implode(', ', $missing)
                    . '.'
                );
            }
        }

        return $data;
    }

    /**
     * Preserve the verified Google Maps location captured
     * in the approved Initiative request.
     */
    private function withApprovedLocationData(
        array $data,
        array $request
    ): array {
        foreach (
            [
                'proposed_venue_place_id',
                'proposed_venue_name',
                'proposed_venue_latitude',
                'proposed_venue_longitude',
                'proposed_venue_country_code',
            ] as $field
        ) {
            $data[$field] = $request[$field] ?? null;
        }

        return $data;
    }

    private function upsertDraft(
        int $requestId,
        int $userId,
        array $data,
        string $status
    ): void {
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
                related_agreement_id =
                    EXCLUDED.related_agreement_id,
                form_data = EXCLUDED.form_data,
                status = EXCLUDED.status,
                updated_at = CURRENT_TIMESTAMP"
        );
        $statement->execute([
            'request_id' => $requestId,
            'prepared_by' => $userId,
            'title' => $data['title'] ?? '',
            'description' => $data['description'],
            'initiative_type' =>
                $data['initiative_type'] ?? '',
            'related_agreement_id' =>
                $data['related_agreement_id'],
            'form_data' => json_encode(
                $data,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            'status' => $status,
        ]);
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
                'This approved Initiative request is not available for finalization.'
            );
        }

        if (!$this->canConvert($requestId, $userId)) {
            throw new DomainException(
                'You are not allowed to finalize this Initiative request.'
            );
        }

        return $request;
    }


    private function legacyDraftRow(
        int $draftId,
        int $userId,
        bool $forUpdate
    ): array {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $statement = $this->db->prepare(
            "SELECT draft.*
             FROM initiative_legacy_drafts draft
             WHERE draft.draft_id = :draft_id
               AND draft.status = 'DRAFT'
               AND (
                    draft.prepared_by = :prepared_by
                    OR EXISTS (
                        SELECT 1
                        FROM user_roles user_role
                        JOIN roles role
                          ON role.role_id = user_role.role_id
                        WHERE user_role.user_id = :administrator_user_id
                          AND role.role_name = 'System Administrator'
                    )
               )" . $lock
        );
        $statement->execute([
            'draft_id' => $draftId,
            'prepared_by' => $userId,
            'administrator_user_id' => $userId,
        ]);
        $row = $statement->fetch();

        if (!$row) {
            throw new DomainException(
                'The existing Initiative draft is not available.'
            );
        }

        return $row;
    }

    private function legacyPrefill(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                user_account.user_id,
                CONCAT(
                    user_account.first_name,
                    ' ',
                    user_account.last_name
                ) AS full_name,
                user_account.email,
                user_account.phone
             FROM users user_account
             WHERE user_account.user_id = :user_id"
        );
        $statement->execute(['user_id' => $userId]);
        $user = $statement->fetch() ?: [];

        return [
            'approval_request_id' => null,
            'legacy_approval_date' => null,
            'initiative_number' => null,
            'requester_name' => $user['full_name'] ?? null,
            'requester_email' => $user['email'] ?? null,
            'requester_mobile' => $user['phone'] ?? null,
            'requester_type' => null,
            'requester_position' => null,
            'requester_entity' => null,
            'requester_department' => null,
            'related_agreement' => false,
            'related_agreement_id' => null,
            'title' => null,
            'initiative_type' => null,
            'entity' => null,
            'contributors' => [[
                'user_id' => $userId,
                'name' => $user['full_name'] ?? null,
                'email' => $user['email'] ?? null,
                'mobile' => $user['phone'] ?? null,
                'role' => 'OWNER',
                'is_primary' => true,
                'is_coordinator' => true,
            ]],
            'implementation_participants' => [],
            'activity_status' => 'COMPLETED',
            'activity_recurrence' => 'ONE_TIME',
            'target_groups' => [],
            'resources_mobilized_options' => [],
            'supports_sdg' => false,
            'secondary_sdgs' => [],
            'needs_media_support' => false,
            'declaration_confirmed' => false,
            'excluded_request_attachment_ids' => [],
        ];
    }

    private function legacyAttachments(int $draftId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                original_name,
                file_extension,
                mime_type,
                file_size_bytes,
                uploaded_at
             FROM initiative_legacy_attachments
             WHERE draft_id = :draft_id
             ORDER BY uploaded_at, attachment_id"
        );
        $statement->execute(['draft_id' => $draftId]);
        return $statement->fetchAll();
    }

    private function copyLegacyAttachments(
        int $draftId,
        int $initiativeId,
        int $addedBy
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_attachments (
                initiative_id,
                source_legacy_attachment_id,
                original_name,
                stored_name,
                storage_path,
                file_extension,
                mime_type,
                file_size_bytes,
                added_by
             )
             SELECT
                :initiative_id,
                attachment.attachment_id,
                attachment.original_name,
                attachment.stored_name,
                attachment.storage_path,
                attachment.file_extension,
                attachment.mime_type,
                attachment.file_size_bytes,
                :added_by
             FROM initiative_legacy_attachments attachment
             WHERE attachment.draft_id = :draft_id
             ON CONFLICT (initiative_id, storage_path)
             DO NOTHING"
        );
        $statement->execute([
            'initiative_id' => $initiativeId,
            'added_by' => $addedBy,
            'draft_id' => $draftId,
        ]);
    }

    private function requestParticipants(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                participant.user_id,
                participant.participant_role,
                CONCAT(
                    user_account.first_name,
                    ' ',
                    user_account.last_name
                ) AS full_name,
                user_account.email,
                user_account.phone
             FROM (
                SELECT
                    requester_id AS user_id,
                    'OWNER' AS participant_role
                FROM initiative_requests
                WHERE request_id = :owner_request_id

                UNION ALL

                SELECT
                    user_id,
                    member_role AS participant_role
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

    private function requestAttachments(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                original_name,
                file_extension,
                mime_type,
                file_size_bytes,
                uploaded_at
             FROM initiative_request_attachments
             WHERE request_id = :request_id
             ORDER BY uploaded_at, attachment_id"
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    private function conversionAttachments(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                original_name,
                file_extension,
                mime_type,
                file_size_bytes,
                uploaded_at
             FROM initiative_conversion_attachments
             WHERE request_id = :request_id
             ORDER BY uploaded_at, attachment_id"
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    private function saveFinalPeople(
        int $initiativeId,
        array $people,
        int $ownerUserId,
        int $addedBy
    ): void {
        $personInsert = $this->db->prepare(
            "INSERT INTO initiative_people (
                initiative_id,
                user_id,
                full_name,
                email,
                mobile,
                role_label,
                is_primary,
                is_coordinator,
                created_by
             ) VALUES (
                :initiative_id,
                :user_id,
                :full_name,
                :email,
                :mobile,
                :role_label,
                :is_primary,
                :is_coordinator,
                :created_by
             )
             ON CONFLICT (initiative_id, user_id)
             WHERE user_id IS NOT NULL
             DO UPDATE SET
                full_name = EXCLUDED.full_name,
                email = EXCLUDED.email,
                mobile = EXCLUDED.mobile,
                role_label = EXCLUDED.role_label,
                is_primary = EXCLUDED.is_primary,
                is_coordinator = EXCLUDED.is_coordinator,
                created_by = EXCLUDED.created_by"
        );

        $participantInsert = $this->db->prepare(
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
             DO UPDATE SET
                participant_role = EXCLUDED.participant_role"
        );

        /*
         * Keep the approved request owner as the Initiative owner for access
         * control, while the editable final-form people table preserves every
         * final name, contact value, responsibility, and coordinator choice.
         */
        $participantInsert->execute([
            'initiative_id' => $initiativeId,
            'user_id' => $ownerUserId,
            'participant_role' => 'OWNER',
            'added_by' => $addedBy,
        ]);

        foreach ($people as $person) {
            $userId = $person['user_id'] ?? null;
            $roleLabel = trim((string) ($person['role'] ?? ''));

            $personInsert->execute([
                'initiative_id' => $initiativeId,
                'user_id' => $userId,
                'full_name' => $person['name'] ?? null,
                'email' => $person['email'] ?? null,
                'mobile' => $person['mobile'] ?? null,
                'role_label' => $roleLabel !== ''
                    ? $roleLabel
                    : null,
                'is_primary' => !empty($person['is_primary'])
                    ? 'true'
                    : 'false',
                'is_coordinator' =>
                    !empty($person['is_coordinator'])
                        ? 'true'
                        : 'false',
                'created_by' => $addedBy,
            ]);

            if ($userId === null) {
                continue;
            }

            $normalizedRole = strtoupper(
                str_replace([' ', '-'], '_', $roleLabel)
            );
            $participantRole = (int) $userId === $ownerUserId
                ? 'OWNER'
                : (
                    $normalizedRole === 'CO_OWNER'
                        ? 'CO_OWNER'
                        : 'COLLABORATOR'
                );

            $participantInsert->execute([
                'initiative_id' => $initiativeId,
                'user_id' => (int) $userId,
                'participant_role' => $participantRole,
                'added_by' => $addedBy,
            ]);
        }
    }

    private function copyAttachments(
        int $requestId,
        int $initiativeId,
        int $addedBy,
        array $excludedRequestAttachmentIds
    ): void {
        $insertRequestFiles = $this->db->prepare(
            "INSERT INTO initiative_attachments (
                initiative_id,
                source_request_attachment_id,
                original_name,
                stored_name,
                storage_path,
                file_extension,
                mime_type,
                file_size_bytes,
                added_by
             )
             SELECT
                :initiative_id,
                attachment.attachment_id,
                attachment.original_name,
                attachment.stored_name,
                attachment.storage_path,
                attachment.file_extension,
                attachment.mime_type,
                attachment.file_size_bytes,
                :added_by
             FROM initiative_request_attachments attachment
             WHERE attachment.request_id = :request_id
               AND NOT (
                    attachment.attachment_id =
                    ANY(
                        CAST(
                            :excluded_attachment_ids
                            AS BIGINT[]
                        )
                    )
               )
             ON CONFLICT (initiative_id, storage_path)
             DO NOTHING"
        );
        $insertRequestFiles->execute([
            'initiative_id' => $initiativeId,
            'added_by' => $addedBy,
            'request_id' => $requestId,
            'excluded_attachment_ids' =>
                $this->postgresIntegerArray(
                    $excludedRequestAttachmentIds
                ),
        ]);

        $insertConversionFiles = $this->db->prepare(
            "INSERT INTO initiative_attachments (
                initiative_id,
                source_conversion_attachment_id,
                original_name,
                stored_name,
                storage_path,
                file_extension,
                mime_type,
                file_size_bytes,
                added_by
             )
             SELECT
                :initiative_id,
                attachment.attachment_id,
                attachment.original_name,
                attachment.stored_name,
                attachment.storage_path,
                attachment.file_extension,
                attachment.mime_type,
                attachment.file_size_bytes,
                :added_by
             FROM initiative_conversion_attachments attachment
             WHERE attachment.request_id = :request_id
             ON CONFLICT (initiative_id, storage_path)
             DO NOTHING"
        );
        $insertConversionFiles->execute([
            'initiative_id' => $initiativeId,
            'added_by' => $addedBy,
            'request_id' => $requestId,
        ]);
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
                $eventData === []
                    ? new stdClass()
                    : $eventData,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    private function normalizeUploadedFiles(array $files): array
    {
        if (
            !isset($files['name'])
            || !is_array($files['name'])
        ) {
            return [];
        }

        $normalized = [];

        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? null,
                'tmp_name' =>
                    $files['tmp_name'][$index] ?? null,
                'error' => $files['error'][$index]
                    ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function normalizePeople(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $person) {
            if (!is_array($person)) {
                continue;
            }

            $normalized = [
                'user_id' =>
                    $this->nullableInteger(
                        $person['user_id'] ?? null
                    ),
                'name' =>
                    $this->nullableText(
                        $person['name'] ?? null
                    ),
                'email' =>
                    $this->nullableText(
                        $person['email'] ?? null
                    ),
                'mobile' =>
                    $this->nullableText(
                        $person['mobile'] ?? null
                    ),
                'role' =>
                    $this->nullableText(
                        $person['role'] ?? null
                    ),
                'is_primary' =>
                    $this->nullableBoolean(
                        $person['is_primary'] ?? false
                    ) ?? false,
                'is_coordinator' =>
                    $this->nullableBoolean(
                        $person['is_coordinator'] ?? false
                    ) ?? false,
            ];

            if (
                $normalized['name'] === null
                && $normalized['email'] === null
            ) {
                continue;
            }

            $result[] = $normalized;
        }

        return array_values($result);
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

    private function decodeJsonArray(mixed $value): array
    {
        $decoded = $this->decodeJsonObject($value);
        return array_is_list($decoded)
            ? array_values($decoded)
            : [];
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizeIntegerArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $integer = filter_var(
                $item,
                FILTER_VALIDATE_INT
            );
            if ($integer !== false && $integer > 0) {
                $result[] = (int) $integer;
            }
        }

        return array_values(array_unique($result));
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

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT
        );

        return $integer === false || $integer < 1
            ? null
            : (int) $integer;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(
            trim((string) $value)
        );

        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    private function databaseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['1', 't', 'true', 'yes', 'y'],
            true
        );
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d',
            $value
        );

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(
                'Dates must use the YYYY-MM-DD format.'
            );
        }

        return $value;
    }

    private function nullableNonNegativeNumber(
        mixed $value,
        string $label
    ): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(
                ucfirst($label)
                . ' must be zero or a positive number.'
            );
        }

        return rtrim(
            rtrim(
                number_format(
                    (float) $value,
                    3,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }

    private function postgresIntegerArray(array $values): string
    {
        if ($values === []) {
            return '{}';
        }

        return '{'
            . implode(
                ',',
                array_map('intval', $values)
            )
            . '}';
    }
}