-- Records that a change request was routed to the VP
-- for mediation and destination selection.

ALTER TYPE workflow_action_type
    ADD VALUE IF NOT EXISTS 'ROUTED_TO_VP';