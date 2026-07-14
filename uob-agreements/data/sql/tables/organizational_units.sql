-- ============================================================
-- Table: organizational_units
--
-- Purpose:
-- Represents the hierarchical organizational structure of the
-- university.
--
-- Examples:
-- University
-- ├── President Office
-- ├── Vice President Office
-- ├── Legal Office
-- ├── Financial Office
-- ├── College of IT
-- │      ├── Computer Science
-- │      └── Information Systems
-- └── College of Engineering
--
-- Every unit (except the University root) has exactly one parent.
-- ============================================================

CREATE TABLE organizational_units (

    unit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    display_order INTEGER NOT NULL DEFAULT 0

    name TEXT NOT NULL,

    code VARCHAR(20),

    unit_type organizational_unit_type NOT NULL,

    parent_unit_id BIGINT,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_parent_unit
        FOREIGN KEY (parent_unit_id)
        REFERENCES organizational_units(unit_id)
);