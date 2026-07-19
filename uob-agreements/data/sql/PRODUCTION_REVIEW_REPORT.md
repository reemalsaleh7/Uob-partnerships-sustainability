# University Partnerships & Initiatives Database Review Report

## Executive Summary

Overall score: 58/100

Production readiness: Not production-ready

### Major strengths
- The schema is organized into clear domains: users, organization, agreements, initiatives, workflow, and partner management.
- The deployment flow is reasonably structured and the SQL files are grouped by purpose.
- There is a visible attempt to model workflow templates and approval-related entities.

### Major weaknesses
- The schema is not yet safe to deploy as-is. The agreements table definition contains a syntax error that would break creation.
- The workflow engine is only partially modeled and does not enforce the required business rules.
- Important integrity rules are missing because several constraint files are empty and many tables lack robust checks.
- The permission model is too simplistic for office, college, department, and executive separation.
- The seed data and workflow definitions are incomplete for a full production approval lifecycle.

---

## 1. Project Structure Review

### Assessment
The folder structure is understandable and mostly consistent, but it is not yet a fully hardened deployment package.

### Strengths
- The deployment order is logical in [uob-agreements/data/sql/deploy.sql](uob-agreements/data/sql/deploy.sql).
- Tables, triggers, functions, indexes, and seed data are separated by domain.

### Issues
- The deployment process depends on several files that are effectively empty placeholders, including [uob-agreements/data/sql/constraints/foreign_keys.sql](uob-agreements/data/sql/constraints/foreign_keys.sql) and [uob-agreements/data/sql/constraints/unique_containers.sql](uob-agreements/data/sql/constraints/unique_containers.sql).
- The seed layer is incomplete for organizational units and workflow steps.

### Recommendation
- Keep the current structure, but add a strict migration order and a validation step before deployment.

---

## 2. SQL Style and Conventions Review

### Assessment
The style is readable, but inconsistent in several places.

### Issues
- The project mixes plain TIMESTAMP with a more explicit PostgreSQL style in places, but does not consistently use TIMESTAMPTZ where auditability matters.
- Many files use a verbose multi-line style, but the schema lacks standardized naming for status and action values.
- Several tables use VARCHAR for domain values that should be constrained more tightly.

### Recommendation
- Standardize on TIMESTAMPTZ for audit fields.
- Introduce domain or enum usage for workflow statuses, action types, and agreement/initiative types.

---

## 3. Normalization Review

### Assessment
The schema is generally normalized, but a few areas are too loose and could lead to redundancy and repeated business logic.

### Strengths
- Junction tables such as [uob-agreements/data/sql/tables/role_permissions.sql](uob-agreements/data/sql/tables/role_permissions.sql), [uob-agreements/data/sql/tables/agreement_partners.sql](uob-agreements/data/sql/tables/agreement_partners.sql), and [uob-agreements/data/sql/tables/initiative_agreements.sql](uob-agreements/data/sql/tables/initiative_agreements.sql) are appropriate.

### Issues
- Agreement and initiative types are stored as free-form strings instead of lookup tables.
- Relationship and action types are stored as VARCHAR values and are not constrained by a shared catalog.
- The organization model does not enforce business rules such as one President, one Vice President, one Dean per college, or one Department Head per department.

### Recommendation
- Convert status and type values to proper lookup tables or strongly constrained enums.

---

## 4. Organization Model Review

### Assessment
The base structure is present, but the business rules are not enforced.

### Files reviewed
- [uob-agreements/data/sql/tables/organizational_units.sql](uob-agreements/data/sql/tables/organizational_units.sql)
- [uob-agreements/data/sql/tables/positions.sql](uob-agreements/data/sql/tables/positions.sql)
- [uob-agreements/data/sql/tables/user_positions.sql](uob-agreements/data/sql/tables/user_positions.sql)
- [uob-agreements/data/sql/tables/position_types.sql](uob-agreements/data/sql/tables/position_types.sql)

### Issues
- The model supports hierarchy, but there is no enforcement for the one-President / one-Vice-President / one-Dean / one-Department-Head rules.
- The active-position logic is only partially enforced through a trigger in [uob-agreements/data/sql/triggers/check_positions_trigger.sql](uob-agreements/data/sql/triggers/check_positions_trigger.sql), and it does not cover the broader organizational constraints.
- The design allows multiple active roles in the same unit unless extra logic is applied in the application layer.

### Recommendation
- Add explicit business rules and constraints for organizational leadership roles.

---

## 5. Agreement Module Review

### Assessment
The agreement domain is structurally present but incomplete for production workflows.

### Files reviewed
- [uob-agreements/data/sql/tables/agreements.sql](uob-agreements/data/sql/tables/agreements.sql)
- [uob-agreements/data/sql/tables/agreement_versions.sql](uob-agreements/data/sql/tables/agreement_versions.sql)
- [uob-agreements/data/sql/tables/agreement_relationships.sql](uob-agreements/data/sql/tables/agreement_relationships.sql)
- [uob-agreements/data/sql/tables/agreement_actions.sql](uob-agreements/data/sql/tables/agreement_actions.sql)
- [uob-agreements/data/sql/tables/agreement_partners.sql](uob-agreements/data/sql/tables/agreement_partners.sql)

