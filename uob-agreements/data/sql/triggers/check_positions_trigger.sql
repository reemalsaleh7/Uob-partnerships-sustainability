CREATE TRIGGER trg_unique_positions
BEFORE INSERT OR UPDATE
ON user_positions
FOR EACH ROW
EXECUTE FUNCTION check_unique_position();