-- ==========================================================
-- Agreement return, redraft, and VP-routing workflow
-- ==========================================================

-- Create the Agreement status enum when upgrading a database
-- where agreements.status is still VARCHAR.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_type
        WHERE typname = 'agreement_status'
    ) THEN
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
    END IF;
END
$$;

-- Support databases where the enum already exists but does
-- not yet contain the new status.
ALTER TYPE agreement_status
    ADD VALUE IF NOT EXISTS 'REVISION_REQUIRED';

-- Convert the older VARCHAR Agreement status column to the
-- enum used by the current schema.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'agreements'
          AND column_name = 'status'
          AND data_type = 'character varying'
    ) THEN
        ALTER TABLE agreements
            ALTER COLUMN status DROP DEFAULT;

        ALTER TABLE agreements
            ALTER COLUMN status TYPE agreement_status
            USING status::text::agreement_status;

        ALTER TABLE agreements
            ALTER COLUMN status
            SET DEFAULT 'DRAFT'::agreement_status;
    END IF;
END
$$;

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_CREATOR';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_LEGAL';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_FINANCE';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'RESUBMITTED';

ALTER TABLE workflow_instances
    ADD COLUMN IF NOT EXISTS review_cycle INTEGER
        NOT NULL
        DEFAULT 1;

-- PostgreSQL does not support ADD CONSTRAINT IF NOT EXISTS,
-- so check the system catalogue first.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'workflow_instances'::regclass
          AND conname =
              'chk_workflow_review_cycle_positive'
    ) THEN
        ALTER TABLE workflow_instances
            ADD CONSTRAINT
                chk_workflow_review_cycle_positive
            CHECK (review_cycle > 0);
    END IF;
END
$$;