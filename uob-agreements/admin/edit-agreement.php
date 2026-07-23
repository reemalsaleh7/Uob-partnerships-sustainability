<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Legacy Agreement codes cannot be safely translated to PostgreSQL IDs.
// Send old bookmarks to the protected register instead of guessing a record.
header('Location: ../workspace/agreements.php', true, 302);
exit;
