CREATE OR REPLACE VIEW v_current_user_positions AS

SELECT

    u.user_id,

    u.first_name,

    u.last_name,

    p.name AS position,

    ou.name AS organizational_unit


FROM user_positions up


JOIN users u

ON up.user_id = u.user_id


JOIN positions p

ON up.position_id = p.position_id


JOIN organizational_units ou

ON up.unit_id = ou.unit_id


WHERE up.is_active = TRUE;