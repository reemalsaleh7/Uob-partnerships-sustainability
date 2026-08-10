# Initiative Workflow Runtime Unification V1

## Purpose

This update removes three duplicated **physical** Initiative Workflow tables:

- `initiative_request_stages`
- `initiative_request_events`
- `initiative_admin_skips`

Initiative-request and Agreement execution now share:

- `workflow_instances`
- `workflow_instance_steps`
- `workflow_step_assignments`
- `workflow_history`

The Initiative request, revision discussion, notification, conversion, and final-form tables remain separate because they contain Initiative-specific business data rather than duplicated Workflow runtime data.

## Compatibility

The three former table names are recreated as PostgreSQL views with write triggers. Existing pages and older repository queries can continue using the same relation names while their data is stored in the shared Workflow tables.

This compatibility layer is intentional. It reduces the physical table count immediately without requiring a risky, simultaneous rewrite of every Initiative screen.

## Data preservation

Shared Initiative-request instances use `entity_type = INITIATIVE_REQUEST`, keeping them separate from legacy/final Initiative records that may use `INITIATIVE`.

The migration preserves:

- every Initiative request and revision cycle;
- stage ordering, phases, parallel execution, status, assignment, unit, position, comments, and timing;
- event history and administrative skip reasons;
- revision-thread stage references;
- reminder payload stage identifiers and deduplication keys;
- current template/version snapshots.

Former Initiative stage identifiers are mapped to negative shared Workflow step identifiers. Identity-generated Agreement and future Initiative steps remain positive, preventing collisions while keeping a deterministic migration mapping.

## Runtime behavior

`ConfigurableInitiativeWorkflowService` and `InitiativeReminderService` now read and write the shared Workflow tables directly.

Existing general Initiative pages may still read the compatibility views. New runtime writes from the configurable service do not depend on those views.

## Database migrations

The database manager applies two ordered migrations:

1. `20260805_111900_expand_workflow_step_status.sql` adds the Initiative-specific states to the existing shared `workflow_step_status` enum.
2. `20260805_112000_unify_initiative_workflow_runtime.sql` migrates the data, removes the three physical tables, and creates the compatibility views.

Keeping the enum expansion in its own migration preserves the existing typed Agreement Workflow schema and avoids PostgreSQL's restriction on using a newly added enum value before its transaction commits.

## Safety

- The database manager owns each migration transaction.
- The migration performs final row-count and foreign-key checks.
- Any mismatch raises an exception and rolls back the complete migration.
- Existing SQL migration files must remain in source control.
- Do not manually drop the compatibility views after installation.
