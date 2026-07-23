-- Migration: add_notification
-- Created: 2026-07-23 13:56:58 +03:00
-- Forward-only: never edit this file after another database has applied it.
-- Transaction: do not add BEGIN, COMMIT, or ROLLBACK; the manager supplies it.

-- ============================================================
-- MIGRATION: Notification System
-- Date: 2026-07-23
-- Description: Adds notification tables and related indexes
-- ============================================================

-- ============================================================
-- 1. Create notifications table
-- ============================================================

DROP TABLE IF EXISTS notifications CASCADE;

CREATE TABLE notifications (
    notification_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    title_ar VARCHAR(200) NOT NULL,
    title_en VARCHAR(200) NOT NULL,
    message_ar TEXT,
    message_en TEXT,
    workflow_instance_id BIGINT,
    workflow_step_id BIGINT,
    workflow_history_id BIGINT,
    entity_type VARCHAR(50),
    entity_id BIGINT,
    entity_code VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    action_required BOOLEAN DEFAULT TRUE,
    action_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP,
    delivered_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_notif_workflow_instance FOREIGN KEY (workflow_instance_id) REFERENCES workflow_instances(workflow_instance_id),
    CONSTRAINT fk_notif_workflow_step FOREIGN KEY (workflow_step_id) REFERENCES workflow_instance_steps(instance_step_id),
    CONSTRAINT fk_notif_workflow_history FOREIGN KEY (workflow_history_id) REFERENCES workflow_history(history_id)
);

COMMENT ON TABLE notifications IS 'Stores all system notifications for users';

-- ============================================================
-- 2. Create indexes for notifications
-- ============================================================

CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_notif_user_created ON notifications(user_id, created_at DESC);
CREATE INDEX idx_notif_workflow ON notifications(workflow_instance_id, workflow_step_id);
CREATE INDEX idx_notif_entity ON notifications(entity_type, entity_id);
CREATE INDEX idx_notif_unread ON notifications(user_id, is_read);

COMMENT ON INDEX idx_notif_user_read IS 'For quickly finding unread notifications by user';
COMMENT ON INDEX idx_notif_user_created IS 'For sorting notifications by date for a user';
COMMENT ON INDEX idx_notif_workflow IS 'For finding notifications related to a workflow';

-- ============================================================
-- 3. Create notification preferences table
-- ============================================================

CREATE TABLE notification_preferences (
    preference_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE,
    email_enabled BOOLEAN DEFAULT TRUE,
    browser_enabled BOOLEAN DEFAULT TRUE,
    digest_enabled BOOLEAN DEFAULT FALSE,
    digest_type VARCHAR(20) DEFAULT 'DAILY',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

COMMENT ON TABLE notification_preferences IS 'User preferences for notification delivery (email, browser, digest)';

-- ============================================================
-- 4. Create notification logs table
-- ============================================================

CREATE TABLE notification_logs (
    log_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    notification_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'PENDING',
    error_message TEXT,
    sent_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_notif FOREIGN KEY (notification_id) REFERENCES notifications(notification_id),
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

COMMENT ON TABLE notification_logs IS 'Tracks delivery status of notifications across channels';

CREATE INDEX idx_log_user_channel ON notification_logs(user_id, channel, status);

-- ============================================================
-- 5. Add update trigger for notifications
-- ============================================================

CREATE TRIGGER trg_notifications_updated_at
BEFORE UPDATE ON notifications
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- 6. Verification queries
-- ============================================================

-- Check if all tables exist
SELECT table_name 
FROM information_schema.tables 
WHERE table_name IN ('notifications', 'notification_preferences', 'notification_logs')
ORDER BY table_name;

-- Check notifications table structure
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'notifications' 
ORDER BY ordinal_position;

-- ============================================================
-- 7. Test data (optional - comment out if not needed)
-- ============================================================

-- Insert test notification for admin
INSERT INTO notifications (
    user_id,
    title_ar,
    title_en,
    message_ar,
    message_en,
    action_required,
    is_read,
    is_archived,
    is_deleted
) VALUES (
    (SELECT user_id FROM users WHERE email = 'admin@uob.edu.bh' LIMIT 1),
    '📌 نظام الإشعارات جاهز',
    '📌 Notification System Ready',
    'تم تثبيت نظام الإشعارات بنجاح! 🎉',
    'Notification system installed successfully! 🎉',
    FALSE,
    FALSE,
    FALSE,
    FALSE
) ON CONFLICT DO NOTHING;

-- ============================================================
-- MIGRATION COMPLETE
-- ============================================================

SELECT '✅ Notification system migration completed successfully!' as status;
