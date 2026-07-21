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
an `agreement_actions` record. President approval of a renewal or amendment
creates an `APPROVED` successor Agreement in the same transaction. The source
Agreement and its versions are not changed.

The successor clones the complete structured Agreement record and all normalized
partners, SDGs, rankings, contacts, executive programs, and outcome metrics.
A renewal applies its approved proposed start/end dates and uses the new start
date as its effective date. An amendment keeps the source's structured values;
its exact approved free-text clauses and private evidence remain authoritative
in the lifecycle request instead of being guessed into unrelated columns.

The lifecycle request stores `successor_agreement_id`, and
`agreement_relationships` links source to successor with `RENEWAL` or
`AMENDMENT`. Successor version 1 contains immutable lifecycle provenance, and
the Agreement detail page shows lineage in both directions. A database uniqueness
constraint prevents one request from producing multiple successors.

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

For a completed renewal or amendment, the decision response and later request
reads include `successor_agreement_id`. Terminations return it as `null`.
## Secure request documents

Lifecycle requests have a private document collection separate from the
Agreement's own documents. Every new file is linked to the latest immutable
lifecycle-request version at upload time.

Supported types are request form, supporting evidence, proposed amendment,
renewal evidence, termination evidence, Legal review, Finance review,
President decision, and other. PDF, DOC, and DOCX files are accepted up to
10 MB and use the same signature, MIME, macro, filename, checksum, and private
storage controls as Agreement documents.

Access is deliberately narrower than lifecycle-record visibility:

- the requester can read files throughout the request and upload/delete their
  own files only in `DRAFT` or `REVISION_REQUIRED`;
- the exact active reviewer can read files and upload/delete their own files;
- reviewer access ends immediately when the assignment closes;
- a System Administrator can manage files for recovery and support;
- no storage key or server path is included in API responses.

Routes:

- `GET /agreement-lifecycle-requests/{id}/documents`
- `POST /agreement-lifecycle-requests/{id}/documents`
- `GET /lifecycle-request-documents/{id}/download`
- `DELETE /lifecycle-request-documents/{id}`
