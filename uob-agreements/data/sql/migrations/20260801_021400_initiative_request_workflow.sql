-- Initiative request workflow foundation
-- Forward-only migration for routed approvals, revisions, reminders,
-- collaboration, admin skips, and approved-Initiative conversion.

CREATE TABLE IF NOT EXISTS initiative_requests (
    request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_code VARCHAR(40) UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    initiative_type VARCHAR(100) NOT NULL,
    objective TEXT,
    expected_impact TEXT,
    beneficiaries TEXT,
    proposed_start_date DATE,
    proposed_end_date DATE,
    related_agreement_id BIGINT REFERENCES agreements(agreement_id) ON DELETE SET NULL,
    requester_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    requester_unit_id BIGINT REFERENCES organizational_units(unit_id) ON DELETE RESTRICT,
    requester_role_key VARCHAR(50) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'DRAFT' CHECK (status IN (
        'DRAFT','SUBMITTED','UNDER_REVIEW','REVISION_DISCUSSION',
        'REVISION_REQUIRED','RESUBMITTED','APPROVED','REJECTED',
        'CONVERTING','CONVERTED','CANCELLED'
    )),
    current_stage_order INTEGER,
    current_assignee_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    current_assignee_unit_id BIGINT REFERENCES organizational_units(unit_id) ON DELETE SET NULL,
    submitted_at TIMESTAMP,
    approved_at TIMESTAMP,
    rejected_at TIMESTAMP,
    converted_at TIMESTAMP,
    converted_by BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    initiative_id BIGINT REFERENCES initiatives(initiative_id) ON DELETE SET NULL,
    revision_cycle INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_request_members (
    request_member_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE RESTRICT,
    member_role VARCHAR(30) NOT NULL DEFAULT 'COLLABORATOR',
    can_convert_after_approval BOOLEAN NOT NULL DEFAULT TRUE,
    added_by BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (request_id, user_id)
);

CREATE TABLE IF NOT EXISTS initiative_request_stages (
    request_stage_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    cycle_number INTEGER NOT NULL DEFAULT 0,
    stage_order INTEGER NOT NULL,
    stage_key VARCHAR(50) NOT NULL,
    stage_label VARCHAR(150) NOT NULL,
    responsible_unit_id BIGINT REFERENCES organizational_units(unit_id) ON DELETE RESTRICT,
    assigned_user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    acted_by_user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    acted_on_behalf_of_user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING' CHECK (status IN (
        'PENDING','IN_PROGRESS','APPROVED','REJECTED',
        'CHANGES_REQUESTED','DISCUSSING_REVISION','SKIPPED','CANCELLED'
    )),
    is_office_delegable BOOLEAN NOT NULL DEFAULT FALSE,
    is_skippable BOOLEAN NOT NULL DEFAULT TRUE,
    received_at TIMESTAMP,
    opened_at TIMESTAMP,
    acted_at TIMESTAMP,
    due_at TIMESTAMP,
    reminder_after_days INTEGER NOT NULL DEFAULT 3,
    last_reminder_at TIMESTAMP,
    decision_comment TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (request_id, cycle_number, stage_order)
);

CREATE INDEX IF NOT EXISTS idx_initiative_request_stages_active
    ON initiative_request_stages (assigned_user_id, status, due_at);

CREATE TABLE IF NOT EXISTS initiative_request_events (
    event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    request_stage_id BIGINT REFERENCES initiative_request_stages(request_stage_id) ON DELETE SET NULL,
    cycle_number INTEGER NOT NULL DEFAULT 0,
    event_type VARCHAR(50) NOT NULL,
    actor_user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    target_user_id BIGINT REFERENCES users(user_id) ON DELETE SET NULL,
    from_status VARCHAR(40),
    to_status VARCHAR(40),
    event_note TEXT,
    event_data JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_revision_threads (
    revision_thread_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    cycle_number INTEGER NOT NULL,
    requested_by_stage_id BIGINT NOT NULL REFERENCES initiative_request_stages(request_stage_id),
    counterpart_stage_id BIGINT REFERENCES initiative_request_stages(request_stage_id),
    requested_by_user_id BIGINT NOT NULL REFERENCES users(user_id),
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    consolidated_note TEXT NOT NULL,
    sent_to_creator_at TIMESTAMP,
    resolved_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_revision_comments (
    revision_comment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    revision_thread_id BIGINT NOT NULL REFERENCES initiative_revision_threads(revision_thread_id) ON DELETE CASCADE,
    author_user_id BIGINT NOT NULL REFERENCES users(user_id),
    visibility VARCHAR(30) NOT NULL DEFAULT 'PARTICIPANTS',
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_admin_skips (
    skip_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    request_stage_id BIGINT NOT NULL UNIQUE REFERENCES initiative_request_stages(request_stage_id),
    skipped_by BIGINT NOT NULL REFERENCES users(user_id),
    skipped_user_id BIGINT REFERENCES users(user_id),
    mandatory_reason TEXT NOT NULL CHECK (length(trim(mandatory_reason)) >= 10),
    skipped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_notifications (
    notification_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    recipient_user_id BIGINT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    notification_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    read_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS initiative_conversion_drafts (
    conversion_draft_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    request_id BIGINT NOT NULL UNIQUE REFERENCES initiative_requests(request_id) ON DELETE CASCADE,
    prepared_by BIGINT NOT NULL REFERENCES users(user_id),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    initiative_type VARCHAR(100) NOT NULL,
    related_agreement_id BIGINT REFERENCES agreements(agreement_id) ON DELETE SET NULL,
    form_data JSONB NOT NULL DEFAULT '{}'::jsonb,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION initiative_touch_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_initiative_requests_updated_at ON initiative_requests;
CREATE TRIGGER trg_initiative_requests_updated_at
BEFORE UPDATE ON initiative_requests
FOR EACH ROW EXECUTE FUNCTION initiative_touch_updated_at();

CREATE OR REPLACE VIEW v_initiative_request_timeline AS
SELECT
    r.request_id, r.request_code, r.title, r.status AS request_status,
    s.cycle_number, s.stage_order, s.stage_key, s.stage_label,
    s.status AS stage_status, s.assigned_user_id, s.acted_by_user_id,
    s.received_at, s.opened_at, s.acted_at,
    CASE
        WHEN s.status IN ('PENDING','IN_PROGRESS','DISCUSSING_REVISION')
            THEN CURRENT_TIMESTAMP - COALESCE(s.received_at, s.created_at)
        ELSE COALESCE(s.acted_at, s.created_at)
             - COALESCE(s.received_at, s.created_at)
    END AS stage_elapsed,
    s.due_at, s.last_reminder_at
FROM initiative_requests r
JOIN initiative_request_stages s ON s.request_id = r.request_id;
