BEGIN;

ALTER TABLE partners
    ADD COLUMN IF NOT EXISTS city VARCHAR(150),
    ADD COLUMN IF NOT EXISTS logo_url TEXT,
    ADD COLUMN IF NOT EXISTS latitude NUMERIC(9, 6),
    ADD COLUMN IF NOT EXISTS longitude NUMERIC(9, 6);

ALTER TABLE agreements
    ADD COLUMN IF NOT EXISTS agreement_code VARCHAR(50),
    ADD COLUMN IF NOT EXISTS title_ar VARCHAR(255),
    ADD COLUMN IF NOT EXISTS geographic_scope VARCHAR(20),
    ADD COLUMN IF NOT EXISTS start_date DATE,
    ADD COLUMN IF NOT EXISTS end_date DATE,
    ADD COLUMN IF NOT EXISTS effective_date DATE,
    ADD COLUMN IF NOT EXISTS signing_date DATE,
    ADD COLUMN IF NOT EXISTS auto_renew BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS renewal_term_months INTEGER,
    ADD COLUMN IF NOT EXISTS non_renewal_notice_months INTEGER,
    ADD COLUMN IF NOT EXISTS termination_notice_months INTEGER NOT NULL DEFAULT 6,
    ADD COLUMN IF NOT EXISTS responsible_unit_id BIGINT REFERENCES organizational_units(unit_id),
    ADD COLUMN IF NOT EXISTS need_justification TEXT,
    ADD COLUMN IF NOT EXISTS expected_value TEXT,
    ADD COLUMN IF NOT EXISTS objectives TEXT,
    ADD COLUMN IF NOT EXISTS focus_areas TEXT,
    ADD COLUMN IF NOT EXISTS collaboration_areas TEXT,
    ADD COLUMN IF NOT EXISTS implementation_methods TEXT,
    ADD COLUMN IF NOT EXISTS financial_commitments BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS financial_amount NUMERIC(14, 2),
    ADD COLUMN IF NOT EXISTS financial_currency CHAR(3) NOT NULL DEFAULT 'BHD',
    ADD COLUMN IF NOT EXISTS financial_description TEXT,
    ADD COLUMN IF NOT EXISTS human_resources_commitments BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS human_resources_description TEXT,
    ADD COLUMN IF NOT EXISTS training_programs BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS training_programs_description TEXT,
    ADD COLUMN IF NOT EXISTS annual_report_required BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS monitoring_plan TEXT,
    ADD COLUMN IF NOT EXISTS confidentiality_terms TEXT,
    ADD COLUMN IF NOT EXISTS intellectual_property_terms TEXT,
    ADD COLUMN IF NOT EXISTS compliance_terms TEXT,
    ADD COLUMN IF NOT EXISTS relationship_disclaimer TEXT,
    ADD COLUMN IF NOT EXISTS amendment_terms TEXT,
    ADD COLUMN IF NOT EXISTS dispute_resolution_terms TEXT,
    ADD COLUMN IF NOT EXISTS other_terms TEXT,
    ADD COLUMN IF NOT EXISTS legal_binding_status VARCHAR(20) NOT NULL DEFAULT 'NON_BINDING',
    ADD COLUMN IF NOT EXISTS signing_link TEXT,
    ADD COLUMN IF NOT EXISTS source_record_id VARCHAR(100);

CREATE UNIQUE INDEX IF NOT EXISTS ux_agreements_agreement_code
    ON agreements (agreement_code)
    WHERE agreement_code IS NOT NULL;

