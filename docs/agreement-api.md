# Agreement API

The Agreement API provides CRUD, versioning, document metadata, approval workflow, change-request, redraft, routing, and rejection operations.

## Base URL and authentication

Local XAMPP base URL:

```text
http://localhost/Uob-partnerships-sustainability/api/index.php
```

All endpoints require a PHP session unless stated otherwise. Traditional API clients may log in through `POST /login`, retain the `PHPSESSID` cookie, and send it with later requests.

The Agreement workspace instead sends a cryptographically random tab identifier in the `X-UOB-Tab-Session` request header. The API returns the active identifier in the response header with the same name. The workspace stores it in browser `sessionStorage`, so separate tabs can sign in as different users and retain their own identities after refresh. A client using this header must replace its stored identifier when login returns a regenerated value.

Successful responses use:

```json
{
  "success": true,
  "data": {}
}
```

Error responses use:

```json
{
  "success": false,
  "error": "Error description"
}
```

Common status codes:

| Status  | Meaning                                                             |
| ------- | ------------------------------------------------------------------- |
| `200` | Request completed successfully.                                     |
| `401` | No authenticated session.                                           |
| `403` | The user lacks the required permission.                             |
| `404` | Resource or route not found.                                        |
| `409` | The requested workflow transition is invalid for the current state. |
| `422` | Request validation failed.                                          |
| `500` | Unexpected server error.                                            |

## Authentication

### Log in

`POST /login`

```json
{
  "email": "dev.vp@uob.test",
  "password": "UobDev2026!"
}
```

The fixture password is development-only and must never be used in production.

### Current session

`GET /me`

Returns the authenticated user's identity, roles, permissions, and active positions.

### Log out

`POST /logout`

## Agreement CRUD and supporting resources

| Method     | Endpoint                                | Permission           | Purpose                                                                |
| ---------- | --------------------------------------- | -------------------- | ---------------------------------------------------------------------- |
| `GET`    | `/agreements`                         | `VIEW_AGREEMENT`   | List Agreements visible to the authenticated user.                     |
| `GET`    | `/agreements/{id}`                    | `VIEW_AGREEMENT`   | Get one Agreement when the authenticated user may view it.             |
| `POST`   | `/agreements`                         | `CREATE_AGREEMENT` | Create a draft Agreement and version 1.                                |
| `PUT`    | `/agreements/{id}`                    | `EDIT_AGREEMENT`   | Update a draft/returned Agreement and create a snapshot version.       |
| `POST`   | `/agreements/{id}/submit`             | `SUBMIT_AGREEMENT` | Start the approval workflow and move the Agreement to`UNDER_REVIEW`. |
| `POST`   | `/agreements/{id}/resubmit`           | `SUBMIT_AGREEMENT` | Resubmit a newly versioned returned Agreement to Initial VP.           |
| `DELETE` | `/agreements/{id}`                    | `DELETE_AGREEMENT` | Delete the Agreement.                                                  |
| `GET`    | `/agreements/{id}/versions`           | `VIEW_AGREEMENT`   | List immutable versions newest first.                                  |
| `GET`    | `/agreements/{id}/versions/{version}` | `VIEW_AGREEMENT`   | Get one immutable Agreement snapshot.                                  |
| `GET`    | `/agreements/{id}/documents`          | `VIEW_AGREEMENT`   | List document metadata.                                                |
| `POST`   | `/agreements/{id}/documents`          | `CREATE_AGREEMENT` | Create document metadata.                                              |
| `DELETE` | `/documents/{id}`                     | `DELETE_AGREEMENT` | Remove document metadata.                                              |
| `GET`    | `/partners`                           | `VIEW_AGREEMENT`   | List active partners for Agreement forms.                              |

### List active partners

`GET /partners`

Returns active partners ordered by organization name. The response contains `partner_id`, `organization_name`, `partner_type`, and `country`; inactive partner records are excluded from new Agreement forms.

### Create Agreement

`POST /agreements`

```json
{
  "title": "Research Collaboration MOU",
  "agreement_type": "MOU",
  "description": "Research collaboration between both organizations",
  "partner_id": 1
}
```

The authenticated user is always used as `created_by` or `updated_by`. Clients cannot supply trusted actor identifiers. Only the original Agreement creator may edit or submit that Agreement. The update controller accepts only Agreement content fields; clients cannot change workflow status through the general update endpoint.

