-- ============================================================
-- Table: initiative_versions
--
-- Purpose:
-- Stores initiative revisions.
-- ============================================================


CREATE TABLE initiative_versions (

    version_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    initiative_id BIGINT
        NOT NULL,


    version_number INTEGER
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

        REFERENCES users(user_id),


    UNIQUE(
        initiative_id,
        version_number
    )

);