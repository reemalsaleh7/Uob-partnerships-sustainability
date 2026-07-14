CREATE TABLE role_permissions (

    role_id
        BIGINT NOT NULL,

    permission_id
        BIGINT NOT NULL,

    PRIMARY KEY (role_id, permission_id),

    FOREIGN KEY (role_id)
        REFERENCES roles(role_id),

    FOREIGN KEY (permission_id)
        REFERENCES permissions(permission_id)
);