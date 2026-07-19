CREATE TABLE workflow_template_steps (

    template_step_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_template_id BIGINT
        NOT NULL,

    step_order INTEGER
        NOT NULL,

    approval_type workflow_approval_type
        NOT NULL,

    required_unit_id BIGINT,

    required_position_id BIGINT,

    is_optional BOOLEAN
        NOT NULL
        DEFAULT FALSE,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_template_steps_template
        FOREIGN KEY(workflow_template_id)
        REFERENCES workflow_templates(workflow_template_id),

    CONSTRAINT fk_template_steps_unit
        FOREIGN KEY(required_unit_id)
        REFERENCES organizational_units(unit_id),

    CONSTRAINT fk_template_steps_position
        FOREIGN KEY(required_position_id)
        REFERENCES positions(position_id),

    CONSTRAINT uq_template_step_order
        UNIQUE (workflow_template_id, step_order),

    CONSTRAINT chk_step_order_positive
        CHECK (step_order > 0)
);