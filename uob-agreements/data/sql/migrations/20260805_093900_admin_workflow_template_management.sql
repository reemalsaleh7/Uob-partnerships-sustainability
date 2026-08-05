-- ============================================================
-- Versioned administrator-managed Workflow templates
--
-- Important:
-- - The database manager owns the transaction for this migration.
-- - Published changes affect newly-created Workflow instances only.
-- - Existing Agreement and Initiative Workflow snapshots stay unchanged.
-- ============================================================

CREATE UNIQUE INDEX IF NOT EXISTS ux_permissions_permission_code
    ON permissions (permission_code);

INSERT INTO permissions (permission_code, permission_name, description)
VALUES (
    'MANAGE_WORKFLOW_TEMPLATES',
    'Manage Workflow Templates',
    'Create and publish versioned Agreement and Initiative Workflow templates'
)
ON CONFLICT (permission_code) DO UPDATE
SET
    permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code = 'MANAGE_WORKFLOW_TEMPLATES'
WHERE role.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

ALTER TABLE workflow_templates
    ADD COLUMN IF NOT EXISTS template_key VARCHAR(80),
    ADD COLUMN IF NOT EXISTS version_number INTEGER NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by BIGINT REFERENCES users(user_id),
    ADD COLUMN IF NOT EXISTS change_reason VARCHAR(500),
    ADD COLUMN IF NOT EXISTS published_at TIMESTAMP;

UPDATE workflow_templates
SET template_key = CASE
    WHEN UPPER(process_type) = 'AGREEMENT'
        THEN 'AGREEMENT_APPROVAL'
    WHEN UPPER(process_type) = 'INITIATIVE'
        THEN 'INITIATIVE_APPROVAL'
    ELSE REGEXP_REPLACE(UPPER(process_type), '[^A-Z0-9]+', '_', 'g')
         || '_DEFAULT'
END
WHERE template_key IS NULL OR BTRIM(template_key) = '';

WITH numbered AS (
    SELECT
        workflow_template_id,
        ROW_NUMBER() OVER (
            PARTITION BY template_key
            ORDER BY workflow_template_id
        ) AS calculated_version
    FROM workflow_templates
)
UPDATE workflow_templates template
SET version_number = numbered.calculated_version
FROM numbered
WHERE numbered.workflow_template_id = template.workflow_template_id;

WITH ranked_active AS (
    SELECT
        workflow_template_id,
        ROW_NUMBER() OVER (
            PARTITION BY template_key
            ORDER BY version_number DESC, workflow_template_id DESC
        ) AS active_rank
    FROM workflow_templates
    WHERE is_active = TRUE
)
UPDATE workflow_templates template
SET is_active = FALSE,
    updated_at = CURRENT_TIMESTAMP
FROM ranked_active ranked
WHERE ranked.workflow_template_id = template.workflow_template_id
  AND ranked.active_rank > 1;

UPDATE workflow_templates
SET published_at = COALESCE(published_at, created_at)
WHERE is_active = TRUE;

ALTER TABLE workflow_templates
    ALTER COLUMN template_key SET NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_workflow_template_key_version
    ON workflow_templates (template_key, version_number);

CREATE UNIQUE INDEX IF NOT EXISTS ux_workflow_template_active_key
    ON workflow_templates (template_key)
    WHERE is_active = TRUE;

CREATE INDEX IF NOT EXISTS ix_workflow_template_process_versions
    ON workflow_templates (process_type, version_number DESC);

