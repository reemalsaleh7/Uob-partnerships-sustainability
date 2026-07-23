-- Adds secure physical-file metadata while preserving older metadata-only rows.

BEGIN;

ALTER TABLE agreement_documents
    ADD COLUMN IF NOT EXISTS agreement_version_id BIGINT,
    ADD COLUMN IF NOT EXISTS storage_key TEXT,
    ADD COLUMN IF NOT EXISTS mime_type VARCHAR(150),
    ADD COLUMN IF NOT EXISTS file_size_bytes BIGINT,
    ADD COLUMN IF NOT EXISTS sha256_checksum CHAR(64);

ALTER TABLE agreement_documents
    ALTER COLUMN document_type SET DEFAULT 'OTHER';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'agreement_documents'::regclass
          AND conname = 'fk_agreement_document_version'
    ) THEN
        ALTER TABLE agreement_documents
            ADD CONSTRAINT fk_agreement_document_version
            FOREIGN KEY (agreement_version_id)
            REFERENCES agreement_versions(version_id)
            ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'agreement_documents'::regclass
          AND conname = 'chk_agreement_document_size'
    ) THEN
        ALTER TABLE agreement_documents
            ADD CONSTRAINT chk_agreement_document_size
            CHECK (
                file_size_bytes IS NULL
                OR file_size_bytes > 0
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'agreement_documents'::regclass
          AND conname = 'chk_agreement_document_checksum'
    ) THEN
        ALTER TABLE agreement_documents
            ADD CONSTRAINT chk_agreement_document_checksum
            CHECK (
                sha256_checksum IS NULL
                OR sha256_checksum ~ '^[0-9a-f]{64}$'
            );
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_agreement_documents_agreement
    ON agreement_documents(agreement_id, uploaded_at DESC);

CREATE INDEX IF NOT EXISTS idx_agreement_documents_version
    ON agreement_documents(agreement_version_id)
    WHERE agreement_version_id IS NOT NULL;

COMMIT;
