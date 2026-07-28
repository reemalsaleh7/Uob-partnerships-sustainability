-- ==========================================================
-- Initiative extension for data/sql/all_sql.sql
--
-- This file contains the exact fresh-install Initiative section
-- to merge into all_sql.sql. It is also executable immediately
-- after the existing all_sql.sql during local setup.
-- ==========================================================

ALTER TYPE initiative_status
    ADD VALUE IF NOT EXISTS 'REVISION_REQUIRED';

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_CREATOR';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_VP';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'RESUBMITTED';

-- For an existing schema, use the dated migration instead.
-- For a fresh consolidated build, replace the Initiative table
-- blocks in all_sql.sql with the files supplied in tables/.
