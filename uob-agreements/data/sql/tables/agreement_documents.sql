CREATE TABLE agreement_documents (
    document_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    agreement_version_id BIGINT,
    file_name VARCHAR(255) NOT NULL,
    file_path TEXT,
    storage_key TEXT,
    mime_type VARCHAR(150),
    file_size_bytes BIGINT,
    sha256_checksum CHAR(64),
    document_type VARCHAR(100) NOT NULL DEFAULT 'OTHER',
    uploaded_by BIGINT NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    FOREIGN KEY(agreement_version_id) REFERENCES agreement_versions(version_id) ON DELETE RESTRICT,
    FOREIGN KEY(uploaded_by) REFERENCES users(user_id),
    CONSTRAINT chk_agreement_document_size
        CHECK (file_size_bytes IS NULL OR file_size_bytes > 0),
    CONSTRAINT chk_agreement_document_checksum
        CHECK (
            sha256_checksum IS NULL
            OR sha256_checksum ~ '^[0-9a-f]{64}$'
        )
);
