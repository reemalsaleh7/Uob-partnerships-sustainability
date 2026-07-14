CREATE TRIGGER trg_users_updated_at

BEFORE UPDATE

ON users

FOR EACH ROW

EXECUTE FUNCTION update_updated_at();



CREATE TRIGGER trg_agreements_updated_at

BEFORE UPDATE

ON agreements

FOR EACH ROW

EXECUTE FUNCTION update_updated_at();



CREATE TRIGGER trg_initiatives_updated_at

BEFORE UPDATE

ON initiatives

FOR EACH ROW

EXECUTE FUNCTION update_updated_at();



CREATE TRIGGER trg_organizational_units_updated_at

BEFORE UPDATE

ON organizational_units

FOR EACH ROW

EXECUTE FUNCTION update_updated_at();