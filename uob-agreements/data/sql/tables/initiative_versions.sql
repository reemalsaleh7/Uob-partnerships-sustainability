-- ============================================================
-- Table: initiative_versions
-- Immutable Initiative history with complete JSONB snapshots
-- ============================================================

CREATE TABLE initiative_versions (
    version_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    initiative_id BIGINT
        NOT NULL,

    version_number INTEGER
        NOT NULL,

    initiative_snapshot JSONB
        NOT NULL,

    document_path TEXT,
    change_summary TEXT,

    created_by BIGINT
        NOT NULL,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_initiative_versions_initiative
        FOREIGN KEY(initiative_id)
        REFERENCES initiatives(initiative_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_initiative_versions_creator
        FOREIGN KEY(created_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT uq_initiative_version
        UNIQUE(initiative_id, version_number),

    CONSTRAINT chk_initiative_version_positive
        CHECK (version_number > 0),

    CONSTRAINT chk_initiative_snapshot_object
        CHECK (jsonb_typeof(initiative_snapshot) = 'object')
);

CREATE INDEX idx_initiative_versions_created_at
    ON initiative_versions(initiative_id, created_at DESC);
