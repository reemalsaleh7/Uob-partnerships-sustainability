-- University of Bahrain development organizational structure.
-- Development/test data only.
-- Passwords and development accounts are added in the next section.

BEGIN;

-- ============================================================
-- Root university
-- ============================================================

INSERT INTO organizational_units (
    display_order,
    name,
    code,
    unit_type,
    parent_unit_id,
    is_active
)
VALUES (
    0,
    'University of Bahrain',
    'UOB',
    'UNIVERSITY',
    NULL,
    TRUE
)
ON CONFLICT (code)
DO UPDATE SET
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = NULL,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- University offices / administrative units
-- ============================================================

WITH university AS (
    SELECT unit_id
    FROM organizational_units
    WHERE code = 'UOB'
)
INSERT INTO organizational_units (
    display_order,
    name,
    code,
    unit_type,
    parent_unit_id,
    is_active
)
SELECT
    source.display_order,
    source.name,
    source.code,
    'OFFICE'::organizational_unit_type,
    university.unit_id,
    TRUE
FROM (
    VALUES
        (10,  'President''s Office',                                      'PRES'),
        (20,  'VP Office for Partnerships & Development',                 'VP'),
        (30,  'VP Office for Academic Affairs',                           'VPAA'),
        (40,  'General Director of Administrative Services Office',       'GDAS'),
        (50,  'Deanship of Graduate Studies & Scientific Research',       'DGSSR'),
        (60,  'Deanship of Student Affairs',                              'DSA'),
        (70,  'Deanship of Admission & Registration',                     'DAR'),
        (80,  'University Rankings Committee',                            'URC'),
        (90,  'Governance Committee (Policies & Procedures)',             'GOV'),
        (100, 'Quality Assurance & Accreditation Center',                 'QAAC'),
        (110, 'Business Incubator Center',                                'BIC'),
        (120, 'IT & Digital Learning Directorate',                        'ITDL'),
        (130, 'English Language Center',                                  'ELC'),
        (140, 'Media Center',                                             'MEDIA'),
        (150, 'Community Service & Continuing Education Center',          'CSCEC'),
        (160, 'Teaching Excellence & Leadership Unit',                    'TELU'),
        (170, 'Human Resources Directorate',                              'HR'),
        (180, 'Public Relations & Media Directorate',                     'PRM'),
        (190, 'General Services Directorate',                             'GSD'),
        (200, 'Library & Information Services Directorate',               'LISD'),
        (210, 'Security & Safety Directorate',                            'SSD'),
        (220, 'Finance & Budget Directorate',                             'FBD'),
        (230, 'Procurement Directorate',                                  'PROC'),
        (240, 'Assets & Stores Directorate',                              'ASD'),
        (250, 'Buildings & Maintenance Directorate',                      'BMD'),
        (260, 'Alumni Affairs Directorate',                               'ALUM')
) AS source(display_order, name, code)
CROSS JOIN university
ON CONFLICT (code)
DO UPDATE SET
    display_order = EXCLUDED.display_order,
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- Keep legacy workflow offices required by existing Agreement workflow.
WITH university AS (
    SELECT unit_id
    FROM organizational_units
    WHERE code = 'UOB'
)
INSERT INTO organizational_units (
    display_order,
    name,
    code,
    unit_type,
    parent_unit_id,
    is_active
)
SELECT
    source.display_order,
    source.name,
    source.code,
    'OFFICE'::organizational_unit_type,
    university.unit_id,
    TRUE
FROM (
    VALUES
        (270, 'Legal Office',     'LEGAL'),
        (280, 'Financial Office', 'FIN')
) AS source(display_order, name, code)
CROSS JOIN university
ON CONFLICT (code)
DO UPDATE SET
    display_order = EXCLUDED.display_order,
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- Colleges
-- ============================================================

WITH university AS (
    SELECT unit_id
    FROM organizational_units
    WHERE code = 'UOB'
)
INSERT INTO organizational_units (
    display_order,
    name,
    code,
    unit_type,
    parent_unit_id,
    is_active
)
SELECT
    source.display_order,
    source.name,
    source.code,
    'COLLEGE'::organizational_unit_type,
    university.unit_id,
    TRUE
