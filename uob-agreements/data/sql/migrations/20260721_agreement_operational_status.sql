BEGIN;

ALTER TABLE agreements
    ADD COLUMN IF NOT EXISTS activated_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS expired_at TIMESTAMP;

CREATE TABLE IF NOT EXISTS agreement_signing_records (
    signing_record_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL UNIQUE
        REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    signed_document_id BIGINT NOT NULL
        REFERENCES agreement_documents(document_id) ON DELETE RESTRICT,
    signing_date DATE NOT NULL,
    effective_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    venue VARCHAR(255),
    public_announcement_url TEXT,
    ceremony_notes TEXT,
    signatory_snapshot JSONB NOT NULL,
    finalized_by BIGINT NOT NULL REFERENCES users(user_id),
    finalized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agreement_signing_dates_check
        CHECK (expiry_date >= effective_date),
    CONSTRAINT agreement_signatory_snapshot_check
        CHECK (
            jsonb_typeof(signatory_snapshot) = 'array'
            AND jsonb_array_length(signatory_snapshot) >= 2
        )
);

CREATE INDEX IF NOT EXISTS ix_agreement_signing_records_dates
    ON agreement_signing_records (effective_date, expiry_date, agreement_id);

CREATE TABLE IF NOT EXISTS agreement_status_events (
    status_event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL
        REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    from_status VARCHAR(30) NOT NULL,
    to_status VARCHAR(30) NOT NULL,
    effective_as_of DATE NOT NULL,
    reason TEXT NOT NULL,
    performed_by BIGINT REFERENCES users(user_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agreement_status_event_transition_check
        CHECK (
            (from_status = 'APPROVED' AND to_status = 'ACTIVE')
            OR (from_status = 'ACTIVE' AND to_status = 'EXPIRED')
        ),
    CONSTRAINT agreement_status_event_once
        UNIQUE (agreement_id, to_status)
);

CREATE INDEX IF NOT EXISTS ix_agreement_status_events_agreement
    ON agreement_status_events (agreement_id, created_at, status_event_id);

INSERT INTO permissions (permission_code, permission_name, description)
VALUES (
    'MANAGE_AGREEMENT_OPERATIONS',
    'Manage Agreement operations',
    'Finalize signing and activate approved Agreements owned by the user'
)
ON CONFLICT (permission_code) DO UPDATE
SET permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'MANAGE_AGREEMENT_OPERATIONS'
WHERE r.role_name IN ('Agreement Creator', 'System Administrator')
ON CONFLICT DO NOTHING;

COMMIT;
