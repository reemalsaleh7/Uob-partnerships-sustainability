<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

final class InitiativeReminderService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Send the one required reminder for every overdue active Initiative stage.
     * The stage row is locked and the notification uses a dedupe key, so
     * running the worker repeatedly or concurrently does not duplicate alerts.
     *
     * @return array{stages_processed:int,notifications_created:int}
     */
    public function run(int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $this->db->beginTransaction();

        try {
            $dueStages = $this->db->query(
                "SELECT
                    stage.request_stage_id,
                    stage.request_id,
                    stage.stage_label,
                    stage.reminder_after_days,
                    request.request_code,
                    request.title
                 FROM initiative_request_stages stage
                 JOIN initiative_requests request
                   ON request.request_id = stage.request_id
                  AND request.status = 'UNDER_REVIEW'
                 WHERE stage.status = 'IN_PROGRESS'
                   AND stage.due_at IS NOT NULL
                   AND stage.due_at <= CURRENT_TIMESTAMP
                   AND stage.last_reminder_at IS NULL
                 ORDER BY stage.due_at, stage.request_stage_id
                 LIMIT {$limit}
                 FOR UPDATE OF stage SKIP LOCKED"
            )->fetchAll();

            $insertNotification = $this->db->prepare(
                "INSERT INTO initiative_notifications (
                    request_id,
                    recipient_user_id,
                    notification_type,
                    title,
                    message,
                    payload,
                    dedupe_key
                 )
                 SELECT DISTINCT
                    stage.request_id,
                    reviewer.user_id,
                    'STAGE_REMINDER',
                    :title,
                    :message,
                    jsonb_build_object(
                        'request_stage_id', stage.request_stage_id,
                        'stage_label', stage.stage_label,
                        'request_url', CONCAT(
                            'initiative-workflow.php?view=detail&id=',
                            stage.request_id
                        )
                    ),
                    CONCAT('STAGE_REMINDER:', stage.request_stage_id)
                 FROM initiative_request_stages stage
                 JOIN LATERAL (
                    SELECT stage.assigned_user_id AS user_id

                    UNION

                    SELECT delegate_position.user_id
                    FROM user_positions delegate_position
                    JOIN users delegate_user
                      ON delegate_user.user_id = delegate_position.user_id
                     AND delegate_user.is_active = TRUE
                    JOIN user_roles delegate_user_role
                      ON delegate_user_role.user_id = delegate_user.user_id
                    JOIN role_permissions delegate_role_permission
                      ON delegate_role_permission.role_id = delegate_user_role.role_id
                    JOIN permissions delegate_permission
                      ON delegate_permission.permission_id =
                         delegate_role_permission.permission_id
                     AND delegate_permission.permission_code =
                         'APPROVE_INITIATIVE'
                    WHERE stage.is_office_delegable = TRUE
                      AND delegate_position.unit_id = stage.responsible_unit_id
                      AND delegate_position.is_active = TRUE
                      AND (
                            delegate_position.end_date IS NULL
                            OR delegate_position.end_date >= CURRENT_DATE
                      )
                 ) reviewer ON reviewer.user_id IS NOT NULL
                 WHERE stage.request_stage_id = :stage_id
                 ON CONFLICT DO NOTHING"
            );

            $markSent = $this->db->prepare(
                "UPDATE initiative_request_stages
                 SET last_reminder_at = CURRENT_TIMESTAMP
                 WHERE request_stage_id = :stage_id
                   AND last_reminder_at IS NULL"
            );

            $recordEvent = $this->db->prepare(
                "INSERT INTO initiative_request_events (
                    request_id,
                    request_stage_id,
                    cycle_number,
                    event_type,
                    actor_user_id,
                    target_user_id,
                    event_note,
                    event_data
                 )
                 SELECT
                    stage.request_id,
                    stage.request_stage_id,
                    stage.cycle_number,
                    'REMINDER_SENT',
                    NULL,
                    stage.assigned_user_id,
                    :event_note,
                    jsonb_build_object(
                        'notification_count', CAST(:notification_count AS INTEGER),
                        'reminder_after_days', stage.reminder_after_days
                    )
                 FROM initiative_request_stages stage
                 WHERE stage.request_stage_id = :stage_id"
            );

            $notificationsCreated = 0;
            $stagesProcessed = 0;

            foreach ($dueStages as $stage) {
                $days = (int) $stage['reminder_after_days'];
                $requestCode = (string) ($stage['request_code'] ?? 'Initiative request');
                $stageLabel = (string) $stage['stage_label'];

                $insertNotification->execute([
                    'title' => 'Initiative approval reminder',
                    'message' => sprintf(
                        '%s has been awaiting %s for %d day%s.',
                        $requestCode,
                        $stageLabel,
                        $days,
                        $days === 1 ? '' : 's'
                    ),
                    'stage_id' => (int) $stage['request_stage_id'],
                ]);

                $createdForStage = $insertNotification->rowCount();
                $notificationsCreated += $createdForStage;

                $markSent->execute([
                    'stage_id' => (int) $stage['request_stage_id'],
                ]);

                $recordEvent->execute([
                    'event_note' => sprintf(
                        'A %d-day approval reminder was sent for %s.',
                        $days,
                        $stageLabel
                    ),
                    'notification_count' => $createdForStage,
                    'stage_id' => (int) $stage['request_stage_id'],
                ]);

                $stagesProcessed++;
            }

            $this->db->commit();

            return [
                'stages_processed' => $stagesProcessed,
                'notifications_created' => $notificationsCreated,
            ];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
