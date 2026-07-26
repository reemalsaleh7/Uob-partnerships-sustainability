\encoding UTF8

BEGIN;

-- These indexes make the upserts below safe on databases created from older
-- revisions of the schema.
CREATE UNIQUE INDEX IF NOT EXISTS ux_permissions_permission_code
    ON permissions (permission_code);
CREATE UNIQUE INDEX IF NOT EXISTS ux_roles_role_name
    ON roles (role_name);
CREATE UNIQUE INDEX IF NOT EXISTS ux_organizational_units_code
    ON organizational_units (code);

INSERT INTO permissions (permission_code, permission_name, description)
VALUES
    ('CREATE_AGREEMENT', 'Create Agreement', 'Create Agreement drafts'),
    ('EDIT_AGREEMENT', 'Edit Agreement', 'Edit eligible Agreement drafts'),
    ('SUBMIT_AGREEMENT', 'Submit Agreement', 'Submit eligible Agreement drafts'),
    ('VIEW_AGREEMENT', 'View Agreement', 'View Agreements subject to record visibility'),
    ('DELETE_AGREEMENT', 'Delete Agreement', 'Delete eligible owned Agreement drafts'),
    ('APPROVE_AGREEMENT', 'Approve Agreement', 'Approve assigned Agreement workflow steps'),
    ('REJECT_AGREEMENT', 'Reject Agreement', 'Reject assigned Agreement workflow steps'),
    ('MANAGE_AGREEMENT_OPERATIONS', 'Manage Agreement operations', 'Finalize signing for eligible Agreements'),
    ('MANAGE_AGREEMENT_REPORTS', 'Manage Agreement performance reports', 'Prepare and submit owned Agreement reports'),
    ('REVIEW_AGREEMENT_REPORTS', 'Review Agreement performance reports', 'Accept or return submitted Agreement reports'),
    ('VIEW_AGREEMENT_DASHBOARD', 'View Agreement performance dashboard', 'View aggregate Agreement performance data'),
    ('CREATE_INITIATIVE', 'Create Initiative', 'Create Initiative drafts'),
    ('EDIT_INITIATIVE', 'Edit Initiative', 'Edit eligible Initiative drafts'),
    ('APPROVE_INITIATIVE', 'Approve Initiative', 'Approve assigned Initiative workflow steps'),
    ('REJECT_INITIATIVE', 'Reject Initiative', 'Reject assigned Initiative workflow steps'),
    ('VIEW_REPORTS', 'View Reports', 'View authorized reports'),
    ('MANAGE_USERS', 'Manage Users', 'Manage users, roles, and assignments')
ON CONFLICT (permission_code) DO UPDATE
SET permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO roles (role_name, description)
VALUES
    ('Agreement Creator', 'Can create and submit Agreements'),
    ('Agreement Approver', 'Can approve or reject assigned Agreements'),
    ('Initiative Creator', 'Can create Initiatives'),
    ('Initiative Approver', 'Can approve or reject assigned Initiatives'),
    ('System Administrator', 'Full system access')
ON CONFLICT (role_name) DO UPDATE
SET description = EXCLUDED.description;

INSERT INTO organizational_units (
    name, code, unit_type, parent_unit_id, display_order, is_active
)
VALUES (
    'University of Bahrain', 'UOB', 'UNIVERSITY', NULL, 1, TRUE
)
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

WITH units AS (
    SELECT *
    FROM (VALUES
        ('President Office', 'PRES', 'OFFICE', 'UOB', 2),
        ('Vice President Office', 'VP', 'OFFICE', 'UOB', 3),
        ('Legal Office', 'LEGAL', 'OFFICE', 'UOB', 4),
        ('Financial Office', 'FIN', 'OFFICE', 'UOB', 5),
        ('College of Information Technology', 'CIT', 'COLLEGE', 'UOB', 6)
    ) AS v(name, code, unit_type, parent_code, display_order)
)
INSERT INTO organizational_units (
    name, code, unit_type, parent_unit_id, display_order, is_active
)
SELECT
    u.name,
    u.code,
    u.unit_type::organizational_unit_type,
    parent.unit_id,
    u.display_order,
    TRUE
FROM units u
JOIN organizational_units parent ON parent.code = u.parent_code
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

WITH units AS (
    SELECT *
    FROM (VALUES
        ('Department of Computer Science', 'CS', 'DEPARTMENT', 'CIT', 7),
        ('Department of Information Systems', 'IS', 'DEPARTMENT', 'CIT', 8)
    ) AS v(name, code, unit_type, parent_code, display_order)
)
INSERT INTO organizational_units (
    name, code, unit_type, parent_unit_id, display_order, is_active
)
SELECT
    u.name,
    u.code,
    u.unit_type::organizational_unit_type,
    parent.unit_id,
    u.display_order,
    TRUE
FROM units u
JOIN organizational_units parent ON parent.code = u.parent_code
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p ON (
    (r.role_name = 'Agreement Creator' AND p.permission_code IN (
        'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
        'VIEW_AGREEMENT', 'DELETE_AGREEMENT',
        'MANAGE_AGREEMENT_OPERATIONS', 'MANAGE_AGREEMENT_REPORTS'
    ))
    OR
    (r.role_name = 'Agreement Approver' AND p.permission_code IN (
        'VIEW_AGREEMENT', 'APPROVE_AGREEMENT', 'REJECT_AGREEMENT',
        'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
    ))
    OR
    (r.role_name = 'Initiative Creator' AND p.permission_code IN (
        'CREATE_INITIATIVE', 'EDIT_INITIATIVE', 'VIEW_REPORTS',
        'VIEW_AGREEMENT'
    ))
    OR
    (r.role_name = 'Initiative Approver' AND p.permission_code IN (
        'APPROVE_INITIATIVE', 'REJECT_INITIATIVE', 'VIEW_REPORTS'
    ))
    OR
    (r.role_name = 'System Administrator')
)
ON CONFLICT DO NOTHING;

COMMIT;
