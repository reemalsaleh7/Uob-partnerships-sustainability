-- Phase 6C - Separate office Staff and Delegate positions
-- Local development seed.
-- Password for both new staff accounts: UobDev2026!
-- Safe to run more than once.

-- Rename the previous demo positions when the new names do not exist yet.
UPDATE positions
SET
    name = 'Vice President Office Delegate',
    description = 'Authorized Initiative delegate in the Vice President Office',
    is_unique = FALSE
WHERE name = 'Vice President Office Member'
  AND NOT EXISTS (
      SELECT 1
      FROM positions
      WHERE name = 'Vice President Office Delegate'
  );

UPDATE positions
SET
    name = 'President Office Delegate',
    description = 'Authorized Initiative delegate in the President Office',
    is_unique = FALSE
WHERE name = 'President Office Member'
  AND NOT EXISTS (
      SELECT 1
      FROM positions
      WHERE name = 'President Office Delegate'
  );

-- Ensure all four office positions exist.
INSERT INTO positions (
    position_type_id,
    name,
    description,
    is_unique
)
SELECT
    position_type.position_type_id,
    position_values.position_name,
    position_values.position_description,
    FALSE
FROM (
    VALUES
        (
            'Vice President Office Staff',
            'Regular employee in the Vice President Office; cannot approve on behalf of the Vice President'
        ),
        (
            'Vice President Office Delegate',
            'Authorized Initiative delegate in the Vice President Office'
        ),
        (
            'President Office Staff',
            'Regular employee in the President Office; cannot approve on behalf of the President'
        ),
        (
            'President Office Delegate',
            'Authorized Initiative delegate in the President Office'
        )
) AS position_values(position_name, position_description)
JOIN position_types position_type
  ON position_type.name = 'Administrative'
ON CONFLICT (name) DO UPDATE
SET
    position_type_id = EXCLUDED.position_type_id,
    description = EXCLUDED.description,
    is_unique = FALSE;

-- Keep the previous delegate demo accounts aligned with the new position names.
UPDATE users
SET last_name = 'VP Office Delegate'
WHERE email = 'dev.vp.office@uob.test';

UPDATE users
SET last_name = 'President Office Delegate'
WHERE email = 'dev.president.office@uob.test';

-- Create two regular office staff accounts.
INSERT INTO users (
    university_id,
    first_name,
    last_name,
    email,
    password_hash,
    is_active
)
VALUES
    (
        'DEV-VP-STAFF-001',
        'Dev',
        'VP Office Staff',
        'dev.vp.staff@uob.test',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66',
        TRUE
    ),
    (
        'DEV-PRES-STAFF-001',
        'Dev',
        'President Office Staff',
        'dev.president.staff@uob.test',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66',
        TRUE
    )
ON CONFLICT (email) DO UPDATE
SET
    university_id = EXCLUDED.university_id,
    first_name = EXCLUDED.first_name,
    last_name = EXCLUDED.last_name,
    password_hash = EXCLUDED.password_hash,
    is_active = TRUE;

-- Give regular staff the Initiative Creator role only when that role exists.
-- They are deliberately not given any role that grants APPROVE_INITIATIVE.
INSERT INTO user_roles (user_id, role_id)
SELECT
    user_account.user_id,
    role.role_id
FROM users user_account
JOIN roles role
  ON role.role_name = 'Initiative Creator'
WHERE user_account.email IN (
    'dev.vp.staff@uob.test',
    'dev.president.staff@uob.test'
)
ON CONFLICT DO NOTHING;

-- Defensive rule: remove every role from the new staff accounts that grants
-- APPROVE_INITIATIVE, regardless of the role name.
DELETE FROM user_roles assigned_role
USING role_permissions role_permission,
      permissions permission,
      users user_account
WHERE assigned_role.role_id = role_permission.role_id
  AND role_permission.permission_id = permission.permission_id
  AND assigned_role.user_id = user_account.user_id
  AND permission.permission_code = 'APPROVE_INITIATIVE'
  AND user_account.email IN (
      'dev.vp.staff@uob.test',
      'dev.president.staff@uob.test'
  );

