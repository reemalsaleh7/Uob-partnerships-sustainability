CREATE INDEX idx_org_parent
ON organizational_units(parent_unit_id);

CREATE INDEX idx_org_type
ON organizational_units(unit_type);