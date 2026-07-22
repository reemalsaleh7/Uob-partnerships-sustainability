BEGIN;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'users_failed_login_attempts_nonnegative'
          AND conrelid = 'users'::regclass
    ) THEN
        ALTER TABLE users
            ADD CONSTRAINT users_failed_login_attempts_nonnegative
            CHECK (failed_login_attempts >= 0);
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS ix_users_locked_until
    ON users (locked_until)
    WHERE locked_until IS NOT NULL;

INSERT INTO permissions (permission_code, permission_name, description)
VALUES
    ('CREATE_AGREEMENT', 'Create Agreement', 'Create Agreement drafts'),
    ('EDIT_AGREEMENT', 'Edit Agreement', 'Edit eligible Agreement drafts'),
    ('SUBMIT_AGREEMENT', 'Submit Agreement', 'Submit eligible Agreement drafts'),
    ('VIEW_AGREEMENT', 'View Agreement', 'Open the Agreement workspace subject to record visibility'),
    ('DELETE_AGREEMENT', 'Delete Agreement', 'Delete eligible owned Agreement drafts'),
    ('APPROVE_AGREEMENT', 'Approve Agreement', 'Approve assigned Agreement workflow steps'),
    ('REJECT_AGREEMENT', 'Reject Agreement', 'Reject assigned Agreement workflow steps'),
    ('MANAGE_AGREEMENT_OPERATIONS', 'Manage Agreement operations', 'Finalize signing for eligible Agreements'),
    ('MANAGE_AGREEMENT_REPORTS', 'Manage Agreement performance reports', 'Prepare and submit owned Agreement reports'),
    ('REVIEW_AGREEMENT_REPORTS', 'Review Agreement performance reports', 'Accept or return submitted Agreement reports'),
    ('VIEW_AGREEMENT_DASHBOARD', 'View Agreement performance dashboard', 'View aggregate Agreement performance data')
ON CONFLICT (permission_code) DO UPDATE
SET permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
      'VIEW_AGREEMENT', 'DELETE_AGREEMENT',
      'MANAGE_AGREEMENT_OPERATIONS', 'MANAGE_AGREEMENT_REPORTS'
  )
WHERE r.role_name = 'Agreement Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'VIEW_AGREEMENT', 'APPROVE_AGREEMENT', 'REJECT_AGREEMENT',
      'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
  )
WHERE r.role_name = 'Agreement Approver'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
      'VIEW_AGREEMENT', 'DELETE_AGREEMENT',
      'APPROVE_AGREEMENT', 'REJECT_AGREEMENT',
      'MANAGE_AGREEMENT_OPERATIONS', 'MANAGE_AGREEMENT_REPORTS',
      'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
  )
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

COMMIT;
