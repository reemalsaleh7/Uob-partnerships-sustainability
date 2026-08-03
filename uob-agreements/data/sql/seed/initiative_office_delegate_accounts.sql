-- Phase 6D - Authorized office delegate accounts
-- Local development seed.
-- Password for both accounts: UobDev2026!
-- Safe to run more than once.

-- Ensure the two delegate positions exist.
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
            'Vice President Office Delegate',
            'Authorized Initiative delegate in the Vice President Office'
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

-- Create or refresh the two delegate accounts.
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
        'DEV-VP-DELEGATE-001',
        'Dev',
        'VP Office Delegate',
        'dev.vp.office@uob.test',
        '$2y$10$xbIlZ2LKFvYRQc7rW4GFgeyA2feEPxPTT9WlPwC//YI/8u3k.QX66',
        TRUE
    ),
    (
        'DEV-PRES-DELEGATE-001',
        'Dev',
        'President Office Delegate',
        'dev.president.office@uob.test',
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

-- Both delegates must receive the Initiative Approver role.
INSERT INTO user_roles (user_id, role_id)
SELECT
    user_account.user_id,
    role.role_id
FROM users user_account
JOIN roles role
  ON role.role_name = 'Initiative Approver'
WHERE user_account.email IN (
    'dev.vp.office@uob.test',
    'dev.president.office@uob.test'
)
ON CONFLICT DO NOTHING;

-- The VP Office delegate may also create Initiative requests.
INSERT INTO user_roles (user_id, role_id)
SELECT
    user_account.user_id,
    role.role_id
FROM users user_account
JOIN roles role
  ON role.role_name = 'Initiative Creator'
WHERE user_account.email = 'dev.vp.office@uob.test'
ON CONFLICT DO NOTHING;

-- Assign each delegate to the correct office.
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
            'dev.vp.office@uob.test',
            'Vice President Office Delegate',
            'VP'
        ),
        (
            'dev.president.office@uob.test',
            'President Office Delegate',
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

-- Verification: both delegate accounts must be assigned correctly and must
-- receive APPROVE_INITIATIVE through at least one role.
DO $$
DECLARE
    configured_delegate_count INTEGER;
    approver_delegate_count INTEGER;
BEGIN
    SELECT COUNT(*)
    INTO configured_delegate_count
    FROM users user_account
    JOIN user_positions user_position
      ON user_position.user_id = user_account.user_id
     AND user_position.is_active = TRUE
    JOIN positions position
      ON position.position_id = user_position.position_id
    JOIN organizational_units unit
      ON unit.unit_id = user_position.unit_id
    WHERE (
        user_account.email = 'dev.vp.office@uob.test'
        AND position.name = 'Vice President Office Delegate'
        AND unit.code = 'VP'
    )
    OR (
        user_account.email = 'dev.president.office@uob.test'
        AND position.name = 'President Office Delegate'
        AND unit.code = 'PRES'
    );

    SELECT COUNT(DISTINCT user_account.user_id)
    INTO approver_delegate_count
    FROM users user_account
    JOIN user_roles assigned_role
      ON assigned_role.user_id = user_account.user_id
    JOIN role_permissions role_permission
      ON role_permission.role_id = assigned_role.role_id
    JOIN permissions permission
      ON permission.permission_id = role_permission.permission_id
    WHERE user_account.email IN (
        'dev.vp.office@uob.test',
        'dev.president.office@uob.test'
    )
      AND permission.permission_code = 'APPROVE_INITIATIVE';

    IF configured_delegate_count <> 2 THEN
        RAISE EXCEPTION
            'The two office delegate accounts were not assigned correctly.';
    END IF;

    IF approver_delegate_count <> 2 THEN
        RAISE EXCEPTION
            'The two office delegate accounts do not both have APPROVE_INITIATIVE.';
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
    'dev.vp.office@uob.test',
    'dev.president.office@uob.test'
)
ORDER BY user_account.email;
