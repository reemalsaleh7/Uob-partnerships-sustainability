-- Phase 9B
-- Repair a Phase 9 installation where the application expects
-- initiative_revision_threads.last_comment_at but the column is absent.
--
-- database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiative_revision_threads
    ADD COLUMN IF NOT EXISTS last_comment_at
    TIMESTAMP WITHOUT TIME ZONE;

-- Keep the Phase 9 visibility values consistent.
UPDATE public.initiative_revision_comments
SET visibility = 'PUBLIC'
WHERE visibility IS NULL
   OR visibility NOT IN ('PUBLIC', 'PRIVATE');

ALTER TABLE public.initiative_revision_comments
    ALTER COLUMN visibility SET DEFAULT 'PUBLIC';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid =
              'public.initiative_revision_comments'::regclass
          AND conname =
              'initiative_revision_comments_visibility_check'
    ) THEN
        ALTER TABLE public.initiative_revision_comments
            ADD CONSTRAINT
            initiative_revision_comments_visibility_check
            CHECK (
                visibility IN ('PUBLIC', 'PRIVATE')
            );
    END IF;
END
$$;

-- Calculate the most recent discussion activity for existing threads.
UPDATE public.initiative_revision_threads revision_thread
SET last_comment_at = COALESCE(
        (
            SELECT MAX(revision_comment.created_at)
            FROM public.initiative_revision_comments
                 revision_comment
            WHERE revision_comment.revision_thread_id =
                  revision_thread.revision_thread_id
        ),
        revision_thread.created_at
    )
WHERE revision_thread.last_comment_at IS NULL;

CREATE INDEX IF NOT EXISTS
    idx_initiative_revision_threads_request_cycle
ON public.initiative_revision_threads (
    request_id,
    cycle_number DESC,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_revision_comments_thread_created
ON public.initiative_revision_comments (
    revision_thread_id,
    created_at,
    revision_comment_id
);

-- Verification shown by psql/database manager logs.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name =
              'initiative_revision_threads'
          AND column_name = 'last_comment_at'
    ) THEN
        RAISE EXCEPTION
            'Phase 9B failed: last_comment_at is still missing.';
    END IF;
END
$$;
