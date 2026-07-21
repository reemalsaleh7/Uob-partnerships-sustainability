-- Consolidated SQL for University Partnerships & Initiatives
-- Generated from the project SQL files in data/sql
-- This file is for review and deployment convenience only.

-- ==========================================================
-- Extensions
-- ==========================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ==========================================================
-- Types
-- ==========================================================

CREATE TYPE organizational_unit_type AS ENUM (
    'UNIVERSITY',
    'OFFICE',
    'COLLEGE',
    'DEPARTMENT'
);

CREATE TYPE agreement_status AS ENUM (
    'DRAFT',
    'UNDER_REVIEW',
    'REVISION_REQUIRED',
    'APPROVED',
    'ACTIVE',
    'REJECTED',
    'EXPIRED',
    'TERMINATED'
);

CREATE TYPE agreement_relationship_type AS ENUM (
    'RENEWAL',
    'AMENDMENT',
    'TERMINATION'
);

CREATE TYPE initiative_status AS ENUM (
    'DRAFT',
    'UNDER_REVIEW',
    'APPROVED',
    'ACTIVE',
    'REJECTED',
    'COMPLETED',
    'CANCELLED'
);

CREATE TYPE workflow_status AS ENUM (
    'IN_PROGRESS',
    'COMPLETED',
    'REJECTED',
    'CANCELLED'
);

CREATE TYPE workflow_step_status AS ENUM (
    'PENDING',
    'IN_PROGRESS',
    'APPROVED',
    'CHANGES_REQUESTED',
    'REJECTED',
    'SKIPPED'
);

CREATE TYPE workflow_approval_type AS ENUM (
    'CREATOR',
    'APPROVAL',
    'REJECTION'
);

CREATE TYPE workflow_action_type AS ENUM (
    'SUBMITTED',
    'APPROVED',
    'CHANGES_REQUESTED',
    'ROUTED_TO_VP',
    'ROUTED_TO_CREATOR',
    'ROUTED_TO_LEGAL',
    'ROUTED_TO_FINANCE',
    'RESUBMITTED',
    'REJECTED',
    'REDRAFTED',
    'COMPLETED'
);

CREATE TYPE audit_action AS ENUM (
    'INSERT',
    'UPDATE',
    'DELETE',
    'LOGIN',
    'LOGOUT',
    'APPROVE',
    'REJECT'
);

-- ==========================================================
-- Tables
-- ==========================================================

CREATE TABLE users (
    user_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    university_id VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(30),
    password_hash TEXT NOT NULL,
    last_login TIMESTAMP,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    password_changed_at TIMESTAMP,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE organizational_units (
    unit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    display_order INTEGER NOT NULL DEFAULT 0,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20) UNIQUE,
    unit_type organizational_unit_type NOT NULL,
    parent_unit_id BIGINT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_parent_unit FOREIGN KEY (parent_unit_id) REFERENCES organizational_units(unit_id),
    CONSTRAINT uq_parent_name UNIQUE (parent_unit_id, name)
);

CREATE TABLE position_types (
    position_type_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE positions (
    position_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    position_type_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_unique BOOLEAN NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_position_type FOREIGN KEY(position_type_id) REFERENCES position_types(position_type_id)
);

CREATE TABLE permissions (
    permission_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    permission_code VARCHAR(100) NOT NULL UNIQUE,
    permission_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    role_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL,
    permission_id BIGINT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id)
);

CREATE TABLE user_roles (
    user_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY(role_id) REFERENCES roles(role_id) ON DELETE CASCADE
);

CREATE TABLE user_positions (
    user_position_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id BIGINT NOT NULL,
    position_id BIGINT NOT NULL,
    unit_id BIGINT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_positions_user FOREIGN KEY(user_id) REFERENCES users(user_id),
    CONSTRAINT fk_user_positions_position FOREIGN KEY(position_id) REFERENCES positions(position_id),
    CONSTRAINT fk_user_positions_unit FOREIGN KEY(unit_id) REFERENCES organizational_units(unit_id)
);

