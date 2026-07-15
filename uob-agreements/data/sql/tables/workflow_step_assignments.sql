CREATE TABLE workflow_step_assignments (
    assignment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workflow_instance_step_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY(workflow_instance_step_id) REFERENCES workflow_instance_steps(instance_step_id),
    FOREIGN KEY(user_id) REFERENCES users(user_id)
);
