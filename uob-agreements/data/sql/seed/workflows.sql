-- Reusable workflow templates.

INSERT INTO workflow_templates (
    name,
    description,
    process_type,
    is_active
)
VALUES
    (
        'Agreement Approval',
        'Creator to initial VP review, mandatory Legal review, optional Finance review, final VP review, and President approval',
        'AGREEMENT',
        TRUE
    ),
    (
        'Initiative Approval',
        'Creator through department, college, VP, and President approval',
        'INITIATIVE',
        TRUE
    )
ON CONFLICT (name) DO UPDATE
SET
    description = EXCLUDED.description,
    process_type = EXCLUDED.process_type,
    is_active = TRUE;

-- Agreement workflow:
-- Creator -> VP -> Legal + optional Finance -> VP -> President

WITH stage_data (
    step_order,
    step_key,
    approval_type,
    required_unit_code,
    is_optional
) AS (
    VALUES
        (
            1,
            'CREATOR',
            'CREATOR'::workflow_approval_type,
            NULL,
            FALSE
        ),
        (
            2,
            'VP_INITIAL',
            'APPROVAL'::workflow_approval_type,
            'VP',
            FALSE
        ),
        (
            3,
            'LEGAL_REVIEW',
            'APPROVAL'::workflow_approval_type,
            'LEGAL',
            FALSE
        ),
        (
            4,
            'FINANCE_REVIEW',
            'APPROVAL'::workflow_approval_type,
            'FIN',
            TRUE
        ),
        (
            5,
            'VP_FINAL',
            'APPROVAL'::workflow_approval_type,
            'VP',
            FALSE
        ),
        (
            6,
            'PRESIDENT_APPROVAL',
            'APPROVAL'::workflow_approval_type,
            'PRES',
            FALSE
        )
)
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    step_key,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    sd.step_order,
    sd.step_key,
    sd.approval_type,
    ou.unit_id,
    NULL,
    sd.is_optional
FROM workflow_templates wt
CROSS JOIN stage_data sd
LEFT JOIN organizational_units ou
    ON ou.code = sd.required_unit_code
WHERE wt.name = 'Agreement Approval'
ON CONFLICT (
    workflow_template_id,
    step_order
) DO UPDATE
SET
    step_key = EXCLUDED.step_key,
    approval_type = EXCLUDED.approval_type,
    required_unit_id = EXCLUDED.required_unit_id,
    required_position_id = NULL,
    is_optional = EXCLUDED.is_optional;

-- Initiative workflow remains sequential. Department and college
-- resolution will later be made dynamic by the hierarchy resolver.

WITH stage_data (
    step_order,
    step_key,
    approval_type,
    required_unit_code,
    is_optional
) AS (
    VALUES
        (
            1,
            'CREATOR',
            'CREATOR'::workflow_approval_type,
            NULL,
            FALSE
        ),
        (
            2,
            'DEPARTMENT_HEAD',
            'APPROVAL'::workflow_approval_type,
            'CS',
            FALSE
        ),
        (
            3,
            'COLLEGE_HEAD',
            'APPROVAL'::workflow_approval_type,
            'CIT',
            FALSE
        ),
        (
            4,
            'VP_APPROVAL',
            'APPROVAL'::workflow_approval_type,
            'VP',
            FALSE
        ),
        (
            5,
            'PRESIDENT_APPROVAL',
            'APPROVAL'::workflow_approval_type,
            'PRES',
            FALSE
        )
)
INSERT INTO workflow_template_steps (
    workflow_template_id,
    step_order,
    step_key,
    approval_type,
    required_unit_id,
    required_position_id,
    is_optional
)
SELECT
    wt.workflow_template_id,
    sd.step_order,
    sd.step_key,
    sd.approval_type,
    ou.unit_id,
    NULL,
    sd.is_optional
FROM workflow_templates wt
CROSS JOIN stage_data sd
LEFT JOIN organizational_units ou
    ON ou.code = sd.required_unit_code
WHERE wt.name = 'Initiative Approval'
ON CONFLICT (
    workflow_template_id,
    step_order
) DO UPDATE
SET
    step_key = EXCLUDED.step_key,
    approval_type = EXCLUDED.approval_type,
    required_unit_id = EXCLUDED.required_unit_id,
    required_position_id = NULL,
    is_optional = EXCLUDED.is_optional;