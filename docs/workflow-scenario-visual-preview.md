# Workflow Scenario Visual Preview V1

## Purpose

Adds a visual scenario explorer to the administrator Workflow template editor.

The editor now provides:

- **Current route**: the editable draft route already shown by the page.
- **All scenarios**: every possible route produced by optional stages.
- Parallel stages grouped visually inside the same execution phase.
- Required, optional, included, and skipped stages shown explicitly.
- Progressive loading in batches of 12 so large scenario sets do not freeze the page.
- Live regeneration while the administrator renames, reorders, adds, deletes, or changes a stage between required/optional and sequential/parallel.

## Scenario rules

- Required stages are included in every route.
- Each optional stage creates an include/skip branch.
- A template with `n` optional stages has `2^n` visual scenarios.
- The first scenario is the complete route with every optional stage included.
- A parallel stage stays in the same phase as the stage immediately before it.
- Empty phases are omitted when every stage in that phase is skipped.

## Database impact

None. This feature is a browser-side preview and does not add or change database migrations.

## Files

- `uob-agreements/workspace/admin-workflows.php`
- `uob-agreements/workspace/assets/js/admin-workflows.js`
- `uob-agreements/workspace/assets/js/admin-workflow-scenarios.js`
- `uob-agreements/workspace/assets/css/admin-workflow-scenarios.css`
- `tests/WorkflowScenarioVisualPreviewSourceTest.php`
- `tests/workflow-scenario-preview.test.js`
