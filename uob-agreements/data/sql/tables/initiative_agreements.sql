-- ============================================================
-- Table: initiative_agreements
--
-- Purpose:
-- Links initiatives to supporting agreements.
-- ============================================================


CREATE TABLE initiative_agreements (

    initiative_id BIGINT
        NOT NULL,


    agreement_id BIGINT
        NOT NULL,


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