FROM (
    VALUES
        (1000, 'College of Arts',                                      'ARTS'),
        (1010, 'College of Science',                                   'SCI'),
        (1020, 'College of Business Administration',                   'CBA'),
        (1030, 'Mohammed Jaber Al Ansari College for Teachers',        'BTC'),
        (1040, 'College of Applied Studies',                            'CAS'),
        (1050, 'College of Information Technology',                    'CIT'),
        (1060, 'College of Law',                                       'LAW'),
        (1070, 'College of Health & Sport Sciences',                   'CHSS'),
        (1080, 'College of Engineering',                               'ENG')
) AS source(display_order, name, code)
CROSS JOIN university
ON CONFLICT (code)
DO UPDATE SET
    display_order = EXCLUDED.display_order,
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- Academic departments
-- ============================================================

WITH departments (
    display_order,
    name,
    code,
    college_code
) AS (
    VALUES
        -- College of Arts
        (2000, 'Arabic and Islamic Studies',                       'ARTS-AIS',   'ARTS'),
        (2010, 'Mass Communication, Tourism and Fine Arts',        'ARTS-MCTFA', 'ARTS'),
        (2020, 'English Language and Literature',                  'ARTS-ELL',   'ARTS'),
        (2030, 'Psychology',                                       'ARTS-PSY',   'ARTS'),
        (2040, 'Social Sciences',                                  'ARTS-SS',    'ARTS'),

        -- College of Science
        (2100, 'Mathematics',                                      'SCI-MATH',   'SCI'),
        (2110, 'Chemistry',                                        'SCI-CHEM',   'SCI'),
        (2120, 'Biology',                                          'SCI-BIO',    'SCI'),
        (2130, 'Physics',                                          'SCI-PHYS',   'SCI'),

        -- College of Business Administration
        (2200, 'Accounting',                                       'CBA-ACC',    'CBA'),
        (2210, 'Economics & Finance',                              'CBA-EF',     'CBA'),
        (2220, 'Management and Marketing',                         'CBA-MM',     'CBA'),
        (2230, 'Islamic Banking',                                  'CBA-IB',     'CBA'),

        -- Mohammed Jaber Al Ansari College for Teachers
        (2300, 'Initial Teacher Education',                        'BTC-ITE',    'BTC'),
        (2310, 'Arabic & Islamic Studies',                         'BTC-AIS',    'BTC'),
        (2320, 'English Language Education',                       'BTC-ELE',    'BTC'),
        (2330, 'Mathematics & Science',                            'BTC-MS',     'BTC'),
        (2340, 'Education Studies',                                'BTC-ES',     'BTC'),

        -- College of Applied Studies
        (2400, 'Engineering Programs',                             'CAS-ENGP',   'CAS'),
        (2410, 'Administrative and Technical Programs',            'CAS-ATP',    'CAS'),

        -- College of Information Technology
        (2500, 'Computer Science',                                 'CS',         'CIT'),
        (2510, 'Information Systems',                              'IS',         'CIT'),
        (2520, 'Computer Engineering',                             'CIT-CE',     'CIT'),

        -- College of Law
        (2600, 'Public Law',                                       'LAW-PUB',    'LAW'),
        (2610, 'Private Law',                                      'LAW-PRIV',   'LAW'),

        -- College of Health & Sport Sciences
        (2700, 'Nursing',                                          'CHSS-NUR',   'CHSS'),
        (2710, 'Allied Health',                                    'CHSS-AH',    'CHSS'),
        (2720, 'Physical Education',                               'CHSS-PE',    'CHSS'),

        -- College of Engineering
        (2800, 'Civil Engineering',                               'ENG-CIV',    'ENG'),
        (2810, 'Architecture & Interior Design',                   'ENG-AID',    'ENG'),
        (2820, 'Chemical Engineering',                             'ENG-CHEM',   'ENG'),
        (2830, 'Electrical & Electronics Engineering',             'ENG-EEE',    'ENG'),
        (2840, 'Mechanical Engineering',                           'ENG-MECH',   'ENG')
)
INSERT INTO organizational_units (
    display_order,
    name,
    code,
    unit_type,
    parent_unit_id,
    is_active
)
SELECT
    departments.display_order,
    departments.name,
    departments.code,
    'DEPARTMENT'::organizational_unit_type,
    college.unit_id,
    TRUE
