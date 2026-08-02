<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/routes/initiative-workflow/InitiativeReminderService.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $limit = isset($argv[1]) ? (int) $argv[1] : 100;
    $result = (new InitiativeReminderService())->run($limit);

    echo sprintf(
        "Initiative reminders complete: %d stage(s), %d notification(s).%s",
        $result['stages_processed'],
        $result['notifications_created'],
        PHP_EOL
    );

    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'Initiative reminder worker failed: '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}
