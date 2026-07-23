BEGIN;

ALTER TABLE agreement_lifecycle_requests
    ADD COLUMN IF NOT EXISTS successor_agreement_id BIGINT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_lifecycle_request_successor_agreement'
    ) THEN
        ALTER TABLE agreement_lifecycle_requests
            ADD CONSTRAINT fk_lifecycle_request_successor_agreement
            FOREIGN KEY (successor_agreement_id)
            REFERENCES agreements(agreement_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_lifecycle_successor_not_source'
    ) THEN
        ALTER TABLE agreement_lifecycle_requests
            ADD CONSTRAINT chk_lifecycle_successor_not_source
            CHECK (
                successor_agreement_id IS NULL
                OR successor_agreement_id <> agreement_id
            );
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS ux_lifecycle_successor_agreement
    ON agreement_lifecycle_requests (successor_agreement_id)
    WHERE successor_agreement_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_agreement_relationship_identity
    ON agreement_relationships (
        parent_agreement_id,
        related_agreement_id,
        relationship_type
    );

COMMIT;
