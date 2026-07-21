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
                country
            FROM partners
            WHERE is_active = TRUE
            ORDER BY organization_name, partner_id
        ');

        return $statement->fetchAll();
    }
}