CREATE INDEX IF NOT EXISTS ix_agreements_responsible_unit
    ON agreements (responsible_unit_id);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'agreements_geographic_scope_check'
    ) THEN
        ALTER TABLE agreements ADD CONSTRAINT agreements_geographic_scope_check
            CHECK (geographic_scope IS NULL OR geographic_scope IN ('LOCAL', 'INTERNATIONAL'));
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'agreements_legal_binding_status_check'
    ) THEN
        ALTER TABLE agreements ADD CONSTRAINT agreements_legal_binding_status_check
            CHECK (legal_binding_status IN ('NON_BINDING', 'BINDING', 'MIXED'));
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'agreements_duration_check'
    ) THEN
        ALTER TABLE agreements ADD CONSTRAINT agreements_duration_check
            CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'agreements_financial_amount_check'
    ) THEN
        ALTER TABLE agreements ADD CONSTRAINT agreements_financial_amount_check
            CHECK (financial_amount IS NULL OR financial_amount >= 0);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS agreement_sdgs (
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    sdg_number SMALLINT NOT NULL CHECK (sdg_number BETWEEN 1 AND 17),
    PRIMARY KEY (agreement_id, sdg_number)
);

CREATE TABLE IF NOT EXISTS agreement_rankings (
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    ranking_code VARCHAR(30) NOT NULL CHECK (
        ranking_code IN ('QS_WORLD', 'THE_IMPACT', 'UI_GREENMETRIC')
    ),
    PRIMARY KEY (agreement_id, ranking_code)
);

CREATE TABLE IF NOT EXISTS agreement_contacts (
    agreement_contact_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    party_type VARCHAR(20) NOT NULL CHECK (party_type IN ('UOB', 'PARTNER')),
    contact_role VARCHAR(20) NOT NULL CHECK (contact_role IN ('COORDINATOR', 'SIGNATORY')),
    partner_id BIGINT REFERENCES partners(partner_id),
    full_name VARCHAR(255) NOT NULL,
    job_title VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    display_order INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_agreement_contacts_agreement
    ON agreement_contacts (agreement_id, display_order, agreement_contact_id);

CREATE TABLE IF NOT EXISTS agreement_executive_programs (
    executive_program_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    objectives TEXT,
    expected_outputs TEXT,
    start_date DATE,
    end_date DATE,
    responsible_entity VARCHAR(255),
    applicant_name VARCHAR(255),
    display_order INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)
);

CREATE INDEX IF NOT EXISTS ix_agreement_executive_programs_agreement
    ON agreement_executive_programs (agreement_id, display_order, executive_program_id);

CREATE TABLE IF NOT EXISTS agreement_metrics (
    agreement_metric_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    metric_code VARCHAR(30) NOT NULL CHECK (
        metric_code IN ('STUDENTS_EXCHANGED', 'FACULTY_EXCHANGED', 'JOINT_PROGRAMS')
    ),
    planned_value INTEGER CHECK (planned_value IS NULL OR planned_value >= 0),
    actual_value INTEGER CHECK (actual_value IS NULL OR actual_value >= 0),
    notes TEXT,
    UNIQUE (agreement_id, metric_code)
);

CREATE TABLE IF NOT EXISTS agreement_lifecycle_requests (
    lifecycle_request_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL REFERENCES agreements(agreement_id),
    request_type VARCHAR(20) NOT NULL CHECK (
        request_type IN ('RENEWAL', 'AMENDMENT', 'TERMINATION')
    ),
    justification TEXT,
    activities_summary TEXT,
    achieved_value TEXT,
    proposed_start_date DATE,
    proposed_end_date DATE,
    financial_amount NUMERIC(14, 2),
    financial_currency CHAR(3) NOT NULL DEFAULT 'BHD',
    financial_description TEXT,
    amendment_type VARCHAR(255),
    amendment_reason TEXT,
    terms_to_amend TEXT,
    termination_reason TEXT,
    proposed_termination_date DATE,
    previous_initiatives BOOLEAN,
    requested_by BIGINT NOT NULL REFERENCES users(user_id),
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (proposed_start_date IS NULL OR proposed_end_date IS NULL OR proposed_end_date >= proposed_start_date),
    CHECK (financial_amount IS NULL OR financial_amount >= 0)
);

CREATE INDEX IF NOT EXISTS ix_agreement_lifecycle_requests_agreement
    ON agreement_lifecycle_requests (agreement_id, request_type, created_at DESC);

COMMIT;
