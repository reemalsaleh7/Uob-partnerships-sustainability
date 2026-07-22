<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../repositories/AgreementOperationRepository.php';
require_once __DIR__ . '/../repositories/AgreementRepository.php';
require_once __DIR__ . '/../repositories/AuditRepository.php';
require_once __DIR__ . '/../services/AgreementService.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../services/DocumentStorageService.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../helpers/AuditAction.php';

class AgreementOperationService
{
    private AgreementOperationRepository $operations;
    private AgreementRepository $agreements;
    private AgreementService $agreementService;
    private AuditService $audit;
    private PermissionService $permissions;
    private DocumentStorageService $storage;

    public function __construct()
    {
        $this->operations = new AgreementOperationRepository();
        $this->agreements = new AgreementRepository();
        $this->agreementService = new AgreementService();
        $this->audit = new AuditService();
        $this->permissions = new PermissionService();
        $this->storage = new DocumentStorageService();
    }

    public function summary(int $agreementId, int $userId): ?array
    {
        $agreement = $this->agreementService->findByIdForUser(
            $agreementId,
            $userId
        );
        if ($agreement === null) {
            return null;
        }

        $record = $this->operations->findSigningRecord($agreementId);
        $canManage = $this->canManage($agreement, $userId);

        return [
            'agreement_id' => $agreementId,
            'agreement_status' => $agreement['status'],
            'operational_state' => $this->operationalState($agreement, $record),
            'signing_record' => $record,
            'status_events' => $this->operations->findStatusEvents($agreementId),
            'can_finalize' => $canManage
                && $agreement['status'] === 'APPROVED'
                && $record === null,
            'eligible_documents' => $canManage && $record === null
                ? $this->operations->findEligibleSignedDocuments($agreementId)
                : [],
        ];
    }

