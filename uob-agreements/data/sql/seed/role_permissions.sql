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
