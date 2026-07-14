-- ============================================================
-- Table: initiatives
--
-- Purpose:
-- Stores university initiatives.
--
-- An initiative can optionally be linked to an agreement.
-- Approval is handled through the workflow engine.
-- ============================================================


CREATE TABLE initiatives (

    initiative_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    title VARCHAR(255)
        NOT NULL,


    description TEXT,


    initiative_type VARCHAR(100)
        NOT NULL,


    status VARCHAR(50)
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


    CONSTRAINT fk_initiative_creator

        FOREIGN KEY(created_by)

        REFERENCES users(user_id)

);