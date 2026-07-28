-- ============================================================
-- Table: initiatives
-- Updated by Fatema Initiative SQL integration
-- ============================================================

CREATE TABLE initiatives (
    initiative_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    initiative_code VARCHAR(30)
        UNIQUE,

    title VARCHAR(255)
        NOT NULL,

    description TEXT,
    objectives TEXT,

    initiative_type VARCHAR(100)
        NOT NULL,

    expected_budget NUMERIC(14, 2),

    planned_start_date DATE,
    planned_end_date DATE,
    actual_start_date DATE,
    actual_end_date DATE,

    status initiative_status
        NOT NULL
        DEFAULT 'DRAFT',

    revision_count INTEGER
        NOT NULL
        DEFAULT 0,

    submitted_at TIMESTAMP,
    final_decision_at TIMESTAMP,

    created_by BIGINT
        NOT NULL,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_initiative_creator
        FOREIGN KEY(created_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_initiative_title_not_empty
        CHECK (length(trim(title)) > 0),

    CONSTRAINT chk_initiative_type_not_empty
        CHECK (length(trim(initiative_type)) > 0),

    CONSTRAINT chk_initiative_budget_nonnegative
        CHECK (
            expected_budget IS NULL
            OR expected_budget >= 0
        ),

    CONSTRAINT chk_initiative_revision_count_nonnegative
        CHECK (revision_count >= 0),

    CONSTRAINT chk_initiative_planned_dates
        CHECK (
            planned_start_date IS NULL
            OR planned_end_date IS NULL
            OR planned_end_date >= planned_start_date
        ),

    CONSTRAINT chk_initiative_actual_dates
        CHECK (
            actual_start_date IS NULL
            OR actual_end_date IS NULL
            OR actual_end_date >= actual_start_date
        )
);

CREATE INDEX idx_initiatives_status
    ON initiatives(status);

CREATE INDEX idx_initiatives_created_by
    ON initiatives(created_by);

CREATE INDEX idx_initiatives_planned_dates
    ON initiatives(planned_start_date, planned_end_date);