FROM departments
JOIN organizational_units college
  ON college.code = departments.college_code
 AND college.unit_type = 'COLLEGE'
ON CONFLICT (code)
DO UPDATE SET
    display_order = EXCLUDED.display_order,
    name = EXCLUDED.name,
    unit_type = EXCLUDED.unit_type,
    parent_unit_id = EXCLUDED.parent_unit_id,
    is_active = TRUE,
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- Development users, roles, and positions
-- ============================================================

-- Local development password for all accounts below:
-- UobDev2026!

INSERT INTO position_types (name, description)
VALUES
    ('Leadership', 'University leadership position'),
    ('Academic', 'Academic position'),
    ('Administrative', 'Administrative position')
ON CONFLICT (name)
DO UPDATE SET
    description = EXCLUDED.description;

-- Normalize legacy office-member names if an older local database has them.
UPDATE positions
SET name = 'Vice President Office Delegate'
WHERE LOWER(name) = 'vice president office member'
  AND NOT EXISTS (
      SELECT 1
      FROM positions
      WHERE LOWER(name) = 'vice president office delegate'
  );

UPDATE positions
SET name = 'President Office Delegate'
WHERE LOWER(name) = 'president office member'
  AND NOT EXISTS (
      SELECT 1
      FROM positions
      WHERE LOWER(name) = 'president office delegate'
  );

-- Ensure all positions required by this development structure exist.
INSERT INTO positions (
    position_type_id,
    name,
    description,
    is_unique
)
SELECT
    pt.position_type_id,
    source.name,
    source.description,
    source.is_unique
FROM (
    VALUES
        (
            'Leadership',
            'Dean',
            'College dean',
            TRUE
        ),
        (
            'Leadership',
            'Department Head',
            'Academic department head',
            TRUE
        ),
        (
            'Academic',
            'Faculty Member',
            'Academic faculty member',
            FALSE
        ),
        (
            'Leadership',
            'Vice President for Academic Affairs',
            'Vice President responsible for Academic Affairs',
            TRUE
        ),
        (
            'Administrative',
            'President Office Delegate',
            'Authorized President Office delegate',
            FALSE
        ),
        (
            'Administrative',
            'President Office Staff',
            'President Office staff member',
            FALSE
        ),
        (
            'Administrative',
            'Vice President Office Delegate',
            'Authorized Partnerships and Development VP Office delegate',
            FALSE
        ),
        (
            'Administrative',
            'Vice President Office Staff',
            'Partnerships and Development VP Office staff member',
            FALSE
        ),
        (
            'Administrative',
            'Vice President for Academic Affairs Office Delegate',
            'Authorized Academic Affairs VP Office delegate',
            FALSE
        ),
        (
            'Administrative',
            'Vice President for Academic Affairs Office Staff',
            'Academic Affairs VP Office staff member',
            FALSE
        )
) AS source(type_name, name, description, is_unique)
JOIN position_types pt
  ON pt.name = source.type_name
ON CONFLICT (name)
DO UPDATE SET
    position_type_id = EXCLUDED.position_type_id,
    description = EXCLUDED.description,
    is_unique = EXCLUDED.is_unique;

-- ============================================================
-- Managed development identities
-- ============================================================

CREATE TEMP TABLE uob_structure_people (
    university_id VARCHAR(30) PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    position_name VARCHAR(255) NOT NULL,
    unit_code VARCHAR(50) NOT NULL,
    password_hash TEXT NOT NULL
) ON COMMIT DROP;

