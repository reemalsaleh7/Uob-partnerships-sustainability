CREATE TABLE positions (

    position_id
        BIGINT GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    position_type_id
        BIGINT NOT NULL,

    name
        VARCHAR(100)
        NOT NULL
        UNIQUE,

    description
        TEXT,

    is_unique
        BOOLEAN NOT NULL,

    created_at
        TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_position_type

        FOREIGN KEY(position_type_id)

        REFERENCES position_types(position_type_id)

);