-- ============================================================
-- Table: student_profiles
-- Links a student user to the academic Department that owns
-- the student's current major/specialization.
-- ============================================================

CREATE TABLE student_profiles (
    user_id BIGINT
        PRIMARY KEY,

    student_number VARCHAR(30)
        NOT NULL
        UNIQUE,

    department_id BIGINT
        NOT NULL,

    program_name VARCHAR(200),

    is_active BOOLEAN
        NOT NULL
        DEFAULT TRUE,

    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_student_profile_user
        FOREIGN KEY(user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_student_profile_department
        FOREIGN KEY(department_id)
        REFERENCES organizational_units(unit_id)
        ON DELETE RESTRICT
);

CREATE INDEX idx_student_profiles_department
    ON student_profiles(department_id);

CREATE INDEX idx_student_profiles_active
    ON student_profiles(is_active);