-- Assign each regular employee to the correct office and Staff position.
INSERT INTO user_positions (
    user_id,
    position_id,
    unit_id,
    start_date,
    is_active
)
SELECT
    user_account.user_id,
    position.position_id,
    unit.unit_id,
    CURRENT_DATE,
    TRUE
FROM (
    VALUES
        (
            'dev.vp.staff@uob.test',
            'Vice President Office Staff',
            'VP'
        ),
        (
            'dev.president.staff@uob.test',
            'President Office Staff',
            'PRES'
        )
) AS assignment(email, position_name, unit_code)
JOIN users user_account
  ON user_account.email = assignment.email
JOIN positions position
  ON position.name = assignment.position_name
JOIN organizational_units unit
  ON unit.code = assignment.unit_code
WHERE NOT EXISTS (
    SELECT 1
    FROM user_positions existing_assignment
    WHERE existing_assignment.user_id = user_account.user_id
      AND existing_assignment.position_id = position.position_id
      AND existing_assignment.unit_id = unit.unit_id
      AND existing_assignment.is_active = TRUE
      AND (
          existing_assignment.end_date IS NULL
          OR existing_assignment.end_date >= CURRENT_DATE
      )
);

-- Verification: both accounts must exist in the correct office and must not
-- receive APPROVE_INITIATIVE through any assigned role.
DO $$
DECLARE
    configured_staff_count INTEGER;
    unauthorized_approver_count INTEGER;
BEGIN
    SELECT COUNT(*)
    INTO configured_staff_count
    FROM users user_account
    JOIN user_positions user_position
      ON user_position.user_id = user_account.user_id
     AND user_position.is_active = TRUE
    JOIN positions position
      ON position.position_id = user_position.position_id
    JOIN organizational_units unit
      ON unit.unit_id = user_position.unit_id
    WHERE (
        user_account.email = 'dev.vp.staff@uob.test'
        AND position.name = 'Vice President Office Staff'
        AND unit.code = 'VP'
    )
    OR (
        user_account.email = 'dev.president.staff@uob.test'
        AND position.name = 'President Office Staff'
        AND unit.code = 'PRES'
    );

    SELECT COUNT(DISTINCT user_account.user_id)
    INTO unauthorized_approver_count
    FROM users user_account
    JOIN user_roles assigned_role
      ON assigned_role.user_id = user_account.user_id
    JOIN role_permissions role_permission
      ON role_permission.role_id = assigned_role.role_id
    JOIN permissions permission
      ON permission.permission_id = role_permission.permission_id
    WHERE user_account.email IN (
        'dev.vp.staff@uob.test',
        'dev.president.staff@uob.test'
    )
      AND permission.permission_code = 'APPROVE_INITIATIVE';

    IF configured_staff_count <> 2 THEN
        RAISE EXCEPTION
            'The two regular office staff accounts were not assigned correctly.';
    END IF;

    IF unauthorized_approver_count <> 0 THEN
        RAISE EXCEPTION
            'A regular office staff account still has APPROVE_INITIATIVE.';
    END IF;
END
$$;

-- Final result shown in psql.
SELECT
    user_account.email,
    CONCAT(user_account.first_name, ' ', user_account.last_name) AS full_name,
    position.name AS position,
    unit.code AS unit_code,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM user_roles assigned_role
            JOIN role_permissions role_permission
              ON role_permission.role_id = assigned_role.role_id
            JOIN permissions permission
              ON permission.permission_id = role_permission.permission_id
            WHERE assigned_role.user_id = user_account.user_id
              AND permission.permission_code = 'APPROVE_INITIATIVE'
        )
        THEN 'YES'
        ELSE 'NO'
    END AS can_approve_initiative
FROM users user_account
JOIN user_positions user_position
  ON user_position.user_id = user_account.user_id
 AND user_position.is_active = TRUE
JOIN positions position
  ON position.position_id = user_position.position_id
JOIN organizational_units unit
  ON unit.unit_id = user_position.unit_id
WHERE user_account.email IN (
    'dev.vp.staff@uob.test',
    'dev.president.staff@uob.test'
)
ORDER BY user_account.email;