-- Existing President / Partnerships VP office fixtures plus VPAA.
INSERT INTO uob_structure_people (
    university_id,
    first_name,
    last_name,
    email,
    position_name,
    unit_code,
    password_hash
)
VALUES
    (
        'DEV-PRES-DELEGATE-001',
        'Dev',
        'President Office Delegate',
        'dev.president.office@uob.test',
        'President Office Delegate',
        'PRES',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-PRES-STAFF-001',
        'Dev',
        'President Office Staff',
        'dev.president.staff@uob.test',
        'President Office Staff',
        'PRES',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-VP-DELEGATE-001',
        'Dev',
        'VP Office Delegate',
        'dev.vp.office@uob.test',
        'Vice President Office Delegate',
        'VP',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-VP-STAFF-001',
        'Dev',
        'VP Office Staff',
        'dev.vp.staff@uob.test',
        'Vice President Office Staff',
        'VP',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-VPAA-001',
        'Dev',
        'VP Academic Affairs',
        'dev.vpaa@uob.test',
        'Vice President for Academic Affairs',
        'VPAA',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-VPAA-DELEGATE-001',
        'Dev',
        'VPAA Office Delegate',
        'dev.vpaa.office@uob.test',
        'Vice President for Academic Affairs Office Delegate',
        'VPAA',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    ),
    (
        'DEV-VPAA-STAFF-001',
        'Dev',
        'VPAA Office Staff',
        'dev.vpaa.staff@uob.test',
        'Vice President for Academic Affairs Office Staff',
        'VPAA',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
    );

-- One Dean for every College.
-- Reuse the existing CIT development Dean identity.
INSERT INTO uob_structure_people (
    university_id,
    first_name,
    last_name,
    email,
    position_name,
    unit_code,
    password_hash
)
SELECT
    CASE
        WHEN college.code = 'CIT'
            THEN 'DEV-DEAN-001'
        ELSE 'DEV-DEAN-' || college.code
    END,
    'Dev',
    college.code || ' Dean',
    CASE
        WHEN college.code = 'CIT'
            THEN 'dev.dean@uob.test'
        ELSE
            'dev.dean.' ||
            LOWER(college.code) ||
            '@uob.test'
    END,
    'Dean',
    college.code,
    '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
FROM organizational_units college
WHERE college.code IN (
    'ARTS',
    'SCI',
    'CBA',
    'BTC',
    'CAS',
    'CIT',
    'LAW',
    'CHSS',
    'ENG'
)
  AND college.unit_type = 'COLLEGE';

-- One Department Head for every academic Department.
-- Reuse the existing Computer Science development Head identity.
INSERT INTO uob_structure_people (
    university_id,
    first_name,
    last_name,
    email,
    position_name,
    unit_code,
    password_hash
)
SELECT
    CASE
        WHEN department.code = 'CS'
            THEN 'DEV-HEAD-001'
        ELSE 'DEV-HEAD-' || department.code
    END,
    'Dev',
    department.code || ' Department Head',
    CASE
        WHEN department.code = 'CS'
            THEN 'dev.head@uob.test'
        ELSE
            'dev.head.' ||
            LOWER(REPLACE(department.code, '-', '.')) ||
            '@uob.test'
    END,
    'Department Head',
    department.code,
    '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
FROM organizational_units department
WHERE department.code IN (
    'ARTS-AIS','ARTS-MCTFA','ARTS-ELL','ARTS-PSY','ARTS-SS',
    'SCI-MATH','SCI-CHEM','SCI-BIO','SCI-PHYS',
    'CBA-ACC','CBA-EF','CBA-MM','CBA-IB',
    'BTC-ITE','BTC-AIS','BTC-ELE','BTC-MS','BTC-ES',
    'CAS-ENGP','CAS-ATP',
    'CS','IS','CIT-CE',
    'LAW-PUB','LAW-PRIV',
    'CHSS-NUR','CHSS-AH','CHSS-PE',
    'ENG-CIV','ENG-AID','ENG-CHEM','ENG-EEE','ENG-MECH'
)
  AND department.unit_type = 'DEPARTMENT';

-- One Faculty Member / Doctor for every academic Department.
-- Reuse the existing Computer Science development Faculty identity.
INSERT INTO uob_structure_people (
    university_id,
    first_name,
    last_name,
    email,
    position_name,
    unit_code,
    password_hash
)
SELECT
    CASE
        WHEN department.code = 'CS'
            THEN 'DEV-FAC-001'
        ELSE 'DEV-FAC-' || department.code
    END,
    'Dev',
    department.code || ' Faculty',
    CASE
        WHEN department.code = 'CS'
            THEN 'dev.faculty@uob.test'
        ELSE
            'dev.faculty.' ||
            LOWER(REPLACE(department.code, '-', '.')) ||
            '@uob.test'
    END,
    'Faculty Member',
    department.code,
    '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66'
