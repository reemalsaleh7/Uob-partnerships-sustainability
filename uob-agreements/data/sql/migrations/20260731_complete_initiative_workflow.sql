BEGIN;

ALTER TYPE initiative_status ADD VALUE IF NOT EXISTS 'REVISION_REQUIRED';
ALTER TYPE initiative_status ADD VALUE IF NOT EXISTS 'ACTIVE';
ALTER TYPE workflow_step_status ADD VALUE IF NOT EXISTS 'CHANGES_REQUESTED';

ALTER TABLE initiatives
    ADD COLUMN IF NOT EXISTS initiative_code VARCHAR(30),
    ADD COLUMN IF NOT EXISTS objectives TEXT,
    ADD COLUMN IF NOT EXISTS expected_budget NUMERIC(14,2),
    ADD COLUMN IF NOT EXISTS planned_start_date DATE,
    ADD COLUMN IF NOT EXISTS planned_end_date DATE,
    ADD COLUMN IF NOT EXISTS revision_count INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS final_decision_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS converted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS converted_by BIGINT REFERENCES users(user_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_initiatives_code
ON initiatives(initiative_code) WHERE initiative_code IS NOT NULL;

CREATE TABLE IF NOT EXISTS initiative_participants (
    initiative_id BIGINT NOT NULL REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    participant_role VARCHAR(60) NOT NULL DEFAULT 'MEMBER',
    added_by BIGINT NOT NULL REFERENCES users(user_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (initiative_id, user_id)
);

CREATE TABLE IF NOT EXISTS initiative_revision_rounds (
    revision_round_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    initiative_id BIGINT NOT NULL REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    workflow_instance_id BIGINT REFERENCES workflow_instances(workflow_instance_id) ON DELETE SET NULL,
    round_number INTEGER NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    requested_by BIGINT NOT NULL REFERENCES users(user_id),
    request_comments TEXT NOT NULL,
    opened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    resolved_by BIGINT REFERENCES users(user_id),
    UNIQUE (initiative_id, round_number),
    CHECK (status IN ('OPEN','RESUBMITTED','CLOSED'))
);

CREATE TABLE IF NOT EXISTS initiative_revision_notes (
    revision_note_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    revision_round_id BIGINT NOT NULL REFERENCES initiative_revision_rounds(revision_round_id) ON DELETE CASCADE,
    author_id BIGINT NOT NULL REFERENCES users(user_id),
    note_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_events (
    initiative_event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    initiative_id BIGINT NOT NULL REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    actor_id BIGINT REFERENCES users(user_id),
    event_type VARCHAR(50) NOT NULL,
    event_data JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_initiative_events_entity
ON initiative_events(initiative_id, created_at DESC);

CREATE TABLE IF NOT EXISTS initiative_notifications (
    initiative_notification_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    initiative_id BIGINT REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_initiative_notifications_user
ON initiative_notifications(user_id, is_read, created_at DESC);

COMMIT;
