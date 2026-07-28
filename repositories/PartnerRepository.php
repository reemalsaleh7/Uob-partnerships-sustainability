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

        return $statement->fetchAll();
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
            'SELECT partner_id, country
             FROM partners
             WHERE is_active = TRUE
               AND partner_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        return $statement->fetchAll();
    }

    public function findActiveDuplicate(
        string $organizationName,
        ?string $country
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
            ORDER BY partner_id
            LIMIT 1
        ');
        $statement->execute([
            'organization_name' => $organizationName,
            'country_empty' => $country,
            'country_match' => $country,
        ]);
        $partner = $statement->fetch();

        return $partner ?: null;
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

        return $partner;
    }
}