FROM organizational_units department
WHERE department.code IN (
    'ARTS-AIS','ARTS-MCTFA','ARTS-ELL','ARTS-PSY','ARTS-SS',
    'SCI-MATH','SCI-CHEM','SCI-BIO','SCI-PHYS',
    'CBA-ACC','CBA-EF','CBA-MM','CBA-IB',
    'BTC-ITE','BTC-AIS','BTC-ELE','BTC-MS','BTC-ES',
    'CAS-ENGP','CAS-ATP',
    'CS','IS','CIT-CE',
    'LAW-PUB','LAW-PRIV',
    'CHSS-NUR','CHSS-AH','CHSS-PE',
    'ENG-CIV','ENG-AID','ENG-CHEM','ENG-EEE','ENG-MECH'
)
  AND department.unit_type = 'DEPARTMENT';

-- 7 office actors + 9 Deans + 33 Heads + 33 Faculty = 82.
DO $$
BEGIN
    IF (
        SELECT COUNT(*)
        FROM uob_structure_people
    ) <> 82 THEN
        RAISE EXCEPTION
            'University structure development seed expected 82 managed users, found %.',
            (SELECT COUNT(*) FROM uob_structure_people);
    END IF;
END
$$;

-- Stop on ambiguous identity collisions instead of merging two accounts.
DO $$
DECLARE
    conflicting_ids TEXT;
BEGIN
    SELECT string_agg(
        expected.university_id,
        ', '
        ORDER BY expected.university_id
    )
    INTO conflicting_ids
    FROM uob_structure_people expected
    JOIN users by_id
      ON by_id.university_id = expected.university_id
    JOIN users by_email
      ON by_email.email = expected.email
    WHERE by_id.user_id <> by_email.user_id;

    IF conflicting_ids IS NOT NULL THEN
        RAISE EXCEPTION
            'Development identity collision for: %. University ID and email belong to different users.',
            conflicting_ids;
    END IF;
END
$$;

UPDATE users existing
SET
    university_id = expected.university_id,
    first_name = expected.first_name,
    last_name = expected.last_name,
    email = expected.email,
    password_hash = expected.password_hash,
    is_active = TRUE
FROM uob_structure_people expected
WHERE existing.university_id = expected.university_id
   OR existing.email = expected.email;

INSERT INTO users (
    university_id,
    first_name,
    last_name,
    email,
    password_hash,
    is_active
)
SELECT
    expected.university_id,
    expected.first_name,
    expected.last_name,
    expected.email,
    expected.password_hash,
    TRUE
FROM uob_structure_people expected
WHERE NOT EXISTS (
    SELECT 1
    FROM users existing
    WHERE existing.university_id = expected.university_id
       OR existing.email = expected.email
);

DO $$
BEGIN
    IF (
        SELECT COUNT(*)
        FROM uob_structure_people expected
        JOIN users actual
          ON actual.university_id = expected.university_id
         AND actual.email = expected.email
         AND actual.is_active = TRUE
    ) <> 82 THEN
        RAISE EXCEPTION
            'Development user reconciliation did not produce all 82 active users.';
    END IF;
END
$$;

-- ============================================================
-- Development role assignments
-- ============================================================

DO $$
BEGIN
    IF (
        SELECT COUNT(DISTINCT role_name)
        FROM roles
        WHERE role_name IN (
            'Agreement Creator',
            'Agreement Approver',
            'Initiative Creator',
            'Initiative Approver'
        )
    ) <> 4 THEN
        RAISE EXCEPTION
            'Required development Workflow roles are missing.';
    END IF;
END
$$;

-- Ensure Initiative Approvers carry the permission used by office delegation.
INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    role.role_id,
    permission.permission_id
FROM roles role
JOIN permissions permission
  ON permission.permission_code IN (
      'APPROVE_INITIATIVE',
      'REJECT_INITIATIVE',
      'VIEW_REPORTS'
  )
WHERE role.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

