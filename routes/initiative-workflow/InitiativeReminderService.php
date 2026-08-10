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
     * Send the one required reminder for every overdue active Initiative step.
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
                    instance.workflow_instance_id,
                    step.instance_step_id AS request_stage_id,
                    instance.entity_id AS request_id,
                    step.step_label AS stage_label,
                    step.reminder_after_days,
                    request.request_code,
                    request.title
                 FROM workflow_instance_steps step
                 JOIN workflow_instances instance
                   ON instance.workflow_instance_id =
                      step.workflow_instance_id
                  AND instance.entity_type = 'INITIATIVE_REQUEST'
                 JOIN initiative_requests request
                   ON request.request_id = instance.entity_id
                  AND request.status = 'UNDER_REVIEW'
                  AND request.revision_cycle =
                      instance.cycle_number
                 WHERE step.status = 'IN_PROGRESS'
                   AND step.due_at IS NOT NULL
                   AND step.due_at <= CURRENT_TIMESTAMP
                   AND step.last_reminder_at IS NULL
                 ORDER BY
                    step.due_at,
                    step.instance_step_id
                 LIMIT {$limit}
                 FOR UPDATE OF step SKIP LOCKED"
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
                    instance.entity_id,
                    reviewer.user_id,
                    'STAGE_REMINDER',
                    :title,
                    :message,
                    jsonb_build_object(
                        'request_stage_id',
                        step.instance_step_id,
                        'stage_label',
                        step.step_label,
                        'request_url',
                        CONCAT(
                            'initiative-workflow.php?view=detail&id=',
                            instance.entity_id
                        )
                    ),
                    CONCAT(
                        'STAGE_REMINDER:',
                        step.instance_step_id
                    )
                 FROM workflow_instance_steps step
                 JOIN workflow_instances instance
                   ON instance.workflow_instance_id =
                      step.workflow_instance_id
                  AND instance.entity_type = 'INITIATIVE_REQUEST'
                 JOIN LATERAL (
                    SELECT assignment.user_id
                    FROM workflow_step_assignments assignment
                    WHERE assignment.workflow_instance_step_id =
                          step.instance_step_id
                      AND assignment.is_active = TRUE

                    UNION

                    SELECT delegate_position.user_id
                    FROM user_positions delegate_position
                    JOIN users delegate_user
                      ON delegate_user.user_id =
                         delegate_position.user_id
                     AND delegate_user.is_active = TRUE
                    JOIN user_roles delegate_user_role
                      ON delegate_user_role.user_id =
                         delegate_user.user_id
                    JOIN role_permissions delegate_role_permission
                      ON delegate_role_permission.role_id =
                         delegate_user_role.role_id
                    JOIN permissions delegate_permission
                      ON delegate_permission.permission_id =
                         delegate_role_permission.permission_id
                     AND delegate_permission.permission_code =
                         step.required_permission_code
                    WHERE step.is_office_delegable = TRUE
                      AND delegate_position.unit_id =
                          step.assigned_unit_id
                      AND delegate_position.is_active = TRUE
                      AND (
                            delegate_position.end_date IS NULL
                            OR delegate_position.end_date >=
                               CURRENT_DATE
                      )
                 ) reviewer
                   ON reviewer.user_id IS NOT NULL
                 WHERE step.instance_step_id = :stage_id
                 ON CONFLICT DO NOTHING"
            );

            $markSent = $this->db->prepare(
                "UPDATE workflow_instance_steps
                 SET last_reminder_at = CURRENT_TIMESTAMP
                 WHERE instance_step_id = :stage_id
                   AND last_reminder_at IS NULL"
            );

            $recordEvent = $this->db->prepare(
                "INSERT INTO workflow_history (
                    workflow_instance_id,
                    workflow_step_id,
                    action,
                    performed_by,
                    comments,
                    event_type,
                    target_user_id,
                    event_data
                 )
                 SELECT
                    instance.workflow_instance_id,
                    step.instance_step_id,
                    NULL,
                    NULL,
                    :event_note,
                    'REMINDER_SENT',
                    assignment.user_id,
                    jsonb_build_object(
                        'notification_count',
                        CAST(:notification_count AS INTEGER),
                        'reminder_after_days',
                        step.reminder_after_days
                    )
                 FROM workflow_instance_steps step
                 JOIN workflow_instances instance
                   ON instance.workflow_instance_id =
                      step.workflow_instance_id
                  AND instance.entity_type = 'INITIATIVE_REQUEST'
                 LEFT JOIN LATERAL (
                    SELECT step_assignment.user_id
                    FROM workflow_step_assignments step_assignment
                    WHERE step_assignment.workflow_instance_step_id =
                          step.instance_step_id
                      AND step_assignment.is_active = TRUE
                    ORDER BY
                        step_assignment.assigned_at DESC,
                        step_assignment.assignment_id DESC
                    LIMIT 1
                 ) assignment
                   ON TRUE
                 WHERE step.instance_step_id = :stage_id"
            );

            $notificationsCreated = 0;
            $stagesProcessed = 0;

            foreach ($dueStages as $stage) {
                $days = (int) $stage['reminder_after_days'];
                $requestCode = (string) (
                    $stage['request_code']
                    ?? 'Initiative request'
                );
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
                    'stage_id' =>
                        (int) $stage['request_stage_id'],
                ]);

                $createdForStage =
                    $insertNotification->rowCount();
                $notificationsCreated += $createdForStage;

                $markSent->execute([
                    'stage_id' =>
                        (int) $stage['request_stage_id'],
                ]);

                $recordEvent->execute([
                    'event_note' => sprintf(
                        'A %d-day approval reminder was sent for %s.',
                        $days,
                        $stageLabel
                    ),
                    'notification_count' => $createdForStage,
                    'stage_id' =>
                        (int) $stage['request_stage_id'],
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
