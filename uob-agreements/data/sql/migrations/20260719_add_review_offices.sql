-- Adds the offices required by the Agreement approval workflow
-- to databases created before those units were included.

BEGIN;

INSERT INTO organizational_units (
    name,
    code,
    unit_type,
    parent_unit_id,
    display_order,
    is_active
)
SELECT
    'Legal Office',
    'LEGAL',
    'OFFICE',
    uob.unit_id,
    4,
    TRUE
FROM organizational_units uob
WHERE uob.code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

INSERT INTO organizational_units (
    name,
    code,
    unit_type,
    parent_unit_id,
    display_order,
    is_active
)
SELECT
    'Financial Office',
    'FIN',
    'OFFICE',
    uob.unit_id,
    5,
    TRUE
FROM organizational_units uob
WHERE uob.code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM organizational_units
        WHERE code = 'LEGAL'
    ) THEN
        RAISE EXCEPTION 'Legal Office could not be created because UOB is missing';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM organizational_units
        WHERE code = 'FIN'
    ) THEN
        RAISE EXCEPTION 'Financial Office could not be created because UOB is missing';
    END IF;
END
$$;

COMMIT;