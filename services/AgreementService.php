<?php
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/AgreementDocumentRepository.php';
require_once __DIR__ . '/../repositories/AuditRepository.php';
require_once __DIR__ . '/../validators/AgreementValidator.php';
require_once __DIR__ . '/../services/AuditService.php';

class AgreementService {
    private AgreementRepository $agreementRepo;
    private AgreementVersionRepository $agreementVersionRepo;
    private AgreementDocumentRepository $agreementDocumentRepo;
    private AuditRepository $auditRepo;
    private AuditService $auditService;

    public function __construct() {
        $this->agreementRepo = new AgreementRepository();
        $this->agreementVersionRepo = new AgreementVersionRepository();
        $this->agreementDocumentRepo = new AgreementDocumentRepository();
        $this->auditRepo = new AuditRepository();
        $this->auditService = new AuditService();
    }

    public function createAgreement(array $data): array {
        $errors = AgreementValidator::validateCreate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $agreementId = $this->agreementRepo->create([
            'title' => trim($data['title']),
            'agreement_type' => trim($data['agreement_type']),
            'description' => trim($data['description']),
            'partner_id' => $data['partner_id'],
            'created_by' => $data['created_by'],
            'status' => 'DRAFT',
        ]);

        $this->agreementVersionRepo->create($agreementId, [
            'version_number' => 1,
            'change_summary' => 'Initial agreement created',
            'created_by' => $data['created_by'],
        ]);

        $this->auditService->write('agreements', $agreementId, 'CREATE', $data['created_by'] ?? null, null, [
            'agreement_id' => $agreementId,
            'title' => trim($data['title']),
        ]);

        return ['success' => true, 'agreement_id' => $agreementId];
    }

    public function updateAgreement(int $agreementId, array $data): array {
        $errors = AgreementValidator::validateUpdate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $existing = $this->agreementRepo->findById($agreementId);
        $this->agreementRepo->update($agreementId, $data);

        $nextVersion = $this->agreementVersionRepo->findByAgreement($agreementId);
        $versionNumber = count($nextVersion) + 1;

        $this->agreementVersionRepo->create($agreementId, [
            'version_number' => $versionNumber,
            'change_summary' => $data['change_summary'] ?? 'Agreement updated',
            'created_by' => $data['updated_by'] ?? 0,
        ]);

        $this->auditService->write('agreements', $agreementId, 'UPDATE', $data['updated_by'] ?? null, $existing, [
            'agreement_id' => $agreementId,
            'title' => $data['title'] ?? ($existing['title'] ?? null),
        ]);

        return ['success' => true];
    }

    public function submitAgreement(int $agreementId, int $userId): array {
        $this->agreementRepo->changeStatus($agreementId, 'SUBMITTED');
        $this->auditService->write('agreements', $agreementId, 'SUBMIT', $userId, null, ['status' => 'SUBMITTED']);
        return ['success' => true];
    }

    public function deleteAgreement(int $agreementId): void {
        $this->agreementRepo->delete($agreementId);
        $this->auditService->write('agreements', $agreementId, 'DELETE', null, null, ['deleted' => true]);
    }

    public function uploadDocument(int $agreementId, array $data): array {
        $documentId = $this->agreementDocumentRepo->create($agreementId, [
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'document_type' => $data['document_type'] ?? 'GENERAL',
            'uploaded_by' => $data['uploaded_by'],
        ]);

        $this->auditService->write('agreement_documents', $documentId, 'CREATE', $data['uploaded_by'] ?? null, null, ['agreement_id' => $agreementId]);
        return ['success' => true, 'document_id' => $documentId];
    }

    public function listDocuments(int $agreementId): array {
        return $this->agreementDocumentRepo->findByAgreement($agreementId);
    }

    public function findVersions(int $agreementId): array {
        return $this->agreementVersionRepo->findByAgreement($agreementId);
    }

    public function findById(int $agreementId): ?array {
        return $this->agreementRepo->findById($agreementId);
    }

    public function findAll(): array {
        return $this->agreementRepo->findAll();
    }

    public function findByStatus(string $status): array {
        return $this->agreementRepo->findByStatus($status);
    }
}
