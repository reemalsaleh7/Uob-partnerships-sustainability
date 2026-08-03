ALTER TABLE agreement_metrics
    DROP CONSTRAINT IF EXISTS agreement_metrics_metric_code_check;

ALTER TABLE agreement_metrics
    ADD CONSTRAINT agreement_metrics_metric_code_check
    CHECK (
        metric_code IN (
            'STUDENTS_EXCHANGED',
            'TRAINED_STUDENTS',
            'FACULTY_EXCHANGED',
            'JOINT_PROGRAMS'
        )
    );