CREATE TEMP TABLE uob_structure_roles (
    email VARCHAR(255) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (email, role_name)
) ON COMMIT DROP;

-- Deans.
INSERT INTO uob_structure_roles (email, role_name)
SELECT email, 'Agreement Creator'
FROM uob_structure_people
WHERE position_name = 'Dean';

INSERT INTO uob_structure_roles (email, role_name)
SELECT email, 'Initiative Approver'
FROM uob_structure_people
WHERE position_name = 'Dean';

-- Department Heads.
INSERT INTO uob_structure_roles (email, role_name)
SELECT email, 'Initiative Creator'
FROM uob_structure_people
WHERE position_name = 'Department Head';

INSERT INTO uob_structure_roles (email, role_name)
SELECT email, 'Initiative Approver'
FROM uob_structure_people
WHERE position_name = 'Department Head';

-- Faculty Members / Doctors.
INSERT INTO uob_structure_roles (email, role_name)
SELECT email, 'Initiative Creator'
FROM uob_structure_people
WHERE position_name = 'Faculty Member';

-- VPAA principal mirrors the existing VP authority model.
INSERT INTO uob_structure_roles (email, role_name)
VALUES
    ('dev.vpaa@uob.test', 'Agreement Creator'),
    ('dev.vpaa@uob.test', 'Agreement Approver'),
    ('dev.vpaa@uob.test', 'Initiative Approver');

-- Delegates.
INSERT INTO uob_structure_roles (email, role_name)
VALUES
    ('dev.president.office@uob.test', 'Initiative Approver'),

    ('dev.vp.office@uob.test', 'Initiative Creator'),
    ('dev.vp.office@uob.test', 'Initiative Approver'),

    ('dev.vpaa.office@uob.test', 'Initiative Creator'),
    ('dev.vpaa.office@uob.test', 'Initiative Approver');

-- Office Staff are creators only.
INSERT INTO uob_structure_roles (email, role_name)
VALUES
    ('dev.president.staff@uob.test', 'Initiative Creator'),
    ('dev.vp.staff@uob.test', 'Initiative Creator'),
    ('dev.vpaa.staff@uob.test', 'Initiative Creator');

-- Exact role reconciliation for all users managed by this seed.
DELETE FROM user_roles existing
WHERE existing.user_id IN (
    SELECT actual.user_id
    FROM users actual
    JOIN uob_structure_people managed
      ON managed.university_id = actual.university_id
);

INSERT INTO user_roles (user_id, role_id)
SELECT
    actual.user_id,
    role.role_id
FROM uob_structure_roles expected
JOIN users actual
  ON actual.email = expected.email
JOIN roles role
  ON role.role_name = expected.role_name;

-- Defense in depth: Staff must never keep a role granting Initiative approval.
DELETE FROM user_roles user_role
USING users staff_user,
      role_permissions role_permission,
      permissions permission
WHERE user_role.user_id = staff_user.user_id
  AND role_permission.role_id = user_role.role_id
  AND permission.permission_id = role_permission.permission_id
  AND permission.permission_code = 'APPROVE_INITIATIVE'
  AND staff_user.email IN (
      'dev.president.staff@uob.test',
      'dev.vp.staff@uob.test',
      'dev.vpaa.staff@uob.test'
  );

-- ============================================================
-- Development position assignments
-- ============================================================

-- Preserve assignment history by closing obsolete active assignments.
UPDATE user_positions current_assignment
SET
    is_active = FALSE,
    end_date = COALESCE(
        current_assignment.end_date,
        GREATEST(
            CURRENT_DATE,
            current_assignment.start_date
        )
    )
FROM users actual
JOIN uob_structure_people expected
  ON expected.university_id = actual.university_id
WHERE current_assignment.user_id = actual.user_id
  AND current_assignment.is_active = TRUE
  AND NOT EXISTS (
      SELECT 1
      FROM positions expected_position
      JOIN organizational_units expected_unit
        ON expected_unit.code = expected.unit_code
      WHERE expected_position.name = expected.position_name
        AND expected_position.position_id =
            current_assignment.position_id
        AND expected_unit.unit_id =
            current_assignment.unit_id
  );

