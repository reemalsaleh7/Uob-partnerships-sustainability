ALTER TABLE agreements
    ADD COLUMN IF NOT EXISTS fixed_term_months INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'agreements'::regclass
          AND conname = 'chk_agreement_fixed_term_months'
    ) THEN
        ALTER TABLE agreements
            ADD CONSTRAINT chk_agreement_fixed_term_months
            CHECK (
                fixed_term_months IS NULL
                OR fixed_term_months > 0
            );
    END IF;
END
$$;
