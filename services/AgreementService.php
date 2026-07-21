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
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/DocumentStorageService.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
class AgreementService {
    private AgreementRepository $agreementRepo;
    private ApprovalService $approvalService;
    private AgreementVersionRepository $agreementVersionRepo;
    private AgreementDocumentRepository $agreementDocumentRepo;
    private AuditRepository $auditRepo;
    private AuditService $auditService;
    private PermissionService $permissionService;
    private WorkflowRepository $workflowRepo;
    private DocumentStorageService $documentStorage;

    public function __construct() {
        $this->agreementRepo = new AgreementRepository();
        $this->agreementVersionRepo = new AgreementVersionRepository();
        $this->agreementDocumentRepo = new AgreementDocumentRepository();
        $this->auditRepo = new AuditRepository();
        $this->auditService = new AuditService();
        $this->approvalService = new ApprovalService();
        $this->permissionService = new PermissionService();
        $this->workflowRepo = new WorkflowRepository();
        $this->documentStorage = new DocumentStorageService();
    }

    public function createAgreement(array $data): array {
        $errors = AgreementValidator::validateCreate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $content = $this->normalizeAgreementContent($data);
            $content['created_by'] = $data['created_by'];
            $content['status'] = AgreementStatus::DRAFT;
            $agreementId = $this->agreementRepo->create($content);
            $this->replaceAgreementCollections($agreementId, $data);
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
        $changeSummary = trim((string) ($data['change_summary'] ?? ''));
        $changeSummaryLength = function_exists('mb_strlen')
            ? mb_strlen($changeSummary, 'UTF-8')
            : strlen($changeSummary);
        if ($changeSummary === '') {
            $errors[] = 'Explain why these Agreement changes were made';
        } elseif ($changeSummaryLength > 1000) {
            $errors[] = 'Change reason must not exceed 1000 characters';
        }
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

            if (!AgreementStatus::isEditable((string) $existing['status'])) {
                $db->rollBack();

                return [
                    'success' => false,
                    'errors' => [
                        'Only a DRAFT or REVISION_REQUIRED Agreement may be edited',
                    ],
                ];
            }

            $this->agreementRepo->update(
                $agreementId,
                $this->normalizeAgreementContent($data)
            );
            $this->replaceAgreementCollections($agreementId, $data);
            $snapshot = $this->agreementRepo->findById($agreementId);
            $nextVersion = $this->agreementVersionRepo->findByAgreement($agreementId);
            $this->agreementVersionRepo->create($agreementId, [
                'version_number' => count($nextVersion) + 1,
                'change_summary' => $changeSummary,
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

        $submissionErrors = AgreementValidator::validateForSubmission($existing);
        if (!empty($submissionErrors)) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['success' => false, 'errors' => $submissionErrors];
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

    public function resubmitAgreement(
        int $agreementId,
        int $userId,
        ?string $comments = null
    ): array {
        $existing = $this->agreementRepo->findById($agreementId);

        if (!$existing) {
            return [
                'success' => false,
                'errors' => ['Agreement not found'],
            ];
        }

        if ((int) $existing['created_by'] !== $userId) {
            return [
                'success' => false,
                'errors' => [
                    'Only the original Agreement creator may resubmit this Agreement',
                ],
            ];
        }

        if ($existing['status'] !== AgreementStatus::REVISION_REQUIRED) {
            return [
                'success' => false,
                'errors' => [
                    'Only a REVISION_REQUIRED Agreement may be resubmitted',
                ],
            ];
        }

        $submissionErrors = AgreementValidator::validateForSubmission($existing);
        if (!empty($submissionErrors)) {
            return ['success' => false, 'errors' => $submissionErrors];
        }

        $workflow = $this->workflowRepo->findActiveByEntity(
            'AGREEMENT',
            $agreementId
        );

        if (!$workflow) {
            return [
                'success' => false,
                'errors' => ['Active Agreement workflow not found'],
            ];
        }

        try {
            return $this->approvalService->resubmitAgreementAfterRedraft(
                (int) $workflow['workflow_instance_id'],
                $userId,
                $comments
            );
        } catch (DomainException|InvalidArgumentException $exception) {
            return [
                'success' => false,
                'errors' => [$exception->getMessage()],
            ];
        }
    }

    public function deleteAgreement(int $agreementId, int $userId): void {
        $db = Database::connect();
        $db->beginTransaction();
        $documents = [];
        try {
            $existing = $this->agreementRepo->findById($agreementId);
            if (!$existing) {
                $db->rollBack();
                return;
            }
            $documents = $this->agreementDocumentRepo
                ->findByAgreement($agreementId);
            $this->agreementRepo->delete($agreementId);
            $this->auditService->write('agreements', $agreementId, AuditAction::DELETE, $userId, $existing, ['deleted' => true]);
            $db->commit();

            foreach ($documents as $document) {
                if (!empty($document['storage_key'])) {
                    $this->documentStorage->delete(
                        (string) $document['storage_key']
                    );
                }
            }
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function uploadDocument(
        int $agreementId,
        array $uploadedFile,
        string $documentType,
        int $userId
    ): array {
        $agreement = $this->findByIdForUser($agreementId, $userId);

        if (!$agreement) {
            throw new DomainException('Agreement not found');
        }

        if (!$this->canUploadDocument($agreement, $userId)) {
            throw new DomainException(
                'Documents may be uploaded only by the creator while the Agreement is editable or by its currently assigned reviewer'
            );
        }

        $normalizedType = strtoupper(trim($documentType));
        $allowedTypes = [
            'AGREEMENT_DRAFT',
            'SUPPORTING',
            'LEGAL_REVIEW',
            'FINANCE_REVIEW',
            'SIGNED_AGREEMENT',
            'ANNUAL_REPORT',
            'OTHER',
        ];

        if (!in_array($normalizedType, $allowedTypes, true)) {
            throw new InvalidArgumentException(
                'Select a valid document type'
            );
        }

        if (
            $normalizedType === 'SIGNED_AGREEMENT'
            && (
                $agreement['status'] !== AgreementStatus::APPROVED
                || (
                    !$this->isSystemAdministrator($userId)
                    && (
                        (int) $agreement['created_by'] !== $userId
                        || !$this->permissionService->hasPermission(
                            $userId,
                            'MANAGE_AGREEMENT_OPERATIONS'
                        )
                    )
                )
            )
        ) {
            throw new DomainException(
                'Only the Agreement creator or a system administrator may upload the final signed Agreement after approval'
            );
        }

        if (
            in_array($agreement['status'], ['ACTIVE', 'EXPIRED'], true)
            && $normalizedType !== 'ANNUAL_REPORT'
        ) {
            throw new DomainException(
                'Operational Agreements accept only annual-report documents through this upload panel'
            );
        }

        $latestVersion = $this->agreementVersionRepo
            ->findLatest($agreementId);

        if (!$latestVersion) {
            throw new DomainException(
                'The Agreement has no version to associate with this document'
            );
        }

        $storedFile = $this->documentStorage->store($uploadedFile);
        $db = Database::connect();

        try {
            $db->beginTransaction();
            $documentId = $this->agreementDocumentRepo->create(
                $agreementId,
                [
                    'agreement_version_id' =>
                        (int) $latestVersion['version_id'],
                    'file_name' => $storedFile['file_name'],
                    'storage_key' => $storedFile['storage_key'],
                    'mime_type' => $storedFile['mime_type'],
                    'file_size_bytes' =>
                        $storedFile['file_size_bytes'],
                    'sha256_checksum' =>
                        $storedFile['sha256_checksum'],
                    'document_type' => $normalizedType,
                    'uploaded_by' => $userId,
                ]
            );
            $this->auditService->write(
                'agreement_documents',
                $documentId,
                AuditAction::INSERT,
                $userId,
                null,
                [
                    'agreement_id' => $agreementId,
                    'agreement_version_id' =>
                        (int) $latestVersion['version_id'],
                    'file_name' => $storedFile['file_name'],
                    'document_type' => $normalizedType,
                    'mime_type' => $storedFile['mime_type'],
                    'file_size_bytes' =>
                        $storedFile['file_size_bytes'],
                    'sha256_checksum' =>
                        $storedFile['sha256_checksum'],
                ]
            );
            $db->commit();

            return [
                'success' => true,
                'document_id' => $documentId,
            ];
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $this->documentStorage->delete(
                (string) $storedFile['storage_key']
            );

            throw $exception;
        }
    }

    public function listDocuments(
        int $agreementId,
        int $userId
    ): array {
        $agreement = $this->findByIdForUser($agreementId, $userId);

        if (!$agreement) {
            throw new DomainException('Agreement not found');
        }

        $canUpload = $this->canUploadDocument($agreement, $userId);
        $documents = array_map(
            function (array $document) use ($agreement, $userId): array {
                $document['available'] =
                    ($document['has_stored_file'] ?? false)
                    && $this->documentStorage->absolutePath(
                        (string) ($document['storage_key'] ?? '')
                    ) !== null;
                $document['can_delete'] = $this->canDeleteDocument(
                    $agreement,
                    $document,
                    $userId
                );
                unset(
                    $document['storage_key'],
                    $document['file_path'],
                    $document['has_stored_file']
                );

                return $document;
            },
            $this->agreementDocumentRepo
                ->findByAgreement($agreementId)
        );

        return [
            'documents' => $documents,
            'can_upload' => $canUpload,
            'agreement_status' => $agreement['status'],
            'constraints' => [
                'max_file_size_bytes' =>
                    DocumentStorageService::MAX_FILE_SIZE_BYTES,
                'allowed_extensions' =>
                    DocumentStorageService::allowedExtensions(),
            ],
        ];
    }

    public function findVersions(int $agreementId): array {
        return $this->agreementVersionRepo->findByAgreement($agreementId);
    }

    public function findVersion(int $agreementId, int $versionNumber): ?array {
        return $this->agreementVersionRepo->findByAgreementAndVersion($agreementId, $versionNumber);
    }

    public function downloadDocument(
        int $documentId,
        int $userId
    ): ?array {
        $document = $this->agreementDocumentRepo->findById($documentId);

        if (!$document) {
            return null;
        }

        if (!$this->findByIdForUser(
            (int) $document['agreement_id'],
            $userId
        )) {
            return null;
        }

        $absolutePath = $this->documentStorage->absolutePath(
            (string) ($document['storage_key'] ?? '')
        );

        if ($absolutePath === null) {
            return null;
        }

        $expectedChecksum = (string) (
            $document['sha256_checksum'] ?? ''
        );
        $actualChecksum = hash_file('sha256', $absolutePath);

        if (
            $expectedChecksum !== ''
            && (
                $actualChecksum === false
                || !hash_equals(
                    $expectedChecksum,
                    $actualChecksum
                )
            )
        ) {
            throw new RuntimeException(
                'Document integrity verification failed'
            );
        }

        $document['absolute_path'] = $absolutePath;
        unset($document['storage_key'], $document['file_path']);

        return $document;
    }

    public function deleteDocument(int $documentId, int $userId): bool {
        $document = $this->agreementDocumentRepo->findById($documentId);

        if (!$document) {
            return false;
        }

        $agreement = $this->findByIdForUser(
            (int) $document['agreement_id'],
            $userId
        );

        if (!$agreement) {
            return false;
        }

        if (!$this->canDeleteDocument($agreement, $document, $userId)) {
            throw new DomainException(
                'You may delete only your own document while you can still upload to this Agreement'
            );
        }

        if ($this->agreementDocumentRepo->isFinalizedSigningDocument($documentId)) {
            throw new DomainException(
                'The finalized signed Agreement document cannot be deleted'
            );
        }

        if ($this->agreementDocumentRepo->isPerformanceReportDocument($documentId)) {
            throw new DomainException(
                'A document linked to a performance report cannot be deleted'
            );
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $this->agreementDocumentRepo->delete($documentId);
            $this->auditService->write('agreement_documents', $documentId, AuditAction::DELETE, $userId, $document, ['deleted' => true]);
            $db->commit();

            if (!empty($document['storage_key'])) {
                $this->documentStorage->delete(
                    (string) $document['storage_key']
                );
            }

            return true;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function findById(int $agreementId): ?array {
        return $this->agreementRepo->findById($agreementId);
    }

    public function findByIdForUser(int $agreementId, int $userId): ?array {
        return $this->agreementRepo->findByIdVisibleToUser(
            $agreementId,
            $userId,
            $this->isSystemAdministrator($userId)
        );
    }

    public function findAll(int $userId): array {
        return $this->agreementRepo->findVisibleToUser(
            $userId,
            $this->isSystemAdministrator($userId)
        );
    }

    public function workflowTimelineForUser(
        int $agreementId,
        int $userId
    ): ?array {
        if ($this->findByIdForUser($agreementId, $userId) === null) {
            return null;
        }

        $instance = $this->workflowRepo->findLatestByEntity(
            'AGREEMENT',
            $agreementId
        );

        if ($instance === null) {
            return [
                'agreement_id' => $agreementId,
                'workflow' => null,
                'steps' => [],
                'history' => [],
            ];
        }

        $instanceId = (int) $instance['workflow_instance_id'];
        return [
            'agreement_id' => $agreementId,
            'workflow' => $instance,
            'steps' => $this->workflowRepo->findTimelineSteps($instanceId),
            'history' => $this->workflowRepo->findTimelineHistory($instanceId),
        ];
    }

    public function findByStatus(string $status): array {
        return $this->agreementRepo->findByStatus($status);
    }

    private function canUploadDocument(
        array $agreement,
        int $userId
    ): bool {
        if ($this->isSystemAdministrator($userId)) {
            return true;
        }

        $isCreator = (int) $agreement['created_by'] === $userId;

        if (
            $isCreator
            && AgreementStatus::isEditable(
                (string) $agreement['status']
            )
        ) {
            return true;
        }

        if (
            $isCreator
            && $agreement['status'] === AgreementStatus::APPROVED
            && $this->permissionService->hasPermission(
                $userId,
                'MANAGE_AGREEMENT_OPERATIONS'
            )
        ) {
            return true;
        }

        if (
            $isCreator
            && in_array($agreement['status'], ['ACTIVE', 'EXPIRED'], true)
            && $this->permissionService->hasPermission(
                $userId,
                'MANAGE_AGREEMENT_REPORTS'
            )
        ) {
            return true;
        }

        return $agreement['status'] === AgreementStatus::UNDER_REVIEW
            && $this->workflowRepo->hasActiveAssignmentForEntity(
                'AGREEMENT',
                (int) $agreement['agreement_id'],
                $userId
            );
    }

    private function canDeleteDocument(
        array $agreement,
        array $document,
        int $userId
    ): bool {
        if ($this->isSystemAdministrator($userId)) {
            return true;
        }

        return (int) ($document['uploaded_by'] ?? 0) === $userId
            && $this->canUploadDocument($agreement, $userId);
    }

    private function isSystemAdministrator(int $userId): bool {
        return in_array(
            'System Administrator',
            $this->permissionService->getRoleNames($userId),
            true
        );
    }

    private function replaceAgreementCollections(int $agreementId, array $data): void {
        if (array_key_exists('partner_ids', $data) || array_key_exists('partner_id', $data)) {
            $partnerIds = is_array($data['partner_ids'] ?? null)
                ? $data['partner_ids']
                : [$data['partner_id'] ?? null];
            $this->agreementRepo->replacePartners($agreementId, $partnerIds);
        }
        if (array_key_exists('sdgs', $data)) {
            $this->agreementRepo->replaceSdgs($agreementId, $data['sdgs']);
        }
        if (array_key_exists('rankings', $data)) {
            $this->agreementRepo->replaceRankings($agreementId, $data['rankings']);
        }
        if (array_key_exists('contacts', $data)) {
            $this->agreementRepo->replaceContacts($agreementId, $data['contacts']);
        }
        if (array_key_exists('executive_programs', $data)) {
            $this->agreementRepo->replaceExecutivePrograms(
                $agreementId,
                $data['executive_programs']
            );
        }
        if (array_key_exists('metrics', $data)) {
            $this->agreementRepo->replaceMetrics($agreementId, $data['metrics']);
        }
    }

    private function normalizeAgreementContent(array $data): array {
        $content = $data;
        foreach ($content as $field => $value) {
            if (is_string($value)) {
                $content[$field] = trim($value);
            }
        }
        return $content;
    }
}
