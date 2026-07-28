-- ============================================================
-- Table: initiative_documents
-- Initiative attachments and supporting files
-- ============================================================

CREATE TABLE initiative_documents (
    document_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    initiative_id BIGINT
        NOT NULL,

    original_name VARCHAR(255)
        NOT NULL,

    stored_name VARCHAR(255),

    file_path TEXT
        NOT NULL,

    mime_type VARCHAR(150),

    file_size BIGINT,

    document_type VARCHAR(100),

    uploaded_by BIGINT
        NOT NULL,

    uploaded_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_initiative_document_initiative
        FOREIGN KEY(initiative_id)
        REFERENCES initiatives(initiative_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_initiative_document_uploader
        FOREIGN KEY(uploaded_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_initiative_document_size
        CHECK (file_size IS NULL OR file_size >= 0)
);

CREATE INDEX idx_initiative_documents_initiative
    ON initiative_documents(initiative_id);
