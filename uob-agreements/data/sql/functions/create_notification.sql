-- ============================================================
-- Notification System Tables
-- For: UOB Partnerships & Initiatives System
-- ============================================================

-- 1. Create notifications table
CREATE TABLE notifications (
    notification_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
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
    priority VARCHAR(20) DEFAULT 'NORMAL',
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

-- 2. Create indexes for performance
CREATE INDEX idx_notif_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_notif_user_created ON notifications(user_id, created_at DESC);
CREATE INDEX idx_notif_workflow ON notifications(workflow_instance_id, workflow_step_id);
CREATE INDEX idx_notif_entity ON notifications(entity_type, entity_id);
CREATE INDEX idx_notif_unread ON notifications(user_id, is_read, priority);
CREATE INDEX idx_notif_created ON notifications(created_at DESC);

-- 3. Create notification preferences table
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

-- 4. Create notification logs table
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

-- 5. Create indexes for logs
CREATE INDEX idx_log_user_channel ON notification_logs(user_id, channel, status);

-- 6. Add update trigger
CREATE TRIGGER trg_notifications_updated_at
BEFORE UPDATE ON notifications
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

