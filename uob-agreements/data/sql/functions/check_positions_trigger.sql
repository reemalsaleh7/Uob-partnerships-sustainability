CREATE TRIGGER trg_unique_positions

BEFORE INSERT

ON user_positions

FOR EACH ROW

EXECUTE FUNCTION check_unique_position();