-- ============================================================
-- Unify Initiative Workflow runtime with the shared Workflow engine
--
-- Physical tables removed:
--   initiative_request_stages
--   initiative_request_events
--   initiative_admin_skips
--
-- Compatibility views with the same names preserve existing PHP/UI reads
-- and legacy writes while all data is stored in:
--   workflow_instances
--   workflow_instance_steps
--   workflow_step_assignments
--   workflow_history
--
-- IMPORTANT:
-- - database-manager.cmd owns the transaction.
-- - Do not add BEGIN, COMMIT, or ROLLBACK.
-- - Existing Initiative requests, cycles, stages, events, reminders,
--   revision-thread links, decisions, and administrative skips are migrated.
-- ============================================================

DROP VIEW IF EXISTS v_initiative_request_timeline;
DROP VIEW IF EXISTS v_pending_workflows;

-- Keep a transaction-local baseline for final row-count verification.
CREATE TEMP TABLE initiative_runtime_unification_counts
ON COMMIT DROP
AS
SELECT
    (SELECT COUNT(*) FROM initiative_request_stages) AS stage_count,
    (SELECT COUNT(*) FROM initiative_request_events) AS event_count,
    (SELECT COUNT(*) FROM initiative_admin_skips) AS skip_count;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM workflow_instances instance
        WHERE instance.entity_type = 'INITIATIVE_REQUEST'
    ) THEN
        RAISE EXCEPTION
            'Shared Workflow tables already contain Initiative-request instances; refusing an ambiguous duplicate migration.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM workflow_instance_steps
        WHERE instance_step_id < 0
    ) THEN
        RAISE EXCEPTION
            'Negative Workflow step identifiers already exist; safe Initiative identifier mapping is unavailable.';
    END IF;
END
$$;

-- Migration 20260805_111900 expands workflow_step_status first. The database
-- manager applies each migration in its own transaction, so the new enum values
-- are committed and available before this data migration begins.

ALTER TABLE workflow_instances
    ADD COLUMN IF NOT EXISTS cycle_number INTEGER NOT NULL DEFAULT 0;

CREATE UNIQUE INDEX IF NOT EXISTS ux_initiative_workflow_instance_cycle
    ON workflow_instances (entity_type, entity_id, cycle_number)
    WHERE entity_type = 'INITIATIVE_REQUEST';

ALTER TABLE workflow_instance_steps
    ADD COLUMN IF NOT EXISTS acted_on_behalf_of_user_id BIGINT
        REFERENCES users(user_id),
    ADD COLUMN IF NOT EXISTS opened_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS due_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS last_reminder_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS is_office_delegable BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS is_skippable BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS ix_workflow_instance_steps_due
    ON workflow_instance_steps (status, due_at, last_reminder_at)
    WHERE status = 'IN_PROGRESS';

ALTER TABLE workflow_history
    ALTER COLUMN workflow_step_id DROP NOT NULL,
    ALTER COLUMN action DROP NOT NULL,
    ALTER COLUMN performed_by DROP NOT NULL;

ALTER TABLE workflow_history
    ADD COLUMN IF NOT EXISTS event_type VARCHAR(50),
    ADD COLUMN IF NOT EXISTS target_user_id BIGINT REFERENCES users(user_id),
    ADD COLUMN IF NOT EXISTS from_status VARCHAR(40),
    ADD COLUMN IF NOT EXISTS to_status VARCHAR(40),
    ADD COLUMN IF NOT EXISTS event_data JSONB NOT NULL DEFAULT '{}'::JSONB;

CREATE INDEX IF NOT EXISTS ix_workflow_history_event_type
    ON workflow_history (
        workflow_instance_id,
        event_type,
        created_at DESC,
        history_id DESC
    );

CREATE UNIQUE INDEX IF NOT EXISTS ux_workflow_history_initiative_skip_step
    ON workflow_history (workflow_step_id)
    WHERE event_type = 'ADMIN_SKIP_DETAIL';

-- Create or reuse the single shared Workflow instance for one Initiative
-- request and one revision cycle.
CREATE OR REPLACE FUNCTION initiative_runtime_instance_id(
    p_request_id BIGINT,
    p_cycle_number INTEGER,
    p_template_step_id BIGINT DEFAULT NULL
)
RETURNS BIGINT
LANGUAGE plpgsql
AS $$
DECLARE
    v_instance_id BIGINT;
    v_template_id BIGINT;
    v_template_version INTEGER;
    v_requester_id BIGINT;
    v_request_status VARCHAR(40);
    v_started_at TIMESTAMP;
BEGIN
    SELECT instance.workflow_instance_id
    INTO v_instance_id
    FROM workflow_instances instance
    WHERE instance.entity_type = 'INITIATIVE_REQUEST'
      AND instance.entity_id = p_request_id
      AND instance.cycle_number = COALESCE(p_cycle_number, 0)
    ORDER BY instance.workflow_instance_id
    LIMIT 1;

    IF v_instance_id IS NOT NULL THEN
        RETURN v_instance_id;
    END IF;

    SELECT
        request.requester_id,
        request.status,
        COALESCE(request.submitted_at, request.created_at),
        request.workflow_template_id,
        request.workflow_template_version
    INTO
        v_requester_id,
        v_request_status,
        v_started_at,
        v_template_id,
        v_template_version
    FROM initiative_requests request
    WHERE request.request_id = p_request_id
    FOR UPDATE;

    IF v_requester_id IS NULL THEN
        RAISE EXCEPTION
            'Initiative request % was not found while creating its shared Workflow instance.',
            p_request_id;
    END IF;

    IF p_template_step_id IS NOT NULL THEN
        SELECT
            template.workflow_template_id,
            template.version_number
        INTO
            v_template_id,
            v_template_version
        FROM workflow_template_steps template_step
        JOIN workflow_templates template
          ON template.workflow_template_id =
             template_step.workflow_template_id
        WHERE template_step.template_step_id = p_template_step_id;
    END IF;

    IF v_template_id IS NULL THEN
        SELECT
            template.workflow_template_id,
            template.version_number
        INTO
            v_template_id,
            v_template_version
        FROM workflow_templates template
        WHERE template.template_key = 'INITIATIVE_APPROVAL'
          AND template.process_type = 'INITIATIVE'
          AND template.is_active = TRUE
        ORDER BY
            template.version_number DESC,
            template.workflow_template_id DESC
        LIMIT 1;
    END IF;

    IF v_template_id IS NULL THEN
        RAISE EXCEPTION
            'The active Initiative Workflow template was not found.';
    END IF;

    INSERT INTO workflow_instances (
        workflow_template_id,
        entity_type,
        entity_id,
        current_step,
        status,
        started_by,
        started_at,
        completed_at,
        current_phase_order,
        template_version_number,
        engine_version,
        cycle_number
    ) VALUES (
        v_template_id,
        'INITIATIVE_REQUEST',
        p_request_id,
        1,
        CASE
            WHEN v_request_status IN ('APPROVED', 'CONVERTING', 'CONVERTED')
                THEN 'COMPLETED'::workflow_status
            WHEN v_request_status = 'REJECTED'
                THEN 'REJECTED'::workflow_status
            WHEN v_request_status = 'CANCELLED'
                THEN 'CANCELLED'::workflow_status
            ELSE 'IN_PROGRESS'::workflow_status
        END,
        v_requester_id,
        COALESCE(v_started_at, CURRENT_TIMESTAMP),
        CASE
            WHEN v_request_status IN (
                'APPROVED',
                'CONVERTING',
                'CONVERTED',
                'REJECTED',
                'CANCELLED'
            )
                THEN CURRENT_TIMESTAMP
            ELSE NULL
        END,
        NULL,
        v_template_version,
        'CONFIGURABLE_INITIATIVE_V2',
        COALESCE(p_cycle_number, 0)
    )
    RETURNING workflow_instance_id
    INTO v_instance_id;

    RETURN v_instance_id;
END
$$;

-- One instance per request/cycle is created before stages and events move.
WITH initiative_cycles AS (
    SELECT request_id, cycle_number
    FROM initiative_request_stages

    UNION

    SELECT request_id, cycle_number
    FROM initiative_request_events
)
SELECT initiative_runtime_instance_id(
    cycle.request_id,
    cycle.cycle_number,
    NULL
)
FROM initiative_cycles cycle;

-- Preserve current and historical cycle state.
UPDATE workflow_instances instance
SET
    status = CASE
        WHEN instance.cycle_number < request.revision_cycle
            THEN 'CANCELLED'::workflow_status
        WHEN request.status IN ('APPROVED', 'CONVERTING', 'CONVERTED')
            THEN 'COMPLETED'::workflow_status
        WHEN request.status = 'REJECTED'
            THEN 'REJECTED'::workflow_status
        WHEN request.status = 'CANCELLED'
            THEN 'CANCELLED'::workflow_status
        WHEN request.status = 'REVISION_REQUIRED'
            THEN 'CANCELLED'::workflow_status
        ELSE 'IN_PROGRESS'::workflow_status
    END,
    current_step = CASE
        WHEN instance.cycle_number = request.revision_cycle
            THEN COALESCE(request.current_stage_order, instance.current_step, 1)
        ELSE COALESCE(
            (
                SELECT MAX(stage.stage_order)
                FROM initiative_request_stages stage
                WHERE stage.request_id = request.request_id
                  AND stage.cycle_number = instance.cycle_number
            ),
            instance.current_step,
            1
        )
    END,
    current_phase_order = CASE
        WHEN instance.cycle_number = request.revision_cycle
            THEN COALESCE(
                request.current_phase_order,
                request.current_stage_order
            )
        ELSE NULL
    END,
    completed_at = CASE
        WHEN instance.cycle_number < request.revision_cycle
          OR request.status IN (
                'APPROVED',
                'CONVERTING',
                'CONVERTED',
                'REJECTED',
                'CANCELLED',
                'REVISION_REQUIRED'
             )
            THEN COALESCE(
                request.approved_at,
                request.rejected_at,
                request.converted_at,
                request.updated_at
            )
        ELSE NULL
    END,
    engine_version = 'CONFIGURABLE_INITIATIVE_V2'
FROM initiative_requests request
WHERE instance.entity_type = 'INITIATIVE_REQUEST'
  AND instance.entity_id = request.request_id;

-- Negative identifiers preserve a deterministic, collision-free mapping from
-- every former Initiative stage to its shared Workflow step.
INSERT INTO workflow_instance_steps (
    instance_step_id,
    workflow_instance_id,
    template_step_id,
    step_order,
    step_key,
    step_label,
    phase_order,
    execution_mode,
    responsibility_type,
    responsibility_scope,
    required_permission_code,
    reminder_after_days,
    allow_revision,
    assigned_unit_id,
    assigned_position_id,
    status,
    approved_by,
    approved_at,
    acted_on_behalf_of_user_id,
    started_at,
    opened_at,
    completed_at,
    due_at,
    last_reminder_at,
    comments,
    is_optional,
    is_office_delegable,
    is_skippable,
    created_at
)
OVERRIDING SYSTEM VALUE
SELECT
    -stage.request_stage_id,
    instance.workflow_instance_id,
    stage.template_step_id,
    stage.stage_order,
    stage.stage_key,
    stage.stage_label,
    stage.phase_order,
    stage.execution_mode,
    CASE
        WHEN stage.required_position_id IS NOT NULL
            THEN 'POSITION'
        ELSE 'UNIT'
    END,
    stage.responsibility_scope,
    stage.required_permission_code,
    stage.reminder_after_days,
    COALESCE(template_step.allow_revision, TRUE),
    stage.responsible_unit_id,
    stage.required_position_id,
    stage.status::workflow_step_status,
    stage.acted_by_user_id,
    CASE
        WHEN stage.status = 'APPROVED'
            THEN stage.acted_at
        ELSE NULL
    END,
    stage.acted_on_behalf_of_user_id,
    stage.received_at,
    stage.opened_at,
    stage.acted_at,
    stage.due_at,
    stage.last_reminder_at,
    stage.decision_comment,
    stage.is_optional,
    stage.is_office_delegable,
    stage.is_skippable,
    stage.created_at
