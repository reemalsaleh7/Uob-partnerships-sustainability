-- ============================================================
-- Table: initiative_agreements
-- Many-to-many Initiative/Agreement association
-- ============================================================

CREATE TABLE initiative_agreements (
    initiative_id BIGINT
        NOT NULL,

    agreement_id BIGINT
        NOT NULL,

    relation_notes VARCHAR(500),

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(
        initiative_id,
        agreement_id
    ),

    CONSTRAINT fk_initiative_agreement_initiative
        FOREIGN KEY(initiative_id)
        REFERENCES initiatives(initiative_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_initiative_agreement_agreement
        FOREIGN KEY(agreement_id)
        REFERENCES agreements(agreement_id)
        ON DELETE CASCADE
);

CREATE INDEX idx_initiative_agreements_agreement
    ON initiative_agreements(agreement_id);