### Agreement visibility

`VIEW_AGREEMENT` grants access to the Agreement workspace, but record visibility is also enforced by the backend:

- A `DRAFT` or `REVISION_REQUIRED` Agreement is visible only to its creator.
- An `UNDER_REVIEW` Agreement is visible to its creator and users with an active assignment on its current workflow step.
- An `APPROVED` or `ACTIVE` Agreement is visible to every user with `VIEW_AGREEMENT`.
- A user with the `System Administrator` role can view every Agreement.

The same record-level check protects Agreement details, version lists, individual version snapshots, and document metadata. An inaccessible Agreement returns `404` so direct URL or ID changes do not disclose the record.

All API responses send `Cache-Control: no-store` and related compatibility headers. The workspace client also uses the Fetch API's `no-store` mode. This prevents authenticated Agreement data loaded for one user from being reused after another user signs in through the same browser.

### Update Agreement

`PUT /agreements/{id}`

```json
{
  "title": "Updated Research Collaboration MOU",
  "description": "Revised scope and implementation obligations",
  "partner_id": 1,
  "change_summary": "Updated after Legal review"
}
```

Each update creates an immutable row in `agreement_versions` containing a JSON snapshot. Updates are accepted only while the Agreement is `DRAFT` or `REVISION_REQUIRED`; the creator cannot edit it while review is active or after a terminal/published decision.

### Submit Agreement

`POST /agreements/{id}/submit`

Only a `DRAFT` Agreement can start a new workflow. Eligible initiators are a Dean, VP Office member, or President Office member.

Example response:

```json
{
  "success": true,
  "data": {
    "success": true,
    "workflow_instance_id": 64,
    "current_step_key": "VP_INITIAL"
  }
}
```

### Resubmit a revised Agreement by Agreement ID

`POST /agreements/{id}/resubmit`

Permission: `SUBMIT_AGREEMENT`.

```json
{
  "comments": "Requested legal clauses were revised"
}
```

This creator-facing endpoint resolves the active workflow internally. The Agreement must be `REVISION_REQUIRED`, belong to the authenticated creator, and contain a version newer than the recorded redraft baseline. Success returns it to `UNDER_REVIEW` at `VP_INITIAL` for a new review cycle.

## Approval workflow

The normal successful workflow is:

```text
CREATOR
  -> VP_INITIAL
  -> LEGAL_REVIEW + optional FINANCE_REVIEW
  -> VP_FINAL
  -> PRESIDENT_APPROVAL
  -> COMPLETED
```

Legal is always required. The Initial VP decides whether Finance is also required. Legal and Finance can operate in parallel, but Final VP activates only after all required specialist reviews finish.

### Workflow inbox

`GET /workflow-inbox`

Permission: `APPROVE_AGREEMENT` or `REJECT_AGREEMENT`.

Returns only active assignments belonging to the authenticated user where both the workflow and step are `IN_PROGRESS`.

Each inbox row also carries the context needed by the assigned review screen:

- `task_mode` is `REVIEW` for an ordinary approval or `VP_MEDIATION` when `VP_FINAL` was reactivated by a change request.
- `change_request_step_key` and `change_request_reason` identify the active Legal, Finance, or President request awaiting VP routing.
- Legal, Finance, Final VP, and President status/comment fields summarize the decisions already recorded in the workflow.

The President review uses `final_vp_review_status` and `final_vp_review_comments` to display the VP recommendation that activated the President task.

This context does not broaden access. It is returned only with an active assignment owned by the authenticated user.

### Approve Initial VP review

`POST /workflow-instances/{instanceId}/initial-vp/approve`

Permission: `APPROVE_AGREEMENT`.

```json
{
  "include_finance": true,
  "comments": "Legal and Finance reviews required"
}
```

Effects:

- Approves `VP_INITIAL`.
- Always activates `LEGAL_REVIEW`.
- Activates `FINANCE_REVIEW` when `include_finance` is `true`; otherwise marks it `SKIPPED`.
- Creates active assignments for eligible office members.

### Approve specialist review

`POST /workflow-instances/{instanceId}/specialist/approve`

Permission: `APPROVE_AGREEMENT`.

Legal example:

```json
{
  "step_key": "LEGAL_REVIEW",
  "comments": "Legal review approved"
}
```

