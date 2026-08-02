<?php

declare(strict_types=1);

$draftId = filter_input(
    INPUT_GET,
    'draft_id',
    FILTER_VALIDATE_INT
);

$target = 'register-existing-initiative.php';

if ($draftId && $draftId > 0) {
    $target .= '?draft_id=' . $draftId;
}

header('Location: ' . $target, true, 302);
exit;
