-- Field-anchored review comments, private notes, and per-user version views.

\encoding UTF8

BEGIN;

CREATE UNIQUE INDEX IF NOT EXISTS ux_agreement_versions_agreement_version_id
    ON agreement_versions(agreement_id, version_id);

CREATE TABLE IF NOT EXISTS agreement_annotations (
    annotation_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    agreement_version_id BIGINT NOT NULL,
    author_user_id BIGINT NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'SHARED',
    anchor_type VARCHAR(20) NOT NULL DEFAULT 'FIELD',
    field_key VARCHAR(100) NOT NULL,
    selected_text TEXT,
    selection_start INTEGER,
    selection_end INTEGER,
    comment_text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    resolved_by BIGINT,
    resolved_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agreement_annotation_agreement
        FOREIGN KEY (agreement_id)
        REFERENCES agreements(agreement_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agreement_annotation_version
        FOREIGN KEY (agreement_id, agreement_version_id)
        REFERENCES agreement_versions(agreement_id, version_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agreement_annotation_author
        FOREIGN KEY (author_user_id)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_agreement_annotation_resolver
        FOREIGN KEY (resolved_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_agreement_annotation_visibility
        CHECK (visibility IN ('SHARED', 'PRIVATE')),
    CONSTRAINT chk_agreement_annotation_anchor
        CHECK (anchor_type IN ('FIELD', 'TEXT')),
    CONSTRAINT chk_agreement_annotation_status
        CHECK (status IN ('OPEN', 'RESOLVED')),
    CONSTRAINT chk_agreement_annotation_comment
        CHECK (char_length(btrim(comment_text)) BETWEEN 1 AND 4000),
    CONSTRAINT chk_agreement_annotation_selection
        CHECK (
            (anchor_type = 'FIELD'
                AND selection_start IS NULL
                AND selection_end IS NULL)
            OR
            (anchor_type = 'TEXT'
                AND selected_text IS NOT NULL
                AND char_length(selected_text) > 0
                AND selection_start IS NOT NULL
                AND selection_end IS NOT NULL
                AND selection_start >= 0
                AND selection_end > selection_start)
        ),
    CONSTRAINT chk_agreement_annotation_resolution
        CHECK (
            (status = 'OPEN' AND resolved_by IS NULL AND resolved_at IS NULL)
            OR
            (status = 'RESOLVED' AND resolved_by IS NOT NULL AND resolved_at IS NOT NULL)
        )
);

CREATE TABLE IF NOT EXISTS agreement_user_views (
    agreement_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    last_viewed_version_id BIGINT NOT NULL,
    last_viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (agreement_id, user_id),
    CONSTRAINT fk_agreement_user_view_agreement
        FOREIGN KEY (agreement_id)
        REFERENCES agreements(agreement_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agreement_user_view_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_agreement_user_view_version
        FOREIGN KEY (agreement_id, last_viewed_version_id)
        REFERENCES agreement_versions(agreement_id, version_id)
        ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_agreement_annotations_visible
    ON agreement_annotations(agreement_id, visibility, status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_agreement_annotations_author_private
    ON agreement_annotations(author_user_id, agreement_id, created_at DESC)
    WHERE visibility = 'PRIVATE';

CREATE INDEX IF NOT EXISTS idx_agreement_annotations_version
    ON agreement_annotations(agreement_version_id, field_key);

COMMIT;