CREATE TABLE partners (
    partner_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    organization_name VARCHAR(255) NOT NULL,
    partner_type VARCHAR(100) NOT NULL,
    country VARCHAR(100),
    address TEXT,
    website VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE partner_contacts (
    contact_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    partner_id BIGINT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    job_title VARCHAR(150),
    email VARCHAR(255),
    phone VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_partner_contacts_partner FOREIGN KEY(partner_id) REFERENCES partners(partner_id) ON DELETE CASCADE
);

CREATE TABLE agreements (
    agreement_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    agreement_type VARCHAR(100) NOT NULL,
    status agreement_status NOT NULL DEFAULT 'DRAFT',
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agreements_creator FOREIGN KEY(created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT chk_agreement_title_not_empty CHECK (length(trim(title)) > 0),
    CONSTRAINT chk_agreement_type_not_empty CHECK (length(trim(agreement_type)) > 0)
);

CREATE TABLE agreement_partners (
    agreement_id BIGINT NOT NULL,
    partner_id BIGINT NOT NULL,
    PRIMARY KEY(agreement_id, partner_id),
    CONSTRAINT fk_agreement_partner_agreement FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    CONSTRAINT fk_agreement_partner_partner FOREIGN KEY(partner_id) REFERENCES partners(partner_id) ON DELETE CASCADE
);

CREATE TABLE agreement_versions (
    version_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    version_number INTEGER NOT NULL,
    document_path TEXT,
    change_summary TEXT,


    agreement_snapshot JSONB
        NOT NULL,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_versions_agreement FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    CONSTRAINT fk_versions_creator FOREIGN KEY(created_by) REFERENCES users(user_id),
    UNIQUE(agreement_id, version_number)
);

CREATE TABLE agreement_relationships (
    relationship_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    parent_agreement_id BIGINT NOT NULL,
    related_agreement_id BIGINT NOT NULL,
    relationship_type agreement_relationship_type NOT NULL,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_parent_agreement FOREIGN KEY(parent_agreement_id) REFERENCES agreements(agreement_id),
    CONSTRAINT fk_related_agreement FOREIGN KEY(related_agreement_id) REFERENCES agreements(agreement_id),
    CONSTRAINT fk_relationship_creator FOREIGN KEY(created_by) REFERENCES users(user_id)
);

CREATE TABLE agreement_actions (
    action_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    reason TEXT,
    performed_by BIGINT NOT NULL,
    action_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_action_agreement FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    CONSTRAINT fk_action_user FOREIGN KEY(performed_by) REFERENCES users(user_id)
);

CREATE TABLE initiatives (
    initiative_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    initiative_type VARCHAR(100) NOT NULL,
    status initiative_status NOT NULL DEFAULT 'DRAFT',
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_initiative_creator FOREIGN KEY(created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT chk_initiative_title_not_empty CHECK (length(trim(title)) > 0),
    CONSTRAINT chk_initiative_type_not_empty CHECK (length(trim(initiative_type)) > 0)
);

CREATE TABLE initiative_agreements (
    initiative_id BIGINT NOT NULL,
    agreement_id BIGINT NOT NULL,
    PRIMARY KEY(initiative_id, agreement_id),
    CONSTRAINT fk_initiative_agreement_initiative FOREIGN KEY(initiative_id) REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    CONSTRAINT fk_initiative_agreement_agreement FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE
);

CREATE TABLE initiative_versions (
    version_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    initiative_id BIGINT NOT NULL,
    version_number INTEGER NOT NULL,
    document_path TEXT,
    change_summary TEXT,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_initiative_versions_initiative FOREIGN KEY(initiative_id) REFERENCES initiatives(initiative_id) ON DELETE CASCADE,
    CONSTRAINT fk_initiative_versions_creator FOREIGN KEY(created_by) REFERENCES users(user_id),
    UNIQUE(initiative_id, version_number)
);

CREATE TABLE workflow_templates (
    workflow_template_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    process_type VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE workflow_template_steps (
    template_step_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workflow_template_id BIGINT NOT NULL,
    step_order INTEGER NOT NULL,
    approval_type workflow_approval_type NOT NULL,
    required_unit_id BIGINT,
    required_position_id BIGINT,
    is_optional BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_template_steps_template FOREIGN KEY(workflow_template_id) REFERENCES workflow_templates(workflow_template_id),
    CONSTRAINT fk_template_steps_unit FOREIGN KEY(required_unit_id) REFERENCES organizational_units(unit_id),
    CONSTRAINT fk_template_steps_position FOREIGN KEY(required_position_id) REFERENCES positions(position_id),
    CONSTRAINT uq_template_step_order UNIQUE (workflow_template_id, step_order),
    CONSTRAINT chk_step_order_positive CHECK (step_order > 0)
);

CREATE TABLE workflow_instances (
    workflow_instance_id BIGINT
        GENERATED ALWAYS AS IDENTITY
        PRIMARY KEY,

    workflow_template_id BIGINT
        NOT NULL,

    entity_type VARCHAR(50)
        NOT NULL,

    entity_id BIGINT
        NOT NULL,

    current_step INTEGER
        NOT NULL
        DEFAULT 1,

    -- NULL: Initial VP has not decided.
    -- FALSE: Legal review only.
    -- TRUE: Legal and Finance reviews.
    finance_review_required BOOLEAN,

    -- Starts at 1 and increases after each redraft
    -- is resubmitted for another review.
    review_cycle INTEGER
        NOT NULL
        DEFAULT 1,

    -- Latest Agreement version at the time it was returned
    -- to the creator. Resubmission requires a newer version.
    redraft_base_version INTEGER,

    status workflow_status
        NOT NULL
        DEFAULT 'IN_PROGRESS',

    started_by BIGINT
        NOT NULL,

    started_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    completed_at TIMESTAMP,

    CONSTRAINT fk_workflow_instance_template
        FOREIGN KEY (workflow_template_id)
        REFERENCES workflow_templates(
            workflow_template_id
        ),

    CONSTRAINT fk_workflow_instance_starter
        FOREIGN KEY (started_by)
        REFERENCES users(user_id),

    CONSTRAINT chk_workflow_review_cycle_positive
        CHECK (review_cycle > 0),

    CONSTRAINT chk_redraft_base_version_nonnegative
    CHECK (
        redraft_base_version IS NULL
        OR redraft_base_version >= 0
    )
);

CREATE TABLE workflow_instance_steps (
    instance_step_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workflow_instance_id BIGINT NOT NULL,
    step_order INTEGER NOT NULL,
    assigned_unit_id BIGINT,
    assigned_position_id BIGINT,
    status workflow_step_status NOT NULL DEFAULT 'PENDING',
    approved_by BIGINT,
    approved_at TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    comments TEXT,
    FOREIGN KEY(workflow_instance_id) REFERENCES workflow_instances(workflow_instance_id),
    FOREIGN KEY(assigned_unit_id) REFERENCES organizational_units(unit_id),
    FOREIGN KEY(assigned_position_id) REFERENCES positions(position_id),
    FOREIGN KEY(approved_by) REFERENCES users(user_id)
);

CREATE TABLE workflow_step_assignments (
    assignment_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workflow_instance_step_id BIGINT NOT NULL UNIQUE,
    user_id BIGINT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_step_assignment_step FOREIGN KEY(workflow_instance_step_id) REFERENCES workflow_instance_steps(instance_step_id),
    CONSTRAINT fk_step_assignment_user FOREIGN KEY(user_id) REFERENCES users(user_id)
);

CREATE TABLE audit_logs (
    audit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT NOT NULL,
    action audit_action NOT NULL,
    user_id BIGINT,
    old_data JSONB,
    new_data JSONB,
    reason TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(user_id)
);

CREATE TABLE agreement_documents (
    document_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    agreement_version_id BIGINT,
    file_name VARCHAR(255) NOT NULL,
    file_path TEXT,
    storage_key TEXT,
    mime_type VARCHAR(150),
    file_size_bytes BIGINT,
    sha256_checksum CHAR(64),
    document_type VARCHAR(100) NOT NULL DEFAULT 'OTHER',
    uploaded_by BIGINT NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    FOREIGN KEY(agreement_version_id) REFERENCES agreement_versions(version_id) ON DELETE RESTRICT,
    FOREIGN KEY(uploaded_by) REFERENCES users(user_id),
    CONSTRAINT chk_agreement_document_size
        CHECK (file_size_bytes IS NULL OR file_size_bytes > 0),
    CONSTRAINT chk_agreement_document_checksum
        CHECK (
            sha256_checksum IS NULL
            OR sha256_checksum ~ '^[0-9a-f]{64}$'
        )
);

CREATE TABLE workflow_history (
    history_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workflow_instance_id BIGINT NOT NULL,
    workflow_step_id BIGINT NOT NULL,
    action workflow_action_type NOT NULL,
    performed_by BIGINT NOT NULL,
    comments TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(workflow_instance_id) REFERENCES workflow_instances(workflow_instance_id),
    FOREIGN KEY(workflow_step_id) REFERENCES workflow_instance_steps(instance_step_id),
    FOREIGN KEY(performed_by) REFERENCES users(user_id)
);

-- ==========================================================
-- Functions
-- ==========================================================

CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION check_unique_position()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    unique_position BOOLEAN;
BEGIN
    SELECT is_unique
    INTO unique_position
    FROM positions
    WHERE position_id = NEW.position_id;

    IF unique_position = TRUE THEN
        IF EXISTS (
            SELECT 1
            FROM user_positions
            WHERE position_id = NEW.position_id
              AND unit_id = NEW.unit_id
              AND is_active = TRUE
              AND (
                  TG_OP = 'INSERT'
                  OR user_position_id <> NEW.user_position_id
              )
        ) THEN
            RAISE EXCEPTION 'This position already exists in this organizational unit';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

-- ==========================================================
-- Triggers
-- ==========================================================

CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_agreements_updated_at
BEFORE UPDATE ON agreements
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_initiatives_updated_at
BEFORE UPDATE ON initiatives
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_organizational_units_updated_at
BEFORE UPDATE ON organizational_units
FOR EACH ROW EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_unique_positions
BEFORE INSERT OR UPDATE ON user_positions
FOR EACH ROW EXECUTE FUNCTION check_unique_position();

-- ==========================================================
-- Seed Data
-- ==========================================================

INSERT INTO permissions (permission_code, permission_name) VALUES
('CREATE_AGREEMENT', 'Create Agreement'),
('EDIT_AGREEMENT', 'Edit Agreement'),
('SUBMIT_AGREEMENT', 'Submit Agreement'),
('APPROVE_AGREEMENT', 'Approve Agreement'),
('REJECT_AGREEMENT', 'Reject Agreement'),
('CREATE_INITIATIVE', 'Create Initiative'),
('EDIT_INITIATIVE', 'Edit Initiative'),
('APPROVE_INITIATIVE', 'Approve Initiative'),
('REJECT_INITIATIVE', 'Reject Initiative'),
('VIEW_REPORTS', 'View Reports'),
('MANAGE_USERS', 'Manage Users');

INSERT INTO roles (role_name, description) VALUES
('Agreement Creator', 'Can create and submit agreements'),
('Agreement Approver', 'Can approve or reject agreements'),
('Initiative Creator', 'Can create initiatives'),
('Initiative Approver', 'Can approve or reject initiatives'),
('System Administrator', 'Full system access');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name='Agreement Approver'
AND p.permission_code IN ('APPROVE_AGREEMENT', 'REJECT_AGREEMENT');

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
VALUES ('University', 'UOB', 'UNIVERSITY', NULL, 1);

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'President Office', 'PRES', 'OFFICE', unit_id, 2
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Vice President Office', 'VP', 'OFFICE', unit_id, 3
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Legal Office', 'LEGAL', 'OFFICE', unit_id, 4
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Financial Office', 'FIN', 'OFFICE', unit_id, 5
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'College of Information Technology', 'CIT', 'COLLEGE', unit_id, 6
FROM organizational_units
WHERE code = 'UOB';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Department of Computer Science', 'CS', 'DEPARTMENT', unit_id, 7
FROM organizational_units
WHERE code = 'CIT';

INSERT INTO organizational_units (name, code, unit_type, parent_unit_id, display_order)
SELECT 'Department of Information Systems', 'IS', 'DEPARTMENT', unit_id, 8
FROM organizational_units
WHERE code = 'CIT';

INSERT INTO workflow_templates (name, process_type) VALUES
('Agreement Approval', 'AGREEMENT'),
('Initiative Approval', 'INITIATIVE');

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 1, 'CREATOR', NULL, NULL, FALSE
FROM workflow_templates WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 2, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'VP'), NULL, FALSE
FROM workflow_templates WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 3, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'LEGAL'), NULL, FALSE
FROM workflow_templates WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 4, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'FIN'), NULL, TRUE
FROM workflow_templates WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 5, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'PRES'), NULL, FALSE
FROM workflow_templates WHERE name = 'Agreement Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 1, 'CREATOR', NULL, NULL, FALSE
FROM workflow_templates WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 2, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'CS'), NULL, FALSE
FROM workflow_templates WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 3, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'CIT'), NULL, FALSE
FROM workflow_templates WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 4, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'VP'), NULL, FALSE
FROM workflow_templates WHERE name = 'Initiative Approval';

