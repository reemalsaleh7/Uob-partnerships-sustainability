-- ==========================================================
-- 2026-07-28
-- Initiative approval workflow template and permissions
-- Target branch: Fatema
--
-- Approved route:
-- Creator -> Department Head -> Dean -> Vice President
-- -> President
--
-- Important:
-- - Department Head and Dean units are resolved at runtime from
--   the Initiative creator's organizational hierarchy.
-- - VP and President use their active office/unit positions.
-- - Self-approval skipping is application-service logic:
--   Department Head creator skips Department Head.
--   Dean creator skips Department Head and Dean.
-- ==========================================================

BEGIN;

-- ----------------------------------------------------------
-- 1. Ensure Initiative permissions exist
-- ----------------------------------------------------------

INSERT INTO permissions (
    permission_code,
    permission_name,
    description
)
SELECT
    v.permission_code,
    v.permission_name,
    v.description
FROM (
    VALUES
        (
            'CREATE_INITIATIVE',
            'Create Initiative',
            'Create and edit an Initiative draft'
        ),
        (
            'EDIT_INITIATIVE',
            'Edit Initiative',
            'Edit an Initiative when business rules allow'
        ),
        (
            'APPROVE_INITIATIVE',
            'Approve Initiative',
            'Approve assigned Initiative workflow steps'
        ),
        (
            'REJECT_INITIATIVE',
            'Reject Initiative',
            'Reject assigned Initiative workflow steps'
        )
) AS v(
    permission_code,
    permission_name,
    description
)
WHERE NOT EXISTS (
    SELECT 1
    FROM permissions p
    WHERE p.permission_code = v.permission_code
);

-- Update names/descriptions without producing duplicates.
UPDATE permissions p
SET
    permission_name = v.permission_name,
    description = COALESCE(
        p.description,
        v.description
    )
FROM (
    VALUES
        (
            'CREATE_INITIATIVE',
            'Create Initiative',
            'Create and edit an Initiative draft'
        ),
        (
            'EDIT_INITIATIVE',
            'Edit Initiative',
            'Edit an Initiative when business rules allow'
        ),
        (
            'APPROVE_INITIATIVE',
            'Approve Initiative',
            'Approve assigned Initiative workflow steps'
        ),
        (
            'REJECT_INITIATIVE',
            'Reject Initiative',
            'Reject assigned Initiative workflow steps'
        )
) AS v(
    permission_code,
    permission_name,
    description
)
WHERE p.permission_code = v.permission_code;

-- ----------------------------------------------------------
-- 2. Ensure Initiative roles exist
-- ----------------------------------------------------------

INSERT INTO roles (
    role_name,
    description
)
SELECT
    v.role_name,
    v.description
FROM (
    VALUES
        (
            'Initiative Creator',
            'Can create and edit eligible Initiatives'
        ),
        (
            'Initiative Approver',
            'Can review assigned Initiative workflow steps'
        )
) AS v(role_name, description)
WHERE NOT EXISTS (
    SELECT 1
    FROM roles r
    WHERE r.role_name = v.role_name
);

-- ----------------------------------------------------------
-- 3. Assign permissions to Initiative roles
-- ----------------------------------------------------------

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

-- ----------------------------------------------------------
-- 4. Validate the required positions
-- ----------------------------------------------------------

DO $$
DECLARE
    missing_positions TEXT;
BEGIN
    SELECT string_agg(required_name, ', ')
    INTO missing_positions
    FROM (
        VALUES
            ('Department Head'),
            ('Dean'),
            ('Vice President'),
            ('President')
    ) AS required(required_name)
    WHERE NOT EXISTS (
        SELECT 1
        FROM positions p
        WHERE p.name = required.required_name
    );

    IF missing_positions IS NOT NULL THEN
        RAISE EXCEPTION
            'Cannot create Initiative workflow. Missing positions: %',
            missing_positions;
    END IF;
END
$$;

-- ----------------------------------------------------------
-- 5. Validate VP and President organizational units
-- ----------------------------------------------------------

DO $$
DECLARE
    missing_units TEXT;
BEGIN
    SELECT string_agg(required_code, ', ')
    INTO missing_units
    FROM (
        VALUES ('VP'), ('PRES')
    ) AS required(required_code)
    WHERE NOT EXISTS (
        SELECT 1
        FROM organizational_units ou
        WHERE ou.code = required.required_code
          AND ou.is_active = TRUE
    );

    IF missing_units IS NOT NULL THEN
        RAISE EXCEPTION
            'Cannot create Initiative workflow. Missing active units: %',
            missing_units;
    END IF;
END
$$;

-- ----------------------------------------------------------
-- 6. Create or reactivate the Initiative template
-- ----------------------------------------------------------

INSERT INTO workflow_templates (
    name,
    description,
    process_type,
    is_active
)
VALUES (
    'Initiative Approval',
    'Creator -> Department Head -> Dean -> Vice President -> President',
    'INITIATIVE',
    TRUE
)
ON CONFLICT (name)
DO UPDATE SET
    description = EXCLUDED.description,
    process_type = EXCLUDED.process_type,
    is_active = TRUE;

-- Rebuild only this template's definitions. Runtime workflow
-- instances and their history are not changed.
DELETE FROM workflow_template_steps
WHERE workflow_template_id = (
    SELECT workflow_template_id
    FROM workflow_templates
    WHERE name = 'Initiative Approval'
);

-- Step 1: creator/submission step.
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    1,
    'CREATOR',
    NULL,
    NULL,
    FALSE
FROM workflow_templates wt
WHERE wt.name = 'Initiative Approval';

-- Step 2: Department Head in creator's Department.
-- Unit remains NULL intentionally; HierarchyResolver supplies it.
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    2,
    'APPROVAL',
    NULL,
    p.position_id,
    FALSE
FROM workflow_templates wt
CROSS JOIN positions p
WHERE wt.name = 'Initiative Approval'
  AND p.name = 'Department Head';

-- Step 3: Dean in creator's parent College.
-- Unit remains NULL intentionally; HierarchyResolver supplies it.
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    3,
    'APPROVAL',
    NULL,
    p.position_id,
    FALSE
FROM workflow_templates wt
CROSS JOIN positions p
WHERE wt.name = 'Initiative Approval'
  AND p.name = 'Dean';

-- Step 4: Vice President.
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    4,
    'APPROVAL',
    ou.unit_id,
    p.position_id,
    FALSE
FROM workflow_templates wt
CROSS JOIN organizational_units ou
CROSS JOIN positions p
WHERE wt.name = 'Initiative Approval'
  AND ou.code = 'VP'
  AND ou.is_active = TRUE
  AND p.name = 'Vice President';

-- Step 5: President.
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    5,
    'APPROVAL',
    ou.unit_id,
    p.position_id,
    FALSE
FROM workflow_templates wt
CROSS JOIN organizational_units ou
CROSS JOIN positions p
WHERE wt.name = 'Initiative Approval'
  AND ou.code = 'PRES'
  AND ou.is_active = TRUE
  AND p.name = 'President';

-- ----------------------------------------------------------
-- 7. Verification guard
-- ----------------------------------------------------------

DO $$
DECLARE
    step_count INTEGER;
BEGIN
    SELECT count(*)
    INTO step_count
    FROM workflow_template_steps wts
    JOIN workflow_templates wt
      ON wt.workflow_template_id =
         wts.workflow_template_id
    WHERE wt.name = 'Initiative Approval';

    IF step_count <> 5 THEN
        RAISE EXCEPTION
            'Initiative workflow expected 5 steps, found %',
            step_count;
    END IF;
END
$$;

COMMIT;
