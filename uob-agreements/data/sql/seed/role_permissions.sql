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