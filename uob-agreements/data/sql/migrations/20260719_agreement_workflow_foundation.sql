-- Agreement workflow runtime foundation.
--
-- Creator -> Initial VP -> mandatory Legal + optional Finance
--         -> Final VP -> President
--
-- Legal and Finance may run in parallel. Final VP begins only after
-- Legal and, when selected, Finance have completed.

BEGIN;

-- ============================================================
-- Legacy workflow enum alignment
-- ============================================================

DO $$
BEGIN
    CREATE TYPE workflow_status AS ENUM (
        'IN_PROGRESS',
        'COMPLETED',
        'REJECTED',
        'CANCELLED'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$$;

DO $$
BEGIN
    CREATE TYPE workflow_step_status AS ENUM (
        'PENDING',
        'IN_PROGRESS',
        'APPROVED',
        'REJECTED',
        'SKIPPED'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$$;

DO $$
BEGIN
    CREATE TYPE workflow_approval_type AS ENUM (
        'CREATOR',
        'APPROVAL',
        'REJECTION'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$$;

DO $$
BEGIN
    CREATE TYPE workflow_action_type AS ENUM (
        'SUBMITTED',
        'APPROVED',
        'REJECTED',
        'REDRAFTED',
        'COMPLETED'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END
$$;

-- ============================================================
-- Convert legacy VARCHAR workflow columns to enums
-- ============================================================

-- This view depends on workflow_instance_steps.status.
DROP VIEW IF EXISTS v_pending_workflows;

ALTER TABLE workflow_instances
    ALTER COLUMN status DROP DEFAULT;

ALTER TABLE workflow_instance_steps
    ALTER COLUMN status DROP DEFAULT;

ALTER TABLE workflow_template_steps
    ALTER COLUMN approval_type TYPE workflow_approval_type
    USING approval_type::text::workflow_approval_type;

ALTER TABLE workflow_instances
    ALTER COLUMN status TYPE workflow_status
    USING status::text::workflow_status;

ALTER TABLE workflow_instance_steps
    ALTER COLUMN status TYPE workflow_step_status
    USING status::text::workflow_step_status;

ALTER TABLE workflow_history
    ALTER COLUMN action TYPE workflow_action_type
    USING action::text::workflow_action_type;

ALTER TABLE workflow_instances
    ALTER COLUMN status SET DEFAULT 'IN_PROGRESS';

ALTER TABLE workflow_instance_steps
    ALTER COLUMN status SET DEFAULT 'PENDING';

CREATE VIEW v_pending_workflows AS
SELECT
    wi.workflow_instance_id,
    wi.entity_type,
    wi.entity_id,
    wis.step_order,
    wis.status,
    ou.name AS waiting_department
FROM workflow_instances wi
JOIN workflow_instance_steps wis
    ON wis.workflow_instance_id = wi.workflow_instance_id
LEFT JOIN organizational_units ou
    ON ou.unit_id = wis.assigned_unit_id
WHERE wis.status = 'PENDING';

-- ============================================================
-- Template stage identity
-- ============================================================

ALTER TABLE workflow_template_steps
    ADD COLUMN IF NOT EXISTS step_key VARCHAR(50);

CREATE UNIQUE INDEX IF NOT EXISTS
    ux_workflow_template_step_key
ON workflow_template_steps (
    workflow_template_id,
    step_key
)
WHERE step_key IS NOT NULL;


CREATE UNIQUE INDEX IF NOT EXISTS
    ux_workflow_template_step_order
ON workflow_template_steps (
    workflow_template_id,
    step_order
);



-- NULL: initial VP has not decided yet.

-- ============================================================
-- Workflow instance decision state
-- ============================================================

-- NULL: initial VP has not decided yet.
-- FALSE: Legal only.
-- TRUE: Legal and Finance.
ALTER TABLE workflow_instances
    ADD COLUMN IF NOT EXISTS finance_review_required BOOLEAN;

-- ============================================================
-- Workflow instance step snapshots
-- ============================================================

ALTER TABLE workflow_instance_steps
    ADD COLUMN IF NOT EXISTS template_step_id BIGINT,
    ADD COLUMN IF NOT EXISTS step_key VARCHAR(50),
    ADD COLUMN IF NOT EXISTS is_optional BOOLEAN NOT NULL DEFAULT FALSE;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_instance_step_template_step'
          AND conrelid = 'workflow_instance_steps'::regclass
    ) THEN
        ALTER TABLE workflow_instance_steps
            ADD CONSTRAINT fk_instance_step_template_step
            FOREIGN KEY (template_step_id)
            REFERENCES workflow_template_steps(template_step_id);
    END IF;
END
$$;

CREATE UNIQUE INDEX IF NOT EXISTS
    ux_workflow_instance_template_step
ON workflow_instance_steps (
    workflow_instance_id,
    template_step_id
)
WHERE template_step_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS
    ix_workflow_instance_steps_active
ON workflow_instance_steps (
    workflow_instance_id,
    status,
    step_order
);

-- ============================================================
-- Concrete user assignments
-- ============================================================

CREATE TABLE IF NOT EXISTS workflow_step_assignments (
    assignment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,

    workflow_instance_step_id BIGINT NOT NULL,

    user_id BIGINT NOT NULL,

    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    CONSTRAINT fk_step_assignment_step
        FOREIGN KEY (workflow_instance_step_id)
        REFERENCES workflow_instance_steps(instance_step_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_step_assignment_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

CREATE UNIQUE INDEX IF NOT EXISTS
    ux_active_workflow_step_assignment
ON workflow_step_assignments (
    workflow_instance_step_id,
    user_id
)
WHERE is_active = TRUE;

CREATE INDEX IF NOT EXISTS
    ix_workflow_assignments_user_active
ON workflow_step_assignments (
    user_id,
    is_active,
    workflow_instance_step_id
);

-- ============================================================
-- Agreement workflow template
-- ============================================================

INSERT INTO workflow_templates (
    name,
    description,
    process_type,
    is_active
)
VALUES (
    'Agreement Approval',
    'Creator to initial VP review, mandatory Legal review, optional Finance review, final VP review, and President approval',
    'AGREEMENT',
    TRUE
)
ON CONFLICT (name) DO UPDATE
SET
    description = EXCLUDED.description,
    process_type = EXCLUDED.process_type,
    is_active = TRUE;

WITH agreement_template AS (
    SELECT workflow_template_id
    FROM workflow_templates
    WHERE name = 'Agreement Approval'
),
stage_data (
    step_order,
    step_key,
    approval_type,
    required_unit_code,
    is_optional
) AS (
    VALUES
        (
            1,
            'CREATOR',
            'CREATOR'::workflow_approval_type,
            NULL,
            FALSE
        ),
        (
            2,
            'VP_INITIAL',
            'APPROVAL'::workflow_approval_type,
            'VP',
            FALSE
        ),
        (
            3,
            'LEGAL_REVIEW',
            'APPROVAL'::workflow_approval_type,
            'LEGAL',
            FALSE
        ),
        (
            4,
            'FINANCE_REVIEW',
            'APPROVAL'::workflow_approval_type,
            'FIN',
            TRUE
        ),
        (
            5,
            'VP_FINAL',
            'APPROVAL'::workflow_approval_type,
            'VP',
            FALSE
        ),
        (
            6,
            'PRESIDENT_APPROVAL',
            'APPROVAL'::workflow_approval_type,
            'PRES',
            FALSE
        )
)
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    step_key,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    at.workflow_template_id,
    sd.step_order,
    sd.step_key,
    sd.approval_type,
    ou.unit_id,
    NULL,
    sd.is_optional
FROM agreement_template at
CROSS JOIN stage_data sd
LEFT JOIN organizational_units ou
    ON ou.code = sd.required_unit_code
ON CONFLICT (
    workflow_template_id,
    step_order
) DO UPDATE
SET
    step_key = EXCLUDED.step_key,
    approval_type = EXCLUDED.approval_type,
    required_unit_id = EXCLUDED.required_unit_id,
    required_position_id = NULL,
    is_optional = EXCLUDED.is_optional;

-- ============================================================
-- Validation
-- ============================================================

DO $$
DECLARE
    agreement_template_id BIGINT;
    agreement_stage_count INTEGER;
BEGIN
    IF EXISTS (
        SELECT 1
        FROM (
            VALUES
                ('VP'),
                ('LEGAL'),
                ('FIN'),
                ('PRES')
        ) AS required(code)
        WHERE NOT EXISTS (
            SELECT 1
            FROM organizational_units ou
            WHERE ou.code = required.code
              AND ou.is_active = TRUE
        )
    ) THEN
        RAISE EXCEPTION
            'Agreement workflow requires active VP, LEGAL, FIN, and PRES units';
    END IF;

    SELECT workflow_template_id
    INTO agreement_template_id
    FROM workflow_templates
    WHERE name = 'Agreement Approval';

    SELECT COUNT(*)
    INTO agreement_stage_count
    FROM workflow_template_steps
    WHERE workflow_template_id = agreement_template_id
      AND step_key IN (
          'CREATOR',
          'VP_INITIAL',
          'LEGAL_REVIEW',
          'FINANCE_REVIEW',
          'VP_FINAL',
          'PRESIDENT_APPROVAL'
      );

    IF agreement_stage_count <> 6 THEN
        RAISE EXCEPTION
            'Agreement workflow must contain exactly six stage keys';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM workflow_template_steps
        WHERE workflow_template_id = agreement_template_id
          AND step_key = 'LEGAL_REVIEW'
          AND is_optional = TRUE
    ) THEN
        RAISE EXCEPTION
            'Legal review must be mandatory';
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM workflow_template_steps
        WHERE workflow_template_id = agreement_template_id
          AND step_key = 'FINANCE_REVIEW'
          AND is_optional = TRUE
    ) THEN
        RAISE EXCEPTION
            'Finance review must be optional';
    END IF;
END
$$;

COMMIT;