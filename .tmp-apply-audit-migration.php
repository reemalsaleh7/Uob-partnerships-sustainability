<?php
require_once __DIR__ . '/config/database.php';

$db = Database::connect();
$sql = file_get_contents(__DIR__ . '/uob-agreements/data/sql/migrations/20260716_create_audit_logs.sql');
$db->exec($sql);

echo "Audit migration applied", PHP_EOL;
