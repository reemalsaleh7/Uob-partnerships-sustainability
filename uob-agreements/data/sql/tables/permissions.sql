CREATE TABLE permissions (

    permission_id
        BIGINT GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    permission_code
        VARCHAR(100)
        NOT NULL
        UNIQUE,

    permission_name
        VARCHAR(255)
        NOT NULL,

    description
        TEXT,

    created_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
);