FROM initiative_request_stages stage
JOIN workflow_instances instance
  ON instance.entity_type = 'INITIATIVE_REQUEST'
 AND instance.entity_id = stage.request_id
 AND instance.cycle_number = stage.cycle_number
LEFT JOIN workflow_template_steps template_step
  ON template_step.template_step_id = stage.template_step_id
ORDER BY stage.request_stage_id;

INSERT INTO workflow_step_assignments (
    workflow_instance_step_id,
    user_id,
    assigned_at,
    is_active
)
SELECT
    -stage.request_stage_id,
    stage.assigned_user_id,
    COALESCE(stage.received_at, stage.created_at),
    TRUE
FROM initiative_request_stages stage
WHERE stage.assigned_user_id IS NOT NULL
ON CONFLICT DO NOTHING;

-- Store Initiative events and administrative skip details in shared history.
INSERT INTO workflow_history (
    workflow_instance_id,
    workflow_step_id,
    action,
    performed_by,
    comments,
    created_at,
    event_type,
    target_user_id,
    from_status,
    to_status,
    event_data
)
SELECT
    instance.workflow_instance_id,
    CASE
        WHEN event.request_stage_id IS NULL
            THEN NULL
        ELSE -event.request_stage_id
    END,
    NULL,
    event.actor_user_id,
    event.event_note,
    event.occurred_at,
    event.event_type,
    event.target_user_id,
    event.from_status,
    event.to_status,
    event.event_data
FROM initiative_request_events event
JOIN workflow_instances instance
  ON instance.entity_type = 'INITIATIVE_REQUEST'
 AND instance.entity_id = event.request_id
 AND instance.cycle_number = event.cycle_number
ORDER BY event.event_id;

INSERT INTO workflow_history (
    workflow_instance_id,
    workflow_step_id,
    action,
    performed_by,
    comments,
    created_at,
    event_type,
    target_user_id,
    from_status,
    to_status,
    event_data
)
SELECT
    instance.workflow_instance_id,
    -skip_record.request_stage_id,
    NULL,
    skip_record.skipped_by,
    skip_record.mandatory_reason,
    skip_record.skipped_at,
    'ADMIN_SKIP_DETAIL',
    skip_record.skipped_user_id,
    NULL,
    'SKIPPED',
    jsonb_build_object(
        'legacy_skip_id',
        skip_record.skip_id
    )
FROM initiative_admin_skips skip_record
JOIN workflow_instances instance
  ON instance.entity_type = 'INITIATIVE_REQUEST'
 AND instance.entity_id = skip_record.request_id
JOIN initiative_request_stages stage
  ON stage.request_stage_id = skip_record.request_stage_id
 AND instance.cycle_number = stage.cycle_number
ORDER BY skip_record.skip_id;

-- Revision discussions keep their current column names for compatibility, but
-- the foreign keys now point to shared Workflow steps.
DO $$
DECLARE
    constraint_row RECORD;
BEGIN
    FOR constraint_row IN
        SELECT constraint_definition.conname
        FROM pg_constraint constraint_definition
        WHERE constraint_definition.conrelid =
              'initiative_revision_threads'::REGCLASS
          AND constraint_definition.confrelid =
              'initiative_request_stages'::REGCLASS
    LOOP
        EXECUTE FORMAT(
            'ALTER TABLE initiative_revision_threads DROP CONSTRAINT %I',
            constraint_row.conname
        );
    END LOOP;
END
$$;

UPDATE initiative_revision_threads
SET
    requested_by_stage_id = -requested_by_stage_id,
    counterpart_stage_id = CASE
        WHEN counterpart_stage_id IS NULL
            THEN NULL
        ELSE -counterpart_stage_id
    END;

ALTER TABLE initiative_revision_threads
    ADD CONSTRAINT fk_initiative_revision_requested_workflow_step
        FOREIGN KEY (requested_by_stage_id)
        REFERENCES workflow_instance_steps(instance_step_id),
    ADD CONSTRAINT fk_initiative_revision_counterpart_workflow_step
        FOREIGN KEY (counterpart_stage_id)
        REFERENCES workflow_instance_steps(instance_step_id);

COMMENT ON COLUMN initiative_revision_threads.requested_by_stage_id IS
    'Shared workflow_instance_steps.instance_step_id for the reviewer requesting revision.';

COMMENT ON COLUMN initiative_revision_threads.counterpart_stage_id IS
    'Shared workflow_instance_steps.instance_step_id for the paired reviewer stage.';

-- Existing notification payloads and reminder dedupe keys retain valid stage
-- references after the deterministic negative-ID migration.
UPDATE initiative_notifications
SET payload = jsonb_set(
        payload,
        '{request_stage_id}',
        to_jsonb(-((payload ->> 'request_stage_id')::BIGINT)),
        TRUE
    )
WHERE payload ? 'request_stage_id'
  AND (payload ->> 'request_stage_id') ~ '^[0-9]+$';

