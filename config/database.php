<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $local = [];
        $localPath = __DIR__ . '/database.local.php';

        if (is_file($localPath)) {
            $loaded = require $localPath;

            if (!is_array($loaded)) {
                throw new RuntimeException(
                    'config/database.local.php must return an array.'
                );
            }

            $local = $loaded;
        }

        $value = static function (
            string $environmentName,
            string $localName,
            string $default = ''
        ) use ($local): string {
            $environmentValue = getenv($environmentName);

            if ($environmentValue !== false && $environmentValue !== '') {
                return $environmentValue;
            }

            return isset($local[$localName])
                ? (string) $local[$localName]
                : $default;
        };

        $host = $value('UOB_DB_HOST', 'host', '127.0.0.1');
        $port = $value('UOB_DB_PORT', 'port', '5432');
        $database = $value(
            'UOB_DB_NAME',
            'dbname',
            'UOB_Partnership_and_Initiative'
        );
        $user = $value('UOB_DB_USER', 'user', 'postgres');
        $password = $value('UOB_DB_PASSWORD', 'password');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $host,
            $port,
            $database
        );

        self::$instance = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$instance;
    }
}
