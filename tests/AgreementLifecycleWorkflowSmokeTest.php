<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../services/AgreementLifecycleService.php';

$db = Database::connect();
$agreements = new AgreementRepository();
$service = new AgreementLifecycleService();

function assertLifecycle(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function userId(PDO $db, string $email): int
{
    $stmt = $db->prepare('SELECT user_id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Development user {$email} was not found");
    }
    return (int) $id;
}

function assignedReviewer(PDO $db, int $instanceId, string $stepKey): int
{
    $stmt = $db->prepare(
        'SELECT wsa.user_id
         FROM workflow_instance_steps wis
         JOIN workflow_step_assignments wsa
           ON wsa.workflow_instance_step_id = wis.instance_step_id
         WHERE wis.workflow_instance_id = :instance_id
           AND wis.step_key = :step_key
           AND wis.status = \'IN_PROGRESS\'
           AND wsa.is_active = TRUE
         ORDER BY wsa.user_id
         LIMIT 1'
    );
    $stmt->execute(['instance_id' => $instanceId, 'step_key' => $stepKey]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("No active reviewer was assigned to {$stepKey}");
    }
    return (int) $id;
}

$db->beginTransaction();

try {
    $requester = userId($db, 'dev.dean@uob.test');
    $agreementId = $agreements->create([
        'title' => 'Lifecycle workflow smoke Agreement',
        'agreement_type' => 'Memorandum of Understanding',
        'description' => 'Temporary record rolled back by the smoke test.',
        'status' => 'ACTIVE',
        'created_by' => $requester,
    ]);

    $created = $service->create($agreementId, $requester, [
        'request_type' => 'TERMINATION',
        'justification' => 'The cooperation has reached its planned conclusion.',
        'termination_reason' => 'All obligations have been completed.',
        'proposed_termination_date' => date('Y-m-d', strtotime('+30 days')),
        'previous_initiatives' => true,
        'financial_currency' => 'BHD',
    ]);
    assertLifecycle($created['success'] === true, 'Draft creation failed');
    $requestId = (int) $created['lifecycle_request_id'];

    $submitted = $service->submit($requestId, $requester);
    assertLifecycle($submitted['success'] === true, 'Submission failed');
    $instanceId = (int) $submitted['workflow_instance_id'];

    $vp = assignedReviewer($db, $instanceId, 'VP_INITIAL');
    $service->decide($instanceId, $vp, 'APPROVE', 'Route to both specialists.', true);

    $finance = assignedReviewer($db, $instanceId, 'FINANCE_REVIEW');
    $service->decide($instanceId, $finance, 'APPROVE', 'No outstanding financial obligations.', false);
    $legal = assignedReviewer($db, $instanceId, 'LEGAL_REVIEW');
    $service->decide($instanceId, $legal, 'APPROVE', 'Termination terms are satisfied.', false);

    $finalVp = assignedReviewer($db, $instanceId, 'VP_FINAL');
    $service->decide($instanceId, $finalVp, 'APPROVE', 'Recommend final approval.', false);
    $president = assignedReviewer($db, $instanceId, 'PRESIDENT_APPROVAL');
    $result = $service->decide($instanceId, $president, 'APPROVE', 'Approved.', false);

    assertLifecycle($result['status'] === 'APPROVED', 'Request did not complete as approved');
    $request = $service->findByIdForUser($requestId, $requester);
    assertLifecycle($request !== null && $request['status'] === 'APPROVED', 'Approved request was not persisted');
    $agreement = $agreements->findById($agreementId);
    assertLifecycle($agreement !== null && $agreement['status'] === 'TERMINATED', 'Approved termination was not applied');

    $db->rollBack();
    echo "Agreement lifecycle workflow smoke test passed.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
