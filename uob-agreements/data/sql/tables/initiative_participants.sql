-- ============================================================
-- Table: initiative_participants
-- Participants migrated from the legacy request participant model
-- ============================================================

CREATE TABLE initiative_participants (
    initiative_id BIGINT
        NOT NULL,

    user_id BIGINT
        NOT NULL,

    participant_role VARCHAR(50)
        NOT NULL,

    added_by BIGINT
        NOT NULL,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY(initiative_id, user_id),

    CONSTRAINT fk_initiative_participant_initiative
        FOREIGN KEY(initiative_id)
        REFERENCES initiatives(initiative_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_initiative_participant_user
        FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_initiative_participant_added_by
        FOREIGN KEY(added_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_initiative_participant_role
        CHECK (
            participant_role IN (
                'FACULTY',
                'DEPARTMENT_HEAD',
                'COLLEGE_HEAD'
            )
        )
);

CREATE INDEX idx_initiative_participants_user
    ON initiative_participants(user_id);
