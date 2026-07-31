<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$userId = initiativeUserId();

initiativeVerifyCsrf();

$initiativeId = (int) ($_POST['initiative_id'] ?? 0);
$note = trim((string) ($_POST['note'] ?? ''));

$initiative = initiativeLoad($initiativeId);

initiativeRequireView($initiative, $userId);

if ($note === '') {
    http_response_code(400);
    exit('Revision note is required.');
}

$round = initiativeOpenRevision($initiativeId);

if (!$round) {
    http_response_code(400);
    exit('No open revision round.');
}

$db = initiativeDb();

try {
    $db->beginTransaction();

    $statement = $db->prepare(
        "INSERT INTO initiative_revision_notes (
            revision_round_id,
            author_id,
            note_text
         ) VALUES (
            :revision_round_id,
            :author_id,
            :note_text
         )"
    );

    $statement->execute([
        'revision_round_id' => (int) $round['revision_round_id'],
        'author_id' => $userId,
        'note_text' => $note,
    ]);

    initiativeEvent(
        $initiativeId,
        $userId,
        'REVISION_NOTE_ADDED',
        [
            'revision_round_id' => (int) $round['revision_round_id'],
        ]
    );

    $creatorId = (int) $initiative['created_by'];
    $requestedBy = (int) ($round['requested_by'] ?? 0);

    $recipientId = $creatorId === $userId
        ? $requestedBy
        : $creatorId;

    if ($recipientId > 0 && $recipientId !== $userId) {
        initiativeNotify(
            $recipientId,
            $initiativeId,
            'REVISION_NOTE',
            'New revision discussion note',
            (string) $initiative['title']
        );
    }

    $db->commit();

    header(
        'Location: initiative-view.php?id=' . $initiativeId,
        true,
        303
    );

    exit;
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(400);
    exit(initiativeH($exception->getMessage()));
}