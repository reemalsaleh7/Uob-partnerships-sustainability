-- Phase 10 - Approved Initiative request conversion
-- Forward-only migration. database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiatives
    ADD COLUMN IF NOT EXISTS source_request_id BIGINT,
    ADD COLUMN IF NOT EXISTS initiative_code VARCHAR(30),
    ADD COLUMN IF NOT EXISTS objectives TEXT,
    ADD COLUMN IF NOT EXISTS expected_impact TEXT,
    ADD COLUMN IF NOT EXISTS beneficiaries TEXT,
    ADD COLUMN IF NOT EXISTS expected_budget NUMERIC(14, 2),
    ADD COLUMN IF NOT EXISTS planned_start_date DATE,
    ADD COLUMN IF NOT EXISTS planned_end_date DATE,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP,
    ADD COLUMN IF NOT EXISTS final_decision_at TIMESTAMP;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'fk_initiatives_source_request'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT fk_initiatives_source_request
            FOREIGN KEY (source_request_id)
            REFERENCES public.initiative_requests(request_id)
            ON DELETE SET NULL;
    END IF;
END
$$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_initiatives_source_request
    ON public.initiatives(source_request_id)
    WHERE source_request_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_initiatives_initiative_code
    ON public.initiatives(initiative_code)
    WHERE initiative_code IS NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiative_conversion_budget_nonnegative'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiative_conversion_budget_nonnegative
            CHECK (expected_budget IS NULL OR expected_budget >= 0);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiative_conversion_planned_dates'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiative_conversion_planned_dates
            CHECK (
                planned_start_date IS NULL
                OR planned_end_date IS NULL
                OR planned_end_date >= planned_start_date
            );
    END IF;
END
$$;

ALTER TABLE public.initiative_agreements
    ADD COLUMN IF NOT EXISTS relation_notes VARCHAR(500),
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE public.initiative_versions
    ADD COLUMN IF NOT EXISTS initiative_snapshot JSONB;

UPDATE public.initiative_versions version_row
SET initiative_snapshot = jsonb_build_object(
    'initiative_id', initiative.initiative_id,
    'initiative_code', initiative.initiative_code,
    'source_request_id', initiative.source_request_id,
    'title', initiative.title,
    'description', initiative.description,
    'objectives', initiative.objectives,
    'expected_impact', initiative.expected_impact,
    'beneficiaries', initiative.beneficiaries,
    'initiative_type', initiative.initiative_type,
    'expected_budget', initiative.expected_budget,
    'planned_start_date', initiative.planned_start_date,
    'planned_end_date', initiative.planned_end_date,
    'status', initiative.status,
    'created_by', initiative.created_by,
    'created_at', initiative.created_at,
    'updated_at', initiative.updated_at
)
FROM public.initiatives initiative
WHERE initiative.initiative_id = version_row.initiative_id
  AND version_row.initiative_snapshot IS NULL;

ALTER TABLE public.initiative_versions
    ALTER COLUMN initiative_snapshot SET NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_initiative_versions_number
    ON public.initiative_versions(initiative_id, version_number);

CREATE TABLE IF NOT EXISTS public.initiative_participants (
    initiative_id BIGINT NOT NULL
        REFERENCES public.initiatives(initiative_id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL
        REFERENCES public.users(user_id) ON DELETE RESTRICT,
    participant_role VARCHAR(50) NOT NULL,
    added_by BIGINT NOT NULL
        REFERENCES public.users(user_id) ON DELETE RESTRICT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (initiative_id, user_id)
);

ALTER TABLE public.initiative_participants
    DROP CONSTRAINT IF EXISTS chk_initiative_participant_role;

ALTER TABLE public.initiative_participants
    ADD CONSTRAINT chk_initiative_participant_role
    CHECK (
        participant_role IN (
            'OWNER',
            'CO_OWNER',
            'COLLABORATOR',
            'FACULTY',
            'DEPARTMENT_HEAD',
            'COLLEGE_HEAD'
        )
    );

CREATE INDEX IF NOT EXISTS idx_initiative_participants_user
    ON public.initiative_participants(user_id);

ALTER TABLE public.initiative_conversion_drafts
    ADD COLUMN IF NOT EXISTS initiative_id BIGINT,
    ADD COLUMN IF NOT EXISTS finalized_at TIMESTAMP;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_conversion_drafts'::regclass
          AND conname = 'fk_initiative_conversion_draft_initiative'
    ) THEN
        ALTER TABLE public.initiative_conversion_drafts
            ADD CONSTRAINT fk_initiative_conversion_draft_initiative
            FOREIGN KEY (initiative_id)
            REFERENCES public.initiatives(initiative_id)
            ON DELETE SET NULL;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_initiative_conversion_drafts_prepared_by
    ON public.initiative_conversion_drafts(prepared_by, updated_at DESC);
