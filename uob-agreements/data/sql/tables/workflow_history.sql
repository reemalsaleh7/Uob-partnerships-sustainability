CREATE TABLE workflow_history (

    history_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_instance_id BIGINT
        NOT NULL,

    workflow_step_id BIGINT
        NOT NULL,

    action workflow_action_type
        NOT NULL,

    performed_by BIGINT
        NOT NULL,

    comments TEXT,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(workflow_instance_id)
        REFERENCES workflow_instances(workflow_instance_id),

    FOREIGN KEY(workflow_step_id)
        REFERENCES workflow_instance_steps(instance_step_id),

    FOREIGN KEY(performed_by)
        REFERENCES users(user_id)
);