CREATE OR REPLACE VIEW v_user_permissions AS


SELECT

    u.user_id,

    u.email,

    r.role_name,

    p.permission_code,

    p.permission_name


FROM users u


JOIN user_roles ur

ON u.user_id = ur.user_id


JOIN roles r

ON ur.role_id = r.role_id


JOIN role_permissions rp

ON r.role_id = rp.role_id


JOIN permissions p

ON rp.permission_id = p.permission_id;