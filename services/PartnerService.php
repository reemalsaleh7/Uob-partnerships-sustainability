<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/PartnerRepository.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../helpers/AuditAction.php';
require_once __DIR__ . '/../config/database.php';

class PartnerService
{
    private PartnerRepository $partnerRepository;
    private AuditService $auditService;

    public function __construct()
    {
        $this->partnerRepository = new PartnerRepository();
        $this->auditService = new AuditService();
    }

    public function findActive(): array
    {
        return $this->partnerRepository->findActive();
    }

    public function create(array $data, int $userId): array
    {
        $organizationName = trim((string) ($data['organization_name'] ?? ''));
        $partnerType = trim((string) ($data['partner_type'] ?? ''));
        $country = $this->nullableString($data['country'] ?? null);
        $website = $this->nullableString($data['website'] ?? null);
        $profile = $this->nullableString($data['profile'] ?? null);
        $errors = [];

        if ($organizationName === '') {
            $errors[] = 'Organization name is required';
        } elseif ($this->length($organizationName) > 255) {
            $errors[] = 'Organization name must not exceed 255 characters';
        }
        if ($partnerType === '') {
            $errors[] = 'Partner type is required';
        } elseif ($this->length($partnerType) > 100) {
            $errors[] = 'Partner type must not exceed 100 characters';
        }
        if ($country === null) {
            $errors[] = 'Partner country is required';
        } elseif ($this->length($country) > 100) {
            $errors[] = 'Partner country must not exceed 100 characters';
        }
        if ($website !== null) {
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

        $duplicate = $this->partnerRepository->findActiveDuplicate(
            $organizationName,
            $country
        );
        if ($duplicate !== null) {
            $duplicate['already_existed'] = true;
            return $duplicate;
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $partner = $this->partnerRepository->create([
                'organization_name' => $organizationName,
                'partner_type' => $partnerType,
                'country' => $country,
                'website' => $website,
                'profile' => $profile,
            ]);
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

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
