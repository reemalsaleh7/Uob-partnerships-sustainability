-- Phase 12
-- Expanded Initiative request form stored entirely in PostgreSQL.
-- database-manager.cmd controls the transaction.

ALTER TABLE public.initiative_requests
    ADD COLUMN IF NOT EXISTS requester_name_snapshot VARCHAR(255),
    ADD COLUMN IF NOT EXISTS requester_email_snapshot VARCHAR(255),
    ADD COLUMN IF NOT EXISTS requester_mobile VARCHAR(40),
    ADD COLUMN IF NOT EXISTS requester_type VARCHAR(40),
    ADD COLUMN IF NOT EXISTS requester_type_other VARCHAR(150),
    ADD COLUMN IF NOT EXISTS requester_position_snapshot VARCHAR(200),
    ADD COLUMN IF NOT EXISTS requester_entity_snapshot VARCHAR(200),
    ADD COLUMN IF NOT EXISTS requester_department_snapshot VARCHAR(200),
    ADD COLUMN IF NOT EXISTS primary_type_other VARCHAR(150),
    ADD COLUMN IF NOT EXISTS secondary_types JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS target_groups JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS target_group_other VARCHAR(200),
    ADD COLUMN IF NOT EXISTS expected_participants INTEGER,
    ADD COLUMN IF NOT EXISTS implementation_scope VARCHAR(40),
    ADD COLUMN IF NOT EXISTS implementation_scope_other VARCHAR(200),
    ADD COLUMN IF NOT EXISTS proposed_venue VARCHAR(300),
    ADD COLUMN IF NOT EXISTS has_related_agreement BOOLEAN,
    ADD COLUMN IF NOT EXISTS has_external_partner BOOLEAN,
    ADD COLUMN IF NOT EXISTS external_partner_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS external_partner_role TEXT,
    ADD COLUMN IF NOT EXISTS required_resources JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS resource_other VARCHAR(200),
    ADD COLUMN IF NOT EXISTS estimated_budget NUMERIC(14, 3),
    ADD COLUMN IF NOT EXISTS needs_media_support BOOLEAN,
    ADD COLUMN IF NOT EXISTS supports_sdg BOOLEAN,
    ADD COLUMN IF NOT EXISTS sdg_goals JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS declaration_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS declaration_confirmed_at TIMESTAMP;

UPDATE public.initiative_requests request
SET
    requester_name_snapshot = COALESCE(
        request.requester_name_snapshot,
        CONCAT(requester.first_name, ' ', requester.last_name)
    ),
    requester_email_snapshot = COALESCE(
        request.requester_email_snapshot,
        requester.email
    ),
    requester_mobile = COALESCE(
        request.requester_mobile,
        requester.phone
    ),
    requester_type = COALESCE(
        request.requester_type,
        CASE request.requester_role_key
            WHEN 'FACULTY' THEN 'FACULTY'
            WHEN 'DEPARTMENT_HEAD' THEN 'FACULTY'
            WHEN 'DEAN' THEN 'FACULTY'
            WHEN 'STUDENT' THEN 'STUDENT'
            ELSE 'STAFF'
        END
    ),
    has_related_agreement = COALESCE(
        request.has_related_agreement,
        request.related_agreement_id IS NOT NULL
    )
FROM public.users requester
WHERE requester.user_id = request.requester_id;

UPDATE public.initiative_requests request
SET
    requester_position_snapshot = COALESCE(
        request.requester_position_snapshot,
        profile.position_name
    ),
    requester_entity_snapshot = COALESCE(
        request.requester_entity_snapshot,
        profile.entity_name
    ),
    requester_department_snapshot = COALESCE(
        request.requester_department_snapshot,
        profile.department_name
    )
FROM (
    SELECT DISTINCT ON (user_position.user_id)
        user_position.user_id,
        position.name AS position_name,
        COALESCE(parent_unit.name, unit.name)
            AS entity_name,
        unit.name AS department_name
    FROM public.user_positions user_position
    JOIN public.positions position
      ON position.position_id = user_position.position_id
    JOIN public.organizational_units unit
      ON unit.unit_id = user_position.unit_id
    LEFT JOIN public.organizational_units parent_unit
      ON parent_unit.unit_id = unit.parent_unit_id
    WHERE user_position.is_active = TRUE
      AND (
            user_position.end_date IS NULL
            OR user_position.end_date >= CURRENT_DATE
      )
    ORDER BY
        user_position.user_id,
        user_position.start_date DESC NULLS LAST,
        user_position.user_position_id DESC
) profile
WHERE profile.user_id = request.requester_id;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_expected_participants'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_expected_participants
            CHECK (
                expected_participants IS NULL
                OR expected_participants >= 0
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_estimated_budget'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_estimated_budget
            CHECK (
                estimated_budget IS NULL
                OR estimated_budget >= 0
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_secondary_types_json'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_secondary_types_json
            CHECK (jsonb_typeof(secondary_types) = 'array');
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_target_groups_json'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_target_groups_json
            CHECK (jsonb_typeof(target_groups) = 'array');
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_resources_json'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_resources_json
            CHECK (jsonb_typeof(required_resources) = 'array');
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_sdg_goals_json'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_sdg_goals_json
            CHECK (jsonb_typeof(sdg_goals) = 'array');
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.initiative_request_attachments (
    attachment_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,
    request_id BIGINT NOT NULL
        REFERENCES public.initiative_requests(request_id)
        ON DELETE CASCADE,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL UNIQUE,
    storage_path VARCHAR(500) NOT NULL,
    file_extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(150),
    file_size_bytes BIGINT NOT NULL,
    uploaded_by BIGINT NOT NULL
        REFERENCES public.users(user_id)
        ON DELETE RESTRICT,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_initiative_request_attachment_size
        CHECK (
            file_size_bytes > 0
            AND file_size_bytes <= 10485760
        )
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_request_attachments_request
ON public.initiative_request_attachments (
    request_id,
    uploaded_at,
    attachment_id
);
