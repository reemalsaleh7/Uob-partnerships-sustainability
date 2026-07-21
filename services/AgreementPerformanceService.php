<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementPerformanceRepository.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/DocumentStorageService.php';
require_once __DIR__ . '/../helpers/AuditAction.php';

class AgreementPerformanceService
{
    private AgreementPerformanceRepository $reports;
    private PermissionService $permissions;
    private AuditService $audit;
    private DocumentStorageService $storage;

    public function __construct()
    {
        $this->reports = new AgreementPerformanceRepository();
        $this->permissions = new PermissionService();
        $this->audit = new AuditService();
        $this->storage = new DocumentStorageService();
    }

    public function agreementReports(int $agreementId, int $userId): array
    {
        $agreement = $this->requireAgreementAccess($agreementId, $userId);
        $canManage = $this->canManageAgreement($agreement, $userId);
        return [
            'agreement_id' => $agreementId,
            'agreement_title' => $agreement['title'],
            'annual_report_required' => (bool) $agreement['annual_report_required'],
            'can_manage' => $canManage,
            'can_review' => $this->canReview($userId),
            'reports' => $this->reports->listForAgreement(
                $agreementId,
                $canManage || $this->isAdministrator($userId)
            ),
        ];
    }

    public function queue(int $userId): array
    {
        $canReview = $this->canReview($userId);
        return [
            'can_review' => $canReview,
            'can_view_dashboard' => $this->permissions->hasPermission(
                $userId,
                'VIEW_AGREEMENT_DASHBOARD'
            ),
            'reports' => $this->reports->listQueue(
                $userId,
                $canReview,
                $this->isAdministrator($userId)
            ),
        ];
    }

    public function report(int $reportId, int $userId): array
    {
        $report = $this->requireReportAccess($reportId, $userId);
        $agreement = $this->reports->findAgreement((int) $report['agreement_id']);
        if ($agreement === null) {
            throw new DomainException('Agreement not found');
        }
        $canManage = $this->canManageAgreement($agreement, $userId)
            && in_array($report['status'], ['DRAFT', 'RETURNED'], true);
        $report['can_manage'] = $canManage;
        $report['can_submit'] = $canManage;
        $report['can_review'] = $report['status'] === 'SUBMITTED'
            && $this->canReview($userId);
        $report['eligible_documents'] = $canManage
            ? $this->reports->findEligibleReportDocuments(
                (int) $report['agreement_id']
            )
            : [];
        return $report;
    }

