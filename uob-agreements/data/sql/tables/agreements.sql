-- ============================================================
-- Table: agreements
--
-- Purpose:
-- Stores university partnership agreements.
--
-- Approval workflow is handled separately.
-- ============================================================


CREATE TABLE agreements (

    agreement_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    title VARCHAR(255)
        NOT NULL,

    description TEXT,

    agreement_type VARCHAR(100)
        NOT NULL,

    status agreement_status
        NOT NULL
        DEFAULT 'DRAFT',

    created_by BIGINT
        NOT NULL,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_agreements_creator
        FOREIGN KEY(created_by)
        REFERENCES users(user_id),

    CONSTRAINT chk_agreement_title_not_empty
        CHECK (length(trim(title)) > 0),

    CONSTRAINT chk_agreement_type_not_empty
        CHECK (length(trim(agreement_type)) > 0)
);