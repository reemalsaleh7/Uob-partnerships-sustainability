-- ============================================================
-- Table: partners
--
-- Purpose:
-- Stores external organizations that collaborate with
-- the university.
--
-- Examples:
-- Companies
-- Universities
-- Government Organizations
-- Research Centers
-- ============================================================


CREATE TABLE partners (

    partner_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    organization_name VARCHAR(255)
        NOT NULL,


    partner_type VARCHAR(100)
        NOT NULL,


    country VARCHAR(100),


    address TEXT,


    website VARCHAR(255),


    email VARCHAR(255),


    phone VARCHAR(50),


    is_active BOOLEAN
        NOT NULL
        DEFAULT TRUE,


    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP

);