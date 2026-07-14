-- ============================================================
-- Table: agreement_relationships
--
-- Purpose:
-- Stores relationships between agreements.
--
-- Examples:
-- Amendment of agreement
-- Renewal of agreement
-- Termination of agreement
-- ============================================================


CREATE TABLE agreement_relationships (

    relationship_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    parent_agreement_id BIGINT
        NOT NULL,


    related_agreement_id BIGINT
        NOT NULL,


    relationship_type VARCHAR(50)
        NOT NULL,


    created_by BIGINT
        NOT NULL,


    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_parent_agreement

        FOREIGN KEY(parent_agreement_id)

        REFERENCES agreements(agreement_id),


    CONSTRAINT fk_related_agreement

        FOREIGN KEY(related_agreement_id)

        REFERENCES agreements(agreement_id),


    CONSTRAINT fk_relationship_creator

        FOREIGN KEY(created_by)

        REFERENCES users(user_id)

);