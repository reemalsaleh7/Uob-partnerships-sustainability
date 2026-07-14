-- ==========================================================
-- UOB Partnership & Initiative System
-- Deployment Script
-- ==========================================================

\c "UOB_Partnership_and_Initiative"

-- Extensions
\ir extensions.sql

-- Types
\ir types.sql

-- Tables
\ir tables/users.sql
\ir tables/organizational_units.sql
\ir tables/positions_types.sql
\ir tables/positions.sql
\ir tables/permissions.sql
\ir tables/roles.sql
\ir tables/role_permissions.sql
\ir tables/user_roles.sql
\ir tables/user_positions.sql

\ir tables/partners.sql
\ir tables/partner_contacts.sql

\ir tables/agreements.sql
\ir tables/agreement_partners.sql
\ir tables/agreement_versions.sql
\ir tables/agreement_relationships.sql
\ir tables/agreement_actions.sql

\ir tables/initiatives.sql
\ir tables/initiative_agreements.sql
\ir tables/initiatives_versions.sql

\ir tables/workflow_templates.sql
\ir tables/workflow_template_steps.sql
\ir tables/workflow_instances.sql
\ir tables/workflow_instance_steps.sql
\ir tables/workflow_history.sql

-- Functions
\ir functions/update_timestamp.sql
\ir functions/check_unique_positions.sql

-- Triggers
\ir triggers/update_timestamp_triggers.sql
\ir triggers/check_positions_trigger.sql

-- Seed
\ir seed/permissions.sql
\ir seed/roles.sql
\ir seed/role_permissions.sql
\ir seed/workflows.sql

-- Views
\ir views/organization_structure.sql
\ir views/user_positions.sql
\ir views/user_permissions.sql
\ir views/pending_workflows.sql

\echo ''
\echo '===================================='
\echo 'Deployment Complete'
\echo '===================================='