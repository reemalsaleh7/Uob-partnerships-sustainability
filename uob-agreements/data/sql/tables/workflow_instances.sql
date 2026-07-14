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


    status VARCHAR(50)
        NOT NULL
        DEFAULT 'IN_PROGRESS',


    started_by BIGINT
        NOT NULL,


    started_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    completed_at TIMESTAMP,


    FOREIGN KEY(workflow_template_id)

        REFERENCES workflow_templates(workflow_template_id),


    FOREIGN KEY(started_by)

        REFERENCES users(user_id)

);