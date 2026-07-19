CREATE INDEX IF NOT EXISTS idx_org_parent
    ON organizational_units(parent_unit_id);

CREATE INDEX IF NOT EXISTS idx_org_type
    ON organizational_units(unit_type);

CREATE INDEX IF NOT EXISTS idx_users_email
    ON users(email);

CREATE INDEX IF NOT EXISTS idx_agreements_status
    ON agreements(status);

CREATE INDEX IF NOT EXISTS idx_workflow_instances_status
    ON workflow_instances(status);

CREATE INDEX IF NOT EXISTS idx_workflow_instances_entity
    ON workflow_instances(entity_type, entity_id);

CREATE UNIQUE INDEX IF NOT EXISTS ux_active_workflow_entity
    ON workflow_instances(entity_type, entity_id)
    WHERE status = 'IN_PROGRESS';

CREATE UNIQUE INDEX IF NOT EXISTS ux_workflow_template_step_key
    ON workflow_template_steps(
        workflow_template_id,
        step_key
    )
    WHERE step_key IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS ux_workflow_instance_template_step
    ON workflow_instance_steps(
        workflow_instance_id,
        template_step_id
    )
    WHERE template_step_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_workflow_instance_steps_active
    ON workflow_instance_steps(
        workflow_instance_id,
        status,
        step_order
    );

CREATE UNIQUE INDEX IF NOT EXISTS ux_active_workflow_step_assignment
    ON workflow_step_assignments(
        workflow_instance_step_id,
        user_id
    )
    WHERE is_active = TRUE;

CREATE INDEX IF NOT EXISTS ix_workflow_assignments_user_active
    ON workflow_step_assignments(
        user_id,
        is_active,
        workflow_instance_step_id
    );

CREATE INDEX IF NOT EXISTS idx_workflow_instance_steps_status
    ON workflow_instance_steps(status);

-- Lookup index only. Conditional uniqueness is enforced by
-- check_unique_position() for positions marked is_unique.
CREATE INDEX IF NOT EXISTS idx_active_position_assignment
    ON user_positions(position_id, unit_id)
    WHERE is_active = TRUE;