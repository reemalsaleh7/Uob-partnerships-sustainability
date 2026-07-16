INSERT INTO workflow_templates
(name, process_type)
VALUES
('Agreement Approval', 'AGREEMENT'),
('Initiative Approval', 'INITIATIVE');

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 1, 'CREATOR', NULL, NULL, FALSE
FROM workflow_templates
WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 2, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'VP'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 3, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'LEGAL'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 4, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'FIN'), NULL, TRUE
FROM workflow_templates
WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 5, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'PRES'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 1, 'CREATOR', NULL, NULL, FALSE
FROM workflow_templates
WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 2, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'CS'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 3, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'CIT'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 4, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'VP'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps
(workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 5, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'PRES'), NULL, FALSE
FROM workflow_templates
WHERE name = 'Initiative Approval';