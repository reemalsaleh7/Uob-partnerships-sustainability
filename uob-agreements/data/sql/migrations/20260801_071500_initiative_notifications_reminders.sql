-- Phase 7 - Initiative notifications and one-time stage reminders.
-- database-manager.cmd controls the transaction; do not add BEGIN/COMMIT here.

ALTER TABLE initiative_notifications
    ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(180);

CREATE UNIQUE INDEX IF NOT EXISTS
    ux_initiative_notifications_recipient_dedupe
ON initiative_notifications (
    recipient_user_id,
    dedupe_key
)
WHERE dedupe_key IS NOT NULL;

CREATE INDEX IF NOT EXISTS
    idx_initiative_notifications_recipient_unread
ON initiative_notifications (
    recipient_user_id,
    is_read,
    created_at DESC
);

CREATE INDEX IF NOT EXISTS
    idx_initiative_request_stages_due_reminder
ON initiative_request_stages (
    due_at,
    request_stage_id
)
WHERE status = 'IN_PROGRESS'
  AND last_reminder_at IS NULL;
