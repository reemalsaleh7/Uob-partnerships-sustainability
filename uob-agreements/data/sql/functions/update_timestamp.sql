-- ============================================================
-- Function:
-- Automatically updates updated_at columns.
-- ============================================================


CREATE OR REPLACE FUNCTION update_updated_at()

RETURNS TRIGGER

LANGUAGE plpgsql

AS $$

BEGIN

    NEW.updated_at = CURRENT_TIMESTAMP;

    RETURN NEW;

END;

$$;