INSERT INTO workflow_template_steps (workflow_template_id, step_order, approval_type, required_unit_id, required_position_id, is_optional)
SELECT workflow_template_id, 5, 'APPROVAL', (SELECT unit_id FROM organizational_units WHERE code = 'PRES'), NULL, FALSE
FROM workflow_templates WHERE name = 'Initiative Approval';

-- ==========================================================
-- Views
-- ==========================================================

CREATE OR REPLACE VIEW organization_structure AS
SELECT u.unit_id, u.name, u.code, u.unit_type, u.parent_unit_id, u.is_active
FROM organizational_units u;

CREATE OR REPLACE VIEW v_current_user_positions AS
SELECT up.user_position_id, up.user_id, up.position_id, up.unit_id, up.start_date, up.end_date, up.is_active
FROM user_positions up;

CREATE OR REPLACE VIEW user_permissions AS
SELECT ur.user_id, p.permission_code
FROM user_roles ur
JOIN role_permissions rp ON rp.role_id = ur.role_id
JOIN permissions p ON p.permission_id = rp.permission_id;

CREATE OR REPLACE VIEW pending_workflows AS
SELECT wi.workflow_instance_id, wi.entity_type, wi.entity_id, wi.current_step, wi.status
FROM workflow_instances wi
WHERE wi.status = 'IN_PROGRESS';

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_agreements_status ON agreements(status);
CREATE INDEX idx_workflow_instances_status ON workflow_instances(status);
CREATE INDEX idx_workflow_instances_entity ON workflow_instances(entity_type, entity_id);
CREATE INDEX idx_workflow_step_assignments_user ON workflow_step_assignments(user_id);
CREATE INDEX idx_workflow_instance_steps_status ON workflow_instance_steps(status);

CREATE UNIQUE INDEX uq_active_position_assignment
ON user_positions(position_id, unit_id)
WHERE is_active = TRUE;

-- Apply the backward-compatible comprehensive Agreement extension.
-- Kept as an include so this consolidated deployment entry point and the
-- modular deployment script execute the same idempotent migration.
\ir migrations/20260721_comprehensive_agreement_fields.sql

-- Preserve provenance and idempotency for controlled historical imports.
\ir migrations/20260721_legacy_agreement_import_tracking.sql
