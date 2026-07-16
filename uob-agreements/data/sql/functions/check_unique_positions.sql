CREATE OR REPLACE FUNCTION check_unique_position()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    unique_position BOOLEAN;
BEGIN
    SELECT is_unique
    INTO unique_position
    FROM positions
    WHERE position_id = NEW.position_id;

    IF unique_position = TRUE THEN
        IF EXISTS (
            SELECT 1
            FROM user_positions
            WHERE position_id = NEW.position_id
              AND unit_id = NEW.unit_id
              AND is_active = TRUE
              AND (
                  TG_OP = 'INSERT'
                  OR user_position_id <> COALESCE(OLD.user_position_id, -1)
              )
        ) THEN
            RAISE EXCEPTION 'This position already exists in this organizational unit';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;