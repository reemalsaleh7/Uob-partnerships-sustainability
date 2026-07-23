<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../services/AgreementLifecycleService.php';

function successorAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function successorUserId(PDO $db, string $email): int
{
    $statement = $db->prepare('SELECT user_id FROM users WHERE email = :email');
    $statement->execute(['email' => $email]);
    $userId = $statement->fetchColumn();
    if ($userId === false) {
        throw new RuntimeException("Development user {$email} was not found");
    }
    return (int) $userId;
}

function successorAssignedReviewer(PDO $db, int $instanceId, string $stepKey): int
{
    $statement = $db->prepare(
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
    $statement->execute([
        'instance_id' => $instanceId,
        'step_key' => $stepKey,
    ]);
    $userId = $statement->fetchColumn();
    if ($userId === false) {
        throw new RuntimeException("No active reviewer was assigned to {$stepKey}");
    }
    return (int) $userId;
}

function approveSuccessorRequest(
    PDO $db,
    AgreementLifecycleService $service,
    int $instanceId
): array {
    foreach (['VP_INITIAL', 'LEGAL_REVIEW', 'VP_FINAL'] as $stepKey) {
        $reviewer = successorAssignedReviewer($db, $instanceId, $stepKey);
        $service->decide(
            $instanceId,
            $reviewer,
            'APPROVE',
            "{$stepKey} approved the successor test.",
            false
        );
    }
    $president = successorAssignedReviewer(
        $db,
        $instanceId,
        'PRESIDENT_APPROVAL'
    );
    return $service->decide(
        $instanceId,
        $president,
        'APPROVE',
        'President approved the successor Agreement.',
        false
    );
}

$db = Database::connect();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$service = new AgreementLifecycleService();

$db->beginTransaction();
try {
    $requester = successorUserId($db, 'dev.dean@uob.test');
    $partnerId = (int) $db->query(
        'SELECT partner_id FROM partners ORDER BY partner_id LIMIT 1'
    )->fetchColumn();
    successorAssert($partnerId > 0, 'A development partner is required');

    $sourceAgreementId = $agreements->create([
        'title' => 'Lifecycle successor smoke Agreement',
        'title_ar' => 'اتفاقية اختبار الاتفاقية اللاحقة',
        'agreement_type' => 'Memorandum of Understanding',
        'description' => 'Temporary source Agreement rolled back by the smoke test.',
        'geographic_scope' => 'INTERNATIONAL',
        'start_date' => '2025-01-01',
        'end_date' => '2026-12-31',
        'effective_date' => '2025-01-01',
        'need_justification' => 'Preserve structured data in a successor Agreement.',
        'expected_value' => 'Verified lifecycle lineage.',
        'objectives' => 'Verify renewal and amendment completion.',
        'collaboration_areas' => 'Research and student exchange.',
        'implementation_methods' => 'Joint programs.',
        'auto_renew' => false,
        'financial_commitments' => false,
        'human_resources_commitments' => true,
        'human_resources_description' => 'Named coordinators from both parties.',
        'training_programs' => false,
        'annual_report_required' => true,
        'legal_binding_status' => 'NON_BINDING',
        'created_by' => $requester,
        'status' => 'ACTIVE',
    ]);
    $agreements->replacePartners($sourceAgreementId, [$partnerId]);
    $agreements->replaceSdgs($sourceAgreementId, [4, 17]);
    $agreements->replaceRankings($sourceAgreementId, ['THE_IMPACT']);
    $agreements->replaceContacts($sourceAgreementId, [[
        'party_type' => 'PARTNER',
        'contact_role' => 'COORDINATOR',
        'partner_id' => $partnerId,
        'full_name' => 'Successor Test Coordinator',
        'email' => 'successor.test@example.com',
        'is_primary' => true,
    ]]);
    $agreements->replaceExecutivePrograms($sourceAgreementId, [[
        'title' => 'Successor Test Program',
        'start_date' => '2025-02-01',
        'end_date' => '2025-06-01',
    ]]);
    $agreements->replaceMetrics($sourceAgreementId, [[
        'metric_code' => 'STUDENTS_EXCHANGED',
        'planned_value' => 10,
        'actual_value' => 8,
        'notes' => 'Temporary test metric',
    ]]);

    $renewal = $service->create($sourceAgreementId, $requester, [
        'request_type' => 'RENEWAL',
        'justification' => 'Continue the successful cooperation.',
        'activities_summary' => 'All planned activities were completed.',
        'achieved_value' => 'The partnership met its expected outcomes.',
        'proposed_start_date' => '2027-01-01',
        'proposed_end_date' => '2028-12-31',
        'financial_amount' => null,
        'financial_currency' => 'BHD',
    ]);
    successorAssert($renewal['success'] === true, 'Renewal draft creation failed');
    $renewalRequestId = (int) $renewal['lifecycle_request_id'];
    $submitted = $service->submit($renewalRequestId, $requester);
    successorAssert($submitted['success'] === true, 'Renewal submission failed');
    $result = approveSuccessorRequest(
        $db,
        $service,
        (int) $submitted['workflow_instance_id']
    );
    $renewedAgreementId = (int) ($result['successor_agreement_id'] ?? 0);
    successorAssert($renewedAgreementId > 0, 'Renewal did not create a successor');

    $source = $agreements->findById($sourceAgreementId);
    $renewed = $agreements->findById($renewedAgreementId);
    successorAssert($source !== null && $source['status'] === 'ACTIVE', 'Source Agreement changed');
    successorAssert($source['end_date'] === '2026-12-31', 'Source dates were overwritten');
    successorAssert($renewed !== null && $renewed['status'] === 'APPROVED', 'Successor is not approved');
    successorAssert($renewed['start_date'] === '2027-01-01', 'Renewal start date was not applied');
    successorAssert($renewed['end_date'] === '2028-12-31', 'Renewal end date was not applied');
    successorAssert($renewed['partner_ids'] === [$partnerId], 'Partners were not cloned');
    successorAssert($renewed['sdgs'] === [4, 17], 'SDGs were not cloned');
    successorAssert(count($renewed['contacts']) === 1, 'Contacts were not cloned');
    successorAssert(count($renewed['executive_programs']) === 1, 'Programs were not cloned');
    successorAssert(count($renewed['metrics']) === 1, 'Metrics were not cloned');

    $request = $service->findByIdForUser($renewalRequestId, $requester);
    successorAssert(
        (int) ($request['successor_agreement_id'] ?? 0) === $renewedAgreementId,
        'Lifecycle request does not expose its successor'
    );
    $renewedVersions = $versions->findByAgreement($renewedAgreementId);
    successorAssert(count($renewedVersions) === 1, 'Successor must start with one version');
    $provenance = $renewedVersions[0]['agreement_snapshot']['lifecycle_provenance'] ?? [];
    successorAssert(
        (int) ($provenance['lifecycle_request_id'] ?? 0) === $renewalRequestId,
        'Successor version lacks lifecycle provenance'
    );

    $relationship = $db->prepare(
        'SELECT relationship_type
         FROM agreement_relationships
         WHERE parent_agreement_id = :source_id
           AND related_agreement_id = :successor_id'
    );
    $relationship->execute([
        'source_id' => $sourceAgreementId,
        'successor_id' => $renewedAgreementId,
    ]);
    successorAssert(
        $relationship->fetchColumn() === 'RENEWAL',
        'Renewal relationship was not created'
    );

    $amendment = $service->create($sourceAgreementId, $requester, [
        'request_type' => 'AMENDMENT',
        'justification' => 'Update the approved cooperation terms.',
        'amendment_type' => 'Scope amendment',
        'amendment_reason' => 'Add a new research activity.',
        'terms_to_amend' => 'Clause 4 and the attached approved amendment.',
        'financial_amount' => null,
        'financial_currency' => 'BHD',
    ]);
    successorAssert($amendment['success'] === true, 'Amendment draft creation failed');
    $amendmentRequestId = (int) $amendment['lifecycle_request_id'];
    $submitted = $service->submit($amendmentRequestId, $requester);
    $result = approveSuccessorRequest(
        $db,
        $service,
        (int) $submitted['workflow_instance_id']
    );
    $amendedAgreementId = (int) ($result['successor_agreement_id'] ?? 0);
    successorAssert(
        $amendedAgreementId > 0 && $amendedAgreementId !== $renewedAgreementId,
        'Amendment did not create its own successor'
    );
    $amendedVersion = $versions->findLatest($amendedAgreementId);
    $amendmentProvenance =
        $amendedVersion['agreement_snapshot']['lifecycle_provenance'] ?? [];
    successorAssert(
        ($amendmentProvenance['terms_to_amend'] ?? null)
            === 'Clause 4 and the attached approved amendment.',
        'Approved amendment terms are missing from immutable provenance'
    );

    $audit = $db->prepare(
        'SELECT COUNT(*)
         FROM audit_logs
         WHERE table_name = \'agreements\'
           AND record_id IN (:renewed_id, :amended_id)
           AND action = \'INSERT\''
    );
    $audit->execute([
        'renewed_id' => $renewedAgreementId,
        'amended_id' => $amendedAgreementId,
    ]);
    successorAssert((int) $audit->fetchColumn() === 2, 'Successor audit entries are missing');

    $db->rollBack();
    echo "Agreement lifecycle successor smoke test passed.\n";
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
