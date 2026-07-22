CREATE TABLE workflow_instance_steps (
    instance_step_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_instance_id BIGINT
        NOT NULL,

    template_step_id BIGINT,

    step_order INTEGER
        NOT NULL,

    step_key VARCHAR(50),

    assigned_unit_id BIGINT,

    assigned_position_id BIGINT,

    is_optional BOOLEAN
        NOT NULL
        DEFAULT FALSE,

    status workflow_step_status
        NOT NULL
        DEFAULT 'PENDING',

    approved_by BIGINT,

    approved_at TIMESTAMP,

    started_at TIMESTAMP,

    completed_at TIMESTAMP,

    comments TEXT,

    CONSTRAINT fk_instance_step_workflow
        FOREIGN KEY (workflow_instance_id)
        REFERENCES workflow_instances(workflow_instance_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_instance_step_template_step
        FOREIGN KEY (template_step_id)
        REFERENCES workflow_template_steps(template_step_id),

    CONSTRAINT fk_instance_step_unit
        FOREIGN KEY (assigned_unit_id)
        REFERENCES organizational_units(unit_id),

    CONSTRAINT fk_instance_step_position
        FOREIGN KEY (assigned_position_id)
        REFERENCES positions(position_id),

    CONSTRAINT fk_instance_step_approver
        FOREIGN KEY (approved_by)
        REFERENCES users(user_id),

    CONSTRAINT chk_instance_step_order_positive
        CHECK (step_order > 0)
);