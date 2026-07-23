<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AgreementOperationService.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is available only from the CLI.\n");
    exit(1);
}

$options = getopt('', ['as-of:', 'commit', 'help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/sync_agreement_operational_statuses.php [--as-of=YYYY-MM-DD] [--commit]\n";
    echo "Without --commit, the command is a read-only dry run.\n";
    exit(0);
}

$dateValue = (string) ($options['as-of'] ?? date('Y-m-d'));
$asOf = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
if (!$asOf || $asOf->format('Y-m-d') !== $dateValue) {
    fwrite(STDERR, "--as-of must use YYYY-MM-DD.\n");
    exit(2);
}

try {
    $service = new AgreementOperationService();
    $preview = $service->previewTransitions($asOf);
    $commit = array_key_exists('commit', $options);
    $result = $commit ? $service->synchronize($asOf) : $preview;
    echo json_encode([
        'mode' => $commit ? 'commit' : 'dry-run',
        'as_of' => $dateValue,
        'candidate_count' => count($preview),
        'results' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