UPDATE initiative_notifications
SET dedupe_key =
        'STAGE_REMINDER:-'
        || SUBSTRING(
            dedupe_key
            FROM CHAR_LENGTH('STAGE_REMINDER:') + 1
        )
WHERE dedupe_key LIKE 'STAGE_REMINDER:%'
  AND SUBSTRING(
        dedupe_key
        FROM CHAR_LENGTH('STAGE_REMINDER:') + 1
      ) ~ '^[0-9]+$';

-- Requests with stage snapshots are now explicitly associated with their
-- shared Workflow template and engine version.
UPDATE initiative_requests request
SET
    workflow_template_id = instance.workflow_template_id,
    workflow_template_version = instance.template_version_number
FROM workflow_instances instance
WHERE instance.entity_type = 'INITIATIVE_REQUEST'
  AND instance.entity_id = request.request_id
  AND instance.cycle_number = request.revision_cycle
  AND EXISTS (
        SELECT 1
        FROM workflow_instance_steps step
        WHERE step.workflow_instance_id =
              instance.workflow_instance_id
    );

-- Remove the three duplicated physical tables.
DROP TABLE initiative_admin_skips;
DROP TABLE initiative_request_events;
DROP TABLE initiative_request_stages;

-- ============================================================
-- Compatibility view: Initiative stages
-- ============================================================

CREATE VIEW initiative_request_stages AS
SELECT
    step.instance_step_id AS request_stage_id,
    instance.entity_id AS request_id,
    instance.cycle_number,
    step.step_order AS stage_order,
    step.step_key AS stage_key,
    step.step_label AS stage_label,
    step.assigned_unit_id AS responsible_unit_id,
    assignment.user_id AS assigned_user_id,
    step.approved_by AS acted_by_user_id,
    step.acted_on_behalf_of_user_id,
    step.status,
    step.is_office_delegable,
    step.is_skippable,
    step.started_at AS received_at,
    step.opened_at,
    step.completed_at AS acted_at,
    step.due_at,
    step.reminder_after_days,
    step.last_reminder_at,
    step.comments AS decision_comment,
    step.created_at,
    step.template_step_id,
    step.phase_order,
    step.execution_mode,
    step.is_optional,
    step.assigned_position_id AS required_position_id,
    step.required_permission_code,
    step.responsibility_scope
FROM workflow_instances instance
JOIN workflow_instance_steps step
  ON step.workflow_instance_id =
     instance.workflow_instance_id
LEFT JOIN LATERAL (
    SELECT step_assignment.user_id
    FROM workflow_step_assignments step_assignment
    WHERE step_assignment.workflow_instance_step_id =
          step.instance_step_id
    ORDER BY
        step_assignment.is_active DESC,
        step_assignment.assigned_at DESC,
        step_assignment.assignment_id DESC
    LIMIT 1
) assignment
  ON TRUE
WHERE instance.entity_type = 'INITIATIVE_REQUEST';