-- Unique positions such as Dean and Department Head must have one active
-- holder per organizational unit in development fixtures.
UPDATE user_positions conflicting_assignment
SET
    is_active = FALSE,
    end_date = COALESCE(
        conflicting_assignment.end_date,
        GREATEST(
            CURRENT_DATE,
            conflicting_assignment.start_date
        )
    )
FROM uob_structure_people expected
JOIN users target
  ON target.email = expected.email
JOIN positions expected_position
  ON expected_position.name = expected.position_name
JOIN organizational_units expected_unit
  ON expected_unit.code = expected.unit_code
WHERE expected_position.is_unique = TRUE
  AND conflicting_assignment.position_id =
      expected_position.position_id
  AND conflicting_assignment.unit_id =
      expected_unit.unit_id
  AND conflicting_assignment.is_active = TRUE
  AND conflicting_assignment.user_id <> target.user_id;

-- Close duplicate active rows for the same managed actor/position/unit.
WITH ranked_assignments AS (
    SELECT
        current_assignment.user_position_id,
        ROW_NUMBER() OVER (
            PARTITION BY
                current_assignment.user_id,
                current_assignment.position_id,
                current_assignment.unit_id
            ORDER BY
                current_assignment.start_date DESC,
                current_assignment.user_position_id DESC
        ) AS row_number
    FROM user_positions current_assignment
    JOIN users actual
      ON actual.user_id = current_assignment.user_id
    JOIN uob_structure_people managed
      ON managed.university_id = actual.university_id
    WHERE current_assignment.is_active = TRUE
)
UPDATE user_positions current_assignment
SET
    is_active = FALSE,
    end_date = COALESCE(
        current_assignment.end_date,
        GREATEST(
            CURRENT_DATE,
            current_assignment.start_date
        )
    )
FROM ranked_assignments ranked
WHERE ranked.user_position_id =
      current_assignment.user_position_id
  AND ranked.row_number > 1;

INSERT INTO user_positions (
    user_id,
    position_id,
    unit_id,
    start_date,
    is_active
)
SELECT
    actual.user_id,
    expected_position.position_id,
    expected_unit.unit_id,
    CURRENT_DATE,
    TRUE
FROM uob_structure_people expected
JOIN users actual
  ON actual.email = expected.email
JOIN positions expected_position
  ON expected_position.name = expected.position_name
JOIN organizational_units expected_unit
  ON expected_unit.code = expected.unit_code
WHERE NOT EXISTS (
    SELECT 1
    FROM user_positions existing
    WHERE existing.user_id = actual.user_id
      AND existing.position_id =
          expected_position.position_id
      AND existing.unit_id =
          expected_unit.unit_id
      AND existing.is_active = TRUE
);

-- ============================================================
-- Development account verification
-- ============================================================

DO $$
DECLARE
    expected_role_count INTEGER;
    actual_role_count INTEGER;
    active_assignment_count INTEGER;
    delegate_approver_count INTEGER;
    staff_approver_count INTEGER;
