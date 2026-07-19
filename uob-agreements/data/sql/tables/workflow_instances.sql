CREATE TABLE workflow_instances (
    workflow_instance_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_template_id BIGINT
        NOT NULL,

    entity_type VARCHAR(50)
        NOT NULL,

    entity_id BIGINT
        NOT NULL,

    current_step INTEGER
        NOT NULL
        DEFAULT 1,

    -- NULL: initial VP decision has not been made.
    -- FALSE: Legal review only.
    -- TRUE: Legal and Finance reviews.
    finance_review_required BOOLEAN,

    -- Starts at 1 and increases whenever a revised
    -- Agreement is resubmitted for another review cycle.
    review_cycle INTEGER
        NOT NULL
        DEFAULT 1,

    status workflow_status
        NOT NULL
        DEFAULT 'IN_PROGRESS',

    started_by BIGINT
        NOT NULL,

    started_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    completed_at TIMESTAMP,

    CONSTRAINT fk_workflow_instance_template
        FOREIGN KEY (workflow_template_id)
        REFERENCES workflow_templates(workflow_template_id),

    CONSTRAINT fk_workflow_instance_starter
        FOREIGN KEY (started_by)
        REFERENCES users(user_id),

        CONSTRAINT chk_workflow_review_cycle_positive
        CHECK (review_cycle > 0)
);