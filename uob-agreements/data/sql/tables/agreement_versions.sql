CREATE TABLE agreement_versions (

    version_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    agreement_id BIGINT
        NOT NULL,


    version_number INTEGER
        NOT NULL,


    document_path TEXT,


    change_summary TEXT,


    agreement_snapshot JSONB
        NOT NULL,


    created_by BIGINT
        NOT NULL,


    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_versions_agreement

        FOREIGN KEY(agreement_id)

        REFERENCES agreements(agreement_id)
        ON DELETE CASCADE,


    CONSTRAINT fk_versions_creator

        FOREIGN KEY(created_by)

        REFERENCES users(user_id),


    UNIQUE(
        agreement_id,
        version_number
    )

);
