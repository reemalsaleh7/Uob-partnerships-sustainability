-- ============================================================
-- Table: agreement_partners
--
-- Purpose:
-- Connects agreements with partner organizations.
-- ============================================================


CREATE TABLE agreement_partners (

    agreement_id BIGINT
        NOT NULL,


    partner_id BIGINT
        NOT NULL,


    PRIMARY KEY(
        agreement_id,
        partner_id
    ),


    CONSTRAINT fk_agreement_partner_agreement

        FOREIGN KEY(agreement_id)

        REFERENCES agreements(agreement_id)

        ON DELETE CASCADE,


    CONSTRAINT fk_agreement_partner_partner

        FOREIGN KEY(partner_id)

        REFERENCES partners(partner_id)

        ON DELETE CASCADE

);