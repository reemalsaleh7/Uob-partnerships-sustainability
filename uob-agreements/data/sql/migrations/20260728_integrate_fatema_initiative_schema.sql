-- ==========================================================
-- 2026-07-28
-- Integrate Fatema's legacy Initiative SQL into PostgreSQL
-- branch: Fatema
--
-- Safe goals:
-- 1. Keep the repository's shared PostgreSQL workflow engine.
-- 2. Add Initiative business fields from the legacy MariaDB schema.
-- 3. Add immutable Initiative JSONB snapshots.
-- 4. Add Initiative participants and documents.
-- 5. Keep Agreement links many-to-many.
-- ==========================================================

-- This migration assumes the shared Agreement return-workflow
-- migration has already created the shared workflow additions.

ALTER TYPE initiative_status
    ADD VALUE IF NOT EXISTS 'REVISION_REQUIRED';

ALTER TYPE workflow_step_status
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_CREATOR';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_VP';

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'RESUBMITTED';

ALTER TABLE initiatives
    ADD COLUMN IF NOT EXISTS initiative_code VARCHAR(30),
    ADD COLUMN IF NOT EXISTS objectives TEXT,
    ADD COLUMN IF NOT EXISTS expected_budget NUMERIC(14, 2),
    ADD COLUMN IF NOT EXISTS planned_start_date DATE,
    ADD COLUMN IF NOT EXISTS planned_end_date DATE,
    ADD COLUMN IF NOT EXISTS actual_start_date DATE,
    ADD COLUMN IF NOT EXISTS actual_end_date DATE,
    ADD COLUMN IF NOT EXISTS revision_count INTEGER
        NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS final_decision_at TIMESTAMP;

CREATE UNIQUE INDEX IF NOT EXISTS
    uq_initiatives_initiative_code
    ON initiatives(initiative_code)
    WHERE initiative_code IS NOT NULL;

CREATE INDEX IF NOT EXISTS
    idx_initiatives_status
    ON initiatives(status);

CREATE INDEX IF NOT EXISTS
    idx_initiatives_created_by
    ON initiatives(created_by);

CREATE INDEX IF NOT EXISTS
    idx_initiatives_planned_dates
    ON initiatives(planned_start_date, planned_end_date);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'initiatives'::regclass
          AND conname =
              'chk_initiative_budget_nonnegative'
    ) THEN
        ALTER TABLE initiatives
            ADD CONSTRAINT
                chk_initiative_budget_nonnegative
            CHECK (
                expected_budget IS NULL
                OR expected_budget >= 0
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'initiatives'::regclass
          AND conname =
              'chk_initiative_revision_count_nonnegative'
    ) THEN
        ALTER TABLE initiatives
            ADD CONSTRAINT
                chk_initiative_revision_count_nonnegative
            CHECK (revision_count >= 0);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'initiatives'::regclass
          AND conname =
              'chk_initiative_planned_dates'
    ) THEN
        ALTER TABLE initiatives
            ADD CONSTRAINT
                chk_initiative_planned_dates
            CHECK (
                planned_start_date IS NULL
                OR planned_end_date IS NULL
                OR planned_end_date >= planned_start_date
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'initiatives'::regclass
          AND conname =
              'chk_initiative_actual_dates'
    ) THEN
        ALTER TABLE initiatives
            ADD CONSTRAINT
                chk_initiative_actual_dates
            CHECK (
                actual_start_date IS NULL
                OR actual_end_date IS NULL
                OR actual_end_date >= actual_start_date
            );
    END IF;
END
$$;

ALTER TABLE initiative_versions
    ADD COLUMN IF NOT EXISTS initiative_snapshot JSONB;

-- Backfill old version rows with a snapshot from the current
-- Initiative record. New application writes must always supply
-- the exact snapshot that belongs to that version.
UPDATE initiative_versions iv
SET initiative_snapshot = jsonb_build_object(
    'initiative_id', i.initiative_id,
    'initiative_code', i.initiative_code,
    'title', i.title,
    'description', i.description,
    'objectives', i.objectives,
    'initiative_type', i.initiative_type,
    'expected_budget', i.expected_budget,
    'planned_start_date', i.planned_start_date,
    'planned_end_date', i.planned_end_date,
    'actual_start_date', i.actual_start_date,
    'actual_end_date', i.actual_end_date,
    'status', i.status,
    'revision_count', i.revision_count,
    'submitted_at', i.submitted_at,
    'final_decision_at', i.final_decision_at,
    'created_by', i.created_by,
    'created_at', i.created_at,
    'updated_at', i.updated_at
)
FROM initiatives i
WHERE i.initiative_id = iv.initiative_id
  AND iv.initiative_snapshot IS NULL;

ALTER TABLE initiative_versions
    ALTER COLUMN initiative_snapshot SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'initiative_versions'::regclass
          AND conname =
              'chk_initiative_snapshot_object'
    ) THEN
        ALTER TABLE initiative_versions
            ADD CONSTRAINT
                chk_initiative_snapshot_object
            CHECK (
                jsonb_typeof(initiative_snapshot)
                = 'object'
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'initiative_versions'::regclass
          AND conname =
              'chk_initiative_version_positive'
    ) THEN
        ALTER TABLE initiative_versions
            ADD CONSTRAINT
                chk_initiative_version_positive
            CHECK (version_number > 0);
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS
    idx_initiative_versions_created_at
    ON initiative_versions(
        initiative_id,
        created_at DESC
    );

ALTER TABLE initiative_agreements
    ADD COLUMN IF NOT EXISTS
        relation_notes VARCHAR(500),
    ADD COLUMN IF NOT EXISTS
        created_at TIMESTAMP
        NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS
    idx_initiative_agreements_agreement
    ON initiative_agreements(agreement_id);

CREATE TABLE IF NOT EXISTS initiative_participants (
    initiative_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    participant_role VARCHAR(50) NOT NULL,
    added_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL
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

CREATE INDEX IF NOT EXISTS
    idx_initiative_participants_user
    ON initiative_participants(user_id);

CREATE TABLE IF NOT EXISTS initiative_documents (
    document_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    initiative_id BIGINT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255),
    file_path TEXT NOT NULL,
    mime_type VARCHAR(150),
    file_size BIGINT,
    document_type VARCHAR(100),
    uploaded_by BIGINT NOT NULL,
    uploaded_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_initiative_document_initiative
        FOREIGN KEY(initiative_id)
        REFERENCES initiatives(initiative_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_initiative_document_uploader
        FOREIGN KEY(uploaded_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_initiative_document_size
        CHECK (
            file_size IS NULL
            OR file_size >= 0
        )
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_documents_initiative
    ON initiative_documents(initiative_id);

COMMENT ON COLUMN initiatives.description IS
    'Legacy initiative request summary maps to this column.';

COMMENT ON TABLE initiative_participants IS
    'Participants formerly stored against initiative requests.';

COMMENT ON COLUMN initiative_versions.initiative_snapshot IS
    'Immutable complete Initiative state for this version.';
