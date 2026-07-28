-- ==========================================================
-- Development seed extension for Student Initiative creator
-- Run after seed_dev.sql and Initiative approval migrations.
-- ==========================================================

BEGIN;

-- Development Student account.
INSERT INTO users (
    university_id,
    first_name,
    last_name,
    email,
    password_hash,
    is_active
)
SELECT
    'DEV-STUDENT-001',
    'Dev',
    'Student',
    'dev.student@uob.test',
    '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66',
    TRUE
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE university_id = 'DEV-STUDENT-001'
       OR email = 'dev.student@uob.test'
);

-- Student role.
INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    u.user_id,
    r.role_id
FROM users u
CROSS JOIN roles r
WHERE u.email = 'dev.student@uob.test'
  AND r.role_name = 'Student Initiative Creator'
ON CONFLICT DO NOTHING;

-- Student specialization belongs to CS for development.
INSERT INTO student_profiles (
    user_id,
    student_number,
    department_id,
    program_name,
    is_active
)
SELECT
    u.user_id,
    '20260001',
    ou.unit_id,
    'Computer Science',
    TRUE
FROM users u
JOIN organizational_units ou
  ON ou.code = 'CS'
WHERE u.email = 'dev.student@uob.test'
ON CONFLICT (user_id)
DO UPDATE SET
    student_number = EXCLUDED.student_number,
    department_id = EXCLUDED.department_id,
    program_name = EXCLUDED.program_name,
    is_active = TRUE;

COMMIT;
