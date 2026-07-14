CREATE OR REPLACE VIEW v_organization_structure AS

SELECT

    child.unit_id,

    child.name AS unit_name,

    child.unit_type,

    parent.name AS parent_unit


FROM organizational_units child


LEFT JOIN organizational_units parent

ON child.parent_unit_id = parent.unit_id;