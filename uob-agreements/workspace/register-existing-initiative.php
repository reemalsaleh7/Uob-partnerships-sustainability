<?php

declare(strict_types=1);

$draftId = filter_input(
    INPUT_GET,
    'draft_id',
    FILTER_VALIDATE_INT
);

$_GET['view'] = 'existing';

if ($draftId && $draftId > 0) {
    $_GET['draft_id'] = (string) $draftId;
}

require __DIR__ . '/initiative-workflow.php';
