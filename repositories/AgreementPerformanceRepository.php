<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class AgreementPerformanceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findAgreement(int $agreementId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT a.*, sr.effective_date AS finalized_effective_date,
                    sr.expiry_date AS finalized_expiry_date
             FROM agreements a
             LEFT JOIN agreement_signing_records sr
               ON sr.agreement_id = a.agreement_id
             WHERE a.agreement_id = :agreement_id
             LIMIT 1'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function findReportingAgreements(): array
    {
        return $this->db->query(
            'SELECT a.agreement_id, a.title, a.status, a.created_by,
                    COALESCE(sr.effective_date, a.effective_date, a.start_date)
                        AS reporting_start,
                    COALESCE(sr.expiry_date, a.end_date) AS reporting_end
             FROM agreements a
             LEFT JOIN agreement_signing_records sr
               ON sr.agreement_id = a.agreement_id
             WHERE a.annual_report_required = TRUE
               AND a.status IN (\'ACTIVE\', \'EXPIRED\')
               AND COALESCE(sr.effective_date, a.effective_date, a.start_date)
                   IS NOT NULL
               AND COALESCE(sr.expiry_date, a.end_date) IS NOT NULL
             ORDER BY a.agreement_id'
        )->fetchAll();
    }

    public function periodExists(
        int $agreementId,
        string $periodStart,
        string $periodEnd
    ): bool {
        $statement = $this->db->prepare(
            'SELECT 1 FROM agreement_performance_reports
             WHERE agreement_id = :agreement_id
               AND period_start = :period_start
               AND period_end = :period_end
             LIMIT 1'
        );
        $statement->execute([
            'agreement_id' => $agreementId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
        return (bool) $statement->fetchColumn();
    }

    public function createPeriod(
        int $agreementId,
        int $createdBy,
        string $periodStart,
        string $periodEnd,
        string $dueDate
    ): int {
        $statement = $this->db->prepare(
            'INSERT INTO agreement_performance_reports (
                agreement_id, period_start, period_end, due_date,
                status, created_by, created_at, updated_at
             ) VALUES (
                :agreement_id, :period_start, :period_end, :due_date,
                \'DRAFT\', :created_by, NOW(), NOW()
             )
             ON CONFLICT (agreement_id, period_start, period_end) DO NOTHING
             RETURNING performance_report_id'
        );
        $statement->execute([
            'agreement_id' => $agreementId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'created_by' => $createdBy,
        ]);
        $reportId = $statement->fetchColumn();
        if ($reportId === false) {
            throw new DomainException('The reporting period already exists');
        }

        $metricStatement = $this->db->prepare(
            'INSERT INTO agreement_performance_metric_results (
                performance_report_id, agreement_metric_id, metric_code,
                metric_label, planned_value, actual_value, unit,
                notes, display_order
             )
             SELECT :performance_report_id, agreement_metric_id, metric_code,
                    CASE metric_code
                        WHEN \'STUDENTS_EXCHANGED\' THEN \'Students exchanged\'
                        WHEN \'FACULTY_EXCHANGED\' THEN \'Faculty exchanged\'
                        WHEN \'JOINT_PROGRAMS\' THEN \'Joint programs\'
                        ELSE INITCAP(REPLACE(metric_code, \'_\', \' \'))
                    END,
                    planned_value, NULL, \'COUNT\', notes,
                    ROW_NUMBER() OVER (ORDER BY agreement_metric_id)
             FROM agreement_metrics
             WHERE agreement_id = :agreement_id'
        );
        $metricStatement->execute([
            'performance_report_id' => (int) $reportId,
            'agreement_id' => $agreementId,
        ]);

        $programStatement = $this->db->prepare(
            'INSERT INTO agreement_executive_program_updates (
                performance_report_id, executive_program_id, program_title,
                progress_status, completion_percent, display_order
             )
             SELECT :performance_report_id, executive_program_id, title,
                    \'NOT_STARTED\', 0, display_order
             FROM agreement_executive_programs
             WHERE agreement_id = :agreement_id
             ORDER BY display_order, executive_program_id'
        );
        $programStatement->execute([
            'performance_report_id' => (int) $reportId,
            'agreement_id' => $agreementId,
        ]);

        return (int) $reportId;
    }

    public function listForAgreement(
        int $agreementId,
        bool $includeDrafts
    ): array
    {
        $statement = $this->db->prepare(
            $this->reportSelect()
            . ' WHERE pr.agreement_id = :agreement_id
                ' . ($includeDrafts ? '' : 'AND pr.status <> \'DRAFT\'') . '
                ORDER BY pr.period_start DESC, pr.performance_report_id DESC'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        return $statement->fetchAll();
    }

    public function listQueue(
        int $userId,
        bool $canReview,
        bool $isAdministrator
    ): array {
        $where = $isAdministrator
            ? 'TRUE'
            : ($canReview
                ? '(pr.created_by = :user_id OR pr.status <> \'DRAFT\')'
                : 'pr.created_by = :user_id');
        $statement = $this->db->prepare(
            $this->reportSelect()
            . " WHERE {$where}
                ORDER BY
                    CASE WHEN pr.status = 'SUBMITTED' THEN 0 ELSE 1 END,
                    pr.due_date, pr.performance_report_id"
        );
        if (!$isAdministrator) {
            $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    public function findReport(int $reportId): ?array
    {
        $statement = $this->db->prepare(
            $this->reportSelect()
            . ' WHERE pr.performance_report_id = :performance_report_id
                LIMIT 1'
        );
        $statement->execute(['performance_report_id' => $reportId]);
        $report = $statement->fetch();
        if (!$report) {
            return null;
        }
        $report['metrics'] = $this->children(
            'SELECT * FROM agreement_performance_metric_results
             WHERE performance_report_id = :performance_report_id
             ORDER BY display_order, performance_metric_result_id',
            $reportId
        );
        $report['program_updates'] = $this->children(
            'SELECT * FROM agreement_executive_program_updates
             WHERE performance_report_id = :performance_report_id
             ORDER BY display_order, program_update_id',
            $reportId
        );
        $report['events'] = $this->children(
            'SELECT e.*,
                    NULLIF(TRIM(CONCAT(u.first_name, \' \', u.last_name)), \'\')
                        AS performed_by_name,
                    u.email AS performed_by_email
             FROM agreement_performance_report_events e
             LEFT JOIN users u ON u.user_id = e.performed_by
             WHERE e.performance_report_id = :performance_report_id
             ORDER BY e.created_at, e.performance_report_event_id',
            $reportId
        );
        return $report;
    }

    public function lockReport(int $reportId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM agreement_performance_reports
             WHERE performance_report_id = :performance_report_id
             FOR UPDATE'
        );
        $statement->execute(['performance_report_id' => $reportId]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function updateContent(int $reportId, array $data): void
    {
        $statement = $this->db->prepare(
            'UPDATE agreement_performance_reports
             SET executive_summary = :executive_summary,
                 achievements = :achievements,
                 challenges = :challenges,
                 corrective_actions = :corrective_actions,
                 next_period_plan = :next_period_plan,
                 report_document_id = :report_document_id,
                 updated_at = NOW()
             WHERE performance_report_id = :performance_report_id'
        );
        $statement->execute([
            'performance_report_id' => $reportId,
            'executive_summary' => $data['executive_summary'],
            'achievements' => $data['achievements'],
            'challenges' => $data['challenges'],
            'corrective_actions' => $data['corrective_actions'],
            'next_period_plan' => $data['next_period_plan'],
            'report_document_id' => $data['report_document_id'],
        ]);
    }

    public function replaceMetrics(int $reportId, array $metrics): void
    {
        $delete = $this->db->prepare(
            'DELETE FROM agreement_performance_metric_results
             WHERE performance_report_id = :performance_report_id'
        );
        $delete->execute(['performance_report_id' => $reportId]);
        $insert = $this->db->prepare(
            'INSERT INTO agreement_performance_metric_results (
                performance_report_id, agreement_metric_id, metric_code,
                metric_label, planned_value, actual_value, unit, notes,
                display_order
             ) VALUES (
                :performance_report_id, :agreement_metric_id, :metric_code,
                :metric_label, :planned_value, :actual_value, :unit, :notes,
                :display_order
             )'
        );
        foreach (array_values($metrics) as $index => $metric) {
            $insert->execute([
                'performance_report_id' => $reportId,
                'agreement_metric_id' => $metric['agreement_metric_id'],
                'metric_code' => $metric['metric_code'],
                'metric_label' => $metric['metric_label'],
                'planned_value' => $metric['planned_value'],
                'actual_value' => $metric['actual_value'],
                'unit' => $metric['unit'],
                'notes' => $metric['notes'],
                'display_order' => $index + 1,
            ]);
        }
    }

    public function replaceProgramUpdates(int $reportId, array $updates): void
    {
        $delete = $this->db->prepare(
            'DELETE FROM agreement_executive_program_updates
             WHERE performance_report_id = :performance_report_id'
        );
        $delete->execute(['performance_report_id' => $reportId]);
        $insert = $this->db->prepare(
            'INSERT INTO agreement_executive_program_updates (
                performance_report_id, executive_program_id, program_title,
                progress_status, completion_percent, achievements,
                outputs_delivered, challenges, next_steps, display_order
             ) VALUES (
                :performance_report_id, :executive_program_id, :program_title,
                :progress_status, :completion_percent, :achievements,
                :outputs_delivered, :challenges, :next_steps, :display_order
             )'
        );
        foreach (array_values($updates) as $index => $update) {
            $insert->execute([
                'performance_report_id' => $reportId,
                'executive_program_id' => $update['executive_program_id'],
                'program_title' => $update['program_title'],
                'progress_status' => $update['progress_status'],
                'completion_percent' => $update['completion_percent'],
                'achievements' => $update['achievements'],
                'outputs_delivered' => $update['outputs_delivered'],
                'challenges' => $update['challenges'],
                'next_steps' => $update['next_steps'],
                'display_order' => $index + 1,
            ]);
        }
    }

    public function transition(
        int $reportId,
        string $fromStatus,
        string $toStatus,
        int $userId,
        ?string $comments
    ): bool {
        $sets = ['status = :to_status', 'updated_at = NOW()'];
        if ($toStatus === 'SUBMITTED') {
            $sets[] = 'submitted_by = :actor_id';
            $sets[] = 'submitted_at = NOW()';
            $sets[] = 'reviewed_by = NULL';
            $sets[] = 'reviewed_at = NULL';
            $sets[] = 'reviewer_comments = NULL';
        } elseif (in_array($toStatus, ['ACCEPTED', 'RETURNED'], true)) {
            $sets[] = 'reviewed_by = :actor_id';
            $sets[] = 'reviewed_at = NOW()';
            $sets[] = 'reviewer_comments = :comments';
        }
        $statement = $this->db->prepare(
            'UPDATE agreement_performance_reports
             SET ' . implode(', ', $sets) . '
             WHERE performance_report_id = :performance_report_id
               AND status = :from_status'
        );
        $parameters = [
            'performance_report_id' => $reportId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $userId,
        ];
        if (in_array($toStatus, ['ACCEPTED', 'RETURNED'], true)) {
            $parameters['comments'] = $comments;
        }
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $event = $this->db->prepare(
            'INSERT INTO agreement_performance_report_events (
                performance_report_id, from_status, to_status,
                comments, performed_by, created_at
             ) VALUES (
                :performance_report_id, :from_status, :to_status,
                :comments, :performed_by, NOW()
             )'
        );
        $event->execute([
            'performance_report_id' => $reportId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'comments' => $comments,
            'performed_by' => $userId,
        ]);
        return true;
    }

    public function findEligibleReportDocuments(int $agreementId): array
    {
        $statement = $this->db->prepare(
            'SELECT document_id, file_name, document_type, mime_type,
                    file_size_bytes, uploaded_at
             FROM agreement_documents
             WHERE agreement_id = :agreement_id
               AND document_type = \'ANNUAL_REPORT\'
               AND storage_key IS NOT NULL
             ORDER BY uploaded_at DESC, document_id DESC'
        );
        $statement->execute(['agreement_id' => $agreementId]);
        return $statement->fetchAll();
    }

    public function documentBelongsToAgreement(
        int $documentId,
        int $agreementId
    ): bool {
        $statement = $this->db->prepare(
            'SELECT 1 FROM agreement_documents
             WHERE document_id = :document_id
               AND agreement_id = :agreement_id
               AND document_type = \'ANNUAL_REPORT\'
               AND storage_key IS NOT NULL
             LIMIT 1'
        );
        $statement->execute([
            'document_id' => $documentId,
            'agreement_id' => $agreementId,
        ]);
        return (bool) $statement->fetchColumn();
    }

    public function findReportDocument(
        int $documentId,
        int $agreementId
    ): ?array {
        $statement = $this->db->prepare(
            'SELECT * FROM agreement_documents
             WHERE document_id = :document_id
               AND agreement_id = :agreement_id
               AND document_type = \'ANNUAL_REPORT\'
               AND storage_key IS NOT NULL
             LIMIT 1'
        );
        $statement->execute([
            'document_id' => $documentId,
            'agreement_id' => $agreementId,
        ]);
        $row = $statement->fetch();
        return $row ?: null;
    }

    public function dashboard(int $year, ?int $ownerUserId = null): array
    {
        $ownerFilter = $ownerUserId === null
            ? ''
            : ' AND a.created_by = :owner_user_id';
        $yearParameters = ['report_year' => $year];
        $ownerParameters = $ownerUserId === null
            ? []
            : ['owner_user_id' => $ownerUserId];

        $summary = $this->db->prepare(
            'SELECT
                COUNT(*) FILTER (WHERE a.status = \'ACTIVE\') AS active_agreements,
                COUNT(*) FILTER (WHERE a.status = \'APPROVED\') AS scheduled_agreements,
                COUNT(*) FILTER (WHERE a.status = \'EXPIRED\') AS expired_agreements,
                COUNT(*) FILTER (
                    WHERE a.annual_report_required = TRUE
                      AND a.status IN (\'ACTIVE\', \'EXPIRED\')
                ) AS reportable_agreements
             FROM agreements a
             WHERE TRUE' . $ownerFilter
        );
        $summary->execute($ownerParameters);
        $agreementSummary = $summary->fetch() ?: [];

        $reportStatement = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_reports,
                COUNT(*) FILTER (WHERE pr.status = \'ACCEPTED\') AS accepted,
                COUNT(*) FILTER (WHERE pr.status = \'SUBMITTED\') AS submitted,
                COUNT(*) FILTER (WHERE pr.status = \'RETURNED\') AS returned,
                COUNT(*) FILTER (WHERE pr.status = \'DRAFT\') AS draft,
                COUNT(*) FILTER (
                    WHERE pr.status IN (\'DRAFT\', \'RETURNED\')
                      AND pr.due_date < CURRENT_DATE
                ) AS overdue
             FROM agreement_performance_reports pr
             JOIN agreements a ON a.agreement_id = pr.agreement_id
             WHERE EXTRACT(YEAR FROM pr.period_end) = :report_year'
                . $ownerFilter
        );
        $reportStatement->execute(array_merge($yearParameters, $ownerParameters));

        $deadlines = $this->db->prepare(
            'SELECT pr.performance_report_id, pr.agreement_id,
                    a.title AS agreement_title, a.agreement_code,
                    pr.period_start, pr.period_end, pr.due_date, pr.status,
                    CASE
                        WHEN pr.status IN (\'DRAFT\', \'RETURNED\')
                             AND pr.due_date < CURRENT_DATE THEN TRUE
                        ELSE FALSE
                    END AS is_overdue
             FROM agreement_performance_reports pr
             JOIN agreements a ON a.agreement_id = pr.agreement_id
             WHERE pr.status IN (\'DRAFT\', \'RETURNED\', \'SUBMITTED\')
                  AND pr.due_date <= CURRENT_DATE + INTERVAL \'30 days\'
                  AND EXTRACT(YEAR FROM pr.period_end) = :report_year
                ' . $ownerFilter . '
                ORDER BY pr.due_date, pr.performance_report_id
                LIMIT 25'
        );
        $deadlines->execute(array_merge($yearParameters, $ownerParameters));

        $metrics = $this->db->prepare(
            'SELECT mr.metric_code, mr.metric_label, mr.unit,
                    SUM(mr.planned_value) AS planned_value,
                    SUM(mr.actual_value) AS actual_value
             FROM agreement_performance_metric_results mr
             JOIN agreement_performance_reports pr
               ON pr.performance_report_id = mr.performance_report_id
             JOIN agreements a ON a.agreement_id = pr.agreement_id
             WHERE pr.status = \'ACCEPTED\'
               AND EXTRACT(YEAR FROM pr.period_end) = :report_year
               ' . $ownerFilter . '
             GROUP BY mr.metric_code, mr.metric_label, mr.unit
             ORDER BY mr.metric_label'
        );
        $metrics->execute(array_merge($yearParameters, $ownerParameters));

        $programs = $this->db->prepare(
            'SELECT pu.progress_status, COUNT(*) AS program_count
             FROM agreement_executive_program_updates pu
             JOIN agreement_performance_reports pr
               ON pr.performance_report_id = pu.performance_report_id
             JOIN agreements a ON a.agreement_id = pr.agreement_id
             WHERE pr.status = \'ACCEPTED\'
               AND EXTRACT(YEAR FROM pr.period_end) = :report_year
               ' . $ownerFilter . '
             GROUP BY pu.progress_status
             ORDER BY pu.progress_status'
        );
        $programs->execute(array_merge($yearParameters, $ownerParameters));

        return [
            'year' => $year,
            'agreements' => $agreementSummary,
            'reports' => $reportStatement->fetch() ?: [],
            'deadlines' => $deadlines->fetchAll(),
            'metrics' => $metrics->fetchAll(),
            'programs' => $programs->fetchAll(),
        ];
    }

    private function reportSelect(): string
    {
        return
            'SELECT pr.*, a.title AS agreement_title,
                    a.agreement_code, a.status AS agreement_status,
                    ad.file_name AS report_document_name,
                    NULLIF(TRIM(CONCAT(cu.first_name, \' \', cu.last_name)), \'\')
                        AS creator_name,
                    cu.email AS creator_email,
                    NULLIF(TRIM(CONCAT(ru.first_name, \' \', ru.last_name)), \'\')
                        AS reviewer_name,
                    ru.email AS reviewer_email,
                    CASE
                        WHEN pr.status IN (\'DRAFT\', \'RETURNED\')
                             AND pr.due_date < CURRENT_DATE THEN TRUE
                        ELSE FALSE
                    END AS is_overdue
             FROM agreement_performance_reports pr
             JOIN agreements a ON a.agreement_id = pr.agreement_id
             LEFT JOIN agreement_documents ad
               ON ad.document_id = pr.report_document_id
             LEFT JOIN users cu ON cu.user_id = pr.created_by
             LEFT JOIN users ru ON ru.user_id = pr.reviewed_by';
    }

    private function children(string $sql, int $reportId): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute(['performance_report_id' => $reportId]);
        return $statement->fetchAll();
    }
}
