<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/PublicAgreementRepository.php';

function publicAgreementAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$users = new UserRepository();
$agreements = new AgreementRepository();
$publicAgreements = new PublicAgreementRepository();

$dean = $users->findByEmail('dev.dean@uob.test');

publicAgreementAssert(
    $dean !== null,
    'Development Dean was not found'
);

$db->beginTransaction();

try {
    $publishedId = $agreements->create([
        'title' => 'Temporary Public Catalogue Test',
        'agreement_type' => 'MOU',
        'description' => 'Rolled back after public query verification',
        'created_by' => (int) $dean['user_id'],
        'status' => 'APPROVED',
    ]);

    $draftId = $agreements->create([
        'title' => 'Temporary Private Draft Test',
        'agreement_type' => 'MOU',
        'description' => 'This record must remain private',
        'created_by' => (int) $dean['user_id'],
        'status' => 'DRAFT',
    ]);

    $rows = $publicAgreements->findPublished();
    $ids = array_map(
        static fn (array $row): int => (int) $row['agreement_id'],
        $rows
    );

    publicAgreementAssert(
        in_array($publishedId, $ids, true),
        'APPROVED Agreement was missing from the public catalogue'
    );

    publicAgreementAssert(
        !in_array($draftId, $ids, true),
        'DRAFT Agreement leaked into the public catalogue'
    );

    $publishedRow = null;

    foreach ($rows as $row) {
        if ((int) $row['agreement_id'] === $publishedId) {
            $publishedRow = $row;
            break;
        }
    }

    publicAgreementAssert(
        $publishedRow !== null,
        'Temporary published Agreement could not be inspected'
    );

    publicAgreementAssert(
        $publishedRow['public_reference']
            === sprintf('UOB-AGR-%06d', $publishedId),
        'Stable public Agreement reference was not generated correctly'
    );

    publicAgreementAssert(
        !array_key_exists('created_by', $publishedRow),
        'Creator identity was exposed by the public query'
    );

    echo json_encode(
        [
            'success' => true,
            'published_id' => $publishedId,
            'draft_id' => $draftId,
            'message' => 'Public Agreement repository smoke test passed',
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
