BEGIN;

ALTER TABLE agreements
    ADD COLUMN IF NOT EXISTS record_origin VARCHAR(20) NOT NULL DEFAULT 'NEW_SYSTEM';

UPDATE agreements a
SET record_origin = 'LEGACY_IMPORT'
WHERE EXISTS (
    SELECT 1
    FROM agreement_legacy_imports ali
    WHERE ali.agreement_id = a.agreement_id
);

UPDATE agreements a
SET record_origin = 'DEVELOPMENT'
FROM users u
WHERE u.user_id = a.created_by
  AND a.record_origin <> 'LEGACY_IMPORT'
  AND (
      a.agreement_code LIKE 'DEMO-%'
      OR u.university_id LIKE 'DEV-%'
      OR u.email LIKE 'dev.%@uob.test'
  );

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'agreements_record_origin_check'
    ) THEN
        ALTER TABLE agreements
            ADD CONSTRAINT agreements_record_origin_check
            CHECK (record_origin IN ('LEGACY_IMPORT', 'DEVELOPMENT', 'NEW_SYSTEM'));
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS ix_agreements_record_origin
    ON agreements (record_origin, updated_at DESC);

CREATE OR REPLACE FUNCTION set_agreement_record_origin()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.record_origin = 'NEW_SYSTEM' AND EXISTS (
        SELECT 1
        FROM users u
        WHERE u.user_id = NEW.created_by
          AND (
              u.university_id LIKE 'DEV-%'
              OR u.email LIKE 'dev.%@uob.test'
          )
    ) THEN
        NEW.record_origin := 'DEVELOPMENT';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_set_agreement_record_origin ON agreements;
CREATE TRIGGER trg_set_agreement_record_origin
BEFORE INSERT ON agreements
FOR EACH ROW
EXECUTE FUNCTION set_agreement_record_origin();

CREATE TABLE IF NOT EXISTS agreement_administrative_corrections (
    correction_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE RESTRICT,
    version_id BIGINT NOT NULL REFERENCES agreement_versions(version_id) ON DELETE RESTRICT,
    corrected_by BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    reason TEXT NOT NULL CHECK (length(trim(reason)) BETWEEN 10 AND 1000),
    corrected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agreement_administrative_corrections_version_unique UNIQUE (version_id)
);

CREATE INDEX IF NOT EXISTS ix_agreement_admin_corrections_agreement
    ON agreement_administrative_corrections (agreement_id, corrected_at DESC);

INSERT INTO permissions (permission_code, permission_name, description)
VALUES (
    'ADMIN_CORRECT_LEGACY_AGREEMENT',
    'Administratively correct a legacy Agreement',
    'Correct inaccurate imported data while preserving Agreement versions, provenance, and audit history'
)
ON CONFLICT (permission_code) DO UPDATE
SET permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'ADMIN_CORRECT_LEGACY_AGREEMENT'
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

COMMIT;
