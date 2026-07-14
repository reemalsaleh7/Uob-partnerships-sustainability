-- ============================================================
-- Table: user_positions
--
-- Purpose:
-- Stores the official positions held by users.
--
-- Connects:
-- User
-- Position
-- Organizational Unit
--
-- Examples:
-- Dean of College IT
-- Head of Computer Science Department
-- Doctor in Computer Science Department
-- ============================================================


CREATE TABLE user_positions (

    user_position_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    user_id BIGINT NOT NULL,


    position_id BIGINT NOT NULL,


    unit_id BIGINT NOT NULL,


    start_date DATE NOT NULL,


    end_date DATE,


    is_active BOOLEAN NOT NULL
        DEFAULT TRUE,


    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_user_positions_user

        FOREIGN KEY(user_id)

        REFERENCES users(user_id),


    CONSTRAINT fk_user_positions_position

        FOREIGN KEY(position_id)

        REFERENCES positions(position_id),


    CONSTRAINT fk_user_positions_unit

        FOREIGN KEY(unit_id)

        REFERENCES organizational_units(unit_id)

);