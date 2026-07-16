-- ============================================================
-- Table: user_roles
--
-- Purpose:
-- Connects users with system roles.
--
-- Allows a user to have multiple roles.
--
-- Example:
-- User Ahmed:
--   System Administrator
--   Report Viewer
-- ============================================================


CREATE TABLE user_roles (

    user_id BIGINT NOT NULL,

    role_id BIGINT NOT NULL,

    assigned_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    PRIMARY KEY (
        user_id,
        role_id
    ),


    CONSTRAINT fk_user_roles_user

        FOREIGN KEY(user_id)

        REFERENCES users(user_id)

        ON DELETE CASCADE,


    CONSTRAINT fk_user_roles_role

        FOREIGN KEY(role_id)

        REFERENCES roles(role_id)

        ON DELETE CASCADE

);