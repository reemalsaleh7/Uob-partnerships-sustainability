-- Adds runtime timing fields required by workflow step activation
-- and completion logic to legacy databases.

ALTER TABLE workflow_instance_steps
    ADD COLUMN IF NOT EXISTS started_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP;