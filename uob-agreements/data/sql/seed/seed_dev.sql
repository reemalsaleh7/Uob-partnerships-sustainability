-- Development-only fixtures. Run after deploy.sql on an empty or development database.
-- All accounts use the password: DevPassword123!

BEGIN;

-- Older local databases may predate these uniqueness constraints, which are
-- present in the current table definitions and required for idempotent seeds.
CREATE UNIQUE INDEX IF NOT EXISTS ux_permissions_permission_code
    ON permissions (permission_code);
CREATE UNIQUE INDEX IF NOT EXISTS ux_organizational_units_code
    ON organizational_units (code);

UPDATE permissions p
SET permission_name = v.permission_name
FROM (VALUES
    ('VIEW_AGREEMENT', 'View Agreement'),
    ('DELETE_AGREEMENT', 'Delete Agreement')
) AS v(permission_code, permission_name)
WHERE p.permission_code = v.permission_code;

INSERT INTO permissions (permission_code, permission_name)
SELECT v.permission_code, v.permission_name
FROM (VALUES
    ('VIEW_AGREEMENT', 'View Agreement'),
    ('DELETE_AGREEMENT', 'Delete Agreement')
) AS v(permission_code, permission_name)
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.permission_code = v.permission_code
);

INSERT INTO position_types (name, description)
VALUES
    ('Leadership', 'University leadership position'),
    ('Academic', 'Academic position'),
    ('Administrative', 'Administrative position')
ON CONFLICT (name) DO UPDATE
SET description = EXCLUDED.description;

-- The VP office is the parent for the development academic approval chain.
INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
VALUES ('University of Bahrain', 'UOB', 'UNIVERSITY', NULL, 1)
ON CONFLICT (code) DO UPDATE
SET name = EXCLUDED.name, unit_type = EXCLUDED.unit_type, parent_unit_id = EXCLUDED.parent_unit_id, display_order = EXCLUDED.display_order, is_active = TRUE;

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'President Office', 'PRES', 'OFFICE', unit_id, 2
FROM organizational_units WHERE code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET parent_unit_id = EXCLUDED.parent_unit_id, display_order = EXCLUDED.display_order, is_active = TRUE;

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Vice President Office', 'VP', 'OFFICE', unit_id, 3
FROM organizational_units WHERE code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET parent_unit_id = EXCLUDED.parent_unit_id, display_order = EXCLUDED.display_order, is_active = TRUE;

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'College of Information Technology', 'CIT', 'COLLEGE', unit_id, 4
FROM organizational_units WHERE code = 'VP'
ON CONFLICT (code) DO UPDATE
SET parent_unit_id = EXCLUDED.parent_unit_id, display_order = EXCLUDED.display_order, is_active = TRUE;

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Department of Computer Science', 'CS', 'DEPARTMENT', unit_id, 5
FROM organizational_units WHERE code = 'CIT'
ON CONFLICT (code) DO UPDATE
SET parent_unit_id = EXCLUDED.parent_unit_id, display_order = EXCLUDED.display_order, is_active = TRUE;

INSERT INTO positions (position_type_id, name, description, is_unique)
SELECT pt.position_type_id, v.name, v.description, v.is_unique
FROM (VALUES
    ('Administrative', 'System Administrator', 'Development system administrator', FALSE),
    ('Leadership', 'President', 'University president', TRUE),
    ('Leadership', 'Vice President', 'Vice president', TRUE),
    ('Leadership', 'Dean', 'College dean', TRUE),
    ('Leadership', 'Department Head', 'Department head', TRUE),
    ('Academic', 'Faculty Member', 'Faculty agreement creator', FALSE)
) AS v(type_name, name, description, is_unique)
JOIN position_types pt ON pt.name = v.type_name
ON CONFLICT (name) DO UPDATE
SET position_type_id = EXCLUDED.position_type_id, description = EXCLUDED.description, is_unique = EXCLUDED.is_unique;

INSERT INTO users (university_id, first_name, last_name, email, password_hash, is_active)
VALUES
    ('DEV-ADMIN-001', 'Dev', 'Administrator', 'dev.admin@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE),
    ('DEV-PRES-001', 'Dev', 'President', 'dev.president@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE),
    ('DEV-VP-001', 'Dev', 'VicePresident', 'dev.vp@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE),
    ('DEV-DEAN-001', 'Dev', 'Dean', 'dev.dean@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE),
    ('DEV-HEAD-001', 'Dev', 'DepartmentHead', 'dev.head@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE),
    ('DEV-FAC-001', 'Dev', 'Faculty', 'dev.faculty@uob.test', '$2y$10$A4Vgkc3iXYoQKQCcmepNTOU99X6z9/RHiDtMvS9r.i69AxW7tjPOq', TRUE)
ON CONFLICT (email) DO UPDATE
SET university_id = EXCLUDED.university_id, first_name = EXCLUDED.first_name, last_name = EXCLUDED.last_name,
    password_hash = EXCLUDED.password_hash, is_active = TRUE;

DELETE FROM user_roles
WHERE user_id IN (SELECT user_id FROM users WHERE email LIKE 'dev.%@uob.test');

INSERT INTO user_roles (user_id, role_id)
SELECT u.user_id, r.role_id
FROM users u
JOIN roles r ON r.role_name = CASE
    WHEN u.email = 'dev.admin@uob.test' THEN 'System Administrator'
    WHEN u.email IN ('dev.faculty@uob.test', 'dev.head@uob.test') THEN 'Agreement Creator'
    ELSE 'Agreement Approver'
END
WHERE u.email LIKE 'dev.%@uob.test';

-- Give the administrator every current permission and creators the complete Agreement CRUD set.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code IN ('CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT', 'VIEW_AGREEMENT', 'DELETE_AGREEMENT')
WHERE r.role_name = 'Agreement Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code IN ('VIEW_AGREEMENT', 'APPROVE_AGREEMENT', 'REJECT_AGREEMENT')
WHERE r.role_name = 'Agreement Approver'
ON CONFLICT DO NOTHING;

DELETE FROM user_positions
WHERE user_id IN (SELECT user_id FROM users WHERE email LIKE 'dev.%@uob.test');

INSERT INTO user_positions (user_id, position_id, unit_id, start_date, is_active)
SELECT u.user_id, p.position_id, ou.unit_id, CURRENT_DATE, TRUE
FROM (VALUES
    ('dev.admin@uob.test', 'System Administrator', 'UOB'),
    ('dev.president@uob.test', 'President', 'PRES'),
    ('dev.vp@uob.test', 'Vice President', 'VP'),
    ('dev.dean@uob.test', 'Dean', 'CIT'),
    ('dev.head@uob.test', 'Department Head', 'CS'),
    ('dev.faculty@uob.test', 'Faculty Member', 'CS')
) AS v(email, position_name, unit_code)
JOIN users u ON u.email = v.email
JOIN positions p ON p.name = v.position_name
JOIN organizational_units ou ON ou.code = v.unit_code;

INSERT INTO partners (organization_name, partner_type, country, email, is_active)
SELECT v.organization_name, v.partner_type, v.country, v.email, TRUE
FROM (VALUES
    ('Bahrain Institute of Technology', 'University', 'Bahrain', 'contact@bit.test'),
    ('Gulf Research Centre', 'Research Center', 'Bahrain', 'contact@grc.test'),
    ('Future Skills Foundation', 'Nonprofit', 'Bahrain', 'contact@fsf.test')
) AS v(organization_name, partner_type, country, email)
WHERE NOT EXISTS (SELECT 1 FROM partners p WHERE p.organization_name = v.organization_name);

COMMIT;