    public function finalizeSigning(
        int $agreementId,
        int $userId,
        array $input
    ): array {
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $locked = $this->operations->lockAgreement($agreementId);
            if ($locked === null) {
                throw new DomainException('Agreement not found');
            }
            if (!$this->canManage($locked, $userId)) {
                throw new DomainException(
                    'Only the Agreement creator or a system administrator may finalize signing'
                );
            }
            if ($locked['status'] !== 'APPROVED') {
                throw new DomainException(
                    'Only an APPROVED Agreement may be finalized for signing'
                );
            }
            if ($this->operations->findSigningRecord($agreementId) !== null) {
                throw new DomainException(
                    'The Agreement already has a finalized signing record'
                );
            }

            $agreement = $this->agreements->findById($agreementId);
            if ($agreement === null) {
                throw new DomainException('Agreement not found');
            }
            $data = $this->validateSigningInput($agreement, $userId, $input);
            $document = $this->operations->findDocumentForAgreement(
                $agreementId,
                $data['signed_document_id']
            );
            if (
                $document === null
                || ($document['document_type'] ?? null) !== 'SIGNED_AGREEMENT'
            ) {
                throw new DomainException(
                    'Choose a securely uploaded document with type SIGNED_AGREEMENT'
                );
            }
            $this->verifyDocument($document);

            $recordId = $this->operations->createSigningRecord(
                $agreementId,
                $data
            );
            $record = $this->operations->findSigningRecord($agreementId);
            $this->audit->write(
                'agreement_signing_records',
                $recordId,
                AuditAction::INSERT,
                $userId,
                null,
                $record
            );

            $asOf = new DateTimeImmutable('today');
            $transitions = $this->applyDueTransitions(
                $agreementId,
                $asOf,
                $userId
            );

            if ($ownsTransaction) {
                $db->commit();
            }

            $current = $this->agreements->findById($agreementId);
            return [
                'success' => true,
                'signing_record_id' => $recordId,
                'status' => $current['status'] ?? $locked['status'],
                'operational_state' => $this->operationalState(
                    $current ?? $locked,
                    $record
                ),
                'transitions' => $transitions,
            ];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    public function previewTransitions(DateTimeImmutable $asOf): array
    {
        return $this->operations->findTransitionCandidates($asOf);
    }

    public function synchronize(
        DateTimeImmutable $asOf,
        ?int $performedBy = null
    ): array {
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $results = [];
            foreach ($this->operations->findTransitionCandidates($asOf) as $candidate) {
                $agreementId = (int) $candidate['agreement_id'];
                $this->operations->lockAgreement($agreementId);
                $results[] = [
                    'agreement_id' => $agreementId,
                    'title' => $candidate['title'],
                    'transitions' => $this->applyDueTransitions(
                        $agreementId,
                        $asOf,
                        $performedBy
                    ),
                ];
            }
            if ($ownsTransaction) {
                $db->commit();
            }
            return $results;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function applyDueTransitions(
        int $agreementId,
        DateTimeImmutable $asOf,
        ?int $performedBy
    ): array {
        $record = $this->operations->findSigningRecord($agreementId);
        $agreement = $this->agreements->findById($agreementId);
        if ($record === null || $agreement === null) {
            return [];
        }

        $transitions = [];
        $effective = new DateTimeImmutable((string) $record['effective_date']);
        $expiry = new DateTimeImmutable((string) $record['expiry_date']);

        if ($agreement['status'] === 'APPROVED' && $effective <= $asOf) {
            $before = $agreement;
            if ($this->operations->transitionStatus(
                $agreementId,
                'APPROVED',
                'ACTIVE',
                $asOf,
                'Finalized Agreement reached its effective date',
                $performedBy
            )) {
                $agreement = $this->agreements->findById($agreementId) ?? $agreement;
                $this->audit->write(
                    'agreements',
                    $agreementId,
                    AuditAction::UPDATE,
                    $performedBy,
                    $before,
                    $agreement
                );
                $transitions[] = ['from' => 'APPROVED', 'to' => 'ACTIVE'];
            }
        }

        if ($agreement['status'] === 'ACTIVE' && $expiry < $asOf) {
            $before = $agreement;
            if ($this->operations->transitionStatus(
                $agreementId,
                'ACTIVE',
                'EXPIRED',
                $asOf,
                'Finalized Agreement passed its expiry date',
                $performedBy
            )) {
                $agreement = $this->agreements->findById($agreementId) ?? $agreement;
                $this->audit->write(
                    'agreements',
                    $agreementId,
                    AuditAction::UPDATE,
                    $performedBy,
                    $before,
                    $agreement
                );
                $transitions[] = ['from' => 'ACTIVE', 'to' => 'EXPIRED'];
            }
        }

        return $transitions;
    }

    private function validateSigningInput(
        array $agreement,
        int $userId,
        array $input
    ): array {
        $signingDate = $this->date($input['signing_date'] ?? null, 'Signing date');
        $effectiveDate = $this->date($input['effective_date'] ?? null, 'Effective date');
        $expiryDate = $this->date($input['expiry_date'] ?? null, 'Expiry date');
        if ($expiryDate < $effectiveDate) {
            throw new InvalidArgumentException(
                'Expiry date cannot be earlier than effective date'
            );
        }

        $documentId = (int) ($input['signed_document_id'] ?? 0);
        if ($documentId <= 0) {
            throw new InvalidArgumentException('A signed Agreement document is required');
        }

        $signatories = $input['signatories'] ?? null;
        if (!is_array($signatories) || count($signatories) < 2 || count($signatories) > 20) {
            throw new InvalidArgumentException(
                'Provide between 2 and 20 finalized signatories'
            );
        }
        $partnerIds = array_map('intval', $agreement['partner_ids'] ?? []);
        $normalized = [];
        $partyCounts = ['UOB' => 0, 'PARTNER' => 0];
        foreach (array_values($signatories) as $index => $signatory) {
            if (!is_array($signatory)) {
                throw new InvalidArgumentException('Each signatory must be a record');
            }
            $partyType = strtoupper(trim((string) ($signatory['party_type'] ?? '')));
            if (!isset($partyCounts[$partyType])) {
                throw new InvalidArgumentException('Signatory party must be UOB or PARTNER');
            }
            $fullName = trim((string) ($signatory['full_name'] ?? ''));
            $jobTitle = trim((string) ($signatory['job_title'] ?? ''));
            $organization = trim((string) ($signatory['organization_name'] ?? ''));
            if ($fullName === '' || $jobTitle === '' || $organization === '') {
                throw new InvalidArgumentException(
                    'Every signatory requires a name, job title, and organization'
                );
            }
            $partnerId = $partyType === 'PARTNER'
                ? (int) ($signatory['partner_id'] ?? 0)
                : null;
            if (
                $partyType === 'PARTNER'
                && ($partnerId <= 0 || !in_array($partnerId, $partnerIds, true))
            ) {
                throw new InvalidArgumentException(
                    'Each partner signatory must identify a partner on this Agreement'
                );
            }
            $normalized[] = [
                'display_order' => $index + 1,
                'party_type' => $partyType,
                'partner_id' => $partnerId,
                'full_name' => $fullName,
                'job_title' => $jobTitle,
                'organization_name' => $organization,
            ];
            $partyCounts[$partyType]++;
        }
        if ($partyCounts['UOB'] < 1 || $partyCounts['PARTNER'] < 1) {
            throw new InvalidArgumentException(
                'At least one UOB and one partner signatory are required'
            );
        }

        $url = $this->nullable($input['public_announcement_url'] ?? null);
        if ($url !== null) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (
                filter_var($url, FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)
            ) {
                throw new InvalidArgumentException(
                    'Public announcement URL must use HTTP or HTTPS'
                );
            }
        }

        return [
            'signed_document_id' => $documentId,
            'signing_date' => $signingDate->format('Y-m-d'),
            'effective_date' => $effectiveDate->format('Y-m-d'),
            'expiry_date' => $expiryDate->format('Y-m-d'),
            'venue' => $this->nullable($input['venue'] ?? null),
            'public_announcement_url' => $url,
            'ceremony_notes' => $this->nullable($input['ceremony_notes'] ?? null),
            'signatories' => $normalized,
            'finalized_by' => $userId,
        ];
    }

    private function verifyDocument(array $document): void
    {
        $absolutePath = $this->storage->absolutePath(
            (string) ($document['storage_key'] ?? '')
        );
        if ($absolutePath === null) {
            throw new DomainException('The signed Agreement file is unavailable');
        }
        $expected = (string) ($document['sha256_checksum'] ?? '');
        $actual = hash_file('sha256', $absolutePath);
        if (
            $expected === ''
            || $actual === false
            || !hash_equals($expected, $actual)
        ) {
            throw new RuntimeException(
                'Signed Agreement integrity verification failed'
            );
        }
    }

    private function canManage(array $agreement, int $userId): bool
    {
        if ($this->isSystemAdministrator($userId)) {
            return true;
        }
        return (int) ($agreement['created_by'] ?? 0) === $userId
            && $this->permissions->hasPermission(
                $userId,
                'MANAGE_AGREEMENT_OPERATIONS'
            );
    }

    private function isSystemAdministrator(int $userId): bool
    {
        return in_array(
            'System Administrator',
            $this->permissions->getRoleNames($userId),
            true
        );
    }

    private function operationalState(array $agreement, ?array $record): string
    {
        if ($record === null) {
            if (($agreement['status'] ?? null) === 'ACTIVE') {
                return 'ACTIVE_LEGACY';
            }
            return 'NOT_FINALIZED';
        }
        if ($agreement['status'] === 'APPROVED') {
            return 'SCHEDULED';
        }
        return (string) $agreement['status'];
    }

    private function date(mixed $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$label} is required and must be valid");
        }
        return $date;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
