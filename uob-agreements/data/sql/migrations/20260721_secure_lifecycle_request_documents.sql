BEGIN;

CREATE TABLE IF NOT EXISTS agreement_lifecycle_request_documents (
    lifecycle_request_document_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lifecycle_request_id BIGINT NOT NULL
        REFERENCES agreement_lifecycle_requests(lifecycle_request_id)
        ON DELETE CASCADE,
    lifecycle_request_version_id BIGINT
        REFERENCES agreement_lifecycle_request_versions(lifecycle_request_version_id)
        ON DELETE SET NULL,
    file_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(150) NOT NULL,
    file_size_bytes BIGINT NOT NULL
        CHECK (file_size_bytes > 0 AND file_size_bytes <= 10485760),
    sha256_checksum CHAR(64) NOT NULL,
    document_type VARCHAR(40) NOT NULL
        CHECK (document_type IN (
            'REQUEST_FORM', 'SUPPORTING', 'PROPOSED_AMENDMENT',
            'RENEWAL_EVIDENCE', 'TERMINATION_EVIDENCE',
            'LEGAL_REVIEW', 'FINANCE_REVIEW',
            'PRESIDENT_DECISION', 'OTHER'
        )),
    uploaded_by BIGINT NOT NULL REFERENCES users(user_id),
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_lifecycle_request_documents_request
    ON agreement_lifecycle_request_documents (
        lifecycle_request_id, uploaded_at DESC
    );

CREATE INDEX IF NOT EXISTS ix_lifecycle_request_documents_version
    ON agreement_lifecycle_request_documents (lifecycle_request_version_id);

COMMIT;
