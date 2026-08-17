-- Rebuild the clean Initiative Approval template after removing test-only data.
-- database-manager.cmd owns the transaction.

DO $$
DECLARE
    v_template_id BIGINT;
    v_department_head_id BIGINT;
    v_dean_id BIGINT;
    v_vice_president_id BIGINT;
    v_president_id BIGINT;
    v_vp_unit_id BIGINT;
    v_president_unit_id BIGINT;
BEGIN
    IF EXISTS (
        SELECT 1
        FROM workflow_templates
        WHERE template_key = 'INITIATIVE_APPROVAL'
           OR process_type = 'INITIATIVE'
    ) THEN
        RAISE EXCEPTION
            'An Initiative Workflow template already exists; refusing to create a duplicate.';
    END IF;

    SELECT position_id INTO v_department_head_id
    FROM positions
    WHERE LOWER(name) = 'department head'
    LIMIT 1;

    SELECT position_id INTO v_dean_id
    FROM positions
    WHERE LOWER(name) = 'dean'
    LIMIT 1;

    SELECT position_id INTO v_vice_president_id
    FROM positions
    WHERE LOWER(name) = 'vice president'
    LIMIT 1;

    SELECT position_id INTO v_president_id
    FROM positions
    WHERE LOWER(name) = 'president'
    LIMIT 1;

    SELECT unit_id INTO v_vp_unit_id
    FROM organizational_units
    WHERE UPPER(code) = 'VP'
    LIMIT 1;

    SELECT unit_id INTO v_president_unit_id
    FROM organizational_units
    WHERE UPPER(code) = 'PRES'
    LIMIT 1;

    IF v_department_head_id IS NULL
       OR v_dean_id IS NULL
       OR v_vice_president_id IS NULL
       OR v_president_id IS NULL
       OR v_vp_unit_id IS NULL
       OR v_president_unit_id IS NULL THEN
        RAISE EXCEPTION
            'Required Initiative Workflow positions or organizational units are missing.';
    END IF;

    INSERT INTO workflow_templates (
        name,
        description,
        process_type,
        is_active,
        template_key,
        version_number,
        change_reason,
        published_at,
        updated_at
    )
    VALUES (
        'Initiative Approval',
        'Creator, Department Head, Dean, Vice President, and President approval route',
        'INITIATIVE',
        TRUE,
        'INITIATIVE_APPROVAL',
        1,
        'Clean initial Initiative approval template',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    )
    RETURNING workflow_template_id INTO v_template_id;

    INSERT INTO workflow_template_steps (
        workflow_template_id,
        step_order,
        step_key,
        approval_type,
        required_unit_id,
        required_position_id,
        is_optional,
        step_label,
        phase_order,
        execution_mode,
        responsibility_type,
        responsibility_scope,
        required_permission_code,
        reminder_after_days,
        is_system_step,
        allow_revision
    )
    VALUES
        (
            v_template_id, 1, 'CREATOR',
            'CREATOR'::workflow_approval_type,
            NULL, NULL, FALSE,
            'Initiative Creator',
            1, 'SEQUENTIAL',
            'CREATOR', 'NONE',
            'APPROVE_INITIATIVE',
            3, TRUE, TRUE
        ),
        (
            v_template_id, 2, 'DEPARTMENT_HEAD',
            'APPROVAL'::workflow_approval_type,
            NULL, v_department_head_id, FALSE,
            'Department Head',
            2, 'SEQUENTIAL',
            'POSITION', 'REQUESTER_DEPARTMENT',
            'APPROVE_INITIATIVE',
            3, TRUE, TRUE
        ),
        (
            v_template_id, 3, 'DEAN',
            'APPROVAL'::workflow_approval_type,
            NULL, v_dean_id, FALSE,
            'Dean',
            3, 'SEQUENTIAL',
            'POSITION', 'REQUESTER_COLLEGE',
            'APPROVE_INITIATIVE',
            3, TRUE, TRUE
        ),
        (
            v_template_id, 4, 'VICE_PRESIDENT',
            'APPROVAL'::workflow_approval_type,
            v_vp_unit_id, v_vice_president_id, FALSE,
            'Vice President / Office',
            4, 'SEQUENTIAL',
            'POSITION', 'FIXED_UNIT',
            'APPROVE_INITIATIVE',
            3, TRUE, TRUE
        ),
        (
            v_template_id, 5, 'PRESIDENT',
            'APPROVAL'::workflow_approval_type,
            v_president_unit_id, v_president_id, FALSE,
            'President / Office',
            5, 'SEQUENTIAL',
            'POSITION', 'FIXED_UNIT',
            'APPROVE_INITIATIVE',
            3, TRUE, TRUE
        );

    IF (
        SELECT COUNT(*)
        FROM workflow_template_steps
        WHERE workflow_template_id = v_template_id
    ) <> 5 THEN
        RAISE EXCEPTION
            'Initiative Approval template must contain exactly five stages.';
    END IF;
END
$$;
