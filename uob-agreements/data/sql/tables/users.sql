CREATE TABLE users (

    user_id
        BIGINT GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    university_id
        VARCHAR(30)
        NOT NULL
        UNIQUE,

    first_name
        VARCHAR(100)
        NOT NULL,

    last_name
        VARCHAR(100)
        NOT NULL,

    email
        VARCHAR(255)
        NOT NULL
        UNIQUE,

    phone
        VARCHAR(30),

    password_hash
        TEXT
        NOT NULL,

    last_login
        TIMESTAMP,

    failed_login_attempts
        INTEGER NOT NULL
        DEFAULT 0,

    locked_until
        TIMESTAMP,

    password_changed_at
        TIMESTAMP,

    is_active
        BOOLEAN NOT NULL
        DEFAULT TRUE,

    created_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
);
