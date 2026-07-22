-- Aligns existing users tables with the authentication service.
-- Safe to run repeatedly because every addition uses IF NOT EXISTS.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_login TIMESTAMP,
    ADD COLUMN IF NOT EXISTS failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP;