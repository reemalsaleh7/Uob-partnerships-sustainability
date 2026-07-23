BEGIN;

CREATE TABLE IF NOT EXISTS workspace_legacy_handoffs (
    handoff_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT workspace_legacy_handoff_expiry_check
        CHECK (expires_at > created_at)
);

CREATE INDEX IF NOT EXISTS ix_workspace_legacy_handoffs_expiry
    ON workspace_legacy_handoffs (expires_at)
    WHERE used_at IS NULL;

-- The Initiative Creator role existed but had no permission grants, leaving
-- Faculty and Department Head accounts with an empty workspace experience.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_INITIATIVE', 'EDIT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'Initiative Creator'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'APPROVE_INITIATIVE', 'REJECT_INITIATIVE', 'VIEW_REPORTS'
  )
WHERE r.role_name = 'Initiative Approver'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'CREATE_INITIATIVE', 'EDIT_INITIATIVE',
      'APPROVE_INITIATIVE', 'REJECT_INITIATIVE',
      'VIEW_REPORTS'
  )
WHERE r.role_name = 'System Administrator'
ON CONFLICT DO NOTHING;

COMMIT;
