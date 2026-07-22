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
    'REVISION_REQUIRED',
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

CREATE TYPE workflow_status AS ENUM (
    'IN_PROGRESS',
    'COMPLETED',
    'REJECTED',
    'CANCELLED'
);

CREATE TYPE workflow_step_status AS ENUM (
    'PENDING',
    'IN_PROGRESS',
    'APPROVED',
    'CHANGES_REQUESTED',
    'REJECTED',
    'SKIPPED'
);

CREATE TYPE workflow_approval_type AS ENUM (
    'CREATOR',
    'APPROVAL',
    'REJECTION'
);

CREATE TYPE workflow_action_type AS ENUM (
    'SUBMITTED',
    'APPROVED',
    'CHANGES_REQUESTED',
    'ROUTED_TO_VP',
    'ROUTED_TO_CREATOR',
    'ROUTED_TO_LEGAL',
    'ROUTED_TO_FINANCE',
    'RESUBMITTED',
    'REJECTED',
    'REDRAFTED',
    'COMPLETED'
);

CREATE TYPE audit_action AS ENUM (
    'INSERT',
    'UPDATE',
    'DELETE',
    'LOGIN',
    'LOGOUT',
    'APPROVE',
    'REJECT'
);