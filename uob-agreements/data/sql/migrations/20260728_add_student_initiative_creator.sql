-- ==========================================================
-- 2026-07-28
-- Student Initiative creator eligibility and hierarchy routing
--
-- Student route:
-- Student
-- -> Department Head of the student's specialization
-- -> Dean of the parent College
-- -> Vice President
-- -> President
--
-- The Department comes from student_profiles.department_id.
-- The College is resolved through
-- organizational_units.parent_unit_id.
-- ==========================================================

BEGIN;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id BIGINT PRIMARY KEY,
    student_number VARCHAR(30) NOT NULL UNIQUE,
    department_id BIGINT NOT NULL,
    program_name VARCHAR(200),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_student_profile_user
        FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_student_profile_department
        FOREIGN KEY(department_id)
        REFERENCES organizational_units(unit_id)
        ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS
    idx_student_profiles_department
    ON student_profiles(department_id);

CREATE INDEX IF NOT EXISTS
    idx_student_profiles_active
    ON student_profiles(is_active);

-- Make sure student profiles point only to active Department units.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'student_profiles'::regclass
          AND conname =
              'chk_student_profile_department_unit'
    ) THEN
        ALTER TABLE student_profiles
            ADD CONSTRAINT
                chk_student_profile_department_unit
            CHECK (
                department_id IS NOT NULL
            );
    END IF;
END
$$;

-- Student creator role.
INSERT INTO roles (
    role_name,
    description
)
SELECT
    'Student Initiative Creator',
    'Student allowed to create and submit Initiatives'
WHERE NOT EXISTS (
    SELECT 1
    FROM roles
    WHERE role_name = 'Student Initiative Creator'
);

-- Reuse Initiative create/edit permissions.
INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_INITIATIVE',
      'EDIT_INITIATIVE'
  )
WHERE r.role_name = 'Student Initiative Creator'
ON CONFLICT DO NOTHING;

-- Add submit permission if the deployed database does not
-- already contain one.
INSERT INTO permissions (
    permission_code,
    permission_name,
    description
)
SELECT
    'SUBMIT_INITIATIVE',
    'Submit Initiative',
    'Submit an eligible Initiative into approval workflow'
WHERE NOT EXISTS (
    SELECT 1
    FROM permissions
    WHERE permission_code = 'SUBMIT_INITIATIVE'
);

INSERT INTO role_permissions (
    role_id,
    permission_id
)
SELECT
    r.role_id,
    p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'SUBMIT_INITIATIVE'
WHERE r.role_name IN (
    'Student Initiative Creator',
    'Initiative Creator'
)
ON CONFLICT DO NOTHING;

COMMENT ON TABLE student_profiles IS
    'Student academic affiliation used to route Initiative approvals.';

COMMENT ON COLUMN student_profiles.department_id IS
    'Department that owns the student major/specialization.';

COMMIT;
