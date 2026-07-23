-- Adds immutable agreement snapshots to existing installations.
-- Existing versions are backfilled from the current agreement state; past state
-- cannot be reconstructed retroactively where it was never stored.

ALTER TABLE agreement_versions
    ADD COLUMN IF NOT EXISTS agreement_snapshot JSONB;

UPDATE agreement_versions av
SET agreement_snapshot = to_jsonb(a) || jsonb_build_object(
    'partner_id', (
        SELECT ap.partner_id
        FROM agreement_partners ap
        WHERE ap.agreement_id = a.agreement_id
        ORDER BY ap.partner_id
        LIMIT 1
    )
)
FROM agreements a
WHERE av.agreement_id = a.agreement_id
  AND av.agreement_snapshot IS NULL;

ALTER TABLE agreement_versions
    ALTER COLUMN agreement_snapshot SET NOT NULL;
