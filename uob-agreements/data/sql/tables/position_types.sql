-- ============================================================
-- Table: position_types
--
-- Purpose:
-- Categorizes university positions.
--
-- Examples:
-- Academic
-- Leadership
-- Administrative
-- ============================================================

CREATE TABLE position_types (

    position_type_id
        BIGINT GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    name
        VARCHAR(100)
        NOT NULL
        UNIQUE,

    description
        TEXT,

    created_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
);