-- ============================================================
-- Table: agreement_actions
--
-- Purpose:
-- Stores lifecycle actions performed on agreements.
--
-- Examples:
-- Termination request
-- Cancellation
-- Closure
-- ============================================================


CREATE TABLE agreement_actions (

    action_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,


    agreement_id BIGINT
        NOT NULL,


    action_type VARCHAR(50)
        NOT NULL,


    reason TEXT,


    performed_by BIGINT
        NOT NULL,


    action_date TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,


    CONSTRAINT fk_action_agreement

        FOREIGN KEY(agreement_id)

        REFERENCES agreements(agreement_id)
        ON DELETE CASCADE,


    CONSTRAINT fk_action_user

        FOREIGN KEY(performed_by)

        REFERENCES users(user_id)

);