CREATE OR REPLACE FUNCTION initiative_request_stages_compat_write()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_instance_id BIGINT;
    v_step_id BIGINT;
    v_request_id BIGINT;
    v_cycle_number INTEGER;
    v_template_id BIGINT;
    v_template_version INTEGER;
    v_responsibility_type VARCHAR(30);
    v_allow_revision BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' THEN
        v_instance_id := initiative_runtime_instance_id(
            NEW.request_id,
            COALESCE(NEW.cycle_number, 0),
            NEW.template_step_id
        );

        IF EXISTS (
            SELECT 1
            FROM workflow_instance_steps existing_step
            WHERE existing_step.workflow_instance_id = v_instance_id
              AND existing_step.step_order = NEW.stage_order
        ) THEN
            RAISE EXCEPTION
                'Initiative Workflow stage order % already exists in request % cycle %.',
                NEW.stage_order,
                NEW.request_id,
                COALESCE(NEW.cycle_number, 0);
        END IF;

        v_responsibility_type := CASE
            WHEN NEW.required_position_id IS NOT NULL
                THEN 'POSITION'
            ELSE 'UNIT'
        END;
        v_allow_revision := TRUE;

        IF NEW.template_step_id IS NOT NULL THEN
            SELECT
                template.workflow_template_id,
                template.version_number,
                template_step.responsibility_type,
                template_step.allow_revision
            INTO
                v_template_id,
                v_template_version,
                v_responsibility_type,
                v_allow_revision
            FROM workflow_template_steps template_step
            JOIN workflow_templates template
              ON template.workflow_template_id =
                 template_step.workflow_template_id
            WHERE template_step.template_step_id =
                  NEW.template_step_id;
        END IF;

        IF v_template_id IS NOT NULL
           AND NOT EXISTS (
                SELECT 1
                FROM workflow_instance_steps existing_step
                WHERE existing_step.workflow_instance_id =
                      v_instance_id
           ) THEN
            UPDATE workflow_instances
            SET
                workflow_template_id = v_template_id,
                template_version_number = v_template_version,
                engine_version = 'CONFIGURABLE_INITIATIVE_V2'
            WHERE workflow_instance_id = v_instance_id;

            UPDATE initiative_requests
            SET
                workflow_template_id = v_template_id,
                workflow_template_version = v_template_version
            WHERE request_id = NEW.request_id;
        END IF;

        INSERT INTO workflow_instance_steps (
            workflow_instance_id,
            template_step_id,
            step_order,
            step_key,
            step_label,
            phase_order,
            execution_mode,
            responsibility_type,
            responsibility_scope,
            required_permission_code,
            reminder_after_days,
            allow_revision,
            assigned_unit_id,
            assigned_position_id,
            status,
            approved_by,
            approved_at,
            acted_on_behalf_of_user_id,
            started_at,
            opened_at,
            completed_at,
            due_at,
            last_reminder_at,
            comments,
            is_optional,
            is_office_delegable,
            is_skippable,
            created_at
        ) VALUES (
            v_instance_id,
            NEW.template_step_id,
            NEW.stage_order,
            NEW.stage_key,
            NEW.stage_label,
            COALESCE(NEW.phase_order, NEW.stage_order),
            COALESCE(NEW.execution_mode, 'SEQUENTIAL'),
            COALESCE(v_responsibility_type, 'UNIT'),
            COALESCE(NEW.responsibility_scope, 'FIXED_UNIT'),
            COALESCE(
                NEW.required_permission_code,
                'APPROVE_INITIATIVE'
            ),
            COALESCE(NEW.reminder_after_days, 3),
            COALESCE(v_allow_revision, TRUE),
            NEW.responsible_unit_id,
            NEW.required_position_id,
            COALESCE(NEW.status, 'PENDING'),
            NEW.acted_by_user_id,
            CASE
                WHEN NEW.status = 'APPROVED'
                    THEN NEW.acted_at
                ELSE NULL
            END,
            NEW.acted_on_behalf_of_user_id,
            NEW.received_at,
            NEW.opened_at,
            NEW.acted_at,
            NEW.due_at,
            NEW.last_reminder_at,
            NEW.decision_comment,
            COALESCE(NEW.is_optional, FALSE),
            COALESCE(NEW.is_office_delegable, FALSE),
            COALESCE(NEW.is_skippable, TRUE),
            COALESCE(NEW.created_at, CURRENT_TIMESTAMP)
        )
        RETURNING instance_step_id
        INTO v_step_id;

        IF NEW.assigned_user_id IS NOT NULL THEN
            INSERT INTO workflow_step_assignments (
                workflow_instance_step_id,
                user_id,
                assigned_at,
                is_active
            ) VALUES (
                v_step_id,
                NEW.assigned_user_id,
                COALESCE(
                    NEW.received_at,
                    NEW.created_at,
                    CURRENT_TIMESTAMP
                ),
                TRUE
            );
        END IF;

        NEW.request_stage_id := v_step_id;
        NEW.cycle_number := COALESCE(NEW.cycle_number, 0);
        NEW.phase_order := COALESCE(NEW.phase_order, NEW.stage_order);
        NEW.execution_mode := COALESCE(
            NEW.execution_mode,
            'SEQUENTIAL'
        );
        NEW.required_permission_code := COALESCE(
            NEW.required_permission_code,
            'APPROVE_INITIATIVE'
        );
        NEW.responsibility_scope := COALESCE(
            NEW.responsibility_scope,
            'FIXED_UNIT'
        );
        NEW.created_at := COALESCE(
            NEW.created_at,
            CURRENT_TIMESTAMP
        );

        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        SELECT
            instance.entity_id,
            instance.cycle_number
        INTO
            v_request_id,
            v_cycle_number
        FROM workflow_instance_steps step
        JOIN workflow_instances instance
          ON instance.workflow_instance_id =
             step.workflow_instance_id
        WHERE step.instance_step_id =
              OLD.request_stage_id
          AND instance.entity_type = 'INITIATIVE_REQUEST'
        FOR UPDATE OF step;

        IF v_request_id IS NULL THEN
            RAISE EXCEPTION
                'Initiative Workflow step % was not found.',
                OLD.request_stage_id;
        END IF;

        IF NEW.request_id IS DISTINCT FROM v_request_id
           OR COALESCE(NEW.cycle_number, 0)
              IS DISTINCT FROM v_cycle_number THEN
            RAISE EXCEPTION
                'An Initiative Workflow step cannot move to another request or revision cycle.';
        END IF;

        IF EXISTS (
            SELECT 1
            FROM workflow_instance_steps existing_step
            JOIN workflow_instances existing_instance
              ON existing_instance.workflow_instance_id =
                 existing_step.workflow_instance_id
            WHERE existing_instance.entity_type = 'INITIATIVE_REQUEST'
              AND existing_instance.entity_id = v_request_id
              AND existing_instance.cycle_number = v_cycle_number
              AND existing_step.step_order = NEW.stage_order
              AND existing_step.instance_step_id <>
                  OLD.request_stage_id
        ) THEN
            RAISE EXCEPTION
                'Initiative Workflow stage order % already exists in request % cycle %.',
                NEW.stage_order,
                v_request_id,
                v_cycle_number;
        END IF;

        UPDATE workflow_instance_steps
        SET
            template_step_id = NEW.template_step_id,
            step_order = NEW.stage_order,
            step_key = NEW.stage_key,
            step_label = NEW.stage_label,
            phase_order = COALESCE(
                NEW.phase_order,
                NEW.stage_order
            ),
            execution_mode = COALESCE(
                NEW.execution_mode,
                'SEQUENTIAL'
            ),
            responsibility_type = CASE
                WHEN NEW.required_position_id IS NOT NULL
                    THEN 'POSITION'
                ELSE 'UNIT'
            END,
            responsibility_scope = COALESCE(
                NEW.responsibility_scope,
                'FIXED_UNIT'
            ),
            required_permission_code = COALESCE(
                NEW.required_permission_code,
                'APPROVE_INITIATIVE'
            ),
            reminder_after_days = COALESCE(
                NEW.reminder_after_days,
                3
            ),
            assigned_unit_id = NEW.responsible_unit_id,
            assigned_position_id = NEW.required_position_id,
            status = NEW.status,
            approved_by = NEW.acted_by_user_id,
            approved_at = CASE
                WHEN NEW.status = 'APPROVED'
                    THEN NEW.acted_at
                ELSE NULL
            END,
            acted_on_behalf_of_user_id =
                NEW.acted_on_behalf_of_user_id,
            started_at = NEW.received_at,
            opened_at = NEW.opened_at,
            completed_at = NEW.acted_at,
            due_at = NEW.due_at,
            last_reminder_at = NEW.last_reminder_at,
            comments = NEW.decision_comment,
            is_optional = NEW.is_optional,
            is_office_delegable =
                NEW.is_office_delegable,
            is_skippable = NEW.is_skippable,
            created_at = NEW.created_at
        WHERE instance_step_id =
              OLD.request_stage_id;

        UPDATE workflow_step_assignments
        SET is_active = FALSE
        WHERE workflow_instance_step_id =
              OLD.request_stage_id
          AND is_active = TRUE
          AND (
                NEW.assigned_user_id IS NULL
                OR user_id <> NEW.assigned_user_id
          );

        IF NEW.assigned_user_id IS NOT NULL
           AND NOT EXISTS (
                SELECT 1
                FROM workflow_step_assignments assignment
                WHERE assignment.workflow_instance_step_id =
                      OLD.request_stage_id
                  AND assignment.user_id =
                      NEW.assigned_user_id
                  AND assignment.is_active = TRUE
           ) THEN
            INSERT INTO workflow_step_assignments (
                workflow_instance_step_id,
                user_id,
                assigned_at,
                is_active
            ) VALUES (
                OLD.request_stage_id,
                NEW.assigned_user_id,
                CURRENT_TIMESTAMP,
                TRUE
            );
        END IF;

        NEW.request_stage_id := OLD.request_stage_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        IF EXISTS (
            SELECT 1
            FROM workflow_history history
            WHERE history.workflow_step_id =
                  OLD.request_stage_id
              AND history.event_type =
                  'ADMIN_SKIP_DETAIL'
        ) THEN
            RAISE EXCEPTION
                'An administratively skipped Initiative stage cannot be deleted.';
        END IF;

        UPDATE workflow_history
        SET workflow_step_id = NULL
        WHERE workflow_step_id =
              OLD.request_stage_id;

        DELETE FROM workflow_step_assignments
        WHERE workflow_instance_step_id =
              OLD.request_stage_id;

        DELETE FROM workflow_instance_steps
        WHERE instance_step_id =
              OLD.request_stage_id;

        RETURN OLD;
    END IF;

    RETURN NULL;
