-- ============================================================
-- Table: partner_contacts
--
-- Purpose:
-- Stores contact persons inside partner organizations.
-- ============================================================


CREATE TABLE partner_contacts (

    contact_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    partner_id BIGINT
        NOT NULL,


    full_name VARCHAR(255)
        NOT NULL,


    job_title VARCHAR(150),


    email VARCHAR(255),


    phone VARCHAR(50),


    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_partner_contacts_partner

        FOREIGN KEY(partner_id)

        REFERENCES partners(partner_id)

        ON DELETE CASCADE

);