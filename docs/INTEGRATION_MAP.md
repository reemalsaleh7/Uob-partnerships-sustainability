# Legacy MariaDB → PostgreSQL integration map

The uploaded `university_partnerships (1).sql` is a MariaDB dump.
The GitHub `Fatema` branch is PostgreSQL and already has a shared
workflow engine. This package therefore integrates business fields;
it does not copy MariaDB approval tables into PostgreSQL.

| Legacy source | PostgreSQL destination |
|---|---|
| initiative_requests.title | initiatives.title |
| initiative_requests.summary | initiatives.description |
| initiative_requests.objectives | initiatives.objectives |
| initiative_requests.initiative_type | initiatives.initiative_type |
| initiative_requests.expected_budget | initiatives.expected_budget |
| initiative_requests.start_date | initiatives.planned_start_date |
| initiative_requests.end_date | initiatives.planned_end_date |
| initiative_requests.revision_count | initiatives.revision_count |
| initiative_requests.submitted_at | initiatives.submitted_at |
| initiative_requests.final_decision_at | initiatives.final_decision_at |
| initiatives.initiative_code | initiatives.initiative_code |
| initiative_request_participants | initiative_participants |
| initiative_request_files | initiative_documents |
| initiative_agreements.relation_notes | initiative_agreements.relation_notes |
| request revisions/events | shared workflow_history + version snapshots |

Not copied:
- approval_steps
- approval_step_assignees
- workflow_templates from MariaDB
- request_revisions and request_revision_actions

Those would duplicate the repository's PostgreSQL tables:
- workflow_templates
- workflow_template_steps
- workflow_instances
- workflow_instance_steps
- workflow_step_assignments
- workflow_history


## Initiative approval additions

The PostgreSQL shared workflow tables now receive an Initiative
template through the migration:

`20260728_add_initiative_approval_workflow.sql`

No duplicate approval tables are introduced. The template is stored in
`workflow_templates` and `workflow_template_steps`; each submitted
Initiative must create runtime rows in `workflow_instances`,
`workflow_instance_steps`, `workflow_step_assignments`, and
`workflow_history`.


## Student Initiative support

| New requirement | PostgreSQL implementation |
|---|---|
| Student can create Initiative | `Student Initiative Creator` role |
| Student permissions | CREATE/EDIT/SUBMIT_INITIATIVE |
| Student specialization | `student_profiles.department_id` |
| Student Department Head | resolved from the specialization Department |
| Student Dean | resolved from the Department parent College |
| Remaining route | VP then President using the shared workflow template |