END
$$;

CREATE TRIGGER trg_initiative_request_stages_compat_write
INSTEAD OF INSERT OR UPDATE OR DELETE
ON initiative_request_stages
FOR EACH ROW
EXECUTE FUNCTION initiative_request_stages_compat_write();

-- ============================================================
-- Compatibility view: Initiative events
-- ============================================================

CREATE VIEW initiative_request_events AS
SELECT
    history.history_id AS event_id,
    instance.entity_id AS request_id,
    history.workflow_step_id AS request_stage_id,
    instance.cycle_number,
    COALESCE(history.event_type, history.action::TEXT) AS event_type,
    history.performed_by AS actor_user_id,
    history.target_user_id,
    history.from_status,
    history.to_status,
    history.comments AS event_note,
    history.event_data,
    history.created_at AS occurred_at
FROM workflow_history history
JOIN workflow_instances instance
  ON instance.workflow_instance_id =
     history.workflow_instance_id
WHERE instance.entity_type = 'INITIATIVE_REQUEST'
  AND COALESCE(history.event_type, '') <>
      'ADMIN_SKIP_DETAIL';

CREATE OR REPLACE FUNCTION initiative_request_events_compat_write()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_instance_id BIGINT;
    v_step_request_id BIGINT;
    v_step_cycle_number INTEGER;
    v_history_id BIGINT;
BEGIN
    IF TG_OP = 'DELETE' THEN
        DELETE FROM workflow_history
        WHERE history_id = OLD.event_id;
        RETURN OLD;
    END IF;

    IF NEW.request_stage_id IS NOT NULL THEN
        SELECT
            instance.workflow_instance_id,
            instance.entity_id,
            instance.cycle_number
        INTO
            v_instance_id,
            v_step_request_id,
            v_step_cycle_number
        FROM workflow_instance_steps step
        JOIN workflow_instances instance
          ON instance.workflow_instance_id =
             step.workflow_instance_id
        WHERE step.instance_step_id =
              NEW.request_stage_id
          AND instance.entity_type = 'INITIATIVE_REQUEST';

        IF v_instance_id IS NULL THEN
            RAISE EXCEPTION
                'Initiative Workflow step % was not found for event insertion.',
                NEW.request_stage_id;
        END IF;

        IF NEW.request_id IS DISTINCT FROM v_step_request_id
           OR COALESCE(NEW.cycle_number, 0)
              IS DISTINCT FROM v_step_cycle_number THEN
            RAISE EXCEPTION
                'The Initiative event request/cycle does not match its Workflow step.';
        END IF;
    ELSE
        v_instance_id := initiative_runtime_instance_id(
            NEW.request_id,
            COALESCE(NEW.cycle_number, 0),
            NULL
        );
    END IF;

    IF TG_OP = 'INSERT' THEN
        INSERT INTO workflow_history (
            workflow_instance_id,
            workflow_step_id,
            action,
            performed_by,
            comments,
            created_at,
            event_type,
            target_user_id,
            from_status,
            to_status,
            event_data
        ) VALUES (
            v_instance_id,
            NEW.request_stage_id,
            NULL,
            NEW.actor_user_id,
            NEW.event_note,
            COALESCE(NEW.occurred_at, CURRENT_TIMESTAMP),
            NEW.event_type,
            NEW.target_user_id,
            NEW.from_status,
            NEW.to_status,
            COALESCE(NEW.event_data, '{}'::JSONB)
        )
        RETURNING history_id
        INTO v_history_id;

        NEW.event_id := v_history_id;
        NEW.cycle_number := COALESCE(
            NEW.cycle_number,
            v_step_cycle_number,
            0
        );
        NEW.occurred_at := COALESCE(
            NEW.occurred_at,
            CURRENT_TIMESTAMP
        );
        RETURN NEW;
    END IF;

    UPDATE workflow_history
    SET
        workflow_instance_id = v_instance_id,
        workflow_step_id = NEW.request_stage_id,
        action = NULL,
        performed_by = NEW.actor_user_id,
        comments = NEW.event_note,
        created_at = NEW.occurred_at,
        event_type = NEW.event_type,
        target_user_id = NEW.target_user_id,
        from_status = NEW.from_status,
        to_status = NEW.to_status,
        event_data = COALESCE(
            NEW.event_data,
            '{}'::JSONB
        )
    WHERE history_id = OLD.event_id;

    NEW.event_id := OLD.event_id;
    RETURN NEW;
