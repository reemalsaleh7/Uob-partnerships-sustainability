-- Records the latest Agreement version that existed when
-- the VP returned it to the creator for redrafting.

ALTER TABLE workflow_instances
    ADD COLUMN IF NOT EXISTS
        redraft_base_version INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'workflow_instances'::regclass
          AND conname =
              'chk_redraft_base_version_nonnegative'
    ) THEN
        ALTER TABLE workflow_instances
            ADD CONSTRAINT
                chk_redraft_base_version_nonnegative
            CHECK (
                redraft_base_version IS NULL
                OR redraft_base_version >= 0
            );
    END IF;
END
$$;