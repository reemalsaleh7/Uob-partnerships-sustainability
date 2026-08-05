-- Initiative request/final Initiative alignment.
-- Adds pre-execution international-participation data while preserving
-- the existing single initiative_type column and historical secondary_types.

ALTER TABLE public.initiative_requests
    ADD COLUMN IF NOT EXISTS international_participation BOOLEAN,
    ADD COLUMN IF NOT EXISTS international_countries JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS international_partner VARCHAR(255);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_international_countries_json'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_international_countries_json
            CHECK (jsonb_typeof(international_countries) = 'array');
    END IF;
END
$$;

UPDATE public.initiative_requests
SET international_countries = '[]'::jsonb
WHERE international_countries IS NULL;
