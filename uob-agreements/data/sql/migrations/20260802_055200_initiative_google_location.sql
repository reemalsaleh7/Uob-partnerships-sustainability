-- Phase 17I
-- Persist reusable Google Maps location data selected by the requester.

ALTER TABLE public.initiative_requests
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
        WHERE conname =
            'chk_initiative_requests_venue_latitude'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT
                chk_initiative_requests_venue_latitude
            CHECK (
                proposed_venue_latitude IS NULL
                OR proposed_venue_latitude
                    BETWEEN -90 AND 90
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname =
            'chk_initiative_requests_venue_longitude'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT
                chk_initiative_requests_venue_longitude
            CHECK (
                proposed_venue_longitude IS NULL
                OR proposed_venue_longitude
                    BETWEEN -180 AND 180
            );
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname =
            'chk_initiative_requests_venue_country_code'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT
                chk_initiative_requests_venue_country_code
            CHECK (
                proposed_venue_country_code IS NULL
                OR proposed_venue_country_code
                    ~ '^[A-Z]{2}$'
            );
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS
    idx_initiative_requests_venue_place_id
ON public.initiative_requests (
    proposed_venue_place_id
)
WHERE proposed_venue_place_id IS NOT NULL;

COMMENT ON COLUMN
    public.initiative_requests.proposed_venue_place_id IS
    'Google Maps Place ID selected through Place Autocomplete.';

COMMENT ON COLUMN
    public.initiative_requests.proposed_venue_name IS
    'Google Maps display name captured at selection time.';

COMMENT ON COLUMN
    public.initiative_requests.proposed_venue_latitude IS
    'Latitude captured from the selected Google Maps place.';

COMMENT ON COLUMN
    public.initiative_requests.proposed_venue_longitude IS
    'Longitude captured from the selected Google Maps place.';

COMMENT ON COLUMN
    public.initiative_requests.proposed_venue_country_code IS
    'ISO alpha-2 country code returned by Google Maps.';
