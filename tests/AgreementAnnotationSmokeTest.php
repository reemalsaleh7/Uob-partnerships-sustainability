<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementAnnotationService.php';

function annotationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$users = new UserRepository();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$annotations = new AgreementAnnotationService();

$dean = $users->findByEmail('dev.dean@uob.test');
$vp = $users->findByEmail('dev.vp@uob.test');
$admin = $users->findByEmail('dev.admin@uob.test');

annotationAssert(
    $dean !== null && $vp !== null && $admin !== null,
    'Required development users were not found'
);

$db->beginTransaction();

try {
    $agreementId = $agreements->create([
        'title' => 'Temporary Annotation Baseline',
        'agreement_type' => 'MOU',
        'description' => 'Annotation authorization and change review test',
        'created_by' => (int) $dean['user_id'],
        'status' => 'ACTIVE',
    ]);
    $versions->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Initial Agreement version',
        'agreement_snapshot' => $agreements->findById($agreementId),
        'created_by' => (int) $dean['user_id'],
    ]);

    $annotations->markViewed($agreementId, (int) $vp['user_id'], 1);
    $agreements->update($agreementId, [
        'title' => 'Temporary Revised Annotation Agreement',
    ]);
    $versions->create($agreementId, [
        'version_number' => 2,
        'change_summary' => 'Clarified the title after Legal Office feedback',
        'agreement_snapshot' => $agreements->findById($agreementId),
        'created_by' => (int) $dean['user_id'],
    ]);

    $context = $annotations->reviewContext(
        $agreementId,
        (int) $vp['user_id']
    );
    annotationAssert(
        $context['has_unseen_changes'] === true,
        'A previously viewed Agreement did not report its newer version'
    );
    annotationAssert(
        count($context['changes']) === 1
        && $context['changes'][0]['field_key'] === 'title',
        'The title change was not isolated correctly'
    );
    annotationAssert(
        str_contains(
            (string) $context['changes'][0]['reason'],
            'Legal Office feedback'
        ),
        'The revision reason was not attached to the changed field'
    );

    $private = $annotations->create($agreementId, (int) $dean['user_id'], [
        'field_key' => 'description',
        'comment_text' => 'My private preparation note',
        'visibility' => 'PRIVATE',
    ]);
    $shared = $annotations->create($agreementId, (int) $vp['user_id'], [
        'field_key' => 'title',
        'selected_text' => 'Revised',
        'selection_start' => 10,
        'selection_end' => 17,
        'comment_text' => 'Please confirm this revised wording.',
        'visibility' => 'SHARED',
    ]);

    $deanList = $annotations->listForUser(
        $agreementId,
        (int) $dean['user_id']
    );
    $vpList = $annotations->listForUser(
        $agreementId,
        (int) $vp['user_id']
    );
    $adminList = $annotations->listForUser(
        $agreementId,
        (int) $admin['user_id']
    );

    annotationAssert(count($deanList) === 2, 'Author could not see private and shared comments');
    annotationAssert(count($vpList) === 1, 'Another user could see a private comment');
    annotationAssert(count($adminList) === 1, 'Administrator could see another user\'s private comment');
    annotationAssert(
        (int) $vpList[0]['annotation_id'] === (int) $shared['annotation_id'],
        'Shared comment visibility returned the wrong record'
    );

    $privateHidden = false;
    try {
        $annotations->resolve(
            $agreementId,
            (int) $private['annotation_id'],
            (int) $vp['user_id']
        );
    } catch (DomainException $exception) {
        $privateHidden = $exception->getMessage() === 'Comment not found';
    }
    annotationAssert($privateHidden, 'Private comment existence leaked through the resolve action');

    $annotations->resolve(
        $agreementId,
        (int) $shared['annotation_id'],
        (int) $dean['user_id']
    );
    $resolved = array_values(array_filter(
        $annotations->listForUser($agreementId, (int) $dean['user_id']),
        static fn (array $item): bool =>
            (int) $item['annotation_id'] === (int) $shared['annotation_id']
    ));
    annotationAssert(
        count($resolved) === 1 && $resolved[0]['status'] === 'RESOLVED',
        'Agreement creator could not resolve a shared review comment'
    );

    $db->rollBack();
    echo "Agreement annotation smoke test passed; transaction rolled back.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