END
$$;

CREATE TRIGGER trg_initiative_request_events_compat_write
INSTEAD OF INSERT OR UPDATE OR DELETE
ON initiative_request_events
FOR EACH ROW
EXECUTE FUNCTION initiative_request_events_compat_write();

-- ============================================================
-- Compatibility view: Initiative administrative skips
-- ============================================================

CREATE VIEW initiative_admin_skips AS
SELECT
    history.history_id AS skip_id,
    instance.entity_id AS request_id,
    history.workflow_step_id AS request_stage_id,
    history.performed_by AS skipped_by,
    history.target_user_id AS skipped_user_id,
    history.comments AS mandatory_reason,
    history.created_at AS skipped_at
FROM workflow_history history
JOIN workflow_instances instance
  ON instance.workflow_instance_id =
     history.workflow_instance_id
WHERE instance.entity_type = 'INITIATIVE_REQUEST'
  AND history.event_type = 'ADMIN_SKIP_DETAIL';

CREATE OR REPLACE FUNCTION initiative_admin_skips_compat_write()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_instance_id BIGINT;
    v_request_id BIGINT;
    v_history_id BIGINT;
BEGIN
    IF TG_OP = 'DELETE' THEN
        DELETE FROM workflow_history
        WHERE history_id = OLD.skip_id;
        RETURN OLD;
    END IF;

    IF NEW.mandatory_reason IS NULL
       OR CHAR_LENGTH(BTRIM(NEW.mandatory_reason)) < 10 THEN
        RAISE EXCEPTION
            'An administrative skip reason of at least 10 characters is required.';
    END IF;

    SELECT
        instance.workflow_instance_id,
        instance.entity_id
    INTO
        v_instance_id,
        v_request_id
    FROM workflow_instance_steps step
    JOIN workflow_instances instance
      ON instance.workflow_instance_id =
         step.workflow_instance_id
    WHERE step.instance_step_id =
          NEW.request_stage_id
      AND instance.entity_type = 'INITIATIVE_REQUEST';

    IF v_instance_id IS NULL THEN
        RAISE EXCEPTION
            'Initiative Workflow step % was not found for administrative skip.',
            NEW.request_stage_id;
    END IF;

    IF NEW.request_id IS DISTINCT FROM v_request_id THEN
        RAISE EXCEPTION
            'The administrative skip request does not match its Workflow step.';
    END IF;

    IF TG_OP = 'INSERT' THEN
        INSERT INTO workflow_history (
            workflow_instance_id,
            workflow_step_id,
            action,
            performed_by,
            comments,
            created_at,
            event_type,
            target_user_id,
            from_status,
            to_status,
            event_data
        ) VALUES (
            v_instance_id,
            NEW.request_stage_id,
            NULL,
            NEW.skipped_by,
            NEW.mandatory_reason,
            COALESCE(NEW.skipped_at, CURRENT_TIMESTAMP),
            'ADMIN_SKIP_DETAIL',
            NEW.skipped_user_id,
            NULL,
            'SKIPPED',
            '{}'::JSONB
        )
        RETURNING history_id
        INTO v_history_id;

        NEW.skip_id := v_history_id;
        NEW.skipped_at := COALESCE(
            NEW.skipped_at,
            CURRENT_TIMESTAMP
        );
        RETURN NEW;
    END IF;

    UPDATE workflow_history
    SET
        workflow_instance_id = v_instance_id,
        workflow_step_id = NEW.request_stage_id,
        performed_by = NEW.skipped_by,
        comments = NEW.mandatory_reason,
        created_at = NEW.skipped_at,
        target_user_id = NEW.skipped_user_id,
        event_type = 'ADMIN_SKIP_DETAIL',
        to_status = 'SKIPPED'
    WHERE history_id = OLD.skip_id;

    NEW.skip_id := OLD.skip_id;
    RETURN NEW;
END
$$;

CREATE TRIGGER trg_initiative_admin_skips_compat_write
INSTEAD OF INSERT OR UPDATE OR DELETE
ON initiative_admin_skips
FOR EACH ROW
EXECUTE FUNCTION initiative_admin_skips_compat_write();

