<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (AGREEMENT_WORKSPACE_REPLACES_LEGACY_ADMIN) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ../workspace/agreements.php', true, 302);
    exit;
}

header('Location: review-agreements.php', true, 302);
exit;
