-- LOCAL TEST ONLY
-- Makes the newest active Initiative approval stage due immediately.

WITH latest_stage AS (
    SELECT stage.request_stage_id
    FROM initiative_request_stages stage
    JOIN initiative_requests request
      ON request.request_id = stage.request_id
    WHERE request.status = 'UNDER_REVIEW'
      AND stage.status = 'IN_PROGRESS'
    ORDER BY request.updated_at DESC, stage.request_stage_id DESC
    LIMIT 1
)
UPDATE initiative_request_stages stage
SET due_at = CURRENT_TIMESTAMP - INTERVAL '1 minute',
    last_reminder_at = NULL
FROM latest_stage
WHERE stage.request_stage_id = latest_stage.request_stage_id
RETURNING
    stage.request_stage_id,
    stage.request_id,
    stage.stage_label,
    stage.due_at,
    stage.last_reminder_at;
