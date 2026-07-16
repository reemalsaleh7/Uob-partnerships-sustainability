CREATE TABLE roles (

    role_id
        BIGINT GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    role_name
        VARCHAR(100)
        NOT NULL
        UNIQUE,

    description
        TEXT,

    created_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
);