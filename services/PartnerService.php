<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/PartnerRepository.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../helpers/AuditAction.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PartnerLookupService.php';

class PartnerService
{
    private const PARTNER_TYPES = [
        'PUBLIC_GOVERNMENT',
        'PRIVATE',
        'ACADEMIC',
        'NON_PROFIT',
    ];

    private PartnerRepository $partnerRepository;
    private AuditService $auditService;
    private PartnerLookupService $partnerLookupService;

    public function __construct()
    {
        $this->partnerRepository = new PartnerRepository();
        $this->auditService = new AuditService();
        $this->partnerLookupService = new PartnerLookupService();
    }

    public function findActive(): array
    {
        return $this->partnerRepository->findActive();
    }

    public function lookup(string $organizationName): array
    {
        return $this->partnerLookupService->lookup($organizationName);
    }

    public function agreementContext(
        int $partnerId,
        int $userId,
        ?int $excludeAgreementId = null
    ): array {
        if ($this->partnerRepository->findActiveById($partnerId) === null) {
            throw new DomainException('Partner organization not found');
        }

        $agreements = $this->partnerRepository->findAgreementContext(
            $partnerId,
            $excludeAgreementId
        );
        $blockingStatuses = [
            'DRAFT',
            'REVISION_REQUIRED',
            'UNDER_REVIEW',
            'APPROVED',
            'ACTIVE',
        ];
        $blocking = null;
        $expired = null;

        foreach ($agreements as $agreement) {
            $status = strtoupper((string) ($agreement['status'] ?? ''));
            if (
                $blocking === null
                && in_array($status, $blockingStatuses, true)
            ) {
                $isOwner =
                    (int) ($agreement['created_by'] ?? 0) === $userId;
                $canIdentify = $isOwner
                    || in_array($status, ['APPROVED', 'ACTIVE'], true);
                $blocking = [
                    'agreement_id' => $canIdentify
                        ? (int) $agreement['agreement_id']
                        : null,
                    'title' => $canIdentify
                        ? (string) $agreement['title']
                        : null,
                    'status' => $status,
                    'can_amend' => in_array(
                        $status,
                        ['APPROVED', 'ACTIVE'],
                        true
                    ),
                    'can_resume' => $isOwner
                        && in_array(
                            $status,
                            ['DRAFT', 'REVISION_REQUIRED'],
                            true
                        ),
                ];
            }
            if ($expired === null && $status === 'EXPIRED') {
                $expired = [
                    'agreement_id' => (int) $agreement['agreement_id'],
                    'title' => (string) $agreement['title'],
                    'status' => $status,
                    'start_date' => $agreement['start_date'] ?? null,
                    'end_date' => $agreement['end_date'] ?? null,
                ];
            }
        }

        return [
            'blocked' => $blocking !== null,
            'blocking_agreement' => $blocking,
            'expired_agreement' => $expired,
        ];
    }

    public function create(array $data, int $userId): array
    {
        $partnerData = $this->validatedData($data);

        $duplicate = $this->partnerRepository->findActiveDuplicate(
            $partnerData['organization_name'],
            $partnerData['country']
        );
        if ($duplicate !== null) {
            $duplicate['already_existed'] = true;
            return $duplicate;
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $partner = $this->partnerRepository->create($partnerData);
            $this->auditService->write(
                'partners',
                (int) $partner['partner_id'],
                AuditAction::INSERT,
                $userId,
                null,
                $partner
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
        $partner['already_existed'] = false;

        return $partner;
    }

    public function update(int $partnerId, array $data, int $userId): array
    {
        $existing = $this->partnerRepository->findActiveById($partnerId);
        if ($existing === null) {
            throw new DomainException('Partner organization not found');
        }

        $partnerData = $this->validatedData($data);
        $duplicate = $this->partnerRepository->findActiveDuplicate(
            $partnerData['organization_name'],
            $partnerData['country'],
            $partnerId
        );
        if ($duplicate !== null) {
            throw new InvalidArgumentException(
                'Another active partner already uses this organization name and country'
            );
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $partner = $this->partnerRepository->update(
                $partnerId,
                $partnerData
            );
            $this->auditService->write(
                'partners',
                $partnerId,
                AuditAction::UPDATE,
                $userId,
                $existing,
                $partner
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $partner;
    }

    private function validatedData(array $data): array
    {
        $organizationName = trim((string) ($data['organization_name'] ?? ''));
        $partnerType = $this->normalizePartnerType(
            (string) ($data['partner_type'] ?? '')
        );
        $country = $this->nullableString($data['country'] ?? null);
        $website = $this->nullableString($data['website'] ?? null);
        $profile = $this->nullableString($data['profile'] ?? null);
        $errors = [];

        if ($organizationName === '') {
            $errors[] = 'Organization name is required';
        } elseif ($this->length($organizationName) > 255) {
            $errors[] = 'Organization name must not exceed 255 characters';
        }
        if (!in_array($partnerType, self::PARTNER_TYPES, true)) {
            $errors[] = 'Partner type must be Public/government, Private, Academic, or Non-profit';
        }
        if ($country === null) {
            $errors[] = 'Partner country is required';
        } elseif ($this->length($country) > 100) {
            $errors[] = 'Partner country must not exceed 100 characters';
        }
        if ($website === null) {
            $errors[] = 'Partner website is required';
        } else {
            $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
            if (
                filter_var($website, FILTER_VALIDATE_URL) === false
                || !in_array($scheme, ['http', 'https'], true)
            ) {
                $errors[] = 'Enter a valid HTTP or HTTPS partner website URL';
            } elseif ($this->length($website) > 255) {
                $errors[] = 'Partner website must not exceed 255 characters';
            }
        }
        if ($profile !== null && $this->length($profile) > 4000) {
            $errors[] = 'Partner profile must not exceed 4000 characters';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }

        return [
            'organization_name' => $organizationName,
            'partner_type' => $partnerType,
            'country' => $country,
            'website' => $website,
            'profile' => $profile,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePartnerType(string $value): string
    {
        $normalized = preg_replace(
            '/[^A-Z0-9]+/',
            '_',
            strtoupper(trim($value))
        );
        $aliases = [
            'PUBLIC' => 'PUBLIC_GOVERNMENT',
            'GOVERNMENT' => 'PUBLIC_GOVERNMENT',
            'PUBLIC_GOVERNMENT' => 'PUBLIC_GOVERNMENT',
            'COMPANY' => 'PRIVATE',
            'PRIVATE' => 'PRIVATE',
            'UNIVERSITY' => 'ACADEMIC',
            'RESEARCH_CENTER' => 'ACADEMIC',
            'RESEARCH_CENTRE' => 'ACADEMIC',
            'ACADEMIC' => 'ACADEMIC',
            'NONPROFIT' => 'NON_PROFIT',
            'NON_PROFIT' => 'NON_PROFIT',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
