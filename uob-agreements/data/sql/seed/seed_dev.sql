-- Development-only fixtures. Run after deploy.sql on an empty or development database.
-- Local development accounts use the password: UobDev2026!

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
SELECT 'College of Information Technology', 'CIT', 'COLLEGE', unit_id, 6
FROM organizational_units WHERE code = 'UOB'
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
    ('Academic', 'Faculty Member', 'Faculty agreement creator', FALSE),
    ('Administrative', 'Legal Reviewer', 'Legal Office agreement reviewer', FALSE),
    ('Administrative', 'Finance Reviewer', 'Financial Office agreement reviewer', FALSE)
) AS v(type_name, name, description, is_unique)
JOIN position_types pt ON pt.name = v.type_name
ON CONFLICT (name) DO UPDATE
SET position_type_id = EXCLUDED.position_type_id, description = EXCLUDED.description, is_unique = EXCLUDED.is_unique;

-- Reconcile development identities by either unique key. Older installations
-- sometimes have the expected DEV university_id under an obsolete email, so
-- an email-only upsert is not safe or rerunnable.
CREATE TEMP TABLE uob_dev_users (
    university_id VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
) ON COMMIT DROP;

INSERT INTO uob_dev_users (
    university_id, first_name, last_name, email, password_hash
)
VALUES
    ('DEV-ADMIN-001', 'Dev', 'Administrator', 'dev.admin@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-PRES-001', 'Dev', 'President', 'dev.president@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-VP-001', 'Dev', 'VicePresident', 'dev.vp@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-DEAN-001', 'Dev', 'Dean', 'dev.dean@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-HEAD-001', 'Dev', 'DepartmentHead', 'dev.head@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-FAC-001', 'Dev', 'Faculty', 'dev.faculty@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-LEGAL-001', 'Dev', 'LegalReviewer', 'dev.legal@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'),
    ('DEV-FIN-001', 'Dev', 'FinanceReviewer', 'dev.finance@uob.test', '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66');

-- Stop rather than merge or delete accounts if the two unique identifiers
-- point to different rows. That requires a deliberate data-repair decision.
DO $$
DECLARE
    conflicting_ids TEXT;
BEGIN
    SELECT string_agg(d.university_id, ', ' ORDER BY d.university_id)
    INTO conflicting_ids
    FROM uob_dev_users d
    JOIN users by_id
      ON by_id.university_id = d.university_id
    JOIN users by_email
      ON by_email.email = d.email
    WHERE by_id.user_id <> by_email.user_id;

    IF conflicting_ids IS NOT NULL THEN
        RAISE EXCEPTION
            'Development identity collision for: %. The university ID and email belong to different user rows; no accounts were changed.',
            conflicting_ids;
    END IF;
END
$$;

UPDATE users u
SET
    university_id = d.university_id,
    first_name = d.first_name,
    last_name = d.last_name,
    email = d.email,
    password_hash = d.password_hash,
    is_active = TRUE
FROM uob_dev_users d
WHERE u.university_id = d.university_id
   OR u.email = d.email;

INSERT INTO users (
    university_id, first_name, last_name, email, password_hash, is_active
)
SELECT
    d.university_id,
    d.first_name,
    d.last_name,
    d.email,
    d.password_hash,
    TRUE
FROM uob_dev_users d
WHERE NOT EXISTS (
    SELECT 1
    FROM users u
    WHERE u.university_id = d.university_id
       OR u.email = d.email
);

DO $$
BEGIN
    IF (
        SELECT count(*)
        FROM uob_dev_users d
        JOIN users u
          ON u.university_id = d.university_id
         AND u.email = d.email
         AND u.is_active = TRUE
    ) <> 8 THEN
        RAISE EXCEPTION
            'Development user reconciliation did not produce all eight expected active accounts.';
    END IF;
END
$$;

DELETE FROM user_roles
WHERE user_id IN (
    SELECT u.user_id
    FROM users u
    JOIN uob_dev_users d ON d.university_id = u.university_id
);

INSERT INTO user_roles (user_id, role_id)
SELECT
    u.user_id,
    r.role_id
