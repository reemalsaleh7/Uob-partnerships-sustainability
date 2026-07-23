BEGIN;

CREATE TABLE IF NOT EXISTS agreement_legacy_imports (
    legacy_import_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    import_batch_id UUID NOT NULL DEFAULT gen_random_uuid(),
    source_file VARCHAR(255) NOT NULL,
    source_row_number INTEGER NOT NULL CHECK (source_row_number >= 2),
    source_record_id VARCHAR(100),
    source_hash CHAR(64) NOT NULL,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE RESTRICT,
    imported_by BIGINT NOT NULL REFERENCES users(user_id),
    source_payload JSONB NOT NULL,
    import_warnings JSONB NOT NULL DEFAULT '[]'::jsonb,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agreement_legacy_imports_source_row_unique
        UNIQUE (source_file, source_row_number),
    CONSTRAINT agreement_legacy_imports_agreement_unique
        UNIQUE (agreement_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_agreement_legacy_imports_source_record
    ON agreement_legacy_imports (source_file, source_record_id)
    WHERE source_record_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_agreement_legacy_imports_batch
    ON agreement_legacy_imports (import_batch_id, legacy_import_id);

COMMIT;