Finance example:

```json
{
  "step_key": "FINANCE_REVIEW",
  "comments": "Finance review approved"
}
```

Final VP activates only after Legal and every required Finance review are approved.

### Approve Final VP review

`POST /workflow-instances/{instanceId}/final-vp/approve`

Permission: `APPROVE_AGREEMENT`.

```json
{
  "comments": "Final VP review approved"
}
```

Approves `VP_FINAL`, activates `PRESIDENT_APPROVAL`, and assigns eligible President Office users.

### Approve as President

`POST /workflow-instances/{instanceId}/president/approve`

Permission: `APPROVE_AGREEMENT`.

```json
{
  "comments": "Agreement approved by President"
}
```

Effects:

- Approves `PRESIDENT_APPROVAL`.
- Completes the workflow and records `completed_at`.
- Changes the Agreement to `APPROVED`.
- Removes the completed assignment from the President inbox.

The President frontend also exposes the existing change-request and rejection endpoints below. A change request is used when correction is possible; rejection is terminal.

## Change requests and VP mediation

A correctable issue is a change request, not a terminal rejection. Legal, Finance, and President change requests return to the VP, who chooses a controlled destination.

### Request changes

`POST /workflow-instances/{instanceId}/changes/request`

Permission: `APPROVE_AGREEMENT`.

```json
{
  "step_key": "LEGAL_REVIEW",
  "reason": "The termination clause requires revision"
}
```

Allowed `step_key` values:

- `LEGAL_REVIEW`
- `FINANCE_REVIEW`
- `PRESIDENT_APPROVAL`

Effects:

- Marks the source step `CHANGES_REQUESTED`.
- Pauses other active assignments.
- Records `CHANGES_REQUESTED` and `ROUTED_TO_VP` in workflow history.
- Reactivates `VP_FINAL` as the VP mediation task.

### VP routing decision

`POST /workflow-instances/{instanceId}/vp/route`

Permission: `APPROVE_AGREEMENT` or `REJECT_AGREEMENT`.

Route to creator:

```json
{
  "destination": "CREATOR",
  "reason": "Creator must revise the requested clauses"
}
```

Route to Legal:

```json
{
  "destination": "LEGAL",
  "reason": "Legal Office must clarify the requested clause"
}
```

Route to Finance:

```json
{
  "destination": "FINANCE",
  "reason": "Finance Office must reassess the revised cost"
}
```

Terminal rejection:

```json
{
  "destination": "REJECT",
  "reason": "The Agreement cannot proceed"
}
```

| Destination | Result                                                                                                                          |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------- |
| `CREATOR` | Agreement becomes`REVISION_REQUIRED`; creator redraft activates; the current version is recorded as `redraft_base_version`. |
| `LEGAL`   | Legal review reactivates and the Agreement remains`UNDER_REVIEW`.                                                             |
| `FINANCE` | Finance becomes required and its review reactivates.                                                                            |
| `REJECT`  | Workflow and Agreement become terminally`REJECTED`.                                                                           |

## Creator redraft and resubmission

The creator updates the Agreement through `PUT /agreements/{id}`. That update must create a newer immutable version before resubmission is permitted.

### Resubmit revised Agreement

`POST /workflow-instances/{instanceId}/redraft/resubmit`

Permission: `SUBMIT_AGREEMENT`.

```json
{
  "comments": "Requested clauses were revised in version 2"
}
```

Validation rules:

- Only the original creator may resubmit.
- Agreement must be `REVISION_REQUIRED`.
- Creator redraft step must be active and assigned to that creator.
- Latest version number must be greater than `redraft_base_version`.

Successful resubmission:

- Increments `review_cycle`.
- Approves the creator redraft step.
- Clears the prior Finance decision and redraft baseline.
- Resets downstream workflow steps to `PENDING`.
- Changes the Agreement to `UNDER_REVIEW`.
- Reactivates Initial VP review so the VP can select which reviews repeat.
- Records `RESUBMITTED` in workflow history.

The workspace calls `POST /agreements/{id}/resubmit` so the creator does not need the internal workflow instance ID. The instance-based route above remains available for workflow clients and applies the same workflow rules.

## Direct VP decisions

Initial VP and Final VP can return an Agreement directly to the creator or reject it without another VP-mediation loop.

`POST /workflow-instances/{instanceId}/vp/decide`

