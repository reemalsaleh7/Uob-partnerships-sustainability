<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementPerformanceService.php';
require_once __DIR__ . '/../services/AgreementService.php';

function performanceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function performanceUserId(PDO $db, string $email): int
{
    $statement = $db->prepare('SELECT user_id FROM users WHERE email = :email');
    $statement->execute(['email' => $email]);
    $id = $statement->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Development user {$email} was not found");
    }
    return (int) $id;
}

$db = Database::connect();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$performance = new AgreementPerformanceService();
$agreementService = new AgreementService();
$createdPath = null;

$db->beginTransaction();
try {
    $creatorId = performanceUserId($db, 'dev.dean@uob.test');
    $reviewerId = performanceUserId($db, 'dev.vp@uob.test');
    $partnerId = (int) $db->query(
        'SELECT partner_id FROM partners ORDER BY partner_id LIMIT 1'
    )->fetchColumn();
    performanceAssert($partnerId > 0, 'A development partner is required');

    $year = (int) date('Y');
    $periodStart = sprintf('%04d-01-01', $year);
    $periodEnd = sprintf('%04d-12-31', $year);
    $agreementId = $agreements->create([
        'title' => 'Performance monitoring rollback test',
        'agreement_type' => 'Memorandum of Understanding',
        'description' => 'Rollback-only annual reporting regression test.',
        'start_date' => $periodStart,
        'end_date' => $periodEnd,
        'effective_date' => $periodStart,
        'annual_report_required' => true,
        'created_by' => $creatorId,
        'status' => 'ACTIVE',
    ]);
    $agreements->replacePartners($agreementId, [$partnerId]);
    $agreements->replaceMetrics($agreementId, [[
        'metric_code' => 'STUDENTS_EXCHANGED',
        'planned_value' => 10,
        'actual_value' => null,
        'notes' => 'Annual student exchange target.',
    ]]);
    $agreements->replaceExecutivePrograms($agreementId, [[
        'title' => 'Student exchange implementation program',
        'description' => 'Performance smoke-test program.',
        'objectives' => 'Exchange students.',
        'expected_outputs' => 'Ten exchanges.',
        'start_date' => $periodStart,
        'end_date' => $periodEnd,
        'responsible_entity' => 'College',
        'applicant_name' => 'Test creator',
    ]]);
    $versions->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Performance monitoring smoke test',
        'agreement_snapshot' => $agreements->findById($agreementId),
        'created_by' => $creatorId,
    ]);

    $generation = $performance->generatePeriods(new DateTimeImmutable('today'));
    $created = array_values(array_filter(
        $generation['created'],
        static fn (array $report): bool =>
            (int) $report['agreement_id'] === $agreementId
    ));
    performanceAssert(count($created) === 1, 'Current annual period was not generated once');
    $reportId = (int) $created[0]['performance_report_id'];
    performanceAssert(count($created[0]['metrics']) === 1, 'Baseline metric was not copied');
    performanceAssert(count($created[0]['program_updates']) === 1, 'Executive program was not copied');

    $secondGeneration = $performance->generatePeriods(new DateTimeImmutable('today'));
    $duplicate = array_filter(
        $secondGeneration['created'],
        static fn (array $report): bool =>
            (int) $report['agreement_id'] === $agreementId
    );
    performanceAssert(count($duplicate) === 0, 'Generator created a duplicate annual period');

    $storageKey = date('Y/m') . '/' . bin2hex(random_bytes(32)) . '.pdf';
    $createdPath = dirname(__DIR__)
        . '/storage/private/agreement-documents/' . $storageKey;
    if (!is_dir(dirname($createdPath))) {
        mkdir(dirname($createdPath), 0750, true);
    }
    $contents = "%PDF-1.4\n% annual performance rollback test\n%%EOF\n";
    file_put_contents($createdPath, $contents);
    $versionStatement = $db->prepare(
        'SELECT version_id FROM agreement_versions
         WHERE agreement_id = :agreement_id
         ORDER BY version_number DESC LIMIT 1'
    );
    $versionStatement->execute(['agreement_id' => $agreementId]);
    $documentStatement = $db->prepare(
        'INSERT INTO agreement_documents (
            agreement_id, agreement_version_id, file_name, storage_key,
            mime_type, file_size_bytes, sha256_checksum, document_type,
            uploaded_by, uploaded_at
         ) VALUES (
            :agreement_id, :agreement_version_id, :file_name, :storage_key,
            \'application/pdf\', :file_size_bytes, :sha256_checksum,
            \'ANNUAL_REPORT\', :uploaded_by, NOW()
         ) RETURNING document_id'
    );
    $documentStatement->execute([
        'agreement_id' => $agreementId,
        'agreement_version_id' => (int) $versionStatement->fetchColumn(),
        'file_name' => 'annual-performance-test.pdf',
        'storage_key' => $storageKey,
        'file_size_bytes' => strlen($contents),
        'sha256_checksum' => hash('sha256', $contents),
        'uploaded_by' => $creatorId,
    ]);
    $documentId = (int) $documentStatement->fetchColumn();

    $report = $performance->report($reportId, $creatorId);
    $metric = $report['metrics'][0];
    $program = $report['program_updates'][0];
    $input = [
        'executive_summary' => 'The annual program delivered measurable value.',
        'achievements' => 'Eight student exchanges were completed.',
        'challenges' => 'Two placements were delayed.',
        'corrective_actions' => 'Confirm placements earlier.',
        'next_period_plan' => 'Reach the original ten-student target.',
        'report_document_id' => $documentId,
        'metrics' => [[
            'agreement_metric_id' => $metric['agreement_metric_id'],
            'metric_code' => $metric['metric_code'],
            'metric_label' => $metric['metric_label'],
            'planned_value' => $metric['planned_value'],
            'actual_value' => 8,
            'unit' => 'COUNT',
            'notes' => 'Verified against the attached report.',
        ]],
        'program_updates' => [[
            'executive_program_id' => $program['executive_program_id'],
            'program_title' => $program['program_title'],
            'progress_status' => 'ON_TRACK',
            'completion_percent' => 80,
            'achievements' => 'Eight exchanges.',
            'outputs_delivered' => 'Placement and completion records.',
            'challenges' => 'Two delayed placements.',
            'next_steps' => 'Complete remaining placements.',
        ]],
    ];
    $performance->update($reportId, $creatorId, $input);
    $submitted = $performance->submit($reportId, $creatorId);
    performanceAssert($submitted['status'] === 'SUBMITTED', 'Report was not submitted');

    $returned = $performance->review(
        $reportId,
        $reviewerId,
        'RETURN',
        'Clarify the corrective-action owner.'
    );
    performanceAssert($returned['status'] === 'RETURNED', 'Report was not returned');
    $input['corrective_actions'] = 'The College coordinator will confirm placements earlier.';
    $performance->update($reportId, $creatorId, $input);
    $performance->submit($reportId, $creatorId);
    $accepted = $performance->review(
        $reportId,
        $reviewerId,
        'ACCEPT',
        'Verified and accepted.'
    );
    performanceAssert($accepted['status'] === 'ACCEPTED', 'Report was not accepted');
    performanceAssert(count($accepted['events']) === 4, 'Status history is incomplete');

    $dashboard = $performance->dashboard($year, $reviewerId);
    $studentMetric = array_values(array_filter(
        $dashboard['metrics'],
        static fn (array $item): bool =>
            $item['metric_code'] === 'STUDENTS_EXCHANGED'
    ));
    performanceAssert($studentMetric !== [], 'Accepted metric is absent from dashboard');
    performanceAssert(
        (float) $studentMetric[0]['actual_value'] >= 8,
        'Accepted metric result is incorrect'
    );

    try {
        $agreementService->deleteDocument($documentId, $creatorId);
        throw new RuntimeException('Linked report document was deleted');
    } catch (DomainException $expected) {
        performanceAssert(
            str_contains($expected->getMessage(), 'performance report'),
            'Unexpected linked-document protection error'
        );
    }

    $db->rollBack();
    if ($createdPath !== null && is_file($createdPath)) {
        unlink($createdPath);
    }
    echo "Agreement performance monitoring smoke test passed; transaction rolled back.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    if ($createdPath !== null && is_file($createdPath)) {
        unlink($createdPath);
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
