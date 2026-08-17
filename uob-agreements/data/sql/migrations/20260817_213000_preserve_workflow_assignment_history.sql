BEGIN;

ALTER TABLE workflow_step_assignments
    DROP CONSTRAINT IF EXISTS
    workflow_step_assignments_step_user_key;

CREATE UNIQUE INDEX IF NOT EXISTS
    uq_workflow_step_assignments_active_step_user
ON workflow_step_assignments (
    workflow_instance_step_id,
    user_id
)
WHERE is_active = TRUE;

COMMIT;