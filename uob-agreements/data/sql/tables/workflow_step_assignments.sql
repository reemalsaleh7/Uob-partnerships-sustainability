CREATE TABLE workflow_step_assignments (
    assignment_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_instance_step_id BIGINT
        NOT NULL,

    user_id BIGINT
        NOT NULL,

    assigned_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    is_active BOOLEAN
        NOT NULL
        DEFAULT TRUE,

    CONSTRAINT fk_step_assignment_step
        FOREIGN KEY (workflow_instance_step_id)
        REFERENCES workflow_instance_steps(instance_step_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_step_assignment_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);