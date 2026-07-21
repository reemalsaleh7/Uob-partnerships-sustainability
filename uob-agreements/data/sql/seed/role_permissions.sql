INSERT INTO role_permissions

(role_id, permission_id)


SELECT

r.role_id,

p.permission_id


FROM roles r, permissions p


WHERE r.role_name='Agreement Approver'

AND p.permission_code IN

(
'APPROVE_AGREEMENT',
'REJECT_AGREEMENT'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
      'VIEW_AGREEMENT', 'DELETE_AGREEMENT'
  )
WHERE r.role_name = 'Agreement Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'VIEW_AGREEMENT'
WHERE r.role_name = 'Agreement Approver'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'MANAGE_AGREEMENT_OPERATIONS'
WHERE r.role_name IN ('Agreement Creator', 'System Administrator')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'MANAGE_AGREEMENT_REPORTS'
WHERE r.role_name IN ('Agreement Creator', 'System Administrator')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
  )
WHERE r.role_name IN ('Agreement Approver', 'System Administrator')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
      'VIEW_AGREEMENT', 'DELETE_AGREEMENT',
      'APPROVE_AGREEMENT', 'REJECT_AGREEMENT',
      'MANAGE_AGREEMENT_OPERATIONS', 'MANAGE_AGREEMENT_REPORTS',
      'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
  )
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_INITIATIVE', 'EDIT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'APPROVE_INITIATIVE', 'REJECT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_INITIATIVE', 'EDIT_INITIATIVE',
      'APPROVE_INITIATIVE', 'REJECT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;
