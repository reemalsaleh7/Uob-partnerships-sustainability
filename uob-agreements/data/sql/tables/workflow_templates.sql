-- ============================================================
-- Table: workflow_templates
--
-- Purpose:
-- Stores reusable approval workflow definitions.
-- ============================================================


CREATE TABLE workflow_templates (

    workflow_template_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    name VARCHAR(150)
        NOT NULL
        UNIQUE,


    description TEXT,


    process_type VARCHAR(100)
        NOT NULL,


    is_active BOOLEAN
        NOT NULL
        DEFAULT TRUE,


    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP

);