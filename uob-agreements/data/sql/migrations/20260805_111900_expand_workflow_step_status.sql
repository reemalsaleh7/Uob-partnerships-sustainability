-- Initiative runtime step states required by the shared Workflow engine.
-- IMPORTANT: database-manager.cmd owns the transaction.
-- This migration must complete before 20260805_112000 uses the new values.

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'DISCUSSING_REVISION';

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'CANCELLED';
