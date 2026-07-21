-- Run with: psql -d "UOB_Partnership_and_Initiative" -f verify_deployment.sql
-- Every `missing` result must be zero before starting the application.

WITH expected(table_name) AS (
    VALUES
        ('users'), ('roles'), ('permissions'), ('role_permissions'), ('user_roles'),
        ('organizational_units'), ('position_types'), ('positions'), ('user_positions'),
        ('partners'), ('agreements'), ('agreement_partners'), ('agreement_versions'),
        ('agreement_documents'), ('audit_logs'),
        ('agreement_performance_reports'),
        ('agreement_performance_metric_results'),
        ('agreement_executive_program_updates'),
        ('agreement_performance_report_events')
)
SELECT table_name, to_regclass('public.' || table_name) IS NULL AS missing
FROM expected
ORDER BY table_name;

SELECT enumlabel AS audit_action
FROM pg_enum
WHERE enumtypid = 'audit_action'::regtype
ORDER BY enumsortorder;

SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'public' AND table_name = 'audit_logs'
ORDER BY ordinal_position;

SELECT conname AS foreign_key, pg_get_constraintdef(oid) AS definition
FROM pg_constraint
WHERE conrelid = 'audit_logs'::regclass AND contype = 'f';

SELECT indexname, indexdef
FROM pg_indexes
WHERE schemaname = 'public' AND tablename = 'audit_logs';

SELECT
    (SELECT COUNT(*) FROM roles) AS roles,
    (SELECT COUNT(*) FROM permissions) AS permissions,
    (SELECT COUNT(*) FROM organizational_units) AS organizational_units,
    (SELECT COUNT(*) FROM users WHERE is_active) AS active_users;
