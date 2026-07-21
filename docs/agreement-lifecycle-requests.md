# Agreement lifecycle requests

## Purpose

Renewal, amendment, and termination are governed requests linked to an already
`APPROVED` or `ACTIVE` Agreement. They never turn the approved Agreement back
into an editable draft and never overwrite its immutable versions.

## Request fields

All request types store a justification and optional financial implications.

- Renewal: activities completed, value achieved, proposed start/end dates,
  amount, currency, and financial explanation.
- Amendment: amendment category, reason, and exact clauses or terms affected.
- Termination: reason, proposed effective date, and whether initiatives were
  previously implemented under the Agreement.

Applicant identity, timestamps, status, workflow decisions, and final outcome
are system-derived.

## State model

`DRAFT` → `UNDER_REVIEW` → `APPROVED` or `REJECTED`.

A reasoned change request places the record in `REVISION_REQUIRED`. Only its
original requester may edit it. Every save creates an immutable request version,
and resubmission requires a version newer than the return baseline.

## Approval route

1. Initial VP chooses Legal only or parallel Legal and Finance review.
2. Legal reviews every request; Finance reviews only when selected.
3. Specialist or President change requests go to VP mediation.
4. Final VP recommends the request to the President.
5. President approval completes the lifecycle workflow.

An approved termination changes the source Agreement to `TERMINATED` and adds
an `agreement_actions` record. Approved renewal and amendment requests remain
authoritative approvals linked to the original Agreement; the source Agreement
is not changed. A later signed successor Agreement can be linked through
`agreement_relationships` without rewriting history.

## Authorization and visibility

- Draft and revision-required requests: requester only.
- Under review: requester and users holding the active assignment.
- Final approved/rejected requests: visible to users with Agreement viewing
  access for institutional traceability.
- Decisions require both Agreement approval permission and the exact active
  workflow assignment.
- Direct URL changes cannot expose or decide another user's private request.

## API

- `GET /agreement-lifecycle-requests`
- `POST /agreements/{agreementId}/lifecycle-requests`
- `GET /agreement-lifecycle-requests/{requestId}`
- `PUT /agreement-lifecycle-requests/{requestId}`
- `POST /agreement-lifecycle-requests/{requestId}/submit`
- `GET /agreement-lifecycle-requests/{requestId}/versions`
- `POST /lifecycle-workflow-instances/{instanceId}/decide`

Decision payload:

```json
{
  "action": "APPROVE",
  "comments": "Reviewed and recommended",
  "include_finance": true
}
```

`include_finance` is considered only at `VP_INITIAL`. `RETURN` and `REJECT`
require a non-empty reason.
