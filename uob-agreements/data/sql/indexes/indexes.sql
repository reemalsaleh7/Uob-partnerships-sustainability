CREATE INDEX idx_org_parent
ON organizational_units(parent_unit_id);

CREATE INDEX idx_org_type
ON organizational_units(unit_type);

CREATE INDEX idx_users_email
ON users(email);

CREATE INDEX idx_agreements_status
ON agreements(status);

CREATE INDEX idx_workflow_instances_status
ON workflow_instances(status);

CREATE INDEX idx_workflow_instances_entity
ON workflow_instances(entity_type, entity_id);

CREATE INDEX idx_workflow_step_assignments_user
ON workflow_step_assignments(user_id);

CREATE UNIQUE INDEX uq_active_assignment_per_step
ON workflow_step_assignments(workflow_instance_step_id)
WHERE is_active = TRUE;

CREATE INDEX idx_workflow_instance_steps_status
ON workflow_instance_steps(status);

CREATE UNIQUE INDEX uq_active_position_assignment
ON user_positions(position_id, unit_id)
WHERE is_active = TRUE;