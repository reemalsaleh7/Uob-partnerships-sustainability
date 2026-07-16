CREATE OR REPLACE VIEW v_pending_workflows AS


SELECT

    wi.workflow_instance_id,

    wi.entity_type,

    wi.entity_id,

    wis.step_order,

    wis.status,

    ou.name AS waiting_department


FROM workflow_instances wi


JOIN workflow_instance_steps wis

ON wi.workflow_instance_id =
wis.workflow_instance_id


LEFT JOIN organizational_units ou

ON wis.assigned_unit_id = ou.unit_id


WHERE wis.status = 'PENDING';