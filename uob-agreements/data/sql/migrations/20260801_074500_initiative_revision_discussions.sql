-- Phase 9 - Initiative revision discussions, public/private notes, and history.
-- database-manager.cmd controls the transaction; do not add BEGIN/COMMIT here.

ALTER TABLE initiative_revision_threads
    ADD COLUMN IF NOT EXISTS last_comment_at TIMESTAMP;

UPDATE initiative_revision_comments
SET visibility = 'PUBLIC'
WHERE visibility IS NULL
   OR visibility NOT IN ('PUBLIC', 'PRIVATE');

ALTER TABLE initiative_revision_comments
    ALTER COLUMN visibility SET DEFAULT 'PUBLIC';

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'initiative_revision_comments'::regclass
          AND conname = 'initiative_revision_comments_visibility_check'
    ) THEN
        ALTER TABLE initiative_revision_comments
            ADD CONSTRAINT initiative_revision_comments_visibility_check
            CHECK (visibility IN ('PUBLIC', 'PRIVATE'));
    END IF;
END
$$;

-- For a revision requested after the first approval stage, connect the
-- discussion to the immediately previous stage in the same approval cycle.
UPDATE initiative_revision_threads revision_thread
SET counterpart_stage_id = previous_stage.request_stage_id
FROM initiative_request_stages requested_stage
JOIN initiative_request_stages previous_stage
  ON previous_stage.request_id = requested_stage.request_id
 AND previous_stage.cycle_number = requested_stage.cycle_number
 AND previous_stage.stage_order = requested_stage.stage_order - 1
WHERE revision_thread.requested_by_stage_id = requested_stage.request_stage_id
  AND revision_thread.counterpart_stage_id IS NULL;

-- Preserve the original revision reason as the first public discussion note.
INSERT INTO initiative_revision_comments (
    revision_thread_id,
    author_user_id,
    visibility,
    comment_text,
    created_at
)
SELECT
    revision_thread.revision_thread_id,
    revision_thread.requested_by_user_id,
    'PUBLIC',
    revision_thread.consolidated_note,
    revision_thread.created_at
FROM initiative_revision_threads revision_thread
WHERE NOT EXISTS (
    SELECT 1
    FROM initiative_revision_comments existing_comment
    WHERE existing_comment.revision_thread_id =
          revision_thread.revision_thread_id
);

UPDATE initiative_revision_threads revision_thread
SET last_comment_at = COALESCE(
        (
            SELECT MAX(revision_comment.created_at)
            FROM initiative_revision_comments revision_comment
            WHERE revision_comment.revision_thread_id =
                  revision_thread.revision_thread_id
        ),
        revision_thread.created_at
    ),
    updated_at = GREATEST(
        revision_thread.updated_at,
        COALESCE(
            (
                SELECT MAX(revision_comment.created_at)
                FROM initiative_revision_comments revision_comment
                WHERE revision_comment.revision_thread_id =
                      revision_thread.revision_thread_id
            ),
            revision_thread.created_at
        )
    );

CREATE INDEX IF NOT EXISTS idx_initiative_revision_threads_request_cycle
ON initiative_revision_threads (
    request_id,
    cycle_number DESC,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS idx_initiative_revision_comments_thread_created
ON initiative_revision_comments (
    revision_thread_id,
    created_at,
    revision_comment_id
);

CREATE INDEX IF NOT EXISTS idx_initiative_revision_comments_public
ON initiative_revision_comments (
    revision_thread_id,
    created_at
)
WHERE visibility = 'PUBLIC';
