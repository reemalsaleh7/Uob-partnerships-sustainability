<?php
require_once __DIR__ . '/services/AgreementService.php';
require_once __DIR__ . '/config/database.php';

function assertTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = Database::connect();
$cleanup = $db->prepare("DELETE FROM agreements WHERE title = 'Lifecycle Test MOU'");
$cleanup->execute();
$userId = (int) $db->query("SELECT user_id FROM users WHERE email = 'dev.faculty@uob.test'")->fetchColumn();
$partnerId = (int) $db->query("SELECT partner_id FROM partners WHERE organization_name = 'Bahrain Institute of Technology'")->fetchColumn();
$service = new AgreementService();

$created = $service->createAgreement([
    'title' => 'Lifecycle Test MOU', 'agreement_type' => 'MOU',
    'description' => 'Temporary integration test record', 'partner_id' => $partnerId, 'created_by' => $userId,
]);
assertTrue($created['success'], 'Create failed');
$agreementId = (int) $created['agreement_id'];
$version1 = $service->findVersion($agreementId, 1);
assertTrue(($version1['agreement_snapshot']['title'] ?? null) === 'Lifecycle Test MOU', 'Version 1 snapshot incorrect');

$updated = $service->updateAgreement($agreementId, [
    'title' => 'Updated Lifecycle Test MOU', 'change_summary' => 'Test update', 'updated_by' => $userId,
]);
assertTrue($updated['success'], 'Update failed');
$version2 = $service->findVersion($agreementId, 2);
assertTrue(($version1['agreement_snapshot']['title'] ?? null) === 'Lifecycle Test MOU', 'Version 1 changed');
assertTrue(($version2['agreement_snapshot']['title'] ?? null) === 'Updated Lifecycle Test MOU', 'Version 2 snapshot incorrect');

$document = $service->uploadDocument($agreementId, [
    'file_name' => 'lifecycle-test.pdf', 'file_path' => 'storage/test/lifecycle-test.pdf',
    'document_type' => 'TEST', 'uploaded_by' => $userId,
]);
assertTrue($service->deleteDocument((int) $document['document_id'], $userId), 'Document delete failed');

$submitted = $service->submitAgreement($agreementId, $userId);
assertTrue($submitted['success'], 'Submit failed');
assertTrue(($service->findById($agreementId)['status'] ?? null) === 'UNDER_REVIEW', 'Wrong submitted status');

$audit = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE table_name = 'agreements' AND record_id = :id");
$audit->execute(['id' => $agreementId]);
assertTrue((int) $audit->fetchColumn() >= 3, 'Agreement audits missing');

$service->deleteAgreement($agreementId, $userId);
assertTrue($service->findById($agreementId) === null, 'Delete failed');

echo "Agreement lifecycle integration test passed", PHP_EOL;
