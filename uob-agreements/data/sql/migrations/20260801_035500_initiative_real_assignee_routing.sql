

-- Backfill the exact approval holder for Initiative stages that were created
-- before automatic hierarchy assignment was added.
DO $$
DECLARE
    stage_record RECORD;
    target_unit_id BIGINT;
    target_user_id BIGINT;
BEGIN
    FOR stage_record IN
        SELECT
            stage.request_stage_id,
            stage.stage_key,
            request.requester_unit_id
        FROM initiative_request_stages stage
        JOIN initiative_requests request
          ON request.request_id = stage.request_id
        WHERE stage.assigned_user_id IS NULL
           OR stage.responsible_unit_id IS NULL
        ORDER BY stage.request_stage_id
    LOOP
        target_unit_id := NULL;
        target_user_id := NULL;

        IF stage_record.stage_key = 'DEPARTMENT_HEAD' THEN
            WITH RECURSIVE unit_chain AS (
                SELECT
                    unit_id,
                    parent_unit_id,
                    unit_type,
                    0 AS depth
                FROM organizational_units
                WHERE unit_id = stage_record.requester_unit_id
                  AND is_active = TRUE

                UNION ALL

                SELECT
                    parent.unit_id,
                    parent.parent_unit_id,
                    parent.unit_type,
                    chain.depth + 1
                FROM organizational_units parent
                JOIN unit_chain chain
                  ON parent.unit_id = chain.parent_unit_id
                WHERE parent.is_active = TRUE
            )
            SELECT unit_id
            INTO target_unit_id
            FROM unit_chain
            WHERE UPPER(unit_type::TEXT) = 'DEPARTMENT'
            ORDER BY depth
            LIMIT 1;

            SELECT user_position.user_id
            INTO target_user_id
            FROM user_positions user_position
            JOIN users user_account
              ON user_account.user_id = user_position.user_id
             AND user_account.is_active = TRUE
            JOIN positions position
              ON position.position_id = user_position.position_id
            WHERE user_position.unit_id = target_unit_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
              AND LOWER(position.name) IN (
                    'department head',
                    'head of department'
              )
            ORDER BY
                user_position.start_date DESC,
                user_position.user_position_id DESC
            LIMIT 1;

        ELSIF stage_record.stage_key = 'DEAN' THEN
            WITH RECURSIVE unit_chain AS (
                SELECT
                    unit_id,
                    parent_unit_id,
                    unit_type,
                    0 AS depth
                FROM organizational_units
                WHERE unit_id = stage_record.requester_unit_id
                  AND is_active = TRUE

                UNION ALL

                SELECT
                    parent.unit_id,
                    parent.parent_unit_id,
                    parent.unit_type,
                    chain.depth + 1
                FROM organizational_units parent
                JOIN unit_chain chain
                  ON parent.unit_id = chain.parent_unit_id
                WHERE parent.is_active = TRUE
            )
            SELECT unit_id
            INTO target_unit_id
            FROM unit_chain
            WHERE UPPER(unit_type::TEXT) = 'COLLEGE'
            ORDER BY depth
            LIMIT 1;

            SELECT user_position.user_id
            INTO target_user_id
            FROM user_positions user_position
            JOIN users user_account
              ON user_account.user_id = user_position.user_id
             AND user_account.is_active = TRUE
            JOIN positions position
              ON position.position_id = user_position.position_id
            WHERE user_position.unit_id = target_unit_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
              AND LOWER(position.name) = 'dean'
            ORDER BY
                user_position.start_date DESC,
                user_position.user_position_id DESC
            LIMIT 1;

        ELSIF stage_record.stage_key = 'VICE_PRESIDENT' THEN
            SELECT unit_id
            INTO target_unit_id
            FROM organizational_units
            WHERE UPPER(code) = 'VP'
              AND is_active = TRUE
            LIMIT 1;

            SELECT user_position.user_id
            INTO target_user_id
            FROM user_positions user_position
            JOIN users user_account
              ON user_account.user_id = user_position.user_id
             AND user_account.is_active = TRUE
            JOIN positions position
              ON position.position_id = user_position.position_id
            WHERE user_position.unit_id = target_unit_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
              AND LOWER(position.name) = 'vice president'
            ORDER BY
                user_position.start_date DESC,
                user_position.user_position_id DESC
            LIMIT 1;

        ELSIF stage_record.stage_key = 'PRESIDENT' THEN
            SELECT unit_id
            INTO target_unit_id
            FROM organizational_units
            WHERE UPPER(code) = 'PRES'
              AND is_active = TRUE
            LIMIT 1;

            SELECT user_position.user_id
            INTO target_user_id
            FROM user_positions user_position
            JOIN users user_account
              ON user_account.user_id = user_position.user_id
             AND user_account.is_active = TRUE
            JOIN positions position
              ON position.position_id = user_position.position_id
            WHERE user_position.unit_id = target_unit_id
              AND user_position.is_active = TRUE
              AND (
                    user_position.end_date IS NULL
                    OR user_position.end_date >= CURRENT_DATE
              )
              AND LOWER(position.name) = 'president'
            ORDER BY
                user_position.start_date DESC,
                user_position.user_position_id DESC
            LIMIT 1;
        END IF;

        IF target_unit_id IS NOT NULL AND target_user_id IS NOT NULL THEN
            UPDATE initiative_request_stages
            SET
                responsible_unit_id = target_unit_id,
                assigned_user_id = target_user_id
            WHERE request_stage_id = stage_record.request_stage_id;
        END IF;
    END LOOP;
END
$$;

-- Make every open request visible to the exact actor responsible for its
-- current stage.
UPDATE initiative_requests request
SET
    current_assignee_id = stage.assigned_user_id,
    current_assignee_unit_id = stage.responsible_unit_id
FROM initiative_request_stages stage
WHERE stage.request_id = request.request_id
  AND stage.cycle_number = request.revision_cycle
  AND stage.stage_order = request.current_stage_order
  AND request.status IN (
      'SUBMITTED',
      'UNDER_REVIEW',
      'REVISION_DISCUSSION',
      'REVISION_REQUIRED',
      'RESUBMITTED'
  )
  AND stage.assigned_user_id IS NOT NULL;