Permission: `APPROVE_AGREEMENT` or `REJECT_AGREEMENT`.

Return to creator:

```json
{
  "step_key": "VP_INITIAL",
  "decision": "RETURN_TO_CREATOR",
  "reason": "Agreement scope must be clarified"
}
```

Terminal rejection:

```json
{
  "step_key": "VP_FINAL",
  "decision": "REJECT",
  "reason": "Final review determined that the Agreement cannot proceed"
}
```

Allowed step keys are `VP_INITIAL` and `VP_FINAL`. Return activates creator redrafting and records a version baseline. Rejection terminates the workflow and Agreement.

## President rejection

`POST /workflow-instances/{instanceId}/president/reject`

Permission: `REJECT_AGREEMENT`.

```json
{
  "reason": "Agreement does not meet final institutional requirements"
}
```

Effects:

- Marks `PRESIDENT_APPROVAL` as `REJECTED`.
- Changes the workflow and Agreement to `REJECTED`.
- Records the completion timestamp and rejection history.
- Deactivates the President inbox assignment.
- Blocks duplicate decisions after termination.

## State and history guarantees

- Workflow-changing operations run inside database transactions.
- A user can act only on an active step assigned to that user.
- Obsolete active assignments are deactivated instead of deleted.
- Earlier review decisions remain in `workflow_history` even when current step state is reset for another cycle.
- Only one active assignment exists for the same step and user.
- Parallel Legal and Finance completion cannot activate Final VP twice.
- Duplicate approvals or rejections on completed workflows are rejected.
- Agreement creation, update, submission, deletion, and document metadata changes write audit records.
- Workflow decisions write ordered records to `workflow_history`.

## Development accounts

Development fixtures use password `UobDev2026!`:

| Actor            | Email                      |
| ---------------- | -------------------------- |
| Dean             | `dev.dean@uob.test`      |
| Vice President   | `dev.vp@uob.test`        |
| Legal reviewer   | `dev.legal@uob.test`     |
| Finance reviewer | `dev.finance@uob.test`   |
| President        | `dev.president@uob.test` |

These credentials are strictly for local developmen

# Agreement API

All endpoints require an authenticated session and the listed permission.

| Method | Endpoint                                | Permission           | Purpose                                            |
| ------ | --------------------------------------- | -------------------- | -------------------------------------------------- |
| GET    | `/agreements`                         | `VIEW_AGREEMENT`   | List agreements.                                   |
| GET    | `/agreements/{id}`                    | `VIEW_AGREEMENT`   | Get an agreement.                                  |
| POST   | `/agreements`                         | `CREATE_AGREEMENT` | Create a draft agreement and version 1.            |
| PUT    | `/agreements/{id}`                    | `EDIT_AGREEMENT`   | Update an agreement and create a snapshot version. |
| POST   | `/agreements/{id}/submit`             | `SUBMIT_AGREEMENT` | Move the agreement to`UNDER_REVIEW`.             |
| DELETE | `/agreements/{id}`                    | `DELETE_AGREEMENT` | Delete the agreement.                              |
| GET    | `/agreements/{id}/versions`           | `VIEW_AGREEMENT`   | List versions newest first.                        |
| GET    | `/agreements/{id}/versions/{version}` | `VIEW_AGREEMENT`   | Get one immutable agreement snapshot.              |
| GET    | `/agreements/{id}/documents`          | `VIEW_AGREEMENT`   | List document metadata.                            |
| POST   | `/agreements/{id}/documents`          | `CREATE_AGREEMENT` | Create document metadata.                          |
| DELETE | `/documents/{id}`                     | `DELETE_AGREEMENT` | Remove document metadata.                          |

## Create request

```json
{
  "title": "Research Collaboration MOU",
  "agreement_type": "MOU",
  "description": "Development test agreement",
  "partner_id": 1
}
```

The authenticated user is always used as `created_by` or `updated_by`; clients cannot supply those fields.

## Lifecycle and guarantees

- New agreements begin as `DRAFT`; submitting transitions them to `UNDER_REVIEW`.
- Every create, update, and submit writes a JSON `agreement_snapshot` to `agreement_versions`.
- Agreement creation, updates, submission, agreement deletion, and document metadata changes are transactional with their audit entries.
- Audit actions use the database enum values `INSERT`, `UPDATE`, and `DELETE`.