### Issues
- [uob-agreements/data/sql/tables/agreements.sql](uob-agreements/data/sql/tables/agreements.sql) contains a syntax error: the line with DEFAULT 'DRAFT' is missing a comma before created_by. This would cause the table creation to fail.
- The status model is limited to a general enum and does not capture the workflow transition semantics for draft, approval, rejection, redraft, renewal, amendment, and termination in a structured way.
- The workflow engine is not actually wired to the agreement lifecycle in the SQL layer.

### Recommendation
- Fix the syntax issue immediately.
- Define explicit transitions and state constraints for the full agreement lifecycle.

---

## 6. Initiative Module Review

### Assessment
The initiative module is present but not yet robust enough to enforce the desired lifecycle.

### Files reviewed
- [uob-agreements/data/sql/tables/initiatives.sql](uob-agreements/data/sql/tables/initiatives.sql)
- [uob-agreements/data/sql/tables/initiative_versions.sql](uob-agreements/data/sql/tables/initiative_versions.sql)
- [uob-agreements/data/sql/tables/initiative_agreements.sql](uob-agreements/data/sql/tables/initiative_agreements.sql)

### Issues
- Initiative types are free-form strings rather than a controlled catalog.
- The approval chain is not represented in the SQL schema beyond generic workflow tables.

### Recommendation
- Add a stronger initiative workflow model and tie it to explicit status transitions.

---

## 7. Partner Module Review

### Assessment
The partner model is simple and workable.

### Files reviewed
- [uob-agreements/data/sql/tables/partners.sql](uob-agreements/data/sql/tables/partners.sql)
- [uob-agreements/data/sql/tables/partner_contacts.sql](uob-agreements/data/sql/tables/partner_contacts.sql)

### Issues
- The current model does not support richer categories, address history, or organization status in a structured way.

### Recommendation
- Consider a partner category lookup and a more formal address/contact history model if the domain grows.

---

## 8. Workflow Engine Review

### Assessment
The workflow engine exists structurally, but it is not complete enough for production.

### Files reviewed
- [uob-agreements/data/sql/tables/workflow_templates.sql](uob-agreements/data/sql/tables/workflow_templates.sql)
- [uob-agreements/data/sql/tables/workflow_template_steps.sql](uob-agreements/data/sql/tables/workflow_template_steps.sql)
- [uob-agreements/data/sql/tables/workflow_instances.sql](uob-agreements/data/sql/tables/workflow_instances.sql)
- [uob-agreements/data/sql/tables/workflow_instance_steps.sql](uob-agreements/data/sql/tables/workflow_instance_steps.sql)
- [uob-agreements/data/sql/tables/workflow_history.sql](uob-agreements/data/sql/tables/workflow_history.sql)
- [uob-agreements/data/sql/seed/workflows.sql](uob-agreements/data/sql/seed/workflows.sql)

### Issues
- Only two workflow templates are seeded, and they are not detailed enough to represent the full approval chain requested by the business.
- The helper functions in [uob-agreements/data/sql/functions/create_agreement_workflow.sql](uob-agreements/data/sql/functions/create_agreement_workflow.sql) and [uob-agreements/data/sql/functions/create_initiative_workflow.sql](uob-agreements/data/sql/functions/create_initiative_workflow.sql) are empty.
- There are no step-ordering constraints or transition rules to enforce approval, rejection, redraft, or parallel approval logic.

### Recommendation
- Implement workflow steps as first-class seed data and enforce ordering and state transitions in the database layer or a tightly controlled service layer.

---

## 9. Security Review

### Assessment
The permission model is foundational but too simplistic for the stated requirements.

### Files reviewed
- [uob-agreements/data/sql/tables/permissions.sql](uob-agreements/data/sql/tables/permissions.sql)
- [uob-agreements/data/sql/tables/roles.sql](uob-agreements/data/sql/tables/roles.sql)
- [uob-agreements/data/sql/tables/role_permissions.sql](uob-agreements/data/sql/tables/role_permissions.sql)
- [uob-agreements/data/sql/tables/user_roles.sql](uob-agreements/data/sql/tables/user_roles.sql)
- [uob-agreements/data/sql/seed/permissions.sql](uob-agreements/data/sql/seed/permissions.sql)
- [uob-agreements/data/sql/seed/roles.sql](uob-agreements/data/sql/seed/roles.sql)
- [uob-agreements/data/sql/seed/role_permissions.sql](uob-agreements/data/sql/seed/role_permissions.sql)

### Issues
- Permissions are not separated by office, college, department, or executive scope.
- Roles overlap and there is no role inheritance or escalation model.
- The seed data currently grants only limited permissions and does not cover the full business matrix.

