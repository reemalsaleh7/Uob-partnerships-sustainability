-- Phase 6F
-- Allow one workflow step to be assigned to the principal executive and
-- one or more authorized office delegates.
--
-- No BEGIN/COMMIT statements: database-manager.cmd controls migration execution.

ALTER TABLE workflow_step_assignments
    DROP CONSTRAINT IF EXISTS workflow_step_assignments_workflow_instance_step_id_key;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'workflow_step_assignments'::regclass
          AND conname = 'workflow_step_assignments_step_user_key'
    ) THEN
        ALTER TABLE workflow_step_assignments
            ADD CONSTRAINT workflow_step_assignments_step_user_key
            UNIQUE (workflow_instance_step_id, user_id);
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS
    idx_workflow_step_assignments_active_user
ON workflow_step_assignments (user_id, is_active, workflow_instance_step_id);
