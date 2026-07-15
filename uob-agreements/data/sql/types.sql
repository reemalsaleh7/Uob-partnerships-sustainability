-- ==========================================================
-- University Partnerships & Initiatives System
-- PostgreSQL Custom Types
-- ==========================================================

-- ==========================================================
-- ORGANIZATION
-- ==========================================================

CREATE TYPE organizational_unit_type AS ENUM (
    'UNIVERSITY',
    'OFFICE',
    'COLLEGE',
    'DEPARTMENT'
);

-- ==========================================================
-- AGREEMENTS
-- ==========================================================

CREATE TYPE agreement_status AS ENUM (
    'DRAFT',
    'UNDER_REVIEW',
    'APPROVED',
    'ACTIVE',
    'REJECTED',
    'EXPIRED',
    'TERMINATED'
);

CREATE TYPE agreement_relationship_type AS ENUM (
    'RENEWAL',
    'AMENDMENT',
    'TERMINATION'
);

-- ==========================================================
-- INITIATIVES
-- ==========================================================

CREATE TYPE initiative_status AS ENUM (
    'DRAFT',
    'UNDER_REVIEW',
    'APPROVED',
    'ACTIVE',
    'REJECTED',
    'COMPLETED',
    'CANCELLED'
);