ALTER TABLE workflow_template_steps
    ADD COLUMN IF NOT EXISTS step_label VARCHAR(150),
    ADD COLUMN IF NOT EXISTS phase_order INTEGER,
    ADD COLUMN IF NOT EXISTS execution_mode VARCHAR(20) NOT NULL DEFAULT 'SEQUENTIAL',
    ADD COLUMN IF NOT EXISTS responsibility_type VARCHAR(30) NOT NULL DEFAULT 'UNIT',
    ADD COLUMN IF NOT EXISTS responsibility_scope VARCHAR(40) NOT NULL DEFAULT 'FIXED_UNIT',
    ADD COLUMN IF NOT EXISTS required_permission_code VARCHAR(100),
    ADD COLUMN IF NOT EXISTS reminder_after_days INTEGER NOT NULL DEFAULT 3,
    ADD COLUMN IF NOT EXISTS is_system_step BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS allow_revision BOOLEAN NOT NULL DEFAULT TRUE;

UPDATE workflow_template_steps
SET step_label = COALESCE(
        NULLIF(step_label, ''),
        INITCAP(REPLACE(COALESCE(step_key, 'WORKFLOW_STEP'), '_', ' '))
    ),
    phase_order = COALESCE(phase_order, step_order),
    required_permission_code = COALESCE(
        required_permission_code,
        CASE
            WHEN workflow_template_id IN (
                SELECT workflow_template_id
                FROM workflow_templates
                WHERE UPPER(process_type) = 'INITIATIVE'
            ) THEN 'APPROVE_INITIATIVE'
            ELSE 'APPROVE_AGREEMENT'
        END
    );

UPDATE workflow_template_steps step
SET phase_order = CASE step.step_key
        WHEN 'CREATOR' THEN 1
        WHEN 'VP_INITIAL' THEN 2
        WHEN 'LEGAL_REVIEW' THEN 3
        WHEN 'FINANCE_REVIEW' THEN 3
        WHEN 'VP_FINAL' THEN 4
        WHEN 'PRESIDENT_APPROVAL' THEN 5
        ELSE COALESCE(step.phase_order, step.step_order)
    END,
    execution_mode = CASE
        WHEN step.step_key = 'FINANCE_REVIEW' THEN 'PARALLEL'
        ELSE 'SEQUENTIAL'
    END,
    responsibility_type = CASE
        WHEN step.step_key = 'CREATOR' THEN 'CREATOR'
        WHEN step.required_position_id IS NOT NULL THEN 'POSITION'
        ELSE 'UNIT'
    END,
    responsibility_scope = CASE
        WHEN step.step_key = 'CREATOR' THEN 'NONE'
        ELSE 'FIXED_UNIT'
    END,
    is_system_step = step.step_key IN (
        'CREATOR',
        'VP_INITIAL',
        'LEGAL_REVIEW',
        'FINANCE_REVIEW',
        'VP_FINAL',
        'PRESIDENT_APPROVAL'
    )
FROM workflow_templates template
WHERE template.workflow_template_id = step.workflow_template_id
  AND template.template_key = 'AGREEMENT_APPROVAL';

