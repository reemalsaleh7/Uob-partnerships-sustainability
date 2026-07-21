<?php
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AgreementVersionRepository.php';
require_once __DIR__ . '/../repositories/AgreementDocumentRepository.php';
require_once __DIR__ . '/../repositories/AuditRepository.php';
require_once __DIR__ . '/../validators/AgreementValidator.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/AgreementStatus.php';
require_once __DIR__ . '/../helpers/AuditAction.php';
require_once __DIR__ . '/../services/ApprovalService.php';
class AgreementService {
    private AgreementRepository $agreementRepo;
    private ApprovalService $approvalService;
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
        $this->approvalService = new ApprovalService();
    }

    public function createAgreement(array $data): array {
        $errors = AgreementValidator::validateCreate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $agreementId = $this->agreementRepo->create([
                'title' => trim($data['title']),
                'agreement_type' => trim($data['agreement_type']),
                'description' => trim($data['description']),
                'created_by' => $data['created_by'],
                'status' => AgreementStatus::DRAFT,
            ]);
            $this->agreementRepo->replacePartners($agreementId, [(int) $data['partner_id']]);
            $snapshot = $this->agreementRepo->findById($agreementId);
            $this->agreementVersionRepo->create($agreementId, [
                'version_number' => 1,
                'change_summary' => 'Initial agreement created',
                'agreement_snapshot' => $snapshot,
                'created_by' => $data['created_by'],
            ]);
            $this->auditService->write('agreements', $agreementId, AuditAction::INSERT, $data['created_by'] ?? null, null, $snapshot);
            $db->commit();
            return ['success' => true, 'agreement_id' => $agreementId];
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function updateAgreement(int $agreementId, array $data): array {
        $errors = AgreementValidator::validateUpdate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $existing = $this->agreementRepo->findById($agreementId);
            if (!$existing) {
                $db->rollBack();
                return ['success' => false, 'errors' => ['Agreement not found']];
            }

            if (
                (int) $existing['created_by']
                !== (int) ($data['updated_by'] ?? 0)
            ) {
                $db->rollBack();

                return [
                    'success' => false,
                    'errors' => [
                        'Only the original Agreement creator may edit this Agreement',
                    ],
                ];
            }

            $this->agreementRepo->update($agreementId, $data);
            if (array_key_exists('partner_id', $data)) {
                $this->agreementRepo->replacePartners($agreementId, [(int) $data['partner_id']]);
            }
            $snapshot = $this->agreementRepo->findById($agreementId);
            $nextVersion = $this->agreementVersionRepo->findByAgreement($agreementId);
            $this->agreementVersionRepo->create($agreementId, [
                'version_number' => count($nextVersion) + 1,
                'change_summary' => $data['change_summary'] ?? 'Agreement updated',
                'agreement_snapshot' => $snapshot,
                'created_by' => $data['updated_by'] ?? 0,
            ]);
            $this->auditService->write('agreements', $agreementId, AuditAction::UPDATE, $data['updated_by'] ?? null, $existing, $snapshot);
            $db->commit();
            return ['success' => true];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function submitAgreement(
    int $agreementId,
    int $userId
): array {
    $db = Database::connect();
    $ownsTransaction = !$db->inTransaction();

    if ($ownsTransaction) {
        $db->beginTransaction();
    }

    try {
        $existing =
            $this->agreementRepo->findById(
                $agreementId
            );

        if (!$existing) {
            if (
                $ownsTransaction
                && $db->inTransaction()
            ) {
                $db->rollBack();
            }

            return [
                'success' => false,
                'errors' => ['Agreement not found'],
            ];
        }

        if ($existing['status'] !== AgreementStatus::DRAFT) {
            if (
                $ownsTransaction
                && $db->inTransaction()
            ) {
                $db->rollBack();
            }

            return [
                'success' => false,
                'errors' => [
                    'Only a DRAFT Agreement may be submitted',
                ],
            ];
        }

        if ((int) $existing['created_by'] !== $userId) {
            if (
                $ownsTransaction
                && $db->inTransaction()
            ) {
                $db->rollBack();
            }

            return [
                'success' => false,
                'errors' => [
                    'Only the original Agreement creator may submit this Agreement',
                ],
            ];
        }

        $workflow =
            $this->approvalService
                ->startAgreementWorkflow(
                    $agreementId,
                    $userId
                );

        $this->agreementRepo->changeStatus(
            $agreementId,
            AgreementStatus::UNDER_REVIEW
        );

        $updated =
            $this->agreementRepo->findById(
                $agreementId
            );

        $versions =
            $this->agreementVersionRepo
                ->findByAgreement($agreementId);

        $this->agreementVersionRepo->create(
            $agreementId,
            [
                'version_number' =>
                    count($versions) + 1,
                'change_summary' =>
                    'Agreement submitted for review',
                'agreement_snapshot' => $updated,
                'created_by' => $userId,
            ]
        );

        $this->auditService->write(
            'agreements',
            $agreementId,
            AuditAction::UPDATE,
            $userId,
            $existing,
            $updated
        );

        if ($ownsTransaction) {
            $db->commit();
        }

        return [
            'success' => true,
            'workflow_instance_id' =>
                $workflow['workflow_instance_id'],
            'current_step_key' =>
                $workflow['current_step_key'],
        ];
    } catch (DomainException $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        return [
            'success' => false,
            'errors' => [
                $exception->getMessage(),
            ],
        ];
    } catch (Throwable $exception) {
        if (
            $ownsTransaction
            && $db->inTransaction()
        ) {
            $db->rollBack();
        }

        throw $exception;
    }
}

    public function deleteAgreement(int $agreementId, int $userId): void {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $existing = $this->agreementRepo->findById($agreementId);
            if (!$existing) {
                $db->rollBack();
                return;
            }
            $this->agreementRepo->delete($agreementId);
            $this->auditService->write('agreements', $agreementId, AuditAction::DELETE, $userId, $existing, ['deleted' => true]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function uploadDocument(int $agreementId, array $data): array {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $documentId = $this->agreementDocumentRepo->create($agreementId, [
                'file_name' => $data['file_name'],
                'file_path' => $data['file_path'],
                'document_type' => $data['document_type'] ?? 'GENERAL',
                'uploaded_by' => $data['uploaded_by'],
            ]);
            $this->auditService->write('agreement_documents', $documentId, AuditAction::INSERT, $data['uploaded_by'] ?? null, null, ['agreement_id' => $agreementId]);
            $db->commit();
            return ['success' => true, 'document_id' => $documentId];
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function listDocuments(int $agreementId): array {
        return $this->agreementDocumentRepo->findByAgreement($agreementId);
    }

    public function findVersions(int $agreementId): array {
        return $this->agreementVersionRepo->findByAgreement($agreementId);
    }

    public function findVersion(int $agreementId, int $versionNumber): ?array {
        return $this->agreementVersionRepo->findByAgreementAndVersion($agreementId, $versionNumber);
    }

    public function deleteDocument(int $documentId, int $userId): bool {
        $document = $this->agreementDocumentRepo->findById($documentId);
        if (!$document) {
            return false;
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $this->agreementDocumentRepo->delete($documentId);
            $this->auditService->write('agreement_documents', $documentId, AuditAction::DELETE, $userId, $document, ['deleted' => true]);
            $db->commit();
            return true;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
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