FROM (
    VALUES
        ('dev.admin@uob.test', 'System Administrator'),

        ('dev.president@uob.test', 'Agreement Creator'),
        ('dev.president@uob.test', 'Agreement Approver'),
        ('dev.president@uob.test', 'Initiative Approver'),

        ('dev.vp@uob.test', 'Agreement Creator'),
        ('dev.vp@uob.test', 'Agreement Approver'),
        ('dev.vp@uob.test', 'Initiative Approver'),

        ('dev.dean@uob.test', 'Agreement Creator'),
        ('dev.dean@uob.test', 'Initiative Approver'),

        ('dev.legal@uob.test', 'Agreement Approver'),
        ('dev.finance@uob.test', 'Agreement Approver'),

        ('dev.head@uob.test', 'Initiative Creator'),
        ('dev.head@uob.test', 'Initiative Approver'),
        ('dev.faculty@uob.test', 'Initiative Creator')
) AS assignments(email, role_name)
JOIN users u
    ON u.email = assignments.email
JOIN roles r
    ON r.role_name = assignments.role_name;

INSERT INTO organizational_units (
    name,
    code,
    unit_type,
    parent_unit_id,
    display_order
)
SELECT 'Legal Office', 'LEGAL', 'OFFICE', unit_id, 4
FROM organizational_units
WHERE code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

INSERT INTO organizational_units (
    name,
    code,
    unit_type,
    parent_unit_id,
    display_order
)
SELECT 'Financial Office', 'FIN', 'OFFICE', unit_id, 5
FROM organizational_units
WHERE code = 'UOB'
ON CONFLICT (code) DO UPDATE
SET
    name = EXCLUDED.name,
    parent_unit_id = EXCLUDED.parent_unit_id,
    display_order = EXCLUDED.display_order,
    is_active = TRUE;

-- Give the administrator every current permission and creators the complete Agreement CRUD set.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code IN ('CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT', 'VIEW_AGREEMENT', 'DELETE_AGREEMENT', 'MANAGE_AGREEMENT_OPERATIONS')
WHERE r.role_name = 'Agreement Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code IN ('VIEW_AGREEMENT', 'APPROVE_AGREEMENT', 'REJECT_AGREEMENT')
WHERE r.role_name = 'Agreement Approver'
ON CONFLICT DO NOTHING;

