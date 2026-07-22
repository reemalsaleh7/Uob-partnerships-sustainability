<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AgreementPerformanceService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must run from PHP CLI.\n");
    exit(1);
}

$commit = in_array('--commit', $argv, true);
$asOfValue = date('Y-m-d');
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--as-of=')) {
        $asOfValue = substr($argument, strlen('--as-of='));
    }
}
$asOf = DateTimeImmutable::createFromFormat('!Y-m-d', $asOfValue);
if (!$asOf || $asOf->format('Y-m-d') !== $asOfValue) {
    fwrite(STDERR, "--as-of must use YYYY-MM-DD.\n");
    exit(1);
}

$db = Database::connect();
$service = new AgreementPerformanceService();
$db->beginTransaction();

try {
    $result = $service->generatePeriods($asOf);
    if ($commit) {
        $db->commit();
    } else {
        $db->rollBack();
    }
    echo json_encode([
        'mode' => $commit ? 'commit' : 'dry-run',
        'as_of' => $asOfValue,
        'created_count' => count($result['created']),
        'skipped_count' => count($result['skipped']),
        'created' => array_map(
            static fn (array $report): array => [
                'performance_report_id' => $report['performance_report_id'],
                'agreement_id' => $report['agreement_id'],
                'agreement_title' => $report['agreement_title'],
                'period_start' => $report['period_start'],
                'period_end' => $report['period_end'],
                'due_date' => $report['due_date'],
            ],
            $result['created']
        ),
        'skipped' => $result['skipped'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
