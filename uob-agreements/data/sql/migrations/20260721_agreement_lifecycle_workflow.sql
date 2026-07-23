BEGIN;

ALTER TABLE agreement_lifecycle_requests
    ADD COLUMN IF NOT EXISTS workflow_instance_id BIGINT,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS decided_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS decided_by BIGINT,
    ADD COLUMN IF NOT EXISTS decision_comments TEXT,
    ADD COLUMN IF NOT EXISTS applied_at TIMESTAMP;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_lifecycle_request_workflow'
    ) THEN
        ALTER TABLE agreement_lifecycle_requests
            ADD CONSTRAINT fk_lifecycle_request_workflow
            FOREIGN KEY (workflow_instance_id)
            REFERENCES workflow_instances(workflow_instance_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_lifecycle_request_decider'
    ) THEN
        ALTER TABLE agreement_lifecycle_requests
            ADD CONSTRAINT fk_lifecycle_request_decider
            FOREIGN KEY (decided_by)
            REFERENCES users(user_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'agreement_lifecycle_request_status_check'
    ) THEN
        ALTER TABLE agreement_lifecycle_requests
            ADD CONSTRAINT agreement_lifecycle_request_status_check
            CHECK (status IN (
                'DRAFT', 'UNDER_REVIEW', 'REVISION_REQUIRED',
                'APPROVED', 'REJECTED', 'CANCELLED'
            ));
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS ux_lifecycle_request_workflow
    ON agreement_lifecycle_requests (workflow_instance_id)
    WHERE workflow_instance_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_lifecycle_requests_requester_status
    ON agreement_lifecycle_requests (requested_by, status, updated_at DESC);

CREATE UNIQUE INDEX IF NOT EXISTS ux_open_lifecycle_request_per_type
    ON agreement_lifecycle_requests (agreement_id, request_type)
    WHERE status IN ('DRAFT', 'UNDER_REVIEW', 'REVISION_REQUIRED');

CREATE TABLE IF NOT EXISTS agreement_lifecycle_request_versions (
    lifecycle_request_version_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lifecycle_request_id BIGINT NOT NULL
        REFERENCES agreement_lifecycle_requests(lifecycle_request_id)
        ON DELETE CASCADE,
    version_number INTEGER NOT NULL CHECK (version_number > 0),
    request_snapshot JSONB NOT NULL,
    change_summary TEXT,
    created_by BIGINT NOT NULL REFERENCES users(user_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (lifecycle_request_id, version_number)
);

INSERT INTO workflow_templates (
    name, description, process_type, is_active
) VALUES (
    'Agreement Lifecycle Approval',
    'Approval of Agreement renewal, amendment, and termination requests through VP, Legal, optional Finance, final VP, and President',
    'AGREEMENT_LIFECYCLE',
    TRUE
)
ON CONFLICT (name) DO UPDATE SET
    description = EXCLUDED.description,
    process_type = EXCLUDED.process_type,
    is_active = TRUE;

WITH lifecycle_template AS (
    SELECT workflow_template_id
    FROM workflow_templates
    WHERE name = 'Agreement Lifecycle Approval'
), stage_data (
    step_order, step_key, approval_type, required_unit_code, is_optional
) AS (
    VALUES
        (1, 'CREATOR', 'CREATOR'::workflow_approval_type, NULL, FALSE),
        (2, 'VP_INITIAL', 'APPROVAL'::workflow_approval_type, 'VP', FALSE),
        (3, 'LEGAL_REVIEW', 'APPROVAL'::workflow_approval_type, 'LEGAL', FALSE),
        (4, 'FINANCE_REVIEW', 'APPROVAL'::workflow_approval_type, 'FIN', TRUE),
        (5, 'VP_FINAL', 'APPROVAL'::workflow_approval_type, 'VP', FALSE),
        (6, 'PRESIDENT_APPROVAL', 'APPROVAL'::workflow_approval_type, 'PRES', FALSE)
)
INSERT INTO workflow_template_steps (
    workflow_template_id, step_order, step_key, approval_type,
    required_unit_id, required_position_id, is_optional
)
SELECT
    lt.workflow_template_id,
    sd.step_order,
    sd.step_key,
    sd.approval_type,
    ou.unit_id,
    NULL,
    sd.is_optional
FROM lifecycle_template lt
CROSS JOIN stage_data sd
LEFT JOIN organizational_units ou ON ou.code = sd.required_unit_code
ON CONFLICT (workflow_template_id, step_order) DO UPDATE SET
    step_key = EXCLUDED.step_key,
    approval_type = EXCLUDED.approval_type,
    required_unit_id = EXCLUDED.required_unit_id,
    required_position_id = EXCLUDED.required_position_id,
    is_optional = EXCLUDED.is_optional;

COMMIT;
