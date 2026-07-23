CREATE TABLE workflow_instance_steps (

    instance_step_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_instance_id BIGINT
        NOT NULL,

    step_order INTEGER
        NOT NULL,

    assigned_unit_id BIGINT,

    assigned_position_id BIGINT,

    status workflow_step_status
        NOT NULL
        DEFAULT 'PENDING',

    approved_by BIGINT,

    approved_at TIMESTAMP,

    started_at TIMESTAMP,

    completed_at TIMESTAMP,

    comments TEXT,

    FOREIGN KEY(workflow_instance_id)
        REFERENCES workflow_instances(workflow_instance_id),

    FOREIGN KEY(assigned_unit_id)
        REFERENCES organizational_units(unit_id),

    FOREIGN KEY(assigned_position_id)
        REFERENCES positions(position_id),

    FOREIGN KEY(approved_by)
        REFERENCES users(user_id)
);