-- Preserve the old ON DELETE CASCADE behavior of the three physical runtime
-- tables even though workflow_instances.entity_id is intentionally polymorphic.
CREATE OR REPLACE FUNCTION initiative_runtime_cleanup_after_request_delete()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_instance_ids BIGINT[];
BEGIN
    SELECT ARRAY_AGG(instance.workflow_instance_id)
    INTO v_instance_ids
    FROM workflow_instances instance
    WHERE instance.entity_type = 'INITIATIVE_REQUEST'
      AND instance.entity_id = OLD.request_id;

    IF v_instance_ids IS NULL THEN
        RETURN OLD;
    END IF;

    DELETE FROM workflow_history
    WHERE workflow_instance_id = ANY(v_instance_ids);

    DELETE FROM workflow_step_assignments assignment
    USING workflow_instance_steps step
    WHERE assignment.workflow_instance_step_id =
          step.instance_step_id
      AND step.workflow_instance_id = ANY(v_instance_ids);

    DELETE FROM workflow_instance_steps
    WHERE workflow_instance_id = ANY(v_instance_ids);

    DELETE FROM workflow_instances
    WHERE workflow_instance_id = ANY(v_instance_ids);

    RETURN OLD;
END
$$;

DROP TRIGGER IF EXISTS trg_initiative_runtime_cleanup
ON initiative_requests;

CREATE TRIGGER trg_initiative_runtime_cleanup
AFTER DELETE ON initiative_requests
FOR EACH ROW
EXECUTE FUNCTION initiative_runtime_cleanup_after_request_delete();

-- Existing reporting views continue to use the compatibility relation names.
CREATE VIEW v_initiative_request_timeline AS
SELECT
    request.request_id,
    request.request_code,
    request.title,
    request.status AS request_status,
    stage.cycle_number,
    stage.stage_order,
    stage.stage_key,
    stage.stage_label,
    stage.status AS stage_status,
    stage.assigned_user_id,
    stage.acted_by_user_id,
    stage.received_at,
    stage.opened_at,
    stage.acted_at,
    CASE
        WHEN stage.status IN (
            'PENDING',
            'IN_PROGRESS',
            'DISCUSSING_REVISION'
        )
            THEN CURRENT_TIMESTAMP
                 - COALESCE(
                    stage.received_at,
                    stage.created_at
                 )
        ELSE COALESCE(
                stage.acted_at,
                stage.created_at
             )
             - COALESCE(
                stage.received_at,
                stage.created_at
             )
    END AS stage_elapsed,
    stage.due_at,
    stage.last_reminder_at
FROM initiative_requests request
JOIN initiative_request_stages stage
  ON stage.request_id = request.request_id;

CREATE VIEW v_pending_workflows AS
SELECT
    instance.workflow_instance_id,
    instance.entity_type,
    instance.entity_id,
    step.step_order,
    step.status,
    unit.name AS waiting_department
FROM workflow_instances instance
JOIN workflow_instance_steps step
  ON step.workflow_instance_id =
     instance.workflow_instance_id
LEFT JOIN organizational_units unit
  ON unit.unit_id = step.assigned_unit_id
WHERE step.status = 'PENDING';

COMMENT ON VIEW initiative_request_stages IS
    'Compatibility view backed by shared Workflow runtime tables; not a physical Initiative stage table.';

COMMENT ON VIEW initiative_request_events IS
    'Compatibility view backed by workflow_history; not a physical Initiative event table.';

COMMENT ON VIEW initiative_admin_skips IS
    'Compatibility view backed by workflow_history; not a physical Initiative skip table.';

-- Final migration integrity checks.
DO $$
DECLARE
    v_expected_stage_count BIGINT;
    v_expected_event_count BIGINT;
    v_expected_skip_count BIGINT;
    v_actual_stage_count BIGINT;
    v_actual_event_count BIGINT;
    v_actual_skip_count BIGINT;
BEGIN
    SELECT
        stage_count,
        event_count,
        skip_count
    INTO
        v_expected_stage_count,
        v_expected_event_count,
        v_expected_skip_count
    FROM initiative_runtime_unification_counts;

    SELECT COUNT(*)
    INTO v_actual_stage_count
    FROM initiative_request_stages;

    SELECT COUNT(*)
    INTO v_actual_event_count
    FROM initiative_request_events;

    SELECT COUNT(*)
    INTO v_actual_skip_count
    FROM initiative_admin_skips;

    IF v_actual_stage_count <> v_expected_stage_count THEN
        RAISE EXCEPTION
            'Initiative stage migration count mismatch: expected %, found %.',
            v_expected_stage_count,
            v_actual_stage_count;
    END IF;

    IF v_actual_event_count <> v_expected_event_count THEN
        RAISE EXCEPTION
            'Initiative event migration count mismatch: expected %, found %.',
            v_expected_event_count,
            v_actual_event_count;
    END IF;

    IF v_actual_skip_count <> v_expected_skip_count THEN
        RAISE EXCEPTION
            'Initiative skip migration count mismatch: expected %, found %.',
            v_expected_skip_count,
            v_actual_skip_count;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM initiative_revision_threads revision_thread
        LEFT JOIN workflow_instance_steps requested_step
          ON requested_step.instance_step_id =
             revision_thread.requested_by_stage_id
        WHERE requested_step.instance_step_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'At least one Initiative revision thread lost its requested-by Workflow step.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM initiative_revision_threads revision_thread
        LEFT JOIN workflow_instance_steps counterpart_step
          ON counterpart_step.instance_step_id =
             revision_thread.counterpart_stage_id
        WHERE revision_thread.counterpart_stage_id IS NOT NULL
          AND counterpart_step.instance_step_id IS NULL
    ) THEN
        RAISE EXCEPTION
            'At least one Initiative revision thread lost its counterpart Workflow step.';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM pg_class relation
        JOIN pg_namespace namespace
          ON namespace.oid = relation.relnamespace
        WHERE namespace.nspname = CURRENT_SCHEMA()
          AND relation.relname IN (
                'initiative_request_stages',
                'initiative_request_events',
                'initiative_admin_skips'
              )
          AND relation.relkind <> 'v'
    ) THEN
        RAISE EXCEPTION
            'A duplicated Initiative Workflow relation is still a physical table.';
    END IF;
END
$$;
