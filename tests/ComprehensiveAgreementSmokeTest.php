<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../validators/AgreementValidator.php';

function comprehensiveAgreementAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$users = new UserRepository();
$agreements = new AgreementRepository();
$versions = new AgreementVersionRepository();
$dean = $users->findByEmail('dev.dean@uob.test');

comprehensiveAgreementAssert($dean !== null, 'Development Dean was not found');

$partners = $db->query(
    'SELECT partner_id FROM partners WHERE is_active = TRUE ORDER BY partner_id LIMIT 2'
)->fetchAll();
comprehensiveAgreementAssert(count($partners) >= 1, 'At least one active partner is required');

$partnerIds = array_map('intval', array_column($partners, 'partner_id'));
$db->beginTransaction();

try {
    $agreementId = $agreements->create([
        'title' => 'Temporary Comprehensive Agreement Test',
        'title_ar' => 'اختبار اتفاقية شاملة مؤقتة',
        'agreement_type' => 'Memorandum of Understanding',
        'description' => 'Rolled back after comprehensive field verification',
        'geographic_scope' => 'INTERNATIONAL',
        'start_date' => '2026-09-01',
        'end_date' => '2029-08-31',
        'auto_renew' => true,
        'renewal_term_months' => 36,
        'non_renewal_notice_months' => 6,
        'termination_notice_months' => 6,
        'need_justification' => 'Test need',
        'expected_value' => 'Test impact',
        'objectives' => 'Test objectives',
        'collaboration_areas' => 'Test cooperation fields',
        'implementation_methods' => 'Test implementation method',
        'legal_binding_status' => 'NON_BINDING',
        'created_by' => (int) $dean['user_id'],
        'status' => 'DRAFT',
    ]);

    $agreements->replacePartners($agreementId, $partnerIds);
    $agreements->replaceSdgs($agreementId, [4, 9, 17]);
    $agreements->replaceRankings($agreementId, ['QS_WORLD', 'THE_IMPACT']);
    $agreements->replaceContacts($agreementId, [[
        'party_type' => 'UOB',
        'contact_role' => 'COORDINATOR',
        'full_name' => 'Temporary Coordinator',
        'email' => 'temporary@uob.test',
        'is_primary' => true,
    ]]);
    $agreements->replaceExecutivePrograms($agreementId, [[
        'title' => 'Temporary Executive Program',
        'objectives' => 'Test program objectives',
        'expected_outputs' => 'Test outputs',
    ]]);
    $agreements->replaceMetrics($agreementId, [[
        'metric_code' => 'JOINT_PROGRAMS',
        'planned_value' => 1,
        'actual_value' => null,
        'notes' => 'One planned joint program',
    ]]);

    $snapshot = $agreements->findById($agreementId);
    comprehensiveAgreementAssert($snapshot !== null, 'Agreement could not be reloaded');
    comprehensiveAgreementAssert(count($snapshot['partner_ids']) === count($partnerIds), 'Partners were not hydrated');
    comprehensiveAgreementAssert($snapshot['sdgs'] === [4, 9, 17], 'SDGs were not hydrated');
    comprehensiveAgreementAssert(count($snapshot['rankings']) === 2, 'Rankings were not hydrated');
    comprehensiveAgreementAssert(count($snapshot['contacts']) === 1, 'Contacts were not hydrated');
    comprehensiveAgreementAssert(count($snapshot['executive_programs']) === 1, 'Executive programs were not hydrated');
    comprehensiveAgreementAssert(count($snapshot['metrics']) === 1, 'Metrics were not hydrated');
    comprehensiveAgreementAssert(
        AgreementValidator::validateForSubmission($snapshot) === [],
        'Complete Agreement did not pass submission validation'
    );

    $versions->create($agreementId, [
        'version_number' => 1,
        'change_summary' => 'Comprehensive snapshot test',
        'agreement_snapshot' => $snapshot,
        'created_by' => (int) $dean['user_id'],
    ]);

    $storedVersion = $versions->findByAgreementAndVersion($agreementId, 1);
    comprehensiveAgreementAssert(
        count($storedVersion['agreement_snapshot']['sdgs'] ?? []) === 3,
        'Nested comprehensive fields were missing from the immutable snapshot'
    );

    $agreements->update($agreementId, ['title' => 'Legacy-compatible title update']);
    $afterLegacyUpdate = $agreements->findById($agreementId);
    comprehensiveAgreementAssert(
        count($afterLegacyUpdate['contacts']) === 1,
        'An omitted child collection was erased by a scalar-only update'
    );

    echo "Comprehensive Agreement smoke test passed.\n";
} finally {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}
