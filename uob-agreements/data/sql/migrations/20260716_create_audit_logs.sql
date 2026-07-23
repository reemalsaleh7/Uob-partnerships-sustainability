-- Repairs development databases created before audit_logs was included in deployment.
-- The users table must already exist.

DO $$
BEGIN
    CREATE TYPE audit_action AS ENUM (
        'INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'APPROVE', 'REJECT'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT NOT NULL,
    action audit_action NOT NULL,
    user_id BIGINT,
    old_data JSONB,
    new_data JSONB,
    reason TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_table_record_created
    ON audit_logs (table_name, record_id, created_at DESC);
