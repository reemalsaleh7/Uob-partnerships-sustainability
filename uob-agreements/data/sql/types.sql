-- ============================================================
-- ENUM: organizational_unit_type
--
-- Defines the valid organizational unit categories.
-- ============================================================

CREATE TYPE organizational_unit_type AS ENUM (

    'UNIVERSITY',

    'OFFICE',

    'COLLEGE',

    'DEPARTMENT'

);