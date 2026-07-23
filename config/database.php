<?php
class Database {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $host = 'localhost';
            $port = '5432';
            $db   = 'UOB_Partnership_and_Initiative';
            $user = 'postgres';
            $pass = 'MySecurePassword123';

            $dsn = "pgsql:host=$host;port=$port;dbname=$db";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }
}