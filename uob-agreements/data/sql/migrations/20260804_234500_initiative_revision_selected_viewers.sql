-- Selected viewers for Initiative revision notes.
-- IMPORTANT: No BEGIN/COMMIT here; database-manager.cmd owns the transaction.

ALTER TABLE initiative_revision_comments
    ADD COLUMN IF NOT EXISTS audience_mode VARCHAR(30);

UPDATE initiative_revision_comments
SET audience_mode = CASE
    WHEN UPPER(COALESCE(visibility, '')) = 'PUBLIC'
        THEN 'REQUEST_VIEWERS'
    ELSE 'THREAD_PARTICIPANTS'
END
WHERE audience_mode IS NULL;

ALTER TABLE initiative_revision_comments
    ALTER COLUMN audience_mode SET DEFAULT 'THREAD_PARTICIPANTS';

ALTER TABLE initiative_revision_comments
    ALTER COLUMN audience_mode SET NOT NULL;

ALTER TABLE initiative_revision_comments
    DROP CONSTRAINT IF EXISTS chk_initiative_revision_comment_audience;

ALTER TABLE initiative_revision_comments
    ADD CONSTRAINT chk_initiative_revision_comment_audience
    CHECK (
        audience_mode IN (
            'REQUEST_VIEWERS',
            'THREAD_PARTICIPANTS',
            'SELECTED_USERS'
        )
    );

CREATE TABLE IF NOT EXISTS initiative_revision_comment_viewers (
    revision_comment_id BIGINT NOT NULL
        REFERENCES initiative_revision_comments(revision_comment_id)
        ON DELETE CASCADE,
    viewer_user_id BIGINT NOT NULL
        REFERENCES users(user_id)
        ON DELETE CASCADE,
    added_by_user_id BIGINT NOT NULL
        REFERENCES users(user_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (revision_comment_id, viewer_user_id)
);

CREATE INDEX IF NOT EXISTS idx_initiative_revision_comment_viewers_user
    ON initiative_revision_comment_viewers (
        viewer_user_id,
        revision_comment_id
    );

CREATE INDEX IF NOT EXISTS idx_initiative_revision_comments_audience
    ON initiative_revision_comments (
        revision_thread_id,
        audience_mode,
        created_at
    );

COMMENT ON COLUMN initiative_revision_comments.audience_mode IS
    'REQUEST_VIEWERS, THREAD_PARTICIPANTS, or SELECTED_USERS.';

COMMENT ON TABLE initiative_revision_comment_viewers IS
    'Exact users allowed to read a SELECTED_USERS Initiative revision note.';
