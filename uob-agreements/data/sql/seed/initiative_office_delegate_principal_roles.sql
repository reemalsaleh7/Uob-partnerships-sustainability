-- Phase 6F fixed
-- Self-contained repair for office delegate principal access.
-- Safe to run more than once with psql.
--
-- This file:
-- 1) repairs workflow_step_assignments so one step can have multiple users;
-- 2) mirrors VP/President roles to their authorized delegates;
-- 3) copies current active workflow assignments to the delegates.

ALTER TABLE workflow_step_assignments
    DROP CONSTRAINT IF EXISTS
    workflow_step_assignments_workflow_instance_step_id_key;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'workflow_step_assignments'::regclass
          AND conname =
              'workflow_step_assignments_step_user_key'
    ) THEN
        ALTER TABLE workflow_step_assignments
            ADD CONSTRAINT
            workflow_step_assignments_step_user_key
            UNIQUE (
                workflow_instance_step_id,
                user_id
            );
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS
    idx_workflow_step_assignments_active_user
ON workflow_step_assignments (
    user_id,
    is_active,
    workflow_instance_step_id
);

DO $$
DECLARE
    mapping_record RECORD;
    principal_id BIGINT;
    delegate_id BIGINT;
BEGIN
    FOR mapping_record IN
        SELECT *
        FROM (
            VALUES
                (
                    'dev.vp@uob.test',
                    'dev.vp.office@uob.test'
                ),
                (
                    'dev.president@uob.test',
                    'dev.president.office@uob.test'
                )
        ) AS mappings(
            principal_email,
            delegate_email
        )
    LOOP
        principal_id := NULL;
        delegate_id := NULL;

        SELECT user_id
        INTO principal_id
        FROM users
        WHERE email =
              mapping_record.principal_email
          AND is_active = TRUE;

        SELECT user_id
        INTO delegate_id
        FROM users
        WHERE email =
              mapping_record.delegate_email
          AND is_active = TRUE;

        IF principal_id IS NULL THEN
            RAISE EXCEPTION
                'Principal account is missing or inactive: %',
                mapping_record.principal_email;
        END IF;

        IF delegate_id IS NULL THEN
            RAISE EXCEPTION
                'Delegate account is missing or inactive: %',
                mapping_record.delegate_email;
        END IF;

        -- Make the delegate role set exactly match the principal.
        DELETE FROM user_roles delegate_role
        WHERE delegate_role.user_id = delegate_id
          AND NOT EXISTS (
              SELECT 1
              FROM user_roles principal_role
              WHERE principal_role.user_id =
                    principal_id
                AND principal_role.role_id =
                    delegate_role.role_id
          );

        INSERT INTO user_roles (
            user_id,
            role_id
        )
        SELECT
            delegate_id,
            principal_role.role_id
        FROM user_roles principal_role
        WHERE principal_role.user_id =
              principal_id
          AND NOT EXISTS (
              SELECT 1
              FROM user_roles existing_role
              WHERE existing_role.user_id =
                    delegate_id
                AND existing_role.role_id =
                    principal_role.role_id
          );

        -- Copy current active workflow assignments.
        INSERT INTO workflow_step_assignments (
            workflow_instance_step_id,
            user_id,
            assigned_at,
            is_active
        )
        SELECT
            principal_assignment
                .workflow_instance_step_id,
            delegate_id,
            NOW(),
            TRUE
        FROM workflow_step_assignments
             principal_assignment
        JOIN workflow_instance_steps
             workflow_step
          ON workflow_step.instance_step_id =
             principal_assignment
                 .workflow_instance_step_id
         AND workflow_step.status =
             'IN_PROGRESS'
        JOIN workflow_instances
             workflow_instance
          ON workflow_instance.workflow_instance_id =
             workflow_step.workflow_instance_id
         AND workflow_instance.status =
             'IN_PROGRESS'
        WHERE principal_assignment.user_id =
              principal_id
          AND principal_assignment.is_active =
              TRUE
        ON CONFLICT (
            workflow_instance_step_id,
            user_id
        )
        DO UPDATE
        SET
            is_active = TRUE,
            assigned_at = EXCLUDED.assigned_at;
    END LOOP;
END
$$;

-- Verification:
-- missing_permissions must equal 0 for both delegate accounts.
WITH account_mapping AS (
    SELECT *
    FROM (
        VALUES
            (
                'dev.vp@uob.test',
                'dev.vp.office@uob.test'
            ),
            (
                'dev.president@uob.test',
                'dev.president.office@uob.test'
            )
    ) AS mappings(
        principal_email,
        delegate_email
    )
),
resolved AS (
    SELECT
        account_mapping.principal_email,
        account_mapping.delegate_email,
        principal.user_id AS principal_id,
        delegate.user_id AS delegate_id
    FROM account_mapping
    JOIN users principal
      ON principal.email =
         account_mapping.principal_email
    JOIN users delegate
      ON delegate.email =
         account_mapping.delegate_email
),
principal_permissions AS (
    SELECT DISTINCT
        resolved.delegate_email,
        permission.permission_code
    FROM resolved
    JOIN user_roles principal_role
      ON principal_role.user_id =
         resolved.principal_id
    JOIN role_permissions role_permission
      ON role_permission.role_id =
         principal_role.role_id
    JOIN permissions permission
      ON permission.permission_id =
         role_permission.permission_id
),
delegate_permissions AS (
    SELECT DISTINCT
        resolved.delegate_email,
        permission.permission_code
    FROM resolved
    JOIN user_roles delegate_role
      ON delegate_role.user_id =
         resolved.delegate_id
    JOIN role_permissions role_permission
      ON role_permission.role_id =
         delegate_role.role_id
    JOIN permissions permission
      ON permission.permission_id =
         role_permission.permission_id
)
SELECT
    resolved.delegate_email,
    principal.email AS mirrors_account,
    CONCAT(
        delegate.first_name,
        ' ',
        delegate.last_name
    ) AS delegate_name,
    COUNT(
        principal_permissions.permission_code
    ) FILTER (
        WHERE delegate_permissions.permission_code
              IS NULL
    ) AS missing_permissions,
    COUNT(
        DISTINCT
        delegate_permissions.permission_code
    ) AS delegate_permission_count,
    (
        SELECT COUNT(*)
        FROM workflow_step_assignments assignment
        JOIN workflow_instance_steps workflow_step
          ON workflow_step.instance_step_id =
             assignment.workflow_instance_step_id
         AND workflow_step.status =
             'IN_PROGRESS'
        JOIN workflow_instances workflow_instance
          ON workflow_instance.workflow_instance_id =
             workflow_step.workflow_instance_id
         AND workflow_instance.status =
             'IN_PROGRESS'
        WHERE assignment.user_id =
              resolved.delegate_id
          AND assignment.is_active = TRUE
    ) AS active_review_assignments
FROM resolved
JOIN users principal
  ON principal.user_id =
     resolved.principal_id
JOIN users delegate
  ON delegate.user_id =
     resolved.delegate_id
LEFT JOIN principal_permissions
  ON principal_permissions.delegate_email =
     resolved.delegate_email
LEFT JOIN delegate_permissions
  ON delegate_permissions.delegate_email =
     resolved.delegate_email
 AND delegate_permissions.permission_code =
     principal_permissions.permission_code
GROUP BY
    resolved.delegate_email,
    resolved.delegate_id,
    principal.email,
    delegate.first_name,
    delegate.last_name
ORDER BY resolved.delegate_email;