BEGIN
    SELECT COUNT(*)
    INTO expected_role_count
    FROM uob_structure_roles;

    SELECT COUNT(*)
    INTO actual_role_count
    FROM uob_structure_roles expected
    JOIN users actual
      ON actual.email = expected.email
    JOIN roles role
      ON role.role_name = expected.role_name
    JOIN user_roles actual_role
      ON actual_role.user_id = actual.user_id
     AND actual_role.role_id = role.role_id;

    IF actual_role_count <> expected_role_count THEN
        RAISE EXCEPTION
            'Development role reconciliation expected % assignments, found %.',
            expected_role_count,
            actual_role_count;
    END IF;

    SELECT COUNT(*)
    INTO active_assignment_count
    FROM uob_structure_people expected
    JOIN users actual
      ON actual.email = expected.email
    JOIN positions expected_position
      ON expected_position.name = expected.position_name
    JOIN organizational_units expected_unit
      ON expected_unit.code = expected.unit_code
    JOIN user_positions actual_assignment
      ON actual_assignment.user_id = actual.user_id
     AND actual_assignment.position_id =
         expected_position.position_id
     AND actual_assignment.unit_id =
         expected_unit.unit_id
     AND actual_assignment.is_active = TRUE
     AND (
         actual_assignment.end_date IS NULL
         OR actual_assignment.end_date >= CURRENT_DATE
     );

    IF active_assignment_count <> 82 THEN
        RAISE EXCEPTION
            'Development position reconciliation expected 82 active assignments, found %.',
            active_assignment_count;
    END IF;

    SELECT COUNT(DISTINCT delegate_user.user_id)
    INTO delegate_approver_count
    FROM users delegate_user
    JOIN user_roles delegate_role
      ON delegate_role.user_id = delegate_user.user_id
    JOIN role_permissions delegate_role_permission
      ON delegate_role_permission.role_id =
         delegate_role.role_id
    JOIN permissions delegate_permission
      ON delegate_permission.permission_id =
         delegate_role_permission.permission_id
     AND delegate_permission.permission_code =
         'APPROVE_INITIATIVE'
    WHERE delegate_user.email IN (
        'dev.president.office@uob.test',
        'dev.vp.office@uob.test',
        'dev.vpaa.office@uob.test'
    );

    IF delegate_approver_count <> 3 THEN
        RAISE EXCEPTION
            'Expected all 3 office delegates to have APPROVE_INITIATIVE; found %.',
            delegate_approver_count;
    END IF;

    SELECT COUNT(DISTINCT staff_user.user_id)
    INTO staff_approver_count
    FROM users staff_user
    JOIN user_roles staff_role
      ON staff_role.user_id = staff_user.user_id
    JOIN role_permissions staff_role_permission
      ON staff_role_permission.role_id =
         staff_role.role_id
    JOIN permissions staff_permission
      ON staff_permission.permission_id =
         staff_role_permission.permission_id
     AND staff_permission.permission_code =
         'APPROVE_INITIATIVE'
    WHERE staff_user.email IN (
        'dev.president.staff@uob.test',
        'dev.vp.staff@uob.test',
        'dev.vpaa.staff@uob.test'
    );

    IF staff_approver_count <> 0 THEN
        RAISE EXCEPTION
            'Office Staff must not have APPROVE_INITIATIVE; found %.',
            staff_approver_count;
    END IF;
END
$$;


-- ============================================================
-- Structural verification
-- ============================================================

DO $$
DECLARE
    v_colleges INTEGER;
    v_departments INTEGER;
BEGIN
    SELECT COUNT(*)
    INTO v_colleges
    FROM organizational_units
    WHERE code IN (
        'ARTS','SCI','CBA','BTC','CAS','CIT','LAW','CHSS','ENG'
    )
      AND unit_type = 'COLLEGE'
      AND is_active = TRUE;

    IF v_colleges <> 9 THEN
        RAISE EXCEPTION
            'University structure seed expected 9 colleges, found %.',
            v_colleges;
    END IF;

    SELECT COUNT(*)
    INTO v_departments
    FROM organizational_units
    WHERE code IN (
        'ARTS-AIS','ARTS-MCTFA','ARTS-ELL','ARTS-PSY','ARTS-SS',
        'SCI-MATH','SCI-CHEM','SCI-BIO','SCI-PHYS',
        'CBA-ACC','CBA-EF','CBA-MM','CBA-IB',
        'BTC-ITE','BTC-AIS','BTC-ELE','BTC-MS','BTC-ES',
        'CAS-ENGP','CAS-ATP',
        'CS','IS','CIT-CE',
        'LAW-PUB','LAW-PRIV',
        'CHSS-NUR','CHSS-AH','CHSS-PE',
        'ENG-CIV','ENG-AID','ENG-CHEM','ENG-EEE','ENG-MECH'
    )
      AND unit_type = 'DEPARTMENT'
      AND is_active = TRUE;

    IF v_departments <> 33 THEN
        RAISE EXCEPTION
            'University structure seed expected 33 departments, found %.',
            v_departments;
    END IF;
END
$$;

COMMIT;

SELECT
    COUNT(*) FILTER (
        WHERE unit_type = 'COLLEGE'
    ) AS active_colleges,
    COUNT(*) FILTER (
        WHERE unit_type = 'DEPARTMENT'
    ) AS active_departments
FROM organizational_units
WHERE is_active = TRUE;