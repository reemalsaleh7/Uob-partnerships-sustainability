-- Consolidate Initiative request relationship fields and support multiple Agreements.
-- database-manager.cmd controls the transaction.
-- Do not add BEGIN or COMMIT statements.

ALTER TABLE public.initiative_requests
    ADD COLUMN IF NOT EXISTS relationship_type VARCHAR(40),
    ADD COLUMN IF NOT EXISTS external_partner_country VARCHAR(120);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.initiative_requests'::regclass
          AND conname = 'chk_initiative_request_relationship_type'
    ) THEN
        ALTER TABLE public.initiative_requests
            ADD CONSTRAINT chk_initiative_request_relationship_type
            CHECK (
                relationship_type IS NULL
                OR relationship_type IN (
                    'NO_EXTERNAL_PARTY',
                    'EXTERNAL_WITHOUT_AGREEMENT',
                    'LINKED_AGREEMENTS',
                    'UNSURE'
                )
            );
    END IF;
END
$$;

CREATE TABLE IF NOT EXISTS public.initiative_request_agreements (
    request_id BIGINT NOT NULL
        REFERENCES public.initiative_requests(request_id)
        ON DELETE CASCADE,
    agreement_id BIGINT NOT NULL
        REFERENCES public.agreements(agreement_id)
        ON DELETE RESTRICT,
    linked_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id, agreement_id)
);

CREATE INDEX IF NOT EXISTS idx_initiative_request_agreements_agreement
ON public.initiative_request_agreements (agreement_id, request_id);

INSERT INTO public.initiative_request_agreements (
    request_id,
    agreement_id
)
SELECT
    request_id,
    related_agreement_id
FROM public.initiative_requests
WHERE related_agreement_id IS NOT NULL
ON CONFLICT DO NOTHING;

UPDATE public.initiative_requests
SET relationship_type = CASE
    WHEN has_related_agreement IS TRUE
      OR related_agreement_id IS NOT NULL
        THEN 'LINKED_AGREEMENTS'
    WHEN has_external_partner IS TRUE
        THEN 'EXTERNAL_WITHOUT_AGREEMENT'
    WHEN has_related_agreement IS FALSE
      AND has_external_partner IS FALSE
        THEN 'NO_EXTERNAL_PARTY'
    ELSE 'UNSURE'
END
WHERE relationship_type IS NULL;

COMMENT ON COLUMN public.initiative_requests.relationship_type IS
    'Consolidated external-entity and Agreement relationship choice.';

COMMENT ON COLUMN public.initiative_requests.external_partner_country IS
    'Country of an external entity collaborating without an Agreement.';

COMMENT ON TABLE public.initiative_request_agreements IS
    'Many-to-many links between Initiative requests and Agreements.';
