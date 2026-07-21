# UOB Workspace Functional Redesign

## Purpose

This phase replaces the Agreement-list-first interface with a role-aware
operations workspace. It does not change the approved Agreement workflow or
the Initiative teammate's business logic.

## Role experiences

### Dean and Agreement creator

- Opens on a personal portfolio dashboard instead of the Agreement register.
- Sees owned Agreements, records under review, active Agreements, and overdue
  performance reports.
- Sees the current reviewing office and assigned reviewer for every submitted
  Agreement.
- Can open a performance dashboard scoped only to Agreements they created.
- Retains create, revise, resubmit, signing, lifecycle, and annual-report
  actions.

### Faculty and Initiative creator

- Opens on an Initiative-focused dashboard.
- Can start an initiative request from the Initiative hub.
- Can browse initiatives, active Agreements, and SDG guidance.
- Sees the five-stage Initiative approval path and their organizational
  position.
- Does not receive Agreement creation or approval authority.

The existing `Initiative Creator` role had no `role_permissions` rows. The
idempotent migration grants `CREATE_INITIATIVE`, `EDIT_INITIATIVE`, and
`VIEW_REPORTS`. This repairs the empty Faculty account without implementing or
overwriting the Initiative teammate's workflow.

The "Start an initiative" action uses a two-minute, one-time handoff token to
create the existing Initiative portal session. Faculty therefore moves from the
new workspace into the teammate-owned Initiative form without signing in a
second time. Only the token hash is stored; successful tokens are immediately
marked used and cannot be replayed.

### VP, Legal, Finance, President, and Agreement approvers

- Opens on assigned decisions and institutional delivery risk.
- Sees active review count, submitted performance reports, active Agreements,
  and overdue reporting.
- Retains stage-specific decision pages and institutional performance access.

### All authenticated users

- Receive a role-aware Overview page.
- Receive a real Profile page showing University ID, contact information,
  active positions, roles, permissions, last login, and security controls.
- Use one responsive sidebar for Agreements, Initiatives, performance, account,
  and public-portal navigation.

## Review timeline

`GET /agreements/{id}/workflow-timeline` returns the latest Agreement workflow,
ordered steps, action history, assigned offices, assigned reviewer names, and
completed actor names. It uses the same record-visibility rule as the Agreement
detail endpoint, so a user cannot retrieve a timeline for an Agreement they
cannot open.

The Agreement detail screen renders:

- completed, current, pending, skipped, returned, and rejected stages;
- the office currently reviewing the Agreement;
- the assigned reviewer name or account;
- when the current stage started;
- who completed earlier stages; and
- expandable workflow activity history.

## Performance scope

The same performance screen serves two safe scopes:

- `INSTITUTIONAL`: Agreement approvers and administrators with
  `VIEW_AGREEMENT_DASHBOARD` see all Agreements.
- `OWN_PORTFOLIO`: Agreement creators with `MANAGE_AGREEMENT_REPORTS` see only
  Agreements they created, their reporting deadlines, accepted metrics, and
  executive-program results.

The scope is enforced in repository queries, not only hidden in the interface.

## Files added

- `uob-agreements/workspace/profile.php`
- `uob-agreements/workspace/initiative-hub.php`
- `uob-agreements/workspace/assets/js/dashboard.js`
- `uob-agreements/workspace/assets/js/profile.js`
- `uob-agreements/workspace/assets/js/initiative-hub.js`
- `uob-agreements/data/sql/migrations/20260721_functional_workspace_redesign.sql`
- `uob-agreements/workspace-handoff.php`
- `tests/WorkspaceExperienceSmokeTest.php`

## Validation

Run the static workspace test, the performance regression test, and then the
full Agreement suite. Manually sign in as Dean, Faculty, VP, Legal, Finance,
and President to confirm each account begins with useful work rather than the
same generic register.