-- Initiative creators may discover active University Agreements and select one
-- as the partnership context for a proposed Initiative.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code = 'VIEW_AGREEMENT'
WHERE r.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r JOIN permissions p
  ON p.permission_code IN (
      'APPROVE_INITIATIVE', 'REJECT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

CREATE TEMP TABLE uob_dev_positions (
    email VARCHAR(255) PRIMARY KEY,
    position_name VARCHAR(255) NOT NULL,
    unit_code VARCHAR(50) NOT NULL
) ON COMMIT DROP;

INSERT INTO uob_dev_positions (email, position_name, unit_code)
VALUES
    ('dev.admin@uob.test', 'System Administrator', 'UOB'),
    ('dev.president@uob.test', 'President', 'PRES'),
    ('dev.vp@uob.test', 'Vice President', 'VP'),
    ('dev.dean@uob.test', 'Dean', 'CIT'),
    ('dev.head@uob.test', 'Department Head', 'CS'),
    ('dev.faculty@uob.test', 'Faculty Member', 'CS'),
    ('dev.legal@uob.test', 'Legal Reviewer', 'LEGAL'),
    ('dev.finance@uob.test', 'Finance Reviewer', 'FIN');

-- Preserve assignment history: close obsolete active development assignments
-- instead of deleting them.
UPDATE user_positions up
SET
    is_active = FALSE,
    end_date = COALESCE(
        up.end_date,
        GREATEST(CURRENT_DATE, up.start_date)
    )
FROM users u
JOIN uob_dev_users du ON du.university_id = u.university_id
WHERE up.user_id = u.user_id
  AND up.is_active = TRUE
  AND NOT EXISTS (
      SELECT 1
      FROM uob_dev_positions expected
      JOIN positions p ON p.name = expected.position_name
      JOIN organizational_units ou ON ou.code = expected.unit_code
      WHERE expected.email = u.email
        AND p.position_id = up.position_id
        AND ou.unit_id = up.unit_id
  );

-- A local fixture must own each unique workflow position. Close a conflicting
-- active local assignment before adding the deterministic development actor.
UPDATE user_positions up
SET
    is_active = FALSE,
    end_date = COALESCE(
        up.end_date,
        GREATEST(CURRENT_DATE, up.start_date)
    )
FROM positions p,
     organizational_units ou,
     uob_dev_positions expected,
     users target
WHERE p.name = expected.position_name
  AND ou.code = expected.unit_code
  AND target.email = expected.email
  AND p.is_unique = TRUE
  AND up.position_id = p.position_id
  AND up.unit_id = ou.unit_id
  AND up.is_active = TRUE
  AND up.user_id <> target.user_id;

-- Older local seeds may have created duplicate non-unique assignments. Retain
-- the newest active row and close the others before verification.
WITH ranked_assignments AS (
    SELECT
        up.user_position_id,
        row_number() OVER (
            PARTITION BY up.user_id, up.position_id, up.unit_id
            ORDER BY up.start_date DESC, up.user_position_id DESC
        ) AS row_number
    FROM user_positions up
    JOIN users u ON u.user_id = up.user_id
    JOIN uob_dev_users du ON du.university_id = u.university_id
    WHERE up.is_active = TRUE
)
UPDATE user_positions up
SET
    is_active = FALSE,
    end_date = COALESCE(
        up.end_date,
        GREATEST(CURRENT_DATE, up.start_date)
    )
FROM ranked_assignments ranked
WHERE ranked.user_position_id = up.user_position_id
  AND ranked.row_number > 1;

INSERT INTO user_positions (user_id, position_id, unit_id, start_date, is_active)
SELECT u.user_id, p.position_id, ou.unit_id, CURRENT_DATE, TRUE
FROM uob_dev_positions v
JOIN users u ON u.email = v.email
JOIN positions p ON p.name = v.position_name
JOIN organizational_units ou ON ou.code = v.unit_code
WHERE NOT EXISTS (
    SELECT 1
    FROM user_positions existing
    WHERE existing.user_id = u.user_id
      AND existing.position_id = p.position_id
      AND existing.unit_id = ou.unit_id
      AND existing.is_active = TRUE
);

DO $$
BEGIN
    IF (
        SELECT count(*)
        FROM uob_dev_positions expected
        JOIN users u ON u.email = expected.email
        JOIN positions p ON p.name = expected.position_name
        JOIN organizational_units ou ON ou.code = expected.unit_code
        JOIN user_positions up
          ON up.user_id = u.user_id
         AND up.position_id = p.position_id
         AND up.unit_id = ou.unit_id
         AND up.is_active = TRUE
         AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
    ) <> 8 THEN
        RAISE EXCEPTION
            'Development position reconciliation did not produce all eight expected active assignments.';
    END IF;
END
$$;

UPDATE partners p
SET
    partner_type = v.partner_type,
    country = v.country,
    email = v.email,
    is_active = TRUE
FROM (VALUES
    ('Bahrain Institute of Technology', 'University', 'Bahrain', 'contact@bit.test'),
    ('Gulf Research Centre', 'Research Center', 'Bahrain', 'contact@grc.test'),
    ('Future Skills Foundation', 'Nonprofit', 'Bahrain', 'contact@fsf.test')
) AS v(organization_name, partner_type, country, email)
WHERE p.organization_name = v.organization_name;

INSERT INTO partners (organization_name, partner_type, country, email, is_active)
SELECT v.organization_name, v.partner_type, v.country, v.email, TRUE
FROM (VALUES
    ('Bahrain Institute of Technology', 'University', 'Bahrain', 'contact@bit.test'),
    ('Gulf Research Centre', 'Research Center', 'Bahrain', 'contact@grc.test'),
    ('Future Skills Foundation', 'Nonprofit', 'Bahrain', 'contact@fsf.test')
) AS v(organization_name, partner_type, country, email)
WHERE NOT EXISTS (SELECT 1 FROM partners p WHERE p.organization_name = v.organization_name);

COMMIT;
