<?php

declare(strict_types=1);

$initiativeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$target = '../initiative-view.php';

if ($initiativeId && $initiativeId > 0) {
    $target .= '?id=' . $initiativeId;
}

header('Location: ' . $target, true, 302);
exit;