### Recommendation
- Introduce a more explicit authorization model with scoped permissions for offices and organizational units.

---

## 10. Constraints Review

### Assessment
The schema relies too heavily on application logic instead of database constraints.

### Files reviewed
- [uob-agreements/data/sql/constraints/check_containers.sql](uob-agreements/data/sql/constraints/check_containers.sql)
- [uob-agreements/data/sql/constraints/foreign_keys.sql](uob-agreements/data/sql/constraints/foreign_keys.sql)
- [uob-agreements/data/sql/constraints/unique_containers.sql](uob-agreements/data/sql/constraints/unique_containers.sql)

### Issues
- The foreign-key and uniqueness constraint files are empty.
- Several tables do not define useful check constraints for statuses, active date ranges, or approval flow state.

### Recommendation
- Move more business-specific integrity rules into the database layer.

---

## 11. Trigger Review

### Assessment
The triggers are minimal and incomplete.

### Files reviewed
- [uob-agreements/data/sql/functions/update_timestamp.sql](uob-agreements/data/sql/functions/update_timestamp.sql)
- [uob-agreements/data/sql/triggers/update_timestamp_triggers.sql](uob-agreements/data/sql/triggers/update_timestamp_triggers.sql)
- [uob-agreements/data/sql/triggers/check_positions_trigger.sql](uob-agreements/data/sql/triggers/check_positions_trigger.sql)

### Issues
- The existing trigger logic is limited to updated_at maintenance and uniqueness for one position case.
- There are empty trigger and function files for agreement-specific workflow logic.

### Recommendation
- Avoid overusing triggers for business logic; keep them for simple, deterministic data integrity tasks.

---

## 12. Function Review

### Assessment
The custom functions are too sparse to support the required workflow decisions.

### Files reviewed
- [uob-agreements/data/sql/functions/check_unique_positions.sql](uob-agreements/data/sql/functions/check_unique_positions.sql)
- [uob-agreements/data/sql/functions/create_agreement_workflow.sql](uob-agreements/data/sql/functions/create_agreement_workflow.sql)
- [uob-agreements/data/sql/functions/create_initiative_workflow.sql](uob-agreements/data/sql/functions/create_initiative_workflow.sql)

### Issues
- The workflow creation functions are empty placeholders.
- No function currently validates step transitions or approval authority.

### Recommendation
- Implement explicit workflow transition functions before relying on the schema for business enforcement.

---

## 13. Index Review

### Assessment
The index coverage is too thin for a production workflow-heavy system.

### Files reviewed
- [uob-agreements/data/sql/indexes/indexes.sql](uob-agreements/data/sql/indexes/indexes.sql)

### Issues
- Only two indexes are present, both on organizational units.
- There are no indexes for agreement lookup by status, initiative lookup by status, workflow instance lookup by entity, or permission checks by role and unit.

### Recommendation
- Add indexes for workflow, agreement, initiative, and permission queries based on actual access patterns.

---

## 14. Seed Data Review

### Assessment
The seed layer is incomplete.

### Files reviewed
- [uob-agreements/data/sql/seed/permissions.sql](uob-agreements/data/sql/seed/permissions.sql)
- [uob-agreements/data/sql/seed/roles.sql](uob-agreements/data/sql/seed/roles.sql)
- [uob-agreements/data/sql/seed/role_permissions.sql](uob-agreements/data/sql/seed/role_permissions.sql)
- [uob-agreements/data/sql/seed/workflows.sql](uob-agreements/data/sql/seed/workflows.sql)
- [uob-agreements/data/sql/seed/organizational_units.sql](uob-agreements/data/sql/seed/organizational_units.sql)

### Issues
- Organizational units seed data is empty.
- Workflow steps and detailed approval routes are not seeded.

### Recommendation
- Seed at least the baseline organizational hierarchy and full workflow templates and steps for a fresh install.

---

## Critical Issues

1. The table creation script in [uob-agreements/data/sql/tables/agreements.sql](uob-agreements/data/sql/tables/agreements.sql) is syntactically invalid and will fail.
2. The workflow model is not yet capable of enforcing the required approval chain.
3. The database does not enforce organizational leadership constraints.
4. The constraint layer is incomplete.
5. The permission model is too weak for office, executive, college, and department separation.

---

## Recommended Improvements

- Fix the syntax issue in the agreements table.
- Implement workflow steps and transition validation.
- Add stronger check constraints and domain constraints.
- Add indexes for workflow and lookup-heavy queries.
- Expand the permission and organization model to support role scope.

---

## Optional Enhancements

- Switch audit timestamps to TIMESTAMPTZ.
- Replace free-form status/type strings with lookup tables or stricter enums.
- Add integration tests around approval, rejection, redraft, renewal, amendment, and termination.
- Introduce a documented migration strategy for future schema changes.

---

## Final Verdict

Needs Architectural Changes
