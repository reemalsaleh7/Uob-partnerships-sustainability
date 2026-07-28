-- ==========================================================
-- Development seed extension for Initiative approvals
-- Append to seed_dev.sql before COMMIT, or run separately.
-- Idempotent.
-- ==========================================================

BEGIN;

-- Creator permissions.
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
WHERE r.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

-- Approver permissions.
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
      'APPROVE_INITIATIVE',
      'REJECT_INITIATIVE'
  )
WHERE r.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

-- Eligible creators:
-- Faculty, Department Head, and Dean.
INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    u.user_id,
    r.role_id
FROM users u
CROSS JOIN roles r
WHERE r.role_name = 'Initiative Creator'
  AND u.email IN (
      'dev.faculty@uob.test',
      'dev.head@uob.test',
      'dev.dean@uob.test'
  )
ON CONFLICT DO NOTHING;

-- Required reviewers:
-- Department Head, Dean, VP, and President.
INSERT INTO user_roles (
    user_id,
    role_id
)
SELECT
    u.user_id,
    r.role_id
FROM users u
CROSS JOIN roles r
WHERE r.role_name = 'Initiative Approver'
  AND u.email IN (
      'dev.head@uob.test',
      'dev.dean@uob.test',
      'dev.vp@uob.test',
      'dev.president@uob.test'
  )
ON CONFLICT DO NOTHING;

COMMIT;
