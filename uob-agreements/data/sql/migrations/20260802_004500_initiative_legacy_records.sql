-- Phase 14
-- Legacy Initiative records and Workspace portfolio entry.
-- database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiatives
    ADD COLUMN IF NOT EXISTS record_origin VARCHAR(20)
        NOT NULL DEFAULT 'WORKFLOW',
    ADD COLUMN IF NOT EXISTS legacy_reference VARCHAR(120),
    ADD COLUMN IF NOT EXISTS legacy_approval_date DATE,
    ADD COLUMN IF NOT EXISTS legacy_recorded_by BIGINT,
    ADD COLUMN IF NOT EXISTS legacy_recorded_at TIMESTAMP;

UPDATE public.initiatives
SET record_origin = 'LEGACY'
WHERE source_request_id IS NULL
  AND record_origin = 'WORKFLOW';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_record_origin'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_record_origin
            CHECK (record_origin IN ('WORKFLOW', 'LEGACY'));
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'fk_initiatives_legacy_recorded_by'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT fk_initiatives_legacy_recorded_by
            FOREIGN KEY (legacy_recorded_by)
            REFERENCES public.users(user_id)
            ON DELETE SET NULL;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_initiatives_record_origin
ON public.initiatives (record_origin, updated_at DESC);

CREATE TABLE IF NOT EXISTS public.initiative_legacy_drafts (
    draft_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,
    prepared_by BIGINT NOT NULL
        REFERENCES public.users(user_id)
        ON DELETE CASCADE,
    form_data JSONB NOT NULL DEFAULT '{}'::jsonb,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    initiative_id BIGINT
        REFERENCES public.initiatives(initiative_id)
        ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalized_at TIMESTAMP,
    CONSTRAINT chk_initiative_legacy_draft_json
        CHECK (jsonb_typeof(form_data) = 'object'),
    CONSTRAINT chk_initiative_legacy_draft_status
        CHECK (status IN ('DRAFT', 'FINALIZED'))
);

CREATE INDEX IF NOT EXISTS idx_initiative_legacy_drafts_user
ON public.initiative_legacy_drafts (
    prepared_by,
    status,
    updated_at DESC
);

CREATE TABLE IF NOT EXISTS public.initiative_legacy_attachments (
    attachment_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,
    draft_id BIGINT NOT NULL
        REFERENCES public.initiative_legacy_drafts(draft_id)
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
    CONSTRAINT chk_initiative_legacy_attachment_size
        CHECK (
            file_size_bytes > 0
            AND file_size_bytes <= 20971520
        )
);

CREATE INDEX IF NOT EXISTS idx_initiative_legacy_attachments_draft
ON public.initiative_legacy_attachments (
    draft_id,
    uploaded_at,
    attachment_id
);

ALTER TABLE public.initiative_attachments
    ADD COLUMN IF NOT EXISTS source_legacy_attachment_id BIGINT;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_attachments'::regclass
          AND conname = 'fk_initiative_attachment_legacy_source'
    ) THEN
        ALTER TABLE public.initiative_attachments
            ADD CONSTRAINT fk_initiative_attachment_legacy_source
            FOREIGN KEY (source_legacy_attachment_id)
            REFERENCES public.initiative_legacy_attachments(attachment_id)
            ON DELETE SET NULL;
    END IF;
END
$$;
