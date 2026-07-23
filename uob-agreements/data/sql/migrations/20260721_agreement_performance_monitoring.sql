BEGIN;

CREATE TABLE IF NOT EXISTS agreement_performance_reports (
    performance_report_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL
        REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    executive_summary TEXT,
    achievements TEXT,
    challenges TEXT,
    corrective_actions TEXT,
    next_period_plan TEXT,
    report_document_id BIGINT
        REFERENCES agreement_documents(document_id) ON DELETE RESTRICT,
    created_by BIGINT NOT NULL REFERENCES users(user_id),
    submitted_by BIGINT REFERENCES users(user_id),
    submitted_at TIMESTAMP,
    reviewed_by BIGINT REFERENCES users(user_id),
    reviewed_at TIMESTAMP,
    reviewer_comments TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT agreement_performance_period_dates_check
        CHECK (period_end >= period_start),
    CONSTRAINT agreement_performance_due_date_check
        CHECK (due_date >= period_end),
    CONSTRAINT agreement_performance_status_check
        CHECK (status IN ('DRAFT', 'SUBMITTED', 'ACCEPTED', 'RETURNED')),
    CONSTRAINT agreement_performance_period_unique
        UNIQUE (agreement_id, period_start, period_end)
);

CREATE INDEX IF NOT EXISTS ix_agreement_performance_reports_queue
    ON agreement_performance_reports (status, due_date, agreement_id);

CREATE INDEX IF NOT EXISTS ix_agreement_performance_reports_creator
    ON agreement_performance_reports (created_by, status, due_date);

CREATE TABLE IF NOT EXISTS agreement_performance_metric_results (
    performance_metric_result_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    performance_report_id BIGINT NOT NULL
        REFERENCES agreement_performance_reports(performance_report_id)
        ON DELETE CASCADE,
    agreement_metric_id BIGINT
        REFERENCES agreement_metrics(agreement_metric_id) ON DELETE SET NULL,
    metric_code VARCHAR(50) NOT NULL,
    metric_label VARCHAR(150) NOT NULL,
    planned_value NUMERIC(14, 2),
    actual_value NUMERIC(14, 2),
    unit VARCHAR(50) NOT NULL DEFAULT 'COUNT',
    notes TEXT,
    display_order INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT agreement_performance_metric_values_check
        CHECK (
            (planned_value IS NULL OR planned_value >= 0)
            AND (actual_value IS NULL OR actual_value >= 0)
        ),
    CONSTRAINT agreement_performance_metric_unique
        UNIQUE (performance_report_id, metric_code)
);

CREATE TABLE IF NOT EXISTS agreement_executive_program_updates (
    program_update_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    performance_report_id BIGINT NOT NULL
        REFERENCES agreement_performance_reports(performance_report_id)
        ON DELETE CASCADE,
    executive_program_id BIGINT
        REFERENCES agreement_executive_programs(executive_program_id)
        ON DELETE SET NULL,
    program_title VARCHAR(255) NOT NULL,
    progress_status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
    completion_percent NUMERIC(5, 2) NOT NULL DEFAULT 0,
    achievements TEXT,
    outputs_delivered TEXT,
    challenges TEXT,
    next_steps TEXT,
    display_order INTEGER NOT NULL DEFAULT 1,
    CONSTRAINT agreement_program_progress_status_check
        CHECK (
            progress_status IN (
                'NOT_STARTED', 'ON_TRACK', 'AT_RISK',
                'DELAYED', 'COMPLETED', 'CANCELLED'
            )
        ),
    CONSTRAINT agreement_program_completion_check
        CHECK (completion_percent BETWEEN 0 AND 100),
    CONSTRAINT agreement_program_update_unique
        UNIQUE (performance_report_id, executive_program_id)
);

CREATE TABLE IF NOT EXISTS agreement_performance_report_events (
    performance_report_event_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    performance_report_id BIGINT NOT NULL
        REFERENCES agreement_performance_reports(performance_report_id)
        ON DELETE CASCADE,
    from_status VARCHAR(20),
    to_status VARCHAR(20) NOT NULL,
    comments TEXT,
    performed_by BIGINT NOT NULL REFERENCES users(user_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_agreement_performance_report_events
    ON agreement_performance_report_events (
        performance_report_id, created_at, performance_report_event_id
    );

INSERT INTO permissions (permission_code, permission_name, description)
VALUES
    (
        'MANAGE_AGREEMENT_REPORTS',
        'Manage Agreement performance reports',
        'Prepare and submit periodic performance reports for owned Agreements'
    ),
    (
        'REVIEW_AGREEMENT_REPORTS',
        'Review Agreement performance reports',
        'Accept or return submitted Agreement performance reports'
    ),
    (
        'VIEW_AGREEMENT_DASHBOARD',
        'View Agreement performance dashboard',
        'View management-level Agreement reporting and performance summaries'
    )
ON CONFLICT (permission_code) DO UPDATE
SET permission_name = EXCLUDED.permission_name,
    description = EXCLUDED.description;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code = 'MANAGE_AGREEMENT_REPORTS'
WHERE r.role_name IN ('Agreement Creator', 'System Administrator')
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
JOIN permissions p
  ON p.permission_code IN (
      'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD'
  )
WHERE r.role_name IN ('Agreement Approver', 'System Administrator')
ON CONFLICT DO NOTHING;

COMMIT;
