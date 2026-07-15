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