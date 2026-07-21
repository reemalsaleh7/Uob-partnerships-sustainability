<?php
require_once __DIR__ . '/../config/database.php';

class AgreementRepository {
    private PDO $db;

    private const CONTENT_FIELDS = [
        'title',
        'title_ar',
        'agreement_type',
        'description',
        'geographic_scope',
        'start_date',
        'end_date',
        'effective_date',
        'signing_date',
        'auto_renew',
        'renewal_term_months',
        'non_renewal_notice_months',
        'termination_notice_months',
        'responsible_unit_id',
        'need_justification',
        'expected_value',
        'objectives',
        'focus_areas',
        'collaboration_areas',
        'implementation_methods',
        'financial_commitments',
        'financial_amount',
        'financial_currency',
        'financial_description',
        'human_resources_commitments',
        'human_resources_description',
        'training_programs',
        'training_programs_description',
        'annual_report_required',
        'monitoring_plan',
        'confidentiality_terms',
        'intellectual_property_terms',
        'compliance_terms',
        'relationship_disclaimer',
        'amendment_terms',
        'dispute_resolution_terms',
        'other_terms',
        'legal_binding_status',
        'signing_link',
    ];

    private const BOOLEAN_FIELDS = [
        'auto_renew',
        'financial_commitments',
        'human_resources_commitments',
        'training_programs',
        'annual_report_required',
    ];

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int {
        $columns = ['created_by', 'status', 'created_at'];
        $values = [':created_by', ':status', 'NOW()'];
        $params = [
            'created_by' => $data['created_by'],
            'status' => $data['status'] ?? 'DRAFT',
        ];

        foreach (self::CONTENT_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $columns[] = $field;
            $values[] = ':' . $field;
            $params[$field] = $this->databaseValue($field, $data[$field]);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO agreements (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', $values) . ') RETURNING agreement_id'
        );

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $agreementId, array $data): void {
        $fields = [];
        $params = ['agreement_id' => $agreementId];

        foreach (array_merge(self::CONTENT_FIELDS, ['status']) as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $this->databaseValue($field, $data[$field]);
            }
        }

        if (empty($fields)) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE agreements SET ' . implode(', ', $fields) . ' WHERE agreement_id = :agreement_id'
        );
        $stmt->execute($params);
    }

    public function delete(int $agreementId): void {
        $stmt = $this->db->prepare('DELETE FROM agreements WHERE agreement_id = :agreement_id');
        $stmt->execute(['agreement_id' => $agreementId]);
    }

    public function findById(int $agreementId): ?array {
        $stmt = $this->db->prepare('
            SELECT
                a.*,
                COALESCE(
                    ou.name,
                    NULLIF(ali.source_payload->>\'owner_entity\', \'\')
                ) AS responsible_unit_name
            FROM agreements a
            LEFT JOIN organizational_units ou
                ON ou.unit_id = a.responsible_unit_id
            LEFT JOIN agreement_legacy_imports ali
                ON ali.agreement_id = a.agreement_id
            WHERE a.agreement_id = :agreement_id
            LIMIT 1
        ');
        $stmt->execute(['agreement_id' => $agreementId]);
        $agreement = $stmt->fetch();
        return $agreement ? $this->hydrateAgreement($agreement) : null;
    }

    public function findAll(): array {
        $stmt = $this->db->query('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            ORDER BY a.created_at DESC, ap.partner_id
        ');
        return $stmt->fetchAll();
    }

    public function findVisibleToUser(int $userId, bool $isSystemAdministrator = false): array {
        if ($isSystemAdministrator) {
            return $this->findAll();
        }

        $stmt = $this->db->prepare('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            WHERE
                a.created_by = :creator_user_id
                OR a.status IN (\'APPROVED\', \'ACTIVE\')
                OR (
                    a.status = \'UNDER_REVIEW\'
                    AND EXISTS (
                        SELECT 1
                        FROM workflow_instances wi
                        JOIN workflow_instance_steps wis
                            ON wis.workflow_instance_id = wi.workflow_instance_id
                        JOIN workflow_step_assignments wsa
                            ON wsa.workflow_instance_step_id = wis.instance_step_id
                        WHERE wi.entity_type = \'AGREEMENT\'
                          AND wi.entity_id = a.agreement_id
                          AND wi.status = \'IN_PROGRESS\'
                          AND wis.status = \'IN_PROGRESS\'
                          AND wsa.user_id = :reviewer_user_id
                          AND wsa.is_active = TRUE
                    )
                )
            ORDER BY a.created_at DESC, ap.partner_id
        ');
        $stmt->execute([
            'creator_user_id' => $userId,
            'reviewer_user_id' => $userId,
        ]);

        return $stmt->fetchAll();
    }

    public function findByIdVisibleToUser(
        int $agreementId,
        int $userId,
        bool $isSystemAdministrator = false
    ): ?array {
        if ($isSystemAdministrator) {
            return $this->findById($agreementId);
        }

        $stmt = $this->db->prepare('
            SELECT
                a.*,
                COALESCE(
                    ou.name,
                    NULLIF(ali.source_payload->>\'owner_entity\', \'\')
                ) AS responsible_unit_name
            FROM agreements a
            LEFT JOIN organizational_units ou
                ON ou.unit_id = a.responsible_unit_id
            LEFT JOIN agreement_legacy_imports ali
                ON ali.agreement_id = a.agreement_id
            WHERE a.agreement_id = :agreement_id
              AND (
                  a.created_by = :creator_user_id
                  OR a.status IN (\'APPROVED\', \'ACTIVE\')
                  OR (
                      a.status = \'UNDER_REVIEW\'
                      AND EXISTS (
                          SELECT 1
                          FROM workflow_instances wi
                          JOIN workflow_instance_steps wis
                              ON wis.workflow_instance_id = wi.workflow_instance_id
                          JOIN workflow_step_assignments wsa
                              ON wsa.workflow_instance_step_id = wis.instance_step_id
                          WHERE wi.entity_type = \'AGREEMENT\'
                            AND wi.entity_id = a.agreement_id
                            AND wi.status = \'IN_PROGRESS\'
                            AND wis.status = \'IN_PROGRESS\'
                            AND wsa.user_id = :reviewer_user_id
                            AND wsa.is_active = TRUE
                      )
                  )
              )
            LIMIT 1
        ');
        $stmt->execute([
            'agreement_id' => $agreementId,
            'creator_user_id' => $userId,
            'reviewer_user_id' => $userId,
        ]);

        $agreement = $stmt->fetch();

        return $agreement ? $this->hydrateAgreement($agreement) : null;
    }

    public function findByStatus(string $status): array {
        $stmt = $this->db->prepare('
            SELECT
                a.*,
                ap.partner_id,
                p.organization_name AS partner_name
            FROM agreements a
            LEFT JOIN agreement_partners ap ON ap.agreement_id = a.agreement_id
            LEFT JOIN partners p ON p.partner_id = ap.partner_id
            WHERE a.status = :status
            ORDER BY a.created_at DESC, ap.partner_id
        ');
        $stmt->execute(['status' => $status]);
        return $stmt->fetchAll();
    }

    public function changeStatus(int $agreementId, string $status): void {
        $stmt = $this->db->prepare('UPDATE agreements SET status = :status WHERE agreement_id = :agreement_id');
        $stmt->execute(['status' => $status, 'agreement_id' => $agreementId]);
    }

    public function replacePartners(int $agreementId, array $partnerIds): void {
        $delete = $this->db->prepare('DELETE FROM agreement_partners WHERE agreement_id = :agreement_id');
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare(
            'INSERT INTO agreement_partners (agreement_id, partner_id) VALUES (:agreement_id, :partner_id)'
        );
        foreach (array_unique(array_map('intval', $partnerIds)) as $partnerId) {
            if ($partnerId <= 0) {
                continue;
            }
            $insert->execute(['agreement_id' => $agreementId, 'partner_id' => $partnerId]);
        }
    }

    public function replaceSdgs(int $agreementId, array $sdgNumbers): void {
        $this->replaceSimpleChildren(
            'agreement_sdgs',
            'sdg_number',
            $agreementId,
            array_values(array_unique(array_map('intval', $sdgNumbers)))
        );
    }

    public function replaceRankings(int $agreementId, array $rankingCodes): void {
        $values = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => strtoupper(trim((string) $value)),
            $rankingCodes
        ))));
        $this->replaceSimpleChildren(
            'agreement_rankings',
            'ranking_code',
            $agreementId,
            $values
        );
    }

    public function replaceContacts(int $agreementId, array $contacts): void {
        $delete = $this->db->prepare(
            'DELETE FROM agreement_contacts WHERE agreement_id = :agreement_id'
        );
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare('
            INSERT INTO agreement_contacts (
                agreement_id, party_type, contact_role, partner_id,
                full_name, job_title, email, phone, is_primary, display_order
            ) VALUES (
                :agreement_id, :party_type, :contact_role, :partner_id,
                :full_name, :job_title, :email, :phone, :is_primary, :display_order
            )
        ');

        foreach (array_values($contacts) as $index => $contact) {
            if (trim((string) ($contact['full_name'] ?? '')) === '') {
                continue;
            }
            $insert->execute([
                'agreement_id' => $agreementId,
                'party_type' => strtoupper((string) ($contact['party_type'] ?? 'PARTNER')),
                'contact_role' => strtoupper((string) ($contact['contact_role'] ?? 'COORDINATOR')),
                'partner_id' => !empty($contact['partner_id']) ? (int) $contact['partner_id'] : null,
                'full_name' => trim((string) $contact['full_name']),
                'job_title' => $this->nullableString($contact['job_title'] ?? null),
                'email' => $this->nullableString($contact['email'] ?? null),
                'phone' => $this->nullableString($contact['phone'] ?? null),
                'is_primary' => !empty($contact['is_primary']) ? 'true' : 'false',
                'display_order' => $index + 1,
            ]);
        }
    }

    public function replaceExecutivePrograms(int $agreementId, array $programs): void {
        $delete = $this->db->prepare(
            'DELETE FROM agreement_executive_programs WHERE agreement_id = :agreement_id'
        );
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare('
            INSERT INTO agreement_executive_programs (
                agreement_id, title, description, objectives, expected_outputs,
                start_date, end_date, responsible_entity, applicant_name, display_order
            ) VALUES (
                :agreement_id, :title, :description, :objectives, :expected_outputs,
                :start_date, :end_date, :responsible_entity, :applicant_name, :display_order
            )
        ');

        foreach (array_values($programs) as $index => $program) {
            if (trim((string) ($program['title'] ?? '')) === '') {
                continue;
            }
            $insert->execute([
                'agreement_id' => $agreementId,
                'title' => trim((string) $program['title']),
                'description' => $this->nullableString($program['description'] ?? null),
                'objectives' => $this->nullableString($program['objectives'] ?? null),
                'expected_outputs' => $this->nullableString($program['expected_outputs'] ?? null),
                'start_date' => $this->nullableString($program['start_date'] ?? null),
                'end_date' => $this->nullableString($program['end_date'] ?? null),
                'responsible_entity' => $this->nullableString($program['responsible_entity'] ?? null),
                'applicant_name' => $this->nullableString($program['applicant_name'] ?? null),
                'display_order' => $index + 1,
            ]);
        }
    }

    public function replaceMetrics(int $agreementId, array $metrics): void {
        $delete = $this->db->prepare(
            'DELETE FROM agreement_metrics WHERE agreement_id = :agreement_id'
        );
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare('
            INSERT INTO agreement_metrics (
                agreement_id, metric_code, planned_value, actual_value, notes
            ) VALUES (
                :agreement_id, :metric_code, :planned_value, :actual_value, :notes
            )
        ');

        foreach ($metrics as $metric) {
            $code = strtoupper(trim((string) ($metric['metric_code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $insert->execute([
                'agreement_id' => $agreementId,
                'metric_code' => $code,
                'planned_value' => $metric['planned_value'] === '' || $metric['planned_value'] === null
                    ? null : (int) $metric['planned_value'],
                'actual_value' => $metric['actual_value'] === '' || $metric['actual_value'] === null
                    ? null : (int) $metric['actual_value'],
                'notes' => $this->nullableString($metric['notes'] ?? null),
            ]);
        }
    }

    private function hydrateAgreement(array $agreement): array {
        $agreementId = (int) $agreement['agreement_id'];

        $partnerStatement = $this->db->prepare('
            SELECT
                p.partner_id, p.organization_name, p.partner_type, p.country,
                p.city, p.website, p.logo_url, p.latitude, p.longitude
            FROM agreement_partners ap
            JOIN partners p ON p.partner_id = ap.partner_id
            WHERE ap.agreement_id = :agreement_id
            ORDER BY p.organization_name, p.partner_id
        ');
        $partnerStatement->execute(['agreement_id' => $agreementId]);
        $partners = $partnerStatement->fetchAll();
        $agreement['partners'] = $partners;
        $agreement['partner_ids'] = array_map('intval', array_column($partners, 'partner_id'));
        $agreement['partner_names'] = array_column($partners, 'organization_name');
        $agreement['partner_id'] = $partners[0]['partner_id'] ?? null;
        $agreement['partner_name'] = $partners[0]['organization_name'] ?? null;

        $agreement['sdgs'] = $this->fetchColumnValues(
            'SELECT sdg_number FROM agreement_sdgs WHERE agreement_id = :agreement_id ORDER BY sdg_number',
            $agreementId,
            'sdg_number',
            true
        );
        $agreement['rankings'] = $this->fetchColumnValues(
            'SELECT ranking_code FROM agreement_rankings WHERE agreement_id = :agreement_id ORDER BY ranking_code',
            $agreementId,
            'ranking_code'
        );
        $agreement['contacts'] = $this->fetchChildren(
            'SELECT * FROM agreement_contacts WHERE agreement_id = :agreement_id ORDER BY display_order, agreement_contact_id',
            $agreementId
        );
        $agreement['executive_programs'] = $this->fetchChildren(
            'SELECT * FROM agreement_executive_programs WHERE agreement_id = :agreement_id ORDER BY display_order, executive_program_id',
            $agreementId
        );
        $agreement['metrics'] = $this->fetchChildren(
            'SELECT * FROM agreement_metrics WHERE agreement_id = :agreement_id ORDER BY agreement_metric_id',
            $agreementId
        );

        return $agreement;
    }

    private function replaceSimpleChildren(
        string $table,
        string $valueColumn,
        int $agreementId,
        array $values
    ): void {
        $delete = $this->db->prepare(
            "DELETE FROM {$table} WHERE agreement_id = :agreement_id"
        );
        $delete->execute(['agreement_id' => $agreementId]);

        $insert = $this->db->prepare(
            "INSERT INTO {$table} (agreement_id, {$valueColumn}) VALUES (:agreement_id, :value)"
        );
        foreach ($values as $value) {
            $insert->execute(['agreement_id' => $agreementId, 'value' => $value]);
        }
    }

    private function fetchColumnValues(
        string $sql,
        int $agreementId,
        string $column,
        bool $asInteger = false
    ): array {
        $rows = $this->fetchChildren($sql, $agreementId);
        $values = array_column($rows, $column);
        return $asInteger ? array_map('intval', $values) : $values;
    }

    private function fetchChildren(string $sql, int $agreementId): array {
        $statement = $this->db->prepare($sql);
        $statement->execute(['agreement_id' => $agreementId]);
        return $statement->fetchAll();
    }

    private function databaseValue(string $field, mixed $value): mixed {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return !empty($value) ? 'true' : 'false';
        }
        return $value === '' ? null : $value;
    }

    private function nullableString(mixed $value): ?string {
        $normalized = trim((string) ($value ?? ''));
        return $normalized === '' ? null : $normalized;
    }
}
