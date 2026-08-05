-- ============================================================
-- Administrator user management foundation
--
-- Reuses the existing normalized organization and RBAC model:
--   users
--   organizational_units (UNIVERSITY / OFFICE / COLLEGE / DEPARTMENT)
--   positions + user_positions
--   roles + permissions + role_permissions + user_roles
--
-- No parallel college/department/office tables are introduced.
-- ============================================================
CREATE UNIQUE INDEX IF NOT EXISTS ux_permissions_permission_code
    ON permissions (permission_code);

INSERT INTO permissions (permission_code, permission_name, description)
VALUES
    ('CREATE_AGREEMENT', 'Create Agreement', 'Create and start Agreement records and workflows'),
    ('EDIT_AGREEMENT', 'Edit Agreement', 'Edit Agreement records within the assigned scope'),
    ('SUBMIT_AGREEMENT', 'Submit Agreement', 'Submit Agreement records into workflow'),
    ('VIEW_AGREEMENT', 'View Agreement', 'View Agreement records within the assigned scope'),
    ('APPROVE_AGREEMENT', 'Approve Agreement', 'Approve assigned Agreement workflow steps'),
    ('REJECT_AGREEMENT', 'Reject Agreement', 'Reject or return assigned Agreement workflow steps'),
    ('CREATE_INITIATIVE', 'Create Initiative', 'Create and submit Initiative requests'),
    ('EDIT_INITIATIVE', 'Edit Initiative', 'Edit Initiative requests within the assigned scope'),
    ('APPROVE_INITIATIVE', 'Approve Initiative', 'Approve assigned Initiative workflow steps'),
    ('REJECT_INITIATIVE', 'Reject Initiative', 'Reject or return assigned Initiative workflow steps'),
    ('MANAGE_USERS', 'Manage Users', 'Manage user identity, access, roles, and organizational assignments')
ON CONFLICT (permission_code) DO UPDATE
SET
    permission_name = EXCLUDED.permission_name,
    description = COALESCE(permissions.description, EXCLUDED.description);

INSERT INTO roles (role_name, description)
VALUES
    ('Agreement Creator', 'Can create, edit, and submit Agreements'),
    ('Agreement Approver', 'Can review, approve, and reject assigned Agreement steps'),
    ('Initiative Creator', 'Can create and submit Initiative requests'),
    ('Initiative Approver', 'Can review, approve, and reject assigned Initiative steps'),
    ('System Administrator', 'Full system administration, including user management')
ON CONFLICT (role_name) DO UPDATE
SET description = EXCLUDED.description;

-- Creator roles carry the complete minimum permission set required by their
-- respective forms and submit actions. These role assignments are what the
-- administrator page uses for the explicit create Agreement / Initiative toggles.
INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code IN (
      'CREATE_AGREEMENT',
      'EDIT_AGREEMENT',
      'SUBMIT_AGREEMENT',
      'VIEW_AGREEMENT'
  )
WHERE role.role_name = 'Agreement Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code IN (
      'VIEW_AGREEMENT',
      'APPROVE_AGREEMENT',
      'REJECT_AGREEMENT'
  )
WHERE role.role_name = 'Agreement Approver'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code IN (
      'CREATE_INITIATIVE',
      'EDIT_INITIATIVE',
      'VIEW_AGREEMENT'
  )
WHERE role.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code IN (
      'APPROVE_INITIATIVE',
      'REJECT_INITIATIVE'
  )
WHERE role.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

-- The administrator role always receives every current permission. Future
-- migrations should continue using the same idempotent pattern for new permissions.
INSERT INTO role_permissions (role_id, permission_id)
SELECT role.role_id, permission.permission_id
FROM roles role
CROSS JOIN permissions permission
WHERE role.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

-- Ensure the workflow position catalogue contains the standard hierarchy
-- titles. Administrators may then assign these positions to any active unit.
INSERT INTO position_types (name, description)
VALUES
    ('Leadership', 'University leadership position'),
    ('Academic', 'Academic position'),
    ('Administrative', 'Administrative position')
ON CONFLICT (name) DO UPDATE
SET description = EXCLUDED.description;

INSERT INTO positions (position_type_id, name, description, is_unique)
SELECT position_type.position_type_id, values.name, values.description, values.is_unique
FROM (VALUES
    ('Administrative', 'System Administrator', 'System administrator assignment', FALSE),
    ('Leadership', 'President', 'University President', TRUE),
    ('Leadership', 'Vice President', 'University Vice President', TRUE),
    ('Leadership', 'Dean', 'College Dean', TRUE),
    ('Leadership', 'Department Head', 'Department Head', TRUE),
    ('Academic', 'Faculty Member', 'Faculty member', FALSE),
    ('Administrative', 'Vice President Office Staff', 'Regular employee in the Vice President Office', FALSE),
    ('Administrative', 'Vice President Office Delegate', 'Authorized workflow delegate in the Vice President Office', FALSE),
    ('Administrative', 'President Office Staff', 'Regular employee in the President Office', FALSE),
    ('Administrative', 'President Office Delegate', 'Authorized workflow delegate in the President Office', FALSE),
    ('Administrative', 'Legal Reviewer', 'Legal Office reviewer', FALSE),
    ('Administrative', 'Finance Reviewer', 'Financial Office reviewer', FALSE)
) AS values(type_name, name, description, is_unique)
JOIN position_types position_type
  ON position_type.name = values.type_name
ON CONFLICT (name) DO UPDATE
SET
    position_type_id = EXCLUDED.position_type_id,
    description = EXCLUDED.description,
    is_unique = EXCLUDED.is_unique;

CREATE INDEX IF NOT EXISTS idx_users_admin_name_search
    ON users (LOWER(last_name), LOWER(first_name));

CREATE INDEX IF NOT EXISTS idx_user_roles_admin_lookup
    ON user_roles (user_id, role_id);

CREATE INDEX IF NOT EXISTS idx_user_positions_admin_lookup
    ON user_positions (user_id, is_active, end_date, start_date DESC);

CREATE INDEX IF NOT EXISTS idx_organizational_units_admin_hierarchy
    ON organizational_units (parent_unit_id, unit_type, is_active, display_order);
