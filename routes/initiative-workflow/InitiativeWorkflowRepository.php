<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/InitiativeAssigneeResolver.php';
require_once __DIR__ . '/InitiativeConversionRepository.php';
require_once dirname(__DIR__, 2) . '/services/ConfigurableInitiativeWorkflowService.php';

final class InitiativeWorkflowRepository
{
    private PDO $db;
    private InitiativeAssigneeResolver $assigneeResolver;
    private InitiativeConversionRepository $conversionRepository;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->assigneeResolver = new InitiativeAssigneeResolver($this->db);
        $this->conversionRepository = new InitiativeConversionRepository($this->db);
    }

    public function canAdministerInitiatives(int $userId): bool
    {
        return $this->isSystemAdministrator($userId);
    }

    public function requesterFormProfile(int $userId): array
    {
        $profile = $this->requesterProfile($userId);
        $roleKey = $this->isSystemAdministrator($userId)
            ? 'SYSTEM_ADMINISTRATOR'
            : (string) $profile['role_key'];

        return [
            'user_id' => $userId,
            'full_name' => $profile['full_name'],
            'email' => $profile['email'],
            'mobile' => $profile['mobile'],
            'position' => $profile['position_name'],
            'entity' => $profile['entity_name'],
            'department' => $profile['department_name'],
            'role_key' => $roleKey,
            'suggested_requester_type' =>
                $this->suggestedRequesterType($roleKey),
        ];
    }

    public function visibleRequests(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT DISTINCT
                request.request_id,
                request.request_code,
                to_jsonb(request)::text AS request_search_blob,
                request.title,
                request.description,
                request.initiative_type,
                request.objective,
                request.expected_impact,
                request.beneficiaries,
                request.proposed_start_date,
                request.proposed_end_date,
                request.requester_role_key,
                request.status,
                request.created_at,
                request.updated_at,
                COALESCE(
                    request.requester_name_snapshot,
                    CONCAT(
                        requester.first_name,
                        ' ',
                        requester.last_name
                    )
                ) AS requester_name,
                stage.stage_label AS current_stage_label,
                EXTRACT(
                    EPOCH FROM (
                        CURRENT_TIMESTAMP
                        - COALESCE(stage.received_at, stage.created_at)
                    )
                )::BIGINT AS waiting_seconds,
                (
                    request.status IN ('APPROVED', 'CONVERTING')
                    AND request.initiative_id IS NULL
                    AND (
                        request.requester_id =
                            :conversion_requester_user_id
                        OR COALESCE(
                            member.can_convert_after_approval,
                            FALSE
                        ) = TRUE
                        OR EXISTS (
                            SELECT 1
                            FROM user_roles conversion_user_role
                            JOIN roles conversion_role
                              ON conversion_role.role_id =
                                 conversion_user_role.role_id
                             AND conversion_role.role_name =
                                 'System Administrator'
                            WHERE conversion_user_role.user_id =
                                  :conversion_administrator_user_id
                        )
                    )
                ) AS can_convert
             FROM initiative_requests request
             JOIN users requester
               ON requester.user_id = request.requester_id
             LEFT JOIN initiative_request_members member
               ON member.request_id = request.request_id
              AND member.user_id = :member_user_id
             LEFT JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.stage_order = request.current_stage_order
             WHERE request.requester_id = :requester_user_id
                OR request.current_assignee_id = :assignee_user_id
                OR member.user_id IS NOT NULL
                OR EXISTS (
                    SELECT 1
                    FROM initiative_request_stages acted_stage
                    WHERE acted_stage.request_id = request.request_id
                      AND acted_stage.acted_by_user_id = :acted_user_id
                )
               OR EXISTS (
                   SELECT 1
                   FROM initiative_request_stages behalf_stage
                   WHERE behalf_stage.request_id = request.request_id
                     AND behalf_stage.acted_on_behalf_of_user_id =
                         :behalf_user_id
               )
                OR EXISTS (
                    SELECT 1
                    FROM initiative_request_stages assigned_stage
                    WHERE assigned_stage.request_id = request.request_id
                      AND assigned_stage.assigned_user_id = :historical_assignee_user_id
                      AND assigned_stage.cycle_number =
                          request.revision_cycle
                      AND assigned_stage.stage_order <=
                          request.current_stage_order
                      AND assigned_stage.status <> 'PENDING'
                )
                OR EXISTS (
                    SELECT 1
                    FROM initiative_request_stages office_stage
                    JOIN user_positions delegate_position
                      ON delegate_position.user_id = :delegate_user_id
                     AND delegate_position.unit_id =
                         office_stage.responsible_unit_id
                     AND delegate_position.is_active = TRUE
                     AND (
                          delegate_position.end_date IS NULL
                          OR delegate_position.end_date >= CURRENT_DATE
                     )
                    JOIN positions delegate_role_position
                      ON delegate_role_position.position_id =
                         delegate_position.position_id
                     AND delegate_role_position.name =
                         CASE office_stage.stage_key
                             WHEN 'VICE_PRESIDENT'
                                 THEN 'Vice President Office Delegate'
                             WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                 THEN 'President Office Delegate'
                             ELSE '__NO_DELEGATE_POSITION__'
                         END
                    JOIN users delegate_user
                      ON delegate_user.user_id =
                         delegate_position.user_id
                     AND delegate_user.is_active = TRUE
                    JOIN user_roles delegate_user_role
                      ON delegate_user_role.user_id =
                         delegate_user.user_id
                    JOIN role_permissions delegate_role_permission
                      ON delegate_role_permission.role_id =
                         delegate_user_role.role_id
                    JOIN permissions delegate_permission
                      ON delegate_permission.permission_id =
                         delegate_role_permission.permission_id
                     AND delegate_permission.permission_code =
                         'APPROVE_INITIATIVE'
                    WHERE office_stage.request_id =
                          request.request_id
                      AND office_stage.cycle_number =
                          request.revision_cycle
                      AND office_stage.status <> 'PENDING'
                      AND office_stage.is_office_delegable = TRUE
                )
                OR EXISTS (
                    SELECT 1
                    FROM user_roles administrator_user_role
                    JOIN roles administrator_role
                      ON administrator_role.role_id =
                         administrator_user_role.role_id
                     AND administrator_role.role_name =
                         'System Administrator'
                    WHERE administrator_user_role.user_id =
                          :administrator_user_id
                )
             ORDER BY request.updated_at DESC, request.request_id DESC"
        );
        $statement->execute([
            'member_user_id' => $userId,
            'requester_user_id' => $userId,
            'assignee_user_id' => $userId,
            'acted_user_id' => $userId,
            'behalf_user_id' => $userId,
            'historical_assignee_user_id' => $userId,
            'delegate_user_id' => $userId,
            'administrator_user_id' => $userId,
            'conversion_requester_user_id' => $userId,
            'conversion_administrator_user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    public function adminMonitoring(
        int $userId,
        int $limit = 100
    ): array {
        if (!$this->isSystemAdministrator($userId)) {
            throw new DomainException(
                'Only a System Administrator can access Initiative monitoring.'
            );
        }

        $limit = max(10, min($limit, 250));

        $summaryStatement = $this->db->query(
            "SELECT
                COUNT(*) AS total_requests,
                COUNT(*) FILTER (
                    WHERE status = 'UNDER_REVIEW'
                ) AS under_review,
                COUNT(*) FILTER (
                    WHERE status = 'REVISION_REQUIRED'
                ) AS revision_required,
                COUNT(*) FILTER (
                    WHERE status = 'APPROVED'
                ) AS approved,
                COUNT(*) FILTER (
                    WHERE status = 'REJECTED'
                ) AS rejected,
                COUNT(*) FILTER (
                    WHERE status = 'CONVERTING'
                ) AS converting,
                COUNT(*) FILTER (
                    WHERE status = 'CONVERTED'
                ) AS converted
             FROM initiative_requests"
        );

        $summary = $summaryStatement->fetch() ?: [];

        $notificationStatement = $this->db->prepare(
            "SELECT
                notification.notification_id,
                notification.request_id,
                notification.recipient_user_id,
                CONCAT(
                    recipient.first_name,
                    ' ',
                    recipient.last_name
                ) AS recipient_name,
                notification.notification_type,
                notification.title,
                notification.message,
                notification.payload,
                notification.is_read,
                notification.read_at,
                notification.created_at,
                request.request_code,
                request.title AS request_title
             FROM initiative_notifications notification
             LEFT JOIN users recipient
               ON recipient.user_id =
                  notification.recipient_user_id
             LEFT JOIN initiative_requests request
               ON request.request_id =
                  notification.request_id
             ORDER BY
                notification.created_at DESC,
                notification.notification_id DESC
             LIMIT {$limit}"
        );
        $notificationStatement->execute();

        $eventStatement = $this->db->prepare(
            "SELECT
                event.event_id,
                event.request_id,
                event.request_stage_id,
                event.cycle_number,
                event.event_type,
                event.actor_user_id,
                CONCAT(
                    actor.first_name,
                    ' ',
                    actor.last_name
                ) AS actor_name,
                event.target_user_id,
                CONCAT(
                    target.first_name,
                    ' ',
                    target.last_name
                ) AS target_name,
                event.from_status,
                event.to_status,
                event.event_note,
                event.event_data,
                event.occurred_at,
                request.request_code,
                request.title AS request_title
             FROM initiative_request_events event
             LEFT JOIN initiative_requests request
               ON request.request_id = event.request_id
             LEFT JOIN users actor
               ON actor.user_id = event.actor_user_id
             LEFT JOIN users target
               ON target.user_id = event.target_user_id
             ORDER BY
                event.occurred_at DESC,
                event.event_id DESC
             LIMIT {$limit}"
        );
        $eventStatement->execute();

        return [
            'summary' => [
                'total_requests' =>
                    (int) ($summary['total_requests'] ?? 0),
                'under_review' =>
                    (int) ($summary['under_review'] ?? 0),
                'revision_required' =>
                    (int) ($summary['revision_required'] ?? 0),
                'approved' =>
                    (int) ($summary['approved'] ?? 0),
                'rejected' =>
                    (int) ($summary['rejected'] ?? 0),
                'converting' =>
                    (int) ($summary['converting'] ?? 0),
                'converted' =>
                    (int) ($summary['converted'] ?? 0),
            ],
            'requests' => $this->visibleRequests($userId),
            'notifications' => $notificationStatement->fetchAll(),
            'events' => $eventStatement->fetchAll(),
        ];
    }
    public function createRequest(int $userId, array $payload): int
    {
        $submit = filter_var(
            $payload['submit'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $data = $this->normalizeRequestPayload(
            $payload,
            $submit
        );
        $profile = $this->requesterProfile($userId);
        $status = $submit ? 'UNDER_REVIEW' : 'DRAFT';

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "INSERT INTO initiative_requests (
                    title,
                    description,
                    initiative_type,
                    objective,
                    expected_impact,
                    beneficiaries,
                    proposed_start_date,
                    proposed_end_date,
                    related_agreement_id,
                    requester_id,
                    requester_unit_id,
                    requester_role_key,
                    requester_name_snapshot,
                    requester_email_snapshot,
                    requester_mobile,
                    requester_type,
                    requester_type_other,
                    requester_position_snapshot,
                    requester_entity_snapshot,
                    requester_department_snapshot,
                    primary_type_other,
                    secondary_types,
                    target_groups,
                    target_group_other,
                    expected_participants,
                    implementation_scope,
                    implementation_scope_other,
                    proposed_venue,
                    proposed_venue_place_id,
                    proposed_venue_name,
                    proposed_venue_latitude,
                    proposed_venue_longitude,
                    proposed_venue_country_code,
                    implementation_country,
                    online_platform_name,
                    international_participation,
                    international_countries,
                    international_partner,
                    relationship_type,
                    has_related_agreement,
                    has_external_partner,
                    external_partner_name,
                    external_partner_country,
                    external_partner_role,
                    required_resources,
                    resource_other,
                    estimated_budget,
                    needs_media_support,
                    supports_sdg,
                    sdg_goals,
                    declaration_confirmed,
                    declaration_confirmed_at,
                    status,
                    submitted_at
                 ) VALUES (
                    :title,
                    :description,
                    :initiative_type,
                    :objective,
                    :expected_impact,
                    :beneficiaries,
                    CAST(:proposed_start_date AS DATE),
                    CAST(:proposed_end_date AS DATE),
                    CAST(:related_agreement_id AS BIGINT),
                    :requester_id,
                    CAST(:requester_unit_id AS BIGINT),
                    :requester_role_key,
                    :requester_name_snapshot,
                    :requester_email_snapshot,
                    :requester_mobile,
                    :requester_type,
                    :requester_type_other,
                    :requester_position_snapshot,
                    :requester_entity_snapshot,
                    :requester_department_snapshot,
                    :primary_type_other,
                    CAST(:secondary_types AS JSONB),
                    CAST(:target_groups AS JSONB),
                    :target_group_other,
                    CAST(:expected_participants AS INTEGER),
                    :implementation_scope,
                    :implementation_scope_other,
                    :proposed_venue,
                    :proposed_venue_place_id,
                    :proposed_venue_name,
                    CAST(:proposed_venue_latitude AS NUMERIC),
                    CAST(:proposed_venue_longitude AS NUMERIC),
                    :proposed_venue_country_code,
                    :implementation_country,
                    :online_platform_name,
                    CAST(:international_participation AS BOOLEAN),
                    CAST(:international_countries AS JSONB),
                    :international_partner,
                    :relationship_type,
                    CAST(:has_related_agreement AS BOOLEAN),
                    CAST(:has_external_partner AS BOOLEAN),
                    :external_partner_name,
                    :external_partner_country,
                    :external_partner_role,
                    CAST(:required_resources AS JSONB),
                    :resource_other,
                    CAST(:estimated_budget AS NUMERIC),
                    CAST(:needs_media_support AS BOOLEAN),
                    CAST(:supports_sdg AS BOOLEAN),
                    CAST(:sdg_goals AS JSONB),
                    CAST(:declaration_confirmed AS BOOLEAN),
                    :declaration_confirmed_at,
                    :status,
                    :submitted_at
                 )
                 RETURNING request_id"
            );
            $statement->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'initiative_type' => $data['initiative_type'],
                'objective' => $data['objective'],
                'expected_impact' => $data['expected_impact'],
                'beneficiaries' => $data['beneficiaries'],
                'proposed_start_date' =>
                    $data['proposed_start_date'],
                'proposed_end_date' =>
                    $data['proposed_end_date'],
                'related_agreement_id' =>
                    $data['related_agreement_id'],
                'requester_id' => $userId,
                'requester_unit_id' => $profile['unit_id'],
                'requester_role_key' =>
                    $this->isSystemAdministrator($userId)
                        ? 'SYSTEM_ADMINISTRATOR'
                        : $profile['role_key'],
                'requester_name_snapshot' =>
                    $profile['full_name'],
                'requester_email_snapshot' =>
                    $profile['email'],
                'requester_mobile' =>
                    $data['requester_mobile']
                    ?? $profile['mobile'],
                'requester_type' =>
                    $data['requester_type']
                    ?? $this->suggestedRequesterType(
                        (string) $profile['role_key']
                    ),
                'requester_type_other' =>
                    $data['requester_type_other'],
                'requester_position_snapshot' =>
                    $profile['position_name'],
                'requester_entity_snapshot' =>
                    $profile['entity_name'],
                'requester_department_snapshot' =>
                    $profile['department_name'],
                'primary_type_other' =>
                    $data['primary_type_other'],
                'secondary_types' =>
                    $this->jsonArray($data['secondary_types']),
                'target_groups' =>
                    $this->jsonArray($data['target_groups']),
                'target_group_other' =>
                    $data['target_group_other'],
                'expected_participants' =>
                    $data['expected_participants'],
                'implementation_scope' =>
                    $data['implementation_scope'],
                'implementation_scope_other' =>
                    $data['implementation_scope_other'],
                'proposed_venue' => $data['proposed_venue'],
                'proposed_venue_place_id' =>
                    $data['proposed_venue_place_id'],
                'proposed_venue_name' =>
                    $data['proposed_venue_name'],
                'proposed_venue_latitude' =>
                    $data['proposed_venue_latitude'],
                'proposed_venue_longitude' =>
                    $data['proposed_venue_longitude'],
                'proposed_venue_country_code' =>
                    $data['proposed_venue_country_code'],
                'implementation_country' =>
                    $data['implementation_country'],
                'online_platform_name' =>
                    $data['online_platform_name'],
                'international_participation' =>
                    $this->databaseBoolean(
                        $data['international_participation']
                    ),
                'international_countries' =>
                    $this->jsonArray($data['international_countries']),
                'international_partner' =>
                    $data['international_partner'],
                'relationship_type' =>
                    $data['relationship_type'],
                'has_related_agreement' =>
                    $this->databaseBoolean(
                        $data['has_related_agreement']
                    ),
                'has_external_partner' =>
                    $this->databaseBoolean(
                        $data['has_external_partner']
                    ),
                'external_partner_name' =>
                    $data['external_partner_name'],
                'external_partner_country' =>
                    $data['external_partner_country'],
                'external_partner_role' =>
                    $data['external_partner_role'],
                'required_resources' =>
                    $this->jsonArray(
                        $data['required_resources']
                    ),
                'resource_other' => $data['resource_other'],
                'estimated_budget' => $data['estimated_budget'],
                'needs_media_support' =>
                    $this->databaseBoolean(
                        $data['needs_media_support']
                    ),
                'supports_sdg' =>
                    $this->databaseBoolean(
                        $data['supports_sdg']
                    ),
                'sdg_goals' =>
                    $this->jsonArray($data['sdg_goals']),
                'declaration_confirmed' =>
                    $this->databaseBoolean(
                        $data['declaration_confirmed']
                    ),
                'declaration_confirmed_at' =>
                    $data['declaration_confirmed']
                        ? date('Y-m-d H:i:s')
                        : null,
                'status' => $status,
                'submitted_at' => $submit
                    ? date('Y-m-d H:i:s')
                    : null,
            ]);

            $requestId = (int) $statement->fetchColumn();
            $code = sprintf(
                'IR-%s-%06d',
                date('Y'),
                $requestId
            );

            $update = $this->db->prepare(
                'UPDATE initiative_requests
                 SET request_code = :request_code
                 WHERE request_id = :request_id'
            );
            $update->execute([
                'request_code' => $code,
                'request_id' => $requestId,
            ]);

            $this->replaceRequestAgreementLinks(
                $requestId,
                $data['related_agreement_ids']
            );

            $this->insertMembers(
                $requestId,
                $userId,
                is_array($payload['members'] ?? null)
                    ? $payload['members']
                    : []
            );

            $this->recordEvent(
                $requestId,
                $userId,
                $submit ? 'REQUEST_SUBMITTED' : 'DRAFT_CREATED',
                $submit
                    ? 'Initiative request submitted for approval.'
                    : 'Initiative request saved as a draft.'
            );

            if ($submit) {
                $this->createInitialStages(
                    $requestId,
                    $profile['role_key'],
                    $profile['unit_id']
                );

                $this->notifyRequestParticipants(
                    $requestId,
                    'REQUEST_SUBMITTED',
                    'Initiative request submitted',
                    sprintf(
                        '%s was submitted and entered the approval route.',
                        $code
                    )
                );
            }

            $this->db->commit();

            return $requestId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function updateDraft(
        int $requestId,
        int $userId,
        array $payload
    ): void {
        $data = $this->normalizeRequestPayload(
            $payload,
            false
        );

        $this->db->beginTransaction();

        try {
            $draft = $this->ownedEditableRequestForUpdate(
                $requestId,
                $userId
            );

            if ($draft === null) {
                throw new InvalidArgumentException(
                    'This request was not found or cannot be edited.'
                );
            }

            $profile = $this->requesterProfile($userId);

            $statement = $this->db->prepare(
                "UPDATE initiative_requests
                 SET title = :title,
                     description = :description,
                     initiative_type = :initiative_type,
                     objective = :objective,
                     expected_impact = :expected_impact,
                     beneficiaries = :beneficiaries,
                     proposed_start_date =
                         CAST(:proposed_start_date AS DATE),
                     proposed_end_date =
                         CAST(:proposed_end_date AS DATE),
                     related_agreement_id =
                         CAST(:related_agreement_id AS BIGINT),
                     requester_name_snapshot = COALESCE(
                         requester_name_snapshot,
                         :requester_name_snapshot
                     ),
                     requester_email_snapshot = COALESCE(
                         requester_email_snapshot,
                         :requester_email_snapshot
                     ),
                     requester_mobile = :requester_mobile,
                     requester_type = :requester_type,
                     requester_type_other =
                         :requester_type_other,
                     requester_position_snapshot = COALESCE(
                         requester_position_snapshot,
                         :requester_position_snapshot
                     ),
                     requester_entity_snapshot = COALESCE(
                         requester_entity_snapshot,
                         :requester_entity_snapshot
                     ),
                     requester_department_snapshot = COALESCE(
                         requester_department_snapshot,
                         :requester_department_snapshot
                     ),
                     primary_type_other =
                         :primary_type_other,
                     secondary_types =
                         CAST(:secondary_types AS JSONB),
                     target_groups =
                         CAST(:target_groups AS JSONB),
                     target_group_other =
                         :target_group_other,
                     expected_participants =
                         CAST(:expected_participants AS INTEGER),
                     implementation_scope =
                         :implementation_scope,
                     implementation_scope_other =
                         :implementation_scope_other,
                     proposed_venue = :proposed_venue,
                     proposed_venue_place_id =
                         :proposed_venue_place_id,
                     proposed_venue_name =
                         :proposed_venue_name,
                     proposed_venue_latitude =
                         CAST(:proposed_venue_latitude AS NUMERIC),
                     proposed_venue_longitude =
                         CAST(:proposed_venue_longitude AS NUMERIC),
                     proposed_venue_country_code =
                         :proposed_venue_country_code,
                     implementation_country =
                         :implementation_country,
                     online_platform_name =
                         :online_platform_name,
                     international_participation =
                         CAST(:international_participation AS BOOLEAN),
                     international_countries =
                         CAST(:international_countries AS JSONB),
                     international_partner =
                         :international_partner,
                     relationship_type =
                         :relationship_type,
                     has_related_agreement =
                         CAST(:has_related_agreement AS BOOLEAN),
                     has_external_partner =
                         CAST(:has_external_partner AS BOOLEAN),
                     external_partner_name =
                         :external_partner_name,
                     external_partner_country =
                         :external_partner_country,
                     external_partner_role =
                         :external_partner_role,
                     required_resources =
                         CAST(:required_resources AS JSONB),
                     resource_other = :resource_other,
                     estimated_budget =
                         CAST(:estimated_budget AS NUMERIC),
                     needs_media_support =
                         CAST(:needs_media_support AS BOOLEAN),
                     supports_sdg =
                         CAST(:supports_sdg AS BOOLEAN),
                     sdg_goals = CAST(:sdg_goals AS JSONB),
                     declaration_confirmed =
                         CAST(:declaration_confirmed AS BOOLEAN),
                     declaration_confirmed_at =
                         CASE
                             WHEN CAST(
                                 :declaration_confirmed_at_flag
                                 AS BOOLEAN
                             )
                             THEN COALESCE(
                                 declaration_confirmed_at,
                                 CURRENT_TIMESTAMP
                             )
                             ELSE NULL
                         END,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE request_id = :request_id
                   AND requester_id = :requester_id
                   AND status IN ('DRAFT', 'REVISION_REQUIRED')"
            );
            $statement->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'initiative_type' => $data['initiative_type'],
                'objective' => $data['objective'],
                'expected_impact' => $data['expected_impact'],
                'beneficiaries' => $data['beneficiaries'],
                'proposed_start_date' =>
                    $data['proposed_start_date'],
                'proposed_end_date' =>
                    $data['proposed_end_date'],
                'related_agreement_id' =>
                    $data['related_agreement_id'],
                'requester_name_snapshot' =>
                    $profile['full_name'],
                'requester_email_snapshot' =>
                    $profile['email'],
                'requester_mobile' =>
                    $data['requester_mobile']
                    ?? $profile['mobile'],
                'requester_type' =>
                    $data['requester_type']
                    ?? $this->suggestedRequesterType(
                        (string) $profile['role_key']
                    ),
                'requester_type_other' =>
                    $data['requester_type_other'],
                'requester_position_snapshot' =>
                    $profile['position_name'],
                'requester_entity_snapshot' =>
                    $profile['entity_name'],
                'requester_department_snapshot' =>
                    $profile['department_name'],
                'primary_type_other' =>
                    $data['primary_type_other'],
                'secondary_types' =>
                    $this->jsonArray($data['secondary_types']),
                'target_groups' =>
                    $this->jsonArray($data['target_groups']),
                'target_group_other' =>
                    $data['target_group_other'],
                'expected_participants' =>
                    $data['expected_participants'],
                'implementation_scope' =>
                    $data['implementation_scope'],
                'implementation_scope_other' =>
                    $data['implementation_scope_other'],
                'proposed_venue' => $data['proposed_venue'],
                'proposed_venue_place_id' =>
                    $data['proposed_venue_place_id'],
                'proposed_venue_name' =>
                    $data['proposed_venue_name'],
                'proposed_venue_latitude' =>
                    $data['proposed_venue_latitude'],
                'proposed_venue_longitude' =>
                    $data['proposed_venue_longitude'],
                'proposed_venue_country_code' =>
                    $data['proposed_venue_country_code'],
                'implementation_country' =>
                    $data['implementation_country'],
                'online_platform_name' =>
                    $data['online_platform_name'],
                'international_participation' =>
                    $this->databaseBoolean(
                        $data['international_participation']
                    ),
                'international_countries' =>
                    $this->jsonArray($data['international_countries']),
                'international_partner' =>
                    $data['international_partner'],
                'relationship_type' =>
                    $data['relationship_type'],
                'has_related_agreement' =>
                    $this->databaseBoolean(
                        $data['has_related_agreement']
                    ),
                'has_external_partner' =>
                    $this->databaseBoolean(
                        $data['has_external_partner']
                    ),
                'external_partner_name' =>
                    $data['external_partner_name'],
                'external_partner_country' =>
                    $data['external_partner_country'],
                'external_partner_role' =>
                    $data['external_partner_role'],
                'required_resources' =>
                    $this->jsonArray(
                        $data['required_resources']
                    ),
                'resource_other' => $data['resource_other'],
                'estimated_budget' => $data['estimated_budget'],
                'needs_media_support' =>
                    $this->databaseBoolean(
                        $data['needs_media_support']
                    ),
                'supports_sdg' =>
                    $this->databaseBoolean(
                        $data['supports_sdg']
                    ),
                'sdg_goals' =>
                    $this->jsonArray($data['sdg_goals']),
                'declaration_confirmed' =>
                    $this->databaseBoolean(
                        $data['declaration_confirmed']
                    ),
                'declaration_confirmed_at_flag' =>
                    $this->databaseBoolean(
                        $data['declaration_confirmed']
                    ),
                'request_id' => $requestId,
                'requester_id' => $userId,
            ]);

            $this->replaceRequestAgreementLinks(
                $requestId,
                $data['related_agreement_ids']
            );

            $deleteMembers = $this->db->prepare(
                'DELETE FROM initiative_request_members
                 WHERE request_id = :request_id'
            );
            $deleteMembers->execute([
                'request_id' => $requestId,
            ]);

            $this->insertMembers(
                $requestId,
                $userId,
                is_array($payload['members'] ?? null)
                    ? $payload['members']
                    : []
            );

            $isRevisionUpdate =
                (string) $draft['status']
                === 'REVISION_REQUIRED';

            $this->recordEvent(
                $requestId,
                $userId,
                $isRevisionUpdate
                    ? 'REVISION_UPDATED'
                    : 'DRAFT_UPDATED',
                $isRevisionUpdate
                    ? 'Initiative request revision updated.'
                    : 'Initiative request draft updated.'
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function submitDraft(int $requestId, int $userId): void
    {
        $this->db->beginTransaction();

        try {
            $editable = $this->ownedEditableRequestForUpdate(
                $requestId,
                $userId
            );

            if ($editable === null) {
                throw new InvalidArgumentException(
                    'This request was not found or has already been submitted.'
                );
            }

            $this->assertRequestReadyForSubmission(
                $requestId
            );

            $previousStatus = (string) $editable['status'];
            $isResubmission = $previousStatus === 'REVISION_REQUIRED';
            $nextCycle = $isResubmission
                ? ((int) $editable['revision_cycle']) + 1
                : 0;

            $update = $this->db->prepare(
                "UPDATE initiative_requests
                 SET status = 'UNDER_REVIEW',
                     submitted_at = COALESCE(submitted_at, CURRENT_TIMESTAMP),
                     revision_cycle = :revision_cycle,
                     current_stage_order = NULL,
                     current_phase_order = NULL,
                     current_assignee_id = NULL,
                     current_assignee_unit_id = NULL,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE request_id = :request_id
                   AND requester_id = :requester_id
                   AND status IN ('DRAFT', 'REVISION_REQUIRED')"
            );
            $update->execute([
                'revision_cycle' => $nextCycle,
                'request_id' => $requestId,
                'requester_id' => $userId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException(
                    'This request has already been submitted.'
                );
            }

            if (!$isResubmission) {
                $deleteStages = $this->db->prepare(
                    'DELETE FROM initiative_request_stages
                     WHERE request_id = :request_id'
                );
                $deleteStages->execute(['request_id' => $requestId]);
            }

            $this->createInitialStages(
                $requestId,
                (string) $editable['requester_role_key'],
                isset($editable['requester_unit_id'])
                    ? (int) $editable['requester_unit_id']
                    : null,
                $nextCycle
            );

            if ($isResubmission) {
                $this->resolveRevisionThreads($requestId);
            }

            $this->notifyRequestParticipants(
                $requestId,
                $isResubmission
                    ? 'REQUEST_RESUBMITTED'
                    : 'REQUEST_SUBMITTED',
                $isResubmission
                    ? 'Initiative request resubmitted'
                    : 'Initiative request submitted',
                $isResubmission
                    ? 'The revised Initiative request entered a new approval cycle.'
                    : 'The Initiative request entered the approval route.'
            );

            $this->recordEvent(
                $requestId,
                $userId,
                $isResubmission ? 'REQUEST_RESUBMITTED' : 'REQUEST_SUBMITTED',
                $isResubmission
                    ? 'Initiative request revised and resubmitted for approval.'
                    : 'Initiative request submitted for approval.',
                null,
                null,
                $previousStatus,
                'UNDER_REVIEW'
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function decideRequest(
        int $requestId,
        int $userId,
        string $action,
        ?string $comment = null
    ): array {
        // ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1_DECISION
        $configurableWorkflow =
            new ConfigurableInitiativeWorkflowService($this->db);
        if ($configurableWorkflow->isConfigurableRequest($requestId)) {
            return $configurableWorkflow->decide(
                $requestId,
                $userId,
                $action,
                $comment
            );
        }

        $normalizedAction = strtoupper(trim($action));
        $allowedActions = [
            'APPROVE',
            'REQUEST_REVISION',
            'REJECT',
        ];

        if (!in_array($normalizedAction, $allowedActions, true)) {
            throw new InvalidArgumentException(
                'The selected Initiative decision is not supported.'
            );
        }

        $decisionComment = $this->nullableText($comment);

        if (
            in_array($normalizedAction, ['REQUEST_REVISION', 'REJECT'], true)
            && ($decisionComment === null || strlen($decisionComment) < 10)
        ) {
            throw new InvalidArgumentException(
                'A reason of at least 10 characters is required.'
            );
        }

        $this->db->beginTransaction();

        try {
            $context = $this->decisionContextForUpdate(
                $requestId,
                $userId
            );

            if ($context === null) {
                throw new InvalidArgumentException(
                    'This Initiative request is not awaiting your decision.'
                );
            }

            $stageId = (int) $context['request_stage_id'];
            $stageOrder = (int) $context['stage_order'];
            $cycleNumber = (int) $context['revision_cycle'];
            $requesterId = (int) $context['requester_id'];
            $requesterUnitId = isset($context['requester_unit_id'])
                ? (int) $context['requester_unit_id']
                : null;
            $fromStatus = (string) $context['request_status'];
            $principalUserId = (int) $context['assigned_user_id'];
            $actingOnBehalfOfUserId = filter_var(
                $context['acting_as_delegate'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            )
                ? $principalUserId
                : null;

            if ($normalizedAction === 'APPROVE') {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'APPROVED',
                    $decisionComment,
                    $actingOnBehalfOfUserId
                );

                $nextStage = $this->nextStageForUpdate(
                    $requestId,
                    $cycleNumber,
                    $stageOrder
                );

                if ($nextStage !== null) {
                    $nextStageId = (int) $nextStage['request_stage_id'];
                    $nextAssigneeId = (int) $nextStage['assigned_user_id'];
                    $nextUnitId = (int) $nextStage['responsible_unit_id'];

                    $activate = $this->db->prepare(
                        "UPDATE initiative_request_stages
                         SET status = 'IN_PROGRESS',
                             received_at = COALESCE(received_at, CURRENT_TIMESTAMP),
                             due_at = CURRENT_TIMESTAMP
                                + make_interval(days => reminder_after_days)
                         WHERE request_stage_id = :request_stage_id
                           AND status = 'PENDING'"
                    );
                    $activate->execute([
                        'request_stage_id' => $nextStageId,
                    ]);

                    if ($activate->rowCount() !== 1) {
                        throw new DomainException(
                            'The next Initiative approval stage could not be activated.'
                        );
                    }

                    $updateRequest = $this->db->prepare(
                        "UPDATE initiative_requests
                         SET status = 'UNDER_REVIEW',
                             current_stage_order = :stage_order,
                             current_assignee_id = :assignee_id,
                             current_assignee_unit_id = :assignee_unit_id,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE request_id = :request_id"
                    );
                    $updateRequest->execute([
                        'stage_order' => (int) $nextStage['stage_order'],
                        'assignee_id' => $nextAssigneeId,
                        'assignee_unit_id' => $nextUnitId,
                        'request_id' => $requestId,
                    ]);

                    $this->recordEvent(
                        $requestId,
                        $userId,
                        'STAGE_APPROVED',
                        sprintf(
                            '%s approved the request. It moved to %s.',
                            (string) $context['stage_label'],
                            (string) $nextStage['stage_label']
                        ),
                        $stageId,
                        $nextAssigneeId,
                        $fromStatus,
                        'UNDER_REVIEW',
                        [
                            'approved_stage_order' => $stageOrder,
                            'next_stage_order' => (int) $nextStage['stage_order'],
                            'acted_on_behalf_of_user_id' => $actingOnBehalfOfUserId,
                            'acted_on_behalf_of_name' => $actingOnBehalfOfUserId !== null
                                ? (string) $context['assigned_user_name']
                                : null,
                        ]
                    );

                    $this->notifyRequestParticipants(
                        $requestId,
                        'STAGE_APPROVED',
                        'Initiative request moved forward',
                        sprintf(
                            '%s approved the request. It moved to %s.',
                            (string) $context['stage_label'],
                            (string) $nextStage['stage_label']
                        )
                    );

                    $this->notifyStageReviewers(
                        $requestId,
                        $nextStageId,
                        'APPROVAL_ASSIGNED',
                        'Initiative request awaiting your decision',
                        sprintf(
                            '%s is now awaiting review by %s.',
                            (string) $context['request_code'],
                            (string) $nextStage['stage_label']
                        )
                    );

                    $resultStatus = 'UNDER_REVIEW';
                    $resultStage = (string) $nextStage['stage_label'];
                } else {
                    $finish = $this->db->prepare(
                        "UPDATE initiative_requests
                         SET status = 'APPROVED',
                             approved_at = CURRENT_TIMESTAMP,
                             current_stage_order = NULL,
                             current_assignee_id = NULL,
                             current_assignee_unit_id = NULL,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE request_id = :request_id"
                    );
                    $finish->execute(['request_id' => $requestId]);

                    $this->recordEvent(
                        $requestId,
                        $userId,
                        'REQUEST_APPROVED',
                        'Initiative request received final approval.',
                        $stageId,
                        $requesterId,
                        $fromStatus,
                        'APPROVED',
                        [
                            'acted_on_behalf_of_user_id' => $actingOnBehalfOfUserId,
                            'acted_on_behalf_of_name' => $actingOnBehalfOfUserId !== null
                                ? (string) $context['assigned_user_name']
                                : null,
                        ]
                    );

                    $this->notifyRequestParticipants(
                        $requestId,
                        'REQUEST_APPROVED',
                        'Initiative request approved',
                        sprintf(
                            '%s received final approval.',
                            (string) $context['request_code']
                        )
                    );

                    $resultStatus = 'APPROVED';
                    $resultStage = null;
                }
            } elseif ($normalizedAction === 'REQUEST_REVISION') {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'CHANGES_REQUESTED',
                    $decisionComment,
                    $actingOnBehalfOfUserId
                );

                $updateRequest = $this->db->prepare(
                    "UPDATE initiative_requests
                     SET status = 'REVISION_REQUIRED',
                         current_assignee_id = :requester_id,
                         current_assignee_unit_id = :requester_unit_id,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE request_id = :request_id"
                );
                $updateRequest->execute([
                    'requester_id' => $requesterId,
                    'requester_unit_id' => $requesterUnitId,
                    'request_id' => $requestId,
                ]);

                $counterpartStageId = $this->previousStageId(
                    $requestId,
                    $cycleNumber,
                    $stageOrder
                );

                $thread = $this->db->prepare(
                    "INSERT INTO initiative_revision_threads (
                        request_id,
                        cycle_number,
                        requested_by_stage_id,
                        counterpart_stage_id,
                        requested_by_user_id,
                        status,
                        consolidated_note,
                        sent_to_creator_at,
                        last_comment_at
                     ) VALUES (
                        CAST(:request_id AS BIGINT),
                        :cycle_number,
                        CAST(:stage_id AS BIGINT),
                        CAST(:counterpart_stage_id AS BIGINT),
                        CAST(:requested_by_user_id AS BIGINT),
                        'OPEN',
                        :note,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                     )
                     RETURNING revision_thread_id"
                );
                $thread->execute([
                    'request_id' => $requestId,
                    'cycle_number' => $cycleNumber,
                    'stage_id' => $stageId,
                    'counterpart_stage_id' => $counterpartStageId,
                    'requested_by_user_id' => $userId,
                    'note' => $decisionComment,
                ]);
                $revisionThreadId = (int) $thread->fetchColumn();

                $initialComment = $this->db->prepare(
                    "INSERT INTO initiative_revision_comments (
                        revision_thread_id,
                        author_user_id,
                        visibility,
                        comment_text
                     ) VALUES (
                        CAST(:revision_thread_id AS BIGINT),
                        CAST(:author_user_id AS BIGINT),
                        'PUBLIC',
                        :comment_text
                     )
                     RETURNING revision_comment_id"
                );
                $initialComment->execute([
                    'revision_thread_id' => $revisionThreadId,
                    'author_user_id' => $userId,
                    'comment_text' => $decisionComment,
                ]);
                $initialCommentId = (int) $initialComment->fetchColumn();

                $this->recordEvent(
                    $requestId,
                    $userId,
                    'REVISION_REQUESTED',
                    $decisionComment ?? 'Changes were requested.',
                    $stageId,
                    $requesterId,
                    $fromStatus,
                    'REVISION_REQUIRED',
                    [
                        'acted_on_behalf_of_user_id' => $actingOnBehalfOfUserId,
                        'acted_on_behalf_of_name' => $actingOnBehalfOfUserId !== null
                            ? (string) $context['assigned_user_name']
                            : null,
                        'revision_thread_id' => $revisionThreadId,
                        'counterpart_stage_id' => $counterpartStageId,
                        'visibility' => 'PUBLIC',
                    ]
                );

                $this->notifyRevisionThreadUsers(
                    $requestId,
                    $revisionThreadId,
                    $userId,
                    'PUBLIC',
                    'REVISION_DISCUSSION_OPENED',
                    'Initiative request needs revision',
                    sprintf(
                        '%s opened a revision discussion for %s.',
                        (string) $context['stage_label'],
                        (string) $context['request_code']
                    ),
                    $initialCommentId
                );

                $resultStatus = 'REVISION_REQUIRED';
                $resultStage = (string) $context['stage_label'];
            } else {
                $this->completeStage(
                    $stageId,
                    $userId,
                    'REJECTED',
                    $decisionComment,
                    $actingOnBehalfOfUserId
                );

                $reject = $this->db->prepare(
                    "UPDATE initiative_requests
                     SET status = 'REJECTED',
                         rejected_at = CURRENT_TIMESTAMP,
                         current_stage_order = NULL,
                         current_assignee_id = NULL,
                         current_assignee_unit_id = NULL,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE request_id = :request_id"
                );
                $reject->execute(['request_id' => $requestId]);

                $this->recordEvent(
                    $requestId,
                    $userId,
                    'REQUEST_REJECTED',
                    $decisionComment ?? 'Initiative request rejected.',
                    $stageId,
                    $requesterId,
                    $fromStatus,
                    'REJECTED',
                    [
                        'acted_on_behalf_of_user_id' => $actingOnBehalfOfUserId,
                        'acted_on_behalf_of_name' => $actingOnBehalfOfUserId !== null
                            ? (string) $context['assigned_user_name']
                            : null,
                    ]
                );

                $this->notifyRequestParticipants(
                    $requestId,
                    'REQUEST_REJECTED',
                    'Initiative request rejected',
                    $decisionComment ?? 'The Initiative request was rejected.'
                );

                $resultStatus = 'REJECTED';
                $resultStage = null;
            }

            $this->db->commit();

            return [
                'request_id' => $requestId,
                'status' => $resultStatus,
                'current_stage_label' => $resultStage,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function addRevisionComment(
        int $requestId,
        int $revisionThreadId,
        int $userId,
        string $commentText,
        string $visibility
    ): array {
        $comment = trim($commentText);
        $normalizedVisibility = strtoupper(trim($visibility));

        if (strlen($comment) < 2) {
            throw new InvalidArgumentException(
                'A revision note of at least 2 characters is required.'
            );
        }

        if (strlen($comment) > 4000) {
            throw new InvalidArgumentException(
                'A revision note cannot exceed 4000 characters.'
            );
        }

        if (!in_array(
            $normalizedVisibility,
            ['PUBLIC', 'PRIVATE'],
            true
        )) {
            throw new InvalidArgumentException(
                'Revision note visibility must be PUBLIC or PRIVATE.'
            );
        }

        if (!$this->canView($requestId, $userId)) {
            throw new InvalidArgumentException(
                'The Initiative request was not found.'
            );
        }

        $this->db->beginTransaction();

        try {
            $threadStatement = $this->db->prepare(
                "SELECT
                    revision_thread.revision_thread_id,
                    revision_thread.request_id,
                    revision_thread.status,
                    revision_thread.requested_by_stage_id,
                    request.request_code,
                    request.title
                 FROM initiative_revision_threads revision_thread
                 JOIN initiative_requests request
                   ON request.request_id = revision_thread.request_id
                 WHERE revision_thread.revision_thread_id =
                       CAST(:revision_thread_id AS BIGINT)
                   AND revision_thread.request_id =
                       CAST(:request_id AS BIGINT)
                 FOR UPDATE OF revision_thread"
            );
            $threadStatement->execute([
                'revision_thread_id' => $revisionThreadId,
                'request_id' => $requestId,
            ]);
            $thread = $threadStatement->fetch();

            if (!$thread) {
                throw new InvalidArgumentException(
                    'The revision discussion was not found.'
                );
            }

            if ((string) $thread['status'] !== 'OPEN') {
                throw new InvalidArgumentException(
                    'This revision discussion is already resolved.'
                );
            }

            $isAdministrator = $this->isSystemAdministrator($userId);
            $isParticipant = $this->isRevisionThreadParticipant(
                $revisionThreadId,
                $userId
            );

            if (!$isAdministrator && !$isParticipant) {
                throw new DomainException(
                    'You are not a participant in this revision discussion.'
                );
            }

            $insert = $this->db->prepare(
                "INSERT INTO initiative_revision_comments (
                    revision_thread_id,
                    author_user_id,
                    visibility,
                    comment_text
                 ) VALUES (
                    CAST(:revision_thread_id AS BIGINT),
                    CAST(:author_user_id AS BIGINT),
                    CAST(:visibility AS VARCHAR(30)),
                    :comment_text
                 )
                 RETURNING revision_comment_id, created_at"
            );
            $insert->execute([
                'revision_thread_id' => $revisionThreadId,
                'author_user_id' => $userId,
                'visibility' => $normalizedVisibility,
                'comment_text' => $comment,
            ]);
            $createdComment = $insert->fetch();
            $revisionCommentId = (int) $createdComment['revision_comment_id'];

            $touchThread = $this->db->prepare(
                "UPDATE initiative_revision_threads
                 SET last_comment_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE revision_thread_id =
                       CAST(:revision_thread_id AS BIGINT)"
            );
            $touchThread->execute([
                'revision_thread_id' => $revisionThreadId,
            ]);

            $authorName = $this->userDisplayName($userId);
            $isPrivate = $normalizedVisibility === 'PRIVATE';

            $this->recordEvent(
                $requestId,
                $userId,
                $isPrivate
                    ? 'REVISION_PRIVATE_NOTE_ADDED'
                    : 'REVISION_PUBLIC_NOTE_ADDED',
                $isPrivate
                    ? 'A private revision discussion note was added.'
                    : $comment,
                (int) $thread['requested_by_stage_id'],
                null,
                null,
                null,
                [
                    'revision_thread_id' => $revisionThreadId,
                    'revision_comment_id' => $revisionCommentId,
                    'visibility' => $normalizedVisibility,
                ]
            );

            $this->notifyRevisionThreadUsers(
                $requestId,
                $revisionThreadId,
                $userId,
                $normalizedVisibility,
                'REVISION_COMMENT_ADDED',
                $isPrivate
                    ? 'New private revision note'
                    : 'New revision discussion note',
                sprintf(
                    '%s added a %s note to the revision discussion for %s.',
                    $authorName,
                    strtolower($normalizedVisibility),
                    (string) $thread['request_code']
                ),
                $revisionCommentId
            );

            $this->db->commit();

            return [
                'revision_comment_id' => $revisionCommentId,
                'revision_thread_id' => $revisionThreadId,
                'author_user_id' => $userId,
                'author_name' => $authorName,
                'visibility' => $normalizedVisibility,
                'comment_text' => $comment,
                'created_at' => $createdComment['created_at'],
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function adminSkipStage(
        int $requestId,
        int $userId,
        string $reason
    ): array {
        // ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1_ADMIN_SKIP
        $configurableWorkflow =
            new ConfigurableInitiativeWorkflowService($this->db);
        if ($configurableWorkflow->isConfigurableRequest($requestId)) {
            return $configurableWorkflow->adminSkip(
                $requestId,
                $userId,
                $reason
            );
        }

        if (!$this->isSystemAdministrator($userId)) {
            throw new DomainException(
                'Only a System Administrator can skip an Initiative approval stage.'
            );
        }

        $skipReason = $this->nullableText($reason);

        if ($skipReason === null || strlen($skipReason) < 10) {
            throw new InvalidArgumentException(
                'An administrative skip reason of at least 10 characters is required.'
            );
        }

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare(
                "SELECT
                    request.request_id,
                    request.request_code,
                    request.status AS request_status,
                    request.revision_cycle,
                    request.requester_id,
                    stage.request_stage_id,
                    stage.stage_order,
                    stage.stage_label,
                    stage.assigned_user_id,
                    stage.responsible_unit_id,
                    CONCAT(
                        assigned.first_name,
                        ' ',
                        assigned.last_name
                    ) AS assigned_user_name
                 FROM initiative_requests request
                 JOIN initiative_request_stages stage
                   ON stage.request_id = request.request_id
                  AND stage.cycle_number = request.revision_cycle
                  AND stage.stage_order = request.current_stage_order
                 LEFT JOIN users assigned
                   ON assigned.user_id = stage.assigned_user_id
                 WHERE request.request_id = :request_id
                   AND request.status IN (
                        'UNDER_REVIEW',
                        'RESUBMITTED'
                   )
                   AND stage.status = 'IN_PROGRESS'
                   AND stage.is_skippable = TRUE
                 FOR UPDATE OF request, stage"
            );
            $statement->execute([
                'request_id' => $requestId,
            ]);
            $context = $statement->fetch();

            if (!$context) {
                throw new InvalidArgumentException(
                    'This Initiative request has no current skippable approval stage.'
                );
            }

            $stageId = (int) $context['request_stage_id'];
            $stageOrder = (int) $context['stage_order'];
            $cycleNumber = (int) $context['revision_cycle'];
            $skippedUserId = isset($context['assigned_user_id'])
                ? (int) $context['assigned_user_id']
                : null;
            $fromStatus = (string) $context['request_status'];

            $this->completeStage(
                $stageId,
                $userId,
                'SKIPPED',
                $skipReason
            );

            $skipRecord = $this->db->prepare(
                "INSERT INTO initiative_admin_skips (
                    request_id,
                    request_stage_id,
                    skipped_by,
                    skipped_user_id,
                    mandatory_reason
                 ) VALUES (
                    CAST(:request_id AS BIGINT),
                    CAST(:request_stage_id AS BIGINT),
                    CAST(:skipped_by AS BIGINT),
                    CAST(:skipped_user_id AS BIGINT),
                    :mandatory_reason
                 )"
            );
            $skipRecord->execute([
                'request_id' => $requestId,
                'request_stage_id' => $stageId,
                'skipped_by' => $userId,
                'skipped_user_id' => $skippedUserId,
                'mandatory_reason' => $skipReason,
            ]);

            $this->notifyStageReviewers(
                $requestId,
                $stageId,
                'STAGE_SKIPPED',
                'Initiative approval stage skipped',
                sprintf(
                    '%s was skipped by a System Administrator. Reason: %s',
                    (string) $context['stage_label'],
                    $skipReason
                )
            );

            $nextStage = $this->nextStageForUpdate(
                $requestId,
                $cycleNumber,
                $stageOrder
            );

            if ($nextStage !== null) {
                $nextStageId = (int) $nextStage['request_stage_id'];
                $nextAssigneeId = (int) $nextStage['assigned_user_id'];
                $nextUnitId = (int) $nextStage['responsible_unit_id'];

                $activate = $this->db->prepare(
                    "UPDATE initiative_request_stages
                     SET status = 'IN_PROGRESS',
                         received_at = COALESCE(
                            received_at,
                            CURRENT_TIMESTAMP
                         ),
                         due_at = CURRENT_TIMESTAMP
                            + make_interval(
                                days => reminder_after_days
                              )
                     WHERE request_stage_id =
                           :request_stage_id
                       AND status = 'PENDING'"
                );
                $activate->execute([
                    'request_stage_id' => $nextStageId,
                ]);

                if ($activate->rowCount() !== 1) {
                    throw new DomainException(
                        'The next Initiative approval stage could not be activated.'
                    );
                }

                $updateRequest = $this->db->prepare(
                    "UPDATE initiative_requests
                     SET status = 'UNDER_REVIEW',
                         current_stage_order = :stage_order,
                         current_assignee_id = :assignee_id,
                         current_assignee_unit_id =
                             :assignee_unit_id,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE request_id = :request_id"
                );
                $updateRequest->execute([
                    'stage_order' =>
                        (int) $nextStage['stage_order'],
                    'assignee_id' => $nextAssigneeId,
                    'assignee_unit_id' => $nextUnitId,
                    'request_id' => $requestId,
                ]);

                $this->recordEvent(
                    $requestId,
                    $userId,
                    'STAGE_SKIPPED',
                    sprintf(
                        'System Administrator skipped %s. Reason: %s The request moved to %s.',
                        (string) $context['stage_label'],
                        $skipReason,
                        (string) $nextStage['stage_label']
                    ),
                    $stageId,
                    $skippedUserId,
                    $fromStatus,
                    'UNDER_REVIEW',
                    [
                        'skipped_stage_order' => $stageOrder,
                        'skipped_user_id' => $skippedUserId,
                        'skipped_user_name' =>
                            $context['assigned_user_name'] ?? null,
                        'skip_reason' => $skipReason,
                        'next_stage_order' =>
                            (int) $nextStage['stage_order'],
                    ]
                );

                $this->notifyRequestParticipants(
                    $requestId,
                    'STAGE_SKIPPED',
                    'Initiative approval stage skipped',
                    sprintf(
                        '%s was administratively skipped. The request moved to %s. Reason: %s',
                        (string) $context['stage_label'],
                        (string) $nextStage['stage_label'],
                        $skipReason
                    )
                );

                $this->notifyStageReviewers(
                    $requestId,
                    $nextStageId,
                    'APPROVAL_ASSIGNED',
                    'Initiative request awaiting your decision',
                    sprintf(
                        '%s is now awaiting review by %s.',
                        (string) $context['request_code'],
                        (string) $nextStage['stage_label']
                    )
                );

                $resultStatus = 'UNDER_REVIEW';
                $resultStage =
                    (string) $nextStage['stage_label'];
            } else {
                $finish = $this->db->prepare(
                    "UPDATE initiative_requests
                     SET status = 'APPROVED',
                         approved_at = CURRENT_TIMESTAMP,
                         current_stage_order = NULL,
                         current_assignee_id = NULL,
                         current_assignee_unit_id = NULL,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE request_id = :request_id"
                );
                $finish->execute([
                    'request_id' => $requestId,
                ]);

                $this->recordEvent(
                    $requestId,
                    $userId,
                    'STAGE_SKIPPED',
                    sprintf(
                        'System Administrator skipped the final stage, %s. Reason: %s The request was approved because no approval stages remained.',
                        (string) $context['stage_label'],
                        $skipReason
                    ),
                    $stageId,
                    $skippedUserId,
                    $fromStatus,
                    'APPROVED',
                    [
                        'skipped_stage_order' => $stageOrder,
                        'skipped_user_id' => $skippedUserId,
                        'skipped_user_name' =>
                            $context['assigned_user_name'] ?? null,
                        'skip_reason' => $skipReason,
                        'final_stage_skipped' => true,
                    ]
                );

                $this->notifyRequestParticipants(
                    $requestId,
                    'REQUEST_APPROVED',
                    'Initiative request approved',
                    sprintf(
                        '%s was approved after the final approval stage was administratively skipped. Reason: %s',
                        (string) $context['request_code'],
                        $skipReason
                    )
                );

                $resultStatus = 'APPROVED';
                $resultStage = null;
            }

            $this->db->commit();

            return [
                'request_id' => $requestId,
                'status' => $resultStatus,
                'current_stage_label' => $resultStage,
                'skipped_stage_label' =>
                    (string) $context['stage_label'],
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function uploadAttachments(
        int $requestId,
        int $userId,
        array $files
    ): array {
        $this->assertRequestEditableByOwner(
            $requestId,
            $userId
        );

        $normalizedFiles = $this->normalizeUploadedFiles(
            $files
        );

        if ($normalizedFiles === []) {
            throw new InvalidArgumentException(
                'Select at least one attachment.'
            );
        }

        $countStatement = $this->db->prepare(
            "SELECT COUNT(*)
             FROM initiative_request_attachments
             WHERE request_id = :request_id"
        );
        $countStatement->execute([
            'request_id' => $requestId,
        ]);
        $existingCount = (int) $countStatement->fetchColumn();

        if ($existingCount + count($normalizedFiles) > 5) {
            throw new InvalidArgumentException(
                'A maximum of 5 supporting attachments is allowed.'
            );
        }

        $allowedExtensions = [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'jpg',
            'jpeg',
            'png',
        ];
        $maximumSize = 10 * 1024 * 1024;
        $uploadDirectory = dirname(__DIR__, 2)
            . '/uob-agreements/uploads/initiative-requests';

        if (
            !is_dir($uploadDirectory)
            && !mkdir(
                $uploadDirectory,
                0775,
                true
            )
            && !is_dir($uploadDirectory)
        ) {
            throw new RuntimeException(
                'The Initiative attachment directory could not be created.'
            );
        }

        $preparedFiles = [];

        foreach ($normalizedFiles as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    'One of the selected attachments could not be uploaded.'
                );
            }

            $originalName = basename(
                (string) ($file['name'] ?? '')
            );
            $temporaryPath = (string) ($file['tmp_name'] ?? '');
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
                    'Supported attachments are PDF, Word, Excel, PowerPoint, JPG, and PNG files.'
                );
            }

            if (
                $fileSize < 1
                || $fileSize > $maximumSize
            ) {
                throw new InvalidArgumentException(
                    'Each attachment must be 10 MB or smaller.'
                );
            }

            if (
                $temporaryPath === ''
                || !is_uploaded_file($temporaryPath)
            ) {
                throw new InvalidArgumentException(
                    'The selected attachment upload is not valid.'
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
            $relativePath = 'uob-agreements/uploads/'
                . 'initiative-requests/'
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
                "INSERT INTO initiative_request_attachments (
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
                        'An attachment could not be stored.'
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
                'ATTACHMENTS_ADDED',
                sprintf(
                    '%d supporting attachment(s) added.',
                    count($preparedFiles)
                ),
                null,
                null,
                null,
                null,
                [
                    'attachment_count' =>
                        count($preparedFiles),
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

        return $this->attachments($requestId);
    }

    public function deleteAttachment(
        int $requestId,
        int $attachmentId,
        int $userId
    ): bool {
        $this->assertRequestEditableByOwner(
            $requestId,
            $userId
        );

        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                original_name,
                storage_path
             FROM initiative_request_attachments
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
            "DELETE FROM initiative_request_attachments
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

        $this->recordEvent(
            $requestId,
            $userId,
            'ATTACHMENT_REMOVED',
            sprintf(
                'Supporting attachment removed: %s.',
                (string) $attachment['original_name']
            ),
            null,
            null,
            null,
            null,
            [
                'attachment_id' => $attachmentId,
                'file_name' =>
                    (string) $attachment['original_name'],
            ]
        );

        return true;
    }

    public function attachmentForDownload(
        int $requestId,
        int $attachmentId,
        int $userId
    ): ?array {
        if (!$this->canView($requestId, $userId)) {
            return null;
        }

        $statement = $this->db->prepare(
            "SELECT
                attachment_id,
                request_id,
                original_name,
                storage_path,
                mime_type,
                file_size_bytes
             FROM initiative_request_attachments
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

        $attachment['absolute_path'] =
            dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                (string) $attachment['storage_path']
            );

        return $attachment;
    }


    public function notificationsForUser(
        int $userId,
        bool $unreadOnly = false,
        int $limit = 100
    ): array {
        $limit = max(1, min($limit, 250));
        $unreadFilter = $unreadOnly
            ? ' AND notification.is_read = FALSE'
            : '';

        $statement = $this->db->prepare(
            "SELECT
                notification.notification_id,
                notification.request_id,
                notification.notification_type,
                notification.title,
                notification.message,
                notification.payload,
                notification.is_read,
                notification.read_at,
                notification.created_at,
                request.request_code,
                request.title AS request_title
             FROM initiative_notifications notification
             LEFT JOIN initiative_requests request
               ON request.request_id = notification.request_id
             WHERE notification.recipient_user_id = :user_id"
             . $unreadFilter .
             " ORDER BY
                notification.created_at DESC,
                notification.notification_id DESC
             LIMIT {$limit}"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function unreadNotificationCount(int $userId): int
    {
        $statement = $this->db->prepare(
            "SELECT COUNT(*)
             FROM initiative_notifications
             WHERE recipient_user_id = :user_id
               AND is_read = FALSE"
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function markNotificationRead(
        int $notificationId,
        int $userId
    ): bool {
        $statement = $this->db->prepare(
            "UPDATE initiative_notifications
             SET is_read = TRUE,
                 read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE notification_id = :notification_id
               AND recipient_user_id = :user_id"
        );
        $statement->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() === 1;
    }

    public function markAllNotificationsRead(int $userId): int
    {
        $statement = $this->db->prepare(
            "UPDATE initiative_notifications
             SET is_read = TRUE,
                 read_at = COALESCE(read_at, CURRENT_TIMESTAMP)
             WHERE recipient_user_id = :user_id
               AND is_read = FALSE"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount();
    }

    public function requestDetail(int $requestId, int $userId): ?array
    {
        if (!$this->canView($requestId, $userId)) {
            return null;
        }

        $this->markCurrentStageOpened($requestId, $userId);

        $statement = $this->db->prepare(
            "SELECT
                request.*,
                COALESCE(
                    request.requester_name_snapshot,
                    CONCAT(
                        requester.first_name,
                        ' ',
                        requester.last_name
                    )
                ) AS requester_name,
                agreement.title AS related_agreement_title,
                stage.stage_label AS current_stage_label,
                stage.status AS current_stage_status,
                stage.is_skippable AS current_stage_is_skippable,
                CASE
                    WHEN stage.request_stage_id IS NULL
                      OR stage.status NOT IN ('IN_PROGRESS', 'DISCUSSING_REVISION')
                    THEN NULL
                    ELSE CONCAT(
                        FLOOR(EXTRACT(EPOCH FROM (
                            CURRENT_TIMESTAMP
                            - COALESCE(stage.received_at, stage.created_at)
                        )) / 86400),
                        ' days'
                    )
                END AS current_waiting_label
             FROM initiative_requests request
             JOIN users requester
               ON requester.user_id = request.requester_id
             LEFT JOIN agreements agreement
               ON agreement.agreement_id = request.related_agreement_id
             LEFT JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.stage_order = request.current_stage_order
             WHERE request.request_id = :request_id
             LIMIT 1"
        );
        $statement->execute(['request_id' => $requestId]);
        $request = $statement->fetch();

        if (!$request) {
            return null;
        }

        foreach (
            [
                'secondary_types',
                'target_groups',
                'required_resources',
                'international_countries',
                'sdg_goals',
            ] as $arrayField
        ) {
            $request[$arrayField] =
                $this->decodeJsonArray(
                    $request[$arrayField] ?? null
                );
        }

        $request['related_agreement_ids'] =
            $this->requestAgreementIds($requestId);
        $request['related_agreements'] =
            $this->requestAgreements($requestId);
        $request['attachments'] =
            $this->attachments($requestId);

        $isOwnedEditableRequest = (
            in_array(
                (string) $request['status'],
                ['DRAFT', 'REVISION_REQUIRED'],
                true
            )
            && (int) $request['requester_id'] === $userId
        );

        $decisionActor = $this->decisionActorContext(
            $requestId,
            $userId
        );

        $isSystemAdministrator =
            $this->isSystemAdministrator($userId);

        $request['can_edit_draft'] = $isOwnedEditableRequest;
        $request['can_submit_draft'] = $isOwnedEditableRequest;
        $request['can_decide'] = $decisionActor !== null;
        $request['can_admin_skip'] =
            $isSystemAdministrator
            && in_array(
                (string) $request['status'],
                ['UNDER_REVIEW', 'RESUBMITTED'],
                true
            )
            && (string) (
                $request['current_stage_status'] ?? ''
            ) === 'IN_PROGRESS'
            && filter_var(
                $request['current_stage_is_skippable'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        $request['decision_as_delegate'] = $decisionActor !== null
            && filter_var(
                $decisionActor['acting_as_delegate'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        $request['decision_principal_name'] =
            $decisionActor['assigned_user_name'] ?? null;
        $request['members'] = $this->members($requestId);
        $request['stages'] = $this->stages($requestId);
        $request['revision_threads'] = $this->revisionThreadsForUser(
            $requestId,
            $userId
        );
        $request['events'] = $this->events($requestId);
        $request = array_merge(
            $request,
            $this->conversionRepository->accessSummary(
                $requestId,
                $userId
            )
        );

        return $request;
    }

    public function eligibleCollaborators(int $userId): array
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
                MIN(position.name) AS position
             FROM users user_account
             LEFT JOIN user_positions user_position
               ON user_position.user_id = user_account.user_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
             LEFT JOIN positions position
               ON position.position_id = user_position.position_id
             WHERE user_account.is_active = TRUE
               AND user_account.user_id <> :user_id
             GROUP BY
                user_account.user_id,
                user_account.first_name,
                user_account.last_name,
                user_account.email
             ORDER BY
                user_account.first_name,
                user_account.last_name
             LIMIT 250"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }


    private function ownedEditableRequestForUpdate(
        int $requestId,
        int $userId
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT
                request_id,
                requester_role_key,
                requester_unit_id,
                status,
                revision_cycle
             FROM initiative_requests
             WHERE request_id = :request_id
               AND requester_id = :requester_id
               AND status IN ('DRAFT', 'REVISION_REQUIRED')
             FOR UPDATE"
        );
        $statement->execute([
            'request_id' => $requestId,
            'requester_id' => $userId,
        ]);

        $request = $statement->fetch();

        return $request ?: null;
    }

    private function isSystemAdministrator(int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                JOIN users user_account
                  ON user_account.user_id = user_role.user_id
                 AND user_account.is_active = TRUE
                WHERE user_role.user_id = :user_id
                  AND role.role_name = 'System Administrator'
             )"
        );
        $statement->execute([
            'user_id' => $userId,
        ]);

        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }


    private function requesterProfile(int $userId): array
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
                user_account.phone,
                user_position.unit_id,
                position.name AS position_name,
                unit.name AS department_name,
                COALESCE(parent_unit.name, unit.name)
                    AS entity_name
             FROM users user_account
             LEFT JOIN user_positions user_position
               ON user_position.user_id = user_account.user_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
             LEFT JOIN positions position
               ON position.position_id =
                  user_position.position_id
             LEFT JOIN organizational_units unit
               ON unit.unit_id = user_position.unit_id
             LEFT JOIN organizational_units parent_unit
               ON parent_unit.unit_id =
                  unit.parent_unit_id
             WHERE user_account.user_id = :user_id
             ORDER BY
                user_position.start_date DESC NULLS LAST,
                user_position.user_position_id DESC NULLS LAST
             LIMIT 1"
        );
        $statement->execute([
            'user_id' => $userId,
        ]);
        $profile = $statement->fetch() ?: [];

        $positionName = strtolower(
            (string) ($profile['position_name'] ?? '')
        );

        return [
            'user_id' => $userId,
            'full_name' => trim(
                (string) ($profile['full_name'] ?? '')
            ),
            'email' => $this->nullableText(
                $profile['email'] ?? null
            ),
            'mobile' => $this->nullableText(
                $profile['phone'] ?? null
            ),
            'position_name' => $this->nullableText(
                $profile['position_name'] ?? null
            ),
            'entity_name' => $this->nullableText(
                $profile['entity_name'] ?? null
            ),
            'department_name' => $this->nullableText(
                $profile['department_name'] ?? null
            ),
            'unit_id' => isset($profile['unit_id'])
                ? (int) $profile['unit_id']
                : null,
            'role_key' => match (true) {
                str_contains(
                    $positionName,
                    'department head'
                ),
                str_contains(
                    $positionName,
                    'head of department'
                ) => 'DEPARTMENT_HEAD',
                str_contains($positionName, 'dean') => 'DEAN',
                (
                    str_contains($positionName, 'vice president')
                    && str_contains($positionName, 'academic affairs')
                    && str_contains($positionName, 'office')
                ) => 'VPAA_OFFICE',
                (
                    str_contains($positionName, 'vice president')
                    && str_contains($positionName, 'academic affairs')
                ) => 'VICE_PRESIDENT_ACADEMIC_AFFAIRS',
                str_contains(
                    $positionName,
                    'vice president office'
                ) => 'VP_OFFICE',
                str_contains(
                    $positionName,
                    'vice president'
                ) => 'VICE_PRESIDENT',
                str_contains(
                    $positionName,
                    'president office'
                ) => 'PRESIDENT_OFFICE',
                str_contains(
                    $positionName,
                    'president'
                ) => 'PRESIDENT',
                str_contains($positionName, 'faculty'),
                str_contains($positionName, 'professor'),
                str_contains($positionName, 'lecturer') =>
                    'FACULTY',
                str_contains($positionName, 'staff'),
                str_contains($positionName, 'officer'),
                str_contains($positionName, 'specialist'),
                str_contains($positionName, 'coordinator') =>
                    'STAFF',
                default => 'STUDENT',
            },
        ];
    }

    private function createInitialStages(
        int $requestId,
        string $roleKey,
        ?int $requesterUnitId,
        int $cycleNumber = 0
    ): void {
        // ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1: snapshot the active
        // Initiative template for this approval cycle.
        $configurableWorkflow =
            new ConfigurableInitiativeWorkflowService($this->db);
        $configurableWorkflow->createStages(
            $requestId,
            $roleKey,
            $requesterUnitId,
            $cycleNumber
        );
    }

    private function insertMembers(
        int $requestId,
        int $addedBy,
        array $members
    ): void {
        $insert = $this->db->prepare(
            "INSERT INTO initiative_request_members (
                request_id,
                user_id,
                member_role,
                can_convert_after_approval,
                added_by
             ) VALUES (
                :request_id,
                :user_id,
                :member_role,
                :can_convert,
                :added_by
             )
             ON CONFLICT (request_id, user_id) DO NOTHING"
        );

        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }

            $memberUserId = (int) ($member['user_id'] ?? 0);

            if ($memberUserId < 1 || $memberUserId === $addedBy) {
                continue;
            }

            $memberRole = strtoupper(trim((string) (
                $member['member_role'] ?? 'COLLABORATOR'
            )));

            if (!in_array($memberRole, ['COLLABORATOR', 'CO_OWNER'], true)) {
                $memberRole = 'COLLABORATOR';
            }

            $insert->execute([
                'request_id' => $requestId,
                'user_id' => $memberUserId,
                'member_role' => $memberRole,
                'can_convert' => filter_var(
                    $member['can_convert_after_approval'] ?? true,
                    FILTER_VALIDATE_BOOLEAN
                ) ? 'true' : 'false',
                'added_by' => $addedBy,
            ]);
        }
    }

    private function members(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                member.user_id,
                member.member_role,
                member.can_convert_after_approval,
                CONCAT(user_account.first_name, ' ', user_account.last_name)
                    AS full_name,
                user_account.email
             FROM initiative_request_members member
             JOIN users user_account
               ON user_account.user_id = member.user_id
             WHERE member.request_id = :request_id
             ORDER BY member.added_at"
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    private function stages(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                stage.*,
                CONCAT(assigned.first_name, ' ', assigned.last_name)
                    AS assigned_user_name,
                CONCAT(actor.first_name, ' ', actor.last_name)
                    AS acted_by_name,
                CONCAT(principal.first_name, ' ', principal.last_name)
                    AS acted_on_behalf_of_name
             FROM initiative_request_stages stage
             LEFT JOIN users assigned
               ON assigned.user_id = stage.assigned_user_id
             LEFT JOIN users actor
               ON actor.user_id = stage.acted_by_user_id
             LEFT JOIN users principal
               ON principal.user_id = stage.acted_on_behalf_of_user_id
             WHERE stage.request_id = :request_id
             ORDER BY stage.cycle_number, stage.stage_order"
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    private function events(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                event.*,
                CONCAT(actor.first_name, ' ', actor.last_name)
                    AS actor_name
             FROM initiative_request_events event
             LEFT JOIN users actor
               ON actor.user_id = event.actor_user_id
             WHERE event.request_id = :request_id
             ORDER BY event.occurred_at DESC, event.event_id DESC
             LIMIT 100"
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }

    private function canView(int $requestId, int $userId): bool
    {
        if ($this->isSystemAdministrator($userId)) {
            return true;
        }

        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_requests request
                LEFT JOIN initiative_request_members member
                  ON member.request_id = request.request_id
                 AND member.user_id = :member_user_id
                WHERE request.request_id = :request_id
                  AND (
                        request.requester_id = :requester_user_id
                        OR request.current_assignee_id = :assignee_user_id
                        OR member.user_id IS NOT NULL
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages acted_stage
                            WHERE acted_stage.request_id = request.request_id
                              AND acted_stage.acted_by_user_id = :acted_user_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages behalf_stage
                            WHERE behalf_stage.request_id = request.request_id
                              AND behalf_stage.acted_on_behalf_of_user_id =
                                  :behalf_user_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages assigned_stage
                            WHERE assigned_stage.request_id = request.request_id
                              AND assigned_stage.assigned_user_id = :historical_assignee_user_id
                      AND assigned_stage.cycle_number =
                          request.revision_cycle
                      AND assigned_stage.stage_order <=
                          request.current_stage_order
                      AND assigned_stage.status <> 'PENDING'
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages office_stage
                            JOIN user_positions delegate_position
                              ON delegate_position.user_id =
                                 :delegate_user_id
                             AND delegate_position.unit_id =
                                 office_stage.responsible_unit_id
                             AND delegate_position.is_active = TRUE
                             AND (
                                  delegate_position.end_date IS NULL
                                  OR delegate_position.end_date >= CURRENT_DATE
                             )
                            JOIN positions delegate_role_position
                      ON delegate_role_position.position_id =
                         delegate_position.position_id
                     AND delegate_role_position.name =
                         CASE office_stage.stage_key
                             WHEN 'VICE_PRESIDENT'
                                 THEN 'Vice President Office Delegate'
                             WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                 THEN 'President Office Delegate'
                             ELSE '__NO_DELEGATE_POSITION__'
                         END
                    JOIN users delegate_user
                              ON delegate_user.user_id =
                                 delegate_position.user_id
                             AND delegate_user.is_active = TRUE
                            JOIN user_roles delegate_user_role
                              ON delegate_user_role.user_id =
                                 delegate_user.user_id
                            JOIN role_permissions delegate_role_permission
                              ON delegate_role_permission.role_id =
                                 delegate_user_role.role_id
                            JOIN permissions delegate_permission
                              ON delegate_permission.permission_id =
                                 delegate_role_permission.permission_id
                             AND delegate_permission.permission_code =
                                 'APPROVE_INITIATIVE'
                            WHERE office_stage.request_id =
                                  request.request_id
                              AND office_stage.cycle_number =
                                  request.revision_cycle
                              AND office_stage.status <> 'PENDING'
                              AND office_stage.is_office_delegable = TRUE
                        )
                  )
             )"
        );
        $statement->execute([
            'member_user_id' => $userId,
            'request_id' => $requestId,
            'requester_user_id' => $userId,
            'assignee_user_id' => $userId,
            'acted_user_id' => $userId,
            'behalf_user_id' => $userId,
            'historical_assignee_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);

        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function decisionContextForUpdate(
        int $requestId,
        int $userId
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT
                request.request_id,
                request.request_code,
                request.requester_id,
                request.requester_unit_id,
                request.status AS request_status,
                request.revision_cycle,
                stage.request_stage_id,
                stage.stage_order,
                stage.stage_key,
                stage.stage_label,
                stage.status AS stage_status,
                stage.assigned_user_id,
                stage.responsible_unit_id,
                CONCAT(assigned.first_name, ' ', assigned.last_name)
                    AS assigned_user_name,
                CASE
                    WHEN stage.assigned_user_id = :actor_user_id
                    THEN FALSE
                    ELSE TRUE
                END AS acting_as_delegate
             FROM initiative_requests request
             JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.stage_order = request.current_stage_order
             JOIN users assigned
               ON assigned.user_id = stage.assigned_user_id
             WHERE request.request_id = :request_id
               AND request.status IN ('UNDER_REVIEW', 'RESUBMITTED')
               AND stage.status = 'IN_PROGRESS'
               AND (
                    stage.assigned_user_id = :direct_user_id
                    OR (
                        stage.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions delegate_position
                            JOIN positions delegate_role_position
                              ON delegate_role_position.position_id =
                                 delegate_position.position_id
                             AND delegate_role_position.name =
                                 CASE stage.stage_key
                                     WHEN 'VICE_PRESIDENT'
                                         THEN 'Vice President Office Delegate'
                                     WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                         THEN 'President Office Delegate'
                                     ELSE '__NO_DELEGATE_POSITION__'
                                 END
                            JOIN users delegate_user
                              ON delegate_user.user_id = delegate_position.user_id
                             AND delegate_user.is_active = TRUE
                            JOIN user_roles delegate_user_role
                              ON delegate_user_role.user_id = delegate_user.user_id
                            JOIN role_permissions delegate_role_permission
                              ON delegate_role_permission.role_id = delegate_user_role.role_id
                            JOIN permissions delegate_permission
                              ON delegate_permission.permission_id = delegate_role_permission.permission_id
                             AND delegate_permission.permission_code = 'APPROVE_INITIATIVE'
                            WHERE delegate_position.user_id = :delegate_user_id
                              AND delegate_position.unit_id = stage.responsible_unit_id
                              AND delegate_position.is_active = TRUE
                              AND (
                                    delegate_position.end_date IS NULL
                                    OR delegate_position.end_date >= CURRENT_DATE
                              )
                        )
                    )
               )
             FOR UPDATE OF request, stage"
        );
        $statement->execute([
            'request_id' => $requestId,
            'actor_user_id' => $userId,
            'direct_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);

        $context = $statement->fetch();

        return $context ?: null;
    }

    private function decisionActorContext(
        int $requestId,
        int $userId
    ): ?array {
        // ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1_ACTOR
        $configurableWorkflow =
            new ConfigurableInitiativeWorkflowService($this->db);
        if ($configurableWorkflow->isConfigurableRequest($requestId)) {
            return $configurableWorkflow->decisionActorContext(
                $requestId,
                $userId
            );
        }

        $statement = $this->db->prepare(
            "SELECT
                stage.assigned_user_id,
                CONCAT(assigned.first_name, ' ', assigned.last_name)
                    AS assigned_user_name,
                CASE
                    WHEN stage.assigned_user_id = :actor_user_id
                    THEN FALSE
                    ELSE TRUE
                END AS acting_as_delegate
             FROM initiative_requests request
             JOIN initiative_request_stages stage
               ON stage.request_id = request.request_id
              AND stage.cycle_number = request.revision_cycle
              AND stage.stage_order = request.current_stage_order
             JOIN users assigned
               ON assigned.user_id = stage.assigned_user_id
             WHERE request.request_id = :request_id
               AND request.status IN ('UNDER_REVIEW', 'RESUBMITTED')
               AND stage.status = 'IN_PROGRESS'
               AND (
                    stage.assigned_user_id = :direct_user_id
                    OR (
                        stage.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions delegate_position
                            JOIN positions delegate_role_position
                              ON delegate_role_position.position_id =
                                 delegate_position.position_id
                             AND delegate_role_position.name =
                                 CASE stage.stage_key
                                     WHEN 'VICE_PRESIDENT'
                                         THEN 'Vice President Office Delegate'
                                     WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                         THEN 'President Office Delegate'
                                     ELSE '__NO_DELEGATE_POSITION__'
                                 END
                            JOIN users delegate_user
                              ON delegate_user.user_id = delegate_position.user_id
                             AND delegate_user.is_active = TRUE
                            JOIN user_roles delegate_user_role
                              ON delegate_user_role.user_id = delegate_user.user_id
                            JOIN role_permissions delegate_role_permission
                              ON delegate_role_permission.role_id = delegate_user_role.role_id
                            JOIN permissions delegate_permission
                              ON delegate_permission.permission_id = delegate_role_permission.permission_id
                             AND delegate_permission.permission_code = 'APPROVE_INITIATIVE'
                            WHERE delegate_position.user_id = :delegate_user_id
                              AND delegate_position.unit_id = stage.responsible_unit_id
                              AND delegate_position.is_active = TRUE
                              AND (
                                    delegate_position.end_date IS NULL
                                    OR delegate_position.end_date >= CURRENT_DATE
                              )
                        )
                    )
               )
             LIMIT 1"
        );
        $statement->execute([
            'request_id' => $requestId,
            'actor_user_id' => $userId,
            'direct_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);

        $context = $statement->fetch();

        return $context ?: null;
    }

    private function canDecideRequest(array $request, int $userId): bool
    {
        return in_array(
            (string) ($request['status'] ?? ''),
            ['UNDER_REVIEW', 'RESUBMITTED'],
            true
        )
            && isset($request['current_assignee_id'])
            && (int) $request['current_assignee_id'] === $userId;
    }

    private function completeStage(
        int $stageId,
        int $userId,
        string $status,
        ?string $comment,
        ?int $actedOnBehalfOfUserId = null
    ): void {
        $statement = $this->db->prepare(
            "UPDATE initiative_request_stages
             SET status = :status,
                 acted_by_user_id = :user_id,
                 acted_on_behalf_of_user_id = :acted_on_behalf_of_user_id,
                 acted_at = CURRENT_TIMESTAMP,
                 opened_at = COALESCE(opened_at, CURRENT_TIMESTAMP),
                 decision_comment = :decision_comment
             WHERE request_stage_id = :stage_id
               AND status = 'IN_PROGRESS'"
        );
        $statement->execute([
            'status' => $status,
            'user_id' => $userId,
            'acted_on_behalf_of_user_id' => $actedOnBehalfOfUserId,
            'decision_comment' => $comment,
            'stage_id' => $stageId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new DomainException(
                'The Initiative approval stage could not be completed.'
            );
        }
    }

    private function nextStageForUpdate(
        int $requestId,
        int $cycleNumber,
        int $currentOrder
    ): ?array {
        $statement = $this->db->prepare(
            "SELECT
                request_stage_id,
                stage_order,
                stage_label,
                assigned_user_id,
                responsible_unit_id
             FROM initiative_request_stages
             WHERE request_id = :request_id
               AND cycle_number = :cycle_number
               AND stage_order > :current_order
               AND status = 'PENDING'
             ORDER BY stage_order
             LIMIT 1
             FOR UPDATE"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'current_order' => $currentOrder,
        ]);

        $stage = $statement->fetch();

        if (!$stage) {
            return null;
        }

        if (
            empty($stage['assigned_user_id'])
            || empty($stage['responsible_unit_id'])
        ) {
            throw new DomainException(
                'The next Initiative approval stage has no assigned reviewer.'
            );
        }

        return $stage;
    }

    private function markCurrentStageOpened(
        int $requestId,
        int $userId
    ): void {
        // ADMIN_WORKFLOW_TEMPLATE_MANAGEMENT_V1_OPENED
        $configurableWorkflow =
            new ConfigurableInitiativeWorkflowService($this->db);
        if ($configurableWorkflow->isConfigurableRequest($requestId)) {
            $configurableWorkflow->markCurrentStageOpened(
                $requestId,
                $userId
            );
            return;
        }

        $statement = $this->db->prepare(
            "UPDATE initiative_request_stages stage
             SET opened_at = CURRENT_TIMESTAMP
             FROM initiative_requests request
             WHERE request.request_id = :request_id
               AND request.status IN ('UNDER_REVIEW', 'RESUBMITTED')
               AND stage.request_id = request.request_id
               AND stage.cycle_number = request.revision_cycle
               AND stage.stage_order = request.current_stage_order
               AND stage.status = 'IN_PROGRESS'
               AND stage.opened_at IS NULL
               AND (
                    stage.assigned_user_id = :direct_user_id
                    OR (
                        stage.is_office_delegable = TRUE
                        AND EXISTS (
                            SELECT 1
                            FROM user_positions delegate_position
                            JOIN positions delegate_role_position
                              ON delegate_role_position.position_id =
                                 delegate_position.position_id
                             AND delegate_role_position.name =
                                 CASE stage.stage_key
                                     WHEN 'VICE_PRESIDENT'
                                         THEN 'Vice President Office Delegate'
                                     WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                         THEN 'President Office Delegate'
                                     ELSE '__NO_DELEGATE_POSITION__'
                                 END
                            JOIN users delegate_user
                              ON delegate_user.user_id = delegate_position.user_id
                             AND delegate_user.is_active = TRUE
                            JOIN user_roles delegate_user_role
                              ON delegate_user_role.user_id = delegate_user.user_id
                            JOIN role_permissions delegate_role_permission
                              ON delegate_role_permission.role_id = delegate_user_role.role_id
                            JOIN permissions delegate_permission
                              ON delegate_permission.permission_id = delegate_role_permission.permission_id
                             AND delegate_permission.permission_code = 'APPROVE_INITIATIVE'
                            WHERE delegate_position.user_id = :delegate_user_id
                              AND delegate_position.unit_id = stage.responsible_unit_id
                              AND delegate_position.is_active = TRUE
                              AND (
                                    delegate_position.end_date IS NULL
                                    OR delegate_position.end_date >= CURRENT_DATE
                              )
                        )
                    )
               )
             RETURNING stage.request_stage_id, stage.assigned_user_id"
        );
        $statement->execute([
            'request_id' => $requestId,
            'direct_user_id' => $userId,
            'delegate_user_id' => $userId,
        ]);

        $openedStage = $statement->fetch();

        if ($openedStage) {
            $isDelegate = (int) $openedStage['assigned_user_id'] !== $userId;

            $this->recordEvent(
                $requestId,
                $userId,
                'STAGE_OPENED',
                $isDelegate
                    ? 'An authorized office delegate opened the Initiative request.'
                    : 'The assigned reviewer opened the Initiative request.',
                (int) $openedStage['request_stage_id'],
                $isDelegate
                    ? (int) $openedStage['assigned_user_id']
                    : null,
                null,
                null,
                [
                    'acted_on_behalf_of_user_id' => $isDelegate
                        ? (int) $openedStage['assigned_user_id']
                        : null,
                ]
            );
        }
    }

    private function previousStageId(
        int $requestId,
        int $cycleNumber,
        int $currentStageOrder
    ): ?int {
        if ($currentStageOrder <= 1) {
            return null;
        }

        $statement = $this->db->prepare(
            "SELECT request_stage_id
             FROM initiative_request_stages
             WHERE request_id = CAST(:request_id AS BIGINT)
               AND cycle_number = :cycle_number
               AND stage_order < :current_stage_order
             ORDER BY stage_order DESC
             LIMIT 1"
        );
        $statement->execute([
            'request_id' => $requestId,
            'cycle_number' => $cycleNumber,
            'current_stage_order' => $currentStageOrder,
        ]);
        $stageId = $statement->fetchColumn();

        return $stageId === false ? null : (int) $stageId;
    }

    private function revisionThreadsForUser(
        int $requestId,
        int $userId
    ): array {
        $statement = $this->db->prepare(
            "SELECT
                revision_thread.*,
                request.request_code,
                CONCAT(
                    requester.first_name,
                    ' ',
                    requester.last_name
                ) AS requester_name,
                requested_stage.stage_label AS requested_stage_label,
                requested_stage.stage_order AS requested_stage_order,
                requested_stage.assigned_user_id AS requested_principal_id,
                CONCAT(
                    requested_principal.first_name,
                    ' ',
                    requested_principal.last_name
                ) AS requested_principal_name,
                CONCAT(
                    requested_by.first_name,
                    ' ',
                    requested_by.last_name
                ) AS requested_by_name,
                counterpart_stage.stage_label AS counterpart_stage_label,
                counterpart_stage.stage_order AS counterpart_stage_order,
                CONCAT(
                    counterpart_actor.first_name,
                    ' ',
                    counterpart_actor.last_name
                ) AS counterpart_actor_name,
                CONCAT(
                    counterpart_principal.first_name,
                    ' ',
                    counterpart_principal.last_name
                ) AS counterpart_principal_name
             FROM initiative_revision_threads revision_thread
             JOIN initiative_requests request
               ON request.request_id = revision_thread.request_id
             JOIN users requester
               ON requester.user_id = request.requester_id
             JOIN initiative_request_stages requested_stage
               ON requested_stage.request_stage_id =
                  revision_thread.requested_by_stage_id
             JOIN users requested_by
               ON requested_by.user_id =
                  revision_thread.requested_by_user_id
             LEFT JOIN users requested_principal
               ON requested_principal.user_id =
                  requested_stage.assigned_user_id
             LEFT JOIN initiative_request_stages counterpart_stage
               ON counterpart_stage.request_stage_id =
                  revision_thread.counterpart_stage_id
             LEFT JOIN users counterpart_actor
               ON counterpart_actor.user_id =
                  counterpart_stage.acted_by_user_id
             LEFT JOIN users counterpart_principal
               ON counterpart_principal.user_id =
                  counterpart_stage.assigned_user_id
             WHERE revision_thread.request_id =
                   CAST(:request_id AS BIGINT)
             ORDER BY
                revision_thread.cycle_number DESC,
                COALESCE(
                    revision_thread.last_comment_at,
                    revision_thread.updated_at,
                    revision_thread.created_at
                ) DESC,
                revision_thread.revision_thread_id DESC"
        );
        $statement->execute([
            'request_id' => $requestId,
        ]);
        $threads = $statement->fetchAll();
        $isAdministrator = $this->isSystemAdministrator($userId);

        foreach ($threads as &$thread) {
            $threadId = (int) $thread['revision_thread_id'];
            $isParticipant = $this->isRevisionThreadParticipant(
                $threadId,
                $userId
            );
            $canSeePrivate = $isAdministrator || $isParticipant;

            $commentsSql =
                "SELECT
                    revision_comment.revision_comment_id,
                    revision_comment.author_user_id,
                    revision_comment.visibility,
                    revision_comment.comment_text,
                    revision_comment.created_at,
                    CONCAT(
                        author.first_name,
                        ' ',
                        author.last_name
                    ) AS author_name
                 FROM initiative_revision_comments revision_comment
                 JOIN users author
                   ON author.user_id = revision_comment.author_user_id
                 WHERE revision_comment.revision_thread_id =
                       CAST(:revision_thread_id AS BIGINT)";

            if (!$canSeePrivate) {
                $commentsSql .= " AND revision_comment.visibility = 'PUBLIC'";
            }

            $commentsSql .=
                ' ORDER BY revision_comment.created_at,
                           revision_comment.revision_comment_id';

            $commentsStatement = $this->db->prepare($commentsSql);
            $commentsStatement->execute([
                'revision_thread_id' => $threadId,
            ]);
            $thread['comments'] = $commentsStatement->fetchAll();
            $thread['can_comment'] =
                (string) $thread['status'] === 'OPEN'
                && ($isAdministrator || $isParticipant);
            $thread['can_view_private'] = $canSeePrivate;
            $thread['is_participant'] = $isParticipant;
            $thread['is_administrator'] = $isAdministrator;
            $thread['discussion_scope'] = empty(
                $thread['counterpart_stage_label']
            )
                ? sprintf(
                    'Requester and %s',
                    (string) $thread['requested_stage_label']
                )
                : sprintf(
                    'Requester, %s, and %s',
                    (string) $thread['counterpart_stage_label'],
                    (string) $thread['requested_stage_label']
                );
        }
        unset($thread);

        return $threads;
    }

    private function isRevisionThreadParticipant(
        int $revisionThreadId,
        int $userId
    ): bool {
        return in_array(
            $userId,
            $this->revisionThreadParticipantUserIds(
                $revisionThreadId
            ),
            true
        );
    }

    private function revisionThreadParticipantUserIds(
        int $revisionThreadId
    ): array {
        $statement = $this->db->prepare(
            "WITH thread_context AS (
                SELECT
                    request.requester_id,
                    revision_thread.requested_by_user_id,
                    requested_stage.assigned_user_id
                        AS requested_assigned_user_id,
                    requested_stage.acted_by_user_id
                        AS requested_acted_user_id,
                    requested_stage.acted_on_behalf_of_user_id
                        AS requested_principal_user_id,
                    requested_stage.responsible_unit_id
                        AS requested_unit_id,
                    requested_stage.stage_key
                        AS requested_stage_key,
                    requested_stage.is_office_delegable
                        AS requested_office_delegable,
                    counterpart_stage.assigned_user_id
                        AS counterpart_assigned_user_id,
                    counterpart_stage.acted_by_user_id
                        AS counterpart_acted_user_id,
                    counterpart_stage.acted_on_behalf_of_user_id
                        AS counterpart_principal_user_id,
                    counterpart_stage.responsible_unit_id
                        AS counterpart_unit_id,
                    counterpart_stage.stage_key
                        AS counterpart_stage_key,
                    counterpart_stage.is_office_delegable
                        AS counterpart_office_delegable
                FROM initiative_revision_threads revision_thread
                JOIN initiative_requests request
                  ON request.request_id = revision_thread.request_id
                JOIN initiative_request_stages requested_stage
                  ON requested_stage.request_stage_id =
                     revision_thread.requested_by_stage_id
                LEFT JOIN initiative_request_stages counterpart_stage
                  ON counterpart_stage.request_stage_id =
                     revision_thread.counterpart_stage_id
                WHERE revision_thread.revision_thread_id =
                      CAST(:revision_thread_id AS BIGINT)
             ), participant_ids AS (
                SELECT requester_id AS user_id
                FROM thread_context

                UNION

                SELECT requested_by_user_id
                FROM thread_context

                UNION

                SELECT requested_assigned_user_id
                FROM thread_context

                UNION

                SELECT requested_acted_user_id
                FROM thread_context

                UNION

                SELECT requested_principal_user_id
                FROM thread_context

                UNION

                SELECT counterpart_assigned_user_id
                FROM thread_context

                UNION

                SELECT counterpart_acted_user_id
                FROM thread_context

                UNION

                SELECT counterpart_principal_user_id
                FROM thread_context

                UNION

                SELECT delegate_position.user_id
                FROM thread_context context
                JOIN user_positions delegate_position
                  ON delegate_position.is_active = TRUE
                 AND (
                        delegate_position.end_date IS NULL
                        OR delegate_position.end_date >= CURRENT_DATE
                 )
                 AND (
                        (
                            context.requested_office_delegable = TRUE
                            AND delegate_position.unit_id =
                                context.requested_unit_id
                        )
                        OR (
                            context.counterpart_office_delegable = TRUE
                            AND delegate_position.unit_id =
                                context.counterpart_unit_id
                        )
                 )
                JOIN positions delegate_role_position
                  ON delegate_role_position.position_id =
                     delegate_position.position_id
                 AND delegate_role_position.name =
                     CASE
                         WHEN
                             context.requested_office_delegable = TRUE
                             AND delegate_position.unit_id =
                                 context.requested_unit_id
                         THEN CASE context.requested_stage_key
                             WHEN 'VICE_PRESIDENT'
                                 THEN 'Vice President Office Delegate'
                             WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                 THEN 'President Office Delegate'
                             ELSE '__NO_DELEGATE_POSITION__'
                         END
                         WHEN
                             context.counterpart_office_delegable = TRUE
                             AND delegate_position.unit_id =
                                 context.counterpart_unit_id
                         THEN CASE context.counterpart_stage_key
                             WHEN 'VICE_PRESIDENT'
                                 THEN 'Vice President Office Delegate'
                             WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                                 THEN 'President Office Delegate'
                             ELSE '__NO_DELEGATE_POSITION__'
                         END
                         ELSE '__NO_DELEGATE_POSITION__'
                     END
                JOIN users delegate_user
                  ON delegate_user.user_id = delegate_position.user_id
                 AND delegate_user.is_active = TRUE
                JOIN user_roles delegate_user_role
                  ON delegate_user_role.user_id = delegate_user.user_id
                JOIN role_permissions delegate_role_permission
                  ON delegate_role_permission.role_id =
                     delegate_user_role.role_id
                JOIN permissions delegate_permission
                  ON delegate_permission.permission_id =
                     delegate_role_permission.permission_id
                 AND delegate_permission.permission_code =
                     'APPROVE_INITIATIVE'
             )
             SELECT DISTINCT user_id
             FROM participant_ids
             WHERE user_id IS NOT NULL
             ORDER BY user_id"
        );
        $statement->execute([
            'revision_thread_id' => $revisionThreadId,
        ]);

        return array_map(
            static fn (mixed $value): int => (int) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function requestParticipantUserIds(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT requester_id AS user_id
             FROM initiative_requests
             WHERE request_id = CAST(:request_id AS BIGINT)

             UNION

             SELECT user_id
             FROM initiative_request_members
             WHERE request_id = CAST(:member_request_id AS BIGINT)"
        );
        $statement->execute([
            'request_id' => $requestId,
            'member_request_id' => $requestId,
        ]);

        return array_map(
            static fn (mixed $value): int => (int) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function notifyRevisionThreadUsers(
        int $requestId,
        int $revisionThreadId,
        int $authorUserId,
        string $visibility,
        string $type,
        string $title,
        string $message,
        int $revisionCommentId
    ): void {
        $recipientIds = $this->revisionThreadParticipantUserIds(
            $revisionThreadId
        );

        if ($visibility === 'PUBLIC') {
            $recipientIds = array_merge(
                $recipientIds,
                $this->requestParticipantUserIds($requestId)
            );
        }

        $recipientIds = array_values(array_unique(array_filter(
            array_map('intval', $recipientIds),
            static fn (int $recipientId): bool =>
                $recipientId > 0
                && $recipientId !== $authorUserId
        )));

        if ($recipientIds === []) {
            return;
        }

        $statement = $this->db->prepare(
            "INSERT INTO initiative_notifications (
                request_id,
                recipient_user_id,
                notification_type,
                title,
                message,
                payload,
                dedupe_key
             ) VALUES (
                CAST(:request_id AS BIGINT),
                CAST(:recipient_user_id AS BIGINT),
                :notification_type,
                :title,
                :message,
                jsonb_build_object(
                    'revision_thread_id',
                    CAST(:revision_thread_id AS BIGINT),
                    'revision_comment_id',
                    CAST(:revision_comment_id AS BIGINT),
                    'visibility',
                    CAST(:visibility AS TEXT),
                    'request_url',
                    CONCAT(
                        'initiative-workflow.php?view=detail&id=',
                        CAST(:payload_request_id AS BIGINT),
                        '#revision-discussions'
                    )
                ),
                :dedupe_key
             )
             ON CONFLICT DO NOTHING"
        );

        foreach ($recipientIds as $recipientId) {
            $statement->execute([
                'request_id' => $requestId,
                'recipient_user_id' => $recipientId,
                'notification_type' => $type,
                'title' => $title,
                'message' => $message,
                'revision_thread_id' => $revisionThreadId,
                'revision_comment_id' => $revisionCommentId,
                'visibility' => $visibility,
                'payload_request_id' => $requestId,
                'dedupe_key' => sprintf(
                    '%s:COMMENT:%d',
                    $type,
                    $revisionCommentId
                ),
            ]);
        }
    }

    private function userDisplayName(int $userId): string
    {
        $statement = $this->db->prepare(
            "SELECT COALESCE(
                NULLIF(
                    TRIM(CONCAT(first_name, ' ', last_name)),
                    ''
                ),
                email
             )
             FROM users
             WHERE user_id = CAST(:user_id AS BIGINT)"
        );
        $statement->execute([
            'user_id' => $userId,
        ]);
        $name = $statement->fetchColumn();

        return $name === false
            ? 'University user'
            : (string) $name;
    }


    private function resolveRevisionThreads(int $requestId): void
    {
        $statement = $this->db->prepare(
            "UPDATE initiative_revision_threads
             SET status = 'RESOLVED',
                 resolved_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE request_id = :request_id
               AND status = 'OPEN'"
        );
        $statement->execute(['request_id' => $requestId]);
    }

    private function notifyStageReviewers(
        int $requestId,
        int $stageId,
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
                payload,
                dedupe_key
             )
             SELECT DISTINCT
                CAST(:request_id AS BIGINT),
                reviewer.user_id,
                :notification_type,
                :title,
                :message,
                jsonb_build_object(
                    'request_stage_id', stage.request_stage_id,
                    'stage_label', stage.stage_label,
                    'request_url', CONCAT(
                        'initiative-workflow.php?view=detail&id=',
                        stage.request_id
                    )
                ),
                CONCAT(
                    CAST(:dedupe_prefix AS TEXT),
                    ':STAGE:',
                    CAST(stage.request_stage_id AS TEXT)
                )
             FROM initiative_request_stages stage
             JOIN LATERAL (
                SELECT stage.assigned_user_id AS user_id

                UNION

                SELECT delegate_position.user_id
                FROM user_positions delegate_position
                JOIN positions delegate_role_position
                  ON delegate_role_position.position_id =
                     delegate_position.position_id
                 AND delegate_role_position.name =
                     CASE stage.stage_key
                         WHEN 'VICE_PRESIDENT'
                             THEN 'Vice President Office Delegate'
                         WHEN 'VICE_PRESIDENT_ACADEMIC_AFFAIRS'
                                 THEN 'Vice President for Academic Affairs Office Delegate'
                             WHEN 'PRESIDENT'
                             THEN 'President Office Delegate'
                         ELSE '__NO_DELEGATE_POSITION__'
                     END
                JOIN users delegate_user
                  ON delegate_user.user_id = delegate_position.user_id
                 AND delegate_user.is_active = TRUE
                JOIN user_roles delegate_user_role
                  ON delegate_user_role.user_id = delegate_user.user_id
                JOIN role_permissions delegate_role_permission
                  ON delegate_role_permission.role_id = delegate_user_role.role_id
                JOIN permissions delegate_permission
                  ON delegate_permission.permission_id = delegate_role_permission.permission_id
                 AND delegate_permission.permission_code = 'APPROVE_INITIATIVE'
                WHERE stage.is_office_delegable = TRUE
                  AND delegate_position.unit_id = stage.responsible_unit_id
                  AND delegate_position.is_active = TRUE
                  AND (
                        delegate_position.end_date IS NULL
                        OR delegate_position.end_date >= CURRENT_DATE
                  )
             ) reviewer ON reviewer.user_id IS NOT NULL
             WHERE stage.request_stage_id = :stage_id
             ON CONFLICT DO NOTHING"
        );
        $statement->execute([
            'request_id' => $requestId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'dedupe_prefix' => $type,
            'stage_id' => $stageId,
        ]);
    }

    private function notifyUser(
        int $requestId,
        int $recipientUserId,
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
             ) VALUES (
                :request_id,
                :recipient_user_id,
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
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'recipient_user_id' => $recipientUserId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'payload_request_id' => $requestId,
        ]);
    }

    private function notifyRequestParticipants(
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
                WHERE request_id = :request_lookup_id

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
            'request_lookup_id' => $requestId,
            'member_request_id' => $requestId,
            'payload_request_id' => $requestId,
        ]);
    }

    private function recordEvent(
        int $requestId,
        int $actorUserId,
        string $eventType,
        string $note,
        ?int $requestStageId = null,
        ?int $targetUserId = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $eventData = []
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_request_events (
                request_id,
                request_stage_id,
                cycle_number,
                event_type,
                actor_user_id,
                target_user_id,
                from_status,
                to_status,
                event_note,
                event_data
             )
             SELECT
                request_id,
                :request_stage_id,
                revision_cycle,
                :event_type,
                :actor_user_id,
                :target_user_id,
                :from_status,
                :to_status,
                :event_note,
                CAST(:event_data AS JSONB)
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'request_id' => $requestId,
            'request_stage_id' => $requestStageId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'target_user_id' => $targetUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'event_note' => $note,
            'event_data' => json_encode(
                $eventData === [] ? new stdClass() : $eventData,
                JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function assertRequestReadyForSubmission(
        int $requestId
    ): void {
        $statement = $this->db->prepare(
            "SELECT
                title,
                description,
                initiative_type,
                objective,
                expected_impact,
                beneficiaries,
                proposed_start_date,
                proposed_end_date,
                related_agreement_id,
                relationship_type,
                external_partner_country,
                requester_mobile,
                requester_type,
                requester_type_other,
                primary_type_other,
                secondary_types,
                target_groups,
                target_group_other,
                expected_participants,
                implementation_scope,
                implementation_scope_other,
                proposed_venue,
                proposed_venue_place_id,
                proposed_venue_name,
                proposed_venue_latitude,
                proposed_venue_longitude,
                proposed_venue_country_code,
                implementation_country,
                online_platform_name,
                international_participation,
                international_countries,
                international_partner,
                has_related_agreement,
                has_external_partner,
                external_partner_name,
                external_partner_role,
                required_resources,
                resource_other,
                estimated_budget,
                needs_media_support,
                supports_sdg,
                sdg_goals,
                declaration_confirmed
             FROM initiative_requests
             WHERE request_id = :request_id"
        );
        $statement->execute([
            'request_id' => $requestId,
        ]);
        $request = $statement->fetch();

        if (!$request) {
            throw new InvalidArgumentException(
                'Initiative request not found.'
            );
        }

        $this->normalizeRequestPayload(
            $request,
            true
        );
    }

    private function normalizeRequestPayload(
        array $payload,
        bool $forSubmission
    ): array {
        $requesterTypes = [
            'FACULTY',
            'STAFF',
            'STUDENT',
            'STUDENT_GROUP',
            'OTHER',
        ];
        $initiativeTypes = [
            'WORKSHOP_TRAINING',
            'LECTURE_SEMINAR',
            'STUDENT_INITIATIVE',
            'COMMUNITY_ENGAGEMENT',
            'VOLUNTEERING',
            'AWARENESS_CAMPAIGN',
            'RESEARCH',
            'CONSULTATION',
            'PARTNERSHIP',
            'SUSTAINABILITY',
            'ACADEMIC',
            'INNOVATION',
            'OTHER',
        ];
        $targetGroups = [
            'UNIVERSITY_STUDENTS',
            'SCHOOL_STUDENTS',
            'FACULTY_STAFF',
            'PROFESSIONALS',
            'ALUMNI',
            'LOCAL_COMMUNITY',
            'VULNERABLE_GROUPS',
            'OPEN_PUBLIC',
            'OTHER',
        ];
        $implementationScopes = [
            'WITHIN_UOB',
            'OUTSIDE_UOB',
            'VIRTUAL',
            'HYBRID',
            'OTHER',
        ];
        $relationshipTypes = [
            'NO_EXTERNAL_PARTY',
            'EXTERNAL_WITHOUT_AGREEMENT',
            'LINKED_AGREEMENTS',
            'UNSURE',
        ];
        $resourceOptions = [
            'VENUE',
            'BUDGET',
            'MEDIA',
            'TRANSPORT',
            'EQUIPMENT',
            'NONE',
            'OTHER',
        ];
        $sdgGoals = array_map(
            static fn (int $number): string =>
                'SDG_' . $number,
            range(1, 17)
        );

        $data = [
            'title' => trim(
                (string) ($payload['title'] ?? '')
            ),
            'description' => $this->nullableText(
                $payload['description'] ?? null
            ),
            'initiative_type' => $this->normalizeCode(
                $payload['initiative_type'] ?? null,
                $initiativeTypes,
                'initiative type'
            ),
            'objective' => $this->nullableText(
                $payload['objective'] ?? null
            ),
            'expected_impact' => $this->nullableText(
                $payload['expected_impact'] ?? null
            ),
            'beneficiaries' => $this->nullableText(
                $payload['beneficiaries'] ?? null
            ),
            'proposed_start_date' => $this->nullableDate(
                $payload['proposed_start_date'] ?? null
            ),
            'proposed_end_date' => $this->nullableDate(
                $payload['proposed_end_date'] ?? null
            ),
            'relationship_type' =>
                $this->normalizeCode(
                    $payload['relationship_type'] ?? null,
                    $relationshipTypes,
                    'external relationship type'
                ),
            'related_agreement_ids' =>
                $this->normalizeIntegerArray(
                    $payload['related_agreement_ids']
                        ?? (
                            isset($payload['related_agreement_id'])
                                ? [$payload['related_agreement_id']]
                                : []
                        )
                ),
            'related_agreement_id' =>
                $this->nullableInteger(
                    $payload['related_agreement_id'] ?? null
                ),
            'requester_mobile' => $this->nullableText(
                $payload['requester_mobile'] ?? null
            ),
            'requester_type' => $this->normalizeCode(
                $payload['requester_type'] ?? null,
                $requesterTypes,
                'requester type'
            ),
            'requester_type_other' =>
                $this->nullableText(
                    $payload['requester_type_other'] ?? null
                ),
            'primary_type_other' =>
                $this->nullableText(
                    $payload['primary_type_other'] ?? null
                ),
            'secondary_types' =>
                $this->normalizeCodeArray(
                    $payload['secondary_types'] ?? [],
                    array_values(
                        array_filter(
                            $initiativeTypes,
                            static fn (string $type): bool =>
                                $type !== 'OTHER'
                        )
                    )
                ),
            'target_groups' =>
                $this->normalizeCodeArray(
                    $payload['target_groups'] ?? [],
                    $targetGroups
                ),
            'target_group_other' =>
                $this->nullableText(
                    $payload['target_group_other'] ?? null
                ),
            'expected_participants' =>
                $this->nullableNonNegativeInteger(
                    $payload['expected_participants'] ?? null,
                    'Expected participants'
                ),
            'implementation_scope' =>
                $this->normalizeCode(
                    $payload['implementation_scope'] ?? null,
                    $implementationScopes,
                    'implementation scope'
                ),
            'implementation_scope_other' =>
                $this->nullableText(
                    $payload[
                        'implementation_scope_other'
                    ] ?? null
                ),
            'proposed_venue' => $this->nullableText(
                $payload['proposed_venue'] ?? null
            ),
            'proposed_venue_place_id' =>
                $this->nullableText(
                    $payload['proposed_venue_place_id'] ?? null
                ),
            'proposed_venue_name' =>
                $this->nullableText(
                    $payload['proposed_venue_name'] ?? null
                ),
            'proposed_venue_latitude' =>
                $this->nullableCoordinate(
                    $payload['proposed_venue_latitude'] ?? null,
                    -90.0,
                    90.0,
                    'Venue latitude'
                ),
            'proposed_venue_longitude' =>
                $this->nullableCoordinate(
                    $payload['proposed_venue_longitude'] ?? null,
                    -180.0,
                    180.0,
                    'Venue longitude'
                ),
            'proposed_venue_country_code' =>
                $this->nullableCountryCode(
                    $payload['proposed_venue_country_code'] ?? null
                ),
            'implementation_country' =>
                $this->nullableText(
                    $payload['implementation_country'] ?? null
                ),
            'online_platform_name' =>
                $this->nullableText(
                    $payload['online_platform_name'] ?? null
                ),
            'international_participation' =>
                $this->nullableBoolean(
                    $payload['international_participation'] ?? null
                ),
            'international_countries' =>
                $this->normalizeTextArray(
                    $payload['international_countries'] ?? []
                ),
            'international_partner' =>
                $this->nullableText(
                    $payload['international_partner'] ?? null
                ),
            'has_related_agreement' =>
                $this->nullableBoolean(
                    $payload['has_related_agreement'] ?? null
                ),
            'has_external_partner' =>
                $this->nullableBoolean(
                    $payload['has_external_partner'] ?? null
                ),
            'external_partner_name' =>
                $this->nullableText(
                    $payload['external_partner_name'] ?? null
                ),
            'external_partner_country' =>
                $this->nullableText(
                    $payload['external_partner_country'] ?? null
                ),
            'external_partner_role' =>
                $this->nullableText(
                    $payload['external_partner_role'] ?? null
                ),
            'required_resources' =>
                $this->normalizeCodeArray(
                    $payload['required_resources'] ?? [],
                    $resourceOptions
                ),
            'resource_other' => $this->nullableText(
                $payload['resource_other'] ?? null
            ),
            'estimated_budget' =>
                $this->nullableNonNegativeDecimal(
                    $payload['estimated_budget'] ?? null,
                    'Estimated budget'
                ),
            'needs_media_support' =>
                $this->nullableBoolean(
                    $payload['needs_media_support'] ?? null
                ),
            'supports_sdg' => $this->nullableBoolean(
                $payload['supports_sdg'] ?? null
            ),
            'sdg_goals' => $this->normalizeCodeArray(
                $payload['sdg_goals'] ?? [],
                $sdgGoals
            ),
            'declaration_confirmed' =>
                $this->nullableBoolean(
                    $payload['declaration_confirmed']
                        ?? false
                ) ?? false,
        ];

        if ($data['title'] === '') {
            throw new InvalidArgumentException(
                'Initiative title is required before saving.'
            );
        }

        if ($data['initiative_type'] === null) {
            throw new InvalidArgumentException(
                'Primary initiative type is required before saving.'
            );
        }

        if (
            $data['proposed_start_date'] !== null
            && $data['proposed_end_date'] !== null
            && $data['proposed_end_date']
                < $data['proposed_start_date']
        ) {
            throw new InvalidArgumentException(
                'The proposed end date cannot be before the start date.'
            );
        }

        if (
            $data['requester_type'] === 'OTHER'
            && $data['requester_type_other'] === null
            && $forSubmission
        ) {
            throw new InvalidArgumentException(
                'Specify the other requester type.'
            );
        }

        if (
            $data['initiative_type'] === 'OTHER'
            && $data['primary_type_other'] === null
            && $forSubmission
        ) {
            throw new InvalidArgumentException(
                'Specify the other primary initiative type.'
            );
        }

        if (
            in_array(
                'OTHER',
                $data['target_groups'],
                true
            )
            && $data['target_group_other'] === null
            && $forSubmission
        ) {
            throw new InvalidArgumentException(
                'Specify the other target audience.'
            );
        }

        if (
            in_array(
                'OTHER',
                $data['required_resources'],
                true
            )
            && $data['resource_other'] === null
            && $forSubmission
        ) {
            throw new InvalidArgumentException(
                'Specify the other required resource.'
            );
        }

        if (
            in_array(
                'NONE',
                $data['required_resources'],
                true
            )
            && count($data['required_resources']) > 1
        ) {
            throw new InvalidArgumentException(
                'No additional requirements cannot be combined with other resource choices.'
            );
        }

        $locationScope = $data['implementation_scope'];

        if (!in_array($locationScope, ['OUTSIDE_UOB', 'HYBRID'], true)) {
            $data['proposed_venue'] = null;
            $data['proposed_venue_place_id'] = null;
            $data['proposed_venue_name'] = null;
            $data['proposed_venue_latitude'] = null;
            $data['proposed_venue_longitude'] = null;
            $data['proposed_venue_country_code'] = null;
            $data['implementation_country'] = null;
        }

        if ($data['proposed_venue'] === null) {
            $data['proposed_venue_place_id'] = null;
            $data['proposed_venue_name'] = null;
            $data['proposed_venue_latitude'] = null;
            $data['proposed_venue_longitude'] = null;
            $data['proposed_venue_country_code'] = null;
        }

        $hasVenueLatitude =
            $data['proposed_venue_latitude'] !== null;
        $hasVenueLongitude =
            $data['proposed_venue_longitude'] !== null;

        if ($hasVenueLatitude !== $hasVenueLongitude) {
            $data['proposed_venue_latitude'] = null;
            $data['proposed_venue_longitude'] = null;
        }

        if (
            $data['proposed_venue_place_id'] === null
            && $data['proposed_venue_latitude'] === null
            && $data['proposed_venue_longitude'] === null
        ) {
            $data['proposed_venue_name'] = null;
            $data['proposed_venue_country_code'] = null;
        }

        if (!in_array($locationScope, ['VIRTUAL', 'HYBRID'], true)) {
            $data['online_platform_name'] = null;
        }

        if ($data['international_participation'] !== true) {
            $data['international_countries'] = [];
            $data['international_partner'] = null;
        }

        if ($data['relationship_type'] === null) {
            if (
                $data['has_related_agreement'] === true
                || $data['related_agreement_ids'] !== []
                || $data['related_agreement_id'] !== null
            ) {
                $data['relationship_type'] = 'LINKED_AGREEMENTS';
            } elseif ($data['has_external_partner'] === true) {
                $data['relationship_type'] =
                    'EXTERNAL_WITHOUT_AGREEMENT';
            } elseif (
                $data['has_related_agreement'] === false
                && $data['has_external_partner'] === false
            ) {
                $data['relationship_type'] = 'NO_EXTERNAL_PARTY';
            }
        }

        if (
            $data['related_agreement_id'] !== null
            && !in_array(
                $data['related_agreement_id'],
                $data['related_agreement_ids'],
                true
            )
        ) {
            array_unshift(
                $data['related_agreement_ids'],
                $data['related_agreement_id']
            );
            $data['related_agreement_ids'] = array_values(
                array_unique($data['related_agreement_ids'])
            );
        }

        switch ($data['relationship_type']) {
            case 'LINKED_AGREEMENTS':
                $data['has_related_agreement'] = true;
                $data['has_external_partner'] = true;
                $data['related_agreement_id'] =
                    $data['related_agreement_ids'][0] ?? null;
                $data['external_partner_name'] = null;
                $data['external_partner_country'] = null;
                $data['external_partner_role'] = null;
                break;

            case 'EXTERNAL_WITHOUT_AGREEMENT':
                $data['has_related_agreement'] = false;
                $data['has_external_partner'] = true;
                $data['related_agreement_ids'] = [];
                $data['related_agreement_id'] = null;
                break;

            case 'NO_EXTERNAL_PARTY':
                $data['has_related_agreement'] = false;
                $data['has_external_partner'] = false;
                $data['related_agreement_ids'] = [];
                $data['related_agreement_id'] = null;
                $data['external_partner_name'] = null;
                $data['external_partner_country'] = null;
                $data['external_partner_role'] = null;
                break;

            case 'UNSURE':
                $data['has_related_agreement'] = null;
                $data['has_external_partner'] = null;
                $data['related_agreement_ids'] = [];
                $data['related_agreement_id'] = null;
                $data['external_partner_name'] = null;
                $data['external_partner_country'] = null;
                $data['external_partner_role'] = null;
                break;

            default:
                $data['related_agreement_ids'] = [];
                $data['related_agreement_id'] = null;
                $data['external_partner_name'] = null;
                $data['external_partner_country'] = null;
                $data['external_partner_role'] = null;
                break;
        }

        if ($data['supports_sdg'] !== true) {
            $data['sdg_goals'] = [];
        }

        if ($forSubmission) {
            $missing = [];

            if ($data['requester_type'] === null) {
                $missing[] = 'requester type';
            }
            if ($data['description'] === null) {
                $missing[] = 'initiative description';
            }
            if ($data['objective'] === null) {
                $missing[] = 'initiative objective';
            }
            if ($data['target_groups'] === []) {
                $missing[] = 'target audience';
            }
            if ($data['expected_participants'] === null) {
                $missing[] = 'expected participants';
            }
            if ($data['proposed_start_date'] === null) {
                $missing[] = 'proposed start date';
            }
            if ($data['proposed_end_date'] === null) {
                $missing[] = 'proposed end date';
            }
            if ($data['implementation_scope'] === null) {
                $missing[] = 'implementation scope';
            }
            if (
                in_array(
                    $data['implementation_scope'],
                    ['OUTSIDE_UOB', 'HYBRID'],
                    true
                )
                && $data['proposed_venue'] === null
            ) {
                $missing[] = 'proposed venue or location';
            }
            if (
                in_array(
                    $data['implementation_scope'],
                    ['OUTSIDE_UOB', 'HYBRID'],
                    true
                )
                && $data['implementation_country'] === null
            ) {
                $missing[] = 'country';
            }
            if (
                (
                    $data['proposed_venue_place_id'] !== null
                    || $data['proposed_venue_latitude'] !== null
                    || $data['proposed_venue_longitude'] !== null
                )
                && (
                    $data['proposed_venue_latitude'] === null
                    || $data['proposed_venue_longitude'] === null
                )
            ) {
                $missing[] =
                    'complete Google Maps venue coordinates';
            }
            if (
                in_array(
                    $data['implementation_scope'],
                    ['VIRTUAL', 'HYBRID'],
                    true
                )
                && $data['online_platform_name'] === null
            ) {
                $missing[] = 'online platform name';
            }
            if ($data['relationship_type'] === null) {
                $missing[] = 'external relationship type';
            }
            if (
                $data['relationship_type'] === 'LINKED_AGREEMENTS'
                && $data['related_agreement_ids'] === []
            ) {
                $missing[] = 'at least one related Agreement';
            }
            if (
                $data['relationship_type'] ===
                    'EXTERNAL_WITHOUT_AGREEMENT'
                && $data['external_partner_name'] === null
            ) {
                $missing[] = 'external entity name';
            }
            if (
                $data['relationship_type'] ===
                    'EXTERNAL_WITHOUT_AGREEMENT'
                && $data['external_partner_country'] === null
            ) {
                $missing[] = 'external entity country';
            }
            if ($data['international_participation'] === null) {
                $missing[] = 'international participation choice';
            }
            if (
                $data['international_participation'] === true
                && $data['international_countries'] === []
            ) {
                $missing[] = 'international participation countries';
            }
            if ($data['required_resources'] === []) {
                $missing[] = 'required resources';
            }
            if ($data['supports_sdg'] === null) {
                $missing[] = 'SDG support choice';
            }
            if (
                $data['supports_sdg'] === true
                && $data['sdg_goals'] === []
            ) {
                $missing[] = 'at least one SDG';
            }
            if (!$data['declaration_confirmed']) {
                $missing[] = 'final declaration';
            }

            if ($missing !== []) {
                throw new InvalidArgumentException(
                    'Complete the following before submission: '
                    . implode(', ', $missing)
                    . '.'
                );
            }
        }

        return $data;
    }

    private function attachments(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                attachment.attachment_id,
                attachment.request_id,
                attachment.original_name,
                attachment.file_extension,
                attachment.mime_type,
                attachment.file_size_bytes,
                attachment.uploaded_at,
                CONCAT(
                    uploader.first_name,
                    ' ',
                    uploader.last_name
                ) AS uploaded_by_name
             FROM initiative_request_attachments attachment
             JOIN users uploader
               ON uploader.user_id =
                  attachment.uploaded_by
             WHERE attachment.request_id = :request_id
             ORDER BY
                attachment.uploaded_at,
                attachment.attachment_id"
        );
        $statement->execute([
            'request_id' => $requestId,
        ]);

        return $statement->fetchAll();
    }

    private function assertRequestEditableByOwner(
        int $requestId,
        int $userId
    ): void {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_requests
                WHERE request_id = :request_id
                  AND requester_id = :requester_id
                  AND status IN (
                      'DRAFT',
                      'REVISION_REQUIRED'
                  )
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'requester_id' => $userId,
        ]);

        if (
            !filter_var(
                $statement->fetchColumn(),
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            throw new DomainException(
                'Attachments can only be changed by the requester while the request is editable.'
            );
        }
    }

    private function normalizeUploadedFiles(
        array $files
    ): array {
        $names = $files['name'] ?? [];

        if (!is_array($names)) {
            $names = [$names];
        }

        $normalized = [];

        foreach (array_keys($names) as $index) {
            $name = is_array($files['name'] ?? null)
                ? ($files['name'][$index] ?? '')
                : ($files['name'] ?? '');
            $error = is_array($files['error'] ?? null)
                ? ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE)
                : ($files['error'] ?? UPLOAD_ERR_NO_FILE);

            if (
                (int) $error === UPLOAD_ERR_NO_FILE
                || trim((string) $name) === ''
            ) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'type' => is_array($files['type'] ?? null)
                    ? ($files['type'][$index] ?? '')
                    : ($files['type'] ?? ''),
                'tmp_name' =>
                    is_array($files['tmp_name'] ?? null)
                        ? (
                            $files['tmp_name'][$index]
                            ?? ''
                        )
                        : ($files['tmp_name'] ?? ''),
                'error' => $error,
                'size' => is_array($files['size'] ?? null)
                    ? ($files['size'][$index] ?? 0)
                    : ($files['size'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function suggestedRequesterType(
        string $roleKey
    ): string {
        return match ($roleKey) {
            'FACULTY',
            'DEPARTMENT_HEAD',
            'DEAN' => 'FACULTY',
            'STUDENT' => 'STUDENT',
            default => 'STAFF',
        };
    }

    private function normalizeCode(
        mixed $value,
        array $allowed,
        string $label
    ): ?string {
        $normalized = strtoupper(
            trim((string) $value)
        );

        if ($normalized === '') {
            return null;
        }

        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The selected %s is not supported.',
                    $label
                )
            );
        }

        return $normalized;
    }

    private function normalizeIntegerArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded)
                ? $decoded
                : (preg_split('/\s*,\s*/', trim($value)) ?: []);
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (filter_var($item, FILTER_VALIDATE_INT) === false) {
                continue;
            }

            $id = (int) $item;
            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function replaceRequestAgreementLinks(
        int $requestId,
        array $agreementIds
    ): void {
        $delete = $this->db->prepare(
            'DELETE FROM initiative_request_agreements
             WHERE request_id = :request_id'
        );
        $delete->execute(['request_id' => $requestId]);

        if ($agreementIds === []) {
            return;
        }

        $exists = $this->db->prepare(
            'SELECT EXISTS (
                SELECT 1
                FROM agreements
                WHERE agreement_id = :agreement_id
            )'
        );
        $insert = $this->db->prepare(
            'INSERT INTO initiative_request_agreements (
                request_id,
                agreement_id
             ) VALUES (
                :request_id,
                :agreement_id
             )
             ON CONFLICT DO NOTHING'
        );

        foreach ($agreementIds as $agreementId) {
            $exists->execute(['agreement_id' => $agreementId]);
            if (!filter_var(
                $exists->fetchColumn(),
                FILTER_VALIDATE_BOOLEAN
            )) {
                throw new InvalidArgumentException(
                    'One of the selected Agreements does not exist.'
                );
            }

            $insert->execute([
                'request_id' => $requestId,
                'agreement_id' => $agreementId,
            ]);
        }
    }

    private function requestAgreementIds(int $requestId): array
    {
        $statement = $this->db->prepare(
            'SELECT agreement_id
             FROM initiative_request_agreements
             WHERE request_id = :request_id
             ORDER BY agreement_id'
        );
        $statement->execute(['request_id' => $requestId]);

        return array_map(
            static fn (mixed $value): int => (int) $value,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function requestAgreements(int $requestId): array
    {
        $statement = $this->db->prepare(
            'SELECT
                agreement.agreement_id,
                agreement.agreement_code,
                agreement.title,
                agreement.status
             FROM initiative_request_agreements request_agreement
             JOIN agreements agreement
               ON agreement.agreement_id = request_agreement.agreement_id
             WHERE request_agreement.request_id = :request_id
             ORDER BY agreement.agreement_code, agreement.title'
        );
        $statement->execute(['request_id' => $requestId]);

        return $statement->fetchAll();
    }
    private function normalizeTextArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*,\s*/', trim($value)) ?: [];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $normalized[] = substr($text, 0, 120);
            }
        }

        return array_values(array_unique($normalized));
    }
    private function normalizeCodeArray(
        mixed $value,
        array $allowed
    ): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            } elseif (trim($value) === '') {
                $value = [];
            } else {
                $value = [$value];
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $code = strtoupper(
                trim((string) $item)
            );

            if (
                $code !== ''
                && in_array($code, $allowed, true)
            ) {
                $normalized[] = $code;
            }
        }

        return array_values(
            array_unique($normalized)
        );
    }

    private function decodeJsonArray(
        mixed $value
    ): array {
        if (is_array($value)) {
            return array_values($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values($decoded)
            : [];
    }

    private function jsonArray(array $value): string
    {
        return json_encode(
            array_values($value),
            JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_UNICODE
        );
    }

    private function nullableBoolean(
        mixed $value
    ): ?bool {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true'
            || $value === 'yes'
            || $value === 'YES'
        ) {
            return true;
        }

        if (
            $value === false
            || $value === 0
            || $value === '0'
            || $value === 'f'
            || $value === 'false'
            || $value === 'no'
            || $value === 'NO'
        ) {
            return false;
        }

        return null;
    }

    private function databaseBoolean(
        ?bool $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        return $value ? 'true' : 'false';
    }

    private function nullableNonNegativeInteger(
        mixed $value,
        string $label
    ): ?int {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT
        );

        if ($integer === false || $integer < 0) {
            throw new InvalidArgumentException(
                $label . ' must be zero or a positive whole number.'
            );
        }

        return (int) $integer;
    }

    private function nullableNonNegativeDecimal(
        mixed $value,
        string $label
    ): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(
                $label . ' must be zero or a positive number.'
            );
        }

        return number_format(
            (float) $value,
            3,
            '.',
            ''
        );
    }

    private function nullableCoordinate(
        mixed $value,
        float $minimum,
        float $maximum,
        string $label
    ): ?string {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(
                $label . ' must be numeric.'
            );
        }

        $coordinate = (float) $value;

        if (
            !is_finite($coordinate)
            || $coordinate < $minimum
            || $coordinate > $maximum
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must be between %s and %s.',
                    $label,
                    $minimum,
                    $maximum
                )
            );
        }

        return number_format(
            $coordinate,
            7,
            '.',
            ''
        );
    }

    private function nullableCountryCode(
        mixed $value
    ): ?string {
        $code = strtoupper(trim((string) $value));

        if ($code === '') {
            return null;
        }

        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            throw new InvalidArgumentException(
                'Venue country code must use two letters.'
            );
        }

        return $code;
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

        return $integer === false || $integer < 1
            ? null
            : $integer;
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
