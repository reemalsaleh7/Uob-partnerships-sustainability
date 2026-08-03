-- Phase 13
-- Full approved-Initiative form stored entirely in PostgreSQL.
-- database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiatives
    ADD COLUMN IF NOT EXISTS final_form_data JSONB
        NOT NULL DEFAULT '{}'::jsonb,
    ADD COLUMN IF NOT EXISTS final_form_version INTEGER
        NOT NULL DEFAULT 1;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_final_form_json'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_final_form_json
            CHECK (jsonb_typeof(final_form_data) = 'object');
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_final_form_version'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_final_form_version
            CHECK (final_form_version >= 1);
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.initiative_conversion_attachments (
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
    CONSTRAINT chk_initiative_conversion_attachment_size
        CHECK (
            file_size_bytes > 0
            AND file_size_bytes <= 20971520
        )
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_conversion_attachments_request
ON public.initiative_conversion_attachments (
    request_id,
    uploaded_at,
    attachment_id
);

CREATE TABLE IF NOT EXISTS public.initiative_people (
    initiative_person_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,
    initiative_id BIGINT NOT NULL
        REFERENCES public.initiatives(initiative_id)
        ON DELETE CASCADE,
    user_id BIGINT
        REFERENCES public.users(user_id)
        ON DELETE SET NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    mobile VARCHAR(60),
    role_label VARCHAR(200),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_coordinator BOOLEAN NOT NULL DEFAULT FALSE,
    created_by BIGINT NOT NULL
        REFERENCES public.users(user_id)
        ON DELETE RESTRICT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_initiative_people_identity
        CHECK (
            NULLIF(BTRIM(COALESCE(full_name, '')), '') IS NOT NULL
            OR NULLIF(BTRIM(COALESCE(email, '')), '') IS NOT NULL
        )
);

CREATE INDEX IF NOT EXISTS idx_initiative_people_initiative
ON public.initiative_people (
    initiative_id,
    is_primary DESC,
    is_coordinator DESC,
    initiative_person_id
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_initiative_people_user
ON public.initiative_people (initiative_id, user_id)
WHERE user_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS public.initiative_attachments (
    attachment_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,
    initiative_id BIGINT NOT NULL
        REFERENCES public.initiatives(initiative_id)
        ON DELETE CASCADE,
    source_request_attachment_id BIGINT
        REFERENCES public.initiative_request_attachments(attachment_id)
        ON DELETE SET NULL,
    source_conversion_attachment_id BIGINT
        REFERENCES public.initiative_conversion_attachments(attachment_id)
        ON DELETE SET NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    file_extension VARCHAR(20) NOT NULL,
    mime_type VARCHAR(150),
    file_size_bytes BIGINT NOT NULL,
    added_by BIGINT NOT NULL
        REFERENCES public.users(user_id)
        ON DELETE RESTRICT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_initiative_attachment_size
        CHECK (
            file_size_bytes > 0
            AND file_size_bytes <= 20971520
        )
);

CREATE UNIQUE INDEX IF NOT EXISTS
    uq_initiative_attachment_storage
ON public.initiative_attachments (
    initiative_id,
    storage_path
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_attachments_initiative
ON public.initiative_attachments (
    initiative_id,
    created_at,
    attachment_id
);

UPDATE public.initiatives initiative
SET final_form_data = jsonb_strip_nulls(
    jsonb_build_object(
        'approval_request_id', request.request_code,
        'title', initiative.title,
        'initiative_type', initiative.initiative_type,
        'description', initiative.description,
        'objectives', initiative.objectives,
        'expected_impact', initiative.expected_impact,
        'beneficiaries', initiative.beneficiaries,
        'expected_budget', initiative.expected_budget,
        'start_date', initiative.planned_start_date,
        'end_date', initiative.planned_end_date,
        'related_agreement_id', (
            SELECT link.agreement_id
            FROM public.initiative_agreements link
            WHERE link.initiative_id = initiative.initiative_id
            ORDER BY link.agreement_id
            LIMIT 1
        )
    )
)
FROM public.initiative_requests request
WHERE request.request_id = initiative.source_request_id
  AND initiative.final_form_data = '{}'::jsonb;