    public function update(int $reportId, int $userId, array $input): array
    {
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $locked = $this->reports->lockReport($reportId);
            if ($locked === null) {
                throw new DomainException('Performance report not found');
            }
            $agreement = $this->reports->findAgreement(
                (int) $locked['agreement_id']
            );
            if (
                $agreement === null
                || !$this->canManageAgreement($agreement, $userId)
            ) {
                throw new DomainException(
                    'Only the Agreement owner or a system administrator may edit this report'
                );
            }
            if (!in_array($locked['status'], ['DRAFT', 'RETURNED'], true)) {
                throw new DomainException(
                    'Only draft or returned reports may be edited'
                );
            }
            $current = $this->reports->findReport($reportId);
            $data = $this->validateInput($input, $current ?? []);
            if (
                $data['report_document_id'] !== null
                && !$this->reports->documentBelongsToAgreement(
                    $data['report_document_id'],
                    (int) $locked['agreement_id']
                )
            ) {
                throw new InvalidArgumentException(
                    'Choose a secure annual-report document from this Agreement'
                );
            }
            $this->reports->updateContent($reportId, $data);
            $this->reports->replaceMetrics($reportId, $data['metrics']);
            $this->reports->replaceProgramUpdates(
                $reportId,
                $data['program_updates']
            );
            $after = $this->reports->findReport($reportId);
            $this->audit->write(
                'agreement_performance_reports',
                $reportId,
                AuditAction::UPDATE,
                $userId,
                $current,
                $after
            );
            if ($ownsTransaction) {
                $db->commit();
            }
            return $after ?? [];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function submit(int $reportId, int $userId): array
    {
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $locked = $this->reports->lockReport($reportId);
            if ($locked === null) {
                throw new DomainException('Performance report not found');
            }
            $agreement = $this->reports->findAgreement(
                (int) $locked['agreement_id']
            );
            if (
                $agreement === null
                || !$this->canManageAgreement($agreement, $userId)
            ) {
                throw new DomainException('You cannot submit this report');
            }
            if (!in_array($locked['status'], ['DRAFT', 'RETURNED'], true)) {
                throw new DomainException(
                    'Only draft or returned reports may be submitted'
                );
            }
            $report = $this->reports->findReport($reportId);
            $this->validateSubmission($report ?? []);
            $this->verifyReportDocument($report ?? []);
            if (!$this->reports->transition(
                $reportId,
                (string) $locked['status'],
                'SUBMITTED',
                $userId,
                null
            )) {
                throw new RuntimeException('The report status changed; reload and retry');
            }
            $after = $this->reports->findReport($reportId);
            $this->audit->write(
                'agreement_performance_reports',
                $reportId,
                AuditAction::UPDATE,
                $userId,
                $report,
                $after
            );
            if ($ownsTransaction) {
                $db->commit();
            }
            return $after ?? [];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function review(
        int $reportId,
        int $userId,
        string $decision,
        ?string $comments
    ): array {
        if (!$this->canReview($userId)) {
            throw new DomainException(
                'You do not have permission to review performance reports'
            );
        }
        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['ACCEPT', 'RETURN'], true)) {
            throw new InvalidArgumentException('Decision must be ACCEPT or RETURN');
        }
        $comments = $this->nullable($comments);
        if ($decision === 'RETURN' && $comments === null) {
            throw new InvalidArgumentException(
                'Reviewer comments are required when returning a report'
            );
        }
        $toStatus = $decision === 'ACCEPT' ? 'ACCEPTED' : 'RETURNED';
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $locked = $this->reports->lockReport($reportId);
            if ($locked === null) {
                throw new DomainException('Performance report not found');
            }
            if ($locked['status'] !== 'SUBMITTED') {
                throw new DomainException('Only submitted reports may be reviewed');
            }
            if (
                (int) ($locked['created_by'] ?? 0) === $userId
                || (int) ($locked['submitted_by'] ?? 0) === $userId
            ) {
                throw new DomainException(
                    'A report preparer cannot review their own submission'
                );
            }
            $before = $this->reports->findReport($reportId);
            if (!$this->reports->transition(
                $reportId,
                'SUBMITTED',
                $toStatus,
                $userId,
                $comments
            )) {
                throw new RuntimeException('The report status changed; reload and retry');
            }
            $after = $this->reports->findReport($reportId);
            $this->audit->write(
                'agreement_performance_reports',
                $reportId,
                AuditAction::UPDATE,
                $userId,
                $before,
                $after
            );
            if ($ownsTransaction) {
                $db->commit();
            }
            return $after ?? [];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function generatePeriods(DateTimeImmutable $asOf): array
    {
        $created = [];
        $skipped = [];
        $yearFloor = $asOf->setDate((int) $asOf->format('Y'), 1, 1);
        foreach ($this->reports->findReportingAgreements() as $agreement) {
            $start = $this->parseDate($agreement['reporting_start'] ?? null);
            $end = $this->parseDate($agreement['reporting_end'] ?? null);
            if ($start === null || $end === null || $start > $end) {
                $skipped[] = [
                    'agreement_id' => (int) $agreement['agreement_id'],
                    'reason' => 'Reporting dates are incomplete or invalid',
                ];
                continue;
            }
            $periodStart = $start;
            while ($periodStart <= $end) {
                $periodEnd = $periodStart->modify('+1 year -1 day');
                if ($periodEnd > $end) {
                    $periodEnd = $end;
                }
                if ($periodEnd >= $asOf || $periodEnd == $end) {
                    break;
                }
                $periodStart = $periodEnd->modify('+1 day');
            }
            if ($periodStart > $end || $periodStart > $asOf) {
                $skipped[] = [
                    'agreement_id' => (int) $agreement['agreement_id'],
                    'reason' => 'No reporting period is due to open yet',
                ];
                continue;
            }
            if ($periodEnd < $yearFloor) {
                $skipped[] = [
                    'agreement_id' => (int) $agreement['agreement_id'],
                    'reason' => 'Final reporting period ended before the current reporting year',
                ];
                continue;
            }
            $startValue = $periodStart->format('Y-m-d');
            $endValue = $periodEnd->format('Y-m-d');
            if ($this->reports->periodExists(
                (int) $agreement['agreement_id'],
                $startValue,
                $endValue
            )) {
                $skipped[] = [
                    'agreement_id' => (int) $agreement['agreement_id'],
                    'reason' => 'Reporting period already exists',
                ];
                continue;
            }
            $reportId = $this->reports->createPeriod(
                (int) $agreement['agreement_id'],
                (int) $agreement['created_by'],
                $startValue,
                $endValue,
                $periodEnd->modify('+30 days')->format('Y-m-d')
            );
            $report = $this->reports->findReport($reportId);
            $this->audit->write(
                'agreement_performance_reports',
                $reportId,
                AuditAction::INSERT,
                null,
                null,
                $report
            );
            $created[] = $report;
        }
        return ['created' => $created, 'skipped' => $skipped];
    }

    public function dashboard(int $year, int $userId): array
    {
        if (!$this->permissions->hasPermission(
            $userId,
            'VIEW_AGREEMENT_DASHBOARD'
        )) {
            throw new DomainException(
                'You do not have permission to view the performance dashboard'
            );
        }
        if ($year < 2000 || $year > 2200) {
            throw new InvalidArgumentException('Choose a valid reporting year');
        }
        return $this->reports->dashboard($year);
    }

    private function validateInput(array $input, array $current): array
    {
        $allowedMetricIds = [];
        foreach ($current['metrics'] ?? [] as $metric) {
            if (!empty($metric['agreement_metric_id'])) {
                $allowedMetricIds[] = (int) $metric['agreement_metric_id'];
            }
        }
        $metrics = $input['metrics'] ?? [];
        if (!is_array($metrics) || count($metrics) > 50) {
            throw new InvalidArgumentException('Provide no more than 50 metrics');
        }
        $normalizedMetrics = [];
        $codes = [];
        $submittedMetricIds = [];
        foreach (array_values($metrics) as $metric) {
            if (!is_array($metric)) {
                throw new InvalidArgumentException('Each metric must be a record');
            }
            $code = strtoupper(trim((string) ($metric['metric_code'] ?? '')));
            $label = trim((string) ($metric['metric_label'] ?? ''));
            if (!preg_match('/^[A-Z0-9_]{2,50}$/', $code) || $label === '') {
                throw new InvalidArgumentException('Every metric needs a valid code and label');
            }
            if (isset($codes[$code])) {
                throw new InvalidArgumentException('Metric codes must be unique');
            }
            $codes[$code] = true;
            $metricId = $this->positiveIntOrNull($metric['agreement_metric_id'] ?? null);
            if ($metricId !== null && !in_array($metricId, $allowedMetricIds, true)) {
                throw new InvalidArgumentException('A metric does not belong to this report');
            }
            if ($metricId !== null) {
                $submittedMetricIds[] = $metricId;
            }
            $normalizedMetrics[] = [
                'agreement_metric_id' => $metricId,
                'metric_code' => $code,
                'metric_label' => substr($label, 0, 150),
                'planned_value' => $this->numberOrNull($metric['planned_value'] ?? null),
                'actual_value' => $this->numberOrNull($metric['actual_value'] ?? null),
                'unit' => substr(
                    trim((string) ($metric['unit'] ?? 'COUNT')) ?: 'COUNT',
                    0,
                    50
                ),
                'notes' => $this->nullable($metric['notes'] ?? null),
            ];
        }
        if (array_diff($allowedMetricIds, $submittedMetricIds) !== []) {
            throw new InvalidArgumentException(
                'Baseline Agreement metrics cannot be removed from a report'
            );
        }

        $allowedProgramIds = [];
        foreach ($current['program_updates'] ?? [] as $program) {
            if (!empty($program['executive_program_id'])) {
                $allowedProgramIds[] = (int) $program['executive_program_id'];
            }
        }
        $updates = $input['program_updates'] ?? [];
        if (!is_array($updates) || count($updates) > 100) {
            throw new InvalidArgumentException('Provide no more than 100 program updates');
        }
        $normalizedUpdates = [];
        $programIds = [];
        $submittedProgramIds = [];
        $statuses = [
            'NOT_STARTED', 'ON_TRACK', 'AT_RISK',
            'DELAYED', 'COMPLETED', 'CANCELLED',
        ];
        foreach (array_values($updates) as $update) {
            if (!is_array($update)) {
                throw new InvalidArgumentException('Each program update must be a record');
            }
            $programId = $this->positiveIntOrNull(
                $update['executive_program_id'] ?? null
            );
            if (
                $programId !== null
                && !in_array($programId, $allowedProgramIds, true)
            ) {
                throw new InvalidArgumentException('A program does not belong to this report');
            }
            if ($programId !== null && isset($programIds[$programId])) {
                throw new InvalidArgumentException('Program updates must be unique');
            }
            if ($programId !== null) {
                $programIds[$programId] = true;
                $submittedProgramIds[] = $programId;
            }
            $title = trim((string) ($update['program_title'] ?? ''));
            $status = strtoupper(trim((string) ($update['progress_status'] ?? '')));
            $completion = $this->numberOrNull($update['completion_percent'] ?? 0) ?? 0;
            if ($title === '' || !in_array($status, $statuses, true)) {
                throw new InvalidArgumentException('Every program needs a title and valid status');
            }
            if ($completion < 0 || $completion > 100) {
                throw new InvalidArgumentException('Program completion must be between 0 and 100');
            }
            $normalizedUpdates[] = [
                'executive_program_id' => $programId,
                'program_title' => substr($title, 0, 255),
                'progress_status' => $status,
                'completion_percent' => $completion,
                'achievements' => $this->nullable($update['achievements'] ?? null),
                'outputs_delivered' => $this->nullable($update['outputs_delivered'] ?? null),
                'challenges' => $this->nullable($update['challenges'] ?? null),
                'next_steps' => $this->nullable($update['next_steps'] ?? null),
            ];
        }
        if (array_diff($allowedProgramIds, $submittedProgramIds) !== []) {
            throw new InvalidArgumentException(
                'Agreement executive programs cannot be removed from a report'
            );
        }

        return [
            'executive_summary' => $this->nullable($input['executive_summary'] ?? null),
            'achievements' => $this->nullable($input['achievements'] ?? null),
            'challenges' => $this->nullable($input['challenges'] ?? null),
            'corrective_actions' => $this->nullable($input['corrective_actions'] ?? null),
            'next_period_plan' => $this->nullable($input['next_period_plan'] ?? null),
            'report_document_id' => $this->positiveIntOrNull(
                $input['report_document_id'] ?? null
            ),
            'metrics' => $normalizedMetrics,
            'program_updates' => $normalizedUpdates,
        ];
    }

    private function validateSubmission(array $report): void
    {
        $errors = [];
        if ($this->nullable($report['executive_summary'] ?? null) === null) {
            $errors[] = 'executive summary';
        }
        if ($this->nullable($report['achievements'] ?? null) === null) {
            $errors[] = 'achievements';
        }
        if (empty($report['report_document_id'])) {
            $errors[] = 'secure annual-report document';
        }
        foreach ($report['metrics'] ?? [] as $metric) {
            if ($metric['actual_value'] === null || $metric['actual_value'] === '') {
                $errors[] = 'actual result for ' . ($metric['metric_label'] ?? 'a metric');
            }
        }
        if ($errors !== []) {
            throw new InvalidArgumentException(
                'Complete before submission: ' . implode(', ', $errors)
            );
        }
    }

    private function verifyReportDocument(array $report): void
    {
        $documentId = (int) ($report['report_document_id'] ?? 0);
        $document = $this->reports->findReportDocument(
            $documentId,
            (int) ($report['agreement_id'] ?? 0)
        );
        if ($document === null) {
            throw new InvalidArgumentException(
                'The selected annual-report document is unavailable'
            );
        }
        $absolutePath = $this->storage->absolutePath(
            (string) ($document['storage_key'] ?? '')
        );
        if ($absolutePath === null) {
            throw new InvalidArgumentException(
                'The selected annual-report file is unavailable'
            );
        }
        $expected = (string) ($document['sha256_checksum'] ?? '');
        $actual = hash_file('sha256', $absolutePath);
        if (
            $expected === ''
            || $actual === false
            || !hash_equals($expected, $actual)
        ) {
            throw new RuntimeException(
                'Annual-report document integrity verification failed'
            );
        }
    }

    private function requireReportAccess(int $reportId, int $userId): array
    {
        $report = $this->reports->findReport($reportId);
        if ($report === null) {
            throw new DomainException('Performance report not found');
        }
        $agreement = $this->reports->findAgreement((int) $report['agreement_id']);
        $isOwner = $agreement !== null
            && $this->canManageAgreement($agreement, $userId);
        $reviewAccess = $this->canReview($userId)
            && $report['status'] !== 'DRAFT';
        if (!$isOwner && !$reviewAccess && !$this->isAdministrator($userId)) {
            throw new DomainException('Performance report not found');
        }
        return $report;
    }

    private function requireAgreementAccess(int $agreementId, int $userId): array
    {
        $agreement = $this->reports->findAgreement($agreementId);
        if ($agreement === null) {
            throw new DomainException('Agreement not found');
        }
        if (
            !$this->canManageAgreement($agreement, $userId)
            && !$this->canReview($userId)
            && !$this->isAdministrator($userId)
        ) {
            throw new DomainException('Agreement reports not found');
        }
        return $agreement;
    }

    private function canManageAgreement(array $agreement, int $userId): bool
    {
        if ($this->isAdministrator($userId)) {
            return true;
        }
        return (int) ($agreement['created_by'] ?? 0) === $userId
            && $this->permissions->hasPermission(
                $userId,
                'MANAGE_AGREEMENT_REPORTS'
            );
    }

    private function canReview(int $userId): bool
    {
        return $this->permissions->hasPermission(
            $userId,
            'REVIEW_AGREEMENT_REPORTS'
        );
    }

    private function isAdministrator(int $userId): bool
    {
        return in_array(
            'System Administrator',
            $this->permissions->getRoleNames($userId),
            true
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number <= 0) {
            throw new InvalidArgumentException('A selected identifier is invalid');
        }
        return (int) $number;
    }

    private function numberOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Metric values must be non-negative numbers');
        }
        return round((float) $value, 2);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
