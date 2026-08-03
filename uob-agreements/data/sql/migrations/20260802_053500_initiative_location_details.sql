-- Phase 17G
-- Conditional delivery-location details for Initiative requests.

ALTER TABLE public.initiative_requests
    ADD COLUMN IF NOT EXISTS implementation_country VARCHAR(120),
    ADD COLUMN IF NOT EXISTS online_platform_name VARCHAR(200);

COMMENT ON COLUMN public.initiative_requests.implementation_country IS
    'Country required for OUTSIDE_UOB and HYBRID delivery modes.';

COMMENT ON COLUMN public.initiative_requests.online_platform_name IS
    'Online platform required for VIRTUAL and HYBRID delivery modes.';
