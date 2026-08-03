<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class PartnerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findActive(): array
    {
        $statement = $this->db->query('
            SELECT
                partner_id,
                organization_name,
                partner_type,
                country,
                city,
                profile,
                website,
                logo_url,
                latitude,
                longitude
            FROM partners
            WHERE is_active = TRUE
            ORDER BY organization_name, partner_id
        ');

        return array_map(
            [self::class, 'normalizePartnerRow'],
            $statement->fetchAll()
        );
    }

    public function findActiveByIds(array $partnerIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $partnerIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($ids), '?')
        );
        $statement = $this->db->prepare(
            'SELECT partner_id, organization_name, partner_type, country,
                    city, profile, website, logo_url, latitude, longitude
             FROM partners
             WHERE is_active = TRUE
               AND partner_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        return array_map(
            [self::class, 'normalizePartnerRow'],
            $statement->fetchAll()
        );
    }

    public function findActiveDuplicate(
        string $organizationName,
        ?string $country,
        ?int $excludePartnerId = null
    ): ?array {
        $statement = $this->db->prepare('
            SELECT
                partner_id,
                organization_name,
                partner_type,
                country,
                city,
                profile,
                website,
                logo_url,
                latitude,
                longitude
            FROM partners
            WHERE is_active = TRUE
              AND LOWER(TRIM(organization_name)) = LOWER(TRIM(:organization_name))
              AND (
                    NULLIF(TRIM(:country_empty), \'\') IS NULL
                    OR LOWER(COALESCE(country, \'\')) = LOWER(TRIM(:country_match))
              )
              AND (
                    CAST(:exclude_partner_id_empty AS BIGINT) IS NULL
                    OR partner_id <> :exclude_partner_id_match
              )
            ORDER BY partner_id
            LIMIT 1
        ');
        $statement->execute([
            'organization_name' => $organizationName,
            'country_empty' => $country,
            'country_match' => $country,
            'exclude_partner_id_empty' => $excludePartnerId,
            'exclude_partner_id_match' => $excludePartnerId,
        ]);
        $partner = $statement->fetch();

        return $partner ? self::normalizePartnerRow($partner) : null;
    }

    public function create(array $data): array
    {
        $statement = $this->db->prepare('
            INSERT INTO partners (
                organization_name,
                partner_type,
                country,
                website,
                profile,
                is_active
            ) VALUES (
                :organization_name,
                :partner_type,
                :country,
                :website,
                :profile,
                TRUE
            )
            RETURNING
                partner_id,
                organization_name,
                partner_type,
                country,
                city,
                profile,
                website,
                logo_url,
                latitude,
                longitude
        ');
        $statement->execute([
            'organization_name' => $data['organization_name'],
            'partner_type' => $data['partner_type'],
            'country' => $data['country'],
            'website' => $data['website'],
            'profile' => $data['profile'],
        ]);

        $partner = $statement->fetch();
        if (!$partner) {
            throw new RuntimeException('The partner could not be created');
        }

        return self::normalizePartnerRow($partner);
    }

    public function findActiveById(int $partnerId): ?array
    {
        $statement = $this->db->prepare('
            SELECT
                partner_id,
                organization_name,
                partner_type,
                country,
                city,
                profile,
                website,
                logo_url,
                latitude,
                longitude
            FROM partners
            WHERE partner_id = :partner_id
              AND is_active = TRUE
            LIMIT 1
        ');
        $statement->execute(['partner_id' => $partnerId]);
        $partner = $statement->fetch();

        return $partner ? self::normalizePartnerRow($partner) : null;
    }

    public function findAgreementContext(
        int $partnerId,
        ?int $excludeAgreementId = null
    ): array {
        $sql = '
            SELECT
                a.agreement_id,
                a.title,
                a.status,
                a.created_by,
                a.start_date,
                a.end_date,
                a.updated_at
            FROM agreement_partners ap
            JOIN agreements a ON a.agreement_id = ap.agreement_id
            WHERE ap.partner_id = :partner_id
        ';
        $parameters = ['partner_id' => $partnerId];
        if ($excludeAgreementId !== null) {
            $sql .= ' AND a.agreement_id <> :exclude_agreement_id';
            $parameters['exclude_agreement_id'] = $excludeAgreementId;
        }
        $sql .= "
            ORDER BY
                CASE a.status
                    WHEN 'ACTIVE' THEN 1
                    WHEN 'APPROVED' THEN 2
                    WHEN 'UNDER_REVIEW' THEN 3
                    WHEN 'REVISION_REQUIRED' THEN 4
                    WHEN 'DRAFT' THEN 5
                    WHEN 'EXPIRED' THEN 6
                    ELSE 7
                END,
                a.updated_at DESC,
                a.agreement_id DESC
        ";
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function update(int $partnerId, array $data): array
    {
        $statement = $this->db->prepare('
            UPDATE partners
            SET
                organization_name = :organization_name,
                partner_type = :partner_type,
                country = :country,
                website = :website,
                profile = :profile,
                updated_at = NOW()
            WHERE partner_id = :partner_id
              AND is_active = TRUE
            RETURNING
                partner_id,
                organization_name,
                partner_type,
                country,
                city,
                profile,
                website,
                logo_url,
                latitude,
                longitude
        ');
        $statement->execute([
            'partner_id' => $partnerId,
            'organization_name' => $data['organization_name'],
            'partner_type' => $data['partner_type'],
            'country' => $data['country'],
            'website' => $data['website'],
            'profile' => $data['profile'],
        ]);
        $partner = $statement->fetch();
        if (!$partner) {
            throw new DomainException('Partner organization not found');
        }

        return self::normalizePartnerRow($partner);
    }

    private static function normalizePartnerRow(array $partner): array
    {
        $value = strtoupper(trim((string) ($partner['partner_type'] ?? '')));
        $compact = preg_replace('/[^A-Z0-9]+/', '_', $value);
        $aliases = [
            'PUBLIC' => 'PUBLIC_GOVERNMENT',
            'GOVERNMENT' => 'PUBLIC_GOVERNMENT',
            'GOVERNMENT_ORGANIZATION' => 'PUBLIC_GOVERNMENT',
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
        if (isset($aliases[$compact])) {
            $partner['partner_type'] = $aliases[$compact];
        }

        return $partner;
    }
}
