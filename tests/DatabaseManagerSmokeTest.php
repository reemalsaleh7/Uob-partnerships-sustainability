<?php

declare(strict_types=1);

function managerAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function managerRead(string $relativePath): string
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        $relativePath
    );
    managerAssert(is_file($path), "Required manager file is missing: {$relativePath}");

    $content = file_get_contents($path);
    managerAssert(
        is_string($content),
        "Could not read manager file: {$relativePath}"
    );

    return $content;
}

$batch = managerRead('database-manager.cmd');
$manager = managerRead('scripts/database_manager.ps1');
$generator = managerRead('scripts/new_database_migration.ps1');
$databaseConfig = managerRead('config/database.php');
$localExample = managerRead('config/database.local.example.php');
$gitignore = managerRead('.gitignore');
$developmentSeed = managerRead(
    'uob-agreements/data/sql/seed/seed_dev.sql'
);

managerAssert(
    str_contains($batch, ':finished')
        && str_contains($batch, 'set "UOB_DB_EXIT=%ERRORLEVEL%"'),
    'The batch wrapper must retain the finished label and child exit code'
);
managerAssert(
    str_contains($batch, 'set "UOB_DB_PAUSE=0"'),
    'Command-line manager runs must not pause unattended scripts'
);

foreach (['pdo_pgsql', 'fileinfo', 'zip'] as $extension) {
    managerAssert(
        str_contains($manager, "Module = '{$extension}'"),
        "The manager does not check required PHP extension {$extension}"
    );
}
managerAssert(
    str_contains($manager, 'Test-ApplicationDatabaseConfiguration')
        && str_contains($manager, 'UOB_CONFIG_PROBE_PATH'),
    'The manager does not verify the PHP application database loader'
);
managerAssert(
    str_contains($manager, 'expected_positions')
        && str_contains($manager, 'expected_roles')
        && str_contains($manager, 'expected_partners'),
    'The development-data check must verify users, roles, positions, and partners'
);
managerAssert(
    str_contains($manager, '20260802_120000_agreement_administrative_corrections.sql')
        && str_contains($manager, 'ADMIN_CORRECT_LEGACY_AGREEMENT')
        && str_contains($manager, 'agreement_administrative_corrections'),
    'The database manager does not install or verify administrative corrections'
);
managerAssert(
    str_contains($manager, 'ON CONFLICT (migration_name) DO UPDATE'),
    'Repeatable setup checksums are not refreshed after verification'
);

managerAssert(
    !str_contains($databaseConfig, 'MySecurePassword123')
        && str_contains($databaseConfig, 'database.local.php'),
    'Application database configuration contains a hardcoded password or ignores the local file'
);
foreach ([
    'UOB_DB_HOST',
    'UOB_DB_PORT',
    'UOB_DB_NAME',
    'UOB_DB_USER',
    'UOB_DB_PASSWORD',
] as $variable) {
    managerAssert(
        str_contains($databaseConfig, $variable),
        "Application database configuration ignores {$variable}"
    );
}
managerAssert(
    str_contains($localExample, "'password' => 'CHANGE_ME'")
        && str_contains($gitignore, '/config/database.local.php'),
    'The safe local database configuration example or ignore rule is missing'
);

managerAssert(
    !str_contains($developmentSeed, 'ON CONFLICT (email) DO UPDATE')
        && str_contains($developmentSeed, 'uob_dev_users')
        && str_contains($developmentSeed, 'Development identity collision'),
    'The development seed is not safe for existing university IDs'
);
foreach ([
    'dev.president@uob.test',
    'dev.vp@uob.test',
    'dev.dean@uob.test',
    'dev.head@uob.test',
] as $approver) {
    managerAssert(
        preg_match(
            "/\\('"
                . preg_quote($approver, '/')
                . "', 'Initiative Approver'\\)/",
            $developmentSeed
        ) === 1,
        "The Initiative approver fixture is missing for {$approver}"
    );
}
managerAssert(
    str_contains($developmentSeed, 'Preserve assignment history')
        && !str_contains(
            $developmentSeed,
            "DELETE FROM user_positions\nWHERE user_id"
        ),
    'The development seed must preserve position-assignment history'
);

managerAssert(
    str_contains($generator, 'yyyyMMdd_HHmmss')
        && str_contains($generator, 'UOB_MIGRATION_TODO')
        && str_contains($manager, 'Migration checksum mismatch')
        && str_contains($manager, '--single-transaction'),
    'Migration creation or immutable installation safeguards are missing'
);

echo "Database manager smoke test passed.\n";
