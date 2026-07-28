-- Initiative approval workflow verification

SELECT
    wt.workflow_template_id,
    wt.name,
    wt.process_type,
    wt.is_active,
    wts.step_order,
    wts.approval_type,
    ou.code AS required_unit_code,
    p.name AS required_position
FROM workflow_templates wt
JOIN workflow_template_steps wts
  ON wts.workflow_template_id =
     wt.workflow_template_id
LEFT JOIN organizational_units ou
  ON ou.unit_id = wts.required_unit_id
LEFT JOIN positions p
  ON p.position_id = wts.required_position_id
WHERE wt.name = 'Initiative Approval'
ORDER BY wts.step_order;

SELECT
    r.role_name,
    p.permission_code
FROM roles r
JOIN role_permissions rp
  ON rp.role_id = r.role_id
JOIN permissions p
  ON p.permission_id = rp.permission_id
WHERE r.role_name IN (
    'Initiative Creator',
    'Initiative Approver'
)
ORDER BY r.role_name, p.permission_code;


-- Student creator role and academic affiliation.
SELECT
    u.user_id,
    u.email,
    sp.student_number,
    sp.program_name,
    department.code AS department_code,
    college.code AS college_code
FROM users u
JOIN student_profiles sp
  ON sp.user_id = u.user_id
JOIN organizational_units department
  ON department.unit_id = sp.department_id
LEFT JOIN organizational_units college
  ON college.unit_id = department.parent_unit_id
WHERE u.email = 'dev.student@uob.test';

SELECT
    r.role_name,
    p.permission_code
FROM roles r
JOIN role_permissions rp
  ON rp.role_id = r.role_id
JOIN permissions p
  ON p.permission_id = rp.permission_id
WHERE r.role_name = 'Student Initiative Creator'
ORDER BY p.permission_code;
