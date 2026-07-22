<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $settings = self::settings();
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $settings['host'],
            $settings['port'],
            $settings['dbname']
        );

        self::$instance = new PDO(
            $dsn,
            $settings['user'],
            $settings['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$instance;
    }

    private static function settings(): array
    {
        $localPath = __DIR__ . '/database.local.php';
        $local = [];

        if (is_file($localPath)) {
            $loaded = require $localPath;
            if (!is_array($loaded)) {
                throw new RuntimeException(
                    'config/database.local.php must return a configuration array'
                );
            }
            $local = $loaded;
        }

        $settings = [
            'host' => self::value('UOB_DB_HOST', $local, 'host', '127.0.0.1'),
            'port' => self::value('UOB_DB_PORT', $local, 'port', '5432'),
            'dbname' => self::value(
                'UOB_DB_NAME',
                $local,
                'dbname',
                'UOB_Partnership_and_Initiative'
            ),
            'user' => self::value('UOB_DB_USER', $local, 'user', 'postgres'),
            'password' => self::value('UOB_DB_PASSWORD', $local, 'password', ''),
        ];

        if (
            $settings['password'] === ''
            || $settings['password'] === 'CHANGE_ME'
        ) {
            throw new RuntimeException(
                'Database password is not configured. Copy '
                . 'config/database.local.example.php to '
                . 'config/database.local.php and set the local password, '
                . 'or define UOB_DB_PASSWORD.'
            );
        }

        return $settings;
    }

    private static function value(
        string $environmentName,
        array $local,
        string $localKey,
        string $default
    ): string {
        $environmentValue = getenv($environmentName);
        if ($environmentValue !== false && trim($environmentValue) !== '') {
            return trim($environmentValue);
        }

        if (array_key_exists($localKey, $local)) {
            return trim((string) $local[$localKey]);
        }

        return $default;
    }
}
