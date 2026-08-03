-- Phase 17R
-- Persist the approved Initiative location in structured columns.
-- database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiatives
    ADD COLUMN IF NOT EXISTS proposed_venue TEXT,
    ADD COLUMN IF NOT EXISTS proposed_venue_place_id VARCHAR(255),
    ADD COLUMN IF NOT EXISTS proposed_venue_name VARCHAR(255),
    ADD COLUMN IF NOT EXISTS proposed_venue_latitude NUMERIC(10, 7),
    ADD COLUMN IF NOT EXISTS proposed_venue_longitude NUMERIC(10, 7),
    ADD COLUMN IF NOT EXISTS proposed_venue_country_code CHAR(2);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_venue_latitude'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_venue_latitude
            CHECK (
                proposed_venue_latitude IS NULL
                OR proposed_venue_latitude BETWEEN -90 AND 90
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_venue_longitude'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_venue_longitude
            CHECK (
                proposed_venue_longitude IS NULL
                OR proposed_venue_longitude BETWEEN -180 AND 180
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiatives'::regclass
          AND conname = 'chk_initiatives_venue_country_code'
    ) THEN
        ALTER TABLE public.initiatives
            ADD CONSTRAINT chk_initiatives_venue_country_code
            CHECK (
                proposed_venue_country_code IS NULL
                OR proposed_venue_country_code ~ '^[A-Z]{2}$'
            );
    END IF;
END
$$;

UPDATE public.initiatives initiative
SET
    proposed_venue = COALESCE(
        initiative.proposed_venue,
        request.proposed_venue
    ),
    proposed_venue_place_id = COALESCE(
        initiative.proposed_venue_place_id,
        request.proposed_venue_place_id
    ),
    proposed_venue_name = COALESCE(
        initiative.proposed_venue_name,
        request.proposed_venue_name
    ),
    proposed_venue_latitude = COALESCE(
        initiative.proposed_venue_latitude,
        request.proposed_venue_latitude
    ),
    proposed_venue_longitude = COALESCE(
        initiative.proposed_venue_longitude,
        request.proposed_venue_longitude
    ),
    proposed_venue_country_code = COALESCE(
        initiative.proposed_venue_country_code,
        request.proposed_venue_country_code
    )
FROM public.initiative_requests request
WHERE initiative.source_request_id = request.request_id
  AND (
        initiative.proposed_venue IS NULL
        OR initiative.proposed_venue_place_id IS NULL
        OR initiative.proposed_venue_name IS NULL
        OR initiative.proposed_venue_latitude IS NULL
        OR initiative.proposed_venue_longitude IS NULL
        OR initiative.proposed_venue_country_code IS NULL
  );

CREATE INDEX IF NOT EXISTS idx_initiatives_venue_place_id
ON public.initiatives (proposed_venue_place_id)
WHERE proposed_venue_place_id IS NOT NULL;

COMMENT ON COLUMN public.initiatives.proposed_venue IS
    'Approved Initiative venue address or display text.';

COMMENT ON COLUMN public.initiatives.proposed_venue_place_id IS
    'Google Maps Place ID inherited from the approved Initiative request.';

COMMENT ON COLUMN public.initiatives.proposed_venue_name IS
    'Google Maps place name inherited from the approved Initiative request.';

COMMENT ON COLUMN public.initiatives.proposed_venue_latitude IS
    'Latitude inherited from the approved Initiative request.';

COMMENT ON COLUMN public.initiatives.proposed_venue_longitude IS
    'Longitude inherited from the approved Initiative request.';

COMMENT ON COLUMN public.initiatives.proposed_venue_country_code IS
    'ISO alpha-2 country code inherited from the approved Initiative request.';