ALTER TABLE workflow_template_steps
    ALTER COLUMN step_label SET NOT NULL,
    ALTER COLUMN phase_order SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_workflow_template_phase_positive'
          AND conrelid = 'workflow_template_steps'::regclass
    ) THEN
        ALTER TABLE workflow_template_steps
            ADD CONSTRAINT chk_workflow_template_phase_positive
            CHECK (phase_order > 0);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_workflow_template_execution_mode'
          AND conrelid = 'workflow_template_steps'::regclass
    ) THEN
        ALTER TABLE workflow_template_steps
            ADD CONSTRAINT chk_workflow_template_execution_mode
            CHECK (execution_mode IN ('SEQUENTIAL', 'PARALLEL'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_workflow_template_responsibility_type'
          AND conrelid = 'workflow_template_steps'::regclass
    ) THEN
        ALTER TABLE workflow_template_steps
            ADD CONSTRAINT chk_workflow_template_responsibility_type
            CHECK (responsibility_type IN ('CREATOR', 'POSITION', 'UNIT'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_workflow_template_responsibility_scope'
          AND conrelid = 'workflow_template_steps'::regclass
    ) THEN
        ALTER TABLE workflow_template_steps
            ADD CONSTRAINT chk_workflow_template_responsibility_scope
            CHECK (responsibility_scope IN (
                'NONE',
                'FIXED_UNIT',
                'REQUESTER_DEPARTMENT',
                'REQUESTER_COLLEGE',
                'UNIVERSITY'
            ));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'chk_workflow_template_reminder_days'
          AND conrelid = 'workflow_template_steps'::regclass
    ) THEN
        ALTER TABLE workflow_template_steps
            ADD CONSTRAINT chk_workflow_template_reminder_days
            CHECK (reminder_after_days BETWEEN 1 AND 90);
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS ix_workflow_template_steps_phase
    ON workflow_template_steps (
        workflow_template_id,
        phase_order,
        step_order
    );

ALTER TABLE workflow_instance_steps
    ADD COLUMN IF NOT EXISTS step_label VARCHAR(150),
    ADD COLUMN IF NOT EXISTS phase_order INTEGER,
    ADD COLUMN IF NOT EXISTS execution_mode VARCHAR(20) NOT NULL DEFAULT 'SEQUENTIAL',
    ADD COLUMN IF NOT EXISTS responsibility_type VARCHAR(30) NOT NULL DEFAULT 'UNIT',
    ADD COLUMN IF NOT EXISTS responsibility_scope VARCHAR(40) NOT NULL DEFAULT 'FIXED_UNIT',
    ADD COLUMN IF NOT EXISTS required_permission_code VARCHAR(100),
    ADD COLUMN IF NOT EXISTS reminder_after_days INTEGER NOT NULL DEFAULT 3,
    ADD COLUMN IF NOT EXISTS allow_revision BOOLEAN NOT NULL DEFAULT TRUE;

UPDATE workflow_instance_steps instance_step
SET step_label = COALESCE(
        instance_step.step_label,
        template_step.step_label,
        INITCAP(REPLACE(COALESCE(instance_step.step_key, 'WORKFLOW_STEP'), '_', ' '))
    ),
    phase_order = COALESCE(
        instance_step.phase_order,
        template_step.phase_order,
        instance_step.step_order
    ),
    execution_mode = COALESCE(
        template_step.execution_mode,
        instance_step.execution_mode,
        'SEQUENTIAL'
    ),
    responsibility_type = COALESCE(
        template_step.responsibility_type,
        instance_step.responsibility_type,
        CASE
            WHEN instance_step.step_key = 'CREATOR' THEN 'CREATOR'
            WHEN instance_step.assigned_position_id IS NOT NULL THEN 'POSITION'
            ELSE 'UNIT'
        END
    ),
    responsibility_scope = COALESCE(
        template_step.responsibility_scope,
        instance_step.responsibility_scope,
        CASE
            WHEN instance_step.step_key = 'CREATOR' THEN 'NONE'
            ELSE 'FIXED_UNIT'
        END
    ),
    required_permission_code = COALESCE(
        template_step.required_permission_code,
        instance_step.required_permission_code,
        'APPROVE_AGREEMENT'
    ),
    reminder_after_days = COALESCE(
        template_step.reminder_after_days,
        instance_step.reminder_after_days,
        3
    ),
    allow_revision = COALESCE(
        template_step.allow_revision,
        instance_step.allow_revision,
        TRUE
    )
FROM workflow_template_steps template_step
WHERE template_step.template_step_id = instance_step.template_step_id;

UPDATE workflow_instance_steps
SET step_label = COALESCE(
        step_label,
        INITCAP(REPLACE(COALESCE(step_key, 'WORKFLOW_STEP'), '_', ' '))
    ),
    phase_order = COALESCE(phase_order, step_order),
    required_permission_code = COALESCE(
        required_permission_code,
        'APPROVE_AGREEMENT'
    );

ALTER TABLE workflow_instance_steps
    ALTER COLUMN step_label SET NOT NULL,
    ALTER COLUMN phase_order SET NOT NULL;

CREATE INDEX IF NOT EXISTS ix_workflow_instance_steps_phase
    ON workflow_instance_steps (
        workflow_instance_id,
        phase_order,
        status,
        step_order
    );

ALTER TABLE workflow_instances
    ADD COLUMN IF NOT EXISTS current_phase_order INTEGER,
    ADD COLUMN IF NOT EXISTS template_version_number INTEGER,
    ADD COLUMN IF NOT EXISTS engine_version VARCHAR(30) NOT NULL DEFAULT 'LEGACY';

UPDATE workflow_instances instance
SET template_version_number = template.version_number
FROM workflow_templates template
WHERE template.workflow_template_id = instance.workflow_template_id
  AND instance.template_version_number IS NULL;

UPDATE workflow_instances instance
SET current_phase_order = COALESCE(
    (
        SELECT MIN(COALESCE(step.phase_order, step.step_order))
        FROM workflow_instance_steps step
        WHERE step.workflow_instance_id = instance.workflow_instance_id
          AND step.status = 'IN_PROGRESS'
    ),
    instance.current_step
)
WHERE instance.current_phase_order IS NULL;


ALTER TABLE initiative_requests
    ADD COLUMN IF NOT EXISTS workflow_template_id BIGINT REFERENCES workflow_templates(workflow_template_id),
    ADD COLUMN IF NOT EXISTS workflow_template_version INTEGER,
    ADD COLUMN IF NOT EXISTS current_phase_order INTEGER;

UPDATE initiative_requests
SET current_phase_order = current_stage_order
WHERE current_phase_order IS NULL
  AND current_stage_order IS NOT NULL;

ALTER TABLE initiative_request_stages
    ADD COLUMN IF NOT EXISTS template_step_id BIGINT REFERENCES workflow_template_steps(template_step_id),
    ADD COLUMN IF NOT EXISTS phase_order INTEGER,
    ADD COLUMN IF NOT EXISTS execution_mode VARCHAR(20) NOT NULL DEFAULT 'SEQUENTIAL',
    ADD COLUMN IF NOT EXISTS is_optional BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS required_position_id BIGINT REFERENCES positions(position_id),
    ADD COLUMN IF NOT EXISTS required_permission_code VARCHAR(100),
    ADD COLUMN IF NOT EXISTS responsibility_scope VARCHAR(40) NOT NULL DEFAULT 'FIXED_UNIT';

UPDATE initiative_request_stages
SET phase_order = COALESCE(phase_order, stage_order),
    required_permission_code = COALESCE(
        required_permission_code,
        'APPROVE_INITIATIVE'
    );

ALTER TABLE initiative_request_stages
    ALTER COLUMN phase_order SET NOT NULL;

CREATE INDEX IF NOT EXISTS ix_initiative_request_stages_phase
    ON initiative_request_stages (
        request_id,
        cycle_number,
        phase_order,
        status,
        stage_order
    );

CREATE TABLE IF NOT EXISTS workflow_template_publications (
    publication_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    template_key VARCHAR(80) NOT NULL,
    previous_template_id BIGINT REFERENCES workflow_templates(workflow_template_id),
    published_template_id BIGINT NOT NULL REFERENCES workflow_templates(workflow_template_id),
    published_by BIGINT NOT NULL REFERENCES users(user_id),
    change_reason VARCHAR(500) NOT NULL,
    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_workflow_template_publications_key
    ON workflow_template_publications (
        template_key,
        published_at DESC,
        publication_id DESC
    );

INSERT INTO workflow_templates (
    name,
    description,
    process_type,
    is_active,
    template_key,
    version_number,
    change_reason,
    published_at
)
SELECT
    'Initiative Approval v1',
    'Creator, Department Head, Dean, Vice President, and President approval route',
    'INITIATIVE',
    TRUE,
    'INITIATIVE_APPROVAL',
    1,
    'Initial configurable Initiative approval template',
    CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1
    FROM workflow_templates
    WHERE template_key = 'INITIATIVE_APPROVAL'
);

WITH initiative_template AS (
    SELECT workflow_template_id
    FROM workflow_templates
    WHERE template_key = 'INITIATIVE_APPROVAL'
      AND is_active = TRUE
    ORDER BY version_number DESC
    LIMIT 1
),
position_catalogue AS (
    SELECT
        MAX(position_id) FILTER (WHERE LOWER(name) = 'department head') AS department_head_id,
        MAX(position_id) FILTER (WHERE LOWER(name) = 'dean') AS dean_id,
        MAX(position_id) FILTER (WHERE LOWER(name) = 'vice president') AS vice_president_id,
        MAX(position_id) FILTER (WHERE LOWER(name) = 'president') AS president_id
    FROM positions
),
unit_catalogue AS (
    SELECT
        MAX(unit_id) FILTER (WHERE UPPER(code) = 'VP') AS vp_unit_id,
        MAX(unit_id) FILTER (WHERE UPPER(code) = 'PRES') AS president_unit_id
    FROM organizational_units
),
stage_data (
    step_order,
    step_key,
    step_label,
    phase_order,
    execution_mode,
    responsibility_type,
    responsibility_scope,
    required_unit_id,
    required_position_id,
    is_optional,
    is_system_step
) AS (
    VALUES
        (1, 'CREATOR', 'Initiative Creator', 1, 'SEQUENTIAL', 'CREATOR', 'NONE', NULL::BIGINT, NULL::BIGINT, FALSE, TRUE),
        (2, 'DEPARTMENT_HEAD', 'Department Head', 2, 'SEQUENTIAL', 'POSITION', 'REQUESTER_DEPARTMENT', NULL::BIGINT, (SELECT department_head_id FROM position_catalogue), FALSE, TRUE),
        (3, 'DEAN', 'Dean', 3, 'SEQUENTIAL', 'POSITION', 'REQUESTER_COLLEGE', NULL::BIGINT, (SELECT dean_id FROM position_catalogue), FALSE, TRUE),
        (4, 'VICE_PRESIDENT', 'Vice President / Office', 4, 'SEQUENTIAL', 'POSITION', 'FIXED_UNIT', (SELECT vp_unit_id FROM unit_catalogue), (SELECT vice_president_id FROM position_catalogue), FALSE, TRUE),
        (5, 'PRESIDENT', 'President / Office', 5, 'SEQUENTIAL', 'POSITION', 'FIXED_UNIT', (SELECT president_unit_id FROM unit_catalogue), (SELECT president_id FROM position_catalogue), FALSE, TRUE)
)
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    step_key,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional,
    step_label,
    phase_order,
    execution_mode,
    responsibility_type,
    responsibility_scope,
    required_permission_code,
    reminder_after_days,
    is_system_step,
    allow_revision
)
SELECT
    template.workflow_template_id,
    stage.step_order,
    stage.step_key,
    CASE
        WHEN stage.step_key = 'CREATOR'
            THEN 'CREATOR'::workflow_approval_type
        ELSE 'APPROVAL'::workflow_approval_type
    END,
    stage.required_unit_id,
    stage.required_position_id,
    stage.is_optional,
    stage.step_label,
    stage.phase_order,
    stage.execution_mode,
    stage.responsibility_type,
    stage.responsibility_scope,
    'APPROVE_INITIATIVE',
    3,
    stage.is_system_step,
    TRUE
FROM initiative_template template
CROSS JOIN stage_data stage
WHERE NOT EXISTS (
    SELECT 1
    FROM workflow_template_steps existing
    WHERE existing.workflow_template_id = template.workflow_template_id
)
ORDER BY stage.step_order;

UPDATE workflow_templates
SET updated_at = CURRENT_TIMESTAMP
WHERE updated_at IS NULL;
