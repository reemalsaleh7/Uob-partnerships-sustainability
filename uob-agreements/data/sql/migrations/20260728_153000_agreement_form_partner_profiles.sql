ALTER TABLE partners
    ADD COLUMN IF NOT EXISTS profile TEXT;

CREATE INDEX IF NOT EXISTS ix_partners_active_name_country
    ON partners (
        LOWER(TRIM(organization_name)),
        LOWER(COALESCE(country, ''))
    )
    WHERE is_active